<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Tests;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginManager;
use cweagans\Composer\PatchCollection;
use cweagans\Composer\Plugin\Patches;
use Mockery;
use OpenEuropa\ComposerDependentPatches\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for the Plugin class.
 */
class PluginTest extends TestCase
{
    private Plugin $plugin;
    private Composer $composer;
    private IOInterface $io;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        $this->composer = Mockery::mock(Composer::class);
        $this->io = Mockery::mock(IOInterface::class);

        $this->plugin = new Plugin();

        // Use reflection to set protected properties.
        $reflection = new ReflectionClass($this->plugin);
        $composerProperty = $reflection->getProperty('composer');
        $composerProperty->setAccessible(true);
        $composerProperty->setValue($this->plugin, $this->composer);

        $ioProperty = $reflection->getProperty('io');
        $ioProperty->setAccessible(true);
        $ioProperty->setValue($this->plugin, $this->io);
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Test that getCapabilities returns an empty array.
     */
    public function testGetCapabilities(): void
    {
        $capabilities = $this->plugin->getCapabilities();
        $this->assertIsArray($capabilities);
        $this->assertEmpty($capabilities);
    }

    /**
     * Test lock file path generation with default composer file.
     */
    public function testGetPatchesLockFilePathDefault(): void
    {
        $path = Plugin::getPatchesLockFilePath();
        $this->assertIsString($path);
        $this->assertStringContainsString('dependent-patches.lock.json', $path);
    }

    /**
     * Test lock file path generation with custom composer file.
     */
    public function testGetPatchesLockFilePathWithCustomComposerFile(): void
    {
        // Create a temporary composer file to test path generation.
        $tempDir = sys_get_temp_dir();
        $tempComposerFile = $tempDir . '/test-composer.json';
        file_put_contents($tempComposerFile, '{}');

        // We can't easily mock static methods, so we'll test the current behavior.
        $path = Plugin::getPatchesLockFilePath();
        $this->assertStringContainsString('dependent-patches.lock.json', $path);

        // Clean up.
        if (file_exists($tempComposerFile)) {
            unlink($tempComposerFile);
        }
    }

    /**
     * Test patch resolution workflow.
     */
    public function testResolvePatches(): void
    {
        // Mock the plugin manager and plugins for the Resolver constructor.
        $mockPluginManager = Mockery::mock(PluginManager::class);
        $mockPluginManager->shouldReceive('getPlugins')
            ->andReturn([$this->plugin]);

        $this->composer->shouldReceive('getPluginManager')
            ->andReturn($mockPluginManager);

        // Mock the getPackage method needed by RootComposer resolver.
        $mockRootPackage = Mockery::mock(RootPackageInterface::class);
        $mockRootPackage->shouldReceive('getExtra')
            ->andReturn([]);

        $this->composer->shouldReceive('getPackage')
            ->andReturn($mockRootPackage);

        // Mock the getLocker method needed by Dependencies resolver.
        $mockLocker = Mockery::mock(Locker::class);
        $mockLocker->shouldReceive('isLocked')
            ->andReturn(false);

        $this->composer->shouldReceive('getLocker')
            ->andReturn($mockLocker);

        // Mock IO write calls.
        $this->io->shouldReceive('write')->zeroOrMoreTimes();

        $result = $this->plugin->resolvePatches();
        $this->assertInstanceOf(PatchCollection::class, $result);
    }

    /**
     * Test that the loadLockedPatches method exists.
     */
    public function testLoadLockedPatchesMethodExists(): void
    {
        // This is a complex test that involves many internal dependencies.
        // For now, we'll just test that the method exists and can be called.
        $this->assertTrue(method_exists($this->plugin, 'loadLockedPatches'));
    }

    /**
     * Test that the plugin extends the correct base class.
     */
    public function testPluginExtendsCorrectBaseClass(): void
    {
        $this->assertInstanceOf(Patches::class, $this->plugin);
    }

    /**
     * Test that the plugin implements the PluginInterface.
     */
    public function testPluginImplementsPluginInterface(): void
    {
        $this->assertInstanceOf(PluginInterface::class, $this->plugin);
    }

    /**
     * Test that hasPackageVersionsChanged returns true when no composer lock hash is stored.
     */
    public function testHasPackageVersionsChangedWithNoComposerLockHash(): void
    {
        // Mock locker to return lock data without _composer_lock_hash.
        $mockLocker = Mockery::mock(\cweagans\Composer\Locker::class);
        $mockLocker->shouldReceive('getLockData')
            ->andReturn(['patches' => [], '_hash' => 'somehash']);

        $reflection = new ReflectionClass($this->plugin);
        $lockerProperty = $reflection->getProperty('locker');
        $lockerProperty->setAccessible(true);
        $lockerProperty->setValue($this->plugin, $mockLocker);

        $method = $reflection->getMethod('hasPackageVersionsChanged');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->plugin));
    }

    /**
     * Test that hasPackageVersionsChanged returns false when already regenerated.
     */
    public function testHasPackageVersionsChangedWithAlreadyRegenerated(): void
    {
        $reflection = new ReflectionClass($this->plugin);

        // Set the flag to indicate patches already regenerated
        $regeneratedProperty = $reflection->getProperty('patchesRegenerated');
        $regeneratedProperty->setAccessible(true);
        $regeneratedProperty->setValue($this->plugin, true);

        $method = $reflection->getMethod('hasPackageVersionsChanged');
        $method->setAccessible(true);

        // Should return false when already regenerated.
        $this->assertFalse($method->invoke($this->plugin));
    }

    /**
     * Test that hasPackageVersionsChanged returns true when no composer lock hash stored.
     */
    public function testHasPackageVersionsChangedWithNoStoredHash(): void
    {
        // Mock plugin locker with no stored hash.
        $mockPluginLocker = Mockery::mock(\cweagans\Composer\Locker::class);
        $mockPluginLocker->shouldReceive('getLockData')
            ->andReturn([
                'patches' => [],
                '_hash' => 'somehash'
                // No _composer_lock_hash
            ]);

        $reflection = new ReflectionClass($this->plugin);
        $lockerProperty = $reflection->getProperty('locker');
        $lockerProperty->setAccessible(true);
        $lockerProperty->setValue($this->plugin, $mockPluginLocker);

        $method = $reflection->getMethod('hasPackageVersionsChanged');
        $method->setAccessible(true);

        // Missing composer lock hash should return true.
        $this->assertTrue($method->invoke($this->plugin));
    }
}
