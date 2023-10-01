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
				'post_content' => 'Autosave content.',
				'post_ID'      => self::$template_post->ID,
				'post_type'    => self::PARENT_POST_TYPE,
			)
		);
	}

	/**
	 * @covers WP_REST_Template_Autosaves_Controller::register_routes
	 * @ticket 56922
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey(
			'/wp/v2/templates/(?P<id>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)/autosaves',
			$routes,
			'Autosaves route does not exist.'
		);
		$this->assertArrayHasKey(
			'/wp/v2/templates/(?P<parent>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)/autosaves/(?P<id>[\d]+)',
			$routes,
			'Single autosave based on the given ID route does not exist.'
		);
	}

	/**
	 * @covers WP_REST_Template_Autosaves_Controller::get_context_param
	 * @ticket 56922
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/autosaves' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Collection.
		$this->assertCount(
			2,
			$data['endpoints'],
			'Failed to assert that the collection autosave endpoints count is 2.'
		);
		$this->assertSame(
			'view',
			$data['endpoints'][0]['args']['context']['default'],
			'Failed to assert that the default context for the GET collection endpoint is "view".'
		);
		$this->assertSame(
			array( 'view', 'embed', 'edit' ),
			$data['endpoints'][0]['args']['context']['enum'],
			"Failed to assert that the enum values for the GET collection endpoint are 'view', 'embed', and 'edit'."
		);

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/autosaves/1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount(
			1,
			$data['endpoints'],
			'Failed to assert that the single autosave endpoints count is 1.'
		);
		$this->assertSame(
			'view',
			$data['endpoints'][0]['args']['context']['default'],
			'Failed to assert that the default context for the single revision endpoint is "view".'
		);
		$this->assertSame(
			array( 'view', 'embed', 'edit' ),
			$data['endpoints'][0]['args']['context']['enum'],
			"Failed to assert that the enum values for the single revision endpoint are 'view', 'embed', and 'edit'."
		);
	}

	/**
	 * @covers WP_REST_Template_Autosaves_Controller::get_items
	 * @ticket 56922
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
			1,
			$autosaves,
			'Failed asserting that the response data contains exactly 1 item.'
		);

		$this->assertSame(
			self::$autosave_post_id,
			$autosaves[0]['wp_id'],
			'Failed asserting that the ID of the autosave matches the expected autosave post ID.'
		);
		$this->assertSame(
			self::$template_post->ID,
			$autosaves[0]['parent'],
			'Failed asserting that the parent ID of the autosave matches the template post ID.'
		);
		$this->assertSame(
			'Autosave content.',
			$autosaves[0]['content']['raw'],
			'Failed asserting that the content of the autosave is "Autosave content.".'
		);
	}

	/**
	 * @covers WP_REST_Template_Autosaves_Controller::get_item
	 * @ticket 56922
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/autosaves/' . self::$template_post->ID );
		$response = rest_get_server()->dispatch( $request );
		$revision = $response->get_data();

		$this->assertIsArray( $revision, 'Failed asserting that the revision is an array.' );
		$this->assertSame(
			self::$autosave_post_id,
			$revision['wp_id'],
			sprintf(
				'Failed asserting that the autosave id is the same as %s.',
				self::$autosave_post_id
			)
		);
		$this->assertSame(
			self::$template_post->ID,
			$revision['parent'],
			sprintf(
				'Failed asserting that the parent id of the autosave is the same as %s.',
				self::$template_post->ID
			)
		);
	}

	/**
	 * @covers WP_REST_Template_Autosaves_Controller::prepare_item_for_response
	 * @ticket 56922
	 */
	public function test_prepare_item() {
		$autosave_db_post = wp_get_post_autosave( self::$template_post->ID );
		$request          = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/autosaves/' . $autosave_db_post->ID );
		$controller       = new WP_REST_Template_Autosaves_Controller( self::PARENT_POST_TYPE );
		$response         = $controller->prepare_item_for_response( $autosave_db_post, $request );
		$this->assertInstanceOf(
			WP_REST_Response::class,
			$response,
			'Failed asserting that the response object is an instance of WP_REST_Response.'
		);

		$autosave = $response->get_data();
		$this->assertIsArray( $autosave, 'Failed asserting that the autosave is an array.' );
		$this->assertSame(
			$autosave_db_post->ID,
			$autosave['wp_id'],
			sprintf(
				'Failed asserting that the autosave id is the same as %s.',
				$autosave_db_post->ID
			)
		);
		$this->assertSame(
			self::$template_post->ID,
			$autosave['parent'],
			sprintf(
				'Failed asserting that the parent id of the autosave is the same as %s.',
				self::$template_post->ID
			)
		);

		$links = $response->get_links();
		$this->assertIsArray( $links, 'Failed asserting that the links are an array.' );

		$this->assertStringEndsWith(
			self::TEST_THEME . '//' . self::TEMPLATE_NAME . '/autosaves/' . $autosave_db_post->ID,
			$links['self'][0]['href'],
			sprintf(
				'Failed asserting that the self link ends with %s.',
				self::TEST_THEME . '//' . self::TEMPLATE_NAME . '/autosaves/' . $autosave_db_post->ID
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
	 * @ticket 56922
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
	 * @ticket 56922
	 */
	public function test_create_item() {
	}

	/**
	 * @coversNothing
	 * @ticket 56922
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
	 * @coversNothing
	 * @ticket 56922
	 */
	public function test_delete_item() {
		$this->markTestSkipped(
			sprintf(
				"The '%s' controller doesn't currently support the ability to delete template autosaves.",
				WP_REST_Template_Autosaves_Controller::class
			)
		);
	}
}
