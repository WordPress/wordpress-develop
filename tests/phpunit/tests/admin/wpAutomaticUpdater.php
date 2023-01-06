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
}
