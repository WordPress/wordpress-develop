<?php

/**
 * Tests for the save_mod_rewrite_rules() function.
 *
 * @group admin
 * @group rewrite
 *
 * @covers ::save_mod_rewrite_rules
 */
class Tests_save_mod_rewrite_rules extends WP_UnitTestCase {

	/**
	 * Original home path.
	 * @var string|bool
	 */
	private $orig_home;

	/**
	 * Original siteurl path.
	 * @var string|bool
	 */
	private $orig_siteurl;

	/**
	 * Temporary home directory.
	 * @var string
	 */
	private $temp_home;

	/**
	 * Original $_SERVER['SCRIPT_FILENAME']
	 * @var string|null
	 */
	private $orig_script_filename;

	/**
	 * Original $_SERVER['SERVER_SOFTWARE']
	 * @var string|null
	 */
	private $orig_server_software;

	public function set_up() {
		parent::set_up();

		// Multisite test should be handled in test method if possible or skipped if globally multisite.
		if ( is_multisite() ) {
			// This test class is not intended for multisite.
			return;
		}

		// Ensure the function is available.
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$this->orig_home            = get_option( 'home' );
		$this->orig_siteurl         = get_option( 'siteurl' );
		$this->orig_script_filename = isset( $_SERVER['SCRIPT_FILENAME'] ) ? $_SERVER['SCRIPT_FILENAME'] : null;
		$this->orig_server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null;

		$this->temp_home = get_temp_dir() . 'wp_test_home/';
		if ( ! is_dir( $this->temp_home ) ) {
			mkdir( $this->temp_home );
		}
	}

	public function tear_down() {
		if ( $this->temp_home && is_dir( $this->temp_home ) ) {
			$this->recursive_delete( $this->temp_home );
		}

		update_option( 'home', $this->orig_home );
		update_option( 'siteurl', $this->orig_siteurl );
		if ( null !== $this->orig_script_filename ) {
			$_SERVER['SCRIPT_FILENAME'] = $this->orig_script_filename;
		} else {
			unset( $_SERVER['SCRIPT_FILENAME'] );
		}
		if ( null !== $this->orig_server_software ) {
			$_SERVER['SERVER_SOFTWARE'] = $this->orig_server_software;
		} else {
			unset( $_SERVER['SERVER_SOFTWARE'] );
		}

		parent::tear_down();
	}

	/**
	 * Helper to delete a directory and its contents.
	 *
	 * @param string $dir Path to the directory.
	 */
	private function recursive_delete( $dir ) {
		foreach ( scandir( $dir ) as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if ( is_dir( $path ) ) {
				$this->recursive_delete( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}

	/**
	 * Tests that save_mod_rewrite_rules() correctly writes rules to .htaccess.
	 *
	 * @ticket 65146
	 */
	public function test_save_mod_rewrite_rules_success() {
		global $wp_rewrite;

		// Setup temporary home.
		$home_url = 'http://example.org';
		$site_url = 'http://example.org/wp';
		update_option( 'home', $home_url );
		update_option( 'siteurl', $site_url );

		$_SERVER['SCRIPT_FILENAME'] = $this->temp_home . 'wp/index.php';
		if ( ! is_dir( $this->temp_home . 'wp' ) ) {
			mkdir( $this->temp_home . 'wp' );
		}

		$_SERVER['SERVER_SOFTWARE'] = 'Apache';

		// Mock got_mod_rewrite().
		add_filter( 'got_rewrite', '__return_true' );

		// Mock WP_Rewrite rules.
		$orig_structure = $wp_rewrite->permalink_structure;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		$htaccess_file = $this->temp_home . '.htaccess';
		touch( $htaccess_file );

		$result = save_mod_rewrite_rules();

		// Cleanup structure.
		$wp_rewrite->set_permalink_structure( $orig_structure );
		remove_filter( 'got_rewrite', '__return_true' );

		$this->assertTrue( $result, 'save_mod_rewrite_rules() should return true on success.' );
		$this->assertFileExists( $htaccess_file );

		$content = file_get_contents( $htaccess_file );
		$this->assertStringContainsString( '# BEGIN WordPress', $content );
		$this->assertStringContainsString( 'RewriteEngine On', $content );
	}

	/**
	 * Tests that save_mod_rewrite_rules() returns false if mod_rewrite is not available.
	 *
	 * @ticket 65146
	 */
	public function test_save_mod_rewrite_rules_no_mod_rewrite() {
		// Mock got_mod_rewrite() as false.
		add_filter( 'got_rewrite', '__return_false' );

		// Setup path.
		$home_url = 'http://example.org';
		$site_url = 'http://example.org/wp';
		update_option( 'home', $home_url );
		update_option( 'siteurl', $site_url );
		$_SERVER['SCRIPT_FILENAME'] = $this->temp_home . 'wp/index.php';
		if ( ! is_dir( $this->temp_home . 'wp' ) ) {
			mkdir( $this->temp_home . 'wp' );
		}

		touch( $this->temp_home . '.htaccess' );

		$_SERVER['SERVER_SOFTWARE'] = 'Apache';

		$result = save_mod_rewrite_rules();

		remove_filter( 'got_rewrite', '__return_false' );

		$this->assertFalse( $result, 'save_mod_rewrite_rules() should return false when mod_rewrite is not available.' );
	}
	/**
	 * Tests that save_mod_rewrite_rules() returns false if the .htaccess file is not writable.
	 *
	 * @ticket 65146
	 */
	public function test_save_mod_rewrite_rules_non_writable() {
		// Mock is_writable to return false.
		// Since we can't easily mock is_writable, we try the chmod approach.
		// If it is skipped, we'll know.
		if ( 'root' === posix_getpwuid( posix_geteuid() )['name'] ) {
			$this->markTestSkipped( 'Cannot test non-writable files as root.' );
		}

		// Setup path.
		$home_url = 'http://example.org';
		$site_url = 'http://example.org/wp';
		update_option( 'home', $home_url );
		update_option( 'siteurl', $site_url );
		$_SERVER['SCRIPT_FILENAME'] = $this->temp_home . 'wp/index.php';
		if ( ! is_dir( $this->temp_home . 'wp' ) ) {
			mkdir( $this->temp_home . 'wp' );
		}

		$htaccess_file = $this->temp_home . '.htaccess';
		touch( $htaccess_file );
		chmod( $htaccess_file, 0444 ); // Read-only.

		$_SERVER['SERVER_SOFTWARE'] = 'Apache';

		// Mock got_mod_rewrite().
		add_filter( 'got_rewrite', '__return_true' );

		$result = save_mod_rewrite_rules();

		chmod( $htaccess_file, 0666 ); // Restore for cleanup.
		remove_filter( 'got_rewrite', '__return_true' );

		$this->assertFalse( $result, 'save_mod_rewrite_rules() should return false when .htaccess is not writable.' );
	}

	/**
	 * Tests that save_mod_rewrite_rules() returns false if the directory is not writable and file doesn't exist.
	 *
	 * @ticket 65146
	 */
	public function test_save_mod_rewrite_rules_non_writable_dir() {
		if ( 'root' === posix_getpwuid( posix_geteuid() )['name'] ) {
			$this->markTestSkipped( 'Cannot test non-writable directories as root.' );
		}

		$home_dir = $this->temp_home . 'non_writable_home/';
		mkdir( $home_dir );

		// Setup path.
		$home_url = 'http://example.org';
		$site_url = 'http://example.org/wp';
		update_option( 'home', $home_url );
		update_option( 'siteurl', $site_url );
		$_SERVER['SCRIPT_FILENAME'] = $home_dir . 'wp/index.php';
		mkdir( $home_dir . 'wp' );

		chmod( $home_dir, 0555 ); // Read-only dir.

		$_SERVER['SERVER_SOFTWARE'] = 'Apache';

		// Mock got_mod_rewrite().
		add_filter( 'got_rewrite', '__return_true' );

		$result = save_mod_rewrite_rules();

		chmod( $home_dir, 0777 ); // Restore for cleanup.
		remove_filter( 'got_rewrite', '__return_true' );

		$this->assertFalse( $result, 'save_mod_rewrite_rules() should return false when home directory is not writable.' );
	}
	/**
	 * Tests that save_mod_rewrite_rules() returns null in multisite.
	 *
	 * @ticket 65146
	 */
	public function test_save_mod_rewrite_rules_multisite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$this->assertNull( save_mod_rewrite_rules() );
	}
}
