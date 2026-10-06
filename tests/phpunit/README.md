# PHPUnit Tests

PHPUnit is the official testing framework chosen by the core team to test PHP code.

## Configuration Files

Choose the configuration matching the installed PHPUnit version and the site type:

| PHPUnit version | Single site | Multisite |
| --- | --- | --- |
| PHPUnit 9 and earlier | `phpunit.xml.dist` | `tests/phpunit/multisite.xml` |
| PHPUnit 10.1 and later | `phpunit-modern.xml.dist` | `tests/phpunit/multisite-modern.xml` |

The legacy files remain necessary for older PHP versions. PHPUnit 10.0 is not
covered by the modern configuration; use PHPUnit 10.1 or later instead.
The modern configurations retain failure exit statuses for PHP deprecations,
notices, and warnings, without converting them to exceptions.

From the repository root, use the corresponding Composer script:

```sh
composer test
composer test:multisite
composer test:modern
composer test:multisite-modern
```

Additional PHPUnit arguments can be passed after `--`, for example:

```sh
composer test:modern -- --filter Tests_Formatting
```

For direct PHPUnit or Docker-based runs, select the configuration explicitly:

```sh
vendor/bin/phpunit -c phpunit-modern.xml.dist
npm run test:php -- -c tests/phpunit/multisite-modern.xml
```

CI selects the configuration using the installed PHPUnit version, not the PHP
version. These configuration files do not by themselves provide PHPUnit 10+
compatibility for the test framework or its dependencies.

For more information, please review the relevant Core Handbook pages:
- [PHP: PHPUnit](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [Writing PHP Tests](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/)
