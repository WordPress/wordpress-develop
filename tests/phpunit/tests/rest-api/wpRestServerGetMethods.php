<?php

/**
 * Tests covering WP_REST_Server HTTP method array getters.
 *
 * @group restapi
 *
 * @covers WP_REST_Server::get_readable_methods
 * @covers WP_REST_Server::get_creatable_methods
 * @covers WP_REST_Server::get_editable_methods
 * @covers WP_REST_Server::get_deletable_methods
 */
class Tests_REST_API_WpRestServerGetMethods extends WP_Test_REST_TestCase {

	/**
	 * Server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Set up test environment before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->server = new WP_REST_Server();
		do_action( 'rest_api_init', $this->server );
	}

	/**
	 * Tear down test environment after each test.
	 */
	public function tear_down() {
		$this->server = null;
		parent::tear_down();
	}

	/**
	 * @ticket 66583
	 */
	public function test_get_readable_methods_returns_expected_array() {
		$expected = array( 'GET' );
		$actual   = WP_REST_Server::get_readable_methods();

		$this->assertSame( $expected, $actual, 'get_readable_methods() must return an array containing GET.' );
		$this->assertSame(
			array_map( 'trim', explode( ',', WP_REST_Server::READABLE ) ),
			$actual,
			'get_readable_methods() must match the exploded READABLE constant.'
		);
	}

	/**
	 * @ticket 66583
	 */
	public function test_get_creatable_methods_returns_expected_array() {
		$expected = array( 'POST' );
		$actual   = WP_REST_Server::get_creatable_methods();

		$this->assertSame( $expected, $actual, 'get_creatable_methods() must return an array containing POST.' );
		$this->assertSame(
			array_map( 'trim', explode( ',', WP_REST_Server::CREATABLE ) ),
			$actual,
			'get_creatable_methods() must match the exploded CREATABLE constant.'
		);
	}

	/**
	 * @ticket 66583
	 */
	public function test_get_editable_methods_returns_expected_array() {
		$expected = array( 'POST', 'PUT', 'PATCH' );
		$actual   = WP_REST_Server::get_editable_methods();

		$this->assertSame( $expected, $actual, 'get_editable_methods() must return an array containing POST, PUT, and PATCH.' );
		$this->assertSame(
			array_map( 'trim', explode( ',', WP_REST_Server::EDITABLE ) ),
			$actual,
			'get_editable_methods() must match the exploded EDITABLE constant.'
		);
	}

	/**
	 * @ticket 66583
	 */
	public function test_get_deletable_methods_returns_expected_array() {
		$expected = array( 'DELETE' );
		$actual   = WP_REST_Server::get_deletable_methods();

		$this->assertSame( $expected, $actual, 'get_deletable_methods() must return an array containing DELETE.' );
		$this->assertSame(
			array_map( 'trim', explode( ',', WP_REST_Server::DELETABLE ) ),
			$actual,
			'get_deletable_methods() must match the exploded DELETABLE constant.'
		);
	}

	/**
	 * @ticket 66583
	 */
	public function test_constants_remain_unchanged() {
		$this->assertSame( 'GET', WP_REST_Server::READABLE, 'READABLE constant must remain unchanged.' );
		$this->assertSame( 'POST', WP_REST_Server::CREATABLE, 'CREATABLE constant must remain unchanged.' );
		$this->assertSame( 'POST, PUT, PATCH', WP_REST_Server::EDITABLE, 'EDITABLE constant must remain unchanged.' );
		$this->assertSame( 'DELETE', WP_REST_Server::DELETABLE, 'DELETABLE constant must remain unchanged.' );
		$this->assertSame( 'GET, POST, PUT, PATCH, DELETE', WP_REST_Server::ALLMETHODS, 'ALLMETHODS constant must remain unchanged.' );
	}

	/**
	 * @ticket 66583
	 */
	public function test_register_route_with_get_readable_methods() {
		$this->server->register_route(
			'test-ns/v1',
			'/test-ns/v1/test-readable',
			array(
				array(
					'methods'             => WP_REST_Server::get_readable_methods(),
					'callback'            => static function () {
						return new WP_REST_Response( array( 'success' => true ) );
					},
					'permission_callback' => '__return_true',
				),
			)
		);

		$routes = $this->server->get_routes( 'test-ns/v1' );
		$this->assertArrayHasKey( '/test-ns/v1/test-readable', $routes, 'Route must be registered under the namespace.' );
		$this->assertSame(
			array( 'GET' => true ),
			$routes['/test-ns/v1/test-readable'][0]['methods'],
			'Route handler methods must map GET to true.'
		);

		$request  = new WP_REST_Request( 'GET', '/test-ns/v1/test-readable' );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Dispatching GET request must succeed with HTTP 200.' );
		$this->assertSame( array( 'success' => true ), $response->get_data(), 'Response data must match expected payload.' );

		$invalid_request  = new WP_REST_Request( 'DELETE', '/test-ns/v1/test-readable' );
		$invalid_response = $this->server->dispatch( $invalid_request );
		$this->assertSame( 404, $invalid_response->get_status(), 'Dispatching DELETE request to readable endpoint must fail with HTTP 404.' );
		$this->assertSame( 'rest_no_route', $invalid_response->as_error()->get_error_code(), 'Error code should be rest_no_route.' );
	}

	/**
	 * @ticket 66583
	 */
	public function test_register_route_with_get_creatable_methods() {
		$this->server->register_route(
			'test-ns/v1',
			'/test-ns/v1/test-creatable',
			array(
				array(
					'methods'             => WP_REST_Server::get_creatable_methods(),
					'callback'            => static function () {
						return new WP_REST_Response( array( 'created' => true ) );
					},
					'permission_callback' => '__return_true',
				),
			)
		);

		$routes = $this->server->get_routes( 'test-ns/v1' );
		$this->assertArrayHasKey( '/test-ns/v1/test-creatable', $routes, 'Route must be registered under the namespace.' );
		$this->assertSame(
			array( 'POST' => true ),
			$routes['/test-ns/v1/test-creatable'][0]['methods'],
			'Route handler methods must map POST to true.'
		);

		$request  = new WP_REST_Request( 'POST', '/test-ns/v1/test-creatable' );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Dispatching POST request must succeed with HTTP 200.' );
		$this->assertSame( array( 'created' => true ), $response->get_data(), 'Response data must match expected payload.' );
	}

	/**
	 * @ticket 66583
	 */
	public function test_register_route_with_get_editable_methods() {
		$this->server->register_route(
			'test-ns/v1',
			'/test-ns/v1/test-editable',
			array(
				array(
					'methods'             => WP_REST_Server::get_editable_methods(),
					'callback'            => static function ( WP_REST_Request $request ) {
						return new WP_REST_Response( array( 'method' => $request->get_method() ) );
					},
					'permission_callback' => '__return_true',
				),
			)
		);

		$routes = $this->server->get_routes( 'test-ns/v1' );
		$this->assertArrayHasKey( '/test-ns/v1/test-editable', $routes, 'Route must be registered under the namespace.' );
		$this->assertSame(
			array(
				'POST'  => true,
				'PUT'   => true,
				'PATCH' => true,
			),
			$routes['/test-ns/v1/test-editable'][0]['methods'],
			'Route handler methods must map POST, PUT, and PATCH to true.'
		);

		foreach ( array( 'POST', 'PUT', 'PATCH' ) as $method ) {
			$request  = new WP_REST_Request( $method, '/test-ns/v1/test-editable' );
			$response = $this->server->dispatch( $request );
			$this->assertSame( 200, $response->get_status(), "Dispatching {$method} request must succeed with HTTP 200." );
			$this->assertSame(
				array( 'method' => $method ),
				$response->get_data(),
				"Response data must reflect the dispatched method {$method}."
			);
		}
	}

	/**
	 * @ticket 66583
	 */
	public function test_register_route_with_get_deletable_methods() {
		$this->server->register_route(
			'test-ns/v1',
			'/test-ns/v1/test-deletable',
			array(
				array(
					'methods'             => WP_REST_Server::get_deletable_methods(),
					'callback'            => static function () {
						return new WP_REST_Response( array( 'deleted' => true ) );
					},
					'permission_callback' => '__return_true',
				),
			)
		);

		$routes = $this->server->get_routes( 'test-ns/v1' );
		$this->assertArrayHasKey( '/test-ns/v1/test-deletable', $routes, 'Route must be registered under the namespace.' );
		$this->assertSame(
			array( 'DELETE' => true ),
			$routes['/test-ns/v1/test-deletable'][0]['methods'],
			'Route handler methods must map DELETE to true.'
		);

		$request  = new WP_REST_Request( 'DELETE', '/test-ns/v1/test-deletable' );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Dispatching DELETE request must succeed with HTTP 200.' );
		$this->assertSame( array( 'deleted' => true ), $response->get_data(), 'Response data must match expected payload.' );
	}

	/**
	 * @ticket 66583
	 */
	public function test_register_rest_route_with_method_array_getters() {
		register_rest_route(
			'test-helper/v1',
			'/item',
			array(
				array(
					'methods'             => WP_REST_Server::get_readable_methods(),
					'callback'            => static function () {
						return new WP_REST_Response( array( 'action' => 'read' ) );
					},
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::get_creatable_methods(),
					'callback'            => static function () {
						return new WP_REST_Response( array( 'action' => 'create' ) );
					},
					'permission_callback' => '__return_true',
				),
			)
		);

		$get_request  = new WP_REST_Request( 'GET', '/test-helper/v1/item' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$this->assertSame( 200, $get_response->get_status(), 'GET request via register_rest_route must succeed.' );
		$this->assertSame( array( 'action' => 'read' ), $get_response->get_data(), 'GET response data must match.' );

		$post_request  = new WP_REST_Request( 'POST', '/test-helper/v1/item' );
		$post_response = rest_get_server()->dispatch( $post_request );
		$this->assertSame( 200, $post_response->get_status(), 'POST request via register_rest_route must succeed.' );
		$this->assertSame( array( 'action' => 'create' ), $post_response->get_data(), 'POST response data must match.' );
	}
}
