<?php
/**
 * Unit tests covering WP_REST_Template_Autosaves_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class Tests_REST_wpRestTemplateAutosavesController extends WP_Test_REST_Controller_Testcase {

	const TEST_THEME    = 'block-theme';
	const TEMPLATE_NAME = 'my_template';

	const PARENT_POST_TYPE = 'wp_template';

	/**
	 * Admin user ID.
	 *
	 * @since 6.4.0
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Template post.
	 *
	 * @since 6.4.0
	 *
	 * @var WP_Post
	 */
	private static $template_post;

	/**
	 * Autosave post.
	 *
	 * @since 6.4.0
	 *
	 * @var WP_post
	 */
	private static $autosave_post_id;

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {

		self::$admin_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( self::$admin_id );

		// Set up template post.
		self::$template_post = $factory->post->create_and_get(
			array(
				'post_type'    => self::PARENT_POST_TYPE,
				'post_name'    => self::TEMPLATE_NAME,
				'post_title'   => 'My Template',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of my template',
				'tax_input'    => array(
					'wp_theme' => array(
						self::TEST_THEME,
					),
				),
			)
		);
		wp_set_post_terms( self::$template_post->ID, self::TEST_THEME, 'wp_theme' );

		// Create an autosave.
		self::$autosave_post_id = wp_create_post_autosave(
			array(
				'post_content' => 'This content is better.',
				'post_ID'      => self::$template_post->ID,
				'post_type'    => self::PARENT_POST_TYPE,
			)
		);
	}

	public function set_up() {
		parent::set_up();
	}

	/**
	 * @covers WP_REST_Template_Autosaves_Controller::register_routes
	 * @ticket 56922
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey(
			'/wp/v2/templates/(?P<parent>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)/autosaves',
			$routes,
			'Revisions route does not exist.'
		);
		$this->assertArrayHasKey(
			'/wp/v2/templates/(?P<parent>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)/autosaves/(?P<id>[\d]+)',
			$routes,
			'Single revision based on the given ID route does not exist.'
		);
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/autosaves' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame(
			'view',
			$data['endpoints'][0]['args']['context']['default'],
			'Failed to assert that the default context for the collection endpoint is "view".'
		);
		$this->assertSame(
			array( 'view', 'embed', 'edit' ),
			$data['endpoints'][0]['args']['context']['enum'],
			'Failed to assert correct enum values for the collection endpoint.'
		);

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/autosaves/1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount(
			2,
			$data['endpoints'],
			'Failed to assert that the single revision endpoint count is 2.'
		);
		$this->assertSame(
			'view',
			$data['endpoints'][0]['args']['context']['default'],
			'Failed to assert that the default context for the single revision endpoint is "view".'
		);
		$this->assertSame(
			array( 'view', 'embed', 'edit' ),
			$data['endpoints'][0]['args']['context']['enum'],
			'Failed to assert correct enum values for the single revision endpoint.'
		);
	}

	/**
	 * @covers WP_REST_Template_Autosaves_Controller::get_items
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );
		$request   = new WP_REST_Request(
			'GET',
			'/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/autosaves'
		);
		$response  = rest_get_server()->dispatch( $request );
		$autosaves = $response->get_data();

		$this->assertCount(
			4,
			$autosaves,
			'Failed asserting that the response data contains exactly 4 items.'
		);

		$this->assertSame(
			self::$template_post->ID,
			$autosaves[0]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #5',
			$autosaves[0]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #5".'
		);

		$this->assertSame(
			self::$template_post->ID,
			$autosaves[1]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #4',
			$autosaves[1]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #4".'
		);

		$this->assertSame(
			self::$template_post->ID,
			$autosaves[2]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #3',
			$autosaves[2]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #3".'
		);

		$this->assertSame(
			self::$template_post->ID,
			$autosaves[3]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #2',
			$autosaves[3]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #2".'
		);
	}

	/**
	 * @covers WP_REST_Template_Autosaves_Controller::get_item
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$autosaves   = wp_get_post_autosave( self::$template_post );
		$revision_id = array_shift( $autosaves );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/autosaves/' . $revision_id );
		$response = rest_get_server()->dispatch( $request );
		$revision = $response->get_data();

		$this->assertIsArray( $revision, 'Failed asserting that the revision is an array.' );
		$this->assertSame(
			$revision_id,
			$revision['wp_id'],
			sprintf(
				'Failed asserting that the revision id is the same as %s.',
				$revision_id
			)
		);
		$this->assertSame(
			self::$template_post->ID,
			$revision['parent'],
			sprintf(
				'Failed asserting that the parent id of the revision is the same as %s.',
				self::$template_post->ID
			)
		);
	}

	/**
	 * @covers WP_REST_Template_Autosaves_Controller::prepare_item_for_response
	 */
	public function test_prepare_item() {
		$autosaves   = wp_get_post_autosaves( self::$template_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $autosaves );
		$post        = get_post( $revision_id );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/autosaves/' . $revision_id );
		$controller  = new WP_REST_Template_Autosaves_Controller( self::PARENT_POST_TYPE );
		$response    = $controller->prepare_item_for_response( $post, $request );
		$this->assertInstanceOf(
			WP_REST_Response::class,
			$response,
			'Failed asserting that the response object is an instance of WP_REST_Response.'
		);

		$revision = $response->get_data();
		$this->assertIsArray( $revision, 'Failed asserting that the revision is an array.' );
		$this->assertSame(
			$revision_id,
			$revision['wp_id'],
			sprintf(
				'Failed asserting that the revision id is the same as %s.',
				$revision_id
			)
		);
		$this->assertSame(
			self::$template_post->ID,
			$revision['parent'],
			sprintf(
				'Failed asserting that the parent id of the revision is the same as %s.',
				self::$template_post->ID
			)
		);

		$links = $response->get_links();
		$this->assertIsArray( $links, 'Failed asserting that the links are an array.' );

		$this->assertStringEndsWith(
			self::TEST_THEME . '//' . self::TEMPLATE_NAME . '/autosaves/' . $revision_id,
			$links['self'][0]['href'],
			sprintf(
				'Failed asserting that the self link ends with %s.',
				self::TEST_THEME . '//' . self::TEMPLATE_NAME . '/autosaves/' . $revision_id
			)
		);

		$this->assertStringEndsWith(
			self::TEST_THEME . '//' . self::TEMPLATE_NAME,
			$links['parent'][0]['href'],
			sprintf(
				'Failed asserting that the parent link ends with %s.',
				self::TEST_THEME . '//' . self::TEMPLATE_NAME
			)
		);
	}

	/**
	 * @covers WP_REST_Template_Autosaves_Controller::get_item_schema
	 */
	public function test_get_item_schema() {
		$controller  = new WP_REST_Template_Autosaves_Controller( self::PARENT_POST_TYPE );
		$item_schema = $controller->get_item_schema();

		$this->assertIsArray( $item_schema, 'Item schema should be an array.' );

		$this->assertSame( self::PARENT_POST_TYPE, $item_schema['title'], 'Title should be the same as PARENT_POST_TYPE.' );

		$this->assertIsArray( $item_schema['properties'], 'Properties should be an array.' );

		$properties = array(
			'id',
			'slug',
			'theme',
			'type',
			'source',
			'origin',
			'content',
			'title',
			'description',
			'status',
			'wp_id',
			'has_theme_file',
			'author',
			'modified',
			'is_custom',
			'parent',
		);

		foreach ( $properties as $property ) {
			$this->assertArrayHasKey( $property, $item_schema['properties'], "{$property} key should exist in properties." );
		}
	}

	/**
	 * @coversNothing
	 */
	public function test_create_item() {
		$this->markTestSkipped(
			sprintf(
				"The '%s' controller doesn't currently support the ability to create template autosaves.",
				WP_REST_Template_Autosaves_Controller::class
			)
		);
	}

	/**
	 * @coversNothing
	 */
	public function test_update_item() {
		$this->markTestSkipped(
			sprintf(
				"The '%s' controller doesn't currently support the ability to update template autosaves.",
				WP_REST_Template_Autosaves_Controller::class
			)
		);
	}

	/**
	 * @covers WP_REST_Templates_Controller::delete_item
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );

		$autosaves   = wp_get_post_autosave( self::$template_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $autosaves );
		$request     = new WP_REST_Request( 'DELETE', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/autosaves/' . $revision_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Failed asserting that the response status is 200.' );
		$this->assertNull( get_post( $revision_id ), 'Failed asserting that the post with the given revision ID is deleted.' );
	}
}
