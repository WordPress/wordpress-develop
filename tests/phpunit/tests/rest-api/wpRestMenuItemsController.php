<?php
/**
 * WP_REST_Menu_Items_Controller tests
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.9.0
 */

/**
 * Tests for REST API for Menu items.
 *
 * @group restapi
 *
 * @coversDefaultClass WP_REST_Menu_Items_Controller
 */
class Tests_REST_WpRestMenuItemsController extends WP_Test_REST_Post_Type_Controller_Testcase {
	/**
	 * @var int
	 */
	protected $menu_id;
	/**
	 * @var int
	 */
	protected $tag_id;
	/**
	 * @var int
	 */
	protected $menu_item_id;

	/**
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 *
	 */
	const POST_TYPE = 'nav_menu_item';

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
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	/**
	 *
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$subscriber_id );
	}

	/**
	 *
	 */
	public function set_up() {
		parent::set_up();

		$this->tag_id = self::factory()->tag->create();

		$this->menu_id = wp_create_nav_menu( rand_str() );

		$this->menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'post_tag',
				'menu-item-object-id' => $this->tag_id,
				'menu-item-status'    => 'publish',
			)
		);
	}

	/**
	 * @ticket 40878
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/menu-items', $routes );
		$this->assertCount( 2, $routes['/wp/v2/menu-items'] );
		$this->assertArrayHasKey( '/wp/v2/menu-items/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/menu-items/(?P<id>[\d]+)'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_context_param
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/menu-items' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		$this->assertSame( array( 'v1' => true ), $data['endpoints'][0]['allow_batch'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/menu-items/' . $this->menu_item_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		$this->assertSame( array( 'v1' => true ), $data['endpoints'][0]['allow_batch'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_collection_params
	 */
	public function test_registered_query_params() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/menu-items' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['endpoints'][0]['args'];
		$this->assertArrayHasKey( 'before', $properties );
		$this->assertArrayHasKey( 'context', $properties );
		$this->assertArrayHasKey( 'exclude', $properties );
		$this->assertArrayHasKey( 'include', $properties );
		$this->assertArrayHasKey( 'menu_order', $properties );
		$this->assertArrayHasKey( 'menus', $properties );
		$this->assertArrayHasKey( 'menus_exclude', $properties );
		$this->assertArrayHasKey( 'offset', $properties );
		$this->assertArrayHasKey( 'order', $properties );
		$this->assertArrayHasKey( 'orderby', $properties );
		$this->assertArrayHasKey( 'page', $properties );
		$this->assertArrayHasKey( 'per_page', $properties );
		$this->assertArrayHasKey( 'search', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'status', $properties );
	}

	/**
	 * @ticket 40878
	 */
	public function test_registered_get_item_params() {
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( '/wp/v2/menu-items/%d', $this->menu_item_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertSame( array( 'context', 'id' ), $keys );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_items
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-items' );
		$response = rest_get_server()->dispatch( $request );

		$this->check_get_menu_items_response( $response );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/menu-items/%d', $this->menu_item_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->check_get_menu_item_response( $response, 'view' );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item
	 */
	public function test_get_item_edit() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/menu-items/%d', $this->menu_item_id ) );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );

		$this->check_get_menu_item_response( $response, 'edit' );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item
	 * @covers ::prepare_links
	 */
	public function test_get_item_term_links() {
		wp_set_current_user( self::$admin_id );

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'post_tag',
				'menu-item-object-id' => $this->tag_id,
				'menu-item-status'    => 'publish',
				'menu-item-title'     => 'Food',
			)
		);
		$request      = new WP_REST_Request( 'GET', sprintf( '/wp/v2/menu-items/%d', $menu_item_id ) );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );

		$this->check_get_menu_item_response( $response, 'edit' );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item
	 * @covers ::prepare_links
	 */
	public function test_get_item_term_posts() {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create();

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
				'menu-item-title'     => 'Food',
			)
		);
		$request      = new WP_REST_Request( 'GET', sprintf( '/wp/v2/menu-items/%d', $menu_item_id ) );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );

		$this->check_get_menu_item_response( $response, 'edit' );
	}

	/**
	 * Test that title.raw contains the verbatim title and that title.rendered
	 * has been passed through the_title which escapes & characters.
	 *
	 * @see https://github.com/WordPress/gutenberg/pull/24673
	 *
	 * @ticket 40878
	 * @covers ::get_item
	 */
	public function test_get_item_escapes_title() {
		wp_set_current_user( self::$admin_id );

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => 'post_tag',
				'menu-item-object-id' => $this->tag_id,
				'menu-item-status'    => 'publish',
				'menu-item-title'     => '<strong>Foo</strong> & bar',
			)
		);

		$request = new WP_REST_Request(
			'GET',
			"/wp/v2/menu-items/$menu_item_id"
		);
		$request->set_query_params(
			array(
				'context' => 'edit',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$data  = $response->get_data();
		$title = $data['title'];

		if ( ! is_multisite() ) {
			// Check that title.raw is the unescaped title and that
			// title.rendered has been run through the_title.
			$this->assertSame( '<strong>Foo</strong> &#038; bar', $title['rendered'] );
			$this->assertSame( '<strong>Foo</strong> & bar', $title['raw'] );
		} else {
			// In a multisite, administrators do not have unfiltered_html and
			// post_title is ran through wp_kses before being saved in the
			// database. Running the title through the_title does nothing in
			// this case.
			$this->assertSame( '<strong>Foo</strong> &amp; bar', $title['rendered'] );
			$this->assertSame( '<strong>Foo</strong> &amp; bar', $title['raw'] );
		}

		wp_delete_post( $menu_item_id );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data();
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->check_create_menu_item_response( $response );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_invalid_invalid() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'menus' => array( 123, 456 ),
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_invalid_term() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'type'  => 'taxonomy',
				'title' => 'Tags',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid_id', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_change_position() {
		wp_set_current_user( self::$admin_id );
		$new_menu_id = wp_create_nav_menu( rand_str() );
		$expected    = array();
		$actual      = array();
		for ( $i = 1; $i < 5; $i ++ ) {
			$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
			$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
			$params = $this->set_menu_item_data(
				array(
					'menu_order' => $i,
					'menus'      => $new_menu_id,
				)
			);
			$request->set_body_params( $params );
			$response = rest_get_server()->dispatch( $request );
			$this->check_create_menu_item_response( $response );
			$data = $response->get_data();

			$expected[] = $i;
			$actual[]   = $data['menu_order'];
		}
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_menu_order_must_be_set() {
		wp_set_current_user( self::$admin_id );
		$new_menu_id = wp_create_nav_menu( rand_str() );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'menu_order' => 0,
				'menus'      => $new_menu_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'menu_order' => 1,
				'menus'      => $new_menu_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_invalid_position_2() {
		wp_set_current_user( self::$admin_id );
		$new_menu_id = wp_create_nav_menu( rand_str() );
		$request     = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'menu_order' => 'ddddd',
				'menus'      => $new_menu_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_invalid_position_3() {
		wp_set_current_user( self::$admin_id );
		$new_menu_id = wp_create_nav_menu( rand_str() );
		$request     = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'menu_order' => -9,
				'menus'      => $new_menu_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_invalid_parent() {
		wp_set_current_user( self::$admin_id );
		wp_create_nav_menu( rand_str() );
		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'parent' => -9,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_invalid_menu() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'menus' => -9,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'invalid_menu_id', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_invalid_post() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'type'  => 'post_type',
				'title' => 'Post',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_invalid_post_type() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'type'             => 'post_type_archive',
				'menu-item-object' => 'invalid_post_type',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_type', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_invalid_custom_link() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'type'  => 'custom',
				'title' => '',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_title_required', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_missing_custom_link_url() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'type' => 'custom',
				'url'  => '',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_url_required', $response, 400 );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_invalid_custom_link_url() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'type' => 'custom',
				'url'  => '"^<>{}`',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertArrayHasKey( 'url', $response->get_data()['data']['details'] );
		$this->assertSame( 'rest_invalid_url', $response->get_data()['data']['details']['url']['code'] );
	}

	/**
	 * @ticket 40878
	 * @covers ::update_item
	 */
	public function test_update_item() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/menu-items/%d', $this->menu_item_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'xfn' => array( 'test1', 'test2', 'test3' ),
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->check_update_menu_item_response( $response );
		$new_data = $response->get_data();
		$this->assertSame( $this->menu_item_id, $new_data['id'] );
		$this->assertSame( $params['title'], $new_data['title']['raw'] );
		$this->assertSame( $params['description'], $new_data['description'] );
		$this->assertSame( $params['type_label'], $new_data['type_label'] );
		$this->assertSame( $params['xfn'], $new_data['xfn'] );
		$post      = get_post( $this->menu_item_id );
		$menu_item = wp_setup_nav_menu_item( $post );
		$this->assertSame( $params['title'], $menu_item->title );
		$this->assertSame( $params['description'], $menu_item->description );
		$this->assertSame( $params['xfn'], explode( ' ', $menu_item->xfn ) );
	}

	/**
	 * @ticket 40878
	 * @covers ::update_item
	 */
	public function test_update_item_clean_xfn() {
		wp_set_current_user( self::$admin_id );

		$bad_data  = array( 'test1":|":', 'test2+|+', 'test3±', 'test4😀' );
		$good_data = array( 'test1', 'test2', 'test3', 'test4' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/menu-items/%d', $this->menu_item_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data(
			array(
				'xfn' => $bad_data,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->check_update_menu_item_response( $response );
		$new_data = $response->get_data();
		$this->assertSame( $this->menu_item_id, $new_data['id'] );
		$this->assertSame( $params['title'], $new_data['title']['raw'] );
		$this->assertSame( $params['description'], $new_data['description'] );
		$this->assertSame( $params['type_label'], $new_data['type_label'] );
		$this->assertSame( $good_data, $new_data['xfn'] );
		$post      = get_post( $this->menu_item_id );
		$menu_item = wp_setup_nav_menu_item( $post );
		$this->assertSame( $params['title'], $menu_item->title );
		$this->assertSame( $params['description'], $menu_item->description );
		$this->assertSame( $good_data, explode( ' ', $menu_item->xfn ) );
	}


	/**
	 * @ticket 40878
	 * @covers ::update_item
	 */
	public function test_update_item_invalid() {
		wp_set_current_user( self::$admin_id );
		$post_id = self::factory()->post->create();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/menu-items/%d', $post_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_menu_item_data();
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 40878
	 * @covers ::delete_item
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/menu-items/%d', $this->menu_item_id ) );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_post( $this->menu_item_id ) );
	}

	/**
	 * @ticket 40878
	 * @covers ::delete_item
	 */
	public function test_delete_item_no_force() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/menu-items/%d', $this->menu_item_id ) );
		$request->set_param( 'force', false );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 501, $response->get_status() );
		$this->assertNotNull( get_post( $this->menu_item_id ) );
	}

	/**
	 * @ticket 40878
	 * @covers ::delete_item
	 */
	public function test_delete_item_invalid() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/menu-items/9999' );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 40878
	 * @covers ::prepare_item_for_response
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-items/' . $this->menu_item_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->check_get_menu_item_response( $response );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/menu-items' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertSame( 18, count( $properties ) );
		$this->assertArrayHasKey( 'type_label', $properties );
		$this->assertArrayHasKey( 'attr_title', $properties );
		$this->assertArrayHasKey( 'classes', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'url', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'menu_order', $properties );
		$this->assertArrayHasKey( 'object', $properties );
		$this->assertArrayHasKey( 'object_id', $properties );
		$this->assertArrayHasKey( 'target', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'xfn', $properties );
		$this->assertArrayHasKey( 'invalid', $properties );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-items' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item_permissions_check
	 */
	public function test_get_item_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-items/' . $this->menu_item_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_wrong_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-items' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 403 );
	}

	/**
	 * @ticket 40878
	 * @covers ::get_item_permissions_check
	 */
	public function test_get_item_wrong_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/menu-items/' . $this->menu_item_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 403 );
	}

	/**
	 * @param WP_REST_Response $response Response Class.
	 * @param string           $context Defaults to View.
	 */
	protected function check_get_menu_items_response( $response, $context = 'view' ) {
		$this->assertNotWPError( $response );
		$response = rest_ensure_response( $response );
		$this->assertSame( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );

		$all_data = $response->get_data();
		foreach ( $all_data as $data ) {
			$post = get_post( $data['id'] );
			// Base fields for every post.
			$menu_item = wp_setup_nav_menu_item( $post );
			/**
			 * As the links for the post are "response_links" format in the data array we have to pull them out and parse them.
			 */
			$links = $data['_links'];
			foreach ( $links as &$links_array ) {
				foreach ( $links_array as &$link ) {
					$attributes         = array_diff_key(
						$link,
						array(
							'href' => 1,
							'name' => 1,
						)
					);
					$link               = array_diff_key( $link, $attributes );
					$link['attributes'] = $attributes;
				}
			}

			$this->check_menu_item_data( $menu_item, $data, $context, $links );
		}
	}

	/**
	 * @param WP_Post $post WP_Post object.
	 * @param array   $data Data compare.
	 * @param string  $context Context of REST Request.
	 * @param array   $links Array links.
	 */
	protected function check_menu_item_data( $post, $data, $context, $links ) {
		$post_type_obj = get_post_type_object( self::POST_TYPE );

		// Standard fields.
		$this->assertSame( $post->ID, $data['id'] );
		$this->assertSame( wpautop( $post->post_content ), $data['description'] );

		// Check filtered values.
		if ( post_type_supports( self::POST_TYPE, 'title' ) ) {
			add_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
			$this->assertSame( $post->title, $data['title']['rendered'] );
			remove_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
			if ( 'edit' === $context ) {
				$this->assertSame( $post->title, $data['title']['raw'] );
			} else {
				$this->assertFalse( isset( $data['title']['raw'] ) );
			}
		} else {
			$this->assertFalse( isset( $data['title'] ) );
		}

		// post_parent.
		$this->assertArrayHasKey( 'parent', $data );
		if ( $post->post_parent ) {
			if ( is_int( $data['parent'] ) ) {
				$this->assertSame( $post->post_parent, $data['parent'] );
			} else {
				$this->assertSame( $post->post_parent, $data['parent']['id'] );
				$menu_item = wp_setup_nav_menu_item( get_post( $data['parent']['id'] ) );
				$this->check_get_menu_item_response( $data['parent'], $menu_item, 'view-parent' );
			}
		} else {
			$this->assertEmpty( $data['parent'] );
		}

		$this->assertFalse( $data['invalid'] );

		// page attributes.
		$this->assertSame( $post->menu_order, $data['menu_order'] );

		$taxonomies = wp_list_filter( get_object_taxonomies( self::POST_TYPE, 'objects' ), array( 'show_in_rest' => true ) );
		foreach ( $taxonomies as $taxonomy ) {
			$this->assertTrue( isset( $data[ $taxonomy->rest_base ] ) );
			$terms = wp_get_object_terms( $post->ID, $taxonomy->name, array( 'fields' => 'ids' ) );
			sort( $terms );
			if ( 'nav_menu' === $taxonomy->name ) {
				$term_id = $terms ? array_shift( $terms ) : 0;
				$this->assertSame( $term_id, $data[ $taxonomy->rest_base ] );
			} else {
				sort( $data[ $taxonomy->rest_base ] );
				$this->assertSame( $terms, $data[ $taxonomy->rest_base ] );
			}
		}

		// test links.
		if ( $links ) {
			$links = test_rest_expand_compact_links( $links );
			$this->assertSame( $links['self'][0]['href'], rest_url( 'wp/v2/' . $post_type_obj->rest_base . '/' . $data['id'] ) );
			$this->assertSame( $links['collection'][0]['href'], rest_url( 'wp/v2/' . $post_type_obj->rest_base ) );
			$this->assertSame( $links['about'][0]['href'], rest_url( 'wp/v2/types/' . self::POST_TYPE ) );

			$num = 0;
			foreach ( $taxonomies as $taxonomy ) {
				$this->assertSame( $taxonomy->name, $links['https://api.w.org/term'][ $num ]['attributes']['taxonomy'] );
				$this->assertSame( add_query_arg( 'post', $data['id'], rest_url( 'wp/v2/' . $taxonomy->rest_base ) ), $links['https://api.w.org/term'][ $num ]['href'] );
				$num ++;
			}

			if ( 'post_type' === $data['type'] ) {
				$this->assertArrayHasKey( 'https://api.w.org/menu-item-object', $links );
				$this->assertArrayHasKey( $data['type'], $links['https://api.w.org/menu-item-object'][0]['attributes'] );
				$this->assertSame( $links['https://api.w.org/menu-item-object'][0]['href'], rest_url( rest_get_route_for_post( $data['object_id'] ) ) );
			}

			if ( 'taxonomy' === $data['type'] ) {
				$this->assertArrayHasKey( 'https://api.w.org/menu-item-object', $links );
				$this->assertArrayHasKey( $data['type'], $links['https://api.w.org/menu-item-object'][0]['attributes'] );
				$this->assertSame( $links['https://api.w.org/menu-item-object'][0]['href'], rest_url( rest_get_route_for_term( $data['object_id'] ) ) );
			}
		}
	}

	/**
	 * @param WP_REST_Response $response Response Class.
	 * @param string           $context Defaults to View.
	 */
	protected function check_get_menu_item_response( $response, $context = 'view' ) {
		$this->assertNotWPError( $response );
		$response = rest_ensure_response( $response );
		$this->assertSame( 200, $response->get_status() );

		$data      = $response->get_data();
		$post      = get_post( $data['id'] );
		$menu_item = wp_setup_nav_menu_item( $post );
		$this->check_menu_item_data( $menu_item, $data, $context, $response->get_links() );
	}

	/**
	 * @param WP_REST_Response $response Response Class.
	 */
	protected function check_create_menu_item_response( $response ) {
		$this->assertNotWPError( $response );
		$response = rest_ensure_response( $response );

		$this->assertSame( 201, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );

		$data      = $response->get_data();
		$post      = get_post( $data['id'] );
		$menu_item = wp_setup_nav_menu_item( $post );
		$this->check_menu_item_data( $menu_item, $data, 'edit', $response->get_links() );
	}

	/**
	 * @param WP_REST_Response $response Response Class.
	 */
	protected function check_update_menu_item_response( $response ) {
		$this->assertNotWPError( $response );
		$response = rest_ensure_response( $response );

		$this->assertSame( 200, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayNotHasKey( 'Location', $headers );

		$data      = $response->get_data();
		$post      = get_post( $data['id'] );
		$menu_item = wp_setup_nav_menu_item( $post );
		$this->check_menu_item_data( $menu_item, $data, 'edit', $response->get_links() );
	}

	/**
	 * @param array $args Override params.
	 *
	 * @return mixed
	 */
	protected function set_menu_item_data( $args = array() ) {
		$defaults = array(
			'object_id'   => 0,
			'parent'      => 0,
			'menu_order'  => 1,
			'menus'       => $this->menu_id,
			'type'        => 'custom',
			'title'       => 'Custom Link Title',
			'url'         => '#',
			'description' => '',
			'attr-title'  => '',
			'target'      => '',
			'type_label'  => 'Custom Link',
			'classes'     => '',
			'xfn'         => '',
			'status'      => 'draft',
		);

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * @ticket 40878
	 * @covers ::create_item
	 */
	public function test_create_item_properly_handles_slashed_data() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/menu-items' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$parameters = $this->set_menu_item_data(
			array(
				'title' => 'Some \\\'title',
			)
		);
		$request->set_body_params( $parameters );
		$response = rest_get_server()->dispatch( $request );
		$this->assertNotWPError( $response->as_error() );
		$data = $response->get_data();
		$post = get_post( $data['id'] );
		$this->assertSame( $parameters['title'], $post->post_title );
	}

	/**
	 * @ticket 40878
	 * @covers ::update_item
	 */
	public function test_update_item_properly_handles_slashed_data() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/menu-items/%d', $this->menu_item_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$title  = 'Some \\\'title';
		$params = $this->set_menu_item_data(
			array(
				'title' => $title,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertSame( $params['title'], $new_data['title']['raw'] );
	}
}
