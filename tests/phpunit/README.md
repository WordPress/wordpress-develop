# PHPUnit Tests

PHPUnit is the official testing framework chosen by the core team to test our PHP code.

This documentation will assume you have a local development environment of your own choosing. For other testing workflows, such as Docker, see the [PHP: PHP Unit](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/) handbook page.

## Setup

1. Check out the wordpress-develop repository

       git clone git://develop.git.wordpress.org/ wordpress-develop
       cd wordpress-develop

2. Run `composer update --with-all-dependencies`
3. Create a clean MySQL database and user. **DO NOT USE AN EXISTING DATABASE** or you will lose data, guaranteed.
4. Copy `wp-tests-config-sample.php` to `wp-tests-config.php`, edit it to include your testing database credentials.
5. Run the tests from the repository root:
   - To execute a particular test:

         $ vendor/bin/phpunit tests/phpunit/tests/test_case.php
   - To execute all tests:

         $ vendor/bin/phpunit

## Notes:

Test cases live in the `tests` subdirectory. All files in that directory will be included by default. Extend the `WP_UnitTestCase` class to ensure your test is run.

`phpunit` will initialize and install a (more or less) complete running copy of WordPress each time it is run. This makes it possible to run functional interface and module tests against a fully working database and codebase, as opposed to pure unit tests with mock objects and stubs. Pure unit tests may be used also, of course.

Changes to the test database will be rolled back as tests are finished, to ensure a clean start next time the tests are run.

`phpunit` is intended to run at the command line, not via a web server.
