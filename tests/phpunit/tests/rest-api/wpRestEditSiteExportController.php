<?php
/**
 * Unit tests covering WP_REST_Edit_Site_Export_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.9.0
 *
 * @covers WP_REST_Edit_Site_Export_Controller
 *
 * @group restapi
 */
class Tests_REST_WpRestEditSiteExportController extends WP_Test_REST_Controller_Testcase {

	/**
	 * The REST API route for the edit site export.
	 *
	 * @since 5.9.0
	 *
	 * @var string
	 */
	const REQUEST_ROUTE = '/wp-block-editor/v1/export';

	/**
	 * Administrator user ID.
	 *
	 * @since 5.9.0
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @since 5.9.0
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Set up class test fixtures.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	/**
	 * Delete test data after our tests run.
	 *
	 * @since 5.9.0
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$subscriber_id );
	}

	/**
	 * @covers WP_REST_Edit_Site_Export_Controller::register_routes
	 * @ticket 54448
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( static::REQUEST_ROUTE, $routes );
		$this->assertCount( 1, $routes[ static::REQUEST_ROUTE ] );
	}

	/**
	 * @covers WP_REST_Edit_Site_Export_Controller::permissions_check
	 *
	 * @ticket 54448
	 */
	public function test_export_for_no_user_permissions() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_export_templates', $response, 401 );
	}

	/**
	 * @covers WP_REST_Edit_Site_Export_Controller::permissions_check
	 *
	 * @ticket 54448
	 */
	public function test_export_for_user_with_insufficient_permissions() {
		wp_set_current_user( self::$subscriber_id );

		$request  = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_export_templates', $response, 403 );
	}

	/**
	 * Edit Site Export endpoint does not support the context request parameter.
	 *
	 * @ticket 40538
	 */
	public function test_context_param() {
		$request  = new WP_REST_Request( 'OPTIONS', static::REQUEST_ROUTE );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $data['endpoints'] );
		foreach ( $data['endpoints'] as $endpoint ) {
			$this->assertArrayNotHasKey( 'context', $endpoint['args'] );
		}
	}

	/**
	 * Edit Site Export has no item route.
	 *
	 * @ticket 66073
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'GET', static::REQUEST_ROUTE . '/example' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	/**
	 * Edit Site Export has no collection list operation; GET on the export route downloads a ZIP.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_items() {
		// Controller does not implement get_items().
	}

	/**
	 * Edit Site Export is read-only; create requests should not match a route.
	 *
	 * @ticket 66073
	 */
	public function test_create_item() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'POST', static::REQUEST_ROUTE );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	/**
	 * Edit Site Export is read-only; update requests should not match a route.
	 *
	 * Uses OPTIONS (not GET) to prove the route exists, because a successful GET
	 * triggers the ZIP export and exits the request.
	 *
	 * @ticket 66073
	 */
	public function test_update_item() {
		wp_set_current_user( self::$admin_id );

		$route = static::REQUEST_ROUTE;

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', $route ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $route, $response->get_matched_route() );

		foreach ( array( 'POST', 'PUT', 'PATCH' ) as $method ) {
			$request  = new WP_REST_Request( $method, $route );
			$response = rest_get_server()->dispatch( $request );

			$this->assertErrorResponse( 'rest_no_route', $response, 404 );
		}
	}

	/**
	 * Edit Site Export is read-only; delete requests should not match a route.
	 *
	 * Uses OPTIONS (not GET) to prove the route exists, because a successful GET
	 * triggers the ZIP export and exits the request.
	 *
	 * @ticket 66073
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );

		$route = static::REQUEST_ROUTE;

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', $route ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $route, $response->get_matched_route() );

		$request  = new WP_REST_Request( 'DELETE', $route );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_prepare_item() {
		// Controller does not implement prepare_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_get_item_schema() {
		// Controller does not implement get_item_schema().
	}
}
