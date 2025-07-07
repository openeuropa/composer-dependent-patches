<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches;

use cweagans\Composer\Resolver as ComposerPatchesResolver;
use OpenEuropa\ComposerDependentPatches\Resolver\Dependencies;
use OpenEuropa\ComposerDependentPatches\Resolver\RootComposer;

class Resolver extends ComposerPatchesResolver {

  /**
   * {@inheritdoc}
   */
  protected function getPatchResolvers(): array
  {
    // Make sure that this plugin only uses the resolvers returned here.
    return [
      new RootComposer($this->composer, $this->io, $this->getPluginInstance()),
      new Dependencies($this->composer, $this->io, $this->getPluginInstance()),
    ];
  }

  /**
   * Get instance of this plugin.
   *
   * @return Plugin|null
   */
  protected function getPluginInstance(): ?Plugin
  {
    foreach ($this->composer->getPluginManager()->getPlugins() as $plugin) {
      if ($plugin instanceof Plugin) {
        return $plugin;
      }
    }

    return NULL;
  }
}
