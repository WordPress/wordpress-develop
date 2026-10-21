<?php

/**
 * Tests for editing plugin files with wp_edit_theme_plugin_file().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::wp_edit_theme_plugin_file
 */
class Tests_Admin_wpEditThemePluginFilePlugin extends WP_UnitTestCase {

	/**
	 * The slug of the test plugin.
	 *
	 * @var string
	 */
	const SLUG = 'editor-test-plugin';

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * Loopback requests made by the current test.
	 *
	 * @var string[]
	 */
	private $loopback_requests = array();

	/**
	 * Sets up the users.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Revokes the super admin grant before the fixture users are deleted.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}
	}

	/**
	 * Creates the test plugin and records loopback requests.
	 */
	public function set_up() {
		parent::set_up();

		$this->remove_plugin();

		$dir = WP_PLUGIN_DIR . '/' . self::SLUG;
		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );

		$files = array(
			self::SLUG . '.php' => "<?php\n/**\n * Plugin Name: Editor Test Plugin\n */\n",
			'readme.txt'        => "Original readme.\n",
			'logo.png'          => 'Not really an image.',
		);
		foreach ( $files as $name => $contents ) {
			$this->assertNotFalse( file_put_contents( $dir . '/' . $name, $contents ), "{$name} should be written." );
		}

		wp_cache_delete( 'plugins', 'plugins' );
		update_option( 'active_plugins', array() );

		$this->loopback_requests = array();
		add_filter( 'pre_http_request', array( $this, 'record_loopback' ), 10, 3 );

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Removes the test plugin.
	 */
	public function tear_down() {
		$this->remove_plugin();

		parent::tear_down();
	}

	/**
	 * Tests the errors for incomplete requests.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_incomplete_requests
	 *
	 * @param array  $args The arguments, merged over a valid request.
	 * @param string $code The expected error code.
	 */
	public function test_should_reject_incomplete_request( $args, $code ) {
		$request = array_merge( $this->get_request( 'readme.txt' ), $args );
		$request = array_filter(
			$request,
			static function ( $value ) {
				return null !== $value;
			}
		);

		$result = wp_edit_theme_plugin_file( $request );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( $code, $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( "Original readme.\n", $this->get_contents( 'readme.txt' ), 'The file should not change.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_requests() {
		return array(
			'no file'                  => array( array( 'file' => null ), 'missing_file' ),
			'empty file'               => array( array( 'file' => '' ), 'missing_file' ),
			'file outside the plugins' => array( array( 'file' => '../wp-config.php' ), 'bad_file' ),
			'no content'               => array( array( 'newcontent' => null ), 'missing_content' ),
			'no nonce'                 => array( array( 'nonce' => null ), 'missing_nonce' ),
			'no plugin or theme'       => array( array( 'plugin' => null ), 'missing_theme_or_plugin' ),
			'empty plugin'             => array( array( 'plugin' => '' ), 'missing_theme_or_plugin' ),
		);
	}

	/**
	 * Tests that a user who cannot edit plugins is rejected.
	 *
	 * @ticket 65819
	 */
	public function test_should_reject_user_without_capability() {
		wp_set_current_user( self::$subscriber_id );

		$result = wp_edit_theme_plugin_file( $this->get_request( 'readme.txt' ) );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'unauthorized', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( 'Sorry, you are not allowed to edit plugins for this site.', $result->get_error_message(), 'The error message should match.' );
	}

	/**
	 * Tests the errors for plugin edits that fail validation.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_invalid_plugin_edits
	 *
	 * @param array  $args    The arguments, merged over a valid request for readme.txt.
	 * @param string $code    The expected error code.
	 * @param string $message The expected error message.
	 */
	public function test_should_reject_invalid_plugin_edit( $args, $code, $message ) {
		$result = wp_edit_theme_plugin_file( array_merge( $this->get_request( 'readme.txt' ), $args ) );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( $code, $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( $message, $result->get_error_message(), 'The error message should match.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_plugin_edits() {
		return array(
			'nonce for another file'       => array( array( 'nonce' => 'invalid' ), 'nonce_failure', '' ),
			'plugin that is not installed' => array( array( 'plugin' => 'missing/missing.php' ), 'invalid_plugin', '' ),
		);
	}

	/**
	 * Tests that a file of another plugin is rejected with a valid nonce for it.
	 *
	 * @ticket 65819
	 */
	public function test_should_reject_file_of_another_plugin() {
		$request          = $this->get_request( 'hello.php' );
		$request['nonce'] = wp_create_nonce( 'edit-plugin_hello.php' );

		$result = wp_edit_theme_plugin_file( $request );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'bad_plugin_file_path', $result->get_error_code(), 'The error code should match.' );
	}

	/**
	 * Tests that a plugin file that is missing on disk is rejected.
	 *
	 * @ticket 65819
	 */
	public function test_should_reject_missing_file() {
		wp_cache_set( 'plugins', array( '' => array( 'ghost/ghost.php' => array( 'Name' => 'Ghost' ) ) ), 'plugins' );

		$result = wp_edit_theme_plugin_file(
			array(
				'plugin'     => 'ghost/ghost.php',
				'file'       => 'ghost/ghost.php',
				'newcontent' => 'New content.',
				'nonce'      => wp_create_nonce( 'edit-plugin_ghost/ghost.php' ),
			)
		);

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'file_does_not_exist', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( 'File does not exist! Please double check the name and try again.', $result->get_error_message(), 'The error message should match.' );
	}

	/**
	 * Tests that a file type that is not editable is rejected.
	 *
	 * @ticket 65819
	 */
	public function test_should_reject_file_type_that_is_not_editable() {
		$result = wp_edit_theme_plugin_file( $this->get_request( 'logo.png' ) );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'illegal_file_type', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( 'Files of this type are not editable.', $result->get_error_message(), 'The error message should match.' );
		$this->assertSame( 'Not really an image.', $this->get_contents( 'logo.png' ), 'The file should not change.' );
	}

	/**
	 * Tests that the editable file types can be filtered per plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_apply_editable_extensions_filter() {
		$filter = new MockAction();
		add_filter( 'editable_extensions', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'editable_extensions',
			static function ( $types ) {
				return array_diff( $types, array( 'txt' ) );
			},
			11
		);

		$result = wp_edit_theme_plugin_file( $this->get_request( 'readme.txt' ) );
		$args   = $filter->get_args();

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'illegal_file_type', $result->get_error_code(), 'Text files should no longer be editable.' );
		$this->assertSame( $this->get_plugin(), $args[0][1], 'The filter should receive the plugin.' );
	}

	/**
	 * Tests that a file of an inactive plugin is saved without a loopback check.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_files
	 *
	 * @param string $file The file, relative to the plugin directory.
	 */
	public function test_should_save_file_of_inactive_plugin( $file ) {
		$this->assertTrue( wp_edit_theme_plugin_file( $this->get_request( $file, "New content.\n" ) ), 'The file should be saved.' );
		$this->assertSame( "New content.\n", $this->get_contents( $file ), 'The new content should be written.' );
		$this->assertSame( array(), $this->loopback_requests, 'No loopback request should be made.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_files() {
		return array(
			'text file'        => array( 'readme.txt' ),
			'main plugin file' => array( self::SLUG . '.php' ),
		);
	}

	/**
	 * Tests that a non-PHP file of an active plugin is saved without a loopback check.
	 *
	 * @ticket 65819
	 */
	public function test_should_save_non_php_file_of_active_plugin_without_loopback() {
		update_option( 'active_plugins', array( $this->get_plugin() ) );

		$this->assertTrue( wp_edit_theme_plugin_file( $this->get_request( 'readme.txt', "New content.\n" ) ), 'The file should be saved.' );
		$this->assertSame( "New content.\n", $this->get_contents( 'readme.txt' ), 'The new content should be written.' );
		$this->assertSame( array(), $this->loopback_requests, 'No loopback request should be made.' );
	}

	/**
	 * Records loopback requests and answers them as successful scrapes.
	 *
	 * @param false|array $response A preemptive response.
	 * @param array       $args     The request arguments.
	 * @param string      $url      The request URL.
	 * @return false|array The response.
	 */
	public function record_loopback( $response, $args, $url ) {
		if ( ! str_contains( $url, 'wp_scrape_key' ) ) {
			return $response;
		}

		$this->loopback_requests[] = $url;

		return new WP_Error( 'loopback_blocked', 'Loopback requests are not made in tests.' );
	}

	/**
	 * Returns a valid request to edit a file of the test plugin.
	 *
	 * @param string $file    The file, relative to the plugin directory.
	 * @param string $content The new content.
	 * @return array The request.
	 */
	private function get_request( $file, $content = 'New content.' ) {
		$path = 'hello.php' === $file ? $file : self::SLUG . '/' . $file;

		return array(
			'plugin'     => $this->get_plugin(),
			'file'       => $path,
			'newcontent' => $content,
			'nonce'      => wp_create_nonce( 'edit-plugin_' . $path ),
		);
	}

	/**
	 * Returns the plugin file of the test plugin.
	 *
	 * @return string The plugin file.
	 */
	private function get_plugin() {
		return self::SLUG . '/' . self::SLUG . '.php';
	}

	/**
	 * Returns the contents of a file of the test plugin.
	 *
	 * @param string $file The file, relative to the plugin directory.
	 * @return string The contents.
	 */
	private function get_contents( $file ) {
		return file_get_contents( WP_PLUGIN_DIR . '/' . self::SLUG . '/' . $file );
	}

	/**
	 * Removes the test plugin and clears the plugins cache.
	 */
	private function remove_plugin() {
		$dir = WP_PLUGIN_DIR . '/' . self::SLUG;

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			rmdir( $dir );
		}

		wp_cache_delete( 'plugins', 'plugins' );
	}
}
