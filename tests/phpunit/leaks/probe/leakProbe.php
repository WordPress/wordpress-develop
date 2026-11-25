<?php
/**
 * Each test leaks one kind of state on purpose, to check WP_PHPUnit_Leak_Listener sees it.
 * Runs only with --testsuite leak-probe, see tests/phpunit/README.md.
 */

function leak_probe_counter() {
	static $calls = 0;
	return ++$calls;
}

class Leak_Probe_Counter {
	public static function count() {
		static $calls = 0;
		return ++$calls;
	}
}

// Before PHP 8.1, has its own copy of count()'s static variable.
class Leak_Probe_Counter_Child extends Leak_Probe_Counter {}

class Tests_Leak_Probe extends WP_UnitTestCase {

	const FILE = 'leak-probe.txt';

	const LINK = 'leak-probe-link';

	const DIR = 'leak-probe-dir';

	const LINK_TARGET = 'leak-probe-missing';

	// A term_relationships row no fixture creates: object_id, term_taxonomy_id.
	const ORPHAN_OBJECT_ID = 999999;

	const PERMANENT_TABLE = 'leak_probe_permanent';

	public static $post_id;

	private static $site_id;

	public static $leaked = 'original';

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// An interrupted run's leftovers would make the probes fail or go unreported.
		self::remove_leftovers();

		// Two term_relationships rows sharing object_id: the composite primary key case.
		$cat_a         = $factory->category->create();
		$cat_b         = $factory->category->create();
		self::$post_id = $factory->post->create( array( 'post_category' => array( $cat_a, $cat_b ) ) );

		$GLOBALS['leak_probe_big']  = range( 0, 2999 );
		$GLOBALS['leak_probe_int']  = 1;
		$GLOBALS['leak_probe_cb']   = function () {
			return 'original';
		};
		$GLOBALS['leak_probe_deep'] = array( 'l1' => array( 'l2' => array( 'l3' => array( 'l4' => array( 'l5' => array( 'l6' => array( 'l7' => array( 'l8' => array( 'l9' => 'original' ) ) ) ) ) ) ) ) );
	}

	// Leaves the site test_leaks_other_site() commits: the class leaks it.
	public static function wpTearDownAfterClass() {
		self::remove_leftovers();
	}

	private static function remove_leftovers() {
		global $wpdb;

		// Outside a test, CREATE and DROP TABLE are not rewritten to temporary ones.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . self::PERMANENT_TABLE ) );

		foreach ( self::file_dirs() as $dir ) {
			// A leftover link makes symlink() create its target.
			foreach ( array( self::FILE, self::LINK, self::LINK_TARGET ) as $name ) {
				if ( is_link( "$dir/$name" ) || is_file( "$dir/$name" ) ) {
					unlink( "$dir/$name" );
				}
			}

			if ( is_dir( "$dir/" . self::DIR ) && ! is_link( "$dir/" . self::DIR ) ) {
				rmdir( "$dir/" . self::DIR );
			}
		}
	}

	private static function file_dirs() {
		return array( WP_CONTENT_DIR, sys_get_temp_dir() );
	}

	public function tear_down() {
		parent::tear_down();

		// After the framework's flush, as a test's own tear_down() can, so a persistent cache keeps it.
		if ( 'test_leaks_cache_after_tear_down' === $this->getName() ) {
			$this->assertTrue( wp_cache_set( 'after_tear_down', 'leaked', 'leak_probe' ) );
			// An option only the cache has.
			$this->assertTrue( wp_cache_set( 'leak_probe_cached_only', 'leaked', 'options' ) );
		}

		if ( 'test_leaks_other_site' === $this->getName() && self::$site_id ) {
			switch_to_blog( self::$site_id );
			$this->assertTrue( wp_cache_set( 'leak_probe_cached_only', 'leaked', 'options' ) );
			restore_current_blog();
		}
	}

	public function test_term_relationships_fixture() {
		$this->assertCount( 2, wp_get_post_categories( self::$post_id ) );
	}

	public function test_leaks_option_cache() {
		update_option( 'blogname', 'leaked blogname' );
		update_option( 'leak_probe_not_autoloaded', 'leaked', false );

		$this->assertSame( 'leaked blogname', get_option( 'blogname' ) );
	}

	public function test_leaks_cache_after_tear_down() {
		$this->assertTrue( true );
	}

	public function test_leaks_composite_key_row() {
		global $wpdb;
		$inserted = $wpdb->insert(
			$wpdb->term_relationships,
			array(
				'object_id'        => self::ORPHAN_OBJECT_ID,
				'term_taxonomy_id' => 1,
			)
		);
		$this->assertSame( 1, $inserted );
		$this->assertNotFalse( $wpdb->query( 'COMMIT' ) );
	}

	public function test_leaks_notoptions_cache() {
		get_option( 'leak_probe_missing' );

		// Commit a row the notoptions cache still says is missing.
		global $wpdb;
		$inserted = $wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => 'leak_probe_missing',
				'option_value' => 'now exists',
				'autoload'     => 'off',
			)
		);
		$this->assertSame( 1, $inserted );
		$this->assertNotFalse( $wpdb->query( 'COMMIT' ) );

		$this->assertFalse( get_option( 'leak_probe_missing' ) );
	}

	public function test_leaks_network_option_cache() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Network options only cache separately on multisite.' );
		}

		update_site_option( 'site_admins', array( 'leak-probe-admin' ) );

		$this->assertSame( array( 'leak-probe-admin' ), get_site_option( 'site_admins' ) );
	}

	public function test_leaks_other_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only multisite has other sites.' );
		}

		global $wpdb;

		// Its tables are temporary: the test case rewrites CREATE TABLE.
		self::$site_id = self::factory()->blog->create();
		$this->assertNotFalse( $wpdb->query( 'COMMIT' ) );

		// Rolled back by tear_down(), not in the cache.
		switch_to_blog( self::$site_id );
		update_option( 'blogname', 'leaked blogname' );
		restore_current_blog();

		$this->assertSame( 'leaked blogname', get_blog_option( self::$site_id, 'blogname' ) );
	}

	public function test_leaks_cache_global_group() {
		wp_cache_add_global_groups( 'leak_probe_group' );
		$this->assertTrue( true );
	}

	public function test_leaks_function_static_var() {
		$this->assertSame( 1, leak_probe_counter() );
		$this->assertSame( 1, self::method_counter() );
		$this->assertSame( 1, Leak_Probe_Counter_Child::count() );
	}

	private static function method_counter() {
		static $calls = 0;
		return ++$calls;
	}

	public function test_leaks_static_property() {
		self::$leaked = 'leaked';
		$this->assertTrue( true );
	}

	public function test_leaks_constant() {
		define( 'LEAK_PROBE_CONSTANT', 'leaked' );
		$this->assertTrue( defined( 'LEAK_PROBE_CONSTANT' ) );
	}

	public function test_leaks_auto_increment() {
		$this->assertIsInt( self::factory()->post->create() );
	}

	public function test_leaks_deep_global_change() {
		$GLOBALS['leak_probe_deep']['l1']['l2']['l3']['l4']['l5']['l6']['l7']['l8']['l9'] = 'leaked';
		$this->assertTrue( true );
	}

	public function test_leaks_big_array_key() {
		$GLOBALS['leak_probe_big'][2500] = 'leaked';
		$this->assertTrue( true );
	}

	public function test_leaks_type_only_change() {
		$GLOBALS['leak_probe_int'] = '1';
		$this->assertTrue( true );
	}

	// rules.php drops leak_probe_filtered_*, then keeps leak_probe_filtered_kept.
	public function test_leaks_filtered_globals() {
		$GLOBALS['leak_probe_filtered_dropped'] = 'leaked';
		$GLOBALS['leak_probe_filtered_kept']    = 'leaked';
		$this->assertTrue( true );
	}

	public function test_leaks_closure_swap() {
		$GLOBALS['leak_probe_cb'] = function () {
			return 'original';
		};
		$this->assertTrue( true );
	}

	public function test_leaks_wpdb_and_server() {
		global $wpdb;
		$wpdb->hide_errors();
		$_SERVER['LEAK_PROBE'] = 'leaked';
		$this->assertTrue( true );
	}

	public function test_leaks_process_state() {
		ini_set( 'precision', '10' );
		error_reporting( E_ALL & ~E_USER_NOTICE );
		date_default_timezone_set( 'Europe/Rome' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- The leak this test plants.
		setlocale( LC_ALL, 'C.UTF-8' );
		putenv( 'LEAK_PROBE=leaked' );
		umask( 0077 );
		mb_internal_encoding( 'ISO-8859-1' );
		libxml_use_internal_errors( true );
		spl_autoload_register( function ( $class_name ) {} );
		set_exception_handler( function ( $e ) {} );
		set_error_handler( function () {} );
		$this->assertTrue( true );
	}

	public function test_leaks_files() {
		foreach ( self::file_dirs() as $dir ) {
			$this->assertNotFalse( file_put_contents( "$dir/" . self::FILE, 'leaked' ) );
			// Dangling: a link has no target metadata to read.
			$this->assertTrue( symlink( "$dir/" . self::LINK_TARGET, "$dir/" . self::LINK ) );
			$this->assertTrue( mkdir( "$dir/" . self::DIR ) );
		}
	}

	public function test_leaks_schema() {
		global $wpdb;
		$this->assertTrue( $wpdb->query( "ALTER TABLE {$wpdb->options} ADD INDEX leak_probe (autoload)" ) );
	}

	public function test_leaks_temp_table() {
		global $wpdb;
		$this->assertTrue( $wpdb->query( "CREATE TABLE {$wpdb->prefix}leak_probe_tmp (id int)" ) );
	}

	public function test_reconnect_drops_temp_table() {
		global $wpdb;
		$this->assertTrue( $wpdb->query( "CREATE TABLE {$wpdb->prefix}leak_probe_gone (id int)" ) );

		// As each class's set_up_before_class() does: the new connection has no temporary tables.
		$this->assertTrue( $wpdb->db_connect() );
	}

	public function test_leaks_permanent_table() {
		global $wpdb;

		// As Tests_DB_dbDelta does.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		$this->assertTrue( $wpdb->query( $wpdb->prepare( 'CREATE TABLE %i (id int)', $wpdb->prefix . self::PERMANENT_TABLE ) ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_leaks_in_child() {
		// In-process state, which dies with the child: must not be reported.
		define( 'LEAK_PROBE_CHILD_CONSTANT', 'leaked' );

		// Committed to a table no class cleanup resets, so the parent sees it.
		global $wpdb;
		$inserted = $wpdb->insert(
			$wpdb->links,
			array(
				'link_url'  => 'https://leak-probe-child.example',
				'link_name' => 'leak_probe_child_row',
			)
		);
		$this->assertSame( 1, $inserted );
		$this->assertNotFalse( $wpdb->query( 'COMMIT' ) );
	}
}
