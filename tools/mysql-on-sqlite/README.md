# MySQL-on-SQLite Installer

Bundles the MySQL-on-SQLite driver into WordPress Core.

The driver is the `wordpress/mysql-on-sqlite` package from the [SQLite Database Integration](https://github.com/WordPress/sqlite-database-integration) monorepo. It emulates MySQL on top of SQLite, so that WordPress can use SQLite as its database.

## Prerequisites

- Git
- PHP with the `pdo_sqlite` extension, to check that the installed driver works

## Usage

Run from the WordPress development repository root:

```bash
# Install a specific release version
bash tools/mysql-on-sqlite/installer.sh --version=3.1.2

# Install from a branch
bash tools/mysql-on-sqlite/installer.sh --branch=trunk
```

You must specify either `--version` or `--branch` (not both). Use a release version for anything that is committed to Core.

## What It Does

1. Clones the monorepo from GitHub (shallow clone of the `v<version>` tag or the specified branch).
2. Checks that the driver version in `version.php` matches the requested version.
3. Splits `packages/mysql-on-sqlite/src/` into the directories below, replacing their contents.
4. Updates the paths between the files in `load.php` and in `class-wp-mysql-on-sqlite.php`.
5. Checks that the driver can run a query.
6. Prints the installed version and the source commit.

The installer stops with an error when the package has a file that it doesn't know where to copy, or when a path can't be updated.

## Output Structure

```
src/wp-includes/
├── parser/                # The generic parser, from src/parser/
└── database/
    ├── mysql-parser/      # The MySQL lexer, parser, and grammar, from src/mysql/
    └── mysql-on-sqlite/   # The SQLite driver, from src/sqlite/, and the other files of src/
```

Except for the paths between them, the files are copied without changes. Do not edit them in Core. Fix issues upstream, release a new version, and run the installer again.

Like other bundled libraries, the directories are excluded from the Core coding standards checks, PHPStan, and code coverage reports.

## Updating the Driver

1. Run the installer with the new version.
2. Review the changes with `git status src/wp-includes/parser src/wp-includes/database` and `git diff src/wp-includes/parser src/wp-includes/database`.
3. Run the PHPUnit tests with both SQLite and MySQL. To use SQLite in the local development environment, set `LOCAL_DB_TYPE=sqlite` in the `.env` file, and run `npm run env:restart`, `npm run env:install`, and `npm run test:php`.
4. Commit the changes, and link to the release notes at `https://github.com/WordPress/sqlite-database-integration/releases/tag/v<version>`.

## How Core Uses the Driver

WordPress uses SQLite when the `DB_ENGINE` constant is set to `sqlite`, and the `DB_PATH` constant is set to the absolute path of the database file. The `wpdb` class then loads the driver with `database/mysql-on-sqlite/load.php`, but only when the `WP_MySQL_On_SQLite` class doesn't exist yet. This lets a `db.php` drop-in, such as the one from the SQLite Database Integration plugin, use its own version of the driver.

Keep the database file outside the web root, where the web server can't serve it, and set `DB_PATH` to its location. Hosts can set `DB_PATH` in `wp-config.php`, or in `wp-config-sample.php`, which the `wp-config.php` setup then keeps. When `DB_PATH` isn't set, the setup sets it to a random file name in `wp-content/database`, a directory that denies web access only on Apache. The setup creates the database only when you run the installation, so changing `DB_PATH` in `wp-config.php` before that is enough to use another location. Later, Site Health recommends moving a database that is in the web root. When you move an existing database, also move its `-wal` and `-shm` files, if they exist.

WordPress doesn't create a missing database file, except in the `wp-config.php` setup and in installs from the command line, such as `wp core install`. An empty database would make the site look not installed, and anyone could install it. When you write `wp-config.php` yourself and install the site over the web, create the database file first, for example as an empty file.

Like in the plugin, the optional `SQLITE_BUSY_TIMEOUT` and `SQLITE_JOURNAL_MODE` constants are passed to the driver. For example, a database on a network file system may need `define( 'SQLITE_JOURNAL_MODE', 'DELETE' );`, as the default WAL mode doesn't work there.
