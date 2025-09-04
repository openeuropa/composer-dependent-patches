<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches;

use Composer\Installer\PackageEvent;
use cweagans\Composer\Downloader;
use cweagans\Composer\PatchCollection;
use cweagans\Composer\Plugin\Patches;

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
   * {@inheritdoc}
   */
  public function getCapabilities(): array
  {
    return [];
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
    $this->locker->setLockData($this->dependentPatchCollection);
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

    $this->dependentPatchCollection = PatchCollection::fromJson($this->locker->getLockData());
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
  public function resolvePatches(): PatchCollection {
    $resolver = new Resolver($this->composer, $this->io, []);
    return $resolver->loadFromResolvers();
  }

  /**
   * {@inheritdoc}
   */
  public function patchPackage(PackageEvent $event): void {
    $package = $this->getPackageFromOperation($event->getOperation());
    if ($package->getName() === 'openeuropa/composer-dependent-patches') {
      // See comment in parent method for explanation.
      return;
    }

    $this->patchCollection = $this->dependentPatchCollection;
    parent::patchPackage($event);
  }

}
