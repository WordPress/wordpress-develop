<?php
/**
 * Unit tests covering WP_REST_Block_Patterns_Controller functionality.
 *
 * @package    WordPress
 * @subpackage REST_API
 * @since      6.0.0
 */

/**
 * Tests for REST API for Block Patterns.
 *
 * @since 6.0.0
 *
 * @ticket 55505
 *
 * @covers WP_REST_Block_Patterns_Controller
 *
 * @group restapi
 */
class Tests_REST_WpRestBlockPatternsController extends WP_Test_REST_Controller_Testcase {

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
	const REQUEST_ROUTE = '/wp/v2/block-patterns/patterns';

	/**
	 * Set up class test fixtures.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		// Setup an empty testing instance of `WP_Block_Patterns_Registry` and save the original.
		self::$orig_registry              = WP_Block_Patterns_Registry::get_instance();
		self::$registry_instance_property = new ReflectionProperty( 'WP_Block_Patterns_Registry', 'instance' );
		self::$registry_instance_property->setAccessible( true );
		$test_registry = new WP_Block_Pattern_Categories_Registry();
		self::$registry_instance_property->setValue( $test_registry );

		// Register some patterns in the test registry.
		$test_registry->register(
			'test/one',
			array(
				'title'         => 'Pattern One',
				'categories'    => array( 'test' ),
				'viewportWidth' => 1440,
				'content'       => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
			)
		);

		$test_registry->register(
			'test/two',
			array(
				'title'      => 'Pattern Two',
				'categories' => array( 'test' ),
				'content'    => '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->',
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

		$request            = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request['_fields'] = 'name,content';
		$response           = rest_get_server()->dispatch( $request );
		$data               = $response->get_data();

		$this->assertIsArray( $data, 'WP_REST_Block_Patterns_Controller::get_items() should return an array' );
		$this->assertGreaterThanOrEqual( 2, count( $data ), 'WP_REST_Block_Patterns_Controller::get_items() should return at least 2 items' );
		$this->assertSame(
			array(
				'name'    => 'test/one',
				'content' => '<!-- wp:heading {"level":1} --><h1>One</h1><!-- /wp:heading -->',
			),
			$data[0],
			'WP_REST_Block_Patterns_Controller::get_items() should return test/one'
		);
		$this->assertSame(
			array(
				'name'    => 'test/two',
				'content' => '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->',
			),
			$data[1],
			'WP_REST_Block_Patterns_Controller::get_items() should return test/two'
		);
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
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$request  = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$response = rest_do_request( $request );

		$this->assertWPError( $response->as_error() );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_context_param() {
		$this->markTestSkipped( 'Controller does not use context_param.' );
	}

	public function test_get_item() {
		$this->markTestSkipped( 'Controller does not have get_item route.' );
	}

	public function test_create_item() {
		$this->markTestSkipped( 'Controller does not have create_item route.' );
	}

	public function test_update_item() {
		$this->markTestSkipped( 'Controller does not have update_item route.' );
	}

	public function test_delete_item() {
		$this->markTestSkipped( 'Controller does not have delete_item route.' );
	}

	public function test_prepare_item() {
		$this->markTestSkipped( 'Controller does not have prepare_item route.' );
	}

	public function test_get_item_schema() {
		$this->markTestSkipped( 'Controller does not have get_item_schema route.' );
	}
}
