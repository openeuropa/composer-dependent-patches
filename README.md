# Composer Dependent Patches

The plugin builds on cweagans/composer-patches version 2. The version
constrained patches must be defined under 'extra.dependent-patches' key in
composer.json. To set version constraints on a patch definition, the expanded
format must be used. The version constraint needs to be placed in the extra
section of the patch definition.

Usage:
```json
{
  "extra": {
    "dependent-patches": {
      "vendor/package": [
        {
          "description": "Patch for package version below 2.0",
          "url": "/path/to/lt2.patch",
          "extra": {
            "version": "<2.0"
          }
        },
        {
          "description": "Patch for package version 2.0 or above",
          "url": "/path/to/gte2.patch",
          "extra": {
            "version": ">=2.0"
          }
        }
      ]
    }
  }
}
```

Root package and dependency package patches are both supported. Patches file
can't be used for these patches, they must be placed in composer.json.

The plugin resolves and applies the version constraint patches separately
from the processes of composer-patches itself, and uses custom resolvers
to collect the patches.
