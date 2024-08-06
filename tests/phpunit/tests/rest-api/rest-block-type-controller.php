<?php
/**
 * Unit tests covering WP_REST_Block_Types_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.5.0
 *
 * @covers WP_REST_Block_Types_Controller
 *
 * @group restapi-blocks
 * @group restapi
 */
class REST_Block_Type_Controller_Test extends WP_Test_REST_Controller_Testcase {

	/**
	 * Admin user ID.
	 *
	 * @since 5.5.0
	 *
	 * @var int $subscriber_id
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @since 5.5.0
	 *
	 * @var int $subscriber_id
	 */
	protected static $subscriber_id;

	/**
	 * Create fake data before our tests run.
	 *
	 * @since 5.5.0
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
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

		$name     = 'fake/test';
		$settings = array(
			'icon' => 'text',
		);

		register_block_type( $name, $settings );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$subscriber_id );
		unregister_block_type( 'fake/test' );
		unregister_block_type( 'fake/invalid' );
		unregister_block_type( 'fake/false' );
	}

	/**
	 * @ticket 47620
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/block-types', $routes );
		$this->assertCount( 1, $routes['/wp/v2/block-types'] );
		$this->assertArrayHasKey( '/wp/v2/block-types/(?P<namespace>[a-zA-Z0-9_-]+)', $routes );
		$this->assertCount( 1, $routes['/wp/v2/block-types/(?P<namespace>[a-zA-Z0-9_-]+)'] );
		$this->assertArrayHasKey( '/wp/v2/block-types/(?P<namespace>[a-zA-Z0-9_-]+)/(?P<name>[a-zA-Z0-9_-]+)', $routes );
		$this->assertCount( 1, $routes['/wp/v2/block-types/(?P<namespace>[a-zA-Z0-9_-]+)/(?P<name>[a-zA-Z0-9_-]+)'] );
	}

	/**
	 * @ticket 47620
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/block-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/block-types/fake/test' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @ticket 47620
	 */
	public function test_get_items() {
		$block_name = 'fake/test';
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/fake' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		$this->check_block_type_object( $block_type, $data[0], $data[0]['_links'] );
	}

	/**
	 * @ticket 47620
	 */
	public function test_get_item() {
		$block_name = 'fake/test';
		wp_set_current_user( self::$admin_id );
		$request    = new WP_REST_Request( 'GET', '/wp/v2/block-types/' . $block_name );
		$response   = rest_get_server()->dispatch( $request );
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		$this->check_block_type_object( $block_type, $response->get_data(), $response->get_links() );
	}

	/**
	 * @ticket 47620
	 */
	public function test_get_item_with_styles() {
		$block_name   = 'fake/styles';
		$block_styles = array(
			'name'         => 'fancy-quote',
			'label'        => 'Fancy Quote',
			'style_handle' => 'myguten-style',
		);
		register_block_type( $block_name );
		register_block_style( $block_name, $block_styles );
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/' . $block_name );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameSets( array( $block_styles ), $data['styles'] );
	}

	/**
	 * @ticket 47620
	 */
	public function test_get_item_with_styles_merge() {
		$block_name   = 'fake/styles2';
		$block_styles = array(
			'name'         => 'fancy-quote',
			'label'        => 'Fancy Quote',
			'style_handle' => 'myguten-style',
		);
		$settings     = array(
			'styles' => array(
				array(
					'name'         => 'blue-quote',
					'label'        => 'Blue Quote',
					'style_handle' => 'myguten-style',
				),
			),
		);
		register_block_type( $block_name, $settings );
		register_block_style( $block_name, $block_styles );
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/' . $block_name );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$expected = array(
			array(
				'name'         => 'fancy-quote',
				'label'        => 'Fancy Quote',
				'style_handle' => 'myguten-style',
			),
			array(
				'name'         => 'blue-quote',
				'label'        => 'Blue Quote',
				'style_handle' => 'myguten-style',
			),
		);
		$this->assertSameSets( $expected, $data['styles'] );
	}

	/**
	 * @ticket 47620
	 */
	public function test_get_block_invalid_name() {
		$block_type = 'fake/block';
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/' . $block_type );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_block_type_invalid', $response, 404 );
	}

	/**
	 * @ticket 47620
	 * @ticket 57585
	 * @ticket 59346
	 * @ticket 59797
	 */
	public function test_get_item_invalid() {
		$block_type = 'fake/invalid';
		$settings   = array(
			'title'            => true,
			'category'         => true,
			'parent'           => 'invalid_parent',
			'ancestor'         => 'invalid_ancestor',
			'allowed_blocks'   => 'invalid_allowed_blocks',
			'icon'             => true,
			'description'      => true,
			'keywords'         => 'invalid_keywords',
			'textdomain'       => true,
			'attributes'       => 'invalid_attributes',
			'provides_context' => 'invalid_provides_context',
			'uses_context'     => 'invalid_uses_context',
			'selectors'        => 'invalid_selectors',
			'supports'         => 'invalid_supports',
			'styles'           => array(),
			'example'          => 'invalid_example',
			'variations'       => 'invalid_variations',
			'block_hooks'      => 'invalid_block_hooks',
			'render_callback'  => 'invalid_callback',
			'editor_script'    => true,
			'script'           => true,
			'view_script'      => true,
			'editor_style'     => true,
			'style'            => true,
		);
		register_block_type( $block_type, $settings );
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/' . $block_type );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $block_type, $data['name'] );
		$this->assertSame( '1', $data['title'] );
		$this->assertNull( $data['category'] );
		$this->assertSameSets( array( 'invalid_parent' ), $data['parent'] );
		$this->assertSameSets( array( 'invalid_ancestor' ), $data['ancestor'] );
		$this->assertSameSets( array( 'invalid_allowed_blocks' ), $data['allowed_blocks'] );
		$this->assertNull( $data['icon'] );
		$this->assertSame( '1', $data['description'] );
		$this->assertSameSets( array( 'invalid_keywords' ), $data['keywords'] );
		$this->assertNull( $data['textdomain'] );
		$this->assertSameSetsWithIndex(
			array(
				'lock'     => array( 'type' => 'object' ),
				'metadata' => array( 'type' => 'object' ),
			),
			$data['attributes']
		);
		$this->assertSameSets( array( 'invalid_uses_context' ), $data['uses_context'] );
		$this->assertSameSets( array(), $data['provides_context'] );
		$this->assertSameSets( array(), $data['selectors'], 'invalid selectors defaults to empty array' );
		$this->assertSameSets( array(), $data['supports'] );
		$this->assertSameSets( array(), $data['styles'] );
		$this->assertNull( $data['example'] );
		$this->assertSameSets( array( array() ), $data['variations'] );
		$this->assertSameSets( array(), $data['block_hooks'], 'invalid block_hooks defaults to empty array' );
		$this->assertSameSets( array(), $data['editor_script_handles'] );
		$this->assertSameSets( array(), $data['script_handles'] );
		$this->assertSameSets( array(), $data['view_script_handles'] );
		$this->assertSameSets( array(), $data['view_script_module_ids'] );
		$this->assertSameSets( array(), $data['editor_style_handles'] );
		$this->assertSameSets( array(), $data['style_handles'] );
		$this->assertFalse( $data['is_dynamic'] );
		// Deprecated properties.
		$this->assertNull( $data['editor_script'] );
		$this->assertNull( $data['script'] );
		$this->assertNull( $data['view_script'] );
		$this->assertNull( $data['editor_style'] );
		$this->assertNull( $data['style'] );
	}

	/**
	 * @ticket 47620
	 * @ticket 57585
	 * @ticket 59346
	 * @ticket 59797
	 */
	public function test_get_item_defaults() {
		$block_type = 'fake/false';
		$settings   = array(
			'title'            => false,
			'category'         => false,
			'parent'           => false,
			'ancestor'         => false,
			'allowed_blocks'   => false,
			'icon'             => false,
			'description'      => false,
			'keywords'         => false,
			'textdomain'       => false,
			'attributes'       => false,
			'provides_context' => false,
			'uses_context'     => false,
			'selectors'        => false,
			'supports'         => false,
			'styles'           => false,
			'example'          => false,
			'variations'       => false,
			'block_hooks'      => false,
			'editor_script'    => false,
			'script'           => false,
			'view_script'      => false,
			'editor_style'     => false,
			'style'            => false,
			'render_callback'  => false,
		);
		register_block_type( $block_type, $settings );
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/' . $block_type );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $block_type, $data['name'] );
		$this->assertSame( '', $data['title'] );
		$this->assertNull( $data['category'] );
		$this->assertSameSets( array(), $data['parent'] );
		$this->assertSameSets( array(), $data['ancestor'] );
		$this->assertSameSets( array(), $data['allowed_blocks'] );
		$this->assertNull( $data['icon'] );
		$this->assertSame( '', $data['description'] );
		$this->assertSameSets( array(), $data['keywords'] );
		$this->assertNull( $data['textdomain'] );
		$this->assertSameSetsWithIndex(
			array(
				'lock'     => array( 'type' => 'object' ),
				'metadata' => array( 'type' => 'object' ),
			),
			$data['attributes']
		);
		$this->assertSameSets( array(), $data['provides_context'] );
		$this->assertSameSets( array(), $data['uses_context'] );
		$this->assertSameSets( array(), $data['selectors'], 'selectors defaults to empty array' );
		$this->assertSameSets( array(), $data['supports'] );
		$this->assertSameSets( array(), $data['styles'] );
		$this->assertNull( $data['example'] );
		$this->assertSameSets( array(), $data['variations'] );
		$this->assertSameSets( array(), $data['block_hooks'], 'block_hooks defaults to empty array' );
		$this->assertSameSets( array(), $data['editor_script_handles'] );
		$this->assertSameSets( array(), $data['script_handles'] );
		$this->assertSameSets( array(), $data['view_script_handles'] );
		$this->assertSameSets( array(), $data['view_script_module_ids'] );
		$this->assertSameSets( array(), $data['editor_style_handles'] );
		$this->assertSameSets( array(), $data['style_handles'] );
		$this->assertFalse( $data['is_dynamic'] );
		// Deprecated properties.
		$this->assertNull( $data['editor_script'] );
		$this->assertNull( $data['script'] );
		$this->assertNull( $data['view_script'] );
		$this->assertNull( $data['editor_style'] );
		$this->assertNull( $data['style'] );
	}

	/**
	 * @ticket 56733
	 */
	public function test_get_item_deprecated() {
		$block_type = 'fake/deprecated';
		$settings   = array(
			'editor_script' => 'hello_world',
			'script'        => 'gutenberg',
			'view_script'   => 'foo_bar',
			'editor_style'  => 'guten_tag',
			'style'         => 'out_of_style',
		);
		register_block_type( $block_type, $settings );
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/' . $block_type );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameSets(
			array( 'hello_world' ),
			$data['editor_script_handles'],
			"Endpoint doesn't return correct array for editor_script_handles."
		);
		$this->assertSameSets(
			array( 'gutenberg' ),
			$data['script_handles'],
			"Endpoint doesn't return correct array for script_handles."
		);
		$this->assertSameSets(
			array( 'foo_bar' ),
			$data['view_script_handles'],
			"Endpoint doesn't return correct array for view_script_handles."
		);
		$this->assertSameSets(
			array( 'guten_tag' ),
			$data['editor_style_handles'],
			"Endpoint doesn't return correct array for editor_style_handles."
		);
		$this->assertSameSets(
			array( 'out_of_style' ),
			$data['style_handles'],
			"Endpoint doesn't return correct array for style_handles."
		);
		// Deprecated properties.
		$this->assertSame(
			'hello_world',
			$data['editor_script'],
			"Endpoint doesn't return correct string for editor_script."
		);
		$this->assertSame(
			'gutenberg',
			$data['script'],
			"Endpoint doesn't return correct string for script."
		);
		$this->assertSame(
			'foo_bar',
			$data['view_script'],
			"Endpoint doesn't return correct string for view_script."
		);
		$this->assertSame(
			'guten_tag',
			$data['editor_style'],
			"Endpoint doesn't return correct string for editor_style."
		);
		$this->assertSame(
			'out_of_style',
			$data['style'],
			"Endpoint doesn't return correct string for style."
		);
	}

	/**
	 * @ticket 56733
	 */
	public function test_get_item_deprecated_with_arrays() {
		$block_type = 'fake/deprecated-with-arrays';
		$settings   = array(
			'editor_script' => array( 'hello', 'world' ),
			'script'        => array( 'gutenberg' ),
			'view_script'   => array( 'foo', 'bar' ),
			'editor_style'  => array( 'guten', 'tag' ),
			'style'         => array( 'out', 'of', 'style' ),
		);
		register_block_type( $block_type, $settings );
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/' . $block_type );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameSets(
			$settings['editor_script'],
			$data['editor_script_handles'],
			"Endpoint doesn't return correct array for editor_script_handles."
		);
		$this->assertSameSets(
			$settings['script'],
			$data['script_handles'],
			"Endpoint doesn't return correct array for script_handles."
		);
		$this->assertSameSets(
			$settings['view_script'],
			$data['view_script_handles'],
			"Endpoint doesn't return correct array for view_script_handles."
		);
		$this->assertSameSets(
			$settings['editor_style'],
			$data['editor_style_handles'],
			"Endpoint doesn't return correct array for editor_style_handles."
		);
		$this->assertSameSets(
			$settings['style'],
			$data['style_handles'],
			"Endpoint doesn't return correct array for style_handles."
		);
		// Deprecated properties.
		// Since the schema only allows strings or null (but no arrays), we return the first array item.
		// Deprecated properties.
		$this->assertSame(
			'hello',
			$data['editor_script'],
			"Endpoint doesn't return first array element for editor_script."
		);
		$this->assertSame(
			'gutenberg',
			$data['script'],
			"Endpoint doesn't return first array element for script."
		);
		$this->assertSame(
			'foo',
			$data['view_script'],
			"Endpoint doesn't return first array element for view_script."
		);
		$this->assertSame(
			'guten',
			$data['editor_style'],
			"Endpoint doesn't return first array element for editor_style."
		);
		$this->assertSame(
			'out',
			$data['style'],
			"Endpoint doesn't return first array element for style."
		);
	}

	public function test_get_variation() {
		$block_type = 'fake/variations';
		$settings   = array(
			'title'       => 'variations block test',
			'description' => 'a variations block test',
			'attributes'  => array( 'kind' => array( 'type' => 'string' ) ),
			'variations'  => array(
				array(
					'name'        => 'variation',
					'title'       => 'variation title',
					'description' => 'variation description',
					'category'    => 'media',
					'icon'        => 'checkmark',
					'attributes'  => array( 'kind' => 'foo' ),
					'isDefault'   => true,
					'example'     => array( 'attributes' => array( 'kind' => 'example' ) ),
					'scope'       => array( 'inserter', 'block' ),
					'keywords'    => array( 'dogs', 'cats', 'mice' ),
					'innerBlocks' => array(
						array(
							'name'       => 'fake/bar',
							'attributes' => array( 'label' => 'hi' ),
						),
					),
				),
			),
		);
		register_block_type( $block_type, $settings );
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/' . $block_type );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $block_type, $data['name'] );
		$this->assertArrayHasKey( 'variations', $data );
		$this->assertCount( 1, $data['variations'] );
		$variation = $data['variations'][0];
		$this->assertSame( 'variation title', $variation['title'] );
		$this->assertSame( 'variation description', $variation['description'] );
		$this->assertSame( 'media', $variation['category'] );
		$this->assertSame( 'checkmark', $variation['icon'] );
		$this->assertSameSets( array( 'inserter', 'block' ), $variation['scope'] );
		$this->assertSameSets( array( 'dogs', 'cats', 'mice' ), $variation['keywords'] );
		$this->assertSameSets( array( 'attributes' => array( 'kind' => 'example' ) ), $variation['example'] );
		$this->assertSameSets(
			array(
				array(
					'name'       => 'fake/bar',
					'attributes' => array( 'label' => 'hi' ),
				),
			),
			$variation['innerBlocks']
		);
		$this->assertSameSets(
			array( 'kind' => 'foo' ),
			$variation['attributes']
		);
	}

	/**
	 * @ticket 47620
	 * @ticket 57585
	 * @ticket 59346
	 * @ticket 60403
	 */
	public function test_get_item_schema() {
		wp_set_current_user( self::$admin_id );
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/block-types' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 33, $properties );
		$this->assertArrayHasKey( 'api_version', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'category', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'ancestor', $properties );
		$this->assertArrayHasKey( 'allowed_blocks', $properties );
		$this->assertArrayHasKey( 'icon', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'keywords', $properties );
		$this->assertArrayHasKey( 'textdomain', $properties );
		$this->assertArrayHasKey( 'attributes', $properties );
		$this->assertArrayHasKey( 'provides_context', $properties );
		$this->assertArrayHasKey( 'uses_context', $properties );
		$this->assertArrayHasKey( 'selectors', $properties, 'schema must contain selectors' );
		$this->assertArrayHasKey( 'supports', $properties );
		$this->assertArrayHasKey( 'styles', $properties );
		$this->assertArrayHasKey( 'example', $properties );
		$this->assertArrayHasKey( 'variations', $properties );
		$this->assertArrayHasKey( 'block_hooks', $properties );
		$this->assertArrayHasKey( 'editor_script_handles', $properties );
		$this->assertArrayHasKey( 'script_handles', $properties );
		$this->assertArrayHasKey( 'view_script_handles', $properties );
		$this->assertArrayHasKey( 'view_script_module_ids', $properties );
		$this->assertArrayHasKey( 'editor_style_handles', $properties );
		$this->assertArrayHasKey( 'style_handles', $properties );
		$this->assertArrayHasKey( 'view_style_handles', $properties, 'schema must contain view_style_handles' );
		$this->assertArrayHasKey( 'is_dynamic', $properties );
		// Deprecated properties.
		$this->assertArrayHasKey( 'editor_script', $properties );
		$this->assertArrayHasKey( 'script', $properties );
		$this->assertArrayHasKey( 'view_script', $properties );
		$this->assertArrayHasKey( 'editor_style', $properties );
		$this->assertArrayHasKey( 'style', $properties );
	}

	/**
	 * @ticket 47620
	 */
	public function test_get_items_wrong_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_block_type_cannot_view', $response, 403 );
	}

	/**
	 * @ticket 47620
	 */
	public function test_get_item_wrong_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/fake/test' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_block_type_cannot_view', $response, 403 );
	}

	/**
	 * @ticket 47620
	 */
	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_block_type_cannot_view', $response, 401 );
	}

	/**
	 * @ticket 47620
	 */
	public function test_get_item_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/fake/test' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_block_type_cannot_view', $response, 401 );
	}

	/**
	 * @ticket 47620
	 */
	public function test_prepare_item() {
		$registry = new WP_Block_Type_Registry();
		$settings = array(
			'icon'            => 'text',
			'render_callback' => '__return_null',
		);
		$registry->register( 'fake/line', $settings );
		$block_type = $registry->get_registered( 'fake/line' );
		$endpoint   = new WP_REST_Block_Types_Controller();
		$request    = new WP_REST_Request();
		$request->set_param( 'context', 'edit' );
		$response = $endpoint->prepare_item_for_response( $block_type, $request );
		$this->check_block_type_object( $block_type, $response->get_data(), $response->get_links() );
	}

	/**
	 * @ticket 47620
	 */
	public function test_prepare_item_limit_fields() {
		$registry = new WP_Block_Type_Registry();
		$settings = array(
			'icon'            => 'text',
			'render_callback' => '__return_null',
		);
		$registry->register( 'fake/line', $settings );
		$block_type = $registry->get_registered( 'fake/line' );
		$request    = new WP_REST_Request();
		$endpoint   = new WP_REST_Block_Types_Controller();
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'name' );
		$response = $endpoint->prepare_item_for_response( $block_type, $request );
		$this->assertSame(
			array(
				'name',
			),
			array_keys( $response->get_data() )
		);
	}

	/**
	 * Util check block type object against.
	 *
	 * @since 5.5.0
	 * @since 6.4.0 Added the `block_hooks` extra field.
	 *
	 * @param WP_Block_Type $block_type Sample block type.
	 * @param array         $data Data to compare against.
	 * @param array         $links Links to compare again.
	 */
	protected function check_block_type_object( $block_type, $data, $links ) {
		// Test data.
		$this->assertSame( $data['attributes'], $block_type->get_attributes() );
		$this->assertSame( $data['is_dynamic'], $block_type->is_dynamic() );

		$extra_fields = array(
			'api_version',
			'name',
			'title',
			'category',
			'parent',
			'ancestor',
			'allowedBlocks',
			'icon',
			'description',
			'keywords',
			'textdomain',
			'provides_context',
			'uses_context',
			'selectors',
			'supports',
			'styles',
			'example',
			'variations',
			'block_hooks',
			'editor_script_handles',
			'script_handles',
			'view_script_handles',
			'view_script_module_ids',
			'editor_style_handles',
			'style_handles',
			// Deprecated fields.
			'editor_script',
			'script',
			'view_script',
			'editor_style',
			'style',
		);

		foreach ( $extra_fields as $extra_field ) {
			if ( isset( $block_type->$extra_field ) ) {
				$this->assertSame( $data[ $extra_field ], $block_type->$extra_field );
			}
		}

		// Test links.
		$this->assertSame( rest_url( 'wp/v2/block-types' ), $links['collection'][0]['href'] );
		$this->assertSame( rest_url( 'wp/v2/block-types/' . $block_type->name ), $links['self'][0]['href'] );
		if ( $block_type->is_dynamic() ) {
			$this->assertArrayHasKey( 'https://api.w.org/render-block', $links );
		}
	}

	/**
	 * @ticket 59969
	 */
	public function test_variation_callback() {
		$block_type = 'test/block';
		$settings   = array(
			'title'              => true,
			'variation_callback' => array( $this, 'mock_variation_callback' ),
		);
		register_block_type( $block_type, $settings );
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/block-types/' . $block_type );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameSets( $this->mock_variation_callback(), $data['variations'] );
	}

	/**
	 * Mock variation callback.
	 *
	 * @return array
	 */
	public function mock_variation_callback() {
		return array(
			array( 'name' => 'var1' ),
			array( 'name' => 'var2' ),
		);
	}

	/**
	 * The create_item() method does not exist for block types.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_create_item() {
		// Controller does not implement create_item().
	}

	/**
	 * The update_item() method does not exist for block types.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_update_item() {
		// Controller does not implement create_item().
	}

	/**
	 * The delete_item() method does not exist for block types.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Controller does not implement delete_item().
	}
}
