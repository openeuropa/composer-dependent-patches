<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatches\Tests;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use cweagans\Composer\Patch;
use Mockery;
use OpenEuropa\ComposerDependentPatches\Plugin;
use OpenEuropa\ComposerDependentPatches\Resolver\Dependencies;
use OpenEuropa\ComposerDependentPatches\Resolver\RootComposer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for version constraint resolver functionality.
 */
class VersionConstraintResolverTest extends TestCase
{
    private Dependencies $dependenciesResolver;
    private RootComposer $rootResolver;
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
        
        $this->dependenciesResolver = new Dependencies($this->composer, $this->io, $this->plugin);
        $this->rootResolver = new RootComposer($this->composer, $this->io, $this->plugin);
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Test that patch definitions are retrieved from the root package.
     */
    public function testGetPatchDefinitionsFromRootPackage(): void
    {
        $mockRootPackage = Mockery::mock(RootPackageInterface::class);
        $extraData = [
            'dependent-patches' => [
                'vendor/package' => [
                    [
                        'description' => 'Test patch',
                        'url' => '/path/to/patch.patch',
                        'extra' => ['version' => '^1.0']
                    ]
                ]
            ]
        ];
        
        $this->composer->shouldReceive('getPackage')
            ->once()
            ->andReturn($mockRootPackage);
            
        $mockRootPackage->shouldReceive('getExtra')
            ->once()
            ->andReturn($extraData);
        
        $result = $this->rootResolver->getPatchDefinitions();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('vendor/package', $result);
        $this->assertCount(1, $result['vendor/package']);
    }

    /**
     * Test that getPatchDefinitions returns empty array when no patches are defined.
     */
    public function testGetPatchDefinitionsReturnsEmptyWhenNoPatchesDefined(): void
    {
        $mockRootPackage = Mockery::mock(RootPackageInterface::class);
        
        $this->composer->shouldReceive('getPackage')
            ->once()
            ->andReturn($mockRootPackage);
            
        $mockRootPackage->shouldReceive('getExtra')
            ->once()
            ->andReturn([]);
        
        $result = $this->rootResolver->getPatchDefinitions();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that getLockedPackages returns locked packages array.
     */
    public function testGetLockedPackagesReturnsPackages(): void
    {
        $lockData = [
            'packages' => [
                ['name' => 'vendor/package1', 'version' => '1.0.0'],
                ['name' => 'vendor/package2', 'version' => '2.0.0'],
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
        
        $result = $this->dependenciesResolver->getLockedPackages();
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('vendor/package1', $result[0]['name']);
        $this->assertEquals('1.0.0', $result[0]['version']);
    }

    /**
     * Test that getLockedPackages returns empty array when not locked.
     */
    public function testGetLockedPackagesReturnsEmptyWhenNotLocked(): void
    {
        $mockLocker = Mockery::mock(Locker::class);
        $mockLocker->shouldReceive('isLocked')
            ->once()
            ->andReturn(false);
            
        $this->composer->shouldReceive('getLocker')
            ->once()
            ->andReturn($mockLocker);
        
        $result = $this->dependenciesResolver->getLockedPackages();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that getLockedPackage returns specific package by name.
     */
    public function testGetLockedPackageReturnsSpecificPackage(): void
    {
        $lockData = [
            'packages' => [
                ['name' => 'vendor/package1', 'version' => '1.0.0'],
                ['name' => 'vendor/package2', 'version' => '2.0.0'],
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
        
        $result = $this->dependenciesResolver->getLockedPackage('vendor/package2');
        
        $this->assertIsArray($result);
        $this->assertEquals('vendor/package2', $result['name']);
        $this->assertEquals('2.0.0', $result['version']);
    }

    /**
     * Test that getLockedPackage returns null when package is not found.
     */
    public function testGetLockedPackageReturnsNullWhenPackageNotFound(): void
    {
        $lockData = [
            'packages' => []
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
        
        $result = $this->dependenciesResolver->getLockedPackage('vendor/nonexistent');
        
        $this->assertNull($result);
    }

    /**
     * Test version constraint validation with various constraint formats.
     *
     * @dataProvider versionConstraintProvider
     *
     * @param string $packageVersion The locked package version.
     * @param string $constraint The version constraint to test.
     * @param bool $expected The expected validation result.
     */
    public function testValidateVersionConstraint(string $packageVersion, string $constraint, bool $expected): void
    {
        // Mock locked packages.
        $lockData = [
            'packages' => [
                ['name' => 'vendor/package', 'version' => $packageVersion],
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
        
        // Create a patch with version constraint.
        $patch = new Patch();
        $patch->package = 'vendor/package';
        $patch->extra = ['version' => $constraint];
        
        $result = $this->dependenciesResolver->validateVersionConstraint($patch);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for version constraint validation tests.
     *
     * @return array<int, array{string, string, bool}> Test data.
     */
    public static function versionConstraintProvider(): array
    {
        return [
            // [package_version, constraint, expected_result].
            ['1.0.0', '^1.0', true],
            ['1.5.0', '^1.0', true],
            ['2.0.0', '^1.0', false],
            ['1.0.0', '>=1.0', true],
            ['2.0.0', '>=1.0', true],
            ['0.9.0', '>=1.0', false],
            ['1.0.0', '<2.0', true],
            ['2.0.0', '<2.0', false],
            ['1.0.0', '~1.0.0', true],
            ['1.0.1', '~1.0.0', true],
            ['1.1.0', '~1.0.0', false],
        ];
    }

    /**
     * Test that validateVersionConstraint returns true when no constraint is set.
     */
    public function testValidateVersionConstraintReturnsTrueWhenNoConstraint(): void
    {
        $patch = new Patch();
        $patch->package = 'vendor/package';
        $patch->extra = []; // No version constraint.
        
        $result = $this->dependenciesResolver->validateVersionConstraint($patch);
        
        $this->assertTrue($result);
    }

    /**
     * Test that validateVersionConstraint returns false when package is not locked.
     */
    public function testValidateVersionConstraintReturnsFalseWhenPackageNotLocked(): void
    {
        // Mock empty locked packages.
        $lockData = ['packages' => []];
        
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
        
        $patch = new Patch();
        $patch->package = 'vendor/nonexistent';
        $patch->extra = ['version' => '^1.0'];
        
        $result = $this->dependenciesResolver->validateVersionConstraint($patch);
        
        $this->assertFalse($result);
    }
}
