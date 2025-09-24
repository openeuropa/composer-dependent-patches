<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Capability;

use Composer\Plugin\Capability\CommandProvider as CommandProviderInterface;
use OpenEuropa\ComposerDependentPatches\Command\DependentPatchesRelockCommand;
use OpenEuropa\ComposerDependentPatches\Command\DependentPatchesRepatchCommand;

class CommandProvider implements CommandProviderInterface
{
    public function getCommands(): array
    {
        return [
            new DependentPatchesRelockCommand(),
            new DependentPatchesRepatchCommand(),
        ];
    }
}
