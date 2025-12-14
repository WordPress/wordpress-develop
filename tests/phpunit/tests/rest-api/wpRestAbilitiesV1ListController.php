<?php declare( strict_types=1 );

/**
 * Tests for the REST list controller for abilities endpoint.
 *
 * @covers WP_REST_Abilities_V1_List_Controller
 *
 * @group abilities-api
 * @group rest-api
 */
class Tests_REST_API_WpRestAbilitiesV1ListController extends WP_UnitTestCase {

	/**
	 * REST Server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// Create a test user with read capabilities
		self::$user_id = self::factory()->user->create(
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

		// Set up REST server
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

		// Reset REST server
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Register test categories for testing.
	 */
	public static function register_test_categories(): void {
		// Simulates the init hook to allow test ability categories registration.
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
		// Register a regular ability.
		$this->register_test_ability(
			'test/calculator',
			array(
				'label'               => 'Calculator',
				'description'         => 'Performs basic calculations',
				'category'            => 'math',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type' => 'string',
							'enum' => array( 'add', 'subtract', 'multiply', 'divide' ),
						),
						'a'         => array( 'type' => 'number' ),
						'b'         => array( 'type' => 'number' ),
					),
				),
				'output_schema'       => array(
					'type' => 'number',
				),
				'execute_callback'    => static function ( array $input ) {
					switch ( $input['operation'] ) {
						case 'add':
							return $input['a'] + $input['b'];
						case 'subtract':
							return $input['a'] - $input['b'];
						case 'multiply':
							return $input['a'] * $input['b'];
						case 'divide':
							return 0 !== $input['b'] ? $input['a'] / $input['b'] : null;
						default:
							return null;
					}
				},
				'permission_callback' => static function () {
					return current_user_can( 'read' );
				},
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Register a read-only ability.
		$this->register_test_ability(
			'test/system-info',
			array(
				'label'               => 'System Info',
				'description'         => 'Returns system information',
				'category'            => 'system',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'detail_level' => array(
							'type'    => 'string',
							'enum'    => array( 'basic', 'full' ),
							'default' => 'basic',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'php_version' => array( 'type' => 'string' ),
						'wp_version'  => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => static function ( array $input ) {
					$info = array(
						'php_version' => phpversion(),
						'wp_version'  => get_bloginfo( 'version' ),
					);
					if ( 'full' === ( $input['detail_level'] ?? 'basic' ) ) {
						$info['memory_limit'] = ini_get( 'memory_limit' );
					}
					return $info;
				},
				'permission_callback' => static function () {
					return current_user_can( 'read' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly' => true,
					),
					'category'     => 'system',
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

		// Register multiple abilities for pagination testing
		for ( $i = 1; $i <= 60; $i++ ) {
			$this->register_test_ability(
				"test/ability-{$i}",
				array(
					'label'               => "Test Ability {$i}",
					'description'         => "Test ability number {$i}",
					'category'            => 'general',
					'execute_callback'    => static function () use ( $i ) {
						return "Result from ability {$i}";
					},
					'permission_callback' => '__return_true',
					'meta'                => array(
						'show_in_rest' => true,
					),
				)
			);
		}
	}

	/**
	 * Test listing all abilities.
	 *
	 * @ticket 64098
	 */
	public function test_get_items(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		$this->assertCount( 50, $data, 'First page should return exactly 50 items (default per_page)' );

		$ability_names = wp_list_pluck( $data, 'name' );
		$this->assertContains( 'test/calculator', $ability_names );
		$this->assertContains( 'test/system-info', $ability_names );
		$this->assertNotContains( 'test/not-show-in-rest', $ability_names );
	}

	/**
	 * Test getting a specific ability.
	 *
	 * @ticket 64098
	 */
	public function test_get_item(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/calculator' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 7, $data, 'Response should contain all fields.' );
		$this->assertSame( 'test/calculator', $data['name'] );
		$this->assertSame( 'Calculator', $data['label'] );
		$this->assertSame( 'Performs basic calculations', $data['description'] );
		$this->assertSame( 'math', $data['category'] );
		$this->assertArrayHasKey( 'input_schema', $data );
		$this->assertArrayHasKey( 'output_schema', $data );
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertTrue( $data['meta']['show_in_rest'] );
	}

	/**
	 * Test getting a specific ability with only selected fields.
	 *
	 * @ticket 64098
	 */
	public function test_get_item_with_selected_fields(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/calculator' );
		$request->set_param( '_fields', 'name,label' );
		$response = $this->server->dispatch( $request );
		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $this->server, $request );
		remove_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10 );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data, 'Response should only contain the requested fields.' );
		$this->assertSame( 'test/calculator', $data['name'] );
		$this->assertSame( 'Calculator', $data['label'] );
	}

	/**
	 * Test getting a specific ability with embed context.
	 *
	 * @ticket 64098
	 */
	public function test_get_item_with_embed_context(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/calculator' );
		$request->set_param( 'context', 'embed' );
		$response = $this->server->dispatch( $request );
		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $this->server, $request );
		remove_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10 );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 3, $data, 'Response should only contain the fields for embed context.' );
		$this->assertSame( 'test/calculator', $data['name'] );
		$this->assertSame( 'Calculator', $data['label'] );
		$this->assertSame( 'math', $data['category'] );
	}

	/**
	 * Test getting a non-existent ability returns 404.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::get_registered
	 */
	public function test_get_item_not_found(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/non/existent' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'rest_ability_not_found', $data['code'] );
	}

	/**
	 * Test getting an ability that does not show in REST returns 404.
	 *
	 * @ticket 64098
	 */
	public function test_get_item_not_show_in_rest(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/not-show-in-rest' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'rest_ability_not_found', $data['code'] );
	}

	/**
	 * Test permission check for listing abilities.
	 *
	 * @ticket 64098
	 */
	public function test_get_items_permission_denied(): void {
		// Test with non-logged-in user
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test pagination headers.
	 *
	 * @ticket 64098
	 */
	public function test_pagination_headers(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'per_page', 10 );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );

		$total_abilities = count( wp_get_abilities() ) - 1; // Exclude the one that doesn't show in REST.
		$this->assertEquals( $total_abilities, (int) $headers['X-WP-Total'] );
		$this->assertEquals( ceil( $total_abilities / 10 ), (int) $headers['X-WP-TotalPages'] );
	}

	/**
	 * Test HEAD method returns empty body with proper headers.
	 *
	 * @ticket 64098
	 */
	public function test_head_request(): void {
		$request  = new WP_REST_Request( 'HEAD', '/wp-abilities/v1/abilities' );
		$response = $this->server->dispatch( $request );

		// Verify empty response body
		$data = $response->get_data();
		$this->assertEmpty( $data );

		// Verify pagination headers are present
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );
	}

	/**
	 * Test pagination links.
	 *
	 * @ticket 64098
	 */
	public function test_pagination_links(): void {
		// Test first page (should have 'next' link header but no 'prev')
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'per_page', 10 );
		$request->set_param( 'page', 1 );
		$response = $this->server->dispatch( $request );

		$headers     = $response->get_headers();
		$link_header = $headers['Link'] ?? '';

		// Parse Link header for rel="next" and rel="prev"
		$this->assertStringContainsString( 'rel="next"', $link_header );
		$this->assertStringNotContainsString( 'rel="prev"', $link_header );

		// Test middle page (should have both 'next' and 'prev' link headers)
		$request->set_param( 'page', 3 );
		$response = $this->server->dispatch( $request );

		$headers     = $response->get_headers();
		$link_header = $headers['Link'] ?? '';

		$this->assertStringContainsString( 'rel="next"', $link_header );
		$this->assertStringContainsString( 'rel="prev"', $link_header );

		// Test last page (should have 'prev' link header but no 'next')
		$total_abilities = count( wp_get_abilities() );
		$last_page       = ceil( $total_abilities / 10 );
		$request->set_param( 'page', $last_page );
		$response = $this->server->dispatch( $request );

		$headers     = $response->get_headers();
		$link_header = $headers['Link'] ?? '';

		$this->assertStringNotContainsString( 'rel="next"', $link_header );
		$this->assertStringContainsString( 'rel="prev"', $link_header );
	}

	/**
	 * Test collection parameters.
	 *
	 * @ticket 64098
	 */
	public function test_collection_params(): void {
		// Test per_page parameter
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'per_page', 5 );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertCount( 5, $data );

		// Test page parameter
		$request->set_param( 'page', 2 );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 5, $data );

		// Verify we got different abilities on page 2
		$page1_request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$page1_request->set_param( 'per_page', 5 );
		$page1_request->set_param( 'page', 1 );
		$page1_response = $this->server->dispatch( $page1_request );
		$page1_names    = wp_list_pluck( $page1_response->get_data(), 'name' );
		$page2_names    = wp_list_pluck( $data, 'name' );

		$this->assertNotEquals( $page1_names, $page2_names );
	}

	/**
	 * Test response links for individual abilities.
	 *
	 * @ticket 64098
	 */
	public function test_ability_response_links(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/calculator' );
		$response = $this->server->dispatch( $request );

		$links = $response->get_links();
		$this->assertArrayHasKey( 'self', $links );
		$this->assertArrayHasKey( 'collection', $links );
		$this->assertArrayHasKey( 'wp:action-run', $links );

		// Verify link URLs
		$self_link = $links['self'][0]['href'];
		$this->assertStringContainsString( '/wp-abilities/v1/abilities/test/calculator', $self_link );

		$collection_link = $links['collection'][0]['href'];
		$this->assertStringContainsString( '/wp-abilities/v1/abilities', $collection_link );

		$run_link = $links['wp:action-run'][0]['href'];
		$this->assertStringContainsString( '/wp-abilities/v1/abilities/test/calculator/run', $run_link );
	}

	/**
	 * Test context parameter.
	 *
	 * @ticket 64098
	 */
	public function test_context_parameter(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/calculator' );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'description', $data );

		$request->set_param( 'context', 'embed' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'name', $data );
		$this->assertArrayHasKey( 'label', $data );
	}

	/**
	 * Test schema retrieval.
	 *
	 * @ticket 64098
	 */
	public function test_get_schema(): void {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp-abilities/v1/abilities' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'schema', $data );
		$schema = $data['schema'];

		$this->assertSame( 'ability', $schema['title'] );
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );

		$properties = $schema['properties'];

		// Assert the count of properties to catch when new keys are added
		$this->assertCount( 7, $properties, 'Schema should have exactly 7 properties. If this fails, update this test to include the new property.' );

		// Check all expected properties exist
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'label', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'input_schema', $properties );
		$this->assertArrayHasKey( 'output_schema', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'category', $properties );
	}

	/**
	 * Test ability name with valid special characters.
	 *
	 * @ticket 64098
	 */
	public function test_ability_name_with_valid_special_characters(): void {
		// Register ability with hyphen (valid).
		$this->register_test_ability(
			'test-hyphen/ability',
			array(
				'label'               => 'Test Hyphen Ability',
				'description'         => 'Test ability with hyphen',
				'category'            => 'general',
				'execute_callback'    => static function ( $input ) {
					return array( 'success' => true );
				},
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);

		// Test valid special characters (hyphen, forward slash)
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test-hyphen/ability' );
		$response = $this->server->dispatch( $request );

		wp_unregister_ability( 'test-hyphen/ability' );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Data provider for invalid ability names.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_invalid_ability_names_provider(): array {
		return array(
			'@ symbol'          => array( 'test@ability' ),
			'space'             => array( 'test ability' ),
			'dot'               => array( 'test.ability' ),
			'hash'              => array( 'test#ability' ),
			'URL encoded space' => array( 'test%20ability' ),
			'angle brackets'    => array( 'test<ability>' ),
			'pipe'              => array( 'test|ability' ),
			'backslash'         => array( 'test\\ability' ),
		);
	}

	/**
	 * Test ability names with invalid special characters.
	 *
	 * @ticket 64098
	 *
	 * @dataProvider data_invalid_ability_names_provider
	 *
	 * @param string $name Invalid ability name to test.
	 */
	public function test_ability_name_with_invalid_special_characters( string $name ): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . $name );
		$response = $this->server->dispatch( $request );
		// Should return 404 as the regex pattern won't match
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test extremely long ability names.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::get_registered
	 */
	public function test_extremely_long_ability_names(): void {
		// Create a very long but valid ability name
		$long_name = 'test/' . str_repeat( 'a', 1000 );

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . $long_name );
		$response = $this->server->dispatch( $request );

		// Should return 404 as ability doesn't exist
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Data provider for invalid pagination parameters.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public function data_invalid_pagination_params_provider(): array {
		return array(
			'Zero page'            => array( array( 'page' => 0 ) ),
			'Negative page'        => array( array( 'page' => -1 ) ),
			'Non-numeric page'     => array( array( 'page' => 'abc' ) ),
			'Zero per page'        => array( array( 'per_page' => 0 ) ),
			'Negative per page'    => array( array( 'per_page' => -10 ) ),
			'Exceeds maximum'      => array( array( 'per_page' => 1000 ) ),
			'Non-numeric per page' => array( array( 'per_page' => 'all' ) ),
		);
	}

	/**
	 * Test pagination parameters with invalid values.
	 *
	 * @ticket 64098
	 *
	 * @dataProvider data_invalid_pagination_params_provider
	 *
	 * @param array<string, mixed> $params Invalid pagination parameters.
	 */
	public function test_invalid_pagination_parameters( array $params ): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_query_params( $params );

		$response = $this->server->dispatch( $request );

		// Should either use defaults or return error
		$this->assertContains( $response->get_status(), array( 200, 400 ) );

		if ( $response->get_status() !== 200 ) {
			return;
		}

		// Check that reasonable defaults were used
		$data = $response->get_data();
		$this->assertIsArray( $data );
	}

	/**
	 * Test filtering abilities by category.
	 *
	 * @ticket 64098
	 */
	public function test_filter_by_category(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'category', 'math' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );

		// Should only have math category abilities
		foreach ( $data as $ability ) {
			$this->assertSame( 'math', $ability['category'], 'All abilities should be in math category' );
		}

		// Should at least contain the calculator
		$ability_names = wp_list_pluck( $data, 'name' );
		$this->assertContains( 'test/calculator', $ability_names );
		$this->assertNotContains( 'test/system-info', $ability_names, 'System info should not be in math category' );
	}

	/**
	 * Test filtering by non-existent category returns empty results.
	 *
	 * @ticket 64098
	 */
	public function test_filter_by_nonexistent_category(): void {
		// Ensure category doesn't exist - test should fail if it does.
		$this->assertFalse(
			wp_has_ability_category( 'nonexistent' ),
			'The nonexistent category should not be registered - test isolation may be broken'
		);

		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'category', 'nonexistent' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertEmpty( $data, 'Should return empty array for non-existent category' );
	}
}
