<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Resolver;

use Composer\Semver\VersionParser;
use cweagans\Composer\Patch;
use cweagans\Composer\Resolver\ResolverBase;

abstract class VersionConstraintResolverBase extends ResolverBase implements VersionConstraintResolverInterface
{

  /**
   * @var array $lockedPackages
   */
  protected array $lockedPackages = [];

  /**
   * {@inheritdoc}
   */
  public function getPatchDefinitions(?string $package_name = NULL): array {
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
  public function getLockedPackages(): array {
    if (empty($this->lockedPackages)) {
      $locker = $this->composer->getLocker();
      if ($locker->isLocked()) {
        $this->lockedPackages = $locker->getLockData()['packages'] ?? [];
      }
    }

    return $this->lockedPackages;
  }

  /**
   * {@inheritdoc}
   */
  public function getLockedPackage(string $name): ?array {
    if ($packages = $this->getLockedPackages()) {
      $key = array_search($name, array_column($packages, 'name'));
      return $packages[$key] ?? NULL;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function validateVersionConstraint(Patch $patch): bool {
    $constraints = $patch->extra['version'] ?? NULL;
    if (empty($constraints)) {
      // No version constraints were specified for the patch.
      return TRUE;
    }

    $package_version = $this->getLockedPackage($patch->package)['version'] ?? NULL;
    if ($package_version) {
      // Check if the patch constraint matches the locked package version.
      $parser = new VersionParser();
      $constraint = $parser->parseConstraints($constraints);
      $provided = $parser->parseConstraints($package_version);
      return $constraint->matches($provided);
    }

    // Cannot validate patch constraint if the locked version of the package to
    // be patched is not available.
    return FALSE;
  }

}
