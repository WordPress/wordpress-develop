# PHP AI Client Installer

Bundles the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) library into WordPress Core at `src/wp-includes/php-ai-client/`.

The installer fetches the package, scopes its third-party dependencies (Http\*, Psr\*, etc.) under `WordPress\AiClientDependencies\*` using [PHP-Scoper](https://github.com/humbug/php-scoper) to avoid conflicts with plugins, reorganizes the files into a namespace-based layout, and generates a manual autoloader.

## Prerequisites

- PHP
- Composer
- Git

## Usage

Run from the WordPress development repository root:

```bash
# Install a specific release version
bash tools/php-ai-client/installer.sh --version=1.0.0

# Install from a branch
bash tools/php-ai-client/installer.sh --branch=main
```

You must specify either `--version` or `--branch` (not both).

## What It Does

1. Clones the package from GitHub (shallow clone of the specified ref).
2. Runs `composer install --no-dev` to fetch dependencies.
3. Downloads PHP-Scoper and scopes all dependency namespaces to `WordPress\AiClientDependencies\*`.
4. Reorganizes scoped dependencies from Composer's `vendor/` layout into a namespace-based `third-party/` directory.
5. Removes unused files (async/promise support, deprecated discovery classes, etc.).
6. Generates `autoload.php` with a PSR-4 autoloader.
7. Validates the output.

## Output Structure

```
src/wp-includes/php-ai-client/
├── autoload.php      # Generated autoloader
├── src/              # AI Client source (WordPress\AiClient\*)
└── third-party/      # Scoped dependencies (WordPress\AiClientDependencies\*)
    ├── Http/
    └── Psr/
```

## Support Files

- **`scoper.inc.php`** — PHP-Scoper configuration. Defines the scoping prefix, excludes the AI Client's own namespace, and patches php-http/discovery to preserve references to external HTTP implementations.
- **`reorganize.php`** — Reads Composer's `installed.json` and copies scoped vendor files into a flat namespace-based directory structure.
