<?php

/**
 * Tests wp_opcache_invalidate_directory().
 *
 * @group file.php
 *
 * @covers ::wp_opcache_invalidate_directory
 */
class Tests_Filesystem_WpOpcacheInvalidateDirectory extends WP_UnitTestCase {

	/**
	 * Sets up the filesystem before any tests run.
	 */
	public static function set_up_before_class() {
		global $wp_filesystem;

		parent::set_up_before_class();

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
	}

	/**
	 * Tests that wp_opcache_invalidate_directory() returns a WP_Error object
	 * when the $dir argument invalid.
	 *
	 * @ticket 57375
	 *
	 * @dataProvider data_should_trigger_error_with_invalid_dir
	 *
	 * @param mixed $dir An invalid directory path.
	 */
	public function test_should_trigger_error_with_invalid_dir( $dir ) {
		$this->expectError();
		$this->expectErrorMessage(
			'<code>wp_opcache_invalidate_directory()</code>',
			'The expected error was not triggered.'
		);

		wp_opcache_invalidate_directory( $dir );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_trigger_error_with_invalid_dir() {
		return array(
			'an empty string'                => array( '' ),
			'a string with spaces'           => array( '   ' ),
			'a string with tabs'             => array( "\t" ),
			'a string with new lines'        => array( "\n" ),
			'a string with carriage returns' => array( "\r" ),
			'int -1'                         => array( -1 ),
			'int 0'                          => array( 0 ),
			'int 1'                          => array( 1 ),
			'float -1.0'                     => array( -1.0 ),
			'float 0.0'                      => array( 0.0 ),
			'float 1.0'                      => array( 1.0 ),
			'false'                          => array( false ),
			'true'                           => array( true ),
			'null'                           => array( null ),
			'an empty array'                 => array( array() ),
			'a non-empty array'              => array( array( 'directory_path' ) ),
			'an empty object'                => array( new stdClass() ),
			'a non-empty object'             => array( (object) array( 'directory_path' ) ),
			'INF'                            => array( INF ),
			'NAN'                            => array( NAN ),
		);
	}

	/**
	 * Tests that wp_opcache_invalidate_directory() does not trigger an error
	 * with a valid directory.
	 *
	 * @ticket 57375
	 *
	 * @dataProvider data_should_not_trigger_error_wp_opcache_valid_directory
	 *
	 * @param string $dir A directory path.
	 */
	public function test_should_not_trigger_error_wp_opcache_valid_directory( $dir ) {
		$this->assertNull( wp_opcache_invalidate_directory( $dir ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_not_trigger_error_wp_opcache_valid_directory() {
		return array(
			'an existing directory'    => array( DIR_TESTDATA ),
			'a non-existent directory' => array( 'non_existent_directory' ),
		);
	}
}
