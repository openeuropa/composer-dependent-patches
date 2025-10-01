<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches;

use Composer\Installer\PackageEvent;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use cweagans\Composer\Downloader;
use cweagans\Composer\PatchCollection;
use cweagans\Composer\Plugin\Patches;
use OpenEuropa\ComposerDependentPatches\Capability\CommandProvider;

/**
 * Composer plugin providing support for patch version constraints.
 */
class Plugin extends Patches
{
    /**
     * @var ?PatchCollection $patchCollection
     */
    protected ?PatchCollection $dependentPatchCollection;

    /**
     * @var bool Flag to track if we've already regenerated patches in this process
     */
    protected bool $patchesRegenerated = false;

    /**
     * {@inheritdoc}
     */
    public function getCapabilities(): array
    {
        return [
            CommandProviderCapability::class => CommandProvider::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function createNewPatchesLock(): void
    {
        $this->dependentPatchCollection = $this->resolvePatches();
        $downloader = new Downloader($this->composer, $this->io, $this->getConfig('disable-downloaders'));
        foreach ($this->dependentPatchCollection->getPatchedPackages() as $package) {
            foreach ($this->dependentPatchCollection->getPatchesForPackage($package) as $patch) {
                $this->download($patch);
                $this->guessDepth($patch);
            }
        }
        $this->setLockDataWithPackageVersions($this->dependentPatchCollection);
        $this->patchesRegenerated = true;
    }

    /**
     * {@inheritdoc}
     */
    public function loadLockedPatches(): void
    {
        $locked = $this->locker->isLocked();
        if (!$locked) {
            $filename = pathinfo($this->getLockFile()->getPath(), \PATHINFO_BASENAME);
            $this->io->write("<warning>$filename does not exist. Creating a new $filename.</warning>");
            $this->createNewPatchesLock();
            return;
        }

        if ($this->hasPackageVersionsChanged()) {
            $filename = pathinfo($this->getLockFile()->getPath(), \PATHINFO_BASENAME);
            $this->io->write("<warning>Package versions have changed since $filename was created. Regenerating lock file.</warning>");
            $this->createNewPatchesLock();
            return;
        }

        if (!isset($this->dependentPatchCollection)) {
            $this->dependentPatchCollection = PatchCollection::fromJson($this->locker->getLockData());
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getPatchesLockFilePath(): string
    {
        $composer_file = \Composer\Factory::getComposerFile();

        $realpath = realpath($composer_file);
        if ($realpath === false) {
            throw new \RuntimeException("Unable to resolve real path for composer file: $composer_file");
        }

        $dir = dirname($realpath);
        $base = pathinfo($composer_file, \PATHINFO_FILENAME);

        if ($base === 'composer') {
            return "$dir/dependent-patches.lock.json";
        }

        return "$dir/$base-dependent-patches.lock.json";
    }

    /**
     * {@inheritdoc}
     */
    public function resolvePatches(): PatchCollection
    {
        $resolver = new Resolver($this->composer, $this->io, [], $this);
        return $resolver->loadFromResolvers();
    }

    /**
     * Override to return dependent patch collection.
     */
    public function getPatchCollection(): ?PatchCollection
    {
        // Ensure dependent patches are loaded
        if (!isset($this->dependentPatchCollection)) {
            $this->loadLockedPatches();
        }

        return $this->dependentPatchCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function patchPackage(PackageEvent $event): void
    {
        $package = $this->getPackageFromOperation($event->getOperation());
        if ($package->getName() === 'openeuropa/composer-dependent-patches') {
            // See comment in parent method for explanation.
            return;
        }

        // Ensure dependent patches are loaded
        if (!isset($this->dependentPatchCollection)) {
            $this->loadLockedPatches();
        }

        $this->patchCollection = $this->dependentPatchCollection;
        parent::patchPackage($event);
    }

    /**
     * Check if package versions have changed since the dependent patches lock was created.
     * Only returns true once per process to avoid multiple regenerations.
     */
    protected function hasPackageVersionsChanged(): bool
    {
        // If we've already regenerated patches in this process, don't do it again
        if ($this->patchesRegenerated) {
            return false;
        }

        try {
            $lockData = $this->locker->getLockData();
        } catch (\Exception $e) {
            return true;
        }

        if (!isset($lockData['_composer_lock_hash'])) {
            return true;
        }

        return $lockData['_composer_lock_hash'] !== $this->getComposerLockHash();
    }

    /**
     * Get the content hash of the current composer.lock file.
     */
    protected function getComposerLockHash(): ?string
    {
        $composerLocker = $this->composer->getLocker();
        if (!$composerLocker->isLocked()) {
            return null;
        }

        $composerLockData = $composerLocker->getLockData();
        return $composerLockData['content-hash'] ?? null;
    }

    /**
     * Set lock data with composer.lock hash included.
     */
    protected function setLockDataWithPackageVersions(PatchCollection $patchCollection): bool
    {
        $lock = $patchCollection->jsonSerialize();
        $lock['_hash'] = $this->locker->getCollectionHash($patchCollection);
        $lock['_composer_lock_hash'] = $this->getComposerLockHash();
        ksort($lock);

        $lockFile = $this->getLockFile();
        $lockFile->write($lock);

        return true;
    }
}
