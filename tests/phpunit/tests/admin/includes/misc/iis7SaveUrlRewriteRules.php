<?php

/**
 * Tests for the iis7_save_url_rewrite_rules() function.
 *
 * @group admin
 * @group rewrite
 * @group iis
 *
 * @covers ::iis7_save_url_rewrite_rules
 */
class Tests_iis7_save_url_rewrite_rules extends WP_UnitTestCase {

	/**
	 * Temporary home directory.
	 *
	 * @var string
	 */
	private $temp_home;

	/**
	 * Original $_SERVER['SCRIPT_FILENAME']
	 *
	 * @var string|null
	 */
	private $orig_script_filename;

	/**
	 * Original $_SERVER['IIS_WasUrlRewritten']
	 *
	 * @var string|null
	 */
	private $orig_iis_rewritten;

	/**
	 * Original $_SERVER['IIS_UrlRewriteModule']
	 *
	 * @var string|null
	 */
	private $orig_iis_url_rewrite;

	/**
	 * Original $is_iis7 global.
	 *
	 * @var bool|null
	 */
	private $orig_is_iis7;

	public function set_up() {
		parent::set_up();

		global $is_iis7;

		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$this->orig_script_filename = isset( $_SERVER['SCRIPT_FILENAME'] ) ? $_SERVER['SCRIPT_FILENAME'] : null;
		$this->orig_iis_rewritten   = isset( $_SERVER['IIS_WasUrlRewritten'] ) ? $_SERVER['IIS_WasUrlRewritten'] : null;
		$this->orig_iis_url_rewrite = isset( $_SERVER['IIS_UrlRewriteModule'] ) ? $_SERVER['IIS_UrlRewriteModule'] : null;
		$this->orig_is_iis7         = isset( $is_iis7 ) ? $is_iis7 : null;

		$this->temp_home = get_temp_dir() . 'wp_iis_test_home_' . uniqid( '', true ) . '/';
		mkdir( $this->temp_home );
		mkdir( $this->temp_home . 'wp' );

		update_option( 'home', 'http://localhost' );
		update_option( 'siteurl', 'http://localhost/wp' );
		$_SERVER['SCRIPT_FILENAME'] = $this->temp_home . 'wp/index.php';
	}

	public function tear_down() {
		if ( $this->temp_home && is_dir( $this->temp_home ) ) {
			$this->recursive_delete( $this->temp_home );
		}

		global $is_iis7;

		if ( null !== $this->orig_script_filename ) {
			$_SERVER['SCRIPT_FILENAME'] = $this->orig_script_filename;
		} else {
			unset( $_SERVER['SCRIPT_FILENAME'] );
		}
		if ( null !== $this->orig_iis_rewritten ) {
			$_SERVER['IIS_WasUrlRewritten'] = $this->orig_iis_rewritten;
		} else {
			unset( $_SERVER['IIS_WasUrlRewritten'] );
		}
		if ( null !== $this->orig_iis_url_rewrite ) {
			$_SERVER['IIS_UrlRewriteModule'] = $this->orig_iis_url_rewrite;
		} else {
			unset( $_SERVER['IIS_UrlRewriteModule'] );
		}
		if ( null !== $this->orig_is_iis7 ) {
			$is_iis7 = $this->orig_is_iis7;
		}

		add_filter( 'flush_rewrite_rules_hard', '__return_false' );
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
	 * Tests that iis7_save_url_rewrite_rules() correctly writes rules to web.config when file does not exist.
	 *
	 * @ticket 65147
	 *
	 * @group ms-excluded
	 */
	public function test_iis7_save_url_rewrite_rules_success() {
		$this->skipWithMultisite();

		global $wp_rewrite, $is_iis7;

		// Simulate IIS7 with permalink support.
		$is_iis7                         = true;
		$_SERVER['IIS_UrlRewriteModule'] = '1';

		// Mock iis7_supports_permalinks() because test runner is not on cgi-fcgi SAPI.
		add_filter( 'iis7_supports_permalinks', '__return_true' );

		// Setup permalinks.
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		$web_config_file = $this->temp_home . 'web.config';

		$result = iis7_save_url_rewrite_rules();

		$this->assertTrue( $result, 'iis7_save_url_rewrite_rules() should return true on success.' );
		$this->assertFileExists( $web_config_file );

		$content = file_get_contents( $web_config_file );
		$this->assertStringContainsString( 'name="WordPress', $content );
		$this->assertStringContainsString( '<match url="*', $content );
		$this->assertStringContainsString( '<action type="Rewrite" url="index.php"', $content );
	}

	/**
	 * Tests that iis7_save_url_rewrite_rules() updates an existing web.config while preserving unrelated rules.
	 *
	 * @ticket 65147
	 *
	 * @group ms-excluded
	 */
	public function test_iis7_save_url_rewrite_rules_existing_config() {
		$this->skipWithMultisite();

		global $wp_rewrite, $is_iis7;

		$is_iis7                         = true;
		$_SERVER['IIS_UrlRewriteModule'] = '1';

		add_filter( 'iis7_supports_permalinks', '__return_true' );

		$web_config_file = $this->temp_home . 'web.config';
		$initial_content = '<configuration><system.webServer><rewrite><rules><rule name="custom_rule" /></rules></rewrite></system.webServer></configuration>';
		file_put_contents( $web_config_file, $initial_content );

		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		$result = iis7_save_url_rewrite_rules();

		$this->assertTrue( $result, 'iis7_save_url_rewrite_rules() should return true on success.' );
		$this->assertFileExists( $web_config_file );

		$content = file_get_contents( $web_config_file );
		$this->assertStringContainsString( 'name="custom_rule"', $content );
		$this->assertStringContainsString( 'name="WordPress', $content );
	}

	/**
	 * Tests that iis7_save_url_rewrite_rules() removes WordPress rules when plain permalinks are enabled.
	 *
	 * @ticket 65147
	 *
	 * @group ms-excluded
	 */
	public function test_iis7_save_url_rewrite_rules_deletes_rules_when_plain_permalinks() {
		$this->skipWithMultisite();

		global $wp_rewrite, $is_iis7;

		$is_iis7                         = true;
		$_SERVER['IIS_UrlRewriteModule'] = '1';

		add_filter( 'iis7_supports_permalinks', '__return_true' );

		$web_config_file = $this->temp_home . 'web.config';
		$initial_content = '<configuration><system.webServer><rewrite><rules><rule name="WordPress Rule 1" /><rule name="custom_rule" /></rules></rewrite></system.webServer></configuration>';
		file_put_contents( $web_config_file, $initial_content );

		$wp_rewrite->set_permalink_structure( '' );

		$result = iis7_save_url_rewrite_rules();

		$this->assertTrue( $result, 'iis7_save_url_rewrite_rules() should return true when deleting rules.' );
		$this->assertFileExists( $web_config_file );

		$content = file_get_contents( $web_config_file );
		$this->assertStringContainsString( 'name="custom_rule"', $content );
		$this->assertStringNotContainsString( 'name="WordPress', $content );
	}

	/**
	 * Tests that iis7_save_url_rewrite_rules() returns false when not on IIS.
	 *
	 * @ticket 65147
	 *
	 * @group ms-excluded
	 */
	public function test_iis7_save_url_rewrite_rules_not_iis() {
		$this->skipWithMultisite();

		global $wp_rewrite, $is_iis7;

		$is_iis7 = false;

		// Configure permalinks and create a writable web.config so failure is specifically due to non-IIS environment.
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		file_put_contents( $this->temp_home . 'web.config', '<configuration/>' );

		$result = iis7_save_url_rewrite_rules();

		$this->assertFalse( $result, 'iis7_save_url_rewrite_rules() should return false when not on IIS.' );
	}

	/**
	 * Tests that iis7_save_url_rewrite_rules() returns null in multisite.
	 *
	 * @ticket 65147
	 *
	 * @group ms-required
	 */
	public function test_iis7_save_url_rewrite_rules_multisite() {
		$this->skipWithoutMultisite();

		$this->assertNull( iis7_save_url_rewrite_rules() );
	}
}
