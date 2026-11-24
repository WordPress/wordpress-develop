<?php
/**
 * Each test leaks one kind of state on purpose, to check WP_PHPUnit_Leak_Listener sees it.
 * Runs only with --testsuite leak-probe, see tests/phpunit/README.md.
 */

function leak_probe_counter() {
	static $calls = 0;
	return ++$calls;
}

class Tests_Leak_Probe extends WP_UnitTestCase {

	const FILE = 'leak-probe.txt';

	public static $post_id;

	public static $leaked = 'original';

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
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

	public static function wpTearDownAfterClass() {
		foreach ( array( WP_CONTENT_DIR . '/', sys_get_temp_dir() . '/' ) as $dir ) {
			if ( file_exists( $dir . self::FILE ) ) {
				unlink( $dir . self::FILE );
			}
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

	public function test_leaks_cache_global_group() {
		wp_cache_add_global_groups( 'leak_probe_group' );
		$this->assertTrue( true );
	}

	public function test_leaks_function_static_var() {
		$this->assertSame( 1, leak_probe_counter() );
		$this->assertSame( 1, self::method_counter() );
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
		file_put_contents( WP_CONTENT_DIR . '/' . self::FILE, 'leaked' );
		file_put_contents( sys_get_temp_dir() . '/' . self::FILE, 'leaked' );
		$this->assertTrue( true );
	}

	public function test_leaks_schema() {
		global $wpdb;
		$this->assertTrue( $wpdb->query( "ALTER TABLE {$wpdb->options} ADD INDEX leak_probe (autoload)" ) );
	}

	public function test_leaks_temp_table() {
		global $wpdb;
		$this->assertTrue( $wpdb->query( "CREATE TABLE {$wpdb->prefix}leak_probe_tmp (id int)" ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_leaks_in_child() {
		// In-process state, which dies with the child: must not be reported.
		define( 'LEAK_PROBE_CHILD_CONSTANT', 'leaked' );

		// Committed to a table no class cleanup resets, so the parent sees it too.
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
