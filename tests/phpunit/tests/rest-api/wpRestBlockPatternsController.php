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
		$reflection = new ReflectionClass( 'WP_Block_Patterns_Registry' );
		$reflection->getProperty( 'instance' )->setAccessible( true );
		self::$orig_registry = $reflection->getStaticPropertyValue( 'instance' );
		$test_registry       = new WP_Block_Patterns_Registry();
		$reflection->setStaticPropertyValue( 'instance', $test_registry );

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
		$reflection = new ReflectionClass( 'WP_Block_Patterns_Registry' );
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

		$expected_names  = array( 'test/one', 'test/two' );
		$expected_fields = array( 'name', 'content' );

		$request            = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request['_fields'] = 'name,content';
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
