<?php
/**
 * Unit tests covering WP_Block_Pattern_Categories_Registry functionality.
 *
 * @package    WordPress
 * @subpackage REST_API
 * @since      6.0.0
 */

/**
 * Tests for REST API for Block Pattern Categories Registry.
 *
 * @since 6.0.0
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
		$reflection = new ReflectionClass( 'WP_Block_Pattern_Categories_Registry' );
		$reflection->getProperty( 'instance' )->setAccessible( true );
		self::$orig_registry = $reflection->getStaticPropertyValue( 'instance' );
		$test_registry       = new WP_Block_Pattern_Categories_Registry();
		$reflection->setStaticPropertyValue( 'instance', $test_registry );

		// Register some categories in the test registry.
		$test_registry->register( 'test', array( 'label' => 'Test' ) );
		$test_registry->register( 'query', array( 'label' => 'Query' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );

		// Restore the original registry instance.
		$reflection = new ReflectionClass( 'WP_Block_Pattern_Categories_Registry' );
		$reflection->setStaticPropertyValue( 'instance', self::$orig_registry );
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
		$expected_fields = array( 'name', 'label' );

		$request            = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request['_fields'] = 'name,label';
		$response           = rest_get_server()->dispatch( $request );
		$data               = $response->get_data();

		$this->assertCount( count( $expected_names ), $data );
		foreach ( $data as $idx => $item ) {
			$this->assertSame( $expected_names[ $idx ], $item['name'] );
			$this->assertSame( $expected_fields, array_keys( $item ) );
		}
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
