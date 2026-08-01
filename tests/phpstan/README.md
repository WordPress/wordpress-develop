# PHPStan

PHPStan is a static analysis tool for PHP that checks your code for errors without needing to execute the specific lines or write extra tests.

## Running the tests

PHPStan requires PHP and Composer dependencies to be installed.

If you don't already have an environment ready, you can set one up by following [these instructions](https://github.com/WordPress/wordpress-develop/blob/master/README.md).

Then you can launch the tests by running:

```bash
npm run typecheck:php
```

which will run PHPStan in the Docker container.

Additional flags supported by PHPStan can be passed by passing `--` followed by the flags themselves. For example, 

```bash
# to increase the memory limit from the default 2G to 4G:
npm run typecheck:php -- --memory-limit=4G

# to analyze only a specific file:
npm run typecheck:php -- src/wp-includes/template.php

# To scan with verbose debugging output:
npm run typecheck:php -- -vvv --debug
```

If you are not using the Docker environment, you can run PHPStan via Composer directly:

```bash
composer run phpstan

composer run phpstan -- --memory-limit=4G
composer run phpstan -- src/wp-includes/template.php
composer run phpstan -- -vvv --debug
```

For available flags, see https://phpstan.org/user-guide/command-line-usage.

## The PHPStan configuration

The PHPStan configuration file is located at [`phpstan.neon.dist`](../../phpstan.neon.dist).

You can create a local copy at `phpstan.neon` to override the default configuration.

For more information about configuring PHPStan, see the [PHPStan documentation's Config reference](https://phpstan.org/config-reference).

## WordPress-specific extensions

This directory also contains extensions that teach PHPStan conventions specific to WordPress. They are registered in [`base.neon`](base.neon), so they apply to the default configuration and to any local override of it.

### Global variables in function docblocks

Core documents the globals a function uses with `@global Type $varname`. `GlobalDocBlockVisitor` bridges that convention to PHPStan's variable type resolution, so those globals are typed rather than `mixed` inside the function.

### Hook documentation

The remaining extensions read the docblock documenting a hook where the hook is fired, which is where WordPress documents its hooks. They cover `apply_filters()`, `do_action()` and their `_deprecated` and `_ref_array` variants.

- **The value a filter returns is typed from its documentation.** `apply_filters()` returns the type of the first `@param` its docblock documents, rather than `mixed`. This assumes callbacks honor the documented type; one that returns something else is treated as the unusual case.
- **Hooks documented elsewhere are resolved.** Core's `/** This filter is documented in <file> */` convention is followed, in its action form as well, so a hook documented in another file is analyzed against its canonical docblock. A dynamic canonical name such as `"{$type}_template_hierarchy"` is matched against the literal name used at the referencing site.
- **Two rules check the documentation itself**: that a hook is documented at all, and that it is fired with as many arguments as its documentation describes.

Calls whose hook name contains no literal text, such as the `apply_filters_ref_array( $hook_name, $args )` re-dispatch in `plugin.php`, name no concrete hook and are skipped.

One consequence worth knowing: because a hook's documentation may live in a different file than the call inheriting it, editing a hook docblock in a file that reference comments point at discards PHPStan's result cache. Every call site inheriting that docblock has to be analyzed again, and PHPStan cannot infer that dependency on its own.

### Errors these rules report

These identifiers are specific to WordPress, and can be ignored or baselined like any other error, as described [below](#ignoring-and-baselining-errors).

| Identifier | What it means |
| --- | --- |
| `wordpress.hookDocMissing` | The hook is fired with neither a docblock documenting it nor a reference comment. Document it, or point at wherever it is documented. |
| `wordpress.hookDocNoParams` | A filter's docblock documents no parameters. A filter always passes at least the value being filtered, so document that value with `@param`, plus one for each further argument. This also fires when an unrelated docblock, such as a `@var` annotation, happens to sit immediately above the call. |
| `wordpress.hookDocReferenceFileMissing` | A reference comment names a file that does not exist in the tree being analyzed. The path is resolved relative to the file holding the comment and to the WordPress root; one that resolves outside the tree counts as missing, since the analysis does not read it. |
| `wordpress.hookDocReferenceHookMissing` | The referenced file exists, but documents no hook of that name. Either the reference is stale, or the canonical docblock has moved. |
| `wordpress.hookParamCountMismatch` | The call passes a different number of arguments than the docblock documents `@param` tags for. Passing fewer risks an `ArgumentCountError` in a callback registered for the documented count; passing more silently drops the extra argument and leaves the documentation misleading. |

## Ignoring and baselining errors

As we adopt PHPStan iteratively, you may be faced with false positives due to legacy code, or code that is not worth changing at this time.

PHPStan errors can be ignored in the following ways:

- Using the `@phpstan-ignore {error-identifier} (Reason for ignoring)` annotation in the code itself. This should be used to suppress false positives with a specific line of code.

- Adding the error pattern to the `ignoreErrors` section of the `phpstan.neon.dist` configuration file. This should be used to handle conflicts with WordPress Coding Standards or similar project decisions, or to allowlist legacy code that is not worth refactoring solely to satisfy the tests.

- Adding an error to the "tech debt" baseline. This should be used for code that needs to be addressed eventually - by fixing, refactoring, or ignoring via one of the above methods - but is not worth addressing right now.

	Baselines are a useful triage tool for handling PHPStan errors in legacy code, as they allow us to enforce stricter code quality checks on new code, while gradually chipping away at the existing issues over time. **Avoid adding PHPStan errors from new code whenever possible, and use baselines as a last resort.**

	The baseline file is located at `tests/phpstan/baseline.php` and generated by running PHPStan with the `--generate-baseline` flag:

	```bash
	npm run typecheck:php -- --generate-baseline=tests/phpstan/baseline.php

	# or, with Composer directly:
	composer run phpstan -- --generate-baseline=tests/phpstan/baseline.php
	```

	This will regenerate the baseline file with any new errors added to the existing ones. You can then commit the updated baseline file.

## Performance and troubleshooting

PHPStan can be resource-intensive, especially on large codebases like WordPress. If you encounter memory limit issues, you can increase the memory limit by passing the `--memory-limit` flag as shown [above](#running-the-tests).

PHPStan caches analysis results to speed up subsequent runs. You can see information about the results cache by running `analyse` with the `-vv` or `-vvv` flag.

Sometimes, due to the lack of type information in legacy code, PHPStan may still struggle to analyze certain parts of the codebase. In such cases, you can use the `--debug` flag to disable caching and see which files are causing issues.
