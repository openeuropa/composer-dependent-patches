<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Tests;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginManager;
use cweagans\Composer\Resolver as ComposerPatchesResolver;
use Mockery;
use OpenEuropa\ComposerDependentPatches\Plugin;
use OpenEuropa\ComposerDependentPatches\Resolver;
use OpenEuropa\ComposerDependentPatches\Resolver\Dependencies;
use OpenEuropa\ComposerDependentPatches\Resolver\RootComposer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for the Resolver class.
 */
class ResolverTest extends TestCase
{
    private Resolver $resolver;
    private Composer $composer;
    private IOInterface $io;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        $this->composer = Mockery::mock(Composer::class);
        $this->io = Mockery::mock(IOInterface::class);
        
        $this->resolver = new Resolver($this->composer, $this->io, []);
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Test that getPatchResolvers returns the correct resolver instances.
     */
    public function testGetPatchResolversReturnsCorrectResolvers(): void
    {
        $mockPluginManager = Mockery::mock(PluginManager::class);
        $mockPlugin = Mockery::mock(Plugin::class);
        
        $this->composer->shouldReceive('getPluginManager')
            ->twice() // Called twice: once for RootComposer, once for Dependencies.
            ->andReturn($mockPluginManager);
            
        $mockPluginManager->shouldReceive('getPlugins')
            ->twice()
            ->andReturn([$mockPlugin]);
        
        // Use reflection to access protected method.
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('getPatchResolvers');
        $method->setAccessible(true);
        
        $resolvers = $method->invoke($this->resolver);
        
        $this->assertIsArray($resolvers);
        $this->assertCount(2, $resolvers);
        $this->assertInstanceOf(RootComposer::class, $resolvers[0]);
        $this->assertInstanceOf(Dependencies::class, $resolvers[1]);
    }

    /**
     * Test that getPluginInstance returns the correct plugin instance.
     */
    public function testGetPluginInstanceReturnsPlugin(): void
    {
        $mockPluginManager = Mockery::mock(PluginManager::class);
        $mockPlugin = Mockery::mock(Plugin::class);
        $mockOtherPlugin = Mockery::mock(PluginInterface::class);
        
        $this->composer->shouldReceive('getPluginManager')
            ->once()
            ->andReturn($mockPluginManager);
            
        $mockPluginManager->shouldReceive('getPlugins')
            ->once()
            ->andReturn([$mockOtherPlugin, $mockPlugin]);
        
        // Use reflection to access protected method.
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('getPluginInstance');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->resolver);
        
        $this->assertInstanceOf(Plugin::class, $result);
        $this->assertSame($mockPlugin, $result);
    }

    /**
     * Test that getPluginInstance throws exception when no plugin is found.
     */
    public function testGetPluginInstanceThrowsExceptionWhenNoPluginFound(): void
    {
        $mockPluginManager = Mockery::mock(PluginManager::class);
        $mockOtherPlugin = Mockery::mock(PluginInterface::class);
        
        $this->composer->shouldReceive('getPluginManager')
            ->once()
            ->andReturn($mockPluginManager);
            
        $mockPluginManager->shouldReceive('getPlugins')
            ->once()
            ->andReturn([$mockOtherPlugin]);
        
        // Use reflection to access protected method.
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('getPluginInstance');
        $method->setAccessible(true);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Plugin instance not found. Make sure the plugin is properly activated.');
        
        $method->invoke($this->resolver);
    }

    /**
     * Test that the resolver extends the correct base class.
     */
    public function testResolverExtendsCorrectBaseClass(): void
    {
        $this->assertInstanceOf(ComposerPatchesResolver::class, $this->resolver);
    }
}