<?php declare( strict_types=1 );

/**
 * Tests for the REST list controller for abilities endpoint.
 *
 * @covers WP_REST_Abilities_V1_List_Controller
 *
 * @group abilities-api
 * @group restapi
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
	 * Helper to register an ability with a custom boolean meta key.
	 *
	 * The `featured` key stands in for any plugin-defined meta. It is not part
	 * of the well-defined annotations, so the meta schema does not declare its
	 * type by default.
	 */
	private function register_featured_ability(): void {
		$this->register_test_ability(
			'test/featured',
			array(
				'label'               => 'Featured',
				'description'         => 'Declares a custom boolean meta value.',
				'category'            => 'general',
				'execute_callback'    => '__return_true',
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
					'featured'     => true,
				),
			)
		);
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

	/**
	 * Test filtering abilities by namespace.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_namespace(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'namespace', 'test' );
		$request->set_param( 'per_page', 100 );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$names = wp_list_pluck( $response->get_data(), 'name' );

		$this->assertNotEmpty( $names, 'Expected at least one ability in the test namespace.' );
		foreach ( $names as $name ) {
			$this->assertStringStartsWith( 'test/', $name );
		}
	}

	/**
	 * Test filtering by non-existent namespace returns empty results.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_nonexistent_namespace(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'namespace', 'nonexistent' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertEmpty( $response->get_data() );
	}

	/**
	 * Test that filtering by namespace still excludes abilities without show_in_rest.
	 *
	 * The 'test/not-show-in-rest' fixture matches the 'test' namespace but is
	 * registered without `show_in_rest => true`, so it must remain excluded.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_namespace_still_respects_show_in_rest(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'namespace', 'test' );
		$request->set_param( 'per_page', 100 );
		$response = $this->server->dispatch( $request );

		$names = wp_list_pluck( $response->get_data(), 'name' );
		$this->assertNotContains( 'test/not-show-in-rest', $names );
	}

	/**
	 * Test filtering abilities by a well-defined behavioral annotation.
	 *
	 * The 'test/system-info' fixture is the only ability marked read only. The
	 * value is passed as a string, the way it arrives over the query string, so
	 * this also confirms the meta schema coerces it to a boolean before matching.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_annotation(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'meta', array( 'annotations' => array( 'readonly' => 'true' ) ) );
		$request->set_param( 'per_page', 100 );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$names = wp_list_pluck( $response->get_data(), 'name' );

		$this->assertContains( 'test/system-info', $names );
		$this->assertNotContains( 'test/calculator', $names, 'Abilities not marked read only should be excluded.' );
	}

	/**
	 * Test that a non-matching annotation returns empty results.
	 *
	 * No fixture marks itself destructive, so the result set is empty.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_non_matching_annotation(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'meta', array( 'annotations' => array( 'destructive' => true ) ) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertEmpty( $response->get_data() );
	}

	/**
	 * Test filtering abilities by several meta conditions at once.
	 *
	 * All conditions must match (AND logic).
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_multiple_meta_conditions(): void {
		$this->register_test_ability(
			'test/read-only-idempotent',
			array(
				'label'               => 'Read Only and Idempotent',
				'description'         => 'Marked both read only and idempotent.',
				'category'            => 'general',
				'execute_callback'    => '__return_true',
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);

		$this->register_test_ability(
			'test/read-only-only',
			array(
				'label'               => 'Read Only',
				'description'         => 'Marked read only but not idempotent.',
				'category'            => 'general',
				'execute_callback'    => '__return_true',
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly' => true,
					),
				),
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param(
			'meta',
			array(
				'annotations' => array(
					'readonly'   => 'true',
					'idempotent' => 'true',
				),
			)
		);
		$request->set_param( 'per_page', 100 );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$names = wp_list_pluck( $response->get_data(), 'name' );

		$this->assertContains( 'test/read-only-idempotent', $names, 'An ability matching every condition should be included.' );
		$this->assertNotContains( 'test/read-only-only', $names, 'An ability matching only one condition should be excluded.' );
	}

	/**
	 * Test that a caller cannot use the meta filter to reveal abilities hidden from REST.
	 *
	 * The forced `show_in_rest => true` condition must always win, even when the
	 * caller passes `show_in_rest => false` through the meta parameter.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_meta_cannot_override_show_in_rest(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'meta', array( 'show_in_rest' => false ) );
		$request->set_param( 'per_page', 100 );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$names = wp_list_pluck( $response->get_data(), 'name' );
		$this->assertNotContains( 'test/not-show-in-rest', $names, 'A caller must not reveal hidden abilities through meta.' );
	}

	/**
	 * Test the default behavior for a custom meta key with no declared type.
	 *
	 * Open-ended meta keys arrive over the query string as strings. The meta
	 * schema declares only the well-defined annotations, so a custom key such as
	 * `featured` has no declared type. REST leaves the value "true" as a string,
	 * and the strict meta match never equals the stored boolean. The ability is
	 * excluded.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_custom_meta_without_declared_type_is_not_coerced(): void {
		$this->register_featured_ability();

		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		// The value is passed as a string, the way it arrives over the query string.
		$request->set_param( 'meta', array( 'featured' => 'true' ) );
		$request->set_param( 'per_page', 100 );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$names = wp_list_pluck( $response->get_data(), 'name' );
		$this->assertNotContains( 'test/featured', $names, 'A custom meta key without a declared type should not coerce the query-string value.' );
	}

	/**
	 * Test that a filter can declare a custom meta key's type so its value coerces.
	 *
	 * A plugin can declare the type for its own meta key through the
	 * `rest_abilities_collection_params` filter. REST then coerces the value
	 * "true" to a boolean before matching, so the ability is included. This is
	 * the supported way to make a custom meta key filterable.
	 *
	 * @ticket 64990
	 */
	public function test_filter_can_declare_custom_meta_type_for_coercion(): void {
		$this->register_featured_ability();

		// Declare the type for the custom meta key so REST coerces the value first.
		add_filter(
			'rest_abilities_collection_params',
			static function ( array $query_params ): array {
				$query_params['meta']['properties']['featured'] = array(
					'type' => array( 'boolean', 'null' ),
				);
				return $query_params;
			}
		);

		// Re-register the routes on a fresh server so the collection parameters pick up the filter.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		// The value is passed as a string, the way it arrives over the query string.
		$request->set_param( 'meta', array( 'featured' => 'true' ) );
		$request->set_param( 'per_page', 100 );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$names = wp_list_pluck( $response->get_data(), 'name' );
		$this->assertContains( 'test/featured', $names, 'A declared schema type should coerce the query-string value before matching.' );
	}

	/**
	 * Test that schema keywords outside the allow-list are stripped from ability schemas in REST response.
	 *
	 * @ticket 65035
	 */
	public function test_unsupported_schema_keywords_stripped_from_response(): void {
		$this->register_test_ability(
			'test/with-unsupported-keywords',
			array(
				'label'               => 'Test Unsupported Keywords',
				'description'         => 'Tests stripping of unsupported schema keywords',
				'category'            => 'general',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'content' ),
					'properties' => array(
						'content' => array(
							'type'              => 'string',
							'description'       => 'The content value.',
							'example'           => 'example content',
							'examples'          => array( 'example content' ),
							'context'           => array( 'view', 'edit', 'embed' ),
							'readonly'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'is_string',
							'arg_options'       => array( 'sanitize_callback' => 'wp_kses_post' ),
						),
					),
				),
				'output_schema'       => array(
					'type'              => 'string',
					'example'           => 'example output',
					'examples'          => array( 'example output' ),
					'context'           => array( 'view', 'edit', 'embed' ),
					'readonly'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'is_string',
					'arg_options'       => array( 'sanitize_callback' => 'wp_kses_post' ),
				),
				'execute_callback'    => static function ( $input ) {
					return $input['content'];
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/with-unsupported-keywords' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'input_schema', $data );
		$this->assertArrayHasKey( 'properties', $data['input_schema'] );
		$this->assertArrayHasKey( 'content', $data['input_schema']['properties'] );
		$this->assertArrayHasKey( 'output_schema', $data );

		// Verify unsupported schema keywords are stripped from input_schema properties.
		$content_schema = $data['input_schema']['properties']['content'];
		$this->assertArrayNotHasKey( 'sanitize_callback', $content_schema );
		$this->assertArrayNotHasKey( 'validate_callback', $content_schema );
		$this->assertArrayNotHasKey( 'arg_options', $content_schema );
		$this->assertArrayNotHasKey( 'example', $content_schema );
		$this->assertArrayNotHasKey( 'examples', $content_schema );
		$this->assertArrayNotHasKey( 'context', $content_schema );
		$this->assertArrayNotHasKey( 'readonly', $content_schema );

		// Verify valid JSON Schema keywords are preserved.
		$this->assertSame( 'string', $content_schema['type'] );
		$this->assertSame( 'The content value.', $content_schema['description'] );
		$this->assertSame( array( 'content' ), $data['input_schema']['required'] );

		// Verify internal keywords are stripped from output_schema.
		$this->assertArrayNotHasKey( 'sanitize_callback', $data['output_schema'] );
		$this->assertArrayNotHasKey( 'validate_callback', $data['output_schema'] );
		$this->assertArrayNotHasKey( 'arg_options', $data['output_schema'] );
		$this->assertArrayNotHasKey( 'example', $data['output_schema'] );
		$this->assertArrayNotHasKey( 'examples', $data['output_schema'] );
		$this->assertArrayNotHasKey( 'context', $data['output_schema'] );
		$this->assertArrayNotHasKey( 'readonly', $data['output_schema'] );
		$this->assertSame( 'string', $data['output_schema']['type'] );
	}

	/**
	 * Test that nested empty object defaults are prepared as objects in REST response schemas.
	 *
	 * @ticket 64955
	 */
	public function test_nested_empty_object_schema_defaults_prepared_for_response(): void {
		$this->register_test_ability(
			'test/nested-object-defaults',
			array(
				'label'               => 'Test Nested Object Defaults',
				'description'         => 'Tests preparing nested empty object defaults.',
				'category'            => 'general',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array(
							'type'       => 'object',
							'default'    => array(),
							'properties' => array(
								'options' => array(
									'type'    => 'object',
									'default' => array(),
								),
							),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'result' => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
				'execute_callback'    => static function (): array {
					return array();
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/nested-object-defaults' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( new stdClass(), $data['input_schema']['properties']['settings']['default'] );
		$this->assertEquals( new stdClass(), $data['input_schema']['properties']['settings']['properties']['options']['default'] );
		$this->assertEquals( new stdClass(), $data['output_schema']['properties']['result']['default'] );
	}

	/**
	 * Test that schema keywords outside the allow-list are stripped from nested sub-schema locations.
	 *
	 * @ticket 64098
	 */
	public function test_unsupported_schema_keywords_stripped_from_nested_sub_schemas(): void {
		$this->register_test_ability(
			'test/nested-unsupported-keywords',
			array(
				'label'               => 'Test Nested Unsupported Keywords',
				'description'         => 'Tests stripping from all sub-schema locations',
				'category'            => 'general',
				'input_schema'        => array(
					'type'                 => 'object',
					'$ref'                 => '#/definitions/address',
					'anyOf'                => array(
						array(
							'type'              => 'object',
							'sanitize_callback' => 'sanitize_text_field',
							'properties'        => array(
								'value' => array(
									'type'              => 'string',
									'validate_callback' => 'is_string',
								),
							),
						),
						array(
							'type'        => 'number',
							'arg_options' => array( 'sanitize_callback' => 'absint' ),
						),
					),
					'oneOf'                => array(
						array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'allOf'                => array(
						array(
							'type'              => 'object',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
					'not'                  => array(
						'type'        => 'null',
						'arg_options' => array( 'sanitize_callback' => 'absint' ),
					),
					'patternProperties'    => array(
						'^S_' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'definitions'          => array(
						'address' => array(
							'type'              => 'object',
							'validate_callback' => 'rest_validate_request_arg',
							'properties'        => array(
								'street' => array(
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
								),
							),
						),
					),
					'dependencies'         => array(
						'bar' => array(
							'type'              => 'object',
							'validate_callback' => 'rest_validate_request_arg',
							'properties'        => array(
								'baz' => array(
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
								),
							),
						),
						'qux' => array( 'bar' ),
					),
					'additionalProperties' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'output_schema'       => array(
					'type'            => 'array',
					'items'           => array(
						array(
							'type'              => 'string',
							'validate_callback' => 'is_string',
						),
						array(
							'type'        => 'number',
							'arg_options' => array( 'sanitize_callback' => 'absint' ),
						),
					),
					'additionalItems' => array(
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
				'execute_callback'    => static function ( $input ) {
					return array();
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/nested-unsupported-keywords' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		// Verify internal keywords are stripped from anyOf sub-schemas.
		$this->assertSame( '#/definitions/address', $data['input_schema']['$ref'] );
		$this->assertArrayHasKey( 'anyOf', $data['input_schema'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $data['input_schema']['anyOf'][0] );
		$this->assertSame( 'object', $data['input_schema']['anyOf'][0]['type'] );
		$this->assertArrayNotHasKey( 'validate_callback', $data['input_schema']['anyOf'][0]['properties']['value'] );
		$this->assertSame( 'string', $data['input_schema']['anyOf'][0]['properties']['value']['type'] );
		$this->assertArrayNotHasKey( 'arg_options', $data['input_schema']['anyOf'][1] );
		$this->assertSame( 'number', $data['input_schema']['anyOf'][1]['type'] );

		// Verify internal keywords are stripped from oneOf sub-schemas.
		$this->assertArrayHasKey( 'oneOf', $data['input_schema'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $data['input_schema']['oneOf'][0] );
		$this->assertSame( 'string', $data['input_schema']['oneOf'][0]['type'] );

		// Verify internal keywords are stripped from allOf sub-schemas.
		$this->assertArrayHasKey( 'allOf', $data['input_schema'] );
		$this->assertArrayNotHasKey( 'validate_callback', $data['input_schema']['allOf'][0] );
		$this->assertSame( 'object', $data['input_schema']['allOf'][0]['type'] );

		// Verify internal keywords are stripped from not sub-schema.
		$this->assertArrayHasKey( 'not', $data['input_schema'] );
		$this->assertArrayNotHasKey( 'arg_options', $data['input_schema']['not'] );
		$this->assertSame( 'null', $data['input_schema']['not']['type'] );

		// Verify internal keywords are stripped from patternProperties sub-schemas.
		$this->assertArrayHasKey( 'patternProperties', $data['input_schema'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $data['input_schema']['patternProperties']['^S_'] );
		$this->assertSame( 'string', $data['input_schema']['patternProperties']['^S_']['type'] );

		// Verify internal keywords are stripped from dependencies schema values.
		$this->assertArrayHasKey( 'dependencies', $data['input_schema'] );
		$this->assertArrayNotHasKey( 'validate_callback', $data['input_schema']['dependencies']['bar'] );
		$this->assertSame( 'object', $data['input_schema']['dependencies']['bar']['type'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $data['input_schema']['dependencies']['bar']['properties']['baz'] );
		$this->assertSame( 'string', $data['input_schema']['dependencies']['bar']['properties']['baz']['type'] );
		// Property dependencies (numeric arrays) should pass through unchanged.
		$this->assertSame( array( 'bar' ), $data['input_schema']['dependencies']['qux'] );

		// Verify internal keywords are stripped from definitions sub-schemas.
		$this->assertArrayHasKey( 'definitions', $data['input_schema'] );
		$this->assertArrayNotHasKey( 'validate_callback', $data['input_schema']['definitions']['address'] );
		$this->assertSame( 'object', $data['input_schema']['definitions']['address']['type'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $data['input_schema']['definitions']['address']['properties']['street'] );
		$this->assertSame( 'string', $data['input_schema']['definitions']['address']['properties']['street']['type'] );

		// Verify internal keywords are stripped from additionalProperties sub-schema.
		$this->assertArrayHasKey( 'additionalProperties', $data['input_schema'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $data['input_schema']['additionalProperties'] );
		$this->assertSame( 'string', $data['input_schema']['additionalProperties']['type'] );

		// Verify internal keywords are stripped from tuple-style items sub-schemas.
		$this->assertArrayHasKey( 'items', $data['output_schema'] );
		$this->assertCount( 2, $data['output_schema']['items'] );
		$this->assertArrayNotHasKey( 'validate_callback', $data['output_schema']['items'][0] );
		$this->assertSame( 'string', $data['output_schema']['items'][0]['type'] );
		$this->assertArrayNotHasKey( 'arg_options', $data['output_schema']['items'][1] );
		$this->assertSame( 'number', $data['output_schema']['items'][1]['type'] );

		// Verify internal keywords are stripped from additionalItems sub-schema.
		$this->assertArrayHasKey( 'additionalItems', $data['output_schema'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $data['output_schema']['additionalItems'] );
		$this->assertSame( 'boolean', $data['output_schema']['additionalItems']['type'] );
	}

	/**
	 * Test that per-property `required` booleans become a draft-04 `required` array.
	 *
	 * @ticket 64955
	 */
	public function test_required_property_booleans_converted_to_draft_04_array(): void {
		$this->register_test_ability(
			'test/required-booleans',
			array(
				'label'               => 'Required Booleans',
				'description'         => 'Tests conversion of per-property required booleans.',
				'category'            => 'general',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'    => array(
							'type'     => 'string',
							'required' => true,
						),
						'content'  => array(
							'type'     => 'string',
							'required' => true,
						),
						'optional' => array(
							'type' => 'string',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
				'execute_callback'    => static function (): array {
					return array( 'id' => 1 );
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/required-booleans' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		// The `required` array lists the names of the properties flagged as required.
		$this->assertArrayHasKey( 'required', $data['input_schema'] );
		$this->assertSameSets( array( 'title', 'content' ), $data['input_schema']['required'] );

		// The boolean flag is removed from each property sub-schema.
		$this->assertArrayNotHasKey( 'required', $data['input_schema']['properties']['title'] );
		$this->assertArrayNotHasKey( 'required', $data['input_schema']['properties']['content'] );
		$this->assertArrayNotHasKey( 'required', $data['input_schema']['properties']['optional'] );

		// Output schemas are normalized the same way.
		$this->assertSame( array( 'id' ), $data['output_schema']['required'] );
		$this->assertArrayNotHasKey( 'required', $data['output_schema']['properties']['id'] );
	}

	/**
	 * Test that per-property `required` booleans are converted in nested object schemas.
	 *
	 * @ticket 64955
	 */
	public function test_required_booleans_converted_in_nested_object_schemas(): void {
		$this->register_test_ability(
			'test/required-nested',
			array(
				'label'               => 'Required Nested',
				'description'         => 'Tests conversion within nested object schemas.',
				'category'            => 'general',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'address' => array(
							'type'       => 'object',
							'required'   => true,
							'properties' => array(
								'street' => array(
									'type'     => 'string',
									'required' => true,
								),
								'city'   => array(
									'type' => 'string',
								),
							),
						),
					),
				),
				'execute_callback'    => static function () {
					return null;
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/required-nested' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data    = $response->get_data();
		$address = $data['input_schema']['properties']['address'];

		// The outer object lists the nested object as a required property.
		$this->assertSame( array( 'address' ), $data['input_schema']['required'] );

		// The nested object's own boolean flag is replaced by a draft-04 array
		// collecting its own required properties (proving the boolean was converted).
		$this->assertSame( array( 'street' ), $address['required'] );
		$this->assertArrayNotHasKey( 'required', $address['properties']['street'] );
		$this->assertArrayNotHasKey( 'required', $address['properties']['city'] );
	}

	/**
	 * Test that `required: false` is removed without emitting an empty `required` array.
	 *
	 * @ticket 64955
	 */
	public function test_required_false_booleans_removed_without_required_array(): void {
		$this->register_test_ability(
			'test/required-false',
			array(
				'label'               => 'Required False',
				'description'         => 'Tests that required:false is stripped.',
				'category'            => 'general',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'maybe' => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				),
				'execute_callback'    => static function () {
					return null;
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/required-false' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayNotHasKey( 'required', $data['input_schema'] );
		$this->assertArrayNotHasKey( 'required', $data['input_schema']['properties']['maybe'] );
	}

	/**
	 * Test that an existing draft-04 `required` array takes precedence over per-property booleans.
	 *
	 * This mirrors rest_validate_object_value_from_schema(), which ignores
	 * per-property `required` booleans when a draft-04 `required` array is
	 * present, so the published schema matches what is actually enforced.
	 *
	 * @ticket 64955
	 */
	public function test_required_draft_04_array_takes_precedence_over_booleans(): void {
		$this->register_test_ability(
			'test/required-mixed',
			array(
				'label'               => 'Required Mixed',
				'description'         => 'Tests precedence of a draft-04 array over draft-03 booleans.',
				'category'            => 'general',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'title' ),
					'properties' => array(
						'title'   => array(
							'type'     => 'string',
							'required' => true,
						),
						'content' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
				'execute_callback'    => static function () {
					return null;
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/required-mixed' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		// The draft-04 array wins: the `content` boolean is ignored, not merged in.
		$this->assertSame( array( 'title' ), $data['input_schema']['required'] );

		// The per-property booleans are still stripped from the output.
		$this->assertArrayNotHasKey( 'required', $data['input_schema']['properties']['title'] );
		$this->assertArrayNotHasKey( 'required', $data['input_schema']['properties']['content'] );
	}

	/**
	 * Test that a boolean `required` with no draft-04 equivalent (e.g. on a scalar) is dropped.
	 *
	 * @ticket 64955
	 */
	public function test_required_boolean_on_scalar_schema_removed(): void {
		$this->register_test_ability(
			'test/required-scalar',
			array(
				'label'               => 'Required Scalar',
				'description'         => 'Tests stripping of a boolean required on a scalar schema.',
				'category'            => 'general',
				'input_schema'        => array(
					'type'        => 'string',
					'description' => 'The text to analyze.',
					'required'    => true,
				),
				'output_schema'       => array(
					'type'     => 'string',
					'required' => true,
				),
				'execute_callback'    => static function ( $input ) {
					return $input;
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/required-scalar' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayNotHasKey( 'required', $data['input_schema'] );
		$this->assertSame( 'string', $data['input_schema']['type'] );
		$this->assertArrayNotHasKey( 'required', $data['output_schema'] );
	}

	/**
	 * Test that per-property `required` booleans are converted in an array's `items` object.
	 *
	 * @ticket 64955
	 */
	public function test_required_booleans_converted_in_array_items_object_schemas(): void {
		$this->register_test_ability(
			'test/required-array-items',
			array(
				'label'               => 'Required Array Items',
				'description'         => 'Tests conversion within array item object schemas.',
				'category'            => 'general',
				'input_schema'        => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'    => array(
								'type'     => 'integer',
								'required' => true,
							),
							'label' => array(
								'type' => 'string',
							),
						),
					),
				),
				'execute_callback'    => static function () {
					return null;
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/test/required-array-items' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data  = $response->get_data();
		$items = $data['input_schema']['items'];

		// The object schema inside `items` collects its own required properties
		// into a draft-04 array, and the per-property boolean is removed.
		$this->assertSame( array( 'id' ), $items['required'] );
		$this->assertArrayNotHasKey( 'required', $items['properties']['id'] );
		$this->assertArrayNotHasKey( 'required', $items['properties']['label'] );
	}
}
