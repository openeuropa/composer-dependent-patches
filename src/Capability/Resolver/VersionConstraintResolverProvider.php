<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatching\Capability\Resolver;

use cweagans\Composer\Capability\Resolver\CoreResolverProvider;
use OpenEuropa\ComposerDependentPatching\Resolver\Dependencies;
use OpenEuropa\ComposerDependentPatching\Resolver\RootComposer;

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
