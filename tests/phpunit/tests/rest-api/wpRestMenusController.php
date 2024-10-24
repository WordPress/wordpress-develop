<?php
/**
 * Unit tests covering WP_REST_Menus_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.9.0
 *
 * @group restapi
 *
 * @coversDefaultClass WP_REST_Menus_Controller
 */
class Tests_REST_WpRestMenusController extends WP_Test_REST_Controller_Testcase {
	/**
	 * @var int
	 */
	public $menu_id;

	/**
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 *
	 */
	const TAXONOMY = 'nav_menu';

	/**
	 * @var int
	 */
	protected static $per_page = 50;

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$editor_id     = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	/**
	 *
	 */
	public function set_up() {
		parent::set_up();
		// Unregister all nav menu locations.
		foreach ( array_keys( get_registered_nav_menus() ) as $location ) {
			unregister_nav_menu( $location );
		}

		$orig_args = array(
			'name'        => 'Original Name',
			'description' => 'Original Description',
			'slug'        => 'original-slug',
			'taxonomy'    => 'nav_menu',
		);

		$this->menu_id = self::factory()->term->create( $orig_args );

		register_meta(
			'term',
			'test_single_menu',
			array(
				'object_subtype' => self::TAXONOMY,
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
			)
		);
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
		$this->assertArrayHasKey( '/wp/v2/menus', $routes );
		$this->assertArrayHasKey( '/wp/v2/menus/(?P<id>[\d]+)', $routes );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_context_param
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/menus' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		$this->assertSame( array( 'v1' => true ), $data['endpoints'][0]['allow_batch'] );
		// Single.
		$tag1     = self::factory()->tag->create( array( 'name' => 'Season 5' ) );
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/menus/' . $tag1 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		$this->assertSame( array( 'v1' => true ), $data['endpoints'][0]['allow_batch'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_collection_params
	 */
	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/menus' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertSame(
			array(
				'context',
				'exclude',
				'hide_empty',
				'include',
				'offset',
				'order',
				'orderby',
				'page',
				'per_page',
				'post',
				'search',
				'slug',
			),
			$keys
		);
	}

	/**
	 * @ticket 40878
	 * @covers ::get_items
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );
		wp_update_nav_menu_object(
			0,
			array(
				'description' => 'Test get',
				'menu-name'   => 'test Name get',
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/menus' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_taxonomy_terms_response( $response );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );
		$nav_menu_id = wp_update_nav_menu_object(
			0,
			array(
				'description' => 'Test menu',
				'menu-name'   => 'test Name',
			)
		);

		$this->register_nav_menu_locations( array( 'primary' ) );
		set_theme_mod( 'nav_menu_locations', array( 'primary' => $nav_menu_id ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/menus/' . $nav_menu_id );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_taxonomy_term_response( $response, $nav_menu_id );
	}


	/**
	 * @ticket 54304
	 * @covers ::get_items
	 */
	public function test_get_items_filter() {
		add_filter( 'rest_menu_read_access', '__return_true' );
		wp_update_nav_menu_object(
			0,
			array(
				'description' => 'Test get',
				'menu-name'   => 'test Name get',
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/menus' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_taxonomy_terms_response( $response );
	}

	/**
	 * @ticket 54304
	 * @covers ::get_item
	 */
	public function test_get_item_filter() {
		add_filter( 'rest_menu_read_access', '__return_true' );
		$nav_menu_id = wp_update_nav_menu_object(
			0,
			array(
				'description' => 'Test menu',
				'menu-name'   => 'test Name',
			)
		);

		$this->register_nav_menu_locations( array( 'primary' ) );
		set_theme_mod( 'nav_menu_locations', array( 'primary' => $nav_menu_id ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/menus/' . $nav_menu_id );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_taxonomy_term_response( $response, $nav_menu_id );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menus' );
		$request->set_param( 'name', 'My Awesome menus' );
		$request->set_param( 'description', 'This menu is so awesome.' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$headers = $response->get_headers();
		$data    = $response->get_data();
		$this->assertStringContainsString( '/wp/v2/menus/' . $data['id'], $headers['Location'] );
		$this->assertSame( 'My Awesome menus', $data['name'] );
		$this->assertSame( 'This menu is so awesome.', $data['description'] );
		$this->assertSame( 'my-awesome-menus', $data['slug'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_same_name() {
		wp_set_current_user( self::$admin_id );

		wp_update_nav_menu_object(
			0,
			array(
				'description' => 'This menu is so Original',
				'menu-name'   => 'Original',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/menus' );
		$request->set_param( 'name', 'Original' );
		$request->set_param( 'description', 'This menu is so Original' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'menu_exists', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::update_item
	 * @covers ::handle_auto_add
	 */
	public function test_update_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menus/' . $this->menu_id );
		$request->set_param( 'name', 'New Name' );
		$request->set_param( 'description', 'New Description' );
		$request->set_param( 'auto_add', true );
		$request->set_param(
			'meta',
			array(
				'test_single_menu' => 'just meta',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'New Name', $data['name'] );
		$this->assertSame( 'New Description', $data['description'] );
		$this->assertSame( true, $data['auto_add'] );
		$this->assertSame( 'new-name', $data['slug'] );
		$this->assertSame( 'just meta', $data['meta']['test_single_menu'] );
		$this->assertFalse( isset( $data['meta']['test_cat_meta'] ) );
	}

	/**
	 * @ticket 40878
	 * @covers ::delete_item
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );

		$nav_menu_id = wp_update_nav_menu_object(
			0,
			array(
				'description' => 'Deleted Menu',
				'menu-name'   => 'Deleted Menu',
			)
		);

		$term = get_term_by( 'id', $nav_menu_id, self::TAXONOMY );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/menus/' . $term->term_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertSame( 'Deleted Menu', $data['previous']['name'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::prepare_item_for_response
	 * @covers ::get_item
	 */
	public function test_prepare_item() {
		$nav_menu_id = wp_update_nav_menu_object(
			0,
			array(
				'description' => 'Foo Menu',
				'menu-name'   => 'Foo Menu',
			)
		);

		$term = get_term_by( 'id', $nav_menu_id, self::TAXONOMY );
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menus/' . $term->term_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->check_taxonomy_term( $term, $data, $response->get_links() );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/menus' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 7, $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'locations', $properties );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_with_location_permission_correct() {
		$this->register_nav_menu_locations( array( 'primary', 'secondary' ) );
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/menus' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'slug', 'so-awesome' );
		$request->set_param( 'locations', 'primary' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$data      = $response->get_data();
		$term_id   = $data['id'];
		$locations = get_nav_menu_locations();
		$this->assertSame( $locations['primary'], $term_id );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_with_invalid_location() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/menus' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'slug', 'so-awesome' );
		$request->set_param( 'locations', 'bar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertArrayHasKey( 'locations', $response->get_data()['data']['details'] );
		$this->assertSame( 'rest_invalid_menu_location', $response->get_data()['data']['details']['locations']['code'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::update_item
	 */
	public function test_update_item_with_no_location() {
		$this->register_nav_menu_locations( array( 'primary', 'secondary' ) );
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menus/' . $this->menu_id );
		$request->set_param( 'name', 'New Name' );
		$request->set_param( 'description', 'New Description' );
		$request->set_param( 'slug', 'new-slug' );
		$request->set_param( 'locations', 'bar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * @ticket 40878
	 * @covers ::update_item
	 */
	public function test_update_item_with_location_permission_correct() {
		$this->register_nav_menu_locations( array( 'primary', 'secondary' ) );
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/menus/' . $this->menu_id );
		$request->set_param( 'name', 'New Name' );
		$request->set_param( 'description', 'New Description' );
		$request->set_param( 'slug', 'new-slug' );
		$request->set_param( 'locations', 'primary' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$locations = get_nav_menu_locations();
		$this->assertSame( $locations['primary'], $this->menu_id );
	}

	/**
	 * @ticket 40878
	 * @covers ::update_item
	 */
	public function test_update_item_with_location_permission_incorrect() {
		$this->register_nav_menu_locations( array( 'primary', 'secondary' ) );
		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/menus/' . $this->menu_id );
		$request->set_param( 'name', 'New Name' );
		$request->set_param( 'description', 'New Description' );
		$request->set_param( 'slug', 'new-slug' );
		$request->set_param( 'locations', 'primary' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( rest_authorization_required_code(), $response->get_status() );
	}

	/**
	 * @ticket 40878
	 * @covers ::prepare_links
	 */
	public function test_get_item_links() {
		wp_set_current_user( self::$admin_id );

		$nav_menu_id = wp_update_nav_menu_object(
			0,
			array(
				'description' => 'Foo Menu',
				'menu-name'   => 'Foo Menu',
			)
		);

		register_nav_menu( 'foo', 'Bar' );

		set_theme_mod( 'nav_menu_locations', array( 'foo' => $nav_menu_id ) );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/menus/%d', $nav_menu_id ) );
		$response = rest_get_server()->dispatch( $request );

		$links = $response->get_links();
		$this->assertArrayHasKey( 'https://api.w.org/menu-location', $links );

		$location_url = rest_url( '/wp/v2/menu-locations/foo' );
		$this->assertSame( $location_url, $links['https://api.w.org/menu-location'][0]['href'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::update_item
	 * @covers ::handle_locations
	 */
	public function test_change_menu_location() {
		$this->register_nav_menu_locations( array( 'primary', 'secondary' ) );
		$secondary_id = self::factory()->term->create(
			array(
				'name'        => 'Secondary Name',
				'description' => 'Secondary Description',
				'slug'        => 'secondary-slug',
				'taxonomy'    => 'nav_menu',
			)
		);

		$locations              = get_nav_menu_locations();
		$locations['primary']   = $this->menu_id;
		$locations['secondary'] = $secondary_id;
		set_theme_mod( 'nav_menu_locations', $locations );

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menus/' . $this->menu_id );
		$request->set_body_params(
			array(
				'locations' => array( 'secondary' ),
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$locations = get_nav_menu_locations();
		$this->assertArrayNotHasKey( 'primary', $locations );
		$this->assertArrayHasKey( 'secondary', $locations );
		$this->assertSame( $this->menu_id, $locations['secondary'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_items
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menus' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_items
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_item_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menus/' . $this->menu_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_items
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_wrong_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menus' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 403 );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item
	 * @covers ::get_item_permissions_check
	 */
	public function test_get_item_wrong_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menus/' . $this->menu_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 403 );
	}

	/**
	 * @ticket 40878
	 */
	public function test_it_allows_batch_requests_when_updating_menus() {
		$rest_server = rest_get_server();
		// This call is needed to initialize route_options.
		$rest_server->get_routes();
		$route_options = $rest_server->get_route_options( '/wp/v2/menus/(?P<id>[\d]+)' );

		$this->assertArrayHasKey( 'allow_batch', $route_options );
		$this->assertSame( array( 'v1' => true ), $route_options['allow_batch'] );
	}

	/**
	 * @param WP_REST_Response $response Response Class.
	 */
	protected function check_get_taxonomy_terms_response( $response ) {
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$args = array(
			'hide_empty' => false,
		);
		$tags = get_terms( self::TAXONOMY, $args );
		$this->assertCount( count( $tags ), $data );
		$this->assertSame( $tags[0]->term_id, $data[0]['id'] );
		$this->assertSame( $tags[0]->name, $data[0]['name'] );
		$this->assertSame( $tags[0]->slug, $data[0]['slug'] );
		$this->assertSame( $tags[0]->description, $data[0]['description'] );
	}

	/**
	 * @param WP_REST_Response $response Response Class.
	 * @param int              $id Term ID.
	 */
	protected function check_get_taxonomy_term_response( $response, $id ) {
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$menu = get_term( $id, self::TAXONOMY );
		$this->check_taxonomy_term( $menu, $data, $response->get_links() );
	}

	/**
	 * @param WP_Term $term WP_Term object.
	 * @param array   $data Data from REST API.
	 * @param array   $links Array of links.
	 */
	protected function check_taxonomy_term( $term, $data, $links ) {
		$this->assertSame( $term->term_id, $data['id'] );
		$this->assertSame( $term->name, $data['name'] );
		$this->assertSame( $term->slug, $data['slug'] );
		$this->assertSame( $term->description, $data['description'] );
		$this->assertFalse( isset( $data['parent'] ) );

		$locations = get_nav_menu_locations();
		if ( ! empty( $locations ) ) {
			$menu_locations = array();
			foreach ( $locations as $location => $menu_id ) {
				if ( $menu_id === $term->term_id ) {
					$menu_locations[] = $location;
				}
			}

			$this->assertSame( $menu_locations, $data['locations'] );
		}

		$relations = array(
			'self',
			'collection',
			'about',
			'https://api.w.org/post_type',
		);

		if ( ! empty( $data['parent'] ) ) {
			$relations[] = 'up';
		}

		if ( ! empty( $data['locations'] ) ) {
			$relations[] = 'https://api.w.org/menu-location';
		}

		$this->assertSameSets( $relations, array_keys( $links ) );
		$this->assertStringContainsString( 'wp/v2/taxonomies/' . $term->taxonomy, $links['about'][0]['href'] );
		$this->assertSame( add_query_arg( 'menus', $term->term_id, rest_url( 'wp/v2/menu-items' ) ), $links['https://api.w.org/post_type'][0]['href'] );
	}
}
