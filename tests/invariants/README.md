# Invariants

Invariants are properties of WordPress which should hold every time some code runs, such as "a post ID given to `get_post()` is never negative", or "a query from `$wpdb->prepare()` reaches MySQL with its structure unchanged".

They are checked while the whole PHPUnit test suite runs, by hooks on WordPress functions and methods. The [gameshark](https://github.com/dmsnell/php-ext-gameshark-extension) PHP extension calls those hooks just before or just after each call to their target. The existing tests do not change: they only cause the calls which the invariants look at.

Where it helps, invariants compare what WordPress produces with how an independent parser reads it: PHP's browser-grade HTML parser `Dom\HTMLDocument`, its URL parser `Uri\WhatWg\Url`, its XML parser `Dom\XMLDocument`, and a MySQL parser generated from MySQL's own grammar. These oracles are why the suite needs PHP 8.5.

Violations are reported only; they never fail a test. The [`PHPUnit Invariants`](../../.github/workflows/phpunit-invariants.yml) workflow runs the suite with the invariants on PHP 8.5, and shows the report in the run's summary. It runs for pull requests and pushes to trunk, and again when the `invariant-tests` label is added to a pull request.

## Files

- `specs/`: the invariants, in one file for each area of WordPress, such as `post.php` or `wpdb.php`.
- `gameshark.php`: the file the extension loads at the start of every PHP process, before PHPUnit and WordPress. It returns the hooks of every invariant in `specs/`.
- `bootstrap.php` and `phpunit.xml.dist`: run the WordPress test suite with the invariants. The test suites and groups are the same as in `phpunit.xml.dist` at the root of the repository.
- `includes/`: the support code. `WP_Invariant` defines invariants, `WP_Invariant_Call` is what their checks receive, and `WP_Invariant_SQL_Lexer` compares the structure of queries and of `wpdb::prepare()` templates.
- `includes/oracles/`: the independent parsers. `WP_Invariant_Browser` reads HTML, URLs and XML as browsers and XML parsers do; `WP_Invariant_MySQL` parses queries with the [MySQL parser](https://github.com/WordPress/sqlite-database-integration/tree/trunk/packages/mysql-parser) from `WordPress/sqlite-database-integration`.
- `self-tests/`: tests of the support code, with fixtures standing in for WordPress. They need the extension, but not WordPress or a database.
- `bin/install-gameshark.sh`: builds the extension in the PHP container of the local Docker environment.
- `DEPENDENCIES`: the commits of the extension and of `sqlite-database-integration`, for the MySQL parser. Both are checked out in `.invariants/`, which git ignores.

## Running locally

With the [local Docker environment](../../README.md) started and WordPress installed (`npm run env:start` and `npm run env:install`):

```sh
# Check out the extension and the MySQL parser once, at the pinned commits.
git clone https://github.com/dmsnell/php-ext-gameshark-extension.git .invariants/gameshark
git -C .invariants/gameshark checkout "$( sed -n 's/^gameshark=//p' tests/invariants/DEPENDENCIES )"
git clone https://github.com/WordPress/sqlite-database-integration.git .invariants/sqlite-database-integration
git -C .invariants/sqlite-database-integration checkout "$( sed -n 's/^sqlite-database-integration=//p' tests/invariants/DEPENDENCIES )"

# Build the extension and enable it in the PHP container. Rust and the build are kept in .invariants/.
# Run this again whenever the container is recreated.
npm run env:gameshark

npm run test:php:invariants:self-tests
npm run test:php:invariants
npm run test:php:invariants -- --group post
```

The report is written to `tests/phpunit/build/logs/invariants.md` and `invariants.json`. Set `WP_INVARIANTS_REPORT_DIR` to write it somewhere else.

To leave out spec files, set `WP_INVARIANTS_SKIP` to their names, without `.php`: `WP_INVARIANTS_SKIP=pcre,xml npm run test:php:invariants` runs without the invariants which hook built-in PHP functions, which are the slowest. To load the MySQL parser from somewhere else, set `WP_INVARIANTS_MYSQL_PARSER` to its `packages/mysql-parser/src` directory.

To stop at a violation with its full stack, set `WP_INVARIANTS_THROW=1`. A check which throws then breaks the code under test, so only use it to investigate one invariant.

Outside Docker, PHP 8.5 or newer which is not thread-safe can run the suite: build it with `scripts/build-unix.sh` from its repository, then run PHPUnit with `-d extension=/path/to/gameshark.so` and the environment variables `GAMESHARK_INVARIANTS=1` and `GAMESHARK_INVARIANTS_FILE=/absolute/path/to/tests/invariants/gameshark.php`. The self-tests need no database, so they run this way anywhere. Tests which run in a separate process start PHP again without `-d` options, so for them, enable the extension in an ini file instead, in a directory given by `PHP_INI_SCAN_DIR`.

## Writing an invariant

Add it to the file in `specs/` for its area of WordPress, or start a new file. A spec file returns a list of invariants:

```php
return array(
	WP_Invariant::define( 'positive-id' )
		->describe( 'A numeric post ID given to get_post() is a whole number above 0, or 0 for the current post.' )
		->before(
			'get_post',
			static function ( WP_Invariant_Call $call ) {
				$post = $call->arg( 'post' );
				if ( is_numeric( $post ) ) {
					$call->expect( (int) $post >= 0, 'get_post() received the post ID %s.', $post );
				}
			}
		),
);
```

Its id is the file's path in `specs/` and its name: `post/positive-id`.

An invariant has:

- a **description** of what must hold, which is shown with its violations;
- any number of **hooks**: `->before( $target, $check )` runs before each call to the target, and `->after( $target, $check )` after each call returns. `->before_each( $targets, $check )` and `->after_each()` add the same check to several targets. A target is a function, such as `get_post`, or a method, such as `wpdb::prepare`. Private methods, methods inherited by subclasses, and built-in PHP functions and methods work too;
- optionally, a **state** shared by its hooks: `->state( $initial, 'test' )` starts it again before each test, while `'run'` keeps it for the whole run.

A check is `function ( WP_Invariant_Call $call, &$state )`. It records a violation with `$call->expect( $condition, $format, ...$values )`, and can read globals such as `$wpdb` or `$wp_filter` like any other code.

`WP_Invariant_Call` gives:

| Method | |
| --- | --- |
| `arg( $name )` | An argument by its parameter name, or the parameter's default when it was not passed. |
| `args()` | The arguments in order. |
| `self()` | The object of an instance method, or null. |
| `return_value()` | What the call returned, in an after check. |
| `before()` | What the paired before check returned for this same call, in an after check. |
| `caller()` | The file and line the target was called from. |
| `caller_args()` | The arguments of the function which called the target. It takes a backtrace with arguments, so only call it once a check has found something to report. |
| `depth()` | How deep the call is. A call the target makes is one deeper. |
| `stack()` | The frames of the call stack in `src/`, innermost first. |
| `target()`, `when()` | The target, and `before` or `after`. |

### Inputs and results

An after check sees both the arguments and the return value, so it can relate them:

```php
WP_Invariant::define( 'returns-requested-post' )
	->describe( 'get_post() given a post ID returns the post with that ID, or null.' )
	->after(
		'get_post',
		static function ( WP_Invariant_Call $call ) {
			$result = $call->return_value();
			if ( is_numeric( $call->arg( 'post' ) ) && $result instanceof WP_Post ) {
				$call->expect( (int) $call->arg( 'post' ) === $result->ID, 'get_post( %s ) returned the post %d.', $call->arg( 'post' ), $result->ID );
			}
		}
	),
```

The arguments an after check sees are as they were when the call started, even if the function changed its parameters. Objects are not copied, though: an object argument, or `self()`, shows the changes the call made. To compare with an object as it was before the call, pair the after check with a before check.

### Before and after one call

When an invariant has a before check and an after check on the same target, what the before check returns is given to the after check for the same call, as `$call->before()`. This works through recursion, and when a call inside throws:

```php
WP_Invariant::define( 'query-is-counted' )
	->describe( 'A successful wpdb::query() adds at least 1 to $wpdb->num_queries.' )
	->before(
		'wpdb::query',
		static function ( WP_Invariant_Call $call ) {
			return $call->self()->num_queries;
		}
	)
	->after(
		'wpdb::query',
		static function ( WP_Invariant_Call $call ) {
			if ( false !== $call->return_value() ) {
				$call->expect( $call->self()->num_queries > $call->before(), 'num_queries went from %d to %d.', $call->before(), $call->self()->num_queries );
			}
		}
	),
```

After checks do not run for a call which throws.

### Tables of calls

To check one call against others, keep a table of calls in the invariant's state, or in an object shared by several invariants, in any spec files: `WP_Invariant::shared( 'wpdb/prepared' )` returns a plain object, which is emptied before each test unless it is given the scope `'run'`. In `specs/wpdb.php`, `prepared-structure` records every query that `wpdb::prepare()` returns, and `prepared-reaches-mysql` looks for them in the queries sent to MySQL.

`WP_Invariant::strings_in( $value )` returns the strings in a value and its nested arrays, which helps with arguments such as `$postarr`. It does not look inside objects.

### Other helpers

| | |
| --- | --- |
| `WP_Invariant::property( $instance, $declaring, $name )` | A private or protected property, read with reflection, which runs none of the object's code. `$declaring` is the class which declares it. `specs/html.php` reads where the tag processor's current tag is. |
| `WP_Invariant::methods_in( $directory, $pattern )` | The `Class::method` names declared in a directory's PHP files whose names match a pattern, read from their tokens without loading them, as targets for a spec file. `specs/rest.php` hooks every core REST permission check this way. |
| `WP_Invariant::has_callbacks( $hook )` | Whether a filter or action has callbacks, read from `$wp_filter` without running anything. Checks use it to stay quiet when a test filters what they check. |

### Asking an oracle

An oracle reads the same thing WordPress produced, independently. Compare with it rather than with WordPress's own functions:

| | |
| --- | --- |
| `WP_Invariant_Browser::elements( $html )` | The elements a browser builds from HTML, as the contents of a BODY element, including those inside TEMPLATE elements. |
| `WP_Invariant_Browser::tree( $html )` | How a browser serializes HTML after parsing it: two pieces of HTML with the same tree give the same string. |
| `WP_Invariant_Browser::attribute_value( $value )` | The value a browser reads from `href="…"`, or null when the markup does not stay one attribute. |
| `WP_Invariant_Browser::url_scheme( $url )` | The scheme a browser gives a URL, or null when it is relative or cannot be parsed. |
| `WP_Invariant_Browser::url_host( $url, $base )` | The host a browser goes to for a URL, such as a Location header, resolved against a base. |
| `WP_Invariant_Browser::start_tag( $tag )` | The name and attribute values a browser reads from one start tag, parsed inside a TEMPLATE, where tags such as TD are kept. |
| `WP_Invariant_Browser::textarea_value( $html )` | The value a browser gives `<textarea>…</textarea>`, or null when the markup does not stay one TEXTAREA. |
| `WP_Invariant_Browser::nodes( $html )` | The comments, text and elements a browser builds directly in a BODY element. |
| `WP_Invariant_Browser::xml_child_elements( $xml )` | How many elements XML content adds, or null when it is not well-formed. |
| `WP_Invariant_Browser::xml_well_formed( $xml )` | Whether an XML document is well-formed. |
| `WP_Invariant_MySQL::parse_tree( $sql )` | The shape of a query's parse tree without its values, or null when MySQL would reject it. |
| `WP_Invariant_MySQL::table_names( $sql )` | The tables a query reads or changes, without aliases or common table expressions, or null when MySQL would reject it. |

Most spec files use them. A check which compares WordPress with itself, such as `blocks/serialized-blocks-parse-back`, which parses what `serialize_blocks()` returns with WordPress's own block parser, is a round trip, not an oracle: it finds where the two disagree, but not a mistake they share.

### Calls from tests

By default, a violation is not reported when the target was called directly from test code, in `tests/`. Tests deliberately give public functions invalid input, and the question an invariant answers is whether WordPress itself breaks it. The check runs either way; only the report depends on who called.

Invariants about what a function returns hold whoever calls it. Add `->include_test_callers()` to those, so that they report calls from tests too.

### Checks must not change anything

A check runs inside the code under test. If it changes anything, the suite does something different from what it would do without the invariants, and tests can fail for no reason of their own. Read what the hooks recorded, or ask an oracle, instead of calling WordPress again:

- calling a function which runs a filter, such as `map_meta_cap()`, changes how many times the filter runs, and tests count that;
- a lookup such as `get_userdata()` can run a query, and tests count queries;
- `wp_cache_get()` changes the cache's statistics;
- `WP_HTML_Tag_Processor::has_class()` applies pending class changes;
- `wp_unslash()` changes the objects it is given;
- `wpdb::prepare()` can call `_doing_it_wrong()`, which fails tests that do not expect it;
- any PCRE function resets `preg_last_error()`.

For example, `specs/capabilities.php` records what `map_meta_cap()` returned, by `$call->depth()`, so that the check on `WP_User::has_cap()` finds the capabilities that call mapped to, without mapping them again.

### Hooks on built-in functions

Once any built-in PHP function has a hook, the extension looks at every call to a built-in function, which slows down the whole suite. Keep invariants on built-in functions in their own spec files, such as `specs/pcre.php` and `specs/xml.php`, so that `WP_INVARIANTS_SKIP` can leave them out.

### Keep checks cheap

A hook on a function which runs often, such as `get_post`, runs millions of times in the suite. Return early from a check when it does not apply. Calls a check makes itself are not hooked.

### Mistakes

- A spec file which fails to load is a fatal error in every PHP process, and the PHPUnit bootstrap refuses to run the suite.
- A check which throws is reported as a hook error, and the code under test carries on.
- A target which is never called, which is what a misspelled target looks like, is listed in the report under "Hooks which never ran".

## Limits

- Tests which run in a separate process run the hooks too, but what they record is not reported.
- The extension tracks at most 128 nested calls with after checks at once; deeper calls get no after checks.
- The suite needs PHP 8.5 or newer, for the oracles. The extension does not support thread-safe builds or Windows.
- `WP_Invariant_MySQL` parses MySQL 8.4, as CI runs it. Syntax only MariaDB accepts would be reported as invalid.
