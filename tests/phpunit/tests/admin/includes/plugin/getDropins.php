<?php

/**
 * Tests for get_dropins() and _get_dropins().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::get_dropins
 * @covers ::_get_dropins
 */
class Tests_Admin_Includes_Plugin_GetDropins extends WP_UnitTestCase {

	/**
	 * Created test files to clean up in tear_down.
	 *
	 * @var string[]
	 */
	private $created_files = array();

	/**
	 * Sets up the environment before any tests in this class run.
	 */
	public static function wpSetUpBeforeClass() {
		self::_back_up_drop_ins();
	}

	/**
	 * Restores the environment after all tests in this class have run.
	 */
	public static function wpTearDownAfterClass() {
		self::_restore_drop_ins();
	}

	/**
	 * Sets up the environment before each test.
	 */
	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * Cleans up the environment after each test.
	 */
	public function tear_down() {
		foreach ( $this->created_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		$this->created_files = array();

		wp_cache_delete( 'plugins', 'plugins' );

		parent::tear_down();
	}

	/**
	 * Helper to create a test drop-in file in WP_CONTENT_DIR.
	 *
	 * @param string $filename File name in WP_CONTENT_DIR.
	 * @param string $content  File contents.
	 * @return string Full path to the created file.
	 */
	private function create_dropin_file( $filename, $content ) {
		$full_path = WP_CONTENT_DIR . '/' . $filename;

		$this->assertFileDoesNotExist( $full_path, "Drop-in fixture already exists: {$full_path}" );

		$this->assertNotFalse( file_put_contents( $full_path, $content ), "Failed to write drop-in fixture: {$full_path}" );
		$this->created_files[] = $full_path;

		wp_cache_delete( 'plugins', 'plugins' );

		return $full_path;
	}

	/**
	 * Tests that _get_dropins() returns expected standard drop-ins.
	 *
	 * @ticket 65819
	 */
	public function test_get_dropins_definitions_contain_standard_dropins() {
		$dropins = _get_dropins();

		$expected_standard_keys = array(
			'advanced-cache.php',
			'db.php',
			'db-error.php',
			'install.php',
			'maintenance.php',
			'object-cache.php',
			'php-error.php',
			'fatal-error-handler.php',
		);

		foreach ( $expected_standard_keys as $key ) {
			$this->assertArrayHasKey( $key, $dropins, "Expected drop-in key {$key} not found." );
			$this->assertIsArray( $dropins[ $key ], "Drop-in value for {$key} should be an array." );
			$this->assertCount( 2, $dropins[ $key ], "Drop-in value for {$key} should contain 2 elements." );
			$this->assertIsString( $dropins[ $key ][0], "Description for {$key} should be a string." );
		}

		$this->assertSame( 'WP_CACHE', $dropins['advanced-cache.php'][1] );
		$this->assertTrue( $dropins['db.php'][1] );
		$this->assertTrue( $dropins['db-error.php'][1] );
		$this->assertTrue( $dropins['install.php'][1] );
		$this->assertTrue( $dropins['maintenance.php'][1] );
		$this->assertTrue( $dropins['object-cache.php'][1] );
		$this->assertTrue( $dropins['php-error.php'][1] );
		$this->assertTrue( $dropins['fatal-error-handler.php'][1] );
	}

	/**
	 * Tests multisite drop-in definitions in _get_dropins().
	 *
	 * @ticket 65819
	 */
	public function test_get_dropins_definitions_multisite() {
		$dropins = _get_dropins();

		$multisite_keys = array(
			'sunrise.php',
			'blog-deleted.php',
			'blog-inactive.php',
			'blog-suspended.php',
		);

		if ( is_multisite() ) {
			foreach ( $multisite_keys as $key ) {
				$this->assertArrayHasKey( $key, $dropins, "Multisite drop-in key {$key} should be defined." );
			}
			$this->assertSame( 'SUNRISE', $dropins['sunrise.php'][1] );
			$this->assertTrue( $dropins['blog-deleted.php'][1] );
			$this->assertTrue( $dropins['blog-inactive.php'][1] );
			$this->assertTrue( $dropins['blog-suspended.php'][1] );
		} else {
			foreach ( $multisite_keys as $key ) {
				$this->assertArrayNotHasKey( $key, $dropins, "Multisite drop-in key {$key} should not be defined on single site." );
			}
		}
	}

	/**
	 * Tests that get_dropins() detects a valid drop-in file and parses its header metadata.
	 *
	 * @ticket 65819
	 */
	public function test_get_dropins_detects_file_and_extracts_headers() {
		$content = "<?php\n/**\n * Plugin Name: Custom Maintenance\n * Description: Drop-in maintenance screen.\n * Version: 2.1.0\n * Author: WordPress Team\n */\n";
		$this->create_dropin_file( 'maintenance.php', $content );

		$dropins = get_dropins();

		$this->assertArrayHasKey( 'maintenance.php', $dropins );
		$this->assertSame( 'Custom Maintenance', $dropins['maintenance.php']['Name'] );
		$this->assertSame( 'Drop-in maintenance screen.', $dropins['maintenance.php']['Description'] );
		$this->assertSame( '2.1.0', $dropins['maintenance.php']['Version'] );
		$this->assertSame( 'WordPress Team', $dropins['maintenance.php']['Author'] );
	}

	/**
	 * Tests that get_dropins() falls back to using the file name when no Plugin Name header is present.
	 *
	 * @ticket 65819
	 */
	public function test_get_dropins_falls_back_to_filename_when_header_empty() {
		$content = "<?php\n// Simple drop-in script with no plugin header comments.\necho 'Database error';\n";
		$this->create_dropin_file( 'db-error.php', $content );

		$dropins = get_dropins();

		$this->assertArrayHasKey( 'db-error.php', $dropins );
		$this->assertSame( 'db-error.php', $dropins['db-error.php']['Name'] );
	}

	/**
	 * Tests that get_dropins() ignores non-dropin files in WP_CONTENT_DIR.
	 *
	 * @ticket 65819
	 */
	public function test_get_dropins_ignores_non_dropin_files() {
		$content = "<?php\n/**\n * Plugin Name: Ignored File\n */\n";
		$this->create_dropin_file( 'custom-non-dropin.php', $content );

		$dropins = get_dropins();

		$this->assertArrayNotHasKey( 'custom-non-dropin.php', $dropins );
	}

	/**
	 * Tests that get_dropins() returns items sorted using natural case-insensitive comparison.
	 *
	 * @ticket 65819
	 */
	public function test_get_dropins_sorts_keys_naturally() {
		$this->create_dropin_file( 'maintenance.php', "<?php\n/**\n * Plugin Name: Maintenance\n */\n" );
		$this->create_dropin_file( 'db-error.php', "<?php\n/**\n * Plugin Name: DB Error\n */\n" );

		$dropins = get_dropins();
		$keys    = array_keys( $dropins );

		$db_error_pos    = array_search( 'db-error.php', $keys, true );
		$maintenance_pos = array_search( 'maintenance.php', $keys, true );

		$this->assertNotFalse( $db_error_pos );
		$this->assertNotFalse( $maintenance_pos );
		$this->assertLessThan( $maintenance_pos, $db_error_pos, 'db-error.php should precede maintenance.php when sorted.' );
	}

	/**
	 * Tests that get_dropins() returns an empty array when no drop-ins exist.
	 *
	 * @ticket 65819
	 */
	public function test_get_dropins_when_no_dropins_exist() {
		$this->assertSame( array(), get_dropins() );
	}

	/**
	 * Moves existing drop-ins to wp-content/drop-ins-backup.
	 */
	private static function _back_up_drop_ins() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$di_bu_dir = WP_CONTENT_DIR . '/drop-ins-backup';
		if ( ! is_dir( $di_bu_dir ) ) {
			mkdir( $di_bu_dir );
		}

		foreach ( _get_dropins() as $file_to_move => $v ) {
			if ( file_exists( WP_CONTENT_DIR . '/' . $file_to_move ) ) {
				rename( WP_CONTENT_DIR . '/' . $file_to_move, $di_bu_dir . '/' . $file_to_move );
			}
		}
	}

	/**
	 * Restores backed-up drop-ins.
	 */
	private static function _restore_drop_ins() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$di_bu_dir = WP_CONTENT_DIR . '/drop-ins-backup';

		if ( ! is_dir( $di_bu_dir ) ) {
			return;
		}

		foreach ( _get_dropins() as $file_to_move => $v ) {
			if ( file_exists( $di_bu_dir . '/' . $file_to_move ) ) {
				if ( file_exists( WP_CONTENT_DIR . '/' . $file_to_move ) ) {
					unlink( WP_CONTENT_DIR . '/' . $file_to_move );
				}
				rename( $di_bu_dir . '/' . $file_to_move, WP_CONTENT_DIR . '/' . $file_to_move );
			}
		}

		if ( is_dir( $di_bu_dir ) ) {
			rmdir( $di_bu_dir );
		}
	}
}
