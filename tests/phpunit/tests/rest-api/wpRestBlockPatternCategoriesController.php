<?php
/**
 * Unit tests covering WP_Block_Pattern_Categories_Registry functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 6.0.0
 *
 * @ticket 55505
 *
 * @covers WP_REST_Block_Pattern_Categories_Controller
 *
 * @group restapi
 */
class Tests_REST_WpRestBlockPatternCategoriesController extends WP_Test_REST_Controller_Testcase {

	/**
	 * Admin user ID.
	 *
	 * @since 6.0.0
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Original instance of WP_Block_Patterns_Registry.
	 *
	 * @since 6.0.0
	 *
	 * @var WP_Block_Patterns_Registry
	 */
	protected static $orig_registry;

	/**
	 * Instance of the reflected `instance` property.
	 *
	 * @since 6.0.0
	 *
	 * @var ReflectionProperty
	 */
	private static $registry_instance_property;

	/**
	 * The REST API route.
	 *
	 * @since 6.0.0
	 *
	 * @var string
	 */
	const REQUEST_ROUTE = '/wp/v2/block-patterns/categories';

	/**
	 * Set up class test fixtures.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetupBeforeClass( $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		// Setup an empty testing instance of `WP_Block_Pattern_Categories_Registry` and save the original.
		self::$orig_registry              = WP_Block_Pattern_Categories_Registry::get_instance();
		self::$registry_instance_property = new ReflectionProperty( 'WP_Block_Pattern_Categories_Registry', 'instance' );
		self::$registry_instance_property->setAccessible( true );
		$test_registry = new WP_Block_Pattern_Categories_Registry();
		self::$registry_instance_property->setValue( $test_registry );

		// Register some categories in the test registry.
		$test_registry->register(
			'test',
			array(
				'label'       => 'Test',
				'description' => 'Test description',
			)
		);
		$test_registry->register(
			'query',
			array(
				'label'       => 'Query',
				'description' => 'Query',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );

		// Restore the original registry instance.
		self::$registry_instance_property->setValue( self::$orig_registry );
		self::$registry_instance_property->setAccessible( false );
		self::$registry_instance_property = null;
		self::$orig_registry              = null;
	}

	public function set_up() {
		parent::set_up();

		switch_theme( 'emptytheme' );
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( static::REQUEST_ROUTE, $routes );
	}

	public function test_get_items() {
		wp_set_current_user( self::$admin_id );

		$expected_names  = array( 'test', 'query' );
		$expected_fields = array( 'name', 'label', 'description' );

		$request            = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request['_fields'] = 'name,label,description';
		$response           = rest_get_server()->dispatch( $request );
		$data               = $response->get_data();

		$this->assertCount( count( $expected_names ), $data );
		foreach ( $data as $idx => $item ) {
			$this->assertSame( $expected_names[ $idx ], $item['name'] );
			$this->assertSame( $expected_fields, array_keys( $item ) );
		}
	}

	/**
	 * Verify capability check for unauthorized request (not logged in).
	 */
	public function test_get_items_unauthorized() {
		// Ensure current user is logged out.
		wp_logout();

		$request  = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$response = rest_do_request( $request );

		$this->assertWPError( $response->as_error() );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Verify capability check for forbidden request (insufficient capability).
	 */
	public function test_get_items_forbidden() {
		// Set current user without `edit_posts` capability.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$request  = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$response = rest_do_request( $request );

		$this->assertWPError( $response->as_error() );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_context_param() {
		// Controller does not use get_context_param().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// Controller does not implement get_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_create_item() {
		// Controller does not implement create_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_update_item() {
		// Controller does not implement update_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Controller does not implement delete_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_prepare_item() {
		// Controller does not implement prepare_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_get_item_schema() {
		// Controller does not implement get_item_schema().
	}
}
