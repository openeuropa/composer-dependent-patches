<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Command;

use Composer\Command\BaseCommand;
use OpenEuropa\ComposerDependentPatches\Plugin;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to relock dependent patches lock file.
 */
class DependentPatchesRelockCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('dependent-patches-relock');
        $filename = pathinfo(Plugin::getPatchesLockFilePath(), \PATHINFO_BASENAME);
        $this->setDescription("Find all dependent patches defined in the project and re-write $filename.");
        $this->setAliases(['dprl']);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugin = $this->getDependentPatchesPluginInstance();
        if (is_null($plugin)) {
            $output->writeln('<error>Dependent patches plugin not found.</error>');
            return 1;
        }

        if (file_exists($plugin->getLockFile()->getPath())) {
            unlink($plugin->getLockFile()->getPath());
        }
        $plugin->createNewPatchesLock();
        $filename = pathinfo($plugin->getLockFile()->getPath(), \PATHINFO_BASENAME);
        $output->write("  - <info>$filename</info> has been recreated successfully.", true);
        return 0;
    }

    /**
     * Get the Dependent Patches plugin
     */
    protected function getDependentPatchesPluginInstance(): ?Plugin
    {
        foreach ($this->requireComposer()->getPluginManager()->getPlugins() as $plugin) {
            if ($plugin instanceof Plugin) {
                return $plugin;
            }
        }

        return null;
    }
}
