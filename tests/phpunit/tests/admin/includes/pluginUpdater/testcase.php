<?php

abstract class Admin_Includes_PluginUpdater_TestCase extends WP_UnitTestCase {
	protected        $plugin     = array();
	protected        $error_data = array();
	protected static $filesystem;

	public static function setUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		self::$filesystem = new WP_Filesystem_Direct( new StdClass() );

		self::remove_hello_dolly_plugin();
	}

	public function setUp() {
		parent::setUp();

		$this->plugin     = array();
		$this->error_data = array();

		// Remove upgrade hooks which are not required for plugin installation tests
		// and may interfere with the results due to a timeout in external HTTP requests.
		remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );
		remove_action( 'upgrader_process_complete', 'wp_version_check' );
		remove_action( 'upgrader_process_complete', 'wp_update_plugins' );
		remove_action( 'upgrader_process_complete', 'wp_update_themes' );
	}

	public function tearDown() {
		self::remove_hello_dolly_plugin();

		delete_site_transient( 'update_plugins' );

		parent::tearDown();
	}

	protected static function remove_hello_dolly_plugin() {
		if ( file_exists( WP_PLUGIN_DIR . '/hello-dolly/hello.php' ) ) {
			self::$filesystem->rmdir( WP_PLUGIN_DIR . '/hello-dolly/', true );
		}
	}

	protected function shortcircuit_w_org_download() {
		add_filter(
			'upgrader_pre_download',
			function ( $reply, $package, $upgrader ) {
				if ( ! empty( $package ) && $upgrader instanceof Plugin_Upgrader ) {
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

	protected function install_older_version( $upgrader, $package, $update_plugins = array() ) {
		ob_start();
		$upgrader->install( $package );
		ob_get_clean();
		$upgrader->result = null;

		if ( ! empty( $update_plugins ) ) {
			set_site_transient( 'update_plugins', $update_plugins );
		}
	}
}
