<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatching\Resolver;

use cweagans\Composer\Patch;
use cweagans\Composer\PatchCollection;

class RootComposer extends VersionConstraintResolverBase
{

  /**
   * {@inheritdoc}
   */
  public function resolve(PatchCollection $collection): void
  {
    if ($patch_definitions = $this->getPatchDefinitions()) {
      $this->io->write('  - <info>[composer-dependent-patching] Resolving patches from root package.</info>');
      foreach ($this->findPatchesInJson($patch_definitions) as $patches) {
        /** @var Patch $patch */
        foreach ($patches as $patch) {
          if ($this->validateVersionConstraint($patch)) {
            $patch->extra['provenance'] = 'root';
            $collection->addPatch($patch);
          }
        }
      }
    }
  }

}
