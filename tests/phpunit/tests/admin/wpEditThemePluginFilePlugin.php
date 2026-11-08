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
	 * The slug of the second test plugin.
	 *
	 * @var string
	 */
	const OTHER_SLUG = 'editor-other-plugin';

	/**
	 * The edited content of the main plugin file.
	 *
	 * @var string
	 */
	const EDITED_PHP = "<?php\n/**\n * Plugin Name: Editor Test Plugin\n */\n// Edited.\n";

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
	 * The scrape results returned by the next loopback requests, in order.
	 *
	 * A null result makes the request fail.
	 *
	 * @var array
	 */
	private $scrape_results = array();

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

		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			$this->markTestSkipped( 'File editing is disabled via DISALLOW_FILE_EDIT.' );
		}

		if ( ! is_writable( WP_PLUGIN_DIR ) ) {
			$this->markTestSkipped( 'WP_PLUGIN_DIR is not writable.' );
		}

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

		$other_dir = WP_PLUGIN_DIR . '/' . self::OTHER_SLUG;
		$this->assertTrue( mkdir( $other_dir ), 'The second test plugin directory should be created.' );
		$this->assertNotFalse(
			file_put_contents(
				$other_dir . '/' . self::OTHER_SLUG . '.php',
				"<?php\n/**\n * Plugin Name: Editor Other Plugin\n */\n"
			),
			'The second test plugin file should be written.'
		);

		wp_cache_delete( 'plugins', 'plugins' );
		update_option( 'active_plugins', array() );

		$this->loopback_requests = array();
		$this->scrape_results    = array();
		add_filter( 'pre_http_request', array( $this, 'record_loopback' ), 10, 3 );

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Removes the test plugin.
	 */
	public function tear_down() {
		unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );

		if ( is_multisite() ) {
			update_site_option( 'active_sitewide_plugins', array() );
		}

		$this->remove_plugin();

		parent::tear_down();

		$this->assertEmpty( $this->scrape_results, 'All queued scrape results should be consumed.' );
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
	 * Tests that a request with a nonce for another file is rejected.
	 *
	 * @ticket 65819
	 */
	public function test_should_reject_nonce_for_another_file() {
		$request          = $this->get_request( 'readme.txt' );
		$request['nonce'] = wp_create_nonce( 'edit-plugin_' . $this->get_plugin() );

		$result = wp_edit_theme_plugin_file( $request );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'nonce_failure', $result->get_error_code(), 'The error code should match.' );
	}

	/**
	 * Tests that editing an uninstalled plugin is rejected.
	 *
	 * @ticket 65819
	 */
	public function test_should_reject_uninstalled_plugin() {
		$result = wp_edit_theme_plugin_file(
			array_merge(
				$this->get_request( 'readme.txt' ),
				array( 'plugin' => 'missing/missing.php' )
			)
		);

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'invalid_plugin', $result->get_error_code(), 'The error code should match.' );
	}

	/**
	 * Tests that a file of another plugin is rejected with a valid nonce for it.
	 *
	 * @ticket 65819
	 */
	public function test_should_reject_file_of_another_plugin() {
		$other_file       = self::OTHER_SLUG . '/' . self::OTHER_SLUG . '.php';
		$other_filepath   = WP_PLUGIN_DIR . '/' . $other_file;
		$original_content = file_get_contents( $other_filepath );

		$result = wp_edit_theme_plugin_file(
			array(
				'plugin'     => $this->get_plugin(),
				'file'       => $other_file,
				'newcontent' => 'New content.',
				'nonce'      => wp_create_nonce( 'edit-plugin_' . $other_file ),
			)
		);

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'bad_plugin_file_path', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( 'Sorry, that file cannot be edited.', $result->get_error_message(), 'The error message should match.' );
		$this->assertSame( $original_content, file_get_contents( $other_filepath ), 'The other plugin file should be unchanged.' );
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
	 * Tests that a PHP file of an active plugin is saved when both loopback checks pass.
	 *
	 * @ticket 65819
	 */
	public function test_should_save_php_file_of_active_plugin_when_loopback_passes() {
		update_option( 'active_plugins', array( $this->get_plugin() ) );
		$this->scrape_results = array( 'true', 'true' );

		$this->assertTrue( wp_edit_theme_plugin_file( $this->get_request( self::SLUG . '.php', self::EDITED_PHP ) ), 'The file should be saved.' );
		$this->assertSame( self::EDITED_PHP, $this->get_contents( self::SLUG . '.php' ), 'The new content should be written.' );
		$this->assertSame(
			array(
				wp_parse_url( admin_url( 'plugin-editor.php' ), PHP_URL_PATH ),
				wp_parse_url( home_url( '/' ), PHP_URL_PATH ),
			),
			$this->get_loopback_paths(),
			'The editor and the home page should be checked.'
		);
		$this->assert_scrape_key_deleted();
	}

	/**
	 * Tests that a Basic Authorization header is included in loopback requests when PHP_AUTH credentials exist.
	 *
	 * @ticket 65819
	 */
	public function test_should_include_basic_auth_in_loopback_requests() {
		$_SERVER['PHP_AUTH_USER'] = 'testuser';
		$_SERVER['PHP_AUTH_PW']   = 'testpass';

		update_option( 'active_plugins', array( $this->get_plugin() ) );
		$this->scrape_results = array( 'true', 'true' );

		$captured_headers = array();
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( &$captured_headers ) {
				if ( str_contains( $url, 'wp_scrape_key' ) ) {
					$captured_headers[] = $args['headers'];
				}
				return $response;
			},
			5,
			3
		);

		$result = wp_edit_theme_plugin_file( $this->get_request( self::SLUG . '.php', self::EDITED_PHP ) );

		$this->assertTrue( $result, 'The file should be saved.' );
		$this->assertNotEmpty( $captured_headers, 'Loopback requests should be made.' );
		$expected_auth = 'Basic ' . base64_encode( 'testuser:testpass' );
		foreach ( $captured_headers as $headers ) {
			$this->assertArrayHasKey( 'Authorization', $headers, 'Basic Auth header should be included.' );
			$this->assertSame( $expected_auth, $headers['Authorization'], 'Basic Auth header should match.' );
		}
	}

	/**
	 * Tests that a PHP file of a network-active plugin triggers loopback checks on multisite.
	 *
	 * @ticket 65819
	 * @group ms-required
	 */
	public function test_should_trigger_loopback_for_network_active_plugin() {
		update_site_option( 'active_sitewide_plugins', array( $this->get_plugin() => time() ) );
		$this->scrape_results = array( 'true', 'true' );

		$this->assertTrue( wp_edit_theme_plugin_file( $this->get_request( self::SLUG . '.php', self::EDITED_PHP ) ), 'The file should be saved.' );
		$this->assertSame( self::EDITED_PHP, $this->get_contents( self::SLUG . '.php' ), 'The new content should be written.' );
		$this->assertSame(
			array(
				wp_parse_url( admin_url( 'plugin-editor.php' ), PHP_URL_PATH ),
				wp_parse_url( home_url( '/' ), PHP_URL_PATH ),
			),
			$this->get_loopback_paths(),
			'The editor and the home page should be checked.'
		);
		$this->assert_scrape_key_deleted();
	}

	/**
	 * Tests that a PHP file edit of an active plugin is reverted when a loopback check fails.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_failed_loopbacks
	 *
	 * @param array    $scrape_results The scrape results of the loopback requests.
	 * @param string   $message        The expected error message.
	 * @param array    $data           The expected error data.
	 * @param string[] $paths          The expected paths of the loopback requests.
	 */
	public function test_should_revert_php_file_of_active_plugin_when_loopback_fails( $scrape_results, $message, $data, $paths ) {
		update_option( 'active_plugins', array( $this->get_plugin() ) );
		$original             = $this->get_contents( self::SLUG . '.php' );
		$this->scrape_results = $scrape_results;

		$result = wp_edit_theme_plugin_file( $this->get_request( self::SLUG . '.php', self::EDITED_PHP ) );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'php_error', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( $message, $result->get_error_message(), 'The error message should match.' );
		$this->assertSame( $data, $result->get_error_data(), 'The error data should match.' );
		$this->assertSame( $original, $this->get_contents( self::SLUG . '.php' ), 'The previous content should be restored.' );
		$this->assertSame( $paths, $this->get_loopback_paths(), 'The loopback requests should match.' );
		$this->assert_scrape_key_deleted();
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_failed_loopbacks() {
		$loopback_failed = 'Unable to communicate back with site to check for fatal errors, so the PHP change was reverted. You will need to upload your PHP file change by some other means, such as by using SFTP.';
		$editor_path     = wp_parse_url( admin_url( 'plugin-editor.php' ), PHP_URL_PATH );
		$home_path       = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		return array(
			'editor request fails'       => array(
				array( null ),
				$loopback_failed,
				array( 'code' => 'loopback_request_failed' ),
				array( $editor_path ),
			),
			'editor reports an error'    => array(
				array( '{"code":"E_ERROR","message":"Fatal error.","line":5}' ),
				'Fatal error.',
				array(
					'code' => 'E_ERROR',
					'line' => 5,
				),
				array( $editor_path ),
			),
			'editor returns no JSON'     => array(
				array( 'not JSON' ),
				'An error occurred. Please try again later.',
				array( 'code' => 'json_parse_error' ),
				array( $editor_path ),
			),
			'home page returns no JSON'  => array(
				array( 'true', 'not JSON' ),
				'An error occurred. Please try again later.',
				array( 'code' => 'json_parse_error' ),
				array( $editor_path, $home_path ),
			),
			'home page request fails'    => array(
				array( 'true', null ),
				$loopback_failed,
				array( 'code' => 'loopback_request_failed' ),
				array( $editor_path, $home_path ),
			),
			'home page reports an error' => array(
				array( 'true', '{"code":"E_PARSE","message":"Parse error."}' ),
				'Parse error.',
				array( 'code' => 'E_PARSE' ),
				array( $editor_path, $home_path ),
			),
		);
	}

	/**
	 * Records loopback requests and answers them with the next scrape result.
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

		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$this->assertArrayHasKey( 'wp_scrape_key', $query, 'The loopback should include the scrape key.' );
		$this->assertArrayHasKey( 'wp_scrape_nonce', $query, 'The loopback should include the scrape nonce.' );

		$editor_path = wp_parse_url( admin_url( 'plugin-editor.php' ), PHP_URL_PATH );
		if ( wp_parse_url( $url, PHP_URL_PATH ) === $editor_path ) {
			$this->assertArrayHasKey( 'plugin', $query, 'The editor loopback should include the plugin query arg.' );
			$this->assertSame( $this->get_plugin(), $query['plugin'], 'The editor loopback should specify the edited plugin.' );
			$this->assertArrayHasKey( 'file', $query, 'The editor loopback should include the file query arg.' );
			$this->assertSame( self::SLUG . '/' . self::SLUG . '.php', $query['file'], 'The editor loopback should specify the edited file.' );
		}

		$key = $query['wp_scrape_key'];
		$this->assertSame( $query['wp_scrape_nonce'], get_transient( 'scrape_key_' . $key ), 'The scrape nonce should match its live transient.' );

		$scrape_result = array_shift( $this->scrape_results );
		if ( null === $scrape_result ) {
			return new WP_Error( 'loopback_blocked', 'Loopback requests are not made in tests.' );
		}

		return array(
			'headers'  => array(),
			'body'     => "<html></html>\n###### wp_scraping_result_start:{$key} ######\n{$scrape_result}\n###### wp_scraping_result_end:{$key} ######\n",
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Returns the scrape key of a loopback request.
	 *
	 * @param string $url The request URL.
	 * @return string The scrape key.
	 */
	private function get_scrape_key( $url ) {
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		return $query['wp_scrape_key'] ?? '';
	}

	/**
	 * Returns the paths of the loopback requests.
	 *
	 * @return string[] The paths.
	 */
	private function get_loopback_paths() {
		return array_map(
			static function ( $url ) {
				return wp_parse_url( $url, PHP_URL_PATH );
			},
			$this->loopback_requests
		);
	}

	/**
	 * Asserts that the scrape key transient of the loopback requests was deleted.
	 */
	private function assert_scrape_key_deleted() {
		$this->assertNotEmpty( $this->loopback_requests, 'A loopback request should be made.' );
		$this->assertFalse( get_transient( 'scrape_key_' . $this->get_scrape_key( $this->loopback_requests[0] ) ), 'The scrape key should be deleted.' );
	}

	/**
	 * Returns a valid request to edit a file of the test plugin.
	 *
	 * @param string $file    The file, relative to the plugin directory.
	 * @param string $content The new content.
	 * @return array The request.
	 */
	private function get_request( $file, $content = 'New content.' ) {
		$path = self::SLUG . '/' . $file;

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
		foreach ( array( self::SLUG, self::OTHER_SLUG ) as $slug ) {
			$dir = WP_PLUGIN_DIR . '/' . $slug;

			if ( is_dir( $dir ) ) {
				$this->rmdir( $dir );
				$this->delete_folders( $dir );
			}
		}

		wp_cache_delete( 'plugins', 'plugins' );
	}
}
