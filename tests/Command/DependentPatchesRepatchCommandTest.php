<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Tests\Command;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginManager;
use Composer\Repository\RepositoryManager;
use Composer\Repository\InstalledRepositoryInterface;
use cweagans\Composer\PatchCollection;
use Mockery;
use OpenEuropa\ComposerDependentPatches\Command\DependentPatchesRepatchCommand;
use OpenEuropa\ComposerDependentPatches\Plugin;
use PHPUnit\Framework\TestCase;
use React\Promise\Promise;
use Composer\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the DependentPatchesRepatchCommand class.
 */
class DependentPatchesRepatchCommandTest extends TestCase
{
    private DependentPatchesRepatchCommand $command;
    private Composer $composer;
    private IOInterface $io;
    private Plugin $plugin;
    private PluginManager $pluginManager;
    private RepositoryManager $repositoryManager;
    private InstalledRepositoryInterface $localRepository;
    private InstallationManager $installationManager;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        $this->composer = Mockery::mock(Composer::class);
        $this->io = Mockery::mock(IOInterface::class);
        $this->plugin = Mockery::mock(Plugin::class);
        $this->pluginManager = Mockery::mock(PluginManager::class);
        $this->repositoryManager = Mockery::mock(RepositoryManager::class);
        $this->localRepository = Mockery::mock(InstalledRepositoryInterface::class);
        $this->installationManager = Mockery::mock(InstallationManager::class);

        $this->command = new DependentPatchesRepatchCommand();

        // Set up application context
        $application = new Application();
        $application->add($this->command);

        // Mock composer access
        $this->command->setComposer($this->composer);
        $this->command->setIO($this->io);

        $this->composer->shouldReceive('getPluginManager')
            ->andReturn($this->pluginManager);

        $this->composer->shouldReceive('getRepositoryManager')
            ->andReturn($this->repositoryManager);

        $this->repositoryManager->shouldReceive('getLocalRepository')
            ->andReturn($this->localRepository);

        $this->composer->shouldReceive('getInstallationManager')
            ->andReturn($this->installationManager);

        // Mock event dispatcher to avoid test errors
        $eventDispatcher = Mockery::mock(EventDispatcher::class);
        $eventDispatcher->shouldReceive('dispatch')->zeroOrMoreTimes();
        $this->composer->shouldReceive('getEventDispatcher')
            ->andReturn($eventDispatcher);
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Test command configuration.
     */
    public function testCommandConfiguration(): void
    {
        $this->assertEquals('dependent-patches-repatch', $this->command->getName());
        $this->assertContains('dprp', $this->command->getAliases());
        $this->assertStringContainsString('re-patch each dependency', $this->command->getDescription());
    }

    /**
     * Test execution when plugin is not found.
     */
    public function testExecutePluginNotFound(): void
    {
        $this->pluginManager->shouldReceive('getPlugins')
            ->andReturn([]);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('plugin not found', $commandTester->getDisplay());
    }

    /**
     * Test execution with no patch collection.
     */
    public function testExecuteNoPatchCollection(): void
    {
        $this->pluginManager->shouldReceive('getPlugins')
            ->andReturn([$this->plugin]);

        $this->plugin->shouldReceive('loadLockedPatches')
            ->once();

        $this->plugin->shouldReceive('getPatchCollection')
            ->andReturn(null);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('No dependent patches', $commandTester->getDisplay());
    }

    /**
     * Test execution with no patched packages.
     */
    public function testExecuteNoPatchedPackages(): void
    {
        $patchCollection = Mockery::mock(PatchCollection::class);

        $this->pluginManager->shouldReceive('getPlugins')
            ->andReturn([$this->plugin]);

        $this->plugin->shouldReceive('loadLockedPatches')
            ->once();

        $this->plugin->shouldReceive('getPatchCollection')
            ->andReturn($patchCollection);

        $patchCollection->shouldReceive('getPatchedPackages')
            ->andReturn([]);

        $this->localRepository->shouldReceive('getPackages')
            ->andReturn([]);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('No packages with dependent patches found', $commandTester->getDisplay());
    }

    /**
     * Test successful repatch execution.
     */
    public function testExecuteSuccessfulRepatch(): void
    {
        $patchCollection = Mockery::mock(PatchCollection::class);
        $package1 = Mockery::mock(PackageInterface::class);
        $package2 = Mockery::mock(PackageInterface::class);

        $this->pluginManager->shouldReceive('getPlugins')
            ->andReturn([$this->plugin]);

        $this->plugin->shouldReceive('loadLockedPatches')
            ->once();

        $this->plugin->shouldReceive('getPatchCollection')
            ->andReturn($patchCollection);

        // Mock patch collection returning patched package names
        $patchCollection->shouldReceive('getPatchedPackages')
            ->andReturn(['vendor/package1', 'vendor/package2']);

        // Mock packages in local repository
        $package1->shouldReceive('getName')
            ->andReturn('vendor/package1');
        $package2->shouldReceive('getName')
            ->andReturn('vendor/package2');

        $this->localRepository->shouldReceive('getPackages')
            ->andReturn([$package1, $package2]);

        // Mock uninstallation operations - return null (valid PromiseInterface return)
        $this->installationManager->shouldReceive('uninstall')
            ->with($this->localRepository, Mockery::type(UninstallOperation::class))
            ->twice()
            ->andReturnNull();

        // Mock composer loop (simplified) - should receive empty array after filtering nulls
        $loop = Mockery::mock('React\EventLoop\LoopInterface');
        $this->composer->shouldReceive('getLoop')->andReturn($loop);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Removing packages', $commandTester->getDisplay());
        $this->assertStringContainsString('Re-installing packages', $commandTester->getDisplay());
    }

    /**
     * Test execution with empty promises array.
     */
    public function testExecuteEmptyPromises(): void
    {
        $patchCollection = Mockery::mock(PatchCollection::class);
        $package = Mockery::mock(PackageInterface::class);

        $this->pluginManager->shouldReceive('getPlugins')
            ->andReturn([$this->plugin]);

        $this->plugin->shouldReceive('loadLockedPatches')
            ->once();

        $this->plugin->shouldReceive('getPatchCollection')
            ->andReturn($patchCollection);

        $patchCollection->shouldReceive('getPatchedPackages')
            ->andReturn(['vendor/package']);

        $package->shouldReceive('getName')
            ->andReturn('vendor/package');

        $this->localRepository->shouldReceive('getPackages')
            ->andReturn([$package]);

        // Mock uninstallation returning null (filtered out)
        $this->installationManager->shouldReceive('uninstall')
            ->andReturn(null);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Re-installing packages', $commandTester->getDisplay());
    }
}
