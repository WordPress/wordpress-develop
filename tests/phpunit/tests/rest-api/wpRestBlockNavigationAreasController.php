<?php
/**
 * Unit tests covering WP_REST_Block_Navigation_Areas_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @group restapi
 * @since 5.9.0
 */
class Tests_REST_WpRestBlockNavigationAreasController extends WP_Test_REST_Controller_Testcase {

	const OPTION = 'wp_navigation_areas';

	/**
	 * @var int Administrator user ID
	 */
	protected static $admin_id;

	/**
	 * @var array Contains mapping
	 */
	protected static $old_mapping;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		static::$old_mapping = get_option( static::OPTION, array() );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		update_option( static::OPTION, static::$old_mapping );
	}

	public function test_get_items() {
		wp_set_current_user( static::$admin_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/block-navigation-areas' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$navigation_areas = get_navigation_areas();
		$expected_data    = array();
		foreach ( $navigation_areas as $name => $navigation_area ) {
			$expected_data[] = array(
				'name'        => $name,
				'description' => $navigation_area,
				'navigation'  => 0,
			);
		}
		$this->assertSameSets( $expected_data, $data );
	}

	public function test_register_routes() {
		wp_set_current_user( static::$admin_id );
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/block-navigation-areas', $routes );
		$this->assertArrayHasKey( '/wp/v2/block-navigation-areas/(?P<area>[\\w-]+)', $routes );
	}

	public function test_context_param() {
		wp_set_current_user( static::$admin_id );
		$request  = new WP_REST_Request( Requests::OPTIONS, '/wp/v2/block-navigation-areas' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_get_item() {
		wp_set_current_user( static::$admin_id );
		$navigation_area = array_rand( get_navigation_areas(), 1 );

		$this->assertIsString( $navigation_area );
		$this->assertNotEmpty( $navigation_area );

		$route    = sprintf( '/wp/v2/block-navigation-areas/%s', urlencode( $navigation_area ) );
		$request  = new WP_REST_Request( 'GET', $route );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'name', $data );
		$this->assertSame( $navigation_area, $data['name'] );
	}

	public function test_create_item() {
		// We cannot create new navigation areas using the current block navigation areas API,
		// so the test should be marked as passed.
		$this->markTestSkipped();
	}

	public function test_update_item() {
		wp_set_current_user( static::$admin_id );
		$navigation_area = array_rand( get_navigation_areas(), 1 );
		$route           = sprintf( '/wp/v2/block-navigation-areas/%s', urlencode( $navigation_area ) );
		$request         = new WP_REST_Request( Requests::POST, $route );

		$updated_navigation_area = array(
			'name'        => $navigation_area,
			'description' => 'Test Description',
		);

		$request->set_param( 'navigation', $updated_navigation_area );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$new_mapping = get_option( static::OPTION, array() );
		$this->assertSame( $new_mapping[ $navigation_area ], $updated_navigation_area );
	}

	public function test_delete_item() {
		// We cannot delete navigation areas using the current block navigation areas API,
		// so the test should be marked as passed.
		$this->markTestSkipped();
	}

	public function test_prepare_item() {
		// The current block navigation areas API doesn't implement any custom prepare_item logic
		// so there is nothing to test.
		$this->markTestSkipped();
	}

	public function test_get_item_schema() {
		// The current block navigation areas API doesn't implement any custom item schema
		// so there is nothing to test.
		$this->markTestSkipped();
	}
}

