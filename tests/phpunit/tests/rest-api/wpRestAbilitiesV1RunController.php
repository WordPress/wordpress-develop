<?php declare( strict_types=1 );

/**
 * Tests for the REST run controller for abilities endpoint.
 *
 * @covers WP_REST_Abilities_V1_Run_Controller
 *
 * @group abilities-api
 * @group restapi
 */
class Tests_REST_API_WpRestAbilitiesV1RunController extends WP_UnitTestCase {

	/**
	 * REST Server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Test user ID with permissions.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test user ID without permissions.
	 *
	 * @var int
	 */
	protected static $no_permission_user_id;

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create(
			array(
				'role' => 'editor',
			)
		);

		self::$no_permission_user_id = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		self::register_test_categories();
	}

	/**
	 * Tear down after class.
	 */
	public static function tear_down_after_class(): void {
		// Clean up registered test ability categories.
		foreach ( array( 'math', 'system', 'general' ) as $slug ) {
			wp_unregister_ability_category( $slug );
		}

		parent::tear_down_after_class();
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

		$this->register_test_abilities();

		// Set default user for tests
		wp_set_current_user( self::$user_id );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		// Clean up test abilities.
		foreach ( wp_get_abilities() as $ability ) {
			if ( ! str_starts_with( $ability->get_name(), 'test/' ) ) {
				continue;
			}

			wp_unregister_ability( $ability->get_name() );
		}

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Register test categories for testing.
	 */
	public static function register_test_categories(): void {
		// Simulates the init hook to allow test ability category registration.
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_categories_init';

		wp_register_ability_category(
			'math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations and calculations.',
			)
		);

		wp_register_ability_category(
			'system',
			array(
				'label'       => 'System',
				'description' => 'System information and operations.',
			)
		);

		wp_register_ability_category(
			'general',
			array(
				'label'       => 'General',
				'description' => 'General purpose abilities.',
			)
		);

		array_pop( $wp_current_filter );
	}

	/**
	 * Helper to register a test ability.
	 *
	 * @param string $name Ability name.
	 * @param array  $args Ability arguments.
	 */
	private function register_test_ability( string $name, array $args ): void {
		// Simulates the init hook to allow test abilities registration.
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init';

		wp_register_ability( $name, $args );

		array_pop( $wp_current_filter );
	}

	/**
	 * Register test abilities for testing.
	 */
	private function register_test_abilities(): void {
		// Regular ability (POST only).
		$this->register_test_ability(
			'test/calculator',
			array(
				'label'               => 'Calculator',
				'description'         => 'Performs calculations',
				'category'            => 'math',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'a' => array(
							'type'        => 'number',
							'description' => 'First number',
						),
						'b' => array(
							'type'        => 'number',
							'description' => 'Second number',
						),
					),
					'required'             => array( 'a', 'b' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type' => 'number',
				),
				'execute_callback'    => static function ( array $input ) {
					return $input['a'] + $input['b'];
				},
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Read-only ability (GET method).
		$this->register_test_ability(
			'test/user-info',
			array(
				'label'               => 'User Info',
				'description'         => 'Gets user information',
				'category'            => 'system',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'login' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => static function ( array $input ) {
					$user_id = $input['user_id'] ?? get_current_user_id();
					$user    = get_user_by( 'id', $user_id );
					if ( ! $user ) {
						return new WP_Error( 'user_not_found', 'User not found' );
					}
					return array(
						'id'    => $user->ID,
						'login' => $user->user_login,
					);
				},
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly' => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		// Destructive ability (DELETE method).
		$this->register_test_ability(
			'test/delete-user',
			array(
				'label'               => 'Delete User',
				'description'         => 'Deletes a user',
				'category'            => 'system',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				'output_schema'       => array(
					'type'     => 'string',
					'required' => true,
				),
				'execute_callback'    => static function ( array $input ) {
					$user_id = $input['user_id'] ?? get_current_user_id();
					$user    = get_user_by( 'id', $user_id );
					if ( ! $user ) {
						return new WP_Error( 'user_not_found', 'User not found' );
					}
					return 'User successfully deleted!';
				},
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
				'meta'                => array(
					'annotations'  => array(
						'destructive' => true,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		// Ability with contextual permissions
		$this->register_test_ability(
			'test/restricted',
			array(
				'label'               => 'Restricted Action',
				'description'         => 'Requires specific input for permission',
				'category'            => 'general',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'secret' => array( 'type' => 'string' ),
						'data'   => array( 'type' => 'string' ),
					),
					'required'   => array( 'secret', 'data' ),
				),
				'output_schema'       => array(
					'type' => 'string',
				),
				'execute_callback'    => static function ( array $input ) {
					return 'Success: ' . $input['data'];
				},
				'permission_callback' => static function ( array $input ) {
					// Only allow if secret matches
					return isset( $input['secret'] ) && 'valid_secret' === $input['secret'];
				},
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability that does not show in REST.
		$this->register_test_ability(
			'test/not-show-in-rest',
			array(
				'label'               => 'Hidden from REST',
				'description'         => 'It does not show in REST.',
				'category'            => 'general',
				'execute_callback'    => static function (): int {
					return 0;
				},
				'permission_callback' => '__return_true',
			)
		);

		// Ability that returns null
		$this->register_test_ability(
			'test/null-return',
			array(
				'label'               => 'Null Return',
				'description'         => 'Returns null',
				'category'            => 'general',
				'execute_callback'    => static function () {
					return null;
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability that returns WP_Error
		$this->register_test_ability(
			'test/error-return',
			array(
				'label'               => 'Error Return',
				'description'         => 'Returns error',
				'category'            => 'general',
				'execute_callback'    => static function () {
					return new WP_Error( 'test_error', 'This is a test error' );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability with invalid output
		$this->register_test_ability(
			'test/invalid-output',
			array(
				'label'               => 'Invalid Output',
				'description'         => 'Returns invalid output',
				'category'            => 'general',
				'output_schema'       => array(
					'type' => 'number',
				),
				'execute_callback'    => static function () {
					return 'not a number'; // Invalid - schema expects number
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability that returns an empty object.
		$this->register_test_ability(
			'test/empty-object-output',
			array(
				'label'               => 'Empty Object Output',
				'description'         => 'Returns an empty object.',
				'category'            => 'general',
				'output_schema'       => array(
					'type' => 'object',
				),
				'execute_callback'    => static function () {
					return array();
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability that returns an empty object nested in an object.
		$this->register_test_ability(
			'test/nested-empty-object-output',
			array(
				'label'               => 'Nested Empty Object Output',
				'description'         => 'Returns an object with an empty object property.',
				'category'            => 'general',
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => static function () {
					return array( 'settings' => array() );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability that returns a list of empty objects.
		$this->register_test_ability(
			'test/empty-object-list-output',
			array(
				'label'               => 'Empty Object List Output',
				'description'         => 'Returns a list of empty objects.',
				'category'            => 'general',
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'execute_callback'    => static function () {
					return array( array() );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability that returns an object result with an empty object property.
		$this->register_test_ability(
			'test/nested-empty-object-in-object-output',
			array(
				'label'               => 'Nested Empty Object In Object Output',
				'description'         => 'Returns an object result with an empty object property.',
				'category'            => 'general',
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => static function () {
					return (object) array( 'settings' => array() );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability that returns a JsonSerializable result with an empty object property.
		$this->register_test_ability(
			'test/nested-empty-object-in-json-serializable-output',
			array(
				'label'               => 'Nested Empty Object In JsonSerializable Output',
				'description'         => 'Returns a JsonSerializable result with an empty object property.',
				'category'            => 'general',
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => static function () {
					return new class() implements JsonSerializable {
						public function jsonSerialize(): array {
							return array( 'settings' => array() );
						}
					};
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability that returns an empty list.
		$this->register_test_ability(
			'test/empty-list-output',
			array(
				'label'               => 'Empty List Output',
				'description'         => 'Returns an empty list.',
				'category'            => 'general',
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'execute_callback'    => static function () {
					return array();
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability that returns a list of strings.
		$this->register_test_ability(
			'test/string-list-output',
			array(
				'label'               => 'String List Output',
				'description'         => 'Returns a list of strings.',
				'category'            => 'general',
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'execute_callback'    => static function () {
					return array( 'a', 'b' );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Ability that returns a string.
		$this->register_test_ability(
			'test/string-output',
			array(
				'label'               => 'String Output',
				'description'         => 'Returns a string.',
				'category'            => 'general',
				'output_schema'       => array(
					'type' => 'string',
				),
				'execute_callback'    => static function () {
					return 'value';
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Read-only ability for query params testing.
		$this->register_test_ability(
			'test/query-params',
			array(
				'label'               => 'Query Params Test',
				'description'         => 'Tests query parameter handling',
				'category'            => 'general',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'param1' => array( 'type' => 'string' ),
						'param2' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => static function ( $input ) {
					return $input;
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'annotations'  => array(
						'readonly' => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Test executing a regular ability with POST.
	 *
	 * @ticket 64098
	 */
	public function test_execute_regular_ability_post(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'a' => 5,
						'b' => 3,
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 8.0, $response->get_data() );
	}

	/**
	 * Test executing a read-only ability with GET.
	 *
	 * @ticket 64098
	 */
	public function test_execute_readonly_ability_get(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/user-info/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'user_id' => self::$user_id,
				),
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( self::$user_id, $data['id'] );
	}

	/**
	 * Test executing a destructive ability with GET.
	 *
	 * @ticket 64098
	 */
	public function test_execute_destructive_ability_delete(): void {
		$request = new WP_REST_Request( 'DELETE', '/wp-abilities/v1/abilities/test/delete-user/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'user_id' => self::$user_id,
				),
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'User successfully deleted!', $response->get_data() );
	}

	/**
	 * Test HTTP method validation for regular abilities.
	 *
	 * @ticket 64098
	 */
	public function test_regular_ability_requires_post(): void {
		$this->register_test_ability(
			'test/open-tool',
			array(
				'label'               => 'Open Tool',
				'description'         => 'Tool with no permission requirements',
				'category'            => 'general',
				'execute_callback'    => static function () {
					return 'success';
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/open-tool/run' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_ability_invalid_method', $data['code'] );
		$this->assertSame( 'Abilities that perform updates require POST method.', $data['message'] );
	}

	/**
	 * Test HTTP method validation for read-only abilities.
	 *
	 * @ticket 64098
	 */
	public function test_readonly_ability_requires_get(): void {
		// Try POST on a read-only ability (should fail).
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/user-info/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'user_id' => 1 ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_ability_invalid_method', $data['code'] );
		$this->assertSame( 'Read-only abilities require GET method.', $data['message'] );
	}

	/**
	 * Test HTTP method validation for destructive abilities.
	 *
	 * @ticket 64098
	 */
	public function test_destructive_ability_requires_delete(): void {
		// Try POST on a destructive ability (should fail).
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/delete-user/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'user_id' => 1 ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_ability_invalid_method', $data['code'] );
		$this->assertSame( 'Abilities that perform destructive actions require DELETE method.', $data['message'] );
	}

	/**
	 * Test output validation against schema.
	 * Note: When output validation fails in WP_Ability::execute(), it returns null,
	 * which causes the REST controller to return 'ability_invalid_output'.
	 *
	 * @ticket 64098
	 */
	public function test_output_validation(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/invalid-output/run' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 500, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'ability_invalid_output', $data['code'] );
		$this->assertSame(
			'Ability "test/invalid-output" has invalid output. Reason: output is not of type number.',
			$data['message']
		);
	}

	/**
	 * Data provider for test_output_is_prepared_for_json_clients().
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_output_is_prepared_for_json_clients(): array {
		return array(
			'root empty object'       => array( 'test/empty-object-output', '{}' ),
			'nested empty object'     => array( 'test/nested-empty-object-output', '{"settings":{}}' ),
			'numeric collection'      => array( 'test/empty-object-list-output', '[{}]' ),
			'object result'           => array( 'test/nested-empty-object-in-object-output', '{"settings":{}}' ),
			'JsonSerializable result' => array( 'test/nested-empty-object-in-json-serializable-output', '{"settings":{}}' ),
			'root empty list'         => array( 'test/empty-list-output', '[]' ),
			'list of strings'         => array( 'test/string-list-output', '["a","b"]' ),
			'string'                  => array( 'test/string-output', '"value"' ),
		);
	}

	/**
	 * Tests that ability results are prepared for JSON clients.
	 *
	 * @ticket 66267
	 * @dataProvider data_output_is_prepared_for_json_clients
	 *
	 * @param string $ability_name The ability name.
	 * @param string $expected     The expected encoded result.
	 */
	public function test_output_is_prepared_for_json_clients( string $ability_name, string $expected ): void {
		$request  = new WP_REST_Request( 'POST', "/wp-abilities/v1/abilities/{$ability_name}/run" );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'The ability should run successfully.' );
		$this->assertSame( $expected, wp_json_encode( $response->get_data() ), 'The result should be encoded as expected.' );
	}

	/**
	 * Tests that the global `_fields` and `_embed` parameters and response links leave
	 * ability results unchanged.
	 *
	 * @ticket 66267
	 */
	public function test_global_parameters_and_response_links_leave_output_unchanged(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/nested-empty-object-output/run' );

		// The result has no `other` field, so filtering would leave none of the result.
		$request->set_query_params(
			array(
				'_fields' => 'other',
				'_embed'  => '1',
			)
		);

		add_filter(
			'rest_post_dispatch',
			static function ( $response ) {
				$response->add_link( 'alternate', rest_url( '/wp-abilities/v1/abilities' ), array( 'embeddable' => true ) );
				return $response;
			}
		);

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'The ability should run successfully.' );

		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $this->server, $request );
		$data     = $this->server->response_to_data( $response, rest_parse_embed_param( $request['_embed'] ) );

		$this->assertSame( '{"settings":{}}', wp_json_encode( $data ), 'The result should be left unchanged.' );
	}

	/**
	 * Tests that the global `_embed` parameter leaves the `_links` returned by an ability
	 * unchanged, without requesting the linked resources.
	 *
	 * @ticket 66267
	 */
	public function test_links_in_the_result_are_not_embedded(): void {
		$result = array(
			'id'     => 1,
			'_links' => array(
				'author' => array(
					array(
						'href'       => rest_url( '/wp-abilities/v1/abilities' ),
						'embeddable' => true,
					),
				),
			),
		);
		$this->register_test_ability(
			'test/links-output',
			array(
				'label'               => 'Links Output',
				'description'         => 'Returns an object with its own embeddable link.',
				'category'            => 'general',
				'output_schema'       => array(
					'type' => 'object',
				),
				'execute_callback'    => static function () use ( $result ) {
					return $result;
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/links-output/run' );
		$request->set_query_params( array( '_embed' => '1' ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'The ability should run successfully.' );

		$dispatch = new MockAction();
		add_filter( 'rest_pre_dispatch', array( $dispatch, 'filter' ) );
		$data = $this->server->response_to_data( $response, rest_parse_embed_param( $request['_embed'] ) );

		$this->assertSame( $result, $data, 'The links returned by the ability should be left unchanged.' );
		$this->assertSame( 0, $dispatch->get_call_count(), 'No linked resource should be requested.' );
	}

	/**
	 * Tests that preloading a read-only ability run keeps the result prepared for
	 * JSON clients, also with the global `_fields` and `_embed` parameters.
	 *
	 * @ticket 66267
	 */
	public function test_preloaded_output_is_prepared_for_json_clients(): void {
		$this->register_test_ability(
			'test/readonly-output',
			array(
				'label'               => 'Read-only Output',
				'description'         => 'Returns an empty object from a read-only ability.',
				'category'            => 'general',
				'output_schema'       => array(
					'type' => 'object',
				),
				'execute_callback'    => static function () {
					return array();
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'annotations'  => array(
						'readonly' => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		$path    = '/wp-abilities/v1/abilities/test/readonly-output/run?_fields=other&_embed=1';
		$preload = rest_preload_api_request( array(), $path );

		$this->assertArrayHasKey( $path, $preload, 'The ability run should be preloaded.' );
		$this->assertSame( '{}', wp_json_encode( $preload[ $path ]['body'] ), 'The preloaded result should be encoded as an empty object.' );
	}

	/**
	 * Test permission check for execution.
	 *
	 * @ticket 64098
	 */
	public function test_execution_permission_denied(): void {
		wp_set_current_user( self::$no_permission_user_id );

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'a' => 5,
						'b' => 3,
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_ability_cannot_execute', $data['code'] );
		$this->assertSame( 'Sorry, you are not allowed to execute this ability.', $data['message'] );
	}

	/**
	 * Test contextual permission check.
	 *
	 * @ticket 64098
	 */
	public function test_contextual_permission_check(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/restricted/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'secret' => 'wrong_secret',
						'data'   => 'test data',
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );

		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'secret' => 'valid_secret',
						'data'   => 'test data',
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Success: test data', $response->get_data() );
	}

	/**
	 * Test handling an ability that does not show in REST.
	 *
	 * @ticket 64098
	 */
	public function test_do_not_show_in_rest(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/not-show-in-rest/run' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_ability_not_found', $data['code'] );
		$this->assertSame( 'Ability not found.', $data['message'] );
	}

	/**
	 * Test running an ability exposed in REST via the `public` meta flag.
	 *
	 * @ticket 65568
	 */
	public function test_run_public_meta_ability_is_executable(): void {
		$this->register_test_ability(
			'test/public-ability',
			array(
				'label'               => 'Public Ability',
				'description'         => 'Exposed in REST via the public meta flag.',
				'category'            => 'general',
				'execute_callback'    => '__return_true',
				'permission_callback' => '__return_true',
				'meta'                => array(
					'public' => true,
				),
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/public-ability/run' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data() );
	}

	/**
	 * Test handling of null is a valid return value.
	 *
	 * @ticket 64098
	 */
	public function test_null_return_handling(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/null-return/run' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertNull( $data );
	}

	/**
	 * Test handling of WP_Error return from ability.
	 *
	 * @ticket 64098
	 */
	public function test_wp_error_return_handling(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/error-return/run' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 500, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'test_error', $data['code'] );
		$this->assertSame( 'This is a test error', $data['message'] );
	}

	/**
	 * Test non-existent ability returns 404.
	 *
	 * @ticket 64098
	 */
	public function test_execute_non_existent_ability(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/non/existent/run' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_ability_not_found', $data['code'] );
	}

	/**
	 * Test schema retrieval for run endpoint.
	 *
	 * @ticket 64098
	 */
	public function test_run_endpoint_schema(): void {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp-abilities/v1/abilities/test/calculator/run' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'schema', $data );
		$schema = $data['schema'];

		$this->assertSame( 'ability-execution', $schema['title'] );
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'result', $schema['properties'] );
	}

	/**
	 * Test that invalid JSON in POST body is handled correctly.
	 *
	 * @ticket 64098
	 */
	public function test_invalid_json_in_post_body(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		// Set raw body with invalid JSON
		$request->set_body( '{"input": {invalid json}' );

		$response = $this->server->dispatch( $request );

		// When JSON is invalid, WordPress returns 400 Bad Request
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Test GET request with complex nested input array.
	 *
	 * @ticket 64098
	 */
	public function test_get_request_with_nested_input_array(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/query-params/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'level1' => array(
						'level2' => array(
							'value' => 'nested',
						),
					),
					'array'  => array( 1, 2, 3 ),
				),
			)
		);

		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'nested', $data['level1']['level2']['value'] );
		$this->assertSame( array( 1, 2, 3 ), $data['array'] );
	}

	/**
	 * Test GET request with non-array input parameter.
	 *
	 * @ticket 64098
	 */
	public function test_get_request_with_non_array_input(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/query-params/run' );
		$request->set_query_params(
			array(
				'input' => 'not-an-array', // String instead of array
			)
		);

		$response = $this->server->dispatch( $request );
		// When input is not an array, WordPress returns 400 Bad Request
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Test POST request with non-array input in JSON body.
	 *
	 * @ticket 64098
	 */
	public function test_post_request_with_non_array_input(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => 'string-value', // String instead of array
				)
			)
		);

		$response = $this->server->dispatch( $request );
		// When input is not an array, WordPress returns 400 Bad Request
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Test ability with invalid output that fails validation.
	 *
	 * @ticket 64098
	 */
	public function test_output_validation_failure_returns_error(): void {
		// Register ability with strict output schema.
		$this->register_test_ability(
			'test/strict-output',
			array(
				'label'               => 'Strict Output',
				'description'         => 'Ability with strict output schema',
				'category'            => 'general',
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array(
							'type' => 'string',
							'enum' => array( 'success', 'failure' ),
						),
					),
					'required'   => array( 'status' ),
				),
				'execute_callback'    => static function () {
					// Return invalid output that doesn't match schema
					return array( 'wrong_field' => 'value' );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/strict-output/run' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		// Should return error when output validation fails.
		$this->assertSame( 500, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'ability_invalid_output', $data['code'] );
		$this->assertSame(
			'Ability "test/strict-output" has invalid output. Reason: status is a required property of output.',
			$data['message']
		);
	}

	/**
	 * Test ability with invalid input that fails validation.
	 *
	 * @ticket 64098
	 */
	public function test_input_validation_failure_returns_error(): void {
		// Register ability with strict input schema.
		$this->register_test_ability(
			'test/strict-input',
			array(
				'label'               => 'Strict Input',
				'description'         => 'Ability with strict input schema',
				'category'            => 'general',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'required_field' => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'required_field' ),
				),
				'execute_callback'    => static function () {
					return array( 'status' => 'success' );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/strict-input/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		// Missing required field
		$request->set_body( wp_json_encode( array( 'input' => array( 'other_field' => 'value' ) ) ) );

		$response = $this->server->dispatch( $request );

		// Should return error when input validation fails.
		$this->assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'ability_invalid_input', $data['code'] );
		$this->assertSame(
			'Ability "test/strict-input" has invalid input. Reason: required_field is a required property of input.',
			$data['message']
		);
	}

	/**
	 * Tests that a normalization filter error defaults to a 400 REST response.
	 *
	 * @ticket 64989
	 */
	public function test_normalize_input_filter_error_defaults_to_bad_request_status(): void {
		$filter = static function ( $input ) {
			return new WP_Error( 'normalize_rejected', 'Rejected input.' );
		};

		add_filter( 'wp_ability_normalize_input', $filter );

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'a' => 5,
						'b' => 3,
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );

		remove_filter( 'wp_ability_normalize_input', $filter );

		$this->assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'normalize_rejected', $data['code'] );
		$this->assertSame( 'Rejected input.', $data['message'] );
	}

	/**
	 * Tests that a normalization filter error with custom status keeps that status.
	 *
	 * @ticket 64989
	 */
	public function test_normalize_input_filter_error_preserves_custom_status(): void {
		$filter = static function ( $input ) {
			return new WP_Error(
				'normalize_unprocessable',
				'Input cannot be normalized.',
				array( 'status' => 422 )
			);
		};

		add_filter( 'wp_ability_normalize_input', $filter );

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'a' => 5,
						'b' => 3,
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );

		remove_filter( 'wp_ability_normalize_input', $filter );

		$this->assertSame( 422, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'normalize_unprocessable', $data['code'] );
		$this->assertSame( 'Input cannot be normalized.', $data['message'] );
	}

	/**
	 * Tests that a validation filter error defaults to a 400 REST response.
	 *
	 * @ticket 64311
	 */
	public function test_validate_input_filter_error_defaults_to_bad_request_status(): void {
		$filter = static function () {
			return new WP_Error( 'validate_rejected', 'Rejected input.' );
		};

		add_filter( 'wp_ability_validate_input', $filter );

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'a' => 5,
						'b' => 3,
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );

		remove_filter( 'wp_ability_validate_input', $filter );

		$this->assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'validate_rejected', $data['code'] );
		$this->assertSame( 'Rejected input.', $data['message'] );
	}

	/**
	 * Tests that a validation filter error with custom status keeps that status.
	 *
	 * @ticket 64311
	 */
	public function test_validate_input_filter_error_preserves_custom_status(): void {
		$filter = static function () {
			return new WP_Error(
				'validate_unprocessable',
				'Input cannot be validated.',
				array( 'status' => 422 )
			);
		};

		add_filter( 'wp_ability_validate_input', $filter );

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'a' => 5,
						'b' => 3,
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );

		remove_filter( 'wp_ability_validate_input', $filter );

		$this->assertSame( 422, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'validate_unprocessable', $data['code'] );
		$this->assertSame( 'Input cannot be validated.', $data['message'] );
	}

	/**
	 * Test ability without annotations defaults to POST method.
	 *
	 * @ticket 64098
	 */
	public function test_ability_without_annotations_defaults_to_post_method(): void {
		// Register ability without annotations.
		$this->register_test_ability(
			'test/no-annotations',
			array(
				'label'               => 'No Annotations',
				'description'         => 'Ability without annotations.',
				'category'            => 'general',
				'execute_callback'    => static function () {
					return array( 'executed' => true );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Should require POST (default behavior).
		$get_request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/no-annotations/run' );
		$get_response = $this->server->dispatch( $get_request );
		$this->assertSame( 405, $get_response->get_status() );

		// Should work with POST.
		$post_request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/no-annotations/run' );
		$post_request->set_header( 'Content-Type', 'application/json' );

		$post_response = $this->server->dispatch( $post_request );
		$this->assertSame( 200, $post_response->get_status() );
	}

	/**
	 * Test edge case with empty input for GET method.
	 *
	 * @ticket 64098
	 */
	public function test_empty_input_handling_get_method(): void {
		$this->register_test_ability(
			'test/read-only-empty',
			array(
				'label'               => 'Read-only Empty',
				'description'         => 'Read-only with empty input.',
				'category'            => 'general',
				'execute_callback'    => static function () {
					return array( 'input_was_empty' => 0 === func_num_args() );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'annotations'  => array(
						'readonly' => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		// Tests GET with no input parameter.
		$get_request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/read-only-empty/run' );
		$get_response = $this->server->dispatch( $get_request );
		$this->assertSame( 200, $get_response->get_status() );
		$this->assertTrue( $get_response->get_data()['input_was_empty'] );
	}

	/**
	 * Test edge case with empty input for GET method, and normalized input using schema.
	 *
	 * @ticket 64098
	 */
	public function test_empty_input_handling_get_method_with_normalized_input(): void {
		$this->register_test_ability(
			'test/read-only-empty-array',
			array(
				'label'               => 'Read-only Empty Array',
				'description'         => 'Read-only with inferred empty array input from schema.',
				'category'            => 'general',
				'input_schema'        => array(
					'type'    => 'array',
					'default' => array(),
				),
				'execute_callback'    => static function ( $input ) {
					return is_array( $input ) && empty( $input );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'annotations'  => array(
						'readonly' => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		// Tests GET with no input parameter.
		$get_request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/read-only-empty-array/run' );
		$get_response = $this->server->dispatch( $get_request );
		$this->assertSame( 200, $get_response->get_status() );
		$this->assertTrue( $get_response->get_data() );
	}

	/**
	 * Test edge case with empty input for POST method.
	 *
	 * @ticket 64098
	 */
	public function test_empty_input_handling_post_method(): void {
		$this->register_test_ability(
			'test/regular-empty',
			array(
				'label'               => 'Regular Empty',
				'description'         => 'Regular with empty input.',
				'category'            => 'general',
				'execute_callback'    => static function () {
					return array( 'input_was_empty' => 0 === func_num_args() );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Tests POST with no body.
		$post_request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/regular-empty/run' );
		$post_request->set_header( 'Content-Type', 'application/json' );
		$post_request->set_body( '{}' ); // Empty JSON object

		$post_response = $this->server->dispatch( $post_request );
		$this->assertSame( 200, $post_response->get_status() );
		$this->assertTrue( $post_response->get_data()['input_was_empty'] );
	}

	/**
	 * Data provider for malformed JSON tests.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_malformed_json_provider(): array {
		return array(
			'Missing value'              => array( '{"input": }' ),
			'Trailing comma in array'    => array( '{"input": [1, 2, }' ),
			'Missing quotes on key'      => array( '{input: {}}' ),
			'JavaScript undefined'       => array( '{"input": undefined}' ),
			'JavaScript NaN'             => array( '{"input": NaN}' ),
			'Missing quotes nested keys' => array( '{"input": {a: 1, b: 2}}' ),
			'Single quotes'              => array( '\'{"input": {}}\'' ),
			'Unclosed object'            => array( '{"input": {"key": "value"' ),
		);
	}

	/**
	 * Test malformed JSON in POST body.
	 *
	 * @ticket 64098
	 *
	 * @dataProvider data_malformed_json_provider
	 *
	 * @param string $json Malformed JSON to test.
	 */
	public function test_malformed_json_post_body( string $json ): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $json );

		$response = $this->server->dispatch( $request );

		// Malformed JSON should result in 400 Bad Request
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Test input with various PHP types as strings.
	 *
	 * @ticket 64098
	 */
	public function test_php_type_strings_in_input(): void {
		// Register ability that accepts any input
		$this->register_test_ability(
			'test/echo',
			array(
				'label'               => 'Echo',
				'description'         => 'Echoes input',
				'category'            => 'general',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => static function ( $input ) {
					return array( 'echo' => $input );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		$inputs = array(
			'null'     => null,
			'true'     => true,
			'false'    => false,
			'int'      => 123,
			'float'    => 123.456,
			'string'   => 'test',
			'empty'    => '',
			'zero'     => 0,
			'negative' => -1,
		);

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/echo/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => $inputs ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $inputs, $data['echo'] );
	}

	/**
	 * Test input with mixed encoding.
	 *
	 * @ticket 64098
	 */
	public function test_mixed_encoding_in_input(): void {
		// Register ability that accepts any input
		$this->register_test_ability(
			'test/echo-encoding',
			array(
				'label'               => 'Echo Encoding',
				'description'         => 'Echoes input with encoding',
				'category'            => 'general',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => static function ( $input ) {
					return array( 'echo' => $input );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		$input = array(
			'utf8'     => 'Hello 世界',
			'emoji'    => '🎉🎊🎈',
			'html'     => '<script>alert("xss")</script>',
			'encoded'  => '&lt;test&gt;',
			'newlines' => "line1\nline2\rline3\r\nline4",
			'tabs'     => "col1\tcol2\tcol3",
			'quotes'   => "It's \"quoted\"",
		);

		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/test/echo-encoding/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => $input ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		// Input should be preserved exactly
		$this->assertSame( $input['utf8'], $data['echo']['utf8'] );
		$this->assertSame( $input['emoji'], $data['echo']['emoji'] );
		$this->assertSame( $input['html'], $data['echo']['html'] );
	}

	/**
	 * Data provider for invalid HTTP methods.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_invalid_http_methods_provider(): array {
		return array(
			'PATCH'  => array( 'PATCH' ),
			'PUT'    => array( 'PUT' ),
			'DELETE' => array( 'DELETE' ),
			'HEAD'   => array( 'HEAD' ),
		);
	}

	/**
	 * Test request with invalid HTTP methods.
	 *
	 * @ticket 64098
	 *
	 * @dataProvider data_invalid_http_methods_provider
	 *
	 * @param string $method HTTP method to test.
	 */
	public function test_invalid_http_methods( string $method ): void {
		// Register an ability with no permission requirements for this test
		$this->register_test_ability(
			'test/method-test',
			array(
				'label'               => 'Method Test',
				'description'         => 'Test ability for HTTP method validation',
				'category'            => 'general',
				'execute_callback'    => static function () {
					return array( 'success' => true );
				},
				'permission_callback' => '__return_true', // No permission requirements
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		$request  = new WP_REST_Request( $method, '/wp-abilities/v1/abilities/test/method-test/run' );
		$response = $this->server->dispatch( $request );

		// Regular abilities should only accept POST, so these should return 405.
		$this->assertSame( 405, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_ability_invalid_method', $data['code'] );
		$this->assertSame( 'Abilities that perform updates require POST method.', $data['message'] );
	}

	/**
	 * Test OPTIONS method handling.
	 *
	 * @ticket 64098
	 */
	public function test_options_method_handling(): void {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp-abilities/v1/abilities/test/calculator/run' );
		$response = $this->server->dispatch( $request );
		// OPTIONS requests return 200 with allowed methods
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Registers an ability that reflects the received input value back to the caller.
	 *
	 * The execute callback returns the received input under `value`, so a test can assert
	 * the exact value and PHP type the ability received, whether it is a scalar, an array, or an
	 * object of fields.
	 *
	 * @param string $name         Ability name.
	 * @param array  $input_schema Input schema for the ability.
	 * @param array  $annotations  Optional. Ability annotations. Defaults to a read-only ability
	 *                             (GET). Pass an empty array for a POST ability.
	 */
	private function register_reflecting_ability( string $name, array $input_schema, array $annotations = array( 'readonly' => true ) ): void {
		$this->register_test_ability(
			$name,
			array(
				'label'               => 'Reflecting Ability',
				'description'         => 'Reflects the received input value.',
				'category'            => 'general',
				'input_schema'        => $input_schema,
				'execute_callback'    => static function ( $input ) {
					return array( 'value' => $input );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'annotations'  => $annotations,
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Dispatches a run request for an ability, nesting the value under the `input` parameter.
	 *
	 * GET and DELETE send the input as query parameters, POST sends it as a JSON body. Pass
	 * `null` to send no input at all.
	 *
	 * @param string $method HTTP method.
	 * @param string $name   Ability name.
	 * @param mixed  $input  Optional. Input value, or null to send no input. Default null.
	 * @return WP_REST_Response The dispatched response.
	 */
	private function dispatch_run( string $method, string $name, $input = null ) {
		$request = new WP_REST_Request( $method, "/wp-abilities/v1/abilities/{$name}/run" );

		if ( null !== $input ) {
			if ( in_array( $method, array( 'GET', 'DELETE' ), true ) ) {
				$request->set_query_params( array( 'input' => $input ) );
			} else {
				$request->set_header( 'Content-Type', 'application/json' );
				$request->set_body( wp_json_encode( array( 'input' => $input ) ) );
			}
		}

		return $this->server->dispatch( $request );
	}

	/**
	 * Provides query-string and JSON-body transports for the run endpoint.
	 *
	 * @return array
	 */
	public static function data_coercion_transports(): array {
		return array(
			'GET'    => array( 'GET', array( 'readonly' => true ) ),
			'POST'   => array( 'POST', array() ),
			'DELETE' => array(
				'DELETE',
				array(
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		);
	}

	/**
	 * Tests that REST transports deliver schema-coerced input to the ability.
	 *
	 * @ticket 65594
	 *
	 * @dataProvider data_coercion_transports
	 *
	 * @param string $method      HTTP method.
	 * @param array  $annotations Ability annotations selecting the method.
	 */
	public function test_input_is_coerced_for_transport( string $method, array $annotations ): void {
		$this->register_reflecting_ability(
			'test/coerced-input',
			array(
				'type'       => 'object',
				'properties' => array(
					'count' => array( 'type' => 'integer' ),
					'flag'  => array( 'type' => 'boolean' ),
					'ids'   => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
			),
			$annotations
		);

		$response = $this->dispatch_run(
			$method,
			'test/coerced-input',
			array(
				'count' => '10',
				'flag'  => 'false',
				'ids'   => '1,2',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'count' => 10,
				'flag'  => false,
				'ids'   => array( 1, 2 ),
			),
			$response->get_data()['value']
		);
	}

	/**
	 * Provides transports and input sources for input that fails coercion.
	 *
	 * @return array
	 */
	public static function data_coercion_error_inputs(): array {
		return array(
			'query input'      => array( 'GET', 'request' ),
			'JSON input'       => array( 'POST', 'request' ),
			'schema default'   => array( 'POST', 'default' ),
			'normalizer input' => array( 'POST', 'filter' ),
		);
	}

	/**
	 * Tests that coercion failures use existing REST error handling before permission checks.
	 *
	 * @ticket 66383
	 * @dataProvider data_coercion_error_inputs
	 *
	 * @param string $method HTTP method.
	 * @param string $source Source of the input that fails coercion.
	 */
	public function test_coercion_error_returns_400( string $method, string $source ): void {
		$input  = array( '1', '01' );
		$schema = array(
			'type'        => 'array',
			'items'       => array( 'type' => 'integer' ),
			'uniqueItems' => true,
		);
		if ( 'default' === $source ) {
			$schema['default'] = $input;
		}
		if ( 'filter' === $source ) {
			add_filter(
				'wp_ability_normalize_input',
				static function () use ( $input ) {
					return $input;
				}
			);
		}

		$unexpected_callback = function () {
			$this->fail( 'Coercion errors must stop permission checks and execution.' );
		};
		$this->register_test_ability(
			'test/coercion-error',
			array(
				'label'               => 'Coercion Error',
				'description'         => 'Rejects inputs that cannot be coerced.',
				'category'            => 'general',
				'input_schema'        => $schema,
				'permission_callback' => $unexpected_callback,
				'execute_callback'    => $unexpected_callback,
				'meta'                => array(
					'annotations'  => array( 'readonly' => 'GET' === $method ),
					'show_in_rest' => true,
				),
			)
		);

		$response = $this->dispatch_run( $method, 'test/coercion-error', 'request' === $source ? $input : null );
		$this->assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		if ( 'request' === $source ) {
			$this->assertSame( 'rest_invalid_param', $data['code'] );
			$this->assertSame( 'ability_invalid_input', $data['data']['details']['input']['code'] );
		} else {
			$this->assertSame( 'ability_invalid_input', $data['code'] );
		}
	}

	/**
	 * Data provider for input that coercion must not rescue.
	 *
	 * @return array<string, array{0: array, 1: array}> Schema and the invalid raw input.
	 */
	public static function data_invalid_input_returns_400(): array {
		$schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'count' => array( 'type' => 'integer' ),
			),
			'additionalProperties' => false,
		);

		return array(
			// An unknown property must still be rejected, not silently stripped.
			'unknown property under additionalProperties:false' => array(
				$schema,
				array(
					'count'   => '10',
					'unknown' => 'x',
				),
			),
			// A non-numeric string must still be rejected, not coerced to 0.
			'non-numeric string for an integer' => array(
				$schema,
				array( 'count' => 'not-a-number' ),
			),
		);
	}

	/**
	 * Tests that coercion never rescues otherwise-invalid input.
	 *
	 * Sanitizing invalid input could silently change what is accepted, so coercion runs only on
	 * input that already validates. Invalid input therefore still returns the authoritative
	 * `ability_invalid_input` (400) from validation.
	 *
	 * @ticket 65594
	 *
	 * @dataProvider data_invalid_input_returns_400
	 *
	 * @param array $schema Ability input schema.
	 * @param array $input  Invalid raw input that must not be coerced.
	 */
	public function test_invalid_input_returns_400( array $schema, array $input ): void {
		$this->register_reflecting_ability( 'test/strict-typed-input', $schema );

		$response = $this->dispatch_run( 'GET', 'test/strict-typed-input', $input );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'ability_invalid_input', $response->get_data()['code'] );
	}

	/**
	 * Tests that `validate_input()` decides what gets coerced, filters included.
	 *
	 * Coercion gates on `validate_input()`, not the raw schema, so a `wp_ability_validate_input`
	 * filter that accepts input the schema rejects makes that input coerced too.
	 *
	 * @ticket 65594
	 */
	public function test_validate_input_filter_override_still_coerces(): void {
		$this->register_reflecting_ability(
			'test/filtered-typed-input',
			array(
				'type'       => 'object',
				'properties' => array(
					'count' => array(
						'type'    => 'integer',
						'minimum' => 100,
					),
				),
			)
		);

		// Below `minimum`, so the schema rejects it.
		$input = array( 'count' => '10' );

		$response = $this->dispatch_run( 'GET', 'test/filtered-typed-input', $input );
		$this->assertSame( 400, $response->get_status(), 'Schema validation should reject the input on its own.' );
		$this->assertSame( 'ability_invalid_input', $response->get_data()['code'] );

		// A filter that accepts the input makes it valid, so it must also be coerced.
		add_filter( 'wp_ability_validate_input', '__return_true' );

		$response = $this->dispatch_run( 'GET', 'test/filtered-typed-input', $input );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'count' => 10 ), $response->get_data()['value'], 'Input a validation filter accepts should be coerced.' );
	}

	/**
	 * Tests that already-typed POST input is unchanged by coercion.
	 *
	 * JSON delivers native types, so coercion is a near no-op for POST. Applying it uniformly
	 * keeps GET and POST consistent without altering already-typed values.
	 *
	 * @ticket 65594
	 */
	public function test_post_typed_input_is_unchanged(): void {
		// An empty annotations array registers a POST (non-read-only) ability.
		$this->register_reflecting_ability(
			'test/typed-input-post',
			array(
				'type'       => 'object',
				'properties' => array(
					'count' => array( 'type' => 'integer' ),
					'flag'  => array( 'type' => 'boolean' ),
					'tags'  => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
			),
			array()
		);

		$response = $this->dispatch_run(
			'POST',
			'test/typed-input-post',
			array(
				'count' => 10,
				'flag'  => true,
				'tags'  => array( 'a', 'b' ),
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'count' => 10,
				'flag'  => true,
				'tags'  => array( 'a', 'b' ),
			),
			$response->get_data()['value']
		);
	}

	/**
	 * Tests that an ability without an input schema is unaffected by coercion.
	 *
	 * @ticket 65594
	 */
	public function test_input_without_schema_passes_through(): void {
		$this->register_test_ability(
			'test/no-input-schema',
			array(
				'label'               => 'No Input Schema',
				'description'         => 'Executes without an input schema.',
				'category'            => 'general',
				'execute_callback'    => static function () {
					return array( 'ran' => true );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'annotations'  => array( 'readonly' => true ),
					'show_in_rest' => true,
				),
			)
		);

		$response = $this->dispatch_run( 'GET', 'test/no-input-schema' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['ran'] );
	}

	/**
	 * Provides explicit input and an omitted input using the schema default.
	 *
	 * @return array
	 */
	public static function data_permission_callback_input(): array {
		return array(
			'explicit input' => array( array( 'count' => '10' ) ),
			'default input'  => array( null ),
		);
	}

	/**
	 * Tests that the permission callback receives natively typed input.
	 *
	 * Coercion runs before `check_ability_permissions()`, so an ability that inspects its input
	 * during the permission check sees the coerced integer, not the raw "10" string the GET query
	 * string delivered. The gate runs first, so the first recorded type is the one it acted on.
	 *
	 * @ticket 65594
	 * @dataProvider data_permission_callback_input
	 *
	 * @param array|null $input Request input, or null to use the schema default.
	 */
	public function test_permission_callback_receives_typed_input( $input ): void {
		$seen        = new stdClass();
		$seen->types = array();

		$this->register_test_ability(
			'test/permission-typed-input',
			array(
				'label'               => 'Permission Typed Input',
				'description'         => 'Records the type the permission callback receives.',
				'category'            => 'general',
				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array( 'count' => '10' ),
					'properties' => array(
						'count' => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => static function ( $input ) use ( $seen ) {
					$seen->types[] = gettype( $input['count'] );
					return true;
				},
				'execute_callback'    => static function ( $input ) {
					return $input;
				},
				'meta'                => array(
					'annotations'  => array( 'readonly' => true ),
					'show_in_rest' => true,
				),
			)
		);

		$response = $this->dispatch_run( 'GET', 'test/permission-typed-input', $input );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $seen->types, 'The permission callback should have run.' );
		$this->assertSame( 'integer', $seen->types[0], 'The REST permission gate should receive coerced (typed) input, not a raw string.' );
		$this->assertSame( array( 'count' => 10 ), $response->get_data(), 'The execute callback should receive coerced input too.' );
	}
}
