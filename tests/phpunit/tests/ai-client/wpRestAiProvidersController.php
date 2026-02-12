<?php
/**
 * Tests for WP_REST_AI_V1_Providers_Controller.
 *
 * @group ai-client
 * @group rest-api
 * @covers WP_REST_AI_V1_Providers_Controller
 */
class Tests_AI_Client_WP_REST_AI_V1_Providers_Controller extends WP_UnitTestCase {

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
	 * Test that the providers route is registered.
	 *
	 * @ticket TBD
	 */
	public function test_providers_route_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp-ai/v1/providers', $routes );
	}

	/**
	 * Test that the single provider route is registered.
	 *
	 * @ticket TBD
	 */
	public function test_single_provider_route_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp-ai/v1/providers/(?P<providerId>[^/]+)', $routes );
	}

	/**
	 * Test that the provider models route is registered.
	 *
	 * @ticket TBD
	 */
	public function test_provider_models_route_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp-ai/v1/providers/(?P<providerId>[^/]+)/models', $routes );
	}

	/**
	 * Test that the single model route is registered.
	 *
	 * @ticket TBD
	 */
	public function test_single_model_route_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp-ai/v1/providers/(?P<providerId>[^/]+)/models/(?P<modelId>[^/]+)', $routes );
	}

	/**
	 * Test providers permissions check for admin.
	 *
	 * @ticket TBD
	 */
	public function test_providers_permissions_check_admin() {
		$controller = new WP_REST_AI_V1_Providers_Controller();

		wp_set_current_user( self::$admin_user_id );
		$this->assertTrue( $controller->permissions_check_providers() );
	}

	/**
	 * Test providers permissions check for subscriber.
	 *
	 * @ticket TBD
	 */
	public function test_providers_permissions_check_subscriber() {
		$controller = new WP_REST_AI_V1_Providers_Controller();

		wp_set_current_user( self::$subscriber_user_id );
		$result = $controller->permissions_check_providers();
		$this->assertWPError( $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test models permissions check for admin.
	 *
	 * @ticket TBD
	 */
	public function test_models_permissions_check_admin() {
		$controller = new WP_REST_AI_V1_Providers_Controller();

		wp_set_current_user( self::$admin_user_id );
		$this->assertTrue( $controller->permissions_check_models() );
	}

	/**
	 * Test models permissions check for subscriber.
	 *
	 * @ticket TBD
	 */
	public function test_models_permissions_check_subscriber() {
		$controller = new WP_REST_AI_V1_Providers_Controller();

		wp_set_current_user( self::$subscriber_user_id );
		$result = $controller->permissions_check_models();
		$this->assertWPError( $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test listing providers as admin.
	 *
	 * @ticket TBD
	 */
	public function test_get_providers_as_admin() {
		wp_set_current_user( self::$admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/wp-ai/v1/providers' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test listing providers as anonymous user.
	 *
	 * @ticket TBD
	 */
	public function test_get_providers_as_anonymous() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp-ai/v1/providers' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Test getting a non-existent provider.
	 *
	 * @ticket TBD
	 */
	public function test_get_nonexistent_provider() {
		wp_set_current_user( self::$admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/wp-ai/v1/providers/nonexistent' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test getting models for a non-existent provider.
	 *
	 * @ticket TBD
	 */
	public function test_get_models_nonexistent_provider() {
		wp_set_current_user( self::$admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/wp-ai/v1/providers/nonexistent/models' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test getting a single model for a non-existent provider.
	 *
	 * @ticket TBD
	 */
	public function test_get_model_nonexistent_provider() {
		wp_set_current_user( self::$admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/wp-ai/v1/providers/nonexistent/models/some-model' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test the provider schema.
	 *
	 * @ticket TBD
	 */
	public function test_provider_schema() {
		$controller = new WP_REST_AI_V1_Providers_Controller();
		$schema     = $controller->get_provider_schema();

		$this->assertSame( 'ai_provider', $schema['title'] );
		$this->assertArrayHasKey( 'properties', $schema );
	}

	/**
	 * Test the model schema.
	 *
	 * @ticket TBD
	 */
	public function test_model_schema() {
		$controller = new WP_REST_AI_V1_Providers_Controller();
		$schema     = $controller->get_model_schema();

		$this->assertSame( 'ai_model', $schema['title'] );
		$this->assertArrayHasKey( 'properties', $schema );
	}
}
