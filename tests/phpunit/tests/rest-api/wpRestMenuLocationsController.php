<?php
/**
 * Unit tests covering WP_REST_Menu_Locations_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.9.0
 *
 * @group restapi
 *
 * @coversDefaultClass WP_REST_Menu_Locations_Controller
 */
class Tests_REST_WpRestMenuLocationsController extends WP_Test_REST_Controller_Testcase {

	/**
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
	}

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		// Unregister all nav menu locations.
		foreach ( array_keys( get_registered_nav_menus() ) as $location ) {
			unregister_nav_menu( $location );
		}
	}

	/**
	 * Register nav menu locations.
	 *
	 * @param array $locations Location slugs.
	 */
	public function register_nav_menu_locations( $locations ) {
		foreach ( $locations as $location ) {
			register_nav_menu( $location, ucfirst( $location ) );
		}
	}

	/**
	 * @ticket 40878
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/menu-locations', $routes );
		$this->assertCount( 1, $routes['/wp/v2/menu-locations'] );
		$this->assertArrayHasKey( '/wp/v2/menu-locations/(?P<location>[\w-]+)', $routes );
		$this->assertCount( 1, $routes['/wp/v2/menu-locations/(?P<location>[\w-]+)'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_context_param
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/menu-locations' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		$menu = 'primary';
		$this->register_nav_menu_locations( array( $menu ) );
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/menu-locations/' . $menu );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_items
	 */
	public function test_get_items() {
		$menus = array( 'primary', 'secondary' );
		$this->register_nav_menu_locations( array( 'primary', 'secondary' ) );
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-locations' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = array_values( $data );
		$this->assertCount( 2, $data );
		$names        = wp_list_pluck( $data, 'name' );
		$descriptions = wp_list_pluck( $data, 'description' );
		$this->assertSame( $menus, $names );
		$menu_descriptions = array_map( 'ucfirst', $names );
		$this->assertSame( $menu_descriptions, $descriptions );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$menu = 'primary';
		$this->register_nav_menu_locations( array( $menu ) );

		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-locations/' . $menu );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $menu, $data['name'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item
	 */
	public function test_get_item_invalid() {
		$menu = 'primary';
		$this->register_nav_menu_locations( array( $menu ) );

		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-locations/invalid' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_menu_location_invalid', $response, 404 );
	}

	/**
	 * The create_item() method does not exist for menu locations.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_create_item() {
		// Controller does not implement create_item().
	}

	/**
	 * The update_item() method does not exist for menu locations.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_update_item() {
		// Controller does not implement update_item().
	}

	/**
	 * The delete_item() method does not exist for menu locations.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Controller does not implement delete_item().
	}

	/**
	 * The prepare_item() method does not exist for menu locations.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_prepare_item() {
		// Controller does not implement prepare_item().
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		wp_set_current_user( self::$admin_id );
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/menu-locations' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertSame( 3, count( $properties ) );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'menu', $properties );
	}


	/**
	 * @ticket 40878
	 * @covers ::get_items
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_menu_location_context_without_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-locations' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_view', $response, rest_authorization_required_code() );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item
	 * @covers ::get_item_permissions_check
	 */
	public function test_get_item_menu_location_context_without_permission() {
		$menu = 'primary';
		$this->register_nav_menu_locations( array( $menu ) );

		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-locations/' . $menu );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_view', $response, rest_authorization_required_code() );
	}
}
