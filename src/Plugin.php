<?php

declare(strict_types=1);

namespace OpenEuropa\ComposerDependentPatching;

use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use cweagans\Composer\Capability\Resolver\ResolverProvider;
use cweagans\Composer\Plugin\Patches;
use cweagans\Composer\Resolver;
use OpenEuropa\ComposerDependentPatching\Capability\Resolver\VersionConstraintResolverProvider;

/**
 * Composer plugin providing support for patch version constraints.
 * 
 * The plugin builds on cweagans/composer-patches version 2. The version
 * constrained patches must be defined under 'extra.oe-patches' key in
 * composer.json. To set version constraints on a patch definition, the expanded
 * format must be used. The version constraint needs to be placed in the extra
 * section of the patch definition.
 *
 * Usage:
 * <code>
 * {
 *   [...],
 *   "extra": {
 *     "oe-patches": {
 *       "vendor/package": [
 *         {
 *           "description": "Patch for package version below 2.0",
 *           "url": "/path/to/lt2.patch",
 *           "extra": {
 *             "version": "<2.0"
 *           }
 *         },
 *         {
 *           "description": "Patch for package version 2.0 or above",
 *           "url": "/path/to/gte2.patch",
 *           "extra": {
 *             "version": ">=2.0"
 *           }
 *         }
 *       ]
 *     }
 *   }
 * }
 * </code>
 *
 * Root package and dependency package patches are both supported. Patches file 
 * can't be used for these patches, they must be placed in composer.json.
 * 
 * The plugin resolves and applies the version constraint patches separately
 * from the processes of composer-patches itself, and uses custom resolvers 
 * to collect the patches.
 * 
 * @see https://docs.cweagans.net/composer-patches/usage/defining-patches/#expanded-format
 */
class Plugin extends Patches
{

  /**
   * @var bool $patches_collected
   */
  protected bool $patches_collected = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getCapabilities(): array
  {
    return [
      ResolverProvider::class => VersionConstraintResolverProvider::class,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array
  {
    return [
      PackageEvents::PRE_PACKAGE_INSTALL => ['collectPatches'],
      PackageEvents::PRE_PACKAGE_UPDATE => ['collectPatches'],
    ] + parent::getSubscribedEvents();
  }

  /**
   * Collects patches using resolvers defined by the plugin.
   */
  public function collectPatches(): void
  {
    if ($this->patches_collected) {
      // Collect patches only once.
      return;
    }

    $this->io->write('  - <info>[composer-dependent-patching] Plugin is collecting patches.</info>');
    
    // Disable all composer-patches resolvers.
    $disabled = [
      '\cweagans\Composer\Resolver\RootComposer',
      '\cweagans\Composer\Resolver\PatchesFile',
      '\cweagans\Composer\Resolver\Dependencies',
    ];
    $resolver = new Resolver($this->composer, $this->io, $disabled);
    $this->patchCollection = $resolver->loadFromResolvers();
    $this->patches_collected = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function patchPackage(PackageEvent $event): void {
    $package = $this->getPackageFromOperation($event->getOperation());
    if ($package->getName() === 'openeuropa/composer-dependent-patching') {
      // See comment in parent method for explanation.
      return;
    }

    // @todo composer-patches keeps the IO messages about applying the patches
    //   to a minimum (most of them are restricted to verbose/debug mode).
    //   Consider fully overriding the parent method to display more information
    //   in the console in non-verbose mode.
    parent::patchPackage($event);
  }

}
