<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Resolver;

use cweagans\Composer\Patch;
use cweagans\Composer\PatchCollection;

class Dependencies extends VersionConstraintResolverBase
{

  /**
   * {@inheritdoc}
   */
  public function resolve(PatchCollection $collection): void
  {
    $locked_packages = $this->getLockedPackages();
    if (empty($locked_packages)) {
      $this->io->write('  - <info>Found no locked packages.</info>');
      $this->io->write('  - <info>Patches defined in dependencies will not be resolved.</info>');
      return;
    }

    $this->io->write('  - <info>[composer-dependent-patches] Resolving patches from dependencies.</info>');

    $ignored_dependencies = $this->plugin->getConfig('ignore-dependency-patches');
    foreach ($locked_packages as $locked_package) {
      // Validate that the locked package has a name key.
      if (!isset($locked_package['name']) || !is_string($locked_package['name'])) {
        continue;
      }

      if (in_array($locked_package['name'], $ignored_dependencies)) {
        // Skip gathering patches from ignored dependency.
        continue;
      }

      if ($patch_definitions = $this->getPatchDefinitions($locked_package['name'])) {
        foreach ($this->findPatchesInJson($patch_definitions) as $package_name => $patches) {
          /** @var Patch $patch */
          foreach ($patches as $patch) {
            if ($this->validateVersionConstraint($patch)) {
              $patch->extra['provenance'] = "dependency:$package_name";
              $collection->addPatch($patch);
            }
          }
        }
      }
    }
  }
}
