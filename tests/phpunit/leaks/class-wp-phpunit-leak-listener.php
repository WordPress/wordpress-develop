<?php
/**
 * Finds state tests leave behind, see tests/phpunit/README.md.
 *
 * Snapshots WordPress and PHP process state at:
 *   class_start (before setUpBeforeClass), test_start (before setUp),
 *   test_end (after tearDown), class_end (after tearDownAfterClass).
 * A test_start right after its class's previous test_end holds no state and "reuse": true,
 * as nothing runs between them.
 *
 * Each snapshot is a flat map "area/key" => [hash, preview], appended as one JSON line
 * to WP_TESTS_LEAKS_OUT. diff.php compares snapshots.
 *
 * Scope "class" sweeps a whole suite cheaply: snapshots at class bounds only, and one
 * "class_leaks" line per class that changes state and leaves it unlike the install.
 * A leak is reported once, by the first class leaving it; later ones start with it.
 *
 * Settings: the listener's <arguments> "rules" and "scope", or the WP_TESTS_LEAKS_RULES and
 * WP_TESTS_LEAKS_SCOPE env vars, which win when set. The rules file picks the keys snapshotted
 * per scope and test file, see rules.php. WP_TESTS_LEAKS_AREAS replaces its scope's defaults.
 *
 * Areas:
 *   db/table/pk           row, keyed by a unique key (e.g. option_name) or the primary key
 *   dbcount/table         row count
 *   autoinc/table         AUTO_INCREMENT counter (ROLLBACK does not reset it)
 *   schema/table          SHOW CREATE TABLE without the AUTO_INCREMENT clause
 *   temptable/name        temporary tables created through $wpdb and not dropped
 *   cache/group/key       in-memory object cache (drop-in: cache/derived key)
 *   mc/key                keys on the memcached servers
 *   stale/source/blog/group/key[/option]
 *                         cached option/network option disagreeing with the DB
 *   global/name[/key]     globals, arrays one level deeper; $wpdb minus query noise,
 *                         $wp_object_cache minus its entries and counters
 *   static/Class::$prop   class static properties
 *   fstatic/fn()/$var     static variables of functions and methods
 *   const/NAME            user-defined constants
 *   ini/name, env/NAME, proc/what   ini settings, environment, process state
 *   file/path             files under wp-content and the temp dir top level
 */
class WP_PHPUnit_Leak_Listener implements PHPUnit\Framework\TestListener {

	use Yoast\PHPUnitPolyfills\TestListeners\TestListenerDefaultImplementation;

	private const SKIP_GLOBALS = array( 'GLOBALS', 'timestart', 'argv', 'argc', '__composer_autoload_files', '__PHPUNIT_BOOTSTRAP' );

	// $wpdb properties that change on every query, the listener's own included.
	private const WPDB_NOISE = array( 'last_query', 'last_result', 'col_info', 'queries', 'num_queries', 'num_rows', 'rows_affected', 'insert_id', 'result', 'func_call', 'time_start', 'last_error', 'dbh', 'col_meta', 'table_charset' );

	// $wp_object_cache entries, snapshotted by cache(), and hit counters, which every read changes.
	private const CACHE_NOISE = array( 'cache', 'cache_hits', 'cache_misses' );

	// Test tooling, PHPUnit 5's PEAR-style names (PHPUnit_, PHP_Timer, ...) included.
	private const SKIP_PREFIXES = array( 'PHPUnit', 'PHP_', 'Text_Template', 'File_Iterator', 'Composer\\', 'SebastianBergmann\\', 'Doctrine\\', 'Yoast\\', __CLASS__ );

	private const MAX_DEPTH = 12;

	private const PHP_8_1 = 80100;

	private const PREVIEW_LENGTH = 200;

	private const MISMATCH_PREVIEW_LENGTH = 90;

	private const MEMCACHED_BATCH = 500;

	private const MEMCACHED_TIMEOUT = 2;

	private const SCOPE_CLASS = 'class';

	private const SCOPE_TEST = 'test';

	private const SETTINGS = array( 'rules', 'scope' );

	private const RULES = 'tests/phpunit/leaks/rules.php';

	private const RULE_KEYS = array( 'why', 'paths', 'areas' );

	// Snapshot method => the areas it reads.
	private const COLLECTORS = array(
		'db'          => array( 'db', 'dbcount', 'autoinc', 'schema', 'temptable' ),
		'cache'       => array( 'cache', 'stale' ),
		'memcached'   => array( 'mc', 'stale' ),
		'globals'     => array( 'global' ),
		'statics'     => array( 'static' ),
		'static_vars' => array( 'fstatic' ),
		'constants'   => array( 'const' ),
		'process'     => array( 'ini', 'env', 'proc' ),
		'files'       => array( 'file' ),
	);

	// Pattern shorthands, e.g. WP_TESTS_LEAKS_AREAS=statics,globals.
	private const ALIASES = array(
		'all'        => array( '*/*' ),
		'persistent' => array( 'db/*', 'schema/*', 'mc/*', 'file/*', 'stale/*' ),
		'database'   => array( 'db/*', 'dbcount/*', 'autoinc/*', 'schema/*', 'temptable/*' ),
		'caches'     => array( 'cache/*', 'mc/*', 'stale/*' ),
		'globals'    => array( 'global/*' ),
		'statics'    => array( 'static/*', 'fstatic/*' ),
		'runtime'    => array( 'const/*', 'ini/*', 'env/*', 'proc/*' ),
		'files'      => array( 'file/*' ),
	);

	// Where rule paths are searched for, from the repo root; not fixtures or output.
	private const TESTS_DIR = 'tests/phpunit';

	private const SKIP_DIRS = array( 'data', 'build' );

	private const JSON_FLAGS = JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES;

	// SHOW CREATE TABLE has the live counter; information_schema caches it.
	private const AUTO_INCREMENT = '/ AUTO_INCREMENT=(\d+)/';

	// The result file PHPUnit 9 hands an isolated child, see TestCase::run().
	private const PHPUNIT_TEMP_PREFIX = 'phpunit_';

	// The listener's own environment: its settings.
	private const ENV_PREFIX = 'WP_TESTS_LEAKS_';

	private $out;

	private $scope;

	private $rules_file;

	// The scope's default patterns, each [negated, glob, whole area].
	private $patterns = array();

	// Each [path globs, patterns].
	private $file_rules = array();

	// Test file => plan, see plan().
	private $plans = array();

	// WP_TESTS_LEAKS_SCOPE=class: the state at the run start and at the running class start.
	private $installed = null;

	private $class_start = null;

	// Whether the last snapshot was a test_end: the next test_start reuses it.
	private $ended = false;

	private $columns = array();

	private $primary = array();

	private $temp_tables = array();

	// Tables db() listed, so the stale checks skip a deleted site's.
	private $tables = array();

	// The connection $temp_tables were created on.
	private $connection = null;

	// Suites started and not ended: the run starts at the first and ends with it.
	private $open_suites = 0;

	// Whether any class's plan kept an area: else diff.php would read the run as clean.
	private $snapshotted = false;

	// Key globs that kept a key, see unmatched().
	private $matched = array();

	// DB rows read by the stale checks, reset per snapshot.
	private $rows = array();

	/**
	 * @param array $settings The listener's <arguments>: "rules" (path from the repo root) and "scope" ("test" or "class").
	 */
	public function __construct( array $settings = array() ) {
		$unknown = array_diff( array_keys( $settings ), self::SETTINGS );
		if ( $unknown ) {
			$this->fail( 'Unknown settings: ' . implode( ', ', $unknown ) . '.' );
		}

		// Set and truncated by bootstrap.php.
		$this->out   = getenv( 'WP_TESTS_LEAKS_OUT' );
		$this->scope = $this->setting( 'SCOPE', $settings['scope'] ?? self::SCOPE_TEST );

		if ( ! in_array( $this->scope, array( self::SCOPE_TEST, self::SCOPE_CLASS ), true ) ) {
			$this->fail( 'The scope must be "' . self::SCOPE_TEST . '" or "' . self::SCOPE_CLASS . "\", not \"$this->scope\"." );
		}

		$this->rules_file = $this->absolute( $this->setting( 'RULES', $settings['rules'] ?? self::RULES ) );
		$this->load_rules();

		// Last, so it sees the CREATE TEMPORARY TABLE rewritten by the test case's own filter.
		add_filter( 'query', array( $this, 'track_temp_tables' ), PHP_INT_MAX );
	}

	// The env var if set and not empty, else the default.
	private function setting( $name, $fallback ) {
		$value = getenv( self::ENV_PREFIX . $name );

		return false === $value || '' === $value ? $fallback : $value;
	}

	private function absolute( $path ) {
		return str_starts_with( $path, '/' ) ? $path : $this->root() . "/$path";
	}

	private function root() {
		return dirname( __DIR__, 3 );
	}

	private function load_rules() {
		if ( ! is_file( $this->rules_file ) ) {
			$this->fail( "No rules file at $this->rules_file." );
		}

		$rules  = require $this->rules_file;
		$source = $this->rules_file;
		if ( ! is_array( $rules ) || ! isset( $rules['areas'][ $this->scope ] ) || array_diff( array_keys( $rules ), array( 'areas', 'files' ) ) ) {
			$this->fail( "$source must return an array with 'areas' per scope and, optionally, 'files'." );
		}

		$env            = $this->setting( 'AREAS', '' );
		$this->patterns = '' === $env
			? $this->patterns( $rules['areas'][ $this->scope ], "$source areas $this->scope" )
			: $this->patterns( explode( ',', $env ), self::ENV_PREFIX . 'AREAS' );

		foreach ( $rules['files'] ?? array() as $i => $rule ) {
			$where = "$source files $i";
			if ( ! is_array( $rule ) || array_diff( array_keys( $rule ), self::RULE_KEYS ) || array_diff( self::RULE_KEYS, array_keys( $rule ) ) ) {
				$this->fail( "$where: needs exactly 'why', 'paths' and 'areas'." );
			}

			if ( ! is_string( $rule['why'] ) || '' === trim( $rule['why'] ) ) {
				$this->fail( "$where: 'why' must say why the rule exists." );
			}

			if ( ! is_array( $rule['paths'] ) || ! $rule['paths'] || array_filter( $rule['paths'], 'is_string' ) !== $rule['paths'] ) {
				$this->fail( "$where: 'paths' must be a list of globs." );
			}

			$this->file_rules[] = array( $rule['paths'], $this->patterns( $rule['areas'], $where ) );
		}
	}

	// Entries to [negated, glob, whole area]: aliases expanded, a bare area name as "area/*".
	private function patterns( $entries, $where ) {
		if ( ! is_array( $entries ) || ! $entries ) {
			$this->fail( "$where: needs a list of patterns." );
		}

		$patterns = array();
		foreach ( $entries as $entry ) {
			$entry = is_string( $entry ) ? trim( $entry ) : '';
			if ( '' === $entry || '!' === $entry ) {
				$this->fail( "$where: empty or non-string pattern." );
			}

			$negated = '!' === $entry[0];
			$name    = $negated ? substr( $entry, 1 ) : $entry;
			$globs   = self::ALIASES[ $name ] ?? array( str_contains( $name, '/' ) ? $name : "$name/*" );

			foreach ( $globs as $glob ) {
				list( $area, $key ) = explode( '/', $glob, 2 );
				if ( ! $this->areas_matching( $area ) ) {
					$this->fail( "$where: \"$entry\" matches no area: " . implode( ', ', $this->areas() ) . '.' );
				}

				$patterns[] = array( $negated, $glob, '*' === $key );
			}
		}

		return $patterns;
	}

	private function areas() {
		return array_values( array_unique( array_merge( ...array_values( self::COLLECTORS ) ) ) );
	}

	private function areas_matching( $glob ) {
		return array_filter(
			$this->areas(),
			static function ( $area ) use ( $glob ) {
				return fnmatch( $glob, $area, FNM_NOESCAPE );
			}
		);
	}

	// A rule whose paths match no test file was left behind by a rename or removal.
	private function check_paths() {
		$files = array();
		$dir   = new RecursiveDirectoryIterator( $this->root() . '/' . self::TESTS_DIR, FilesystemIterator::SKIP_DOTS );
		$skip  = new RecursiveCallbackFilterIterator(
			$dir,
			static function ( $file ) {
				return ! $file->isDir() || ! in_array( $file->getFilename(), self::SKIP_DIRS, true );
			}
		);
		foreach ( new RecursiveIteratorIterator( $skip ) as $path => $file ) {
			$files[] = $this->relative( $path );
		}

		foreach ( $this->file_rules as $i => $rule ) {
			foreach ( $rule[0] as $glob ) {
				$matched = array_filter(
					$files,
					function ( $file ) use ( $glob ) {
						return $this->globbed( array( $glob ), $file );
					}
				);
				if ( ! $matched ) {
					$this->fail( "$this->rules_file files $i: \"$glob\" matches no file under " . self::TESTS_DIR . '.' );
				}
			}
		}
	}

	private function relative( $path ) {
		return substr( $path, strlen( $this->root() ) + 1 );
	}

	/*
	 * The test file's patterns, the defaults then each matching rule's, as area => true to keep
	 * all its keys, or its patterns, last first. Areas no pattern keeps are left out.
	 */
	private function plan( $class_name ) {
		$file = class_exists( $class_name, false ) ? (string) ( new ReflectionClass( $class_name ) )->getFileName() : '';
		if ( isset( $this->plans[ $file ] ) ) {
			return $this->plans[ $file ];
		}

		$patterns = $this->patterns;
		foreach ( $this->file_rules as $rule ) {
			if ( '' !== $file && $this->globbed( $rule[0], $this->relative( $file ) ) ) {
				$patterns = array_merge( $patterns, $rule[1] );
			}
		}

		$this->plans[ $file ] = $this->plan_for( $patterns );

		return $this->plans[ $file ];
	}

	private function globbed( array $globs, $path ) {
		foreach ( $globs as $glob ) {
			if ( fnmatch( $glob, $path, FNM_NOESCAPE ) ) {
				return true;
			}
		}

		return false;
	}

	private function plan_for( array $patterns ) {
		$plan = array();
		foreach ( $this->areas() as $area ) {
			$last = array();
			foreach ( array_reverse( $patterns ) as $pattern ) {
				if ( ! fnmatch( strstr( $pattern[1], '/', true ), $area, FNM_NOESCAPE ) ) {
					continue;
				}

				$last[] = $pattern;

				// Earlier patterns can't change a key it matches.
				if ( $pattern[2] ) {
					break;
				}
			}

			if ( ! in_array( false, array_column( $last, 0 ), true ) ) {
				continue;
			}

			$plan[ $area ] = 1 === count( $last ) && $last[0][2] ? true : $last;
		}

		return $plan;
	}

	// The class scope's install snapshot: every key any test file's plan keeps.
	private function union_plan() {
		$patterns = $this->patterns;
		foreach ( $this->file_rules as $rule ) {
			foreach ( $rule[1] as $pattern ) {
				if ( ! $pattern[0] ) {
					$patterns[] = $pattern;
				}
			}
		}

		return $this->plan_for( $patterns );
	}

	private function filter( array $state, array $plan ) {
		$kept = array();
		foreach ( $state as $key => $entry ) {
			$area = $plan[ strstr( $key, '/', true ) ] ?? null;
			if ( true === $area || ( $area && $this->kept( $key, $area ) ) ) {
				$kept[ $key ] = $entry;
			}
		}

		return $kept;
	}

	// The last pattern matching the key decides.
	private function kept( $key, array $patterns ) {
		foreach ( $patterns as $pattern ) {
			if ( fnmatch( $pattern[1], $key, FNM_NOESCAPE ) ) {
				$this->matched[ $pattern[1] ] = true;
				return ! $pattern[0];
			}
		}

		return false;
	}

	public function track_temp_tables( $query ) {
		$this->sync_connection();

		if ( preg_match( '/^\s*CREATE\s+TEMPORARY\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)/i', $query, $m ) ) {
			$this->temp_tables[ $m[1] ] = true;
		} elseif ( preg_match( '/^\s*DROP\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+EXISTS\s+)?(.+)$/is', $query, $m ) ) {
			foreach ( preg_split( '/\s*,\s*/', trim( $m[1], " ;\n" ) ) as $name ) {
				unset( $this->temp_tables[ trim( $name, '`' ) ] );
			}
		}

		return $query;
	}

	public function start_test_suite( $suite ) {
		// Marks the run's start and end so diff.php sees a cut run. The start holds the setup check-probes.php reads.
		if ( 0 === $this->open_suites++ ) {
			$this->check_paths();
			$this->mark( 'run_start', array( 'setup' => $this->setup() ) );

			// Before any class runs, those rules.php skips included.
			if ( self::SCOPE_CLASS === $this->scope ) {
				$this->installed = $this->state( $this->union_plan() );
			}
		}

		$class_name = $suite->getName();
		if ( ! class_exists( $class_name, false ) ) {
			return;
		}

		$plan = $this->plan( $class_name );
		if ( ! $plan ) {
			return;
		}

		$this->snapshotted = true;

		if ( self::SCOPE_CLASS !== $this->scope ) {
			$this->write( 'class_start', $class_name, $plan );
			return;
		}

		$this->class_start = $this->state( $plan );
	}

	public function end_test_suite( $suite ) {
		if ( class_exists( $suite->getName(), false ) ) {
			$this->end_class( $suite->getName() );
		}

		if ( 0 !== --$this->open_suites ) {
			return;
		}

		// With no run_end, diff.php reports the run as incomplete.
		if ( ! $this->snapshotted ) {
			fwrite( STDERR, "No test file's patterns keep an area: nothing was snapshotted.\n" );
			return;
		}

		$unmatched = $this->unmatched();
		foreach ( $unmatched as $glob ) {
			fwrite( STDERR, "\"$glob\" kept no key: a typo, or nothing it names existed.\n" );
		}

		if ( $unmatched ) {
			return;
		}

		$this->mark( 'run_end' );
	}

	// Positive key globs of the plans used that kept no key all run.
	private function unmatched() {
		$globs = array();
		foreach ( $this->plans as $plan ) {
			foreach ( array_filter( $plan, 'is_array' ) as $patterns ) {
				foreach ( $patterns as $pattern ) {
					if ( ! $pattern[0] && ! $pattern[2] && ! isset( $this->matched[ $pattern[1] ] ) ) {
						$globs[ $pattern[1] ] = true;
					}
				}
			}
		}

		return array_keys( $globs );
	}

	private function end_class( $class_name ) {
		// Class hooks run before the next test, this class's again with --repeat.
		$this->ended = false;

		$plan = $this->plan( $class_name );
		if ( ! $plan ) {
			return;
		}

		if ( self::SCOPE_CLASS !== $this->scope ) {
			$this->write( 'class_end', $class_name, $plan );
			return;
		}

		$this->write_class_leaks( $class_name, $plan );
	}

	public function start_test( $test ) {
		$name        = $this->name( $test );
		$reuse       = $this->ended;
		$this->ended = false;

		$plan = $this->plan( get_class( $test ) );
		if ( self::SCOPE_CLASS === $this->scope || ! $plan ) {
			return;
		}

		if ( $reuse ) {
			$this->append( $this->line( 'test_start', $name, array() ) + array( 'reuse' => true ) );
			return;
		}

		$this->write( 'test_start', $name, $plan );
	}

	public function end_test( $test, $time ) {
		$plan = $this->plan( get_class( $test ) );
		if ( self::SCOPE_CLASS !== $this->scope && $plan ) {
			$this->write( 'test_end', $this->name( $test ), $plan );
			$this->ended = true;
		}
	}

	private function name( $test ) {
		return $test instanceof PHPUnit\Framework\TestCase ? get_class( $test ) . '::' . $test->getName() : get_class( $test );
	}

	private function write( $point, $id, array $plan ) {
		$this->append( $this->line( $point, $id, $this->state( $plan ) ) );
	}

	private function line( $point, $id, array $state ) {
		return array(
			'point' => $point,
			'id'    => $id,
			'state' => $state,
		);
	}

	private function mark( $point, array $extra = array() ) {
		$this->append( $this->line( $point, '', array() ) + $extra );
	}

	private function setup() {
		return array(
			'multisite'        => is_multisite(),
			'php'              => PHP_VERSION_ID,
			'persistent_cache' => (bool) wp_using_ext_object_cache(),
			'scope'            => $this->scope,
		);
	}

	/*
	 * Persistent state the class changed and left unlike the install, e.g. `site_admins`
	 * ["admin"] at install, ["superadmin"] at class start, [] at class end. The line
	 * holds the install values as "installed" and the class end ones as "state".
	 */
	private function write_class_leaks( $class_name, array $plan ) {
		$start = $this->class_start;
		$end   = $this->state( $plan );

		$this->class_start = null;

		$installed = array();
		$left      = array();
		foreach ( array_keys( $start + $end ) as $key ) {
			$hash = $end[ $key ][0] ?? null;
			if ( ( $start[ $key ][0] ?? null ) === $hash || ( $this->installed[ $key ][0] ?? null ) === $hash ) {
				continue;
			}

			if ( isset( $this->installed[ $key ] ) ) {
				$installed[ $key ] = $this->installed[ $key ];
			}
			if ( isset( $end[ $key ] ) ) {
				$left[ $key ] = $end[ $key ];
			}
		}

		if ( ! $installed && ! $left ) {
			return;
		}

		$this->append( $this->line( 'class_leaks', $class_name, $left ) + array( 'installed' => $installed ) );
	}

	// The keys the plan keeps, see plan().
	private function state( array $plan ) {
		global $wpdb;

		/*
		 * A memcached run without the drop-in silently snapshots the in-memory cache instead.
		 * Happens when the env's php container (re)starts with LOCAL_PHP_MEMCACHED=false:
		 * its start command deletes src/wp-content/object-cache.php.
		 */
		if ( 'true' === getenv( 'LOCAL_PHP_MEMCACHED' ) && ! wp_using_ext_object_cache() ) {
			$this->fail( 'LOCAL_PHP_MEMCACHED=true but no object-cache.php drop-in is active.' );
		}

		$this->rows = array();

		// The stale checks in cache() and memcached() read it too: a dropped table would fail their reads.
		if ( array_intersect_key( $plan, array_flip( array_merge( self::COLLECTORS['db'], self::COLLECTORS['cache'], self::COLLECTORS['memcached'] ) ) ) ) {
			$this->list_tables();
		}

		$state = array();
		foreach ( self::COLLECTORS as $method => $areas ) {
			if ( array_intersect_key( $plan, array_flip( $areas ) ) ) {
				$state = array_merge( $state, $this->$method() );
			}
		}

		$state = $this->filter( $state, $plan );

		// Ends the snapshot's read view: its metadata locks would block e.g. a child's DDL.
		$wpdb->query( 'COMMIT' );

		return $state;
	}

	private function append( array $line ) {
		if ( false === file_put_contents( $this->out, json_encode( $line, self::JSON_FLAGS ) . "\n", FILE_APPEND | LOCK_EX ) ) {
			$this->fail( "Cannot write $this->out." );
		}
	}

	private function fail( $message ) {
		fwrite( STDERR, __CLASS__ . ": $message\n" );
		exit( 1 );
	}

	// phpcs:disable WordPress.DB.PreparedSQL -- Table and column names come from SHOW TABLES and SHOW COLUMNS.
	private function list_tables() {
		global $wpdb;

		/*
		 * Tests leave autocommit off, so a SELECT a test ran after tear_down()'s ROLLBACK left
		 * a REPEATABLE READ read view open that hides rows committed since, e.g. by an isolated
		 * child process. Snapshots run outside the tests' transactions and START TRANSACTION
		 * would commit anything pending anyway, so this changes nothing for the tests.
		 */
		$wpdb->query( 'COMMIT' );

		$like   = $wpdb->esc_like( $wpdb->base_prefix ) . '%';
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		$this->check_query( 'the tables' );

		$this->tables = array_flip( $tables );
	}

	private function db() {
		global $wpdb;

		$state = array();

		foreach ( array_keys( $this->tables ) as $table ) {
			$create = (string) $wpdb->get_var( "SHOW CREATE TABLE `$table`", 1 );
			$this->check_query( $table );
			$schema = preg_replace( self::AUTO_INCREMENT, '', $create );

			$state[ "schema/$table" ] = $this->text( $schema );
			if ( preg_match( self::AUTO_INCREMENT, $create, $m ) ) {
				$state[ "autoinc/$table" ] = $this->text( $m[1] );
			}

			// Keyed by the schema, so a test's DDL refreshes them.
			$meta = md5( $schema );
			if ( ! isset( $this->columns[ $meta ] ) ) {
				$this->columns[ $meta ] = $wpdb->get_col( "SHOW COLUMNS FROM `$table`" );
				$this->check_query( $table );
				// Column_name is the 5th column of SHOW KEYS.
				$this->primary[ $meta ] = $wpdb->get_col( "SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'", 4 );
				$this->check_query( $table );

				/*
				 * A unique key wins over the auto-increment primary key, and the latter leaves the
				 * row value: e.g. an option deleted and re-added with the same value is no change.
				 */
				$unique = $wpdb->get_col( "SHOW KEYS FROM `$table` WHERE Key_name <> 'PRIMARY' AND Non_unique = 0", 4 );
				$this->check_query( $table );
				if ( $unique ) {
					$this->columns[ $meta ] = array_values( array_diff( $this->columns[ $meta ], $this->primary[ $meta ] ) );
					$this->primary[ $meta ] = $unique;
				}

				// Bumped by every post saved on a site, e.g. by any class creating fixture posts.
				if ( $table === $wpdb->blogs ) {
					$this->columns[ $meta ] = array_values( array_diff( $this->columns[ $meta ], array( 'last_updated' ) ) );
				}
			}

			$cols = $this->columns[ $meta ];
			if ( ! $cols ) {
				continue;
			}

			// No primary key: key by the whole row, duplicates get a #n suffix below.
			$pk   = $this->primary[ $meta ] ? $this->primary[ $meta ] : $cols;
			$rows = $wpdb->get_results( 'SELECT ' . $this->concat( $pk ) . ' AS k, ' . $this->concat( $cols ) . " AS v FROM `$table`", ARRAY_N );
			$this->check_query( $table );

			foreach ( (array) $rows as $row ) {
				$key = "db/$table/" . str_replace( "\x1f", ',', $row[0] );
				for ( $n = 2; isset( $state[ $key ] ); $n++ ) {
					$key = preg_replace( '/#\d+$/', '', $key ) . "#$n";
				}
				$state[ $key ] = array( md5( $row[1] ), substr( $row[1], 0, self::PREVIEW_LENGTH ) );
			}
			$state[ "dbcount/$table" ] = $this->text( (string) count( (array) $rows ) );
		}

		$this->sync_connection();
		foreach ( array_keys( $this->temp_tables ) as $name ) {
			$state[ "temptable/$name" ] = $this->text( 'exists' );
		}

		return $state;
	}

	// A failed read would snapshot as missing data.
	private function check_query( $what ) {
		global $wpdb;

		if ( $wpdb->last_error ) {
			$this->fail( "Cannot read $what: $wpdb->last_error" );
		}
	}

	// A new connection, e.g. from WP_UnitTestCase_Base::set_up_before_class(), has none of the old one's temporary tables.
	private function sync_connection() {
		global $wpdb;

		$connection = is_object( $wpdb->dbh ) ? spl_object_id( $wpdb->dbh ) : null;
		if ( $connection !== $this->connection ) {
			$this->connection  = $connection;
			$this->temp_tables = array();
		}
	}

	// The columns joined by 0x1f, NULL as 0x00.
	private function concat( array $columns ) {
		$columns = array_map(
			static function ( $column ) {
				return "IFNULL(`$column`,0x00)";
			},
			$columns
		);

		return 'CONCAT_WS(0x1f,' . implode( ',', $columns ) . ')';
	}
	// phpcs:enable

	// In-memory object cache entries; for memcached the drop-in's local copy, keyed by derived key.
	private function cache() {
		global $wp_object_cache;

		$state = array();
		if ( ! is_object( $wp_object_cache ) || ! property_exists( $wp_object_cache, 'cache' ) ) {
			return $state;
		}

		$cache = $this->read( new ReflectionProperty( $wp_object_cache, 'cache' ), $wp_object_cache );

		if ( isset( $wp_object_cache->m ) ) {
			foreach ( (array) $cache as $derived => $value ) {
				$state[ "cache/$derived" ] = $this->entry( $value );
				$state                    += $this->stale_derived( 'local', $derived, $value );
			}

			return $state;
		}

		$global_groups = $this->read( new ReflectionProperty( $wp_object_cache, 'global_groups' ), $wp_object_cache );

		foreach ( (array) $cache as $group => $entries ) {
			foreach ( (array) $entries as $id => $value ) {
				$state[ "cache/$group/$id" ] = $this->entry( $value );

				// Multisite prefixes non-global keys with "<blog id>:".
				$blog = 1;
				$key  = (string) $id;
				if ( is_multisite() && ! isset( $global_groups[ $group ] ) && preg_match( '/^(\d+):(.*)$/s', $key, $m ) ) {
					$blog = (int) $m[1];
					$key  = $m[2];
				}

				$state += $this->stale( 'runtime', $blog, $group, $key, $value );
			}
		}

		return $state;
	}

	/*
	 * Keys stored on the memcached servers, not just the drop-in's local copy.
	 * Lists them with `lru_crawler metadump all` (memcached >= 1.4.31), then reads
	 * the values through the drop-in's own client so they come back unserialized.
	 * Flushed items may still be listed; getMulti() skips them.
	 */
	private function memcached() {
		global $wp_object_cache;

		$state = array();
		if ( ! is_object( $wp_object_cache ) || ! isset( $wp_object_cache->m ) || ! $wp_object_cache->m instanceof Memcached ) {
			return $state;
		}

		$keys = array();
		foreach ( $wp_object_cache->m->getServerList() as $server ) {
			$errno   = 0;
			$errstr  = '';
			$address = "{$server['host']}:{$server['port']}";
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Reported below.
			$socket = @fsockopen( $server['host'], $server['port'], $errno, $errstr, self::MEMCACHED_TIMEOUT );
			if ( false === $socket ) {
				$this->fail( "Cannot reach memcached at $address: $errstr" );
			}

			stream_set_timeout( $socket, self::MEMCACHED_TIMEOUT );
			fwrite( $socket, "lru_crawler metadump all\r\n" );

			// memcached 1.6+ acknowledges the command before the keys.
			$line = fgets( $socket );
			if ( "OK\r\n" === $line ) {
				$line = fgets( $socket );
			}

			while ( false !== $line && preg_match( '/^key=(\S+)/', $line, $m ) ) {
				$keys[] = rawurldecode( $m[1] );
				$line   = fgets( $socket );
			}
			fclose( $socket );

			// Anything else, e.g. ERROR, BUSY or a timeout, would drop keys.
			if ( "END\r\n" !== $line ) {
				$this->fail( "lru_crawler metadump on $address failed: " . json_encode( $line ) );
			}
		}

		foreach ( array_chunk( array_unique( $keys ), self::MEMCACHED_BATCH ) as $chunk ) {
			$values = $wp_object_cache->m->getMulti( $chunk );
			if ( false === $values && Memcached::RES_NOTFOUND !== $wp_object_cache->m->getResultCode() ) {
				$this->fail( 'Memcached::getMulti() failed: ' . $wp_object_cache->m->getResultMessage() );
			}

			foreach ( (array) $values as $key => $value ) {
				$state[ "mc/$key" ] = $this->entry( $value );
				$state             += $this->stale_derived( 'memcached', $key, $value );
			}
		}

		return $state;
	}

	/*
	 * Splits a drop-in key built by WP_Object_Cache::buildKey() and runs the stale check:
	 *   multisite:   "<blog>:options:<key>", "site-options:<network>:<key>" (global, no prefix)
	 *   single site: "<table prefix>:options:<key>", "<table prefix>site-options:<key>"
	 */
	private function stale_derived( $source, $derived, $value ) {
		global $table_prefix;

		$prefix = preg_quote( $table_prefix, '/' );
		if ( ! preg_match( "/^(?:(\\d+):|{$prefix}:?)?(options|site-options):(.*)$/s", $derived, $m ) ) {
			return array();
		}

		return $this->stale( $source, $m[1] ? (int) $m[1] : 1, $m[2], $m[3], $value );
	}

	// Cached options and network options that disagree with their DB rows, e.g. after a ROLLBACK.
	private function stale( $source, $blog, $group, $key, $value ) {
		$label = "stale/$source/$blog/$group/$key";

		if ( 'options' === $group ) {
			$rows = $this->option_rows( $blog );
			$name = $key;
		} elseif ( 'site-options' === $group && is_multisite() && preg_match( '/^(\d+):(.+)$/s', $key, $m ) ) {
			$rows = $this->sitemeta_rows( (int) $m[1] );
			$name = $m[2];
		} else {
			return array();
		}

		if ( null === $rows ) {
			return array();
		}

		$out = array();

		if ( 'alloptions' === $name && is_array( $value ) ) {
			foreach ( $value as $option => $cached ) {
				if ( ! $this->same( $rows[ $option ] ?? null, $cached ) ) {
					$out[ "$label/$option" ] = $this->mismatch( $cached, $rows[ $option ] ?? null );
				}
			}
			return $out;
		}

		if ( 'notoptions' === $name && is_array( $value ) ) {
			foreach ( array_keys( $value ) as $option ) {
				if ( isset( $rows[ $option ] ) ) {
					$out[ "$label/$option" ] = $this->mismatch( '(cached as missing)', $rows[ $option ] );
				}
			}
			return $out;
		}

		if ( ! $this->same( $rows[ $name ] ?? null, $value ) ) {
			$out[ $label ] = $this->mismatch( $value, $rows[ $name ] ?? null );
		}

		return $out;
	}

	private function same( $raw, $cached ) {
		if ( null === $raw ) {
			return false;
		}

		// get_option() caches the raw string, get_network_option() the unserialized value, e.g. ints as strings.
		return $raw === $cached || maybe_unserialize( $raw ) == $cached; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	}

	private function mismatch( $cached, $raw ) {
		$cached = is_scalar( $cached ) ? (string) $cached : (string) json_encode( $cached, self::JSON_FLAGS );
		$raw    = null === $raw ? '(no row)' : substr( $raw, 0, self::MISMATCH_PREVIEW_LENGTH );

		return $this->text( 'cache=' . substr( $cached, 0, self::MISMATCH_PREVIEW_LENGTH ) . ' db=' . $raw );
	}

	private function option_rows( $blog ) {
		global $wpdb;

		// A site created in a test has temporary tables, which SHOW TABLES leaves out.
		$table = $wpdb->get_blog_prefix( $blog ) . 'options';
		if ( ! isset( $this->tables[ $table ] ) && ! isset( $this->temp_tables[ $table ] ) ) {
			return null;
		}

		if ( ! isset( $this->rows[ "options:$blog" ] ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT option_name, option_value FROM %i', $table ), OBJECT_K );
			$this->check_query( $table );

			$this->rows[ "options:$blog" ] = wp_list_pluck( (array) $rows, 'option_value' );
		}

		return $this->rows[ "options:$blog" ];
	}

	private function sitemeta_rows( $network ) {
		global $wpdb;

		if ( ! isset( $this->rows[ "sitemeta:$network" ] ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->sitemeta} WHERE site_id = %d", $network ) );
			$this->check_query( $wpdb->sitemeta );

			$this->rows[ "sitemeta:$network" ] = wp_list_pluck( (array) $rows, 'meta_value', 'meta_key' );
		}

		return $this->rows[ "sitemeta:$network" ];
	}

	// Globals; arrays one level deeper so diffs name the changed key.
	private function globals() {
		$state = array();

		foreach ( $GLOBALS as $name => $value ) {
			if ( in_array( $name, self::SKIP_GLOBALS, true ) ) {
				continue;
			}

			if ( $value instanceof wpdb ) {
				$value = array_diff_key( $this->props( $value ), array_flip( self::WPDB_NOISE ) );
			}

			if ( 'wp_object_cache' === $name && is_object( $value ) ) {
				$value = array_diff_key( $this->props( $value ), array_flip( self::CACHE_NOISE ) );
			}

			if ( is_array( $value ) && $value ) {
				// The listener's own queries run the `query` filter too.
				if ( 'wp_filters' === $name ) {
					unset( $value['query'] );
				}

				foreach ( $value as $key => $item ) {
					// The listener's own settings in $_ENV and $_SERVER.
					if ( ! str_starts_with( (string) $key, self::ENV_PREFIX ) ) {
						$state[ "global/$name/$key" ] = $this->entry( $item );
					}
				}
				continue;
			}

			$state[ "global/$name" ] = $this->entry( $value );
		}

		return $state;
	}

	private function skipped( $name ) {
		foreach ( self::SKIP_PREFIXES as $prefix ) {
			if ( str_starts_with( $name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	// Static properties of user-defined classes (WP core + test framework).
	private function statics() {
		$state = array();

		foreach ( get_declared_classes() as $class_name ) {
			$ref = new ReflectionClass( $class_name );
			if ( $ref->isInternal() || $this->skipped( $class_name ) ) {
				continue;
			}

			foreach ( $ref->getProperties( ReflectionProperty::IS_STATIC ) as $prop ) {
				if ( $prop->getDeclaringClass()->getName() !== $class_name ) {
					continue;
				}

				$state[ "static/$class_name::\${$prop->getName()}" ] = $this->entry( $this->read( $prop ) );
			}
		}

		return $state;
	}

	// `static $x` variables inside user functions and methods.
	private function static_vars() {
		$state     = array();
		$functions = array();

		foreach ( get_defined_functions()['user'] as $function ) {
			$functions[ "$function()" ] = new ReflectionFunction( $function );
		}

		foreach ( get_declared_classes() as $class_name ) {
			$ref = new ReflectionClass( $class_name );
			if ( $ref->isInternal() || $this->skipped( $class_name ) ) {
				continue;
			}

			foreach ( $ref->getMethods() as $method ) {
				$inherited = $method->getDeclaringClass()->getName() !== $class_name;

				/*
				 * Before PHP 8.1, each subclass has its own copy of an inherited method's static variables.
				 * A test class's copies are the framework's, e.g. factory()'s.
				 */
				if ( $inherited && ( PHP_VERSION_ID >= self::PHP_8_1 || $ref->isSubclassOf( 'PHPUnit\Framework\TestCase' ) ) ) {
					continue;
				}

				$functions[ "$class_name::{$method->getName()}()" ] = $method;
			}
		}

		foreach ( $functions as $label => $ref ) {
			foreach ( $ref->getStaticVariables() as $var => $value ) {
				$state[ "fstatic/$label/\$$var" ] = $this->entry( $value );
			}
		}

		return $state;
	}

	private function constants() {
		$state = array();

		foreach ( get_defined_constants( true )['user'] ?? array() as $name => $value ) {
			$state[ "const/$name" ] = $this->entry( $value );
		}

		return $state;
	}

	// PHP process state a test can change and not restore.
	private function process() {
		$state = array();

		foreach ( ini_get_all( null, false ) as $name => $value ) {
			$state[ "ini/$name" ] = $this->text( (string) $value );
		}

		foreach ( getenv() as $name => $value ) {
			if ( ! str_starts_with( $name, self::ENV_PREFIX ) ) {
				$state[ "env/$name" ] = $this->text( (string) $value );
			}
		}

		// Reading the current handlers means swapping them out and back.
		$error_handler = set_error_handler( null );
		restore_error_handler();
		$exception_handler = set_exception_handler( null );
		restore_exception_handler();

		$process = array(
			'error_reporting'   => error_reporting(),
			'ob_level'          => ob_get_level(),
			'locale'            => setlocale( LC_ALL, 0 ),
			'timezone'          => date_default_timezone_get(),
			'cwd'               => getcwd(),
			'umask'             => umask(),
			'mb_encoding'       => function_exists( 'mb_internal_encoding' ) ? mb_internal_encoding() : null,
			'error_handler'     => $error_handler,
			'exception_handler' => $exception_handler,
			'autoloaders'       => array_map( array( $this, 'callback' ), spl_autoload_functions() ),
			'libxml_errors'     => libxml_use_internal_errors(),
		);

		foreach ( $process as $what => $value ) {
			$state[ "proc/$what" ] = $this->entry( $value );
		}

		return $state;
	}

	// Files under wp-content (uploads, fonts, languages, ...) and the temp dir's top level.
	private function files() {
		$state = array();

		if ( defined( 'WP_CONTENT_DIR' ) && is_dir( WP_CONTENT_DIR ) ) {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WP_CONTENT_DIR, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
			// Absolute paths: their leading "/" is the separator.
			foreach ( $iterator as $path => $file ) {
				$state[ 'file' . $path ] = $this->file( $file );
			}
		}

		foreach ( new FilesystemIterator( sys_get_temp_dir() ) as $path => $file ) {
			if ( ! str_starts_with( $file->getFilename(), self::PHPUNIT_TEMP_PREFIX ) ) {
				$state[ 'file' . $path ] = $this->file( $file );
			}
		}

		// The listener's own output, e.g. with WP_TESTS_LEAKS_OUT in the temp dir.
		$own = realpath( $this->out );
		if ( false !== $own ) {
			unset( $state[ 'file' . $own ] );
		}

		return $state;
	}

	// A method callback by class and name: e.g. Composer's ClassLoader changes with every lookup.
	private function callback( $callback ) {
		return is_array( $callback ) && is_object( $callback[0] ) ? get_class( $callback[0] ) . '::' . $callback[1] : $callback;
	}

	private function file( SplFileInfo $file ) {
		// Before the target's metadata, which a dangling link has none of.
		if ( $file->isLink() ) {
			return $this->text( 'link ' . $file->getLinkTarget() );
		}

		return $this->text( $file->isDir() ? 'dir' : $file->getSize() . ' ' . $file->getMTime() );
	}

	// Private members need setAccessible() before PHP 8.1, which deprecates it in 8.5.
	private function read( ReflectionProperty $prop, $instance = null ) {
		if ( PHP_VERSION_ID < self::PHP_8_1 ) {
			$prop->setAccessible( true );
		}

		return $prop->isInitialized( $instance ) ? $prop->getValue( $instance ) : '(uninitialized)';
	}

	private function props( $instance ) {
		$props = array();
		foreach ( (array) $instance as $k => $v ) {
			$props[ preg_replace( '/^\0.+\0/', '', $k ) ] = $v;
		}

		return $props;
	}

	private function text( $text ) {
		return array( md5( $text ), substr( $text, 0, self::PREVIEW_LENGTH ) );
	}

	private function entry( $value ) {
		$seen = array();
		$norm = $this->normalize( $value, 0, $seen );
		$text = is_string( $norm ) ? $norm : (string) json_encode( $norm, self::JSON_FLAGS );

		// Not the JSON: it hashes 1 and "1" alike, and invalid UTF-8 as U+FFFD.
		return array( md5( serialize( $norm ) ), substr( $text, 0, self::PREVIEW_LENGTH ) );
	}

	/*
	 * Serializable view of a value: closures by definition site and bound object,
	 * resources by type, cycles cut, depth capped at MAX_DEPTH.
	 */
	private function normalize( $value, $depth, array &$seen ) {
		if ( $depth > self::MAX_DEPTH ) {
			return '(deep)';
		}

		if ( is_resource( $value ) ) {
			return '(resource ' . get_resource_type( $value ) . ')';
		}

		if ( $value instanceof Closure ) {
			$ref   = new ReflectionFunction( $value );
			$bound = $ref->getClosureThis();

			return array(
				'@closure' => $ref->getFileName() . ':' . $ref->getStartLine(),
				'this'     => $bound ? get_class( $bound ) . '#' . spl_object_id( $bound ) : null,
				'use'      => $this->normalize( $ref->getStaticVariables(), $depth + 1, $seen ),
			);
		}

		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = $this->normalize( $v, $depth + 1, $seen );
			}
			return $out;
		}

		// The listener is in $wp_filter through its query filter; its own state is not the test's.
		if ( $value instanceof self ) {
			return '(' . __CLASS__ . ')';
		}

		if ( is_object( $value ) ) {
			$id = spl_object_id( $value );
			if ( isset( $seen[ $id ] ) ) {
				return '(cycle ' . get_class( $value ) . ')';
			}
			$seen[ $id ] = true;

			$props = (array) $value;
			if ( $value instanceof wpdb ) {
				$props = array_diff_key( $this->props( $value ), array_flip( self::WPDB_NOISE ) );
			}

			$out = array( '@class' => get_class( $value ) );
			foreach ( $props as $k => $v ) {
				$out[ str_replace( "\0", '', $k ) ] = $this->normalize( $v, $depth + 1, $seen );
			}
			return $out;
		}

		if ( is_float( $value ) && ! is_finite( $value ) ) {
			return (string) $value;
		}

		return $value;
	}
}
