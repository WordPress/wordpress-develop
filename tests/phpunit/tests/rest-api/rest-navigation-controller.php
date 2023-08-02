<?php
/**
 * Unit tests covering WP_REST_Navigation_Controller functionality.
 *
 * Note: WP_REST_Navigation_Controller extends WP_REST_Posts_Controller.
 * These tests are designed to provide coverage for unique overrides.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @covers WP_REST_Navigation_Controller
 */

/**
 * @group restapi
 * @group navigation
 */
class WP_REST_Navigation_Controller_Test extends WP_Test_REST_Controller_Testcase {
	protected static $post_id;
	protected static $superadmin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();

		self::$superadmin_id = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_type'  => 'wp_navigation',
				'post_title' => WP_TESTS_DOMAIN . ' Privacy Policy',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );

		self::delete_user( self::$superadmin_id );
	}
	/**
	 * Tests that the correct fields are returned by the context parameter.
	 *
	 * By default, the REST response for the Posts Controller will not return all fields
	 * when the context is set to 'embed'. Assert that correct additional fields are added
	 * to the embedded Navigation Post, when the navigation fallback endpoint
	 * is called with the `_embed` param.
	 *
	 * @ticket 58557
	 *
	 * @covers WP_REST_Navigation_Controller::get_item
	 *
	 * @since 6.3.0
	 */
	public function test_get_item() {
		// Fetch the "linked" navigation post from the endpoint, with the context parameter set to 'embed' to simulate fetching embedded links.
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/navigation/%d', self::$post_id ) );
		$request->set_param( 'context', 'embed' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Verify that the additional status field is present.
		$this->assertArrayHasKey( 'status', $data, 'Response title should contain a "status" field.' );

		// Verify that the additional content fields are present.
		$this->assertArrayHasKey( 'content', $data, 'Response should contain a "content" field.' );
		$this->assertArrayHasKey( 'raw', $data['content'], 'Response content should contain a "raw" field.' );
		$this->assertArrayHasKey( 'rendered', $data['content'], 'Response content should contain a "rendered" field.' );
		$this->assertArrayHasKey( 'block_version', $data['content'], 'Response should contain a "block_version" field.' );

		// Verify that the additional title.raw field is present.
		$this->assertArrayHasKey( 'raw', $data['title'], 'Response title should contain a "raw" key.' );
	}

	/**
	 * @ticket 58557
	 *
	 * @covers WP_REST_Navigation_Controller::get_item_schema
	 *
	 * @since 6.3.0
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/navigation' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 14, $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'modified_gmt', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'template', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'type', $properties );

		// Verifies that the additional context items are present.
		$this->assertNotContains( 'embed', $properties['id']['context'], 'id context should not contain `embed` item.' );
		$this->assertContains( 'embed', $properties['status']['context'], 'status context should contain `embed` item.' );
		$this->assertContains( 'embed', $properties['content']['context'], 'content context should contain `embed` item.' );
		$this->assertContains( 'embed', $properties['content']['properties']['raw']['context'], 'content properties raw context should contain `embed` item.' );
		$this->assertContains( 'embed', $properties['content']['properties']['rendered']['context'], 'content properties rendered context should contain `embed` item.' );
		$this->assertContains( 'embed', $properties['content']['properties']['block_version']['context'], 'content properties block_version context should contain `embed` item.' );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_register_routes() {
		// Covered by the WP_REST_Posts_Controller tests.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_prepare_item() {
		// Covered by the WP_REST_Posts_Controller tests.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_context_param() {
		// Covered by the WP_REST_Posts_Controller tests.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_get_items() {
		// Covered by the WP_REST_Posts_Controller tests.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_create_item() {
		// Covered by the WP_REST_Posts_Controller tests.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_update_item() {
		// Covered by the WP_REST_Posts_Controller tests.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Covered by the WP_REST_Posts_Controller tests.
	}
}
