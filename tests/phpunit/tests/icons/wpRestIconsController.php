<?php
/**
 * Unit tests covering WP_REST_Icons_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.0.0
 *
 * @group restapi
 * @group icons
 *
 * @coversDefaultClass WP_REST_Icons_Controller
 */
class Tests_REST_WpRestIconsController extends WP_Test_REST_Controller_Testcase {
	protected static $admin_id;
	protected static $editor_id;
	protected static $contributor_id;
	protected static $subscriber_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id       = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_id      = $factory->user->create( array( 'role' => 'editor' ) );
		self::$contributor_id = $factory->user->create( array( 'role' => 'contributor' ) );
		self::$subscriber_id  = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$editor_id );
		self::delete_user( self::$contributor_id );
		self::delete_user( self::$subscriber_id );
	}

	/**
	 * @ticket 64651
	 *
	 * @covers WP_REST_Icons_Controller::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/icons', $routes );
		$this->assertArrayHasKey( '/wp/v2/icons/(?P<name>[a-z][a-z0-9-]*/[a-z][a-z0-9-]*)', $routes );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_context_param() {
	}

	/**
	 * Asserts that no icons can be created.
	 * No controller method is executed; 404 is returned by route matching.
	 *
	 * @ticket 64651
	 */
	public function test_create_item() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/icons' );
		$request->set_param( 'name', 'foo' );
		$request->set_param( 'label', 'Foo' );
		$request->set_param( 'content', '<svg></svg>' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Asserts that no icons can be updated.
	 * No controller method is executed; 404 is returned by route matching.
	 *
	 * @ticket 64651
	 */
	public function test_update_item() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/icons/core/foo' );
		$request->set_param( 'label', 'Foo' );
		$request->set_param( 'content', '<svg></svg>' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Asserts that no icons can be deleted.
	 * No controller method is executed; 404 is returned by route matching.
	 *
	 * @ticket 64651
	 */
	public function test_delete_item() {
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/icons/core/wordpress' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::get_item
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// see methods test_get_item_*
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_get_items() {
		// see methods test_get_items_*
	}

	/**
	 * @ticket 64651
	 *
	 * @covers WP_REST_Icons_Controller::prepare_item_for_response
	 */
	public function test_prepare_item() {
		$this->markTestSkipped( 'No public icons are available in manifest.php yet' );
		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$icon = $data[0];
		$this->assertArrayHasKey( 'name', $icon );
		$this->assertArrayHasKey( 'content', $icon );
		$this->assertIsString( $icon['name'] );
		$this->assertIsString( $icon['content'] );
		$this->assertStringStartsWith( '<svg ', $icon['content'], 'Icon content should be valid SVG markup' );
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::get_item_schema
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item_schema() {
	}

	/**
	 * Test that GET /wp/v2/icons returns a list of icons for users with edit_posts capability.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_returns_icons_list() {
		$this->markTestSkipped( 'No public icons are available in manifest.php yet' );
		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data, 'Icon registry should contain at least one icon' );
	}

	/**
	 * Test that GET /wp/v2/icons requires proper permissions.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_requires_edit_posts_capability() {
		wp_set_current_user( self::$subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_view', $response, 403 );
	}

	/**
	 * Test that administrators can access icons.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_admin_has_access() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test that contributors can access icons.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_contributor_has_access() {
		wp_set_current_user( self::$contributor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test that GET /wp/v2/icons/core/arrow-left returns specific icon data.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_returns_specific_icon() {
		$this->markTestSkipped( 'No public icons are available in manifest.php yet' );
		wp_set_current_user( self::$editor_id );

		/*
		 * Intentionally avoid mocks or class reflection to register fake
		 * icons. Yes, this blurs the line between unit and integration
		 * testing, but as of now WP_Icons_Registry is closed for registration
		 * and really MUST contain our core icons.
		 */

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icons/core/arrow-left' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'name', $data );
		$this->assertArrayHasKey( 'label', $data );
		$this->assertArrayHasKey( 'content', $data );
		$this->assertSame( 'core/arrow-left', $data['name'] );
		$this->assertSame( 'Arrow Left', $data['label'] );
		$this->assertNotEmpty( $data['content'] );
		$this->assertStringStartsWith(
			'<svg xmlns="',
			$data['content'],
			'Icon content should match the actual SVG asset'
		);
	}

	/**
	 * Test that GET /wp/v2/icons/core/invalid returns 404 for non-existent icons.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_returns_404_for_invalid_icon() {
		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icons/core/invalid-icon-name' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_icon_not_found', $response, 404 );
	}

	/**
	 * Test that GET /wp/v2/icons/?search=arrow returns filtered results.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_search_filters_results() {
		$this->markTestSkipped( 'No public icons are available in manifest.php yet' );
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$request->set_param( 'search', 'arrow' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );

		// All returned icons should contain "arrow" in their name
		foreach ( $data as $icon ) {
			$this->assertStringContainsStringIgnoringCase( 'arrow', $icon['name'] );
		}

		// Assert that 'core/arrow-left' is specifically included in the results
		$icon_names = array_column( $data, 'name' );
		$this->assertContains( 'core/arrow-left', $icon_names, 'Search results should include core/arrow-left icon' );
	}

	/**
	 * Test that search is case-insensitive.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_search_case_insensitive() {
		wp_set_current_user( self::$editor_id );

		// Test with uppercase search term
		$request = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$request->set_param( 'search', 'ARROW' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );

		// All returned icons should contain "arrow" in their name (case insensitive)
		foreach ( $data as $icon ) {
			$this->assertStringContainsStringIgnoringCase( 'arrow', $icon['name'] );
		}
	}

	/**
	 * Test that search with no matches returns empty array.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_search_no_matches() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$request->set_param( 'search', 'nonexistenticon12345' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * Test that _fields parameter filters response fields.
	 *
	 * @ticket 64651
	 *
	 * @covers ::prepare_item_for_response
	 */
	public function test_get_items_fields_parameter() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$request->set_param( '_fields', 'name' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );

		// Each icon should only have the 'name' field
		foreach ( $data as $icon ) {
			$this->assertArrayHasKey( 'name', $icon );
			$this->assertArrayNotHasKey( 'content', $icon );
		}
	}

	/**
	 * Test permissions for getting a specific icon.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_get_item_requires_permissions() {
		$this->markTestSkipped( 'No public icons are available in manifest.php yet' );
		// Get a valid icon name first with proper permissions
		wp_set_current_user( self::$editor_id );
		$list_request  = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$list_response = rest_get_server()->dispatch( $list_request );

		// Icons endpoint must be available
		$this->assertSame( 200, $list_response->get_status(), 'Icons endpoint should be available and return 200' );

		$all_icons = $list_response->get_data();

		// Registry should contain at least our test icon
		$this->assertIsArray( $all_icons, 'Icons endpoint should return an array' );
		$this->assertNotEmpty( $all_icons, 'Icon registry should contain at least one icon' );
		$this->assertArrayHasKey( 'name', $all_icons[0], 'Icons should have a name field' );

		$test_icon_name = $all_icons[0]['name'];

		// Now test with subscriber (no permissions)
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/icons/' . $test_icon_name );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_view', $response, 403 );
	}

	/**
	 * Test that unauthenticated users cannot access icons.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_requires_authentication() {
		wp_set_current_user( 0 ); // No user

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icons' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}

	/**
	 * Test that unauthenticated users cannot access specific icons.
	 *
	 * @ticket 64651
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_get_item_requires_authentication() {
		wp_set_current_user( 0 ); // No user

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icons/core/some-icon' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}
}
