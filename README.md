# Composer Dependent Patches

A Composer plugin that extends [cweagans/composer-patches](https://github.com/cweagans/composer-patches) version 2 to support version-constrained patches. This plugin allows you to define patches that are only applied when specific version constraints are met, providing fine-grained control over when patches are applied based on package versions.

## Features

- **Version-constrained patches**: Apply patches only when package versions match specific constraints
- **Automatic version detection**: Patches are resolved and applied based on installed package versions
- **Lock file management**: Maintains a `dependent-patches.lock.json` file for reproducible builds
- **Separate patch management**: Works alongside regular patches without conflicts
- **Custom commands**: Dedicated commands for managing dependent patches

## Installation

```bash
composer require openeuropa/composer-dependent-patches
```

## Usage

### Defining Dependent Patches

Version-constrained patches must be defined under the `extra.dependent-patches` key in your `composer.json`. Use the expanded format with version constraints in the `extra` section:

```json
{
  "extra": {
    "dependent-patches": {
      "vendor/package": [
        {
          "description": "Fix for legacy versions",
          "url": "/path/to/legacy-fix.patch",
          "extra": {
            "version": "<2.0"
          }
        },
        {
          "description": "Modern version compatibility fix",
          "url": "/path/to/modern-fix.patch",
          "extra": {
            "version": ">=2.0 <3.0"
          }
        },
        {
          "description": "Latest version enhancement",
          "url": "/path/to/latest-enhancement.patch",
          "extra": {
            "version": "^3.0"
          }
        }
      ]
    }
  }
}
```

### Version Constraint Syntax

The plugin supports standard Composer version constraint syntax:

- `^1.0` - Compatible with version 1.0
- `>=2.0 <3.0` - Version 2.0 or higher, but less than 3.0
- `~2.1` - Reasonably close to 2.1
- `2.0.*` - Any version starting with 2.0
- `<2.0` - Less than version 2.0

### Lock File Management

The plugin maintains a `dependent-patches.lock.json` file that:
- Tracks which patches are applied based on current package versions
- Ensures reproducible builds across different environments
- Regenerates automatically when package versions change

## Commands

The plugin provides dedicated commands for managing dependent patches:

### `composer dependent-patches-relock`

Regenerates the `dependent-patches.lock.json` file based on current package versions and defined constraints.

```bash
composer dependent-patches-relock
# or use the short alias
composer dprl
```

### `composer dependent-patches-repatch`

Removes and reinstalls packages that have dependent patches, ensuring all version-appropriate patches are applied.

```bash
composer dependent-patches-repatch
# or use the short alias
composer dprp
```

## How It Works

1. **Resolution**: The plugin analyzes installed package versions against defined constraints
2. **Selection**: Only patches matching current version constraints are selected for application
3. **Application**: Selected patches are applied during package installation
4. **Tracking**: Applied patches are tracked in the lock file with package version information
5. **Regeneration**: Lock file is automatically regenerated when package versions change

## Integration with Regular Patches

This plugin works seamlessly alongside the base `cweagans/composer-patches` plugin:

- **Regular patches** (defined in `extra.patches`) → managed by base plugin → `patches.lock.json`
- **Dependent patches** (defined in `extra.dependent-patches`) → managed by this plugin → `dependent-patches.lock.json`
- Both types of patches are applied during `composer install`
- Use respective commands to manage each type independently

## Requirements

- PHP 8.1 or higher
- Composer 2.0 or higher
- cweagans/composer-patches ^2.0@beta

## Development

### Testing

The plugin includes automated tests using PHPUnit. To run the tests:

```bash
# Install development dependencies.
composer install

# Run tests.
composer test

# Run tests with coverage (requires Xdebug).
composer test-coverage
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

The project uses GitHub Actions for continuous integration, testing against multiple PHP and Composer versions.
