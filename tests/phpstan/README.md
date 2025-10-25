# PHPStan

PHPStan is a static analysis tool for PHP that checks your code for errors without needing to execute the specific lines or write extra tests.

## Running the tests

PHPStan requires PHP and Composer dependencies to be installed.

If you don't already have an environment ready, you can set one up by following [these instructions](https://github.com/WordPress/wordpress-develop/blob/master/README.md).

Then you can launch the tests by running:

```
npm run test:php:stan
```

which will run PHPStan in the Docker container.

Additional flags supported by PHPStan can be passed by passing `--` followed by the flags themselves. For example, 

```
# to increase the memory limit from the default 2G to 4G:
npm run test:php:stan -- --memory-limit=4G

# to analyse only a specific file:
npm run test:php:stan -- tests/phpstan/src/wp-includes/template.php

# To scan with verbose debugging output:
npm run test:php:stan -- -vvv --debug

```

If you are not using the Docker environment, you can run PHPStan via Composer directly:

```
composer run analyse

compose run analyse -- --memory-limit=4G
compose run analyse -- tests/phpstan/src/wp-includes/template.php
compose run analyse -- -vvv --debug
```

For available flags, see https://phpstan.org/user-guide/command-line-usage.

## The PHPStan configuration

The PHPStan configuration file is located at [`phpstan.neon.dist`](../../phpstan.neon.dist).

You can create a local copy at `phpstan.neon` to override the default configuration.

For more information about configuring PHPStan, see the [PHPStan documentation's Config reference](https://phpstan.org/config-reference).

## Ignoring errors

As we adopt PHPStan iteratively, you may be faced with false positives due to legacy code, or code that is not worth changing at this time.

PHPStan errors can be ignored in the following ways:

- Using the `@phpstan-ignore {error-identifier} (Reason for ignoring)` annotation in the code. This should be used when addressing false positives.

- Adding the error pattern to the `ignoreErrors` section of the `phpstan.neon` configuration file. This should be used when addressing conflicts between WordPress coding standards, or legacy code that is not worth refactoring just to satisfy the tests.

- Adding an error to the "tech debt" baseline. This should be used for code that needs to be addressed eventually - by fixing, refactoring, or ignoring via one of the above methods - but is not worth addressing right now.

	Baselines are a useful triage tool for handling PHPStan errors in legacy code, as they allow us to enforce stricter code quality checks on new code, while gradually chipping away at the existing issues over time. You should avoid adding PHPStan errors from new code whenever possible.

	Baselining is done by running (replacing `{%n%}` with the desired level, e.g. `0`, `1`, `2`, etc):

	```
	npm run test:php:stan -- --generate-baseline=tests/phpstan/baseline/level-{%n%}.php

	# or, with Composer directly:
	composer run analyse -- --generate-baseline=tests/phpstan/baseline/level-{%n%}.php
	```

	This will regenerate the baseline file with any new errors added to the existing ones. You can then commit the updated baseline file.

## Performance and troubleshooting

PHPStan can be resource-intensive, especially on large codebases like WordPress. If you encounter memory limit issues, you can increase the memory limit by passing the `--memory-limit` flag as shown above.

PHPStan caches analysis results to speed up subsequent runs. You can see information about the results cache by running `analyse` with the `-vv` flag.

Sometimes, due to the lack of type information in legacy code, PHPStan may still struggle to analyse certain parts of the codebase. In such cases, you can use the `--debug` flag to disable caching and see which files are causing issues.
