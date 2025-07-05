<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Capability\Resolver;

use cweagans\Composer\Capability\Resolver\CoreResolverProvider;
use OpenEuropa\ComposerDependentPatches\Resolver\Dependencies;
use OpenEuropa\ComposerDependentPatches\Resolver\RootComposer;

class VersionConstraintResolverProvider extends CoreResolverProvider
{

  /**
   * @{inheritdoc}
   */
  public function getResolvers(): array
  {
    // @todo A PatchesFile version constraint resolver is not implemented.
    //   Version constrained patches can only be defined in composer.json.
    return [
      new RootComposer($this->composer, $this->io, $this->plugin),
      new Dependencies($this->composer, $this->io, $this->plugin),
    ];
  }

}
