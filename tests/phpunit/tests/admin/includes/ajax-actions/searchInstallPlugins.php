<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_search_install_plugins() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.6.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_search_install_plugins
 */
class Tests_wp_ajax_search_install_plugins extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_search-install-plugins', 'wp_ajax_search_install_plugins', 1 );

		// Hook into wp_die to prevent execution from stopping.
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );

		// Mock plugins_api to avoid external requests.
		add_filter( 'plugins_api', array( $this, 'mock_plugins_api' ), 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );
		remove_filter( 'plugins_api', array( $this, 'mock_plugins_api' ) );
		parent::tear_down();
	}

	/**
	 * Returns our custom die handler.
	 *
	 * @return callable
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
	 * Custom die handler that throws an exception.
	 *
	 * @param string|WP_Error $message
	 */
	public function dieHandler( $message ) {
		$this->_last_response .= ob_get_clean();

		if ( '' === $this->_last_response ) {
			if ( is_scalar( $message ) ) {
				$this->_last_response = (string) $message;
			} else {
				$this->_last_response = '0';
			}
		}

		if ( '-1' === $this->_last_response || ( is_int( $message ) && -1 === $message ) ) {
			throw new WPAjaxDieStopException( $this->_last_response );
		}

		throw new WPAjaxDieContinueException( $this->_last_response );
	}

	/**
	 * Mock plugins_api response.
	 */
	public function mock_plugins_api( $res, $action, $args ) {
		if ( 'query_plugins' !== $action ) {
			return $res;
		}

		$plugin                    = new stdClass();
		$plugin->slug              = 'test-plugin';
		$plugin->name              = 'Test Plugin';
		$plugin->version           = '1.0';
		$plugin->author            = 'Author';
		$plugin->last_updated      = '2023-01-01';
		$plugin->num_ratings       = 5;
		$plugin->rating            = 100;
		$plugin->active_installs   = 1000;
		$plugin->description       = 'Description';
		$plugin->short_description = 'Short description';
		$plugin->icons             = array( 'default' => 'https://example.com/icon.png' );
		$plugin->banners           = array();
		$plugin->tested            = '6.0';
		$plugin->requires          = '5.0';
		$plugin->requires_php      = '7.0';
		$plugin->compatibility     = array();
		$plugin->download_link     = 'https://downloads.wordpress.org/plugin/test-plugin.1.0.zip';

		$api          = new stdClass();
		$api->info    = array(
			'page'    => 1,
			'pages'   => 1,
			'results' => 1,
		);
		$api->plugins = array( $plugin );

		return $api;
	}

	/**
	 * Tests success for wp_ajax_search_install_plugins().
	 *
	 * @ticket 65252
	 */
	public function test_search_install_plugins_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['pagenow']     = 'plugin-install';

		try {
			$this->_handleAjax( 'search-install-plugins' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertArrayHasKey( 'items', $response['data'] );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_search_install_plugins().
	 *
	 * @ticket 65252
	 */
	public function test_search_install_plugins_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );

		try {
			$this->_handleAjax( 'search-install-plugins' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}
}
