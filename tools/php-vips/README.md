# php-vips Installer

Bundles the [libvips/php-vips](https://github.com/libvips/php-vips) library into WordPress Core at `src/wp-includes/php-vips/`.

The installer fetches the package, scopes its PSR-3 dependency (`Psr\Log\*`) under `WordPress\VipsDependencies\*` using [PHP-Scoper](https://github.com/humbug/php-scoper) to avoid conflicts with plugin-bundled versions, and generates a manual autoloader.

`Jcupitt\Vips\*` is deliberately **not** scoped, so plugins that ship the same library keep working against it.

## Prerequisites

- PHP
- Composer
- Git
- curl

## Usage

Run from the WordPress development repository root:

```bash
# Install the latest release
bash tools/php-vips/installer.sh

# Install a specific release
bash tools/php-vips/installer.sh --version=v2.6.1

# Install from a branch
bash tools/php-vips/installer.sh --branch=master
```

With no arguments the newest release tag is resolved from the remote and installed. Prefer that over a branch, because it pins the bundled code to a released version.

`--version` and `--branch` are mutually exclusive. A bare version number is also accepted and gets the `v` prefix the repository uses for release tags.

## What It Does

1. Clones the package from GitHub (shallow clone of the specified ref).
2. Runs `composer install --no-dev` to fetch dependencies.
3. Downloads PHP-Scoper and scopes `Psr\Log\*` to `WordPress\VipsDependencies\*`.
4. Copies the unscoped php-vips source to `src/` and the scoped interfaces to `third-party/Psr/Log/`.
5. Generates `autoload.php` with a PSR-4 autoloader.
6. Validates the output.

## Output Structure

```
src/wp-includes/php-vips/
├── autoload.php      # Generated autoloader
├── LICENSE.txt       # php-vips license
├── src/              # php-vips source (Jcupitt\Vips\*)
└── third-party/      # Scoped dependencies (WordPress\VipsDependencies\*)
    └── Psr/
        └── Log/
```

## Support Files

- **`scoper.inc.php`** — PHP-Scoper configuration. Defines the scoping prefix and excludes the php-vips namespace.

## Notes

- The installer assumes `psr/log` is the only Composer dependency, because the scoped output is copied to a fixed `third-party/Psr/Log` path. It fails loudly if upstream adds another dependency, so the mapping can be updated deliberately.
- `src/wp-includes/php-vips/` is excluded from PHPCS and PHPStan, matching the other bundled libraries.
