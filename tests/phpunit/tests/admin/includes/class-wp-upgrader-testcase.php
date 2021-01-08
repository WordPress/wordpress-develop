<?php

abstract class WP_Upgrader_TestCase extends WP_UnitTestCase {
	protected $error_data = array();
	protected $plugin     = array();
	protected $theme      = array();
	protected $is_theme   = false;

	protected $theme_data_provider;
	protected $plugin_data_provider;

	protected static $filesystem;
	protected static $theme_dir;
	protected static $originals;

	public static function setUpBeforeClass() {
		self::$theme_dir = WP_CONTENT_DIR . '/themes';

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		self::$originals = array(
			'update_themes'  => get_site_transient( 'update_themes' ),
			'update_plugins' => get_site_transient( 'update_plugins' ),
		);
	}

	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();

		foreach ( self::$originals as $transient => $value ) {
			if ( ! empty( $value ) ) {
				set_site_transient( $transient, $value );
			} else {
				delete_site_transient( $transient );
			}
		}
	}

	public function setUp() {
		parent::setUp();

		$this->plugin     = array();
		$this->theme      = array();
		$this->error_data = array();

		// Remove upgrade hooks which are not required for plugin installation tests
		// and may interfere with the results due to a timeout in external HTTP requests.
		remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );
		remove_action( 'upgrader_process_complete', 'wp_version_check' );
		remove_action( 'upgrader_process_complete', 'wp_update_plugins' );
		remove_action( 'upgrader_process_complete', 'wp_update_themes' );
	}

	public function tearDown() {
		$this->remove_test_theme();
		$this->remove_test_plugin();

		parent::tearDown();
	}

	protected function remove_test_plugin() {
		delete_site_transient( 'update_plugins' );
		if ( file_exists( WP_PLUGIN_DIR . '/hello-dolly' ) ) {
			$this->rmdir( WP_PLUGIN_DIR . '/hello-dolly' );
			rmdir( WP_PLUGIN_DIR . '/hello-dolly' );
		}
	}

	protected function remove_test_theme() {
		delete_site_transient( 'update_themes' );
		if ( file_exists( WP_CONTENT_DIR . '/themes/upgrader-test-theme' ) ) {
			$this->rmdir( WP_CONTENT_DIR . '/themes/upgrader-test-theme' );
			rmdir( WP_CONTENT_DIR . '/themes/upgrader-test-theme' );
		}
	}

	protected function shortcircuit_w_org_download() {
		add_filter(
			'upgrader_pre_download',
			function ( $reply, $package, $upgrader ) {
				if ( empty( $package ) ) {
					return $reply;
				}

				if (
					$upgrader instanceof Plugin_Upgrader
					||
					$upgrader instanceof Theme_Upgrader
				) {
					return $package;
				}

				return $reply;
			},
			10,
			3
		);
	}

	protected function capture_error_data() {
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args ) {
				if ( ! isset( $parsed_args['body']['update_stats'] ) ) {
					return $preempt;
				}

				$this->error_data[] = (array) json_decode( $parsed_args['body']['update_stats'] );

				return true;
			},
			10,
			2
		);
	}

	/**
	 * Mocks WP_Upgrader_Skin::feedback method.
	 *
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	protected function mock_skin_feedback() {
		$skin = $this
			->getMockBuilder( WP_Upgrader_Skin::class )
			->setMethods( array( 'feedback' ) )
			->getMock();

		// Mocks the feedback method to prevent `show_message()` from running, i.e.
		// to avoid it from flushing and ending all output buffers. Why?
		// Avoids printing in the console and allows testing the feedback messages.
		$skin
			->expects( $this->atLeastOnce() )
			->method( 'feedback' )
			->willReturnCallback(
				function ( $message ) use ( $skin ) {
					if ( isset( $skin->upgrader->strings[ $message ] ) ) {
						$message = $skin->upgrader->strings[ $message ];
					}

					echo "<p>$message</p>\n";
				}
			);

		return $skin;
	}

	protected function install_older_version( $upgrader, $package, $update_transient = array() ) {
		ob_start();
		$upgrader->install( $package );
		ob_get_clean();
		$upgrader->result = null;
		$this->error_data = array();

		if ( ! empty( $update_transient ) ) {
			$transient = $this->is_theme ? 'update_themes' : 'update_plugins';
			set_site_transient( $transient, $update_transient );
		}
	}

	/**
	 * Initializes the theme's data provider.
	 *
	 * Data is encapsulated for reuse as much of the data repeats from test-to-test.
	 */
	protected function init_theme_data_provider() {
		require_once __DIR__ . '/themeUpgrader/class-theme-upgrader-data-provider.php';

		if ( $this->theme_data_provider instanceof Theme_Upgrader_Data_Provider ) {
			return;
		}

		$this->theme_data_provider = new Theme_Upgrader_Data_Provider();
		$this->theme_data_provider->init();
	}

	/**
	 * Initializes the plugin's data provider.
	 *
	 * Data is encapsulated for reuse as much of the data repeats from test-to-test.
	 */
	protected function init_plugin_data_provider() {
		require_once __DIR__ . '/pluginUpgrader/class-plugin-upgrader-data-provider.php';

		if ( $this->plugin_data_provider instanceof Plugin_Upgrader_Data_Provider ) {
			return;
		}

		$this->plugin_data_provider = new Plugin_Upgrader_Data_Provider();
		$this->plugin_data_provider->init();
	}

	/**
	 * Tests each expected message is contained in the given actual message.
	 *
	 * @param array  $expected Array of expected messages.
	 * @param string $actual   Actual message to assertContains against.
	 */
	protected function assertContainsAdminMessages( $expected, $actual ) {
		$actual = trim( $actual, " \n\r\t\v\0" );

		foreach ( $expected as $expected_message ) {
			$expected_message = trim( $expected_message, " \n\r\t\v\0" );
			$this->assertContains( $expected_message, $actual );
		}
	}

	/**
	 * Tests the error data contains the expected stats.
	 *
	 * @param array $expected Array of error data stats.
	 */
	protected function assertContainsErrorDataStats( $expected ) {
		foreach ( $this->error_data as $index => $stats ) {
			$this->assertContains( $expected[ $index ], $stats );
			$this->assertGreaterThanOrEqual( 0, $stats['time_taken'] );
		}
	}
}
