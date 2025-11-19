<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use cweagans\Composer\Resolver as ComposerPatchesResolver;
use OpenEuropa\ComposerDependentPatches\Resolver\Dependencies;
use OpenEuropa\ComposerDependentPatches\Resolver\RootComposer;

class Resolver extends ComposerPatchesResolver
{
    /**
     * @var PluginInterface
     */
    protected PluginInterface $plugin;

    /**
     * Constructor.
     */
    public function __construct(Composer $composer, IOInterface $io, array $disabledResolvers, PluginInterface $plugin)
    {
        parent::__construct($composer, $io, $disabledResolvers);
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
