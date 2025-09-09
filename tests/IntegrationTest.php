<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Tests;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use cweagans\Composer\PatchCollection;
use Mockery;
use OpenEuropa\ComposerDependentPatches\Plugin;
use OpenEuropa\ComposerDependentPatches\Resolver\Dependencies;
use OpenEuropa\ComposerDependentPatches\Resolver\RootComposer;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the complete patch resolution workflows.
 */
class IntegrationTest extends TestCase
{
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

        // Allow any write calls to avoid test failures.
        $this->io->shouldReceive('write')->zeroOrMoreTimes();
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Test root composer patch resolution with version constraints.
     */
    public function testRootComposerPatchResolutionWithVersionConstraints(): void
    {
        $resolver = new RootComposer($this->composer, $this->io, $this->plugin);

        // Mock root package with dependent patches.
        $mockRootPackage = Mockery::mock(RootPackageInterface::class);
        $patchDefinitions = [
            'vendor/package' => [
                [
                    'description' => 'Patch for version 1.x',
                    'url' => '/path/to/patch1.patch',
                    'extra' => ['version' => '^1.0']
                ],
                [
                    'description' => 'Patch for version 2.x',
                    'url' => '/path/to/patch2.patch',
                    'extra' => ['version' => '^2.0']
                ]
            ]
        ];

        $extraData = ['dependent-patches' => $patchDefinitions];

        $this->composer->shouldReceive('getPackage')
            ->once()
            ->andReturn($mockRootPackage);

        $mockRootPackage->shouldReceive('getExtra')
            ->once()
            ->andReturn($extraData);

        // Mock locked packages.
        $lockData = [
            'packages' => [
                ['name' => 'vendor/package', 'version' => '1.5.0'],
            ]
        ];

        $mockLocker = Mockery::mock(Locker::class);
        $mockLocker->shouldReceive('isLocked')
            ->once()
            ->andReturn(true);
        $mockLocker->shouldReceive('getLockData')
            ->once()
            ->andReturn($lockData);

        $this->composer->shouldReceive('getLocker')
            ->once()
            ->andReturn($mockLocker);

        // Resolve patches.
        $collection = new PatchCollection();
        $resolver->resolve($collection);

        // Should only include the patch for version ^1.0 since package is at 1.5.0.
        $patches = $collection->getPatchesForPackage('vendor/package');
        $this->assertCount(1, $patches);

        $patch = reset($patches);
        $this->assertEquals('Patch for version 1.x', $patch->description);
        $this->assertEquals('root', $patch->extra['provenance']);
    }

    /**
     * Test dependencies resolver with ignored packages configuration.
     */
    public function testDependenciesResolverWithIgnoredPackages(): void
    {
        $resolver = new Dependencies($this->composer, $this->io, $this->plugin);

        // Mock locked packages.
        $lockData = [
            'packages' => [
                ['name' => 'vendor/package1', 'version' => '1.0.0', 'extra' => [
                    'dependent-patches' => [
                        'target/package' => [
                            [
                                'description' => 'Test patch from dependency',
                                'url' => '/path/to/dep-patch.patch',
                                'extra' => ['version' => '^1.0']
                            ]
                        ]
                    ]
                ]
                ],
                ['name' => 'vendor/ignored', 'version' => '1.0.0'],
                ['name' => 'target/package', 'version' => '1.2.0'],
            ]
        ];

        $mockLocker = Mockery::mock(Locker::class);
        $mockLocker->shouldReceive('isLocked')
            ->once()
            ->andReturn(true);
        $mockLocker->shouldReceive('getLockData')
            ->once()
            ->andReturn($lockData);

        $this->composer->shouldReceive('getLocker')
            ->once()
            ->andReturn($mockLocker);

        // Mock plugin configuration.
        $this->plugin->shouldReceive('getConfig')
            ->once()
            ->with('ignore-dependency-patches')
            ->andReturn(['vendor/ignored']);

        // Resolve patches.
        $collection = new PatchCollection();
        $resolver->resolve($collection);

        // Should include patch from vendor/package1 but not from vendor/ignored.
        $patches = $collection->getPatchesForPackage('target/package');
        $this->assertCount(1, $patches);

        $patch = reset($patches);
        $this->assertEquals('Test patch from dependency', $patch->description);
        $this->assertEquals('dependency:target/package', $patch->extra['provenance']);
    }

    /**
     * Test dependencies resolver when no locked packages exist.
     */
    public function testDependenciesResolverWithNoLockedPackages(): void
    {
        $resolver = new Dependencies($this->composer, $this->io, $this->plugin);

        // Mock no locked packages.
        $mockLocker = Mockery::mock(Locker::class);
        $mockLocker->shouldReceive('isLocked')
            ->once()
            ->andReturn(false);

        $this->composer->shouldReceive('getLocker')
            ->once()
            ->andReturn($mockLocker);

        // Resolve patches.
        $collection = new PatchCollection();
        $resolver->resolve($collection);

        // Should be empty.
        $this->assertEmpty($collection->getPatchedPackages());
    }

    /**
     * Test version constraint validation with edge cases.
     */
    public function testVersionConstraintEdgeCases(): void
    {
        $resolver = new RootComposer($this->composer, $this->io, $this->plugin);

        // Mock root package with edge case version constraints.
        $mockRootPackage = Mockery::mock(RootPackageInterface::class);
        $patchDefinitions = [
            'vendor/package' => [
                [
                    'description' => 'Patch for exact version',
                    'url' => '/path/to/exact.patch',
                    'extra' => ['version' => '1.5.0']
                ],
                [
                    'description' => 'Patch for dev version',
                    'url' => '/path/to/dev.patch',
                    'extra' => ['version' => 'dev-main']
                ],
                [
                    'description' => 'Patch for version range',
                    'url' => '/path/to/range.patch',
                    'extra' => ['version' => '>=1.0,<2.0']
                ]
            ]
        ];

        $this->composer->shouldReceive('getPackage')
            ->once()
            ->andReturn($mockRootPackage);

        $mockRootPackage->shouldReceive('getExtra')
            ->once()
            ->andReturn(['dependent-patches' => $patchDefinitions]);

        // Mock locked packages.
        $lockData = [
            'packages' => [
                ['name' => 'vendor/package', 'version' => '1.5.0'],
            ]
        ];

        $mockLocker = Mockery::mock(Locker::class);
        $mockLocker->shouldReceive('isLocked')
            ->once()
            ->andReturn(true);
        $mockLocker->shouldReceive('getLockData')
            ->once()
            ->andReturn($lockData);

        $this->composer->shouldReceive('getLocker')
            ->once()
            ->andReturn($mockLocker);

        // Resolve patches.
        $collection = new PatchCollection();
        $resolver->resolve($collection);

        // Should include exact version match and range match, but not dev version.
        $patches = $collection->getPatchesForPackage('vendor/package');
        $this->assertCount(2, $patches);

        $descriptions = array_map(function ($patch) {
            return $patch->description;
        }, $patches);

        $this->assertContains('Patch for exact version', $descriptions);
        $this->assertContains('Patch for version range', $descriptions);
        $this->assertNotContains('Patch for dev version', $descriptions);
    }

    /**
     * Test patch collection integration with multiple packages.
     */
    public function testPatchCollectionIntegration(): void
    {
        // Test that patches are properly added to the collection.
        $rootResolver = new RootComposer($this->composer, $this->io, $this->plugin);

        // Mock root package with patches.
        $mockRootPackage = Mockery::mock(RootPackageInterface::class);
        $patchDefinitions = [
            'package1' => [
                [
                    'description' => 'Patch 1',
                    'url' => '/path/to/patch1.patch',
                    'extra' => ['version' => '^1.0']
                ]
            ],
            'package2' => [
                [
                    'description' => 'Patch 2',
                    'url' => '/path/to/patch2.patch',
                ]
            ]
        ];

        $this->composer->shouldReceive('getPackage')
            ->once()
            ->andReturn($mockRootPackage);

        $mockRootPackage->shouldReceive('getExtra')
            ->once()
            ->andReturn(['dependent-patches' => $patchDefinitions]);

        // Mock locked packages.
        $lockData = [
            'packages' => [
                ['name' => 'package1', 'version' => '1.0.0'],
                ['name' => 'package2', 'version' => '2.0.0'],
            ]
        ];

        $mockLocker = Mockery::mock(Locker::class);
        $mockLocker->shouldReceive('isLocked')
            ->once()
            ->andReturn(true);
        $mockLocker->shouldReceive('getLockData')
            ->once()
            ->andReturn($lockData);

        $this->composer->shouldReceive('getLocker')
            ->once()
            ->andReturn($mockLocker);

        // Resolve patches.
        $collection = new PatchCollection();
        $rootResolver->resolve($collection);

        // Check that both packages have patches.
        $patchedPackages = $collection->getPatchedPackages();
        $this->assertCount(2, $patchedPackages);
        $this->assertContains('package1', $patchedPackages);
        $this->assertContains('package2', $patchedPackages);

        // Check individual patches.
        $patches1 = $collection->getPatchesForPackage('package1');
        $this->assertCount(1, $patches1);
        $patch1 = reset($patches1);
        $this->assertEquals('Patch 1', $patch1->description);
        $this->assertEquals('root', $patch1->extra['provenance']);

        $patches2 = $collection->getPatchesForPackage('package2');
        $this->assertCount(1, $patches2);
        $patch2 = reset($patches2);
        $this->assertEquals('Patch 2', $patch2->description);
        $this->assertEquals('root', $patch2->extra['provenance']);
    }
}
