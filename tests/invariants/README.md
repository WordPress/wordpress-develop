# Invariants

Invariants are properties of WordPress which should hold every time some code runs, such as "a post ID given to `get_post()` is never negative", or "a query from `$wpdb->prepare()` reaches MySQL with its structure unchanged".

They are checked while the whole PHPUnit test suite runs, by hooks on WordPress functions and methods. The [gameshark](https://github.com/dmsnell/php-ext-gameshark-extension) PHP extension calls those hooks just before or just after each call to their target. The existing tests do not change: they only cause the calls which the invariants look at.

Violations are reported only; they never fail a test. The [`PHPUnit Invariants`](../../.github/workflows/phpunit-invariants.yml) workflow runs the suite with the invariants on PHP 8.5, and shows the report in the run's summary. It runs for pull requests and pushes to trunk, and again when the `invariant-tests` label is added to a pull request.

## Files

- `specs/`: the invariants, in one file for each area of WordPress, such as `post.php` or `wpdb.php`.
- `gameshark.php`: the file the extension loads at the start of every PHP process, before PHPUnit and WordPress. It returns the hooks of every invariant in `specs/`.
- `bootstrap.php` and `phpunit.xml.dist`: run the WordPress test suite with the invariants. The test suites and groups are the same as in `phpunit.xml.dist` at the root of the repository.
- `includes/`: the support code. `WP_Invariant` defines invariants, `WP_Invariant_Call` is what their checks receive, and `WP_Invariant_SQL_Lexer` compares the structure of queries.
- `self-tests/`: tests of the support code, with fixtures standing in for WordPress. They need the extension, but not WordPress or a database.
- `bin/install-gameshark.sh`: builds the extension in the PHP container of the local Docker environment.
- `GAMESHARK_REF`: the commit of the extension that is built.

## Running locally

With the [local Docker environment](../../README.md) started and WordPress installed (`npm run env:start` and `npm run env:install`):

```sh
# Check out the extension once, at the pinned commit.
git clone https://github.com/dmsnell/php-ext-gameshark-extension.git .gameshark/src
git -C .gameshark/src checkout "$( cat tests/invariants/GAMESHARK_REF )"

# Build it and enable it in the PHP container. Rust and the build are kept in .gameshark/.
# Run this again whenever the container is recreated.
npm run env:gameshark

npm run test:php:invariants:self-tests
npm run test:php:invariants
npm run test:php:invariants -- --group post
```

The report is written to `tests/phpunit/build/logs/invariants.md` and `invariants.json`. Set `WP_INVARIANTS_REPORT_DIR` to write it somewhere else.

To stop at a violation with its full stack, set `WP_INVARIANTS_THROW=1`. A check which throws then breaks the code under test, so only use it to investigate one invariant.

Outside Docker, any PHP 8.2 or newer which is not thread-safe can load the extension: build it with `scripts/build-unix.sh` from its repository, then run PHPUnit with `-d extension=/path/to/gameshark.so` and the environment variables `GAMESHARK_INVARIANTS=1` and `GAMESHARK_INVARIANTS_FILE=/absolute/path/to/tests/invariants/gameshark.php`.

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
- any number of **hooks**: `->before( $target, $check )` runs before each call to the target, and `->after( $target, $check )` after each call returns. A target is a function, such as `get_post`, or a method, such as `wpdb::prepare`. Private methods, and methods inherited by subclasses, work too;
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

### Calls from tests

By default, a check does not run when the target was called directly from test code, in `tests/`. Tests deliberately give public functions invalid input, and the question an invariant answers is whether WordPress itself breaks it. Add `->include_test_callers()` to check those calls too.

### Keep checks cheap

A hook on a function which runs often, such as `get_post`, runs millions of times in the suite. Return early from a check when it does not apply. Calls a check makes itself are not hooked.

### Mistakes

- A spec file which fails to load is a fatal error in every PHP process, and the PHPUnit bootstrap refuses to run the suite.
- A check which throws is reported as a hook error, and the code under test carries on.
- A target which is never called, which is what a misspelled target looks like, is listed in the report under "Hooks which never ran".

## Limits

- Tests which run in a separate process run the hooks too, but what they record is not reported.
- The extension tracks at most 128 nested calls with after checks at once; deeper calls get no after checks.
- The extension needs PHP 8.2 or newer, and does not support thread-safe builds or Windows.
