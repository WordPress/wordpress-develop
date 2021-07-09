<?php
/**
 * Unit tests covering WP_REST_Sidebars_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.8.0
 */

/**
 * Tests for REST API for Widgets.
 *
 * @see WP_Test_REST_Controller_Testcase
 * @group restapi
 * @group widgets
 * @covers WP_REST_Sidebars_Controller
 */
class WP_Test_REST_Sidebars_Controller extends WP_Test_REST_Controller_Testcase {

	/**
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id  = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$author_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		wp_delete_user( self::$admin_id );
		wp_delete_user( self::$author_id );
	}

	public function setUp() {
		parent::setUp();

		wp_set_current_user( self::$admin_id );

		// Unregister all widgets and sidebars.
		global $wp_registered_sidebars, $_wp_sidebars_widgets;
		$wp_registered_sidebars = array();
		$_wp_sidebars_widgets   = array();
		update_option( 'sidebars_widgets', array() );
	}

	public function clean_up_global_scope() {
		global $wp_widget_factory, $wp_registered_sidebars, $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_widget_updates;

		$wp_registered_sidebars        = array();
		$wp_registered_widgets         = array();
		$wp_registered_widget_controls = array();
		$wp_registered_widget_updates  = array();
		$wp_widget_factory->widgets    = array();

		parent::clean_up_global_scope();
	}

	private function setup_widget( $option_name, $number, $settings ) {
		$this->setup_widgets(
			$option_name,
			array(
				$number => $settings,
			)
		);
	}

	private function setup_widgets( $option_name, $settings ) {
		update_option( $option_name, $settings );
	}

	private function setup_sidebar( $id, $attrs = array(), $widgets = array() ) {
		global $wp_registered_sidebars;
		update_option(
			'sidebars_widgets',
			array(
				$id => $widgets,
			)
		);
		$wp_registered_sidebars[ $id ] = array_merge(
			array(
				'id'            => $id,
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			$attrs
		);

		global $wp_registered_widgets;
		foreach ( $wp_registered_widgets as $wp_registered_widget ) {
			if ( is_array( $wp_registered_widget['callback'] ) ) {
				$wp_registered_widget['callback'][0]->_register();
			}
		}
	}

	/**
	 * @ticket 41683
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/sidebars', $routes );
		$this->assertArrayHasKey( '/wp/v2/sidebars/(?P<id>[\w-]+)', $routes );
	}

	/**
	 * @ticket 41683
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sidebars/sidebar-1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items() {
		wp_widgets_init();

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( array(), $data );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_wrong_permission_author() {
		wp_set_current_user( self::$author_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_basic_sidebar() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'sidebar-1',
					'name'          => 'Test sidebar',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_active_sidebar_with_widgets() {
		wp_widgets_init();

		$this->setup_widget(
			'widget_rss',
			1,
			array(
				'title' => 'RSS test',
			)
		);
		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'sidebar-1',
					'name'          => 'Test sidebar',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(
						'text-1',
						'rss-1',
					),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 53489
	 */
	public function test_get_items_when_registering_new_sidebars() {
		register_sidebar(
			array(
				'name'          => 'New Sidebar',
				'id'            => 'new-sidebar',
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'wp_inactive_widgets',
					'name'          => 'Inactive widgets',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'inactive',
					'widgets'       => array(),
				),
				array(
					'id'            => 'new-sidebar',
					'name'          => 'New Sidebar',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars/sidebar-1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				'id'            => 'sidebar-1',
				'name'          => 'Test sidebar',
				'description'   => '',
				'class'         => '',
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
				'status'        => 'active',
				'widgets'       => array(),
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_no_permission() {
		wp_set_current_user( 0 );
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars/sidebar-1' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_wrong_permission_author() {
		wp_set_current_user( self::$author_id );
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars/sidebar-1' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * The test_create_item() method does not exist for sidebar.
	 */
	public function test_create_item() {
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item() {
		wp_widgets_init();

		$this->setup_widget(
			'widget_rss',
			1,
			array(
				'title' => 'RSS test',
			)
		);
		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_widget(
			'widget_text',
			2,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sidebars/sidebar-1' );
		$request->set_body_params(
			array(
				'widgets' => array(
					'text-1',
					'text-2',
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				'id'            => 'sidebar-1',
				'name'          => 'Test sidebar',
				'description'   => '',
				'class'         => '',
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
				'status'        => 'active',
				'widgets'       => array(
					'text-1',
					'text-2',
				),
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_removes_widget_from_existing_sidebar() {
		wp_widgets_init();

		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1' )
		);
		$this->setup_sidebar(
			'sidebar-2',
			array(
				'name' => 'Test sidebar 2',
			),
			array()
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sidebars/sidebar-2' );
		$request->set_body_params(
			array(
				'widgets' => array(
					'text-1',
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertContains( 'text-1', $data['widgets'] );

		$this->assertNotContains( 'text-1', rest_do_request( '/wp/v2/sidebars/sidebar-1' )->get_data()['widgets'] );
	}

	/**
	 * @ticket 53612
	 */
	public function test_batch_remove_widgets_from_existing_sidebar() {
		wp_widgets_init();

		$this->setup_widgets(
			'widget_text',
			array(
				2 => array( 'text' => 'Text widget' ),
				3 => array( 'text' => 'Text widget' ),
				4 => array( 'text' => 'Text widget' ),
				5 => array( 'text' => 'Text widget' ),
				6 => array( 'text' => 'Text widget' ),
			)
		);

		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-2', 'text-3', 'text-4', 'text-5', 'text-6' )
		);

		$request = new WP_REST_Request( 'POST', '/batch/v1' );
		$request->set_body_params(
			array(
				'requests' => array(
					array(
						'method' => 'DELETE',
						'path'   => '/wp/v2/widgets/text-2?force=1',
					),
					array(
						'method' => 'DELETE',
						'path'   => '/wp/v2/widgets/text-3?force=1',
					),
				),
			)
		);
		rest_get_server()->dispatch( $request );

		$this->assertSame(
			array( 'text-4', 'text-5', 'text-6' ),
			rest_do_request( '/wp/v2/sidebars/sidebar-1' )->get_data()['widgets']
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_moves_omitted_widget_to_inactive_sidebar() {
		wp_widgets_init();

		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_widget(
			'widget_text',
			2,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1' )
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sidebars/sidebar-1' );
		$request->set_body_params(
			array(
				'widgets' => array(
					'text-2',
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertContains( 'text-2', $data['widgets'] );
		$this->assertNotContains( 'text-1', $data['widgets'] );

		$this->assertContains( 'text-1', rest_do_request( '/wp/v2/sidebars/wp_inactive_widgets' )->get_data()['widgets'] );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_inactive_widgets() {
		wp_widgets_init();

		$this->setup_widget(
			'widget_rss',
			1,
			array(
				'title' => 'RSS test',
			)
		);
		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1' )
		);
		update_option(
			'sidebars_widgets',
			array_merge(
				get_option( 'sidebars_widgets' ),
				array(
					'wp_inactive_widgets' => array( 'rss-1', 'rss' ),
				)
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$request->set_param( 'context', 'view' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'sidebar-1',
					'name'          => 'Test sidebar',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(
						'text-1',
					),
				),
				array(
					'id'            => 'wp_inactive_widgets',
					'name'          => 'Inactive widgets',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'inactive',
					'widgets'       => array(
						'rss-1',
					),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_no_permission() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sidebars/sidebar-1' );
		$request->set_body_params(
			array(
				'widgets' => array(),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_wrong_permission_author() {
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sidebars/sidebar-1' );
		$request->set_body_params(
			array(
				'widgets' => array(),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * The test_delete_item() method does not exist for sidebar.
	 */
	public function test_delete_item() {
	}

	/**
	 * The test_prepare_item() method does not exist for sidebar.
	 */
	public function test_prepare_item() {
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_schema() {
		wp_set_current_user( self::$admin_id );
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/sidebars' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'widgets', $properties );
		$this->assertArrayHasKey( 'class', $properties );
		$this->assertArrayHasKey( 'before_widget', $properties );
		$this->assertArrayHasKey( 'after_widget', $properties );
		$this->assertArrayHasKey( 'before_title', $properties );
		$this->assertArrayHasKey( 'after_title', $properties );
		$this->assertCount( 10, $properties );
	}

	/**
	 * Helper to remove links key.
	 *
	 * @param array $data Array of data.
	 *
	 * @return array
	 */
	protected function remove_links( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$count = 0;
		foreach ( $data as $item ) {
			if ( isset( $item['_links'] ) ) {
				unset( $data[ $count ]['_links'] );
			}
			$count ++;
		}

		return $data;
	}
}
