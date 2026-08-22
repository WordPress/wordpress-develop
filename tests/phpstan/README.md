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

Note the `--` in each of those. Composer needs it in order to pass the flags on to PHPStan rather than reading them as its own, and without it they are discarded silently. The npm script supplies it, which is why only one is needed there.

For available flags, see https://phpstan.org/user-guide/command-line-usage.

## The PHPStan configuration

The PHPStan configuration file is located at [`phpstan.neon.dist`](../../phpstan.neon.dist).

You can create a local copy at `phpstan.neon` to override the default configuration.

For more information about configuring PHPStan, see the [PHPStan documentation's Config reference](https://phpstan.org/config-reference).

## WordPress-specific extensions

This directory also contains extensions that teach PHPStan conventions specific to WordPress. They are registered in [`base.neon`](base.neon), so they apply to the default configuration and to any local override of it.

### Global variables in function docblocks

Core documents the globals a function uses with `@global Type $varname`. `GlobalDocBlockVisitor` bridges that convention to PHPStan's variable type resolution, so those globals are typed rather than `mixed` inside the function.

### Hash notation

Core documents the contents of an array argument with a nested list of `@type` tags, [hash notation](https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/#1-1-parameters-that-are-arrays):

```php
/**
 * @param array $args {
 *     Optional. An array of arguments.
 *
 *     @type string $post_type   Post type. Default 'post'.
 *     @type int    $post_author Post author ID.
 * }
 */
```

PHPStan reads that hash as free text, so the value stays a plain `array` and nothing inside it is typed. `HashNotationVisitor` translates it into the array shape PHPStan understands, which for the example above is `array{post_type?: string, post_author?: int, ...}`, so the same documentation serves the reader and the analysis rather than each shape having to be written a second time as a `@phpstan-param`.

A hash whose translation would be a guess is left alone, and the value keeps whatever type it has today. The visitor therefore only ever narrows a type, and never contradicts one:

- A `@phpstan-param` or `@phpstan-return` written by hand always wins. Hash notation cannot express everything a type can — a function returning either of two shapes, for example — so a shape that has been tuned in the source is never overwritten by the derived one.
- The declared type has to name a bare `array`, on its own or as one member of a union such as `string|array`. A type that is already more specific than the hash, such as `array<string, string|bool>`, is left as written.
- The hash has to be well formed: every `{` closed by a `}` on a line of its own, and every `@type` carrying a type and a `$name`.
- A parameter taken by reference is skipped, because PHPStan checks a by-reference argument in both directions, and a shape there would be a contract every caller's variable has to satisfy before the call rather than a description of what the function reads.

Keys of a `@param` hash are optional, at every level, and the shape is left open with a trailing `...`, because the hash lists the keys core reads rather than the only keys a caller may pass. Keys of a `@return` hash are required and the shape is sealed, since they describe a value core itself builds — unless the description marks one `Optional.`, which the visitor honors. Reading a key that a `@return` hash does not document is therefore reported rather than silently typed as `mixed`.

Two kinds of hash are outside what the visitor covers today:

- **`@var` hashes on properties.** A property declaration is inherited by every subclass and has to accept its own default, so a shape there would say more than the hash does: that no subclass may widen the property, and that the declared default already has the shape.
- **`object` hashes**, such as the one on `get_taxonomy_labels()`. PHPStan's object shapes are structural, so a shape derived for a value core builds as a `stdClass` is no longer assignable to a property declared `stdClass`. Covering these needs the docblocks to name the class rather than `object`, so that the shape can be intersected with it.

Hashes are also written on hook docblocks, where core documents `apply_filters()` and `do_action()`. Those are not attached to a function, so they are outside what this visitor sees, and the value a filter passes stays typed by [the hook extensions below](#hook-documentation).

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

- Adding an error to a "tech debt" baseline. This should be used for code that needs to be addressed eventually - by fixing, refactoring, or ignoring via one of the above methods - but is not worth addressing right now.

	Baselines are a useful triage tool for handling PHPStan errors in legacy code, as they allow us to enforce stricter code quality checks on new code, while gradually chipping away at the existing issues over time. **Avoid adding PHPStan errors from new code whenever possible, and use baselines as a last resort.**

### How the baselines are organized

The baselines live in [`baselines/`](baselines), one file per error identifier, such as `variable.undefined.neon`. Splitting them this way keeps each kind of error visible as a single file that should shrink to nothing and then be deleted, rather than as part of one large file in which every kind is mixed together.

Every entry is scoped to the file the error occurs in and carries an exact occurrence count:

```neon
-
	message: '#^Variable \$wpdb might not be defined\.$#'
	identifier: variable.undefined
	count: 4
	path: ../../../src/wp-trackback.php
```

Both the path and the count matter. A new occurrence of an already baselined error does not match the entry, even in a file that is already listed, and is reported as a new error. That is the point of recording them this way: the baselines describe exactly what exists today, so nothing new slips in behind them.

The consequence is that **fixing a baselined error means regenerating its baseline as part of the same change**, because the count no longer matches. A count that no longer matches is reported as an `ignore.count` error, which PHPStan does not allow to be ignored or baselined.

### Regenerating the baselines

The baselines are generated, and should not be edited by hand. Regenerate them with:

```bash
npm run typecheck:php:baselines
```

which will run the generator in the Docker container.

As with the analysis itself, flags are passed by adding `--` followed by the flags themselves:

```bash
# a single identifier:
npm run typecheck:php:baselines -- --identifier=variable.undefined

# several, either comma separated or by repeating the option:
npm run typecheck:php:baselines -- --identifier=variable.undefined,isset.variable
npm run typecheck:php:baselines -- --identifier=isset.variable --identifier=empty.variable

# print every error as one baseline, writing nothing:
npm run typecheck:php:baselines -- --combined

# the remaining options:
npm run typecheck:php:baselines -- --help
```

If you are not using the Docker environment, you can run the generator via Composer directly:

```bash
composer phpstan:baselines

composer phpstan:baselines -- --identifier=variable.undefined
composer phpstan:baselines -- --combined
composer phpstan:baselines -- --help
```

Note the `--` in each of those. Composer needs it in order to pass the flags on to the script rather than reading them as its own, and without it they are discarded silently, so `composer phpstan:baselines --identifier=variable.undefined` regenerates every baseline rather than that one. The npm script supplies it, which is why only one is needed there.

A run also deletes any baseline whose identifier no longer reports anything, and rewrites the list of them between the `# phpstan:baselines` markers in the `includes` of [`phpstan.neon.dist`](../../phpstan.neon.dist) to match. Adding a newly split out baseline, and retiring one that has reached zero, therefore need no edit of the configuration.

PHPStan's own `--generate-baseline` is deliberately not used directly. It captures every error a run reports, with no way to restrict it to one identifier, so it cannot refresh a single baseline without sweeping every other kind of error into it.

## Performance and troubleshooting

PHPStan can be resource-intensive, especially on large codebases like WordPress. If you encounter memory limit issues, you can increase the memory limit by passing the `--memory-limit` flag as shown [above](#running-the-tests).

PHPStan caches analysis results to speed up subsequent runs. You can see information about the results cache by running `analyse` with the `-vv` or `-vvv` flag.

Sometimes, due to the lack of type information in legacy code, PHPStan may still struggle to analyze certain parts of the codebase. In such cases, you can use the `--debug` flag to disable caching and see which files are causing issues.
