<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Resolver;

use Composer\Semver\VersionParser;
use cweagans\Composer\Patch;
use cweagans\Composer\Resolver\ResolverBase;

abstract class VersionConstraintResolverBase extends ResolverBase implements VersionConstraintResolverInterface
{
    /**
     * @var array|null $lockedPackages
     */
    protected ?array $lockedPackages = null;

    /**
     * {@inheritdoc}
     */
    public function getPatchDefinitions(?string $package_name = null): array
    {
        $extra = $package_name
        ? $this->getLockedPackage($package_name)['extra'] ?? []
        : $this->composer->getPackage()->getExtra();

        // @todo Using a special extra key to store the patch definitions is due to
        //   difficulties with modifying the patch collection that composer-patches
        //   applies, while covering all use cases at the same time.
        //   Ideally, the version constrained patches should also be defined in
        //   extra.patches, but for the time being they need to be separated from
        //   regular patches.
        $definitions = $extra['dependent-patches'] ?? [];
        return is_array($definitions) ? $definitions : [];
    }

    /**
     * {@inheritdoc}
     */
    public function getLockedPackages(): array
    {
        if ($this->lockedPackages === null) {
            $locker = $this->composer->getLocker();
            if ($locker->isLocked()) {
                $this->lockedPackages = $locker->getLockData()['packages'] ?? [];
            } else {
                $this->lockedPackages = [];
            }
        }

        return $this->lockedPackages;
    }

    /**
     * {@inheritdoc}
     */
    public function getLockedPackage(string $name): ?array
    {
        $packages = $this->getLockedPackages();
        if (empty($packages)) {
            return null;
        }

        $key = array_search($name, array_column($packages, 'name'));
        if ($key === false) {
            return null;
        }

        return $packages[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function validateVersionConstraint(Patch $patch): bool
    {
        $constraints = $patch->extra['version'] ?? null;
        if (empty($constraints)) {
            // No version constraints were specified for the patch.
            return true;
        }

        $lockedPackage = $this->getLockedPackage($patch->package);
        if ($lockedPackage === null || !isset($lockedPackage['version'])) {
            // Cannot validate patch constraint if the locked version of the package to
            // be patched is not available.
            return false;
        }

        $package_version = $lockedPackage['version'];
        if (!is_string($package_version) || empty($package_version)) {
            return false;
        }

        // Check if the patch constraint matches the locked package version.
        $parser = new VersionParser();
        try {
            $constraint = $parser->parseConstraints($constraints);
            $provided = $parser->parseConstraints($package_version);
            return $constraint->matches($provided);
        } catch (\Exception $e) {
            // If version parsing fails, skip the patch to avoid errors.
            return false;
        }
    }
}
