<?php declare( strict_types=1 );

/**
 * Tests for the REST run controller for abilities endpoint.
 *
 * @covers WP_REST_Abilities_Run_Controller
 * @group abilities-api
 * @group rest-api
 */
class Tests_REST_API_WpRestAbilitiesRunController extends WP_UnitTestCase {

	/**
	 * REST Server instance.
	 *
	 * @var \WP_REST_Server
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

		do_action( 'abilities_api_init' );

		$this->register_test_abilities();

		// Set default user for tests
		wp_set_current_user( self::$user_id );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
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
	 * Register test abilities for testing.
	 */
	private function register_test_abilities(): void {
		// Tool ability (POST only)
		wp_register_ability(
			'test/calculator',
			array(
				'label'               => 'Calculator',
				'description'         => 'Performs calculations',
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
					'type' => 'tool',
				),
			)
		);

		// Resource ability (GET only)
		wp_register_ability(
			'test/user-info',
			array(
				'label'               => 'User Info',
				'description'         => 'Gets user information',
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
						return new \WP_Error( 'user_not_found', 'User not found' );
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
					'type' => 'resource',
				),
			)
		);

		// Ability with contextual permissions
		wp_register_ability(
			'test/restricted',
			array(
				'label'               => 'Restricted Action',
				'description'         => 'Requires specific input for permission',
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
					'type' => 'tool',
				),
			)
		);

		// Ability that returns null
		wp_register_ability(
			'test/null-return',
			array(
				'label'               => 'Null Return',
				'description'         => 'Returns null',
				'execute_callback'    => static function () {
					return null;
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'type' => 'tool',
				),
			)
		);

		// Ability that returns WP_Error
		wp_register_ability(
			'test/error-return',
			array(
				'label'               => 'Error Return',
				'description'         => 'Returns error',
				'execute_callback'    => static function () {
					return new \WP_Error( 'test_error', 'This is a test error' );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'type' => 'tool',
				),
			)
		);

		// Ability with invalid output
		wp_register_ability(
			'test/invalid-output',
			array(
				'label'               => 'Invalid Output',
				'description'         => 'Returns invalid output',
				'output_schema'       => array(
					'type' => 'number',
				),
				'execute_callback'    => static function () {
					return 'not a number'; // Invalid - schema expects number
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'type' => 'tool',
				),
			)
		);

		// Resource ability for query params testing
		wp_register_ability(
			'test/query-params',
			array(
				'label'               => 'Query Params Test',
				'description'         => 'Tests query parameter handling',
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
					'type' => 'resource',
				),
			)
		);
	}

	/**
	 * Test executing a tool ability with POST.
	 */
	public function test_execute_tool_ability_post(): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/calculator/run' );
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

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 8, $response->get_data() );
	}

	/**
	 * Test executing a resource ability with GET.
	 */
	public function test_execute_resource_ability_get(): void {
		$request = new WP_REST_Request( 'GET', '/wp/v2/abilities/test/user-info/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'user_id' => self::$user_id,
				),
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( self::$user_id, $data['id'] );
	}

	/**
	 * Test HTTP method validation for tool abilities.
	 */
	public function test_tool_ability_requires_post(): void {
		wp_register_ability(
			'test/open-tool',
			array(
				'label'               => 'Open Tool',
				'description'         => 'Tool with no permission requirements',
				'execute_callback'    => static function () {
					return 'success';
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'type' => 'tool',
				),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/abilities/test/open-tool/run' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_ability_invalid_method', $data['code'] );
		$this->assertSame( 'Tool abilities require POST method.', $data['message'] );
	}

	/**
	 * Test HTTP method validation for resource abilities.
	 */
	public function test_resource_ability_requires_get(): void {
		// Try POST on a resource ability (should fail)
		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/user-info/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'user_id' => 1 ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_ability_invalid_method', $data['code'] );
		$this->assertSame( 'Resource abilities require GET method.', $data['message'] );
	}


	/**
	 * Test output validation against schema.
	 * Note: When output validation fails in WP_Ability::execute(), it returns null,
	 * which causes the REST controller to return 'ability_invalid_output'.
	 */
	public function test_output_validation(): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/invalid-output/run' );
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
	 * Test permission check for execution.
	 */
	public function test_execution_permission_denied(): void {
		wp_set_current_user( self::$no_permission_user_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/calculator/run' );
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
	 */
	public function test_contextual_permission_check(): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/restricted/run' );
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
		$this->assertEquals( 403, $response->get_status() );

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
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'Success: test data', $response->get_data() );
	}

	/**
	 * Test handling of null is a valid return value.
	 */
	public function test_null_return_handling(): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/null-return/run' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertNull( $data );
	}

	/**
	 * Test handling of WP_Error return from ability.
	 */
	public function test_wp_error_return_handling(): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/error-return/run' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 500, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'test_error', $data['code'] );
		$this->assertEquals( 'This is a test error', $data['message'] );
	}

	/**
	 * Test non-existent ability returns 404.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::get_registered
	 */
	public function test_execute_non_existent_ability(): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/non/existent/run' );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'rest_ability_not_found', $data['code'] );
	}

	/**
	 * Test schema retrieval for run endpoint.
	 */
	public function test_run_endpoint_schema(): void {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/abilities/test/calculator/run' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'schema', $data );
		$schema = $data['schema'];

		$this->assertEquals( 'ability-execution', $schema['title'] );
		$this->assertEquals( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'result', $schema['properties'] );
	}

	/**
	 * Test that invalid JSON in POST body is handled correctly.
	 */
	public function test_invalid_json_in_post_body(): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		// Set raw body with invalid JSON
		$request->set_body( '{"input": {invalid json}' );

		$response = $this->server->dispatch( $request );

		// When JSON is invalid, WordPress returns 400 Bad Request
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test GET request with complex nested input array.
	 */
	public function test_get_request_with_nested_input_array(): void {
		$request = new WP_REST_Request( 'GET', '/wp/v2/abilities/test/query-params/run' );
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
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'nested', $data['level1']['level2']['value'] );
		$this->assertEquals( array( 1, 2, 3 ), $data['array'] );
	}

	/**
	 * Test GET request with non-array input parameter.
	 */
	public function test_get_request_with_non_array_input(): void {
		$request = new WP_REST_Request( 'GET', '/wp/v2/abilities/test/query-params/run' );
		$request->set_query_params(
			array(
				'input' => 'not-an-array', // String instead of array
			)
		);

		$response = $this->server->dispatch( $request );
		// When input is not an array, WordPress returns 400 Bad Request
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test POST request with non-array input in JSON body.
	 */
	public function test_post_request_with_non_array_input(): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/calculator/run' );
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
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test ability with invalid output that fails validation.
	 */
	public function test_output_validation_failure_returns_error(): void {
		// Register ability with strict output schema.
		wp_register_ability(
			'test/strict-output',
			array(
				'label'               => 'Strict Output',
				'description'         => 'Ability with strict output schema',
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
				'meta'                => array( 'type' => 'tool' ),
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/strict-output/run' );
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
	 */
	public function test_input_validation_failure_returns_error(): void {
		// Register ability with strict input schema.
		wp_register_ability(
			'test/strict-input',
			array(
				'label'               => 'Strict Input',
				'description'         => 'Ability with strict input schema',
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
				'meta'                => array( 'type' => 'tool' ),
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/strict-input/run' );
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
	 * Test ability type not set defaults to tool.
	 */
	public function test_ability_without_type_defaults_to_tool(): void {
		// Register ability without type in meta.
		wp_register_ability(
			'test/no-type',
			array(
				'label'               => 'No Type',
				'description'         => 'Ability without type',
				'execute_callback'    => static function () {
					return array( 'executed' => true );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(), // No type specified
			)
		);

		// Should require POST (default tool behavior)
		$get_request  = new WP_REST_Request( 'GET', '/wp/v2/abilities/test/no-type/run' );
		$get_response = $this->server->dispatch( $get_request );
		$this->assertEquals( 405, $get_response->get_status() );

		// Should work with POST
		$post_request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/no-type/run' );
		$post_request->set_header( 'Content-Type', 'application/json' );

		$post_response = $this->server->dispatch( $post_request );
		$this->assertEquals( 200, $post_response->get_status() );
	}

	/**
	 * Test edge case with empty input for both GET and POST.
	 */
	public function test_empty_input_handling(): void {
		// Registers abilities for empty input testing.
		wp_register_ability(
			'test/resource-empty',
			array(
				'label'               => 'Resource Empty',
				'description'         => 'Resource with empty input',
				'execute_callback'    => static function () {
					return array( 'input_was_empty' => 0 === func_num_args() );
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'type' => 'resource' ),
			)
		);

		wp_register_ability(
			'test/tool-empty',
			array(
				'label'               => 'Tool Empty',
				'description'         => 'Tool with empty input',
				'execute_callback'    => static function () {
					return array( 'input_was_empty' => 0 === func_num_args() );
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'type' => 'tool' ),
			)
		);

		// Tests GET with no input parameter.
		$get_request  = new WP_REST_Request( 'GET', '/wp/v2/abilities/test/resource-empty/run' );
		$get_response = $this->server->dispatch( $get_request );
		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertTrue( $get_response->get_data()['input_was_empty'] );

		// Tests POST with no body.
		$post_request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/tool-empty/run' );
		$post_request->set_header( 'Content-Type', 'application/json' );
		$post_request->set_body( '{}' ); // Empty JSON object

		$post_response = $this->server->dispatch( $post_request );
		$this->assertEquals( 200, $post_response->get_status() );
		$this->assertTrue( $post_response->get_data()['input_was_empty'] );
	}

	/**
	 * Data provider for malformed JSON tests.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_malformed_json_provider(): array {
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
	 * @dataProvider data_malformed_json_provider
	 * @param string $json Malformed JSON to test.
	 */
	public function test_malformed_json_post_body( string $json ): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/calculator/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $json );

		$response = $this->server->dispatch( $request );

		// Malformed JSON should result in 400 Bad Request
		$this->assertEquals( 400, $response->get_status() );
	}


	/**
	 * Test input with various PHP types as strings.
	 */
	public function test_php_type_strings_in_input(): void {
		// Register ability that accepts any input
		wp_register_ability(
			'test/echo',
			array(
				'label'               => 'Echo',
				'description'         => 'Echoes input',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => static function ( $input ) {
					return array( 'echo' => $input );
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'type' => 'tool' ),
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

		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/echo/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => $inputs ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $inputs, $data['echo'] );
	}

	/**
	 * Test input with mixed encoding.
	 */
	public function test_mixed_encoding_in_input(): void {
		// Register ability that accepts any input
		wp_register_ability(
			'test/echo-encoding',
			array(
				'label'               => 'Echo Encoding',
				'description'         => 'Echoes input with encoding',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => static function ( $input ) {
					return array( 'echo' => $input );
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'type' => 'tool' ),
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

		$request = new WP_REST_Request( 'POST', '/wp/v2/abilities/test/echo-encoding/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => $input ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		// Input should be preserved exactly
		$this->assertEquals( $input['utf8'], $data['echo']['utf8'] );
		$this->assertEquals( $input['emoji'], $data['echo']['emoji'] );
		$this->assertEquals( $input['html'], $data['echo']['html'] );
	}

	/**
	 * Data provider for invalid HTTP methods.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_invalid_http_methods_provider(): array {
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
	 * @dataProvider data_invalid_http_methods_provider
	 * @param string $method HTTP method to test.
	 */
	public function test_invalid_http_methods( string $method ): void {
		// Register an ability with no permission requirements for this test
		wp_register_ability(
			'test/method-test',
			array(
				'label'               => 'Method Test',
				'description'         => 'Test ability for HTTP method validation',
				'execute_callback'    => static function () {
					return array( 'success' => true );
				},
				'permission_callback' => '__return_true', // No permission requirements
				'meta'                => array( 'type' => 'tool' ),
			)
		);

		$request  = new WP_REST_Request( $method, '/wp/v2/abilities/test/method-test/run' );
		$response = $this->server->dispatch( $request );

		// Tool abilities should only accept POST, so these should return 405.
		$this->assertSame( 405, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'rest_ability_invalid_method', $data['code'] );
		$this->assertSame( 'Tool abilities require POST method.', $data['message'] );
	}

	/**
	 * Test OPTIONS method handling.
	 */
	public function test_options_method_handling(): void {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/abilities/test/calculator/run' );
		$response = $this->server->dispatch( $request );
		// OPTIONS requests return 200 with allowed methods
		$this->assertEquals( 200, $response->get_status() );
	}
}
