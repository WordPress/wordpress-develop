# AGENTS.md

This file provides a starting point for AI-assisted coding tools working in the
[WordPress Core development repository](https://github.com/WordPress/wordpress-develop).
It follows the [AGENTS.md](https://agents.md/) convention and is intended to be
readable by both humans and agents.

For deeper context, see [README.md](README.md), [CONTRIBUTING.md](CONTRIBUTING.md),
and [docs/architecture.md](docs/architecture.md).

## Project brief

WordPress is open source publishing software. This repository is the canonical
development checkout for WordPress Core — the PHP application, bundled themes,
and build tooling used to produce releases.

Core development is tracked in [Trac](https://core.trac.wordpress.org/). GitHub
pull requests are used for code review only; patches land in Core via SVN. Block
Editor (Gutenberg) development happens in the separate
[Gutenberg repository](https://github.com/WordPress/gutenberg/).

## High-level architecture

See [docs/architecture.md](docs/architecture.md) for directory layout, the `src/`
and `build/` workflow, and where major subsystems live.

## Development tooling and commands

See [README.md](README.md) for environment setup, local development, and test
commands. Common commands:

```
npm install && npm run build:dev && npm run env:start && npm run env:install
npm run dev
npm run test:php -- --filter <test name>
npm run test:e2e
composer lint
composer format
composer phpstan
```

PHPUnit runs via `npm run test:php` inside the Docker environment. PHPCS,
PHPStan, and related Composer scripts run on the host after `npm run env:start`
installs dependencies into `vendor/`. See README.md for WP-CLI, coverage, and
workflow linting details.

## Coding standards and best practices

Follow the [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/)
and [Inline Documentation Standards](https://make.wordpress.org/core/handbook/best-practices/inline-documentation-standards/).
See [CONTRIBUTING.md](CONTRIBUTING.md) for the contribution workflow, Trac
tickets, and GitHub pull request requirements.

When using AI tools, follow the
[WordPress AI Guidelines](https://make.wordpress.org/ai/handbook/ai-guidelines/).

For WordPress plugin, theme, and block development patterns outside Core, the
community maintains
[Agent Skills for WordPress](https://github.com/WordPress/agent-skills).

## Common pitfalls

- Edit source files under `src/`, not `build/`. Run `npm run dev` or
  `npm run build:dev` to sync changes.
- Block Editor packages and compiled assets come from Gutenberg. Core-specific
  JavaScript is built with Grunt/Webpack; see `Gruntfile.js` and
  `webpack.config.js`.
- Legacy code in Core may not match current coding standards. Match the style
  of surrounding code in the file you are editing.
- Do not run `composer format` or `phpcbf` on unrelated legacy files.
