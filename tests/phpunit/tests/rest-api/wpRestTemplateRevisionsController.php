<?php
/**
 * Unit tests covering WP_REST_Template_Revisions_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class Tests_REST_wpRestTemplateRevisionsController extends WP_Test_REST_Controller_Testcase {

	/**
	 * @var string
	 */
	const TEST_THEME = 'block-theme';

	/**
	 * @var string
	 */
	const TEMPLATE_NAME = 'my_template';

	/**
	 * @var string
	 */
	const TEMPLATE_NAME_2 = 'my_template_2';

	/**
	 * @var string
	 */
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
	 * Contributor user ID.
	 *
	 * @since 6.4.0
	 *
	 * @var int
	 */
	private static $contributor_id;

	/**
	 * Template post.
	 *
	 * @since 6.4.0
	 *
	 * @var WP_Post
	 */
	private static $template_post;

	/**
	 * Template post.
	 *
	 * @since 6.5.0
	 *
	 * @var WP_Post
	 */
	private static $template_post_2;

	/**
	 * @var array
	 */
	private static $revisions = array();

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

		self::$contributor_id = $factory->user->create(
			array(
				'role' => 'contributor',
			)
		);

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

		// Update post to create a new revisions.
		self::$revisions[] = _wp_put_post_revision(
			array(
				'ID'           => self::$template_post->ID,
				'post_content' => 'Content revision #2',
			)
		);

		// Update post to create a new revisions.
		self::$revisions[] = _wp_put_post_revision(
			array(
				'ID'           => self::$template_post->ID,
				'post_content' => 'Content revision #3',
			)
		);

		// Update post to create a new revisions.
		self::$revisions[] = _wp_put_post_revision(
			array(
				'ID'           => self::$template_post->ID,
				'post_content' => 'Content revision #4',
			)
		);

		// Update post to create a new revisions.
		self::$revisions[] = _wp_put_post_revision(
			array(
				'ID'           => self::$template_post->ID,
				'post_content' => 'Content revision #5',
			)
		);

		// Create a new template post to test the get_item method.
		self::$template_post_2 = $factory->post->create_and_get(
			array(
				'post_type'    => self::PARENT_POST_TYPE,
				'post_name'    => self::TEMPLATE_NAME_2,
				'post_title'   => 'My Template 2',
				'post_content' => 'Content 2',
				'post_excerpt' => 'Description of my template 2',
				'tax_input'    => array(
					'wp_theme' => array(
						self::TEST_THEME,
					),
				),
			)
		);
		wp_set_post_terms( self::$template_post_2->ID, self::TEST_THEME, 'wp_theme' );
	}

	/**
	 * Remove revisions when tests are complete.
	 */
	public static function wpTearDownAfterClass() {
		// Also deletes revisions.
		foreach ( self::$revisions as $revision ) {
			wp_delete_post( $revision, true );
		}
	}

	/**
	 * @covers WP_REST_Template_Revisions_Controller::register_routes
	 * @ticket 56922
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey(
			'/wp/v2/templates/(?P<parent>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)/revisions',
			$routes,
			'Template revisions route does not exist.'
		);
		$this->assertArrayHasKey(
			'/wp/v2/templates/(?P<parent>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)/revisions/(?P<id>[\d]+)',
			$routes,
			'Single template revision based on the given ID route does not exist.'
		);
		$this->assertArrayHasKey(
			'/wp/v2/template-parts/(?P<parent>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)/revisions',
			$routes,
			'Template part revisions route does not exist.'
		);
		$this->assertArrayHasKey(
			'/wp/v2/template-parts/(?P<parent>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)/revisions/(?P<id>[\d]+)',
			$routes,
			'Single template part revision based on the given ID route does not exist.'
		);
	}

	/**
	 * @covers WP_REST_Template_Revisions_Controller::get_context_param
	 * @ticket 56922
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions' );
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
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions/1' );
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
	 * @covers WP_REST_Template_Revisions_Controller::get_items
	 * @ticket 56922
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );
		$request   = new WP_REST_Request(
			'GET',
			'/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions'
		);
		$response  = rest_get_server()->dispatch( $request );
		$revisions = $response->get_data();

		$this->assertCount(
			4,
			$revisions,
			'Failed asserting that the response data contains exactly 4 items.'
		);

		$this->assertSame(
			self::$template_post->ID,
			$revisions[0]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #5',
			$revisions[0]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #5".'
		);

		$this->assertSame(
			self::$template_post->ID,
			$revisions[1]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #4',
			$revisions[1]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #4".'
		);

		$this->assertSame(
			self::$template_post->ID,
			$revisions[2]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #3',
			$revisions[2]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #3".'
		);

		$this->assertSame(
			self::$template_post->ID,
			$revisions[3]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #2',
			$revisions[3]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #2".'
		);
	}


	/**
	 * @covers WP_REST_Template_Revisions_Controller::get_items_permissions_check
	 * @ticket 56922
	 */
	public function test_get_items_endpoint_should_return_unauthorized_https_status_code_for_unauthorized_request() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, WP_Http::UNAUTHORIZED );
	}

	/**
	 * @covers WP_REST_Template_Revisions_Controller::get_items_permissions_check
	 * @ticket 56922
	 */
	public function test_get_items_endpoint_should_return_forbidden_https_status_code_for_users_with_insufficient_permissions() {
		wp_set_current_user( self::$contributor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, WP_Http::FORBIDDEN );
	}

	/**
	 * @covers WP_REST_Template_Revisions_Controller::get_item
	 * @ticket 56922
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$revisions   = wp_get_post_revisions( self::$template_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $revisions );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions/' . $revision_id );
		$response = rest_get_server()->dispatch( $request );
		$revision = $response->get_data();

		$this->assertIsArray( $revision, 'Failed asserting that the revision is an array.' );
		$this->assertSame(
			$revision_id,
			$revision['wp_id'],
			"Failed asserting that the revision id is the same as $revision_id"
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
	 * @covers WP_REST_Template_Revisions_Controller::get_item
	 * @ticket 56922
	 */
	public function test_get_item_not_found() {
		wp_set_current_user( self::$admin_id );

		$revisions   = wp_get_post_revisions( self::$template_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $revisions );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/invalid//parent/revisions/' . $revision_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, WP_Http::NOT_FOUND );
	}

	/**
	 * @ticket 59875
	 */
	public function test_get_item_invalid_parent_id() {
		wp_set_current_user( self::$admin_id );
		$revisions   = wp_get_post_revisions( self::$template_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $revisions );

		$request = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME_2 . '/revisions/' . $revision_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_revision_parent_id_mismatch', $response, 404 );

		$expected_message = 'The revision does not belong to the specified parent with id of "' . self::$template_post_2->ID . '"';
		$this->assertSame( $expected_message, $response->as_error()->get_error_messages()[0], 'The message must contain the correct parent ID.' );
	}

	/**
	 * @covers WP_REST_Template_Revisions_Controller::prepare_item_for_response
	 * @ticket 56922
	 */
	public function test_prepare_item() {
		$revisions   = wp_get_post_revisions( self::$template_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $revisions );
		$post        = get_post( $revision_id );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions/' . $revision_id );
		$controller  = new WP_REST_Template_Revisions_Controller( self::PARENT_POST_TYPE );
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
			"Failed asserting that the revision id is the same as $revision_id."
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
			self::TEST_THEME . '//' . self::TEMPLATE_NAME . '/revisions/' . $revision_id,
			$links['self'][0]['href'],
			sprintf(
				'Failed asserting that the self link ends with %s.',
				self::TEST_THEME . '//' . self::TEMPLATE_NAME . '/revisions/' . $revision_id
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
	 * @covers WP_REST_Template_Revisions_Controller::get_item_schema
	 * @ticket 56922
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertCount( 19, $properties );
		$this->assertArrayHasKey( 'id', $properties, 'ID key should exist in properties.' );
		$this->assertArrayHasKey( 'slug', $properties, 'Slug key should exist in properties.' );
		$this->assertArrayHasKey( 'theme', $properties, 'Theme key should exist in properties.' );
		$this->assertArrayHasKey( 'source', $properties, 'Source key should exist in properties.' );
		$this->assertArrayHasKey( 'origin', $properties, 'Origin key should exist in properties.' );
		$this->assertArrayHasKey( 'content', $properties, 'Content key should exist in properties.' );
		$this->assertArrayHasKey( 'title', $properties, 'Title key should exist in properties.' );
		$this->assertArrayHasKey( 'description', $properties, 'description key should exist in properties.' );
		$this->assertArrayHasKey( 'status', $properties, 'status key should exist in properties.' );
		$this->assertArrayHasKey( 'wp_id', $properties, 'wp_id key should exist in properties.' );
		$this->assertArrayHasKey( 'has_theme_file', $properties, 'has_theme_file key should exist in properties.' );
		$this->assertArrayHasKey( 'author', $properties, 'author key should exist in properties.' );
		$this->assertArrayHasKey( 'modified', $properties, 'modified key should exist in properties.' );
		$this->assertArrayHasKey( 'is_custom', $properties, 'is_custom key should exist in properties.' );
		$this->assertArrayHasKey( 'parent', $properties, 'Parent key should exist in properties.' );
		$this->assertArrayHasKey( 'author_text', $properties, 'author_text key should exist in properties.' );
		$this->assertArrayHasKey( 'original_source', $properties, 'original_source key should exist in properties.' );
		$this->assertArrayHasKey( 'plugin', $properties, 'plugin key should exist in properties.' );
	}

	/**
	 * @coversNothing
	 * @ticket 56922
	 */
	public function test_create_item() {
		$this->markTestSkipped(
			sprintf(
				"The '%s' controller doesn't currently support the ability to create template revisions.",
				WP_REST_Template_Revisions_Controller::class
			)
		);
	}

	/**
	 * @coversNothing
	 * @ticket 56922
	 */
	public function test_update_item() {
		$this->markTestSkipped(
			sprintf(
				"The '%s' controller doesn't currently support the ability to update template revisions.",
				WP_REST_Template_Revisions_Controller::class
			)
		);
	}

	/**
	 * @covers WP_REST_Templates_Controller::delete_item
	 * @ticket 56922
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );

		$revision_id       = _wp_put_post_revision( self::$template_post );
		self::$revisions[] = $revision_id;

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions/' . $revision_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Failed asserting that the response status is 200.' );
		$this->assertNull( get_post( $revision_id ), 'Failed asserting that the post with the given revision ID is deleted.' );
	}

	/**
	 * @covers WP_REST_Templates_Controller::delete_item
	 * @ticket 56922
	 */
	public function test_delete_item_incorrect_permission() {
		wp_set_current_user( self::$contributor_id );
		$revision_id       = _wp_put_post_revision( self::$template_post );
		self::$revisions[] = $revision_id;

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions/' . $revision_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, WP_Http::FORBIDDEN );
	}

	/**
	 * @covers WP_REST_Templates_Controller::delete_item
	 * @ticket 56922
	 */
	public function test_delete_item_no_permission() {
		wp_set_current_user( 0 );
		$revision_id       = _wp_put_post_revision( self::$template_post );
		self::$revisions[] = $revision_id;

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions/' . $revision_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, WP_Http::UNAUTHORIZED );
	}

	/**
	 * @covers WP_REST_Template_Revisions_Controller::get_item
	 * @ticket 56922
	 */
	public function test_delete_item_not_found() {
		wp_set_current_user( self::$admin_id );

		$revision_id       = _wp_put_post_revision( self::$template_post );
		self::$revisions[] = $revision_id;

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/templates/invalid//parent/revisions/' . $revision_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, WP_Http::NOT_FOUND );
	}
}
