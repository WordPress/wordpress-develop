# WordPress Core architecture

This document describes the high-level layout of the WordPress Core development
repository. For setup and commands, see [README.md](../README.md).

## Source and build directories

| Directory | Purpose |
| --------- | ------- |
| `src/` | Source of truth for PHP, CSS, JavaScript, and bundled themes. Edit files here during development. |
| `build/` | Generated output mirroring `src/`. Used by the local environment and PHPUnit. Do not edit directly. |

Grunt copies and compiles assets from `src/` into `build/`. With `npm run dev`,
a file watcher rebuilds changed files automatically. Without the watcher, run
`npm run build:dev` after making changes.

## Application layout (`src/`)

| Path | Purpose |
| ---- | ------- |
| `src/wp-includes/` | Core libraries, APIs, blocks infrastructure, REST API, and shared PHP. |
| `src/wp-admin/` | Administration screens and admin-only includes. |
| `src/wp-content/themes/` | Bundled default themes. |
| `src/wp-content/plugins/` | Bundled plugins (Akismet, Hello Dolly). |
| `src/js/` | JavaScript source enqueued by Core (admin, editor integrations, etc.). |

Root-level PHP files in `src/` (`wp-settings.php`, `wp-load.php`, etc.) bootstrap
the application.

## Gutenberg integration

The Block Editor is developed in the
[Gutenberg](https://github.com/WordPress/gutenberg/) repository. Compiled
Gutenberg assets (packages under `wp-includes/js/dist/`, block library, etc.)
are copied into this repository via the Gutenberg sync workflow — they are not
authored directly here.

For Block Editor changes, work in the Gutenberg repository. See
[Gutenberg's AGENTS.md](https://github.com/WordPress/gutenberg/blob/trunk/AGENTS.md)
for that project's conventions.

## Key design patterns

- **Hooks**: Actions and filters (`add_action`, `add_filter`) are the primary
  extension mechanism. See `src/wp-includes/plugin.php`.
- **REST API**: Routes registered under `src/wp-includes/rest-api/`. New
  endpoints extend `WP_REST_Controller`.
- **Database**: `$wpdb` wrapper in `src/wp-includes/class-wpdb.php`. Schema
  changes require upgrade routines.
- **Internationalization**: User-facing strings use gettext functions (`__()`,
  `_e()`, etc.) with the `default` text domain unless noted otherwise.

## Tests

| Path | Purpose |
| ---- | ------- |
| `tests/phpunit/` | PHPUnit test suite. Test files mirror Core file paths under `tests/phpunit/tests/`. |
| `tests/e2e/` | Playwright end-to-end tests. |
| `tests/qunit/` | QUnit tests for JavaScript. |
| `tests/phpstan/` | PHPStan configuration and custom rules. |

Run PHPUnit with `npm run test:php`. Filter by test name or Trac ticket group:

```
npm run test:php -- --filter Test_Class_Name
npm run test:php -- --group <ticket number>
```

See [tests/phpunit/README.md](../tests/phpunit/README.md) and the
[PHPUnit handbook](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
for more details.

## Related repositories

| Repository | Scope |
| ---------- | ----- |
| [WordPress/wordpress-develop](https://github.com/WordPress/wordpress-develop) | Core development (this repo). |
| [WordPress/gutenberg](https://github.com/WordPress/gutenberg) | Block Editor, `@wordpress/*` packages. |
| [WordPress/agent-skills](https://github.com/WordPress/agent-skills) | Agent Skills for plugin, theme, and block development. |
