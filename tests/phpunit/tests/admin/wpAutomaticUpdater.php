<?php

/**
 * @group upgrade
 *
 * @covers WP_Automatic_Updater
 */
class Tests_Admin_WpAutomaticUpdater extends WP_UnitTestCase {
	/**
	 * An instance of WP_Automatic_Updater.
	 *
	 * @var WP_Automatic_Updater
	 */
	private static $updater;

	/**
	 * WP_Automatic_Updater::send_plugin_theme_email
	 * made accessible.
	 *
	 * @var ReflectionMethod
	 */
	private static $send_plugin_theme_email;

	/**
	 * Sets up shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php';
		self::$updater = new WP_Automatic_Updater();

		self::$send_plugin_theme_email = new ReflectionMethod( self::$updater, 'send_plugin_theme_email' );
		self::$send_plugin_theme_email->setAccessible( true );
	}

	public function set_up() {
		parent::set_up();
		add_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Tests that `WP_Automatic_Updater::send_plugin_theme_email()` appends
	 * plugin URLs.
	 *
	 * @ticket 53049
	 *
	 * @covers WP_Automatic_Updater::send_plugin_theme_email
	 *
	 * @dataProvider data_send_plugin_theme_email_should_append_plugin_urls
	 *
	 * @param string[] $urls       The URL(s) to search for. Must not be empty.
	 * @param object[] $successful An array of successful plugin update objects.
	 * @param object[] $failed     An array of failed plugin update objects.
	 */
	public function test_send_plugin_theme_email_should_append_plugin_urls( $urls, $successful, $failed ) {
		add_filter(
			'wp_mail',
			function( $args ) use ( $urls ) {
				foreach ( $urls as $url ) {
					$this->assertStringContainsString(
						$url,
						$args['message'],
						'The email message should contain ' . $url
					);
				}
			}
		);

		$has_successful = ! empty( $successful );
		$has_failed     = ! empty( $failed );

		if ( ! $has_successful && ! $has_failed ) {
			$this->markTestSkipped( 'This test requires at least one successful or failed plugin update object.' );
		}

		$type = $has_successful && $has_failed ? 'mixed' : ( ! $has_failed ? 'success' : 'fail' );

		$args = array( $type, array( 'plugin' => $successful ), array( 'plugin' => $failed ) );
		self::$send_plugin_theme_email->invokeArgs( self::$updater, $args );
	}

	/**
	 * Data provider: Provides an array of plugin update objects that should
	 * have their URLs appended to the email message.
	 *
	 * @return array
	 */
	public function data_send_plugin_theme_email_should_append_plugin_urls() {
		return array(
			'successful updates, the current version and the plugin url'       => array(
				'urls'       => array( 'http://example.org/successful-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => 'http://example.org/successful-plugin',
						),
					),
				),
				'failed'     => array(),
			),
			'successful updates, no current version and the plugin url'  => array(
				'urls'       => array( 'http://example.org/successful-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => 'http://example.org/successful-plugin',
						),
					),
				),
				'failed'     => array(),
			),
			'failed updates, the current version and the plugin url'       => array(
				'urls'       => array( 'http://example.org/failed-plugin' ),
				'successful' => array(),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => 'http://example.org/failed-plugin',
						),
					),
				),
			),
			'failed updates, no current version and the plugin url'  => array(
				'urls'       => array( 'http://example.org/failed-plugin' ),
				'successful' => array(),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => 'http://example.org/failed-plugin',
						),
					),
				),
			),
			'mixed updates, the current version and a successful plugin url' => array(
				'urls'       => array( 'http://example.org/successful-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => 'http://example.org/successful-plugin',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => '',
						),
					),
				),
			),
			'mixed updates, no current version and a successful plugin url'  => array(
				'urls'       => array( 'http://example.org/successful-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => 'http://example.org/successful-plugin',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => '',
						),
					),
				),
			),
			'mixed updates, the current version and a failed plugin url' => array(
				'urls'       => array( 'http://example.org/failed-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => '',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => 'http://example.org/failed-plugin',
						),
					),
				),
			),
			'mixed updates, no current version and a failed plugin url'  => array(
				'urls'       => array( 'http://example.org/failed-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => '',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => 'http://example.org/failed-plugin',
						),
					),
				),
			),
			'mixed updates, the current version and both successful and failed plugin urls' => array(
				'urls'       => array(
					'http://example.org/successful-plugin',
					'http://example.org/failed-plugin',
				),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => 'http://example.org/successful-plugin',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => 'http://example.org/failed-plugin',
						),
					),
				),
			),
			'mixed updates, no current version and both successful and failed plugin urls'  => array(
				'urls'       => array(
					'http://example.org/successful-plugin',
					'http://example.org/failed-plugin',
				),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => 'http://example.org/successful-plugin',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => 'http://example.org/failed-plugin',
						),
					),
				),
			),
		);
	}

	/**
	 * Tests that `WP_Automatic_Updater::send_plugin_theme_email()` does not
	 * append plugin URLs.
	 *
	 * @ticket 53049
	 *
	 * @covers WP_Automatic_Updater::send_plugin_theme_email
	 *
	 * @dataProvider data_send_plugin_theme_email_should_not_append_plugin_urls
	 *
	 * @param string[] $urls       The URL(s) to search for. Must not be empty.
	 * @param object[] $successful An array of successful plugin update objects.
	 * @param object[] $failed     An array of failed plugin update objects.
	 */
	public function test_send_plugin_theme_email_should_not_append_plugin_urls( $urls, $successful, $failed ) {
		add_filter(
			'wp_mail',
			function( $args ) use ( $urls ) {
				foreach ( $urls as $url ) {
					$this->assertStringNotContainsString(
						$url,
						$args['message'],
						'The email message should not contain ' . $url
					);
				}
			}
		);

		$has_successful = ! empty( $successful );
		$has_failed     = ! empty( $failed );

		if ( ! $has_successful && ! $has_failed ) {
			$this->markTestSkipped( 'This test requires at least one successful or failed plugin update object.' );
		}

		$type = $has_successful && $has_failed ? 'mixed' : ( ! $has_failed ? 'success' : 'fail' );

		$args = array( $type, array( 'plugin' => $successful ), array( 'plugin' => $failed ) );
		self::$send_plugin_theme_email->invokeArgs( self::$updater, $args );
	}

	/**
	 * Data provider: Provides an array of plugin update objects that should
	 * not have their URL appended to the email message.
	 *
	 * @return array
	 */
	public function data_send_plugin_theme_email_should_not_append_plugin_urls() {
		return array(
			'successful updates, the current version, but no plugin url'    => array(
				'urls'       => array( 'http://example.org/successful-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => '',
						),
					),
				),
				'failed'     => array(),
			),
			'successful updates, but no current version or plugin url' => array(
				'urls'       => array( 'http://example.org/successful-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => '',
						),
					),
				),
				'failed'     => array(),
			),
			'failed updates, the current version, but no plugin url'    => array(
				'urls'       => array( 'http://example.org/failed-plugin' ),
				'successful' => array(),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => '',
						),
					),
				),
			),
			'failed updates, but no current version or plugin url' => array(
				'urls'       => array( 'http://example.org/failed-plugin' ),
				'successful' => array(),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => '',
						),
					),
				),
			),
			'mixed updates, the current version, but no successful plugin url' => array(
				'urls'       => array( 'http://example.org/successful-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => '',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => 'http://example.org/failed-plugin',
						),
					),
				),
			),
			'mixed updates, but no current version or successful plugin url'  => array(
				'urls'       => array( 'http://example.org/successful-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => '',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => 'http://example.org/failed-plugin',
						),
					),
				),
			),
			'mixed updates, the current version, but no failed plugin url' => array(
				'urls'       => array( 'http://example.org/failed-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => 'http://example.org/successful-plugin',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => '',
						),
					),
				),
			),
			'mixed updates, no current version or failed plugin url'  => array(
				'urls'       => array( 'http://example.org/failed-plugin' ),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => 'http://example.org/successful-plugin',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => '',
						),
					),
				),
			),
			'mixed updates, the current version and no successful or failed plugin urls' => array(
				'urls'       => array(
					'http://example.org/successful-plugin',
					'http://example.org/failed-plugin',
				),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => '',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '1.0.0',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => '',
						),
					),
				),
			),
			'mixed updates, no current version and no successful or failed plugin urls'  => array(
				'urls'       => array(
					'http://example.org/successful-plugin',
					'http://example.org/failed-plugin',
				),
				'successful' => array(
					(object) array(
						'name' => 'Successful Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'successful-plugin/successful-plugin.php',
							'url'             => '',
						),
					),
				),
				'failed'     => array(
					(object) array(
						'name' => 'Failed Plugin',
						'item' => (object) array(
							'current_version' => '',
							'new_version'     => '2.0.0',
							'plugin'          => 'failed-plugin/failed-plugin.php',
							'url'             => '',
						),
					),
				),
			),
		);
	}

	/**
	 * Tests that `WP_Automatic_Updater::is_allowed_dir()` returns true
	 * when the `open_basedir` directive is not set.
	 *
	 * @ticket 42619
	 *
	 * @covers WP_Automatic_Updater::is_allowed_dir
	 */
	public function test_is_allowed_dir_should_return_true_if_open_basedir_is_not_set() {
		$this->assertTrue( self::$updater->is_allowed_dir( ABSPATH ) );
	}

	/**
	 * Tests that `WP_Automatic_Updater::is_allowed_dir()` returns true
	 * when the `open_basedir` directive is set and the path is allowed.
	 *
	 * Runs in a separate process to ensure that `open_basedir` changes
	 * don't impact other tests should an error occur.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed" when running in
	 * a separate process.
	 *
	 * @ticket 42619
	 *
	 * @covers WP_Automatic_Updater::is_allowed_dir
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_allowed_dir_should_return_true_if_open_basedir_is_set_and_path_is_allowed() {
		// The repository for PHPUnit and test suite resources.
		$abspath_parent      = trailingslashit( dirname( ABSPATH ) );
		$abspath_grandparent = trailingslashit( dirname( $abspath_parent ) );

		$open_basedir_backup = ini_get( 'open_basedir' );
		// Allow access to the directory one level above the repository.
		ini_set( 'open_basedir', wp_normalize_path( $abspath_grandparent ) );

		// Checking an allowed directory should succeed.
		$actual = self::$updater->is_allowed_dir( wp_normalize_path( ABSPATH ) );

		ini_set( 'open_basedir', $open_basedir_backup );

		$this->assertTrue( $actual );
	}

	/**
	 * Tests that `WP_Automatic_Updater::is_allowed_dir()` returns false
	 * when the `open_basedir` directive is set and the path is not allowed.
	 *
	 * Runs in a separate process to ensure that `open_basedir` changes
	 * don't impact other tests should an error occur.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed" when running in
	 * a separate process.
	 *
	 * @ticket 42619
	 *
	 * @covers WP_Automatic_Updater::is_allowed_dir
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_allowed_dir_should_return_false_if_open_basedir_is_set_and_path_is_not_allowed() {
		// The repository for PHPUnit and test suite resources.
		$abspath_parent      = trailingslashit( dirname( ABSPATH ) );
		$abspath_grandparent = trailingslashit( dirname( $abspath_parent ) );

		$open_basedir_backup = ini_get( 'open_basedir' );
		// Allow access to the directory one level above the repository.
		ini_set( 'open_basedir', wp_normalize_path( $abspath_grandparent ) );

		// Checking a directory not within the allowed path should trigger an `open_basedir` warning.
		$actual = self::$updater->is_allowed_dir( '/.git' );

		ini_set( 'open_basedir', $open_basedir_backup );

		$this->assertFalse( $actual );
	}

	/**
	 * Tests that `WP_Automatic_Updater::is_allowed_dir()` throws `_doing_it_wrong()`
	 * when an invalid `$dir` argument is provided.
	 *
	 * @ticket 42619
	 *
	 * @covers WP_Automatic_Updater::is_allowed_dir
	 *
	 * @expectedIncorrectUsage WP_Automatic_Updater::is_allowed_dir
	 *
	 * @dataProvider data_is_allowed_dir_should_throw_doing_it_wrong_with_invalid_dir
	 *
	 * @param mixed $dir The directory to check.
	 */
	public function test_is_allowed_dir_should_throw_doing_it_wrong_with_invalid_dir( $dir ) {
		$this->assertFalse( self::$updater->is_allowed_dir( $dir ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_is_allowed_dir_should_throw_doing_it_wrong_with_invalid_dir() {
		return array(
			// Type checks and boolean comparisons.
			'null'                              => array( 'dir' => null ),
			'(bool) false'                      => array( 'dir' => false ),
			'(bool) true'                       => array( 'dir' => true ),
			'(int) 0'                           => array( 'dir' => 0 ),
			'(int) -0'                          => array( 'dir' => -0 ),
			'(int) 1'                           => array( 'dir' => 1 ),
			'(int) -1'                          => array( 'dir' => -1 ),
			'(float) 0.0'                       => array( 'dir' => 0.0 ),
			'(float) -0.0'                      => array( 'dir' => -0.0 ),
			'(float) 1.0'                       => array( 'dir' => 1.0 ),
			'empty string'                      => array( 'dir' => '' ),
			'empty array'                       => array( 'dir' => array() ),
			'populated array'                   => array( 'dir' => array( ABSPATH ) ),
			'empty object'                      => array( 'dir' => new stdClass() ),
			'populated object'                  => array( 'dir' => (object) array( ABSPATH ) ),
			'INF'                               => array( 'dir' => INF ),
			'NAN'                               => array( 'dir' => NAN ),

			// Ensures that `trim()` has been called.
			'string with only spaces'           => array( 'dir' => '   ' ),
			'string with only tabs'             => array( 'dir' => "\t\t" ),
			'string with only newlines'         => array( 'dir' => "\n\n" ),
			'string with only carriage returns' => array( 'dir' => "\r\r" ),
		);
	}
}
