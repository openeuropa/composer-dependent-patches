<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Tests;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
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
    private Plugin $plugin;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        $this->composer = Mockery::mock(Composer::class);
        $this->io = Mockery::mock(IOInterface::class);
        $this->plugin = Mockery::mock(Plugin::class);

        $this->resolver = new Resolver($this->composer, $this->io, [], $this->plugin);
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
     * Test that the plugin instance is properly stored.
     */
    public function testPluginInstanceIsStored(): void
    {
        // Use reflection to access protected property.
        $reflection = new ReflectionClass($this->resolver);
        $property = $reflection->getProperty('plugin');
        $property->setAccessible(true);

        $result = $property->getValue($this->resolver);

        $this->assertInstanceOf(PluginInterface::class, $result);
        $this->assertSame($this->plugin, $result);
    }

    /**
     * Test that the resolver extends the correct base class.
     */
    public function testResolverExtendsCorrectBaseClass(): void
    {
        $this->assertInstanceOf(ComposerPatchesResolver::class, $this->resolver);
    }
}
