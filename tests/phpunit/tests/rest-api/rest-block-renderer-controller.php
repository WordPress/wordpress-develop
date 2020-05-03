<?php
/**
 * WP_REST_Block_Renderer_Controller tests.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.0.0
 */

/**
 * Tests for WP_REST_Block_Renderer_Controller.
 *
 * @since 5.0.0
 *
 * @covers WP_REST_Block_Renderer_Controller
 *
 * @group restapi-blocks
 * @group restapi
 */
class REST_Block_Renderer_Controller_Test extends WP_Test_REST_Controller_Testcase {

	/**
	 * The REST API route for the block renderer.
	 *
	 * @since 5.0.0
	 *
	 * @var string
	 */
	protected static $rest_api_route = '/wp/v2/block-renderer/';

	/**
	 * Test block's name.
	 *
	 * @since 5.0.0
	 *
	 * @var string
	 */
	protected static $block_name = 'core/test-block';

	/**
	 * Test post context block's name.
	 *
	 * @since 5.0.0
	 *
	 * @var string
	 */
	protected static $context_block_name = 'core/context-test-block';

	/**
	 * Test API user's ID.
	 *
	 * @since 5.0.0
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test post ID.
	 *
	 * @since 5.0.0
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Author test user ID.
	 *
	 * @since 5.0.0
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Create test data before the tests run.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);

		self::$author_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Test Post',
			)
		);
	}

	/**
	 * Delete test data after our tests run.
	 *
	 * @since 5.0.0
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user_id );
	}

	/**
	 * Set up each test method.
	 *
	 * @since 5.0.0
	 */
	public function setUp() {
		$this->register_test_block();
		$this->register_post_context_test_block();
		parent::setUp();
	}

	/**
	 * Tear down each test method.
	 *
	 * @since 5.0.0
	 */
	public function tearDown() {
		WP_Block_Type_Registry::get_instance()->unregister( self::$block_name );
		WP_Block_Type_Registry::get_instance()->unregister( self::$context_block_name );
		parent::tearDown();
	}

	/**
	 * Register test block.
	 *
	 * @since 5.0.0
	 */
	public function register_test_block() {
		register_block_type(
			self::$block_name,
			array(
				'attributes'      => array(
					'some_string' => array(
						'type'    => 'string',
						'default' => 'some_default',
					),
					'some_int'    => array(
						'type' => 'integer',
					),
					'some_array'  => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
				),
				'render_callback' => array( $this, 'render_test_block' ),
			)
		);
	}

	/**
	 * Register test block with post_id as attribute for post context test.
	 *
	 * @since 5.0.0
	 */
	public function register_post_context_test_block() {
		register_block_type(
			self::$context_block_name,
			array(
				'attributes'      => array(),
				'render_callback' => array( $this, 'render_post_context_test_block' ),
			)
		);
	}

	/**
	 * Test render callback.
	 *
	 * @since 5.0.0
	 *
	 * @param array $attributes Props.
	 * @return string Rendered attributes, which is here just JSON.
	 */
	public function render_test_block( $attributes ) {
		return wp_json_encode( $attributes );
	}

	/**
	 * Test render callback for testing post context.
	 *
	 * @since 5.0.0
	 *
	 * @return string
	 */
	public function render_post_context_test_block() {
		return get_the_title();
	}

	/**
	 * Check that the route was registered properly.
	 *
	 * @ticket 45098
	 *
	 * @covers WP_REST_Block_Renderer_Controller::register_routes()
	 */
	public function test_register_routes() {
		$dynamic_block_names = get_dynamic_block_names();
		$this->assertContains( self::$block_name, $dynamic_block_names );

		$routes = rest_get_server()->get_routes();
		foreach ( $dynamic_block_names as $dynamic_block_name ) {
			$this->assertArrayHasKey( self::$rest_api_route . "(?P<name>$dynamic_block_name)", $routes );
		}
	}

	/**
	 * Test getting item without permissions.
	 *
	 * @ticket 45098
	 *
	 * @covers WP_REST_Block_Renderer_Controller::get_item()
	 */
	public function test_get_item_without_permissions() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', self::$rest_api_route . self::$block_name );
		$request->set_param( 'context', 'edit' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'block_cannot_read', $response, rest_authorization_required_code() );
	}

	/**
	 * Test getting item without 'edit' context.
	 *
	 * @ticket 45098
	 */
	public function test_get_item_with_invalid_context() {
		wp_set_current_user( self::$user_id );

		$request  = new WP_REST_Request( 'GET', self::$rest_api_route . self::$block_name );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test getting item with invalid block name.
	 *
	 * @ticket 45098
	 *
	 * @covers WP_REST_Block_Renderer_Controller::get_item()
	 */
	public function test_get_item_invalid_block_name() {
		wp_set_current_user( self::$user_id );
		$request = new WP_REST_Request( 'GET', self::$rest_api_route . 'core/123' );

		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	/**
	 * Check getting item with an invalid param provided.
	 *
	 * @ticket 45098
	 *
	 * @covers WP_REST_Block_Renderer_Controller::get_item()
	 */
	public function test_get_item_invalid_attribute() {
		wp_set_current_user( self::$user_id );
		$request = new WP_REST_Request( 'GET', self::$rest_api_route . self::$block_name );
		$request->set_param( 'context', 'edit' );
		$request->set_param(
			'attributes',
			array(
				'some_string' => array( 'no!' ),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Check getting item with an invalid param provided.
	 *
	 * @ticket 45098
	 *
	 * @covers WP_REST_Block_Renderer_Controller::get_item()
	 */
	public function test_get_item_unrecognized_attribute() {
		wp_set_current_user( self::$user_id );
		$request = new WP_REST_Request( 'GET', self::$rest_api_route . self::$block_name );
		$request->set_param( 'context', 'edit' );
		$request->set_param(
			'attributes',
			array(
				'unrecognized' => 'yes',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Check getting item with default attributes provided.
	 *
	 * @ticket 45098
	 *
	 * @covers WP_REST_Block_Renderer_Controller::get_item()
	 */
	public function test_get_item_default_attributes() {
		wp_set_current_user( self::$user_id );

		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( self::$block_name );
		$defaults   = array();
		foreach ( $block_type->attributes as $key => $attribute ) {
			if ( isset( $attribute['default'] ) ) {
				$defaults[ $key ] = $attribute['default'];
			}
		}

		$request = new WP_REST_Request( 'GET', self::$rest_api_route . self::$block_name );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'attributes', array() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertEquals( $defaults, json_decode( $data['rendered'], true ) );
		$this->assertEquals(
			json_decode( $block_type->render( $defaults ) ),
			json_decode( $data['rendered'] )
		);
	}

	/**
	 * Check getting item with attributes provided.
	 *
	 * @ticket 45098
	 *
	 * @covers WP_REST_Block_Renderer_Controller::get_item()
	 */
	public function test_get_item() {
		wp_set_current_user( self::$user_id );

		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( self::$block_name );
		$attributes = array(
			'some_int'    => '123',
			'some_string' => 'foo',
			'some_array'  => array( 1, '2', 3 ),
		);

		$expected_attributes               = $attributes;
		$expected_attributes['some_int']   = (int) $expected_attributes['some_int'];
		$expected_attributes['some_array'] = array_map( 'intval', $expected_attributes['some_array'] );

		$request = new WP_REST_Request( 'GET', self::$rest_api_route . self::$block_name );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'attributes', $attributes );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertEquals( $expected_attributes, json_decode( $data['rendered'], true ) );
		$this->assertEquals(
			json_decode( $block_type->render( $attributes ), true ),
			json_decode( $data['rendered'], true )
		);
	}

	/**
	 * Check filtering block output using the pre_render_block filter.
	 *
	 * @ticket 49387
	 */
	public function test_get_item_with_pre_render_block_filter() {
		wp_set_current_user( self::$user_id );

		$pre_render_filter = function( $output, $block ) {
			if ( $block['blockName'] === self::$block_name ) {
				return '<p>Alternate content.</p>';
			}
		};
		add_filter( 'pre_render_block', $pre_render_filter, 10, 2 );

		$attributes = array(
			'some_int'    => '123',
			'some_string' => 'foo',
			'some_array'  => array( 1, '2', 3 ),
		);

		$request = new WP_REST_Request( 'GET', self::$rest_api_route . self::$block_name );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'attributes', $attributes );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( '<p>Alternate content.</p>', $data['rendered'] );

		remove_filter( 'pre_render_block', $pre_render_filter );
	}

	/**
	 * Check success response for getting item with layout attribute provided.
	 *
	 * @ticket 45098
	 */
	public function test_get_item_with_layout() {
		wp_set_current_user( self::$user_id );

		$attributes = array(
			'layout' => 'foo',
		);

		$request = new WP_REST_Request( 'GET', self::$rest_api_route . self::$block_name );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'attributes', $attributes );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test getting item with post context.
	 *
	 * @ticket 45098
	 */
	public function test_get_item_with_post_context() {
		wp_set_current_user( self::$user_id );

		$expected_title = 'Test Post';
		$request        = new WP_REST_Request( 'GET', self::$rest_api_route . self::$context_block_name );
		$request->set_param( 'context', 'edit' );

		// Test without post ID.
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertTrue( empty( $data['rendered'] ) );

		// Now test with post ID.
		$request->set_param( 'post_id', self::$post_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertEquals( $expected_title, $data['rendered'] );
	}

	/**
	 * Test a POST request, with the attributes in the body.
	 *
	 * @ticket 49680
	 */
	public function test_get_item_post_request() {
		wp_set_current_user( self::$user_id );
		$string_attribute = 'Lorem ipsum dolor';
		$attributes       = array( 'some_string' => $string_attribute );
		$request          = new WP_REST_Request( 'POST', self::$rest_api_route . self::$block_name );
		$request->set_param( 'context', 'edit' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( compact( 'attributes' ) ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertContains( $string_attribute, $response->get_data()['rendered'] );
	}

	/**
	 * Test getting item with invalid post ID.
	 *
	 * @ticket 45098
	 */
	public function test_get_item_without_permissions_invalid_post() {
		wp_set_current_user( self::$user_id );

		$request = new WP_REST_Request( 'GET', self::$rest_api_route . self::$context_block_name );
		$request->set_param( 'context', 'edit' );

		// Test with invalid post ID.
		$request->set_param( 'post_id', PHP_INT_MAX );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'block_cannot_read', $response, 403 );
	}

	/**
	 * Test getting item without permissions to edit context post.
	 *
	 * @ticket 45098
	 */
	public function test_get_item_without_permissions_cannot_edit_post() {
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'GET', self::$rest_api_route . self::$context_block_name );
		$request->set_param( 'context', 'edit' );

		// Test with private post ID.
		$request->set_param( 'post_id', self::$post_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'block_cannot_read', $response, 403 );
	}

	/**
	 * Get item schema.
	 *
	 * @ticket 45098
	 *
	 * @covers WP_REST_Block_Renderer_Controller::get_item_schema()
	 */
	public function test_get_item_schema() {
		$request  = new WP_REST_Request( 'OPTIONS', self::$rest_api_route . self::$block_name );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEqualSets( array( 'GET', 'POST' ), $data['endpoints'][0]['methods'] );
		$this->assertEqualSets(
			array( 'name', 'context', 'attributes', 'post_id' ),
			array_keys( $data['endpoints'][0]['args'] )
		);
		$this->assertEquals( 'object', $data['endpoints'][0]['args']['attributes']['type'] );

		$this->assertArrayHasKey( 'schema', $data );
		$this->assertEquals( 'rendered-block', $data['schema']['title'] );
		$this->assertEquals( 'object', $data['schema']['type'] );
		$this->arrayHasKey( 'rendered', $data['schema']['properties'] );
		$this->arrayHasKey( 'string', $data['schema']['properties']['rendered']['type'] );
		$this->assertEquals( array( 'edit' ), $data['schema']['properties']['rendered']['context'] );
	}

	/**
	 * The update_item() method does not exist for block rendering.
	 */
	public function test_update_item() {
		$this->markTestSkipped( 'Controller does not implement update_item().' );
	}

	/**
	 * The create_item() method does not exist for block rendering.
	 */
	public function test_create_item() {
		$this->markTestSkipped( 'Controller does not implement create_item().' );
	}

	/**
	 * The delete_item() method does not exist for block rendering.
	 */
	public function test_delete_item() {
		$this->markTestSkipped( 'Controller does not implement delete_item().' );
	}

	/**
	 * The get_items() method does not exist for block rendering.
	 */
	public function test_get_items() {
		$this->markTestSkipped( 'Controller does not implement get_items().' );
	}

	/**
	 * The context_param() method does not exist for block rendering.
	 */
	public function test_context_param() {
		$this->markTestSkipped( 'Controller does not implement context_param().' );
	}

	/**
	 * The prepare_item() method does not exist for block rendering.
	 */
	public function test_prepare_item() {
		$this->markTestSkipped( 'Controller does not implement prepare_item().' );
	}
}
