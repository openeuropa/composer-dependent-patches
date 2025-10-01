<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches;

use cweagans\Composer\Resolver as ComposerPatchesResolver;
use OpenEuropa\ComposerDependentPatches\Resolver\Dependencies;
use OpenEuropa\ComposerDependentPatches\Resolver\RootComposer;

class Resolver extends ComposerPatchesResolver
{
    /**
     * @var Plugin
     */
    protected Plugin $plugin;

    /**
     * Constructor.
     */
    public function __construct($composer, $io, $config, Plugin $plugin)
    {
        parent::__construct($composer, $io, $config);
        $this->plugin = $plugin;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPatchResolvers(): array
    {
        // Make sure that this plugin only uses the resolvers returned here.
        return [
            new RootComposer($this->composer, $this->io, $this->plugin),
            new Dependencies($this->composer, $this->io, $this->plugin),
        ];
    }
}
