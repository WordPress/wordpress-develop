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
	const TEMPLATE_PART_NAME = 'my_template_part';

	/**
	 * @var string
	 */
	const TEMPLATE_PART_NAME_2 = 'my_template_part_2';

	/**
	 * @var string
	 */
	const TEMPLATE_POST_TYPE = 'wp_template';

	/**
	 * @var string
	 */
	const TEMPLATE_PART_POST_TYPE = 'wp_template_part';

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
	 * Template part post.
	 *
	 * @since 6.7.0
	 *
	 * @var WP_Post
	 */
	private static $template_part_post;

	/**
	 * Template part post.
	 *
	 * @since 6.7.0
	 *
	 * @var WP_Post
	 */
	private static $template_part_post_2;

	/**
	 * @var array
	 */
	private static $template_revisions = array();

	/**
	 * @var array
	 */
	private static $template_part_revisions = array();

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
				'post_type'    => self::TEMPLATE_POST_TYPE,
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

		// Update post to create new revisions.
		foreach ( range( 2, 5 ) as $revision_index ) {
			self::$template_revisions[] = _wp_put_post_revision(
				array(
					'ID'           => self::$template_post->ID,
					'post_content' => 'Content revision #' . $revision_index,
				)
			);
		}

		// Create a new template post to test the get_item method.
		self::$template_post_2 = $factory->post->create_and_get(
			array(
				'post_type'    => self::TEMPLATE_POST_TYPE,
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

		// Set up template part post.
		self::$template_part_post = $factory->post->create_and_get(
			array(
				'post_type'    => self::TEMPLATE_PART_POST_TYPE,
				'post_name'    => self::TEMPLATE_PART_NAME,
				'post_title'   => 'My template part',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of my template part',
				'tax_input'    => array(
					'wp_theme'              => array(
						self::TEST_THEME,
					),
					'wp_template_part_area' => array(
						WP_TEMPLATE_PART_AREA_HEADER,
					),
				),
			)
		);
		wp_set_post_terms( self::$template_part_post->ID, self::TEST_THEME, 'wp_theme' );
		wp_set_post_terms( self::$template_part_post->ID, WP_TEMPLATE_PART_AREA_HEADER, 'wp_template_part_area' );

		// Update post to create new revisions.
		foreach ( range( 2, 5 ) as $revision_index ) {
			self::$template_part_revisions[] = _wp_put_post_revision(
				array(
					'ID'           => self::$template_part_post->ID,
					'post_content' => 'Content revision #' . $revision_index,
				)
			);
		}

		// Set up template part post.
		self::$template_part_post_2 = $factory->post->create_and_get(
			array(
				'post_type'    => self::TEMPLATE_PART_POST_TYPE,
				'post_name'    => self::TEMPLATE_PART_NAME_2,
				'post_title'   => 'My template part 2',
				'post_content' => 'Content 2',
				'post_excerpt' => 'Description of my template part 2',
				'tax_input'    => array(
					'wp_theme'              => array(
						self::TEST_THEME,
					),
					'wp_template_part_area' => array(
						WP_TEMPLATE_PART_AREA_HEADER,
					),
				),
			)
		);
		wp_set_post_terms( self::$template_part_post_2->ID, self::TEST_THEME, 'wp_theme' );
		wp_set_post_terms( self::$template_part_post_2->ID, WP_TEMPLATE_PART_AREA_HEADER, 'wp_template_part_area' );
	}

	/**
	 * Remove revisions when tests are complete.
	 */
	public static function wpTearDownAfterClass() {
		// Also deletes revisions.
		foreach ( self::$template_revisions as $template_revision ) {
			wp_delete_post( $template_revision, true );
		}

		foreach ( self::$template_part_revisions as $template_part_revision ) {
			wp_delete_post( $template_part_revision, true );
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
	 * @coversNothing
	 * @ticket 56922
	 */
	public function test_context_param() {
		// A proper data provider cannot be used because this method's signature must match the parent method.
		// Therefore, actual tests are performed in the test_context_param_with_data_provider method.
		$this->assertTrue( true );
	}

	/**
	 * @dataProvider data_context_param_with_data_provider
	 * @covers WP_REST_Template_Revisions_Controller::get_context_param
	 * @ticket 56922
	 *
	 * @param string $rest_base   Base part of the REST API endpoint to test.
	 * @param string $template_id Template ID to use in the test.
	 */
	public function test_context_param_with_data_provider( $rest_base, $template_id ) {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions' );
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
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions/1' );
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
	 * Data provider for test_context_param.
	 *
	 * @return array
	 */
	public function data_context_param_with_data_provider() {
		return array(
			'templates'      => array( 'templates', self::TEST_THEME . '//' . self::TEMPLATE_NAME ),
			'template parts' => array( 'template-parts', self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME ),
		);
	}

	/**
	 * @coversNothing
	 * @ticket 56922
	 */
	public function test_get_items() {
		// A proper data provider cannot be used because this method's signature must match the parent method.
		// Therefore, actual tests are performed in the test_get_items_with_data_provider method.
		$this->assertTrue( true );
	}

	/**
	 * @dataProvider data_get_items_with_data_provider
	 * @covers WP_REST_Template_Revisions_Controller::get_items
	 * @ticket 56922
	 *
	 * @param string $parent_post_property_name A class property name that contains the parent post object.
	 * @param string $rest_base                 Base part of the REST API endpoint to test.
	 * @param string $template_id               Template ID to use in the test.
	 */
	public function test_get_items_with_data_provider( $parent_post_property_name, $rest_base, $template_id ) {
		wp_set_current_user( self::$admin_id );
		$parent_post = self::$$parent_post_property_name;

		$request   = new WP_REST_Request(
			'GET',
			'/wp/v2/' . $rest_base . '/' . $template_id . '/revisions'
		);
		$response  = rest_get_server()->dispatch( $request );
		$revisions = $response->get_data();
		$this->assertSame( WP_Http::OK, $response->get_status(), 'Response is expected to have a status code of 200.' );

		$this->assertCount(
			4,
			$revisions,
			'Failed asserting that the response data contains exactly 4 items.'
		);

		$this->assertSame(
			$parent_post->ID,
			$revisions[0]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #5',
			$revisions[0]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #5".'
		);

		$this->assertSame(
			$parent_post->ID,
			$revisions[1]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #4',
			$revisions[1]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #4".'
		);

		$this->assertSame(
			$parent_post->ID,
			$revisions[2]['parent'],
			'Failed asserting that the parent ID of the revision matches the template post ID.'
		);
		$this->assertSame(
			'Content revision #3',
			$revisions[2]['content']['raw'],
			'Failed asserting that the content of the revision is "Content revision #3".'
		);

		$this->assertSame(
			$parent_post->ID,
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
	 * Data provider for test_get_items_with_data_provider.
	 *
	 * @return array
	 */
	public function data_get_items_with_data_provider() {
		return array(
			'templates'      => array( 'template_post', 'templates', self::TEST_THEME . '//' . self::TEMPLATE_NAME ),
			'template parts' => array( 'template_part_post', 'template-parts', self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME ),
		);
	}
	/**
	 * @ticket 56481
	 */
	public function test_get_items_should_return_no_response_body_for_head_requests() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request(
			'HEAD',
			'/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions'
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Response status is 200.' );
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * @dataProvider data_get_items_endpoint_should_return_unauthorized_https_status_code_for_unauthorized_request
	 * @covers WP_REST_Template_Revisions_Controller::get_items_permissions_check
	 * @ticket 56922
	 * @ticket 56481
	 *
	 * @param string $rest_base   Base part of the REST API endpoint to test.
	 * @param string $template_id Template ID to use in the test.
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_endpoint_should_return_unauthorized_https_status_code_for_unauthorized_request( $rest_base, $template_id, $method ) {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( $method, '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, WP_Http::UNAUTHORIZED );
	}

	/**
	 * Data provider for test_get_items_endpoint_should_return_unauthorized_https_status_code_for_unauthorized_request.
	 *
	 * @return array
	 */
	public function data_get_items_endpoint_should_return_unauthorized_https_status_code_for_unauthorized_request() {
		return array(
			'templates, GET request'       => array( 'templates', self::TEST_THEME . '//' . self::TEMPLATE_NAME, 'GET' ),
			'templates, HEAD request'      => array( 'templates', self::TEST_THEME . '//' . self::TEMPLATE_NAME, 'HEAD' ),
			'template parts, GET request'  => array( 'template-parts', self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME, 'GET' ),
			'template parts, HEAD request' => array( 'template-parts', self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME, 'HEAD' ),
		);
	}

	/**
	 * @dataProvider data_get_items_endpoint_should_return_forbidden_https_status_code_for_users_with_insufficient_permissions
	 * @covers WP_REST_Template_Revisions_Controller::get_items_permissions_check
	 * @ticket 56922
	 * @ticket 56481
	 *
	 * @param string $rest_base   Base part of the REST API endpoint to test.
	 * @param string $template_id Template ID to use in the test.
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_endpoint_should_return_forbidden_https_status_code_for_users_with_insufficient_permissions( $rest_base, string $template_id, $method ) {
		wp_set_current_user( self::$contributor_id );
		$request  = new WP_REST_Request( $method, '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, WP_Http::FORBIDDEN );
	}

	/**
	 * Data provider for test_get_items_endpoint_should_return_unauthorized_https_status_code_for_unauthorized_request.
	 *
	 * @return array
	 */
	public function data_get_items_endpoint_should_return_forbidden_https_status_code_for_users_with_insufficient_permissions() {
		return array(
			'templates, GET request'       => array( 'templates', self::TEST_THEME . '//' . self::TEMPLATE_NAME, 'GET' ),
			'templates, HEAD request'      => array( 'templates', self::TEST_THEME . '//' . self::TEMPLATE_NAME, 'HEAD' ),
			'template parts, GET request'  => array( 'template-parts', self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME, 'GET' ),
			'template parts, HEAD request' => array( 'template-parts', self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME, 'HEAD' ),
		);
	}

	/**
	 * @dataProvider data_get_items_for_templates_based_on_theme_files_should_return_bad_response_status
	 * @ticket 61970
	 *
	 * @param string $rest_base   Base part of the REST API endpoint to test.
	 * @param string $template_id Template ID to use in the test.
	 */
	public function test_get_items_for_templates_based_on_theme_files_should_return_bad_response_status( $rest_base, $template_id ) {
		wp_set_current_user( self::$admin_id );
		switch_theme( 'block-theme' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse(
			'rest_invalid_template',
			$response,
			WP_Http::BAD_REQUEST,
			sprintf( 'Response is expected to have a status code of %d.', WP_Http::BAD_REQUEST )
		);
	}

	/**
	 * Data provider for test_get_items_for_templates_based_on_theme_files_should_return_bad_response_status.
	 *
	 * @return array
	 */
	public function data_get_items_for_templates_based_on_theme_files_should_return_bad_response_status() {
		return array(
			'templates'      => array( 'templates', self::TEST_THEME . '//page-home' ),
			'template parts' => array( 'template-parts', self::TEST_THEME . '//small-header' ),
		);
	}

	/**
	 * @dataProvider data_get_item_for_templates_based_on_theme_files_should_return_bad_response_status
	 * @ticket 56922
	 *
	 * @param string $rest_base   Base part of the REST API endpoint to test.
	 * @param string $template_id Template ID to use in the test.
	 */
	public function test_get_item_for_templates_based_on_theme_files_should_return_bad_response_status( $rest_base, $template_id ) {
		wp_set_current_user( self::$admin_id );
		switch_theme( 'block-theme' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions/1' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse(
			'rest_invalid_template',
			$response,
			WP_Http::BAD_REQUEST,
			sprintf( 'Response is expected to have a status code of %d.', WP_Http::BAD_REQUEST )
		);
	}

	/**
	 * Data provider for test_get_item_for_templates_based_on_theme_files_should_return_bad_response_status.
	 *
	 * @return array
	 */
	public function data_get_item_for_templates_based_on_theme_files_should_return_bad_response_status() {
		return array(
			'templates'      => array( 'templates', self::TEST_THEME . '//page-home' ),
			'template parts' => array( 'template-parts', self::TEST_THEME . '//small-header' ),
		);
	}

	/**
	 * @coversNothing
	 * @ticket 56922
	 */
	public function test_get_item() {
		// A proper data provider cannot be used because this method's signature must match the parent method.
		// Therefore, actual tests are performed in the test_get_item_with_data_provider method.
		$this->assertTrue( true );
	}

	/**
	 * @dataProvider data_get_item_with_data_provider
	 * @covers WP_REST_Template_Revisions_Controller::get_item
	 * @ticket 56922
	 *
	 * @param string  $parent_post_property_name  A class property name that contains the parent post object.
	 * @param string  $rest_base                  Base part of the REST API endpoint to test.
	 * @param string  $template_id                Template ID to use in the test.
	 */
	public function test_get_item_with_data_provider( $parent_post_property_name, $rest_base, $template_id ) {
		wp_set_current_user( self::$admin_id );

		$parent_post = self::$$parent_post_property_name;

		$revisions   = wp_get_post_revisions( $parent_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $revisions );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions/' . $revision_id );
		$response = rest_get_server()->dispatch( $request );
		$revision = $response->get_data();

		$this->assertIsArray( $revision, 'Failed asserting that the revision is an array.' );
		$this->assertSame(
			$revision_id,
			$revision['wp_id'],
			"Failed asserting that the revision id is the same as $revision_id"
		);
		$this->assertSame(
			$parent_post->ID,
			$revision['parent'],
			sprintf(
				'Failed asserting that the parent id of the revision is the same as %s.',
				self::$template_post->ID
			)
		);
	}

	/**
	 * @ticket 56481
	 */
	public function test_get_item_should_return_no_response_body_for_head_requests() {
		wp_set_current_user( self::$admin_id );
		$revisions   = wp_get_post_revisions( self::$template_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $revisions );
		$request     = new WP_REST_Request( 'HEAD', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions/' . $revision_id );
		$response    = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Response status is 200.' );
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * Data provider for test_get_item_with_data_provider.
	 *
	 * @return array
	 */
	public function data_get_item_with_data_provider() {
		return array(
			'templates'      => array( 'template_post', 'templates', self::TEST_THEME . '//' . self::TEMPLATE_NAME ),
			'template parts' => array( 'template_part_post', 'template-parts', self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME ),
		);
	}

	/**
	 * @dataProvider data_get_item_with_data_provider
	 * @covers       WP_REST_Template_Revisions_Controller::get_item
	 * @ticket 56922
	 *
	 * @param string $parent_post_property_name A class property name that contains the parent post object.
	 * @param string $rest_base Base part of the REST API endpoint to test.
	 * @param string $template_id Template ID to use in the test.
	 */
	public function test_get_item_head_request_with_specified_fields_returns_success_response( $parent_post_property_name, $rest_base, $template_id ) {
		wp_set_current_user( self::$admin_id );

		$parent_post = self::$$parent_post_property_name;

		$revisions   = wp_get_post_revisions( $parent_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $revisions );

		$request = new WP_REST_Request(
			'HEAD',
			'/wp/v2/' . $rest_base . '/' . $template_id . '/revisions/' . $revision_id
		);
		$request->set_param( '_fields', 'id' );
		$server   = rest_get_server();
		$response = $server->dispatch( $request );
		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $server, $request );
		remove_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10 );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
	}

	/**
	 * @dataProvider data_get_items_with_data_provider
	 * @covers       WP_REST_Template_Revisions_Controller::get_items
	 * @ticket 56922
	 *
	 * @param string $parent_post_property_name A class property name that contains the parent post object.
	 * @param string $rest_base Base part of the REST API endpoint to test.
	 * @param string $template_id Template ID to use in the test.
	 */
	public function test_get_items_head_request_with_specified_fields_returns_success_response( $parent_post_property_name, $rest_base, $template_id ) {
		wp_set_current_user( self::$admin_id );
		$parent_post = self::$$parent_post_property_name;

		$request = new WP_REST_Request(
			'HEAD',
			'/wp/v2/' . $rest_base . '/' . $template_id . '/revisions'
		);

		$request->set_param( '_fields', 'id' );
		$server   = rest_get_server();
		$response = $server->dispatch( $request );
		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $server, $request );
		remove_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10 );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
	}

	/**
	 * @dataProvider data_get_item_not_found
	 * @covers WP_REST_Template_Revisions_Controller::get_item
	 * @ticket 56922
	 * @ticket 56481
	 *
	 * @param string  $parent_post_property_name  A class property name that contains the parent post object.
	 * @param string  $rest_base                  Base part of the REST API endpoint to test.
	 * @param string  $method                     HTTP method to use.
	 */
	public function test_get_item_not_found( $parent_post_property_name, $rest_base, $method ) {
		wp_set_current_user( self::$admin_id );

		$parent_post = self::$$parent_post_property_name;

		$revisions   = wp_get_post_revisions( $parent_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $revisions );

		$request  = new WP_REST_Request( $method, '/wp/v2/' . $rest_base . '/invalid//parent/revisions/' . $revision_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, WP_Http::NOT_FOUND );
	}

	/**
	 * Data provider for test_get_item_not_found.
	 *
	 * @return array
	 */
	public function data_get_item_not_found() {
		return array(
			'templates, GET request'       => array( 'template_post', 'templates', 'GET' ),
			'templates, HEAD request'      => array( 'template_post', 'templates', 'HEAD' ),
			'template parts, GET request'  => array( 'template_part_post', 'template-parts', 'GET' ),
			'template parts, HEAD request' => array( 'template_part_post', 'template-parts', 'HEAD' ),
		);
	}

	/**
	 * @dataProvider data_get_item_invalid_parent_id
	 * @covers WP_REST_Template_Revisions_Controller::get_item
	 * @ticket 59875
	 * @ticket 56481
	 *
	 * @param string $parent_post_property_name        A class property name that contains the parent post object.
	 * @param string $actual_parent_post_property_name A class property name that contains the parent post object.
	 * @param string $rest_base                        Base part of the REST API endpoint to test.
	 * @param string $template_id                      Template ID to use in the test.
	 * @param string $method HTTP method to use.
	 */
	public function test_get_item_invalid_parent_id( $parent_post_property_name, $actual_parent_post_property_name, $rest_base, $template_id, $method ) {
		wp_set_current_user( self::$admin_id );

		$parent_post        = self::$$parent_post_property_name;
		$actual_parent_post = self::$$actual_parent_post_property_name;
		$revisions          = wp_get_post_revisions( $parent_post, array( 'fields' => 'ids' ) );
		$revision_id        = array_shift( $revisions );

		$request = new WP_REST_Request( $method, '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions/' . $revision_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_revision_parent_id_mismatch', $response, 404 );

		$expected_message = 'The revision does not belong to the specified parent with id of "' . $actual_parent_post->ID . '"';
		$this->assertSame( $expected_message, $response->as_error()->get_error_messages()[0], 'The message must contain the correct parent ID.' );
	}

	/**
	 * Data provider for test_get_item_invalid_parent_id.
	 *
	 * @return array
	 */
	public function data_get_item_invalid_parent_id() {
		return array(
			'templates, GET request'       => array(
				'template_post',
				'template_post_2',
				'templates',
				self::TEST_THEME . '//' . self::TEMPLATE_NAME_2,
				'GET',
			),
			'templates, HEAD request'      => array(
				'template_post',
				'template_post_2',
				'templates',
				self::TEST_THEME . '//' . self::TEMPLATE_NAME_2,
				'HEAD',
			),
			'template parts, GET request'  => array(
				'template_part_post',
				'template_part_post_2',
				'template-parts',
				self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME_2,
				'GET',
			),
			'template parts, HEAD request' => array(
				'template_part_post',
				'template_part_post_2',
				'template-parts',
				self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME_2,
				'HEAD',
			),
		);
	}

	/**
	 * @coversNothing
	 * @ticket 56922
	 */
	public function test_prepare_item() {
		// A proper data provider cannot be used because this method's signature must match the parent method.
		// Therefore, actual tests are performed in the test_prepare_item_with_data_provider method.
		$this->assertTrue( true );
	}

	/**
	 * @dataProvider data_prepare_item_with_data_provider
	 * @covers WP_REST_Template_Revisions_Controller::prepare_item_for_response
	 * @ticket 56922
	 *
	 * @param string $parent_post_property_name A class property name that contains the parent post object.
	 * @param string $rest_base                 Base part of the REST API endpoint to test.
	 * @param string $template_id               Template ID to use in the test.
	 */
	public function test_prepare_item_with_data_provider( $parent_post_property_name, $rest_base, $template_id ) {
		$parent_post = self::$$parent_post_property_name;
		$revisions   = wp_get_post_revisions( $parent_post, array( 'fields' => 'ids' ) );
		$revision_id = array_shift( $revisions );
		$post        = get_post( $revision_id );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions/' . $revision_id );
		$controller  = new WP_REST_Template_Revisions_Controller( $parent_post->post_type );
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
			$parent_post->ID,
			$revision['parent'],
			sprintf(
				'Failed asserting that the parent id of the revision is the same as %s.',
				$parent_post->ID
			)
		);

		$links = $response->get_links();
		$this->assertIsArray( $links, 'Failed asserting that the links are an array.' );

		$this->assertStringEndsWith(
			$template_id . '/revisions/' . $revision_id,
			$links['self'][0]['href'],
			sprintf(
				'Failed asserting that the self link ends with %s.',
				$template_id . '/revisions/' . $revision_id
			)
		);

		$this->assertStringEndsWith(
			$template_id,
			$links['parent'][0]['href'],
			sprintf(
				'Failed asserting that the parent link ends with %s.',
				$template_id
			)
		);
	}

	/**
	 * Data provider for test_prepare_item_with_data_provider.
	 *
	 * @return array
	 */
	public function data_prepare_item_with_data_provider() {
		return array(
			'templates'      => array( 'template_post', 'templates', self::TEST_THEME . '//' . self::TEMPLATE_NAME ),
			'template parts' => array( 'template_part_post', 'template-parts', self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME ),
		);
	}

	/**
	 * @coversNothing
	 * @ticket 56922
	 */
	public function test_get_item_schema() {
		// A proper data provider cannot be used because this method's signature must match the parent method.
		// Therefore, actual tests are performed in the test_prepare_item_with_data_provider method.
		$this->assertTrue( true );
	}

	/**
	 * @dataProvider data_get_item_schema_with_data_provider
	 * @covers WP_REST_Template_Revisions_Controller::get_item_schema
	 * @ticket 56922
	 *
	 * @param string $rest_base             Base part of the REST API endpoint to test.
	 * @param string $template_id           Template ID to use in the test.
	 * @param int    $properties_count      Number of properties to check for in the schema.
	 * @param array  $additional_properties Additional properties to check for in the schema.
	 */
	public function test_get_item_schema_with_data_provider( $rest_base, $template_id, $properties_count, $additional_properties = array() ) {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertCount( $properties_count, $properties );
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
		$this->assertArrayHasKey( 'parent', $properties, 'Parent key should exist in properties.' );
		$this->assertArrayHasKey( 'author_text', $properties, 'author_text key should exist in properties.' );
		$this->assertArrayHasKey( 'original_source', $properties, 'original_source key should exist in properties.' );

		foreach ( $additional_properties as $additional_property ) {
			$this->assertArrayHasKey( $additional_property, $properties, $additional_property . ' key should exist in properties.' );
		}
	}

	/**
	 * Data provider for data_get_item_schema_with_data_provider.
	 *
	 * @return array
	 */
	public function data_get_item_schema_with_data_provider() {
		return array(
			'templates'      => array(
				'templates',
				self::TEST_THEME . '//' . self::TEMPLATE_NAME,
				19,
				array( 'is_custom', 'plugin' ),
			),
			'template parts' => array(
				'template-parts',
				self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME,
				18,
				array( 'area' ),
			),
		);
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
	 * @coversNothing
	 * @ticket 56922
	 */
	public function test_delete_item() {
		// A proper data provider cannot be used because this method's signature must match the parent method.
		// Therefore, actual tests are performed in the test_delete_item_with_data_provider method.
		$this->assertTrue( true );
	}

	/**
	 * @dataProvider data_delete_item_with_data_provider
	 * @covers WP_REST_Templates_Controller::delete_item
	 * @ticket 56922
	 *
	 * @param string $parent_post_property_name A class property name that contains the parent post object.
	 * @param string $revisions_property_name   A class property name that contains the revisions array.
	 * @param string $rest_base                 Base part of the REST API endpoint to test.
	 * @param string $template_id               Template ID to use in the test.
	 */
	public function test_delete_item_with_data_provider( $parent_post_property_name, $revisions_property_name, $rest_base, $template_id ) {
		wp_set_current_user( self::$admin_id );

		$parent_post = self::$$parent_post_property_name;
		$revisions   = self::$$revisions_property_name;

		$revision_id = _wp_put_post_revision( $parent_post );
		$revisions[] = $revision_id;

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions/' . $revision_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Failed asserting that the response status is 200.' );
		$this->assertNull( get_post( $revision_id ), 'Failed asserting that the post with the given revision ID is deleted.' );
	}

	/**
	 * Data provider for test_delete_item_with_data_provider.
	 *
	 * @return array
	 */
	public function data_delete_item_with_data_provider() {
		return array(
			'templates'      => array(
				'template_post',
				'template_revisions',
				'templates',
				self::TEST_THEME . '//' . self::TEMPLATE_NAME,
			),
			'template parts' => array(
				'template_part_post',
				'template_part_revisions',
				'template-parts',
				self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME,
			),
		);
	}

	/**
	 * @dataProvider data_delete_item_incorrect_permission
	 * @covers WP_REST_Templates_Controller::delete_item
	 * @ticket 56922
	 *
	 * @param string $parent_post_property_name A class property name that contains the parent post object.
	 * @param string $revisions_property_name   A class property name that contains the revisions array.
	 * @param string $rest_base                 Base part of the REST API endpoint to test.
	 * @param string $template_id               Template ID to use in the test.
	 */
	public function test_delete_item_incorrect_permission( $parent_post_property_name, $revisions_property_name, $rest_base, $template_id ) {
		wp_set_current_user( self::$contributor_id );
		$parent_post = self::$$parent_post_property_name;
		$revisions   = self::$$revisions_property_name;

		$revision_id = _wp_put_post_revision( $parent_post );
		$revisions[] = $revision_id;

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions/' . $revision_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, WP_Http::FORBIDDEN );
	}

	/**
	 * Data provider for test_delete_item_with_data_provider.
	 *
	 * @return array
	 */
	public function data_delete_item_incorrect_permission() {
		return array(
			'templates'      => array(
				'template_post',
				'template_revisions',
				'templates',
				self::TEST_THEME . '//' . self::TEMPLATE_NAME,
			),
			'template parts' => array(
				'template_part_post',
				'template_part_revisions',
				'template-parts',
				self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME,
			),
		);
	}

	/**
	 * @dataProvider data_delete_item_no_permission
	 * @covers WP_REST_Templates_Controller::delete_item
	 * @ticket 56922
	 *
	 * @param string $parent_post_property_name A class property name that contains the parent post object.
	 * @param string $revisions_property_name   A class property name that contains the revisions array.
	 * @param string $rest_base                 Base part of the REST API endpoint to test.
	 * @param string $template_id               Template ID to use in the test.
	 */
	public function test_delete_item_no_permission( $parent_post_property_name, $revisions_property_name, $rest_base, $template_id ) {
		wp_set_current_user( 0 );

		$parent_post = self::$$parent_post_property_name;
		$revisions   = self::$$revisions_property_name;

		$revision_id = _wp_put_post_revision( $parent_post );
		$revisions[] = $revision_id;

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/' . $rest_base . '/' . $template_id . '/revisions/' . $revision_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, WP_Http::UNAUTHORIZED );
	}

	/**
	 * Data provider for test_delete_item_no_permission.
	 *
	 * @return array
	 */
	public function data_delete_item_no_permission() {
		return array(
			'templates'      => array(
				'template_post',
				'template_revisions',
				'templates',
				self::TEST_THEME . '//' . self::TEMPLATE_NAME,
			),
			'template parts' => array(
				'template_part_post',
				'template_part_revisions',
				'template-parts',
				self::TEST_THEME . '//' . self::TEMPLATE_PART_NAME,
			),
		);
	}

	/**
	 * @dataProvider data_delete_item_not_found
	 * @covers WP_REST_Template_Revisions_Controller::get_item
	 * @ticket 56922
	 *
	 * @param string $parent_post_property_name A class property name that contains the parent post object.
	 * @param string $revisions_property_name   A class property name that contains the revisions array.
	 * @param string $rest_base                 Base part of the REST API endpoint to test.
	 */
	public function test_delete_item_not_found( $parent_post_property_name, $revisions_property_name, $rest_base ) {
		wp_set_current_user( self::$admin_id );

		$parent_post = self::$$parent_post_property_name;
		$revisions   = self::$$revisions_property_name;

		$revision_id = _wp_put_post_revision( $parent_post );
		$revisions[] = $revision_id;

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/' . $rest_base . '/invalid//parent/revisions/' . $revision_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, WP_Http::NOT_FOUND );
	}

	/**
	 * Data provider for test_delete_item_not_found.
	 *
	 * @return array
	 */
	public function data_delete_item_not_found() {
		return array(
			'templates'      => array(
				'template_post',
				'template_revisions',
				'templates',
			),
			'template parts' => array(
				'template_part_post',
				'template_part_revisions',
				'template-parts',
			),
		);
	}

	/**
	 * Tests for the pagination.
	 *
	 * @ticket 62292
	 *
	 * @covers WP_REST_Template_Revisions_Controller::get_items
	 */
	public function test_get_template_revisions_pagination() {
		wp_set_current_user( self::$admin_id );

		// Test offset.
		$request = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions' );
		$request->set_param( 'offset', 1 );
		$request->set_param( 'per_page', 1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 4, $response->get_headers()['X-WP-Total'] );
		$this->assertSame( 4, $response->get_headers()['X-WP-TotalPages'] );

		// Test paged.
		$request = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions' );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 2 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 4, $response->get_headers()['X-WP-Total'] );
		$this->assertSame( 2, $response->get_headers()['X-WP-TotalPages'] );

		// Test out of bounds.
		$request = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions' );
		$request->set_param( 'page', 4 );
		$request->set_param( 'per_page', 6 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_revision_invalid_page_number', $response, 400 );
	}
}
