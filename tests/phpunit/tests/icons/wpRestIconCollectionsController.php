<?php
/**
 * Unit tests covering WP_REST_Icon_Collections_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.1.0
 *
 * @group restapi
 * @group icons
 *
 * @coversDefaultClass WP_REST_Icon_Collections_Controller
 */
class Tests_REST_WpRestIconCollectionsController extends WP_Test_REST_Controller_Testcase {
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

	public function set_up() {
		parent::set_up();

		/*
		 * Other suites reset the `WP_Icon_Collections_Registry` singleton, wiping the
		 * core collection that `init` only registers once. Re-register it when empty
		 * so order-dependent tests pass.
		 */
		if ( ! WP_Icon_Collections_Registry::get_instance()->is_registered( 'core' ) ) {
			_wp_register_default_icon_collections();
		}
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/icon-collections', $routes );
		$this->assertArrayHasKey( '/wp/v2/icon-collections/(?P<slug>[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?)', $routes );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::get_items
	 */
	public function test_get_items() {
		wp_register_icon_collection( 'rest-test-collection', array( 'label' => 'REST Test' ) );

		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icon-collections' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		$slugs = array_column( $data, 'slug' );
		$this->assertContains( 'core', $slugs );
		$this->assertContains( 'rest-test-collection', $slugs );

		wp_unregister_icon_collection( 'rest-test-collection' );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		wp_register_icon_collection(
			'rest-test-collection',
			array(
				'label'       => 'REST Test',
				'description' => 'A REST test collection.',
			)
		);

		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icon-collections/rest-test-collection' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'rest-test-collection', $data['slug'] );
		$this->assertSame( 'REST Test', $data['label'] );
		$this->assertSame( 'A REST test collection.', $data['description'] );

		wp_unregister_icon_collection( 'rest-test-collection' );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_returns_404_for_unknown_collection() {
		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icon-collections/unknown-collection' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_icon_collection_not_found', $response, 404 );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_requires_edit_posts_capability() {
		wp_set_current_user( self::$subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icon-collections' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_view', $response, 403 );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_requires_authentication() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icon-collections' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_admin_has_access() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icon-collections' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_contributor_has_access() {
		wp_set_current_user( self::$contributor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icon-collections' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_get_item_requires_authentication() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icon-collections/core' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::prepare_item_for_response
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/icon-collections/core' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'slug', $data );
		$this->assertArrayHasKey( 'label', $data );
		$this->assertArrayHasKey( 'description', $data );
		$this->assertSame( 'core', $data['slug'] );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::prepare_item_for_response
	 */
	public function test_get_items_fields_parameter() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/icon-collections' );
		$request->set_param( '_fields', 'slug' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );

		foreach ( $data as $collection ) {
			$this->assertArrayHasKey( 'slug', $collection );
			$this->assertArrayNotHasKey( 'label', $collection );
		}
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/icon-collections' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$properties = $data['schema']['properties'];
		$this->assertCount( 3, $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'label', $properties );
		$this->assertArrayHasKey( 'description', $properties );
	}

	/**
	 * Asserts that no icon collections can be created.
	 * No controller method is executed; 404 is returned by route matching.
	 *
	 * @ticket 64847
	 */
	public function test_create_item() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/icon-collections' );
		$request->set_param( 'slug', 'foo' );
		$request->set_param( 'label', 'Foo' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Asserts that no icon collections can be updated.
	 * No controller method is executed; 404 is returned by route matching.
	 *
	 * @ticket 64847
	 */
	public function test_update_item() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/icon-collections/core' );
		$request->set_param( 'label', 'Foo' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Asserts that no icon collections can be deleted.
	 * No controller method is executed; 404 is returned by route matching.
	 *
	 * @ticket 64847
	 */
	public function test_delete_item() {
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/icon-collections/core' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_context_param() {
	}
}
