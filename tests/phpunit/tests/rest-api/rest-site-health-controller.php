<?php
/**
 * Unit tests covering the site health controller.
 *
 * Also generates the fixture data used by the wp-api.js QUnit tests.
 *
 * @package    WordPress
 * @subpackage REST API
 * @since      5.6.0
 */

/**
 * @group restapi
 */
class WP_Test_REST_Site_Health_Controller extends WP_Test_REST_TestCase {

	/**
	 * Subscriber user ID.
	 *
	 * @since 5.6.0
	 *
	 * @var int
	 */
	private static $subscriber;

	/**
	 * Administrator user id.
	 *
	 * @since 5.6.0
	 *
	 * @var int
	 */
	private static $admin;

	/**
	 * Set up class test fixtures.
	 *
	 * @since 5.6.0
	 *
	 * @param WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$subscriber = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		self::$admin      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		if ( is_multisite() ) {
			grant_super_admin( self::$admin );
		}
	}

	/**
	 * Clean up test fixtures.
	 *
	 * @since 5.6.0
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$subscriber );
		self::delete_user( self::$admin );
	}

	public function test_logged_out() {
		$response = rest_do_request( '/wp-site-health/v1/tests/dotorg-communication' );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
	}

	public function test_insufficient_caps() {
		wp_set_current_user( self::$subscriber );
		$response = rest_do_request( '/wp-site-health/v1/tests/dotorg-communication' );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_custom_capability() {
		wp_set_current_user( self::$admin );

		add_filter(
			'site_health_test_rest_capability_dotorg_communication',
			static function () {
				return 'a_custom_capability';
			}
		);

		$response = rest_do_request( '/wp-site-health/v1/tests/dotorg-communication' );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test() {
		wp_set_current_user( self::$admin );
		$response = rest_do_request( '/wp-site-health/v1/tests/dotorg-communication' );
		$this->assertSame( 'dotorg_communication', $response->get_data()['test'] );
	}

	/**
	 * Tests Page Cache Rest endpoint registration.
	 *
	 * @ticket 56041
	 */
	public function test_page_cache_endpoint() {
		$server = rest_get_server();
		$routes = $server->get_routes();

		$endpoint = '/wp-site-health/v1/tests/page-cache';
		$this->assertArrayHasKey( $endpoint, $routes );

		$route = $routes[ $endpoint ];
		$this->assertCount( 1, $route );

		$route = current( $route );
		$this->assertSame(
			array( WP_REST_Server::READABLE => true ),
			$route['methods']
		);

		$this->assertSame(
			'test_page_cache',
			$route['callback'][1]
		);

		$this->assertIsCallable( $route['permission_callback'] );

		if ( current_user_can( 'view_site_health_checks' ) ) {
			$this->assertTrue( call_user_func( $route['permission_callback'] ) );
		} else {
			$this->assertFalse( call_user_func( $route['permission_callback'] ) );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertFalse( call_user_func( $route['permission_callback'] ) );

		$user = wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			// Site health cap is only available for super admins in Multi sites.
			grant_super_admin( $user->ID );
		}
		$this->assertTrue( call_user_func( $route['permission_callback'] ) );
	}
}
