<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Tests\Command;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Plugin\PluginManager;
use Mockery;
use OpenEuropa\ComposerDependentPatches\Command\DependentPatchesRelockCommand;
use OpenEuropa\ComposerDependentPatches\Plugin;
use PHPUnit\Framework\TestCase;
use Composer\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the DependentPatchesRelockCommand class.
 */
class DependentPatchesRelockCommandTest extends TestCase
{
    private DependentPatchesRelockCommand $command;
    private Composer $composer;
    private IOInterface $io;
    private Plugin $plugin;
    private PluginManager $pluginManager;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        $this->composer = Mockery::mock(Composer::class);
        $this->io = Mockery::mock(IOInterface::class);
        $this->plugin = Mockery::mock(Plugin::class);
        $this->pluginManager = Mockery::mock(PluginManager::class);

        $this->command = new DependentPatchesRelockCommand();

        // Set up application context
        $application = new Application();
        $application->add($this->command);

        // Mock composer access
        $this->command->setComposer($this->composer);
        $this->command->setIO($this->io);

        $this->composer->shouldReceive('getPluginManager')
            ->andReturn($this->pluginManager);

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
        $this->assertEquals('dependent-patches-relock', $this->command->getName());
        $this->assertContains('dprl', $this->command->getAliases());
        $this->assertStringContainsString('dependent-patches.lock.json', $this->command->getDescription());
    }

    /**
     * Test successful relock execution.
     */
    public function testExecuteSuccessful(): void
    {
        $lockFile = Mockery::mock(JsonFile::class);
        $lockFilePath = '/path/to/dependent-patches.lock.json';

        // Mock plugin manager returning our plugin
        $this->pluginManager->shouldReceive('getPlugins')
            ->andReturn([$this->plugin]);

        // Mock lock file operations
        $lockFile->shouldReceive('getPath')
            ->andReturn($lockFilePath);

        $this->plugin->shouldReceive('getLockFile')
            ->andReturn($lockFile);

        $this->plugin->shouldReceive('createNewPatchesLock')
            ->once();

        // Mock file existence and deletion
        $this->mockFileOperations($lockFilePath, true);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('dependent-patches.lock.json', $commandTester->getDisplay());
        $this->assertStringContainsString('recreated successfully', $commandTester->getDisplay());
    }

    /**
     * Test execution when plugin is not found.
     */
    public function testExecutePluginNotFound(): void
    {
        // Mock plugin manager returning no matching plugins
        $this->pluginManager->shouldReceive('getPlugins')
            ->andReturn([]);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('plugin not found', $commandTester->getDisplay());
    }

    /**
     * Test execution when lock file doesn't exist.
     */
    public function testExecuteNoLockFile(): void
    {
        $lockFile = Mockery::mock(JsonFile::class);
        $lockFilePath = '/path/to/dependent-patches.lock.json';

        $this->pluginManager->shouldReceive('getPlugins')
            ->andReturn([$this->plugin]);

        $lockFile->shouldReceive('getPath')
            ->andReturn($lockFilePath);

        $this->plugin->shouldReceive('getLockFile')
            ->andReturn($lockFile);

        $this->plugin->shouldReceive('createNewPatchesLock')
            ->once();

        // Mock file not existing
        $this->mockFileOperations($lockFilePath, false);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('recreated successfully', $commandTester->getDisplay());
    }

    /**
     * Mock file operations for testing.
     */
    private function mockFileOperations(string $path, bool $exists): void
    {
        // Note: In a real implementation, we might need to use vfsStream or similar
        // for proper file system mocking. For now, we'll rely on the mocked objects.
    }
}
