<?php
/**
 * Tests for WP_REST_AI_V1_Generate_Controller.
 *
 * @group ai-client
 * @group rest-api
 * @covers WP_REST_AI_V1_Generate_Controller
 */
class Tests_AI_Client_WP_REST_AI_V1_Generate_Controller extends WP_UnitTestCase {

	/**
	 * REST Server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Test admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_user_id;

	/**
	 * Test subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_user_id;

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$admin_user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		self::$subscriber_user_id = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Test that the generate route is registered.
	 *
	 * @ticket TBD
	 */
	public function test_generate_route_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp-ai/v1/generate', $routes );
	}

	/**
	 * Test that the is-supported route is registered.
	 *
	 * @ticket TBD
	 */
	public function test_is_supported_route_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp-ai/v1/is-supported', $routes );
	}

	/**
	 * Test permissions check for admin user.
	 *
	 * @ticket TBD
	 */
	public function test_permissions_check_admin() {
		$controller = new WP_REST_AI_V1_Generate_Controller();

		wp_set_current_user( self::$admin_user_id );
		$this->assertTrue( $controller->permissions_check() );
	}

	/**
	 * Test permissions check for subscriber.
	 *
	 * @ticket TBD
	 */
	public function test_permissions_check_subscriber() {
		$controller = new WP_REST_AI_V1_Generate_Controller();

		wp_set_current_user( self::$subscriber_user_id );
		$result = $controller->permissions_check();
		$this->assertWPError( $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test permissions check for anonymous user.
	 *
	 * @ticket TBD
	 */
	public function test_permissions_check_anonymous() {
		$controller = new WP_REST_AI_V1_Generate_Controller();

		wp_set_current_user( 0 );
		$result = $controller->permissions_check();
		$this->assertWPError( $result );
	}

	/**
	 * Test generate endpoint returns 403 for non-authenticated users.
	 *
	 * @ticket TBD
	 */
	public function test_generate_endpoint_forbidden_anonymous() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/wp-ai/v1/generate' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'messages' => array(
						array(
							'role'  => 'user',
							'parts' => array(
								array(
									'channel' => 'content',
									'type'    => 'text',
									'text'    => 'Hello',
								),
							),
						),
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Test is-supported endpoint returns 403 for non-authenticated users.
	 *
	 * @ticket TBD
	 */
	public function test_is_supported_endpoint_forbidden_anonymous() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/wp-ai/v1/is-supported' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'messages' => array(
						array(
							'role'  => 'user',
							'parts' => array(
								array(
									'channel' => 'content',
									'type'    => 'text',
									'text'    => 'Hello',
								),
							),
						),
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Test the generation request schema has expected properties.
	 *
	 * @ticket TBD
	 */
	public function test_generation_request_schema() {
		$controller = new WP_REST_AI_V1_Generate_Controller();
		$schema     = $controller->get_generation_request_schema();

		$this->assertSame( 'ai_generation_request', $schema['title'] );
		$this->assertArrayHasKey( 'messages', $schema['properties'] );
		$this->assertArrayHasKey( 'modelConfig', $schema['properties'] );
		$this->assertArrayHasKey( 'providerId', $schema['properties'] );
		$this->assertArrayHasKey( 'modelId', $schema['properties'] );
		$this->assertArrayHasKey( 'modelPreferences', $schema['properties'] );
		$this->assertArrayHasKey( 'capability', $schema['properties'] );
		$this->assertArrayHasKey( 'requestOptions', $schema['properties'] );
	}

	/**
	 * Test the is_supported schema.
	 *
	 * @ticket TBD
	 */
	public function test_is_supported_schema() {
		$controller = new WP_REST_AI_V1_Generate_Controller();
		$schema     = $controller->get_is_supported_schema();

		$this->assertSame( 'ai_is_supported_response', $schema['title'] );
		$this->assertArrayHasKey( 'supported', $schema['properties'] );
	}

	/**
	 * Test the generation result schema.
	 *
	 * @ticket TBD
	 */
	public function test_generation_result_schema() {
		$controller = new WP_REST_AI_V1_Generate_Controller();
		$schema     = $controller->get_generation_result_schema();

		$this->assertSame( 'ai_generation_result', $schema['title'] );
	}
}
