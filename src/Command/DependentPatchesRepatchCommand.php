<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Command;

use Composer\Command\BaseCommand;
use Composer\DependencyResolver\Operation\UninstallOperation;
use OpenEuropa\ComposerDependentPatches\Plugin;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to repatch dependent patches.
 */
class DependentPatchesRepatchCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('dependent-patches-repatch');
        $this->setDescription('Delete, re-download, and re-patch each dependency with any dependent patches defined.');
        $this->setAliases(['dprp']);
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

        $plugin->loadLockedPatches();
        $patchCollection = $plugin->getPatchCollection();
        if (is_null($patchCollection)) {
            $output->writeln('<info>No dependent patches to reapply.</info>');
            return 0;
        }

        $localRepository = $this->requireComposer()
            ->getRepositoryManager()
            ->getLocalRepository();

        $patched_packages = $patchCollection->getPatchedPackages();
        $packages = array_filter($localRepository->getPackages(), function ($val) use ($patched_packages) {
            return in_array($val->getName(), $patched_packages);
        });

        if (empty($packages)) {
            $output->writeln('<info>No packages with dependent patches found to reapply.</info>');
            return 0;
        }

        $output->writeln('<info>Removing packages with dependent patches...</info>');
        // Remove patched packages so that we can re-install/re-patch.
        $promises = [];
        foreach ($packages as $package) {
            $output->writeln("  - Removing {$package->getName()}");
            $uninstallOperation = new UninstallOperation($package);
            $promises[] = $this->requireComposer()
                ->getInstallationManager()
                ->uninstall($localRepository, $uninstallOperation);
        }
        // Wait for uninstalls to finish.
        $promises = array_filter($promises);
        if (!empty($promises)) {
            $this->requireComposer()->getLoop()->wait($promises);
        }

        $output->writeln('<info>Re-installing packages with dependent patches...</info>');
        $input = new ArrayInput(['command' => 'install']);
        $application = $this->getApplication();
        $application->setAutoExit(false);
        $application->run($input, $output);

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
