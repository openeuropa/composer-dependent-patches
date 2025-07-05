<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Resolver;

use cweagans\Composer\Patch;

interface VersionConstraintResolverInterface
{

  /**
   * Gets the patches defined in a package extra for this plugin.
   *
   * @param string|null $package_name
   *   Name of the package. Optional, if omitted the root package is used.
   *
   * @return array
   *   An array of patch definitions keyed by the package names.
   */
  public function getPatchDefinitions(?string $package_name = NULL): array;

  /**
   * Gets locked packages.
   *
   * @return array
   *   An array of locked packages.
   */
  public function getLockedPackages(): array;


  /**
   * Gets locked package.
   *
   * @param string $name
   *    The package name.
   *
   * @return array|null
   *   An associative array of package data or NULL.
   */
  public function getLockedPackage(string $name): ?array;

  /**
   * Resolves version constraints for patches.
   *
   * @param Patch $patch
   *   Patch to validate version constraint for.
   * 
   * @return bool
   *   TRUE if the patch matches the version constraint FALSE otherwise.
   */
  public function validateVersionConstraint(Patch $patch): bool;

}
