# Invariants

Invariants are properties of WordPress which should hold every time some code runs, such as "a post ID given to `get_post()` is never negative", or "a query from `$wpdb->prepare()` reaches MySQL with its structure unchanged".

They are checked while the whole PHPUnit test suite runs, by hooks on WordPress functions and methods. The [gameshark](https://github.com/dmsnell/php-ext-gameshark-extension) PHP extension calls those hooks just before or just after each call to their target. The existing tests do not change: they only cause the calls which the invariants look at.

Where it helps, invariants compare what WordPress produces with how an independent parser reads it: PHP's browser-grade HTML parser `Dom\HTMLDocument`, its URL parser `Uri\WhatWg\Url`, its XML parser `Dom\XMLDocument`, and a MySQL parser generated from MySQL's own grammar. These oracles are why the suite needs PHP 8.5.

Violations are reported only; they never fail a test. The [`PHPUnit Invariants`](../../.github/workflows/phpunit-invariants.yml) workflow runs the suite with the invariants on PHP 8.5, and shows the report in the run's summary. It runs for pull requests and pushes to trunk, and again when the `invariant-tests` label is added to a pull request.

## Files

- `specs/`: the invariants, in one file for each area of WordPress, such as `post.php` or `wpdb.php`.
- `gameshark.php`: the file the extension loads at the start of every PHP process, before PHPUnit and WordPress. It returns the hooks of every invariant in `specs/`.
- `bootstrap.php` and `phpunit.xml.dist`: run the WordPress test suite with the invariants. The test suites and groups are the same as in `phpunit.xml.dist` at the root of the repository.
- `includes/`: the support code. `WP_Invariant` defines invariants, `WP_Invariant_Call` is what their checks receive, and `WP_Invariant_SQL_Lexer` compares the structure of queries and of `wpdb::prepare()` templates. `WP_Invariant_Request` finds what the request sent, and `WP_Invariant_Security` reads addresses, serialized data and files as an attacker would use them.
- `includes/oracles/`: the independent parsers. `WP_Invariant_Browser` reads HTML, URLs and XML as browsers and XML parsers do; `WP_Invariant_MySQL` parses queries with the [MySQL parser](https://github.com/WordPress/sqlite-database-integration/tree/trunk/packages/mysql-parser) from `WordPress/sqlite-database-integration`; `WP_Invariant_HTML_API` finds where the syntax of HTML is with WordPress's own tag processor.
- `self-tests/`: tests of the support code, with fixtures standing in for WordPress. They need the extension, but not WordPress or a database.
- `canaries/`: tests which break each security invariant on purpose, with WordPress loaded, and check that it is reported. Each has a twin which does the same safely, and checks that nothing is.
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

To leave out spec files, set `WP_INVARIANTS_SKIP` to their names, without `.php`: `WP_INVARIANTS_SKIP=pcre,xml,filesystem,output,secrets,uploads,archives,serialize,random npm run test:php:invariants` runs without the invariants which hook built-in PHP functions, which are the slowest.

The AJAX tests are a group which the main run leaves out, as `phpunit.xml.dist` does. Run them on their own, with a report of their own: `WP_INVARIANTS_REPORT_NAME=invariants-ajax npm run test:php:invariants -- --group ajax`. The security invariants which look at request handlers, in `specs/request.php`, mostly find their handlers there.

`npm run test:php:invariants:canaries` runs the canaries. They need a database, as the suite does. To load the MySQL parser from somewhere else, set `WP_INVARIANTS_MYSQL_PARSER` to its `packages/mysql-parser/src` directory.

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

An invariant which finds places in WordPress, rather than calls, such as `pcre/matches-no-html-syntax`, keeps the places it has reported in a `'run'` state, and reports each place once.

`WP_Invariant::strings_in( $value )` returns the strings in a value and its nested arrays, which helps with arguments such as `$postarr`. It does not look inside objects.

### Other helpers

| | |
| --- | --- |
| `WP_Invariant::property( $instance, $declaring, $name )` | A private or protected property, read with reflection, which runs none of the object's code. `$declaring` is the class which declares it. `specs/html.php` reads where the tag processor's current tag is. |
| `WP_Invariant::methods_in( $directory, $pattern )` | The `Class::method` names declared in a directory's PHP files whose names match a pattern, read from their tokens without loading them, as targets for a spec file. `specs/rest.php` hooks every core REST permission check this way. |
| `WP_Invariant::has_callbacks( $hook )` | Whether a filter or action has callbacks, read from `$wp_filter` without running anything. Checks use it to stay quiet when a test filters what they check. |
| `WP_Invariant_Request::values( $characters )` | The values the request sent, which hold any of some characters: the strings in `$_GET`, `$_POST`, `$_COOKIE` and `$_REQUEST`, the URI, query string and headers, and the parameters of the REST requests being dispatched, as sent and unslashed, of at least 4 bytes. `WP_Invariant_Request::occurrences()` finds where one appears. |
| `WP_Invariant::frame_mark( $function )` and `WP_Invariant::running( $mark, $function )` | A scope for the run of a call, such as an AJAX handler's `do_action()`, without an after hook, which would not run when the call throws. `specs/request.php` and `specs/archives.php` keep stacks of them. |
| `WP_Invariant::resolve_path()`, `path_inside()`, `path_resolves_inside()` and `directory_forms()` | Paths with `..` and symbolic links resolved, and whether they are inside some directories. |
| `WP_Invariant::functions_calling( $directories, $callee )` and `WP_Invariant::cached()` | The functions whose own code calls a function, read from tokens, as targets; and a cache of such a result, kept while the source has not changed, for the processes of separate-process tests. |

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
| `WP_Invariant_HTML_API::markup( $html )` | The tags, comments and DOCTYPE declarations in HTML, counted by kind, as WordPress's HTML API reads them: not what only looks like markup inside a SCRIPT or TEXTAREA element, nor a tag the end of the HTML cuts off. |
| `WP_Invariant_HTML_API::boundaries( $html )` | Where tags, tag names, attributes, attribute values, comments and DOCTYPE declarations start in HTML, by byte offset, as the HTML API reads them. |
| `WP_Invariant_MySQL::parse_tree( $sql )` | The shape of a query's parse tree without its values, or null when MySQL would reject it. |
| `WP_Invariant_MySQL::table_names( $sql )` | The tables a query reads or changes, without aliases or common table expressions, or null when MySQL would reject it. |

Most spec files use them. `WP_Invariant_HTML_API` uses WordPress's tag processor, which follows the tokenizer of the HTML standard and, unlike `Dom\HTMLDocument`, gives byte offsets. It is independent of the regular expressions and string functions it checks, such as `wpautop()`, but not of the HTML API, and it is only there once WordPress has loaded the HTML API: before that, it returns null. A check which compares WordPress with itself, such as `blocks/serialized-blocks-parse-back`, which parses what `serialize_blocks()` returns with WordPress's own block parser, is a round trip, not an oracle: it finds where the two disagree, but not a mistake they share.

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

Once any built-in PHP function has a hook, the extension looks at every call to a built-in function, which slows down the whole suite. Keep invariants on built-in functions in their own spec files, such as `specs/pcre.php`, `specs/xml.php`, `specs/filesystem.php`, `specs/output.php`, `specs/secrets.php`, `specs/uploads.php`, `specs/archives.php`, `specs/serialize.php` and `specs/random.php`, so that `WP_INVARIANTS_SKIP` can leave them out.

### Keep checks cheap

A hook on a function which runs often, such as `get_post`, runs millions of times in the suite. Return early from a check when it does not apply. Calls a check makes itself are not hooked.

### Mistakes

- A spec file which fails to load is a fatal error in every PHP process, and the PHPUnit bootstrap refuses to run the suite.
- A check which throws is reported as a hook error, and the code under test carries on.
- A target which is never called, which is what a misspelled target looks like, is listed in the report under "Hooks which never ran".

## Security invariants

Some invariants follow what the request sent, what happened earlier in the request, or real secrets, which static analysis and unit tests miss:

| Invariant | What it catches |
| --- | --- |
| `output/request-values-stay-text` | Reflected XSS: a request value which holds HTML syntax where WordPress printed it, as the HTML API reads the whole output, including the HTML in JSON responses. |
| `wpdb/request-values-stay-literals` | SQL injection without `wpdb::prepare()`: a request value outside a string literal in a query. It needs MySQL. |
| `http/validated-urls-stay-external` | SSRF: a URL `wp_http_validate_url()` accepts whose host, as a WHATWG URL parser reads it, differs from the one it checked, or is internal in any IPv4 or IPv6 form. |
| `secrets/never-sent` | Secret disclosure: a salt, password hash, reset or request key, session token, auth cookie or application password in a REST or JSON response, printed HTML, a `wp_die()` message, a header or the error log. |
| `request/writes-check-a-nonce` | CSRF: a function which changes state, run in an AJAX, admin-post or admin action handler before a nonce check passed there. |
| `request/writes-check-a-capability` | Missing authorization: the same, before a capability check passed, and REST callbacks which run as another user than their permission check. |
| `uploads/nothing-executable` | Malicious uploads: a file in the uploads directory whose name or contents could run as code, such as `photo.php.jpg`. |
| `archives/extract-inside-destination` | Zip slip: a write outside the directory `unzip_file()` or `copy_dir()` was given. |
| `serialize/no-foreign-objects` | PHP object injection: serialized objects of classes with magic methods which this run did not serialize, or request values, given to `unserialize()`. |
| `kses/stored-html-is-filtered` | Stored XSS: post, comment, term or user description HTML stored unfiltered while kses filters that field. |
| `random/secrets-use-csprng` | Predictable secrets: `mt_rand()` and the like inside functions which make secrets. |
| `sanitize/idempotent` | Sanitizers which change their own output, which lets a value pass a check made between two sanitizations. |
| `multisite/blog-restored` | Data written to the wrong site: a function which returns while still switched to another site. It runs only in multisite runs, with `WP_MULTISITE=1`. |
| `mail/no-header-injection` | Email header injection through addresses, subjects or headers with line breaks. |

The canaries check that each of these is reported, except `http/validated-urls-stay-external`, `sanitize/idempotent` and `multisite/blog-restored`, which only WordPress itself could break.

## Types report

With `WP_INVARIANTS_TYPES=1`, as in CI, the run also counts the types of the arguments every WordPress function and method is given, and of the values it returns. The result is a second report, `invariants-types.md` and `invariants-types.json`, beside the invariant report, for adding type annotations and correcting them:

```
wp_kses()  src/wp-includes/kses.php:979
    calls: 242 (154 core, 88 tests)
    $content                      @param string
         242: string                  (154 core, 88 tests)
    $allowed_html                 @param array[]|string
          92: false                   (92 core)
          37: 'post'                  (34 core, 3 tests)
    ...
```

- **What it covers:** every named function and method declared in `src/wp-includes` and `src/wp-admin`, private or not. Private ones are marked, as are protected and private methods, magic methods, static methods, deprecated functions and generators. The bundled third-party libraries which `tests/phpstan/base.neon` lists are left out, as are closures and the methods of traits and interfaces. `WP_Invariant::functions_in()` finds them from the source, and functions which were never called are listed at the end.
- **How it counts them:** two catch-all hooks of the extension, with the target `*`, run before and after every call to a user-space function in those directories. Each type is counted separately for calls from WordPress and for calls made directly from tests, which deliberately pass odd types.
- **Documented types:** the native type, the PHPDoc `@param` or `@return` type, and the PHPStan `@phpstan-param` or `@phpstan-return` type are shown as written, next to what was counted. The report does not decide whether they agree; the JSON keeps them in separate fields, for a script to compare.
- **How types are written:**
  - `true` and `false` are shown apart until both are seen, then as `bool`.
  - Up to 4 distinct ints are shown as literals, such as `0` or `-1`. A fifth distinct int makes them all `int`. Floats are always `float`.
  - Up to 10 distinct strings shorter than 32 bytes are shown as literals, such as `'post'`. Another distinct string, or a longer one, makes them all `string`.
  - Once ints and strings are no longer literals, they are counted by their properties, from the first one seen, such as `int [zero]`, `string [html slash]` or `string [non-empty]`:
    - `zero` and `non-zero` ints.
    - `empty` strings, and `non-empty` ones, which is shown only when none of the other tags is, since each implies it.
    - `numeric`: it is a numeric string, as `is_numeric()` reads it, such as `'42'`, `'-1.5'`, `'1e3'` or `' 7'`, but not `'0x1A'`.
    - `html`: the Tag Processor finds a token in it other than text. A tag cut off by the end of the string is text.
    - `ref`: it holds an HTML character reference which decodes in text, such as `&amp;` or a legacy `&amp`.
    - `url`: `Uri\WhatWg\Url::parse()` accepts it without a base, so it is an absolute URL. That includes `mailto:` and `a:b`, and Windows paths such as `C:\x`, whose drive letter is a scheme.
    - `slash`: `wp_slash()` would change it. `unslash`: `wp_unslash()` would change it, which implies `slash`.
    - `path`: it is probably a file path, but nothing is there. `file`: it is probably a file path, and a file or directory is there, at the time of the call. A path is an absolute one whose first segment is a directory at `/`, such as `/var/www/…`, so not `/wp-admin/`; a Windows path starting with a drive letter; or a relative one starting with `./` or `../`. It has no NUL, newline, `<` or `://`.
    - `-utf8`: it is not valid UTF-8. Strings are assumed to be valid otherwise.
  - Literals have no tags, and strings and ints inside arrays are not tagged.
  - An array is `array{}` when empty, `list<…>` with the types of its first 16 elements, or a record such as `array{post_type: string, paged?: int}` of its first-level keys, where `?` marks a key some arrays did not have. Past 64 keys, it is `array`.
  - A stdClass object is a record of its properties, like an array, and other objects are their class.
  - `(not passed)` counts calls which left out an optional parameter, and `(extra)` counts arguments beyond the declared parameters of a function without a variadic one.
- **Separate processes:** tests which run in a separate process save what they counted when they finish, and the report adds it in. This needs the extension enabled in an ini file, as in CI.

Return values can be counted fewer times than calls: none is counted for a call which throws, or for a generator, whose caller receives a Generator. The cost is about twice the run time of the groups measured locally.

## Limits

- Tests which run in a separate process run the hooks too, but what their invariants record is not reported. The types report does include what they count.
- The extension tracks at most 128 nested calls with after checks at once; deeper calls get no after checks.
- A call to an inaccessible or undefined method, which PHP routes to `__call()` or `__callStatic()`, is hooked as that method. A generator is hooked once, when it is called, and gets no after checks.
- The suite needs PHP 8.5 or newer, for the oracles. The extension does not support thread-safe builds or Windows.
- `WP_Invariant_MySQL` parses MySQL 8.4, as CI runs it. Syntax only MariaDB accepts would be reported as invalid.
