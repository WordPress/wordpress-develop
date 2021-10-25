<?php
/**
 * REST_Block_Editor_Settings_Controller_Test tests.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.9.0
 */

/**
 * Tests for REST_Block_Editor_Settings_Controller_Test.
 *
 * @since 5.9.0
 *
 * @covers REST_Block_Editor_Settings_Controller_Test
 *
 * @group restapi-block-editor
 * @group restapi
 */
class REST_Block_Editor_Settings_Controller_Test extends WP_Test_REST_Controller_Testcase {

	/**
	 * Admin user ID.
	 *
	 * @since 5.9.0
	 *
	 * @var int $subscriber_id
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @since 5.9.0
	 *
	 * @var int $subscriber_id
	 */
	protected static $subscriber_id;

	/**
	 * Create fake data before our tests run.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$subscriber_id );
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-block-editor/v1/settings', $routes );
	}

	/**
	 * The test_context_param() method does not exist for block editor settings.
	 */
	public function test_context_param() {

	}

	public function test_get_items() {
		wp_set_current_user( self::$admin_id );
		$editor_context    = new WP_Block_Editor_Context();
		$expected_settings = get_block_editor_settings( array(), $editor_context );

		$request  = new WP_REST_Request( 'GET', '/wp-block-editor/v1/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $response->get_data(), $expected_settings );
	}

	/**
	 * The test_get_item() method does not exist for block editor settings.
	 */
	public function test_get_item() {}

	/**
	 * The test_create_item() method does not exist for block editor settings.
	 */
	public function test_create_item() {}

	/**
	 * The test_update_item() method does not exist for block editor settings.
	 */
	public function test_update_item() {}

	/**
	 * The test_delete_item() method does not exist for block editor settings.
	 */
	public function test_delete_item() {}

	/**
	 * The test_prepare_item() method does not exist for block editor settings.
	 */
	public function test_prepare_item() {}
	public function test_get_item_schema() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp-block-editor/v1/settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
	}
}
