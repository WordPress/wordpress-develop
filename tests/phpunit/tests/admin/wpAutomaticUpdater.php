<?php

/**
 * @group admin
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
		if ( PHP_VERSION_ID < 80100 ) {
			self::$send_plugin_theme_email->setAccessible( true );
		}
	}

	public function set_up() {
		parent::set_up();
		add_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Builds an open_basedir value that allows PHPUnit to load Composer dependencies.
	 *
	 * Composer dependencies may live outside a secondary worktree, and PHPUnit can
	 * load assertion-related classes after the restriction is set.
	 *
	 * @param string $abspath_grandparent Directory containing the repository.
	 * @return string The open_basedir value.
	 */
	private function get_open_basedir_for_tests( $abspath_grandparent ) {
		$composer_vendor_dir = dirname(
			( new ReflectionClass( Composer\Autoload\ClassLoader::class ) )->getFileName(),
			2
		);

		return implode(
			PATH_SEPARATOR,
			array(
				sys_get_temp_dir(),
				wp_normalize_path( $abspath_grandparent ),
				wp_normalize_path( $composer_vendor_dir ),
			)
		);
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
			function ( $args ) use ( $urls ) {
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
	public static function data_send_plugin_theme_email_should_append_plugin_urls() {
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
			function ( $args ) use ( $urls ) {
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
	public static function data_send_plugin_theme_email_should_not_append_plugin_urls() {
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
		ini_set( 'open_basedir', $this->get_open_basedir_for_tests( $abspath_grandparent ) );

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
		ini_set( 'open_basedir', $this->get_open_basedir_for_tests( $abspath_grandparent ) );

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
	public static function data_is_allowed_dir_should_throw_doing_it_wrong_with_invalid_dir() {
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

	/**
	 * Tests that `WP_Automatic_Updater::is_vcs_checkout()` returns `false`
	 * when none of the checked directories are allowed.
	 *
	 * @ticket 58563
	 *
	 * @covers WP_Automatic_Updater::is_vcs_checkout
	 */
	public function test_is_vcs_checkout_should_return_false_when_no_directories_are_allowed() {
		$updater_mock = $this->getMockBuilder( 'WP_Automatic_Updater' )
			// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
			->setMethods( array( 'is_allowed_dir' ) )
			->getMock();

		/*
		 * As none of the directories should be allowed, simply mocking `WP_Automatic_Updater`
		 * and forcing `::is_allowed_dir()` to return `false` removes the need to run the test
		 * in a separate process due to setting the `open_basedir` PHP directive.
		 */
		$updater_mock->expects( $this->any() )->method( 'is_allowed_dir' )->willReturn( false );

		$this->assertFalse( $updater_mock->is_vcs_checkout( get_temp_dir() ) );
	}

	/**
	 * Tests that {@see WP_Automatic_Updater::send_plugin_theme_email()} lists the details of a
	 * fatal error under the plugin whose update was rolled back.
	 *
	 * @ticket 64155
	 *
	 * @covers WP_Automatic_Updater::send_plugin_theme_email
	 */
	public function test_send_plugin_theme_email_should_include_fatal_error_details(): void {
		$filter = new MockAction();
		add_filter( 'wp_mail', array( $filter, 'filter' ) );

		$fatal_error = 'PHP Fatal error: Uncaught Error: Call to undefined function failed_plugin_init() in wp-content/plugins/failed-plugin/failed-plugin.php on line 12';

		$result = new WP_Error( 'plugin_update_fatal_error_rollback_successful', "The update for 'failed-plugin' contained a fatal error. The previously installed version has been restored." );
		$result->add( 'plugin_update_fatal_error', $fatal_error );

		$failed = array(
			(object) array(
				'name'   => 'Failed Plugin',
				'result' => $result,
				'item'   => (object) array(
					'current_version' => '1.0.0',
					'new_version'     => '2.0.0',
					'plugin'          => 'failed-plugin/failed-plugin.php',
				),
			),
		);

		self::$send_plugin_theme_email->invokeArgs( self::$updater, array( 'fail', array(), array( 'plugin' => $failed ) ) );

		$this->assertSame( 1, $filter->get_call_count(), 'An email should have been sent.' );

		$mail_args = $filter->get_args()[0][0];
		$this->assertIsArray( $mail_args );
		$this->assertArrayHasKey( 'message', $mail_args );
		$this->assertIsString( $mail_args['message'] );
		$this->assertStringContainsString(
			"- Failed Plugin (from version 1.0.0 to 2.0.0)\n  {$fatal_error}\n",
			$mail_args['message'],
			'The fatal error should be listed under the plugin that failed to update.'
		);
	}

	/**
	 * Tests that {@see WP_Automatic_Updater::has_fatal_error()} records the details of the fatal
	 * error found by the loopback request, without any stack trace.
	 *
	 * @ticket 64155
	 *
	 * @covers WP_Automatic_Updater::has_fatal_error
	 *
	 * @dataProvider data_has_fatal_error_should_record_fatal_error_details
	 *
	 * @param array<string, string>|true|WP_Error $scrape_result    The result of the loopback request: the error
	 *                                                                found by the scrape, `true` for none, or a
	 *                                                                `WP_Error` for a failed request.
	 * @param bool                                $expected_return  The expected return value.
	 * @param string|null                         $expected_message The expected details of the fatal error.
	 */
	public function test_has_fatal_error_should_record_fatal_error_details( $scrape_result, bool $expected_return, ?string $expected_message ): void {
		$upgrading = 1700000000;

		$maintenance_file = ABSPATH . '.maintenance';
		$this->assertFileDoesNotExist( $maintenance_file, 'The test requires that no maintenance file exists.' );
		file_put_contents( $maintenance_file, "<?php \$upgrading = {$upgrading};" );

		add_filter(
			'pre_http_request',
			static function () use ( $scrape_result, $upgrading ) {
				if ( $scrape_result instanceof WP_Error ) {
					return $scrape_result;
				}

				$scrape_key = md5( (string) $upgrading );

				return array(
					'headers'  => array(),
					'body'     => "<html></html>\n###### wp_scraping_result_start:{$scrape_key} ######\n" . wp_json_encode( $scrape_result ) . "\n###### wp_scraping_result_end:{$scrape_key} ######\n",
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			}
		);

		$has_fatal_error = new ReflectionMethod( self::$updater, 'has_fatal_error' );
		$message         = new ReflectionProperty( self::$updater, 'fatal_error_message' );
		if ( PHP_VERSION_ID < 80100 ) {
			$has_fatal_error->setAccessible( true );
			$message->setAccessible( true );
		}

		try {
			$this->assertSame( $expected_return, $has_fatal_error->invoke( self::$updater ), 'The return value should indicate whether a fatal error was detected.' );
		} finally {
			unlink( $maintenance_file );
		}

		$this->assertSame( $expected_message, $message->getValue( self::$updater ), 'The recorded details of the fatal error do not match.' );
	}

	/**
	 * Data provider.
	 *
	 * The scrape results mirror those of {@see wp_finalize_scraping_edited_file_errors()}, which
	 * passes the error through `str_replace()`, so that every value is a string.
	 *
	 * @return array<non-falsy-string, array{
	 *     scrape_result: array<string, string>|true|WP_Error,
	 *     expected_return: bool,
	 *     expected_message: string|null,
	 * }>
	 */
	public function data_has_fatal_error_should_record_fatal_error_details(): array {
		return array(
			'fatal error with a stack trace'    => array(
				'scrape_result'    => array(
					'type'    => '1',
					'message' => "Uncaught Exception: Could not connect to the database in wp-content/plugins/failed-plugin/failed-plugin.php:3\nStack trace:\n#0 wp-content/plugins/failed-plugin/failed-plugin.php(7): failed_plugin_connect('db_user', 'secret_password')\n#1 {main}\n  thrown",
					'file'    => 'wp-content/plugins/failed-plugin/failed-plugin.php',
					'line'    => '3',
				),
				'expected_return'  => true,
				'expected_message' => 'PHP Fatal error: Uncaught Exception: Could not connect to the database in wp-content/plugins/failed-plugin/failed-plugin.php:3 in wp-content/plugins/failed-plugin/failed-plugin.php on line 3',
			),
			'fatal error without a stack trace' => array(
				'scrape_result'    => array(
					'type'    => '64',
					'message' => 'Cannot redeclare failed_plugin_init()',
					'file'    => 'wp-content/plugins/failed-plugin/failed-plugin.php',
					'line'    => '12',
				),
				'expected_return'  => true,
				'expected_message' => 'PHP Fatal error: Cannot redeclare failed_plugin_init() in wp-content/plugins/failed-plugin/failed-plugin.php on line 12',
			),
			'no fatal error'                    => array(
				'scrape_result'    => true,
				'expected_return'  => false,
				'expected_message' => null,
			),
			'failed loopback request'           => array(
				'scrape_result'    => new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ),
				'expected_return'  => true,
				'expected_message' => 'The loopback request to check for fatal errors failed: cURL error 28: Operation timed out',
			),
		);
	}
}
