# PHPUnit Tests

PHPUnit is the official testing framework chosen by the core team to test our PHP code.

This documentation will assume you have a local development environment of your own choosing. For other testing workflows, such as Docker, see the [PHP: PHPUnit](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/) handbook page.

## Setup

1. Check out the [`wordpress-develop` repository](https://github.com/WordPress/wordpress-develop):
	```
	git clone git@github.com:WordPress/wordpress-develop.git
	cd wordpress-develop
	```
2. Run `composer update --with-all-dependencies`.
3. Create a clean MySQL database and user. **DO NOT USE AN EXISTING DATABASE.** The test database is emptied and repopulated every time tests are run.
4. Copy `wp-tests-config-sample.php` to `wp-tests-config.php` and enter your database credentials. **Again, use a separate database.**
5. Run the tests from the repository root:
   - To execute a particular test:
      ```
      vendor/bin/phpunit tests/phpunit/tests/[test_case].php
      ```
   - To execute all tests:
      ```
      vendor/bin/phpunit
      ```

## Notes:

Test cases live in the `tests` subdirectory. All files in that directory will be included by default. Extend the `WP_UnitTestCase` class to ensure your test is run.

`phpunit` will initialize and install a (more or less) complete running copy of WordPress each time it is run. This makes it possible to run functional interface and module tests against a fully working database and codebase, as opposed to pure unit tests with mock objects and stubs. Pure unit tests may be used also, of course.

Changes to the test database will be rolled back as tests are finished, to ensure a clean start next time the tests are run.

`phpunit` is intended to run at the command line, not via a web server.
