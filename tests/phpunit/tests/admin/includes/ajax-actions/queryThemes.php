<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_query_themes() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.9.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_query_themes
 */
class Tests_wp_ajax_query_themes extends WP_Ajax_UnitTestCase {

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
		add_action( 'wp_ajax_query-themes', 'wp_ajax_query_themes', 1 );

		// Hook into wp_die to prevent execution from stopping.
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );

		// Mock themes_api to avoid external requests.
		add_filter( 'themes_api', array( $this, 'mock_themes_api' ), 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );
		remove_filter( 'themes_api', array( $this, 'mock_themes_api' ) );
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
	 * Mock themes_api response.
	 */
	public function mock_themes_api( $res, $action, $args ) {
		if ( 'query_themes' !== $action ) {
			return $res;
		}

		$theme                 = new stdClass();
		$theme->screenshot_url = 'http://example.com/screenshot.png';
		$theme->preview_url    = 'http://example.com/preview';
		$theme->rating         = 100;
		$theme->num_ratings    = 5;
		$theme->homepage       = 'http://example.com';
		$theme->description    = 'Description';
		$theme->author         = array( 'display_name' => 'Author' );
		$theme->version        = '1.0';
		$theme->name           = 'Test Theme';
		$theme->slug           = 'test-theme';
		$theme->sections       = array( 'description' => 'Description' );
		$theme->photovote      = '';
		$theme->vendor         = '';
		$theme->tags           = array();
		$theme->screenshots    = array();
		$theme->requires       = '5.0';
		$theme->requires_php   = '7.0';

		$api         = new stdClass();
		$api->info   = array(
			'page'    => 1,
			'pages'   => 1,
			'results' => 1,
		);
		$api->themes = array( $theme );

		return $api;
	}

	/**
	 * Tests success for wp_ajax_query_themes().
	 *
	 * @ticket 65252
	 */
	public function test_query_themes_success(): void {
		wp_set_current_user( self::$admin_id );

		$_REQUEST['request'] = array( 'per_page' => 1 );

		try {
			$this->_handleAjax( 'query-themes' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertArrayHasKey( 'themes', $response['data'] );
		$this->assertCount( 1, $response['data']['themes'] );
		$this->assertEquals( 'test-theme', $response['data']['themes'][0]['slug'] );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_query_themes().
	 *
	 * @ticket 65252
	 */
	public function test_query_themes_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		try {
			$this->_handleAjax( 'query-themes' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}
}
