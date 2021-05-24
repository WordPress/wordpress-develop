<?php
/**
 * Unit tests covering the templates endpoint..
 *
 * @package WordPress
 * @subpackage REST API
 */

class WP_REST_Template_Controller_Test extends WP_Test_REST_Controller_Testcase {
	/**
	 * @var int
	 */
	protected static $admin_id;
	private static $post;

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetupBeforeClass( $factory ) {
		self::$admin_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		// Set up template post.
		$args       = array(
			'post_type'    => 'wp_template',
			'post_name'    => 'my_template',
			'post_title'   => 'My Template',
			'post_content' => 'Content',
			'post_excerpt' => 'Description of my template.',
			'tax_input'    => array(
				'wp_theme' => array(
					get_stylesheet(),
				),
			),
		);
		self::$post = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( self::$post->ID, get_stylesheet(), 'wp_theme' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post->ID );
	}


	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/templates', $routes );
		$this->assertArrayHasKey( '/wp/v2/templates/(?P<id>[\/\w-]+)', $routes );
	}

	public function test_context_param() {
		// TODO: Implement test_context_param() method.
	}

	public function test_get_items() {
		function find_and_normalize_template_by_id( $templates, $id ) {
			foreach ( $templates as $template ) {
				if ( $template['id'] === $id ) {
					unset( $template['content'] );
					unset( $template['_links'] );
					return $template;
				}
			}

			return null;
		}

		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_templates', $response, 401 );

		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals(
			array(
				'id'             => 'default//my_template',
				'theme'          => 'default',
				'slug'           => 'my_template',
				'title'          => array(
					'raw'      => 'My Template',
					'rendered' => 'My Template',
				),
				'description'    => 'Description of my template.',
				'status'         => 'publish',
				'source'         => 'custom',
				'type'           => 'wp_template',
				'wp_id'          => self::$post->ID,
				'has_theme_file' => false,
			),
			find_and_normalize_template_by_id( $data, 'default//my_template' )
		);
	}

	public function test_get_item() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/default//my_template' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		unset( $data['content'] );
		unset( $data['_links'] );

		$this->assertEquals(
			array(
				'id'             => 'default//my_template',
				'theme'          => 'default',
				'slug'           => 'my_template',
				'title'          => array(
					'raw'      => 'My Template',
					'rendered' => 'My Template',
				),
				'description'    => 'Description of my template.',
				'status'         => 'publish',
				'source'         => 'custom',
				'type'           => 'wp_template',
				'wp_id'          => self::$post->ID,
				'has_theme_file' => false,
			),
			$data
		);
	}

	public function test_create_item() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/templates' );
		$request->set_body_params(
			array(
				'slug'        => 'my_custom_template',
				'title'       => 'My Template',
				'description' => 'Just a description',
				'content'     => 'Content',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		unset( $data['_links'] );
		unset( $data['wp_id'] );

		$this->assertEquals(
			array(
				'id'             => 'default//my_custom_template',
				'theme'          => 'default',
				'slug'           => 'my_custom_template',
				'title'          => array(
					'raw'      => 'My Template',
					'rendered' => 'My Template',
				),
				'description'    => 'Just a description',
				'status'         => 'publish',
				'source'         => 'custom',
				'type'           => 'wp_template',
				'content'        => array(
					'raw' => 'Content',
				),
				'has_theme_file' => false,
			),
			$data
		);
	}

	public function test_update_item() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/templates/default//my_template' );
		$request->set_body_params(
			array(
				'title' => 'My new Index Title',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'My new Index Title', $data['title']['raw'] );
		$this->assertEquals( 'custom', $data['source'] );
	}

	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/templates/justrandom//template' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_template_not_found', $response, 404 );
	}

	public function test_prepare_item() {
		// TODO: Implement test_prepare_item() method.
	}

	public function test_get_item_schema() {
		// TODO: Implement test_get_item_schema() method.
	}
}
