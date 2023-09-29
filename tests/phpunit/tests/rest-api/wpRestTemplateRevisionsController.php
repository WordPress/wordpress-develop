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

	const TEST_THEME    = 'block-theme';
	const TEMPLATE_NAME = 'my_template';

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
	 * @var WP_post
	 */
	private static $template_post;
	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {

		self::$admin_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( self::$admin_id );
		// Set up template post.
		self::$template_post = $factory->post->create_and_get(
			array(
				'post_type'    => 'wp_template',
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
		$updated_template_post = array(
			'ID'           => self::$template_post->ID,
			'post_content' => 'Content revision #2',
		);

		wp_update_post( $updated_template_post, true, false );

		// Update post to create a new revisions.
		$updated_template_post = array(
			'ID'           => self::$template_post->ID,
			'post_content' => 'Content revision #3',
		);

		wp_update_post( $updated_template_post, true, false );

		// Update post to create a new revisions.
		$updated_template_post = array(
			'ID'           => self::$template_post->ID,
			'post_content' => 'Content revision #4',
		);

		wp_update_post( $updated_template_post, true, false );

		// Update post to create a new revisions.
		$updated_template_post = array(
			'ID'           => self::$template_post->ID,
			'post_content' => 'Content revision #5',
		);

		wp_update_post( $updated_template_post, true, false );
	}

	public function set_up() {
		parent::set_up();
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
			'Revisions route does not exist'
		);
		$this->assertArrayHasKey(
			'/wp/v2/templates/(?P<parent>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)/revisions/(?P<id>[\d]+)',
			$routes,
			'Single revision based on the given ID route does not exist'
		);
	}

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
	 * @covers WP_REST_Templates_Controller::get_items
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


	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		// Choosing random revision for the test.
		$revision_id = array_rand( wp_get_post_revisions( self::$template_post ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/' . self::TEST_THEME . '/' . self::TEMPLATE_NAME . '/revisions/' . $revision_id );
		$response = rest_get_server()->dispatch( $request );
		$revision = $response->get_data();

		$this->assertIsArray( $revision, 'Failed asserting that the revision is an array.' );
		$this->assertSame(
			$revision_id,
			$revision['id'],
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

	public function test_prepare_item() {
	}

	public function test_get_item_schema() {
	}

	public function test_create_item() {
	}

	public function test_update_item() {
	}

	public function test_delete_item() {
	}
}
