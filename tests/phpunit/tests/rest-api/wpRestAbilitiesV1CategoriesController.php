<?php declare( strict_types=1 );

/**
 * Tests for the REST controller for the ability categories endpoint.
 *
 * @covers WP_REST_Abilities_V1_Categories_Controller
 *
 * @group abilities-api
 * @group rest-api
 */
class Tests_REST_API_WpRestAbilitiesV1CategoriesController extends WP_UnitTestCase {

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

		$this->register_test_ability_categories();

		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		// Clean up test ability categories.
		foreach ( wp_get_ability_categories() as $ability_category ) {
			if ( ! str_starts_with( $ability_category->get_slug(), 'test-' ) ) {
				continue;
			}

			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Register test ability categories for testing.
	 */
	public function register_test_ability_categories(): void {
		// Simulates the init hook to allow test ability categories registration.
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_categories_init';

		wp_register_ability_category(
			'test-data-retrieval',
			array(
				'label'       => 'Data Retrieval',
				'description' => 'Abilities that retrieve and return data from the WordPress site.',
			)
		);

		wp_register_ability_category(
			'test-data-modification',
			array(
				'label'       => 'Data Modification',
				'description' => 'Abilities that modify data on the WordPress site.',
			)
		);

		wp_register_ability_category(
			'test-communication',
			array(
				'label'       => 'Communication',
				'description' => 'Abilities that send messages or notifications.',
				'meta'        => array(
					'priority' => 'high',
				),
			)
		);

		// Register multiple ability categories for pagination testing
		for ( $i = 1; $i <= 60; $i++ ) {
			wp_register_ability_category(
				"test-category-{$i}",
				array(
					'label'       => "Test Category {$i}",
					'description' => "Test category number {$i}",
				)
			);
		}

		array_pop( $wp_current_filter );
	}

	/**
	 * Test listing all ability categories.
	 *
	 * @ticket 64098
	 */
	public function test_get_items(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		$this->assertCount( 50, $data, 'First page should return exactly 50 items (default per_page)' );

		$category_slugs = wp_list_pluck( $data, 'slug' );
		$this->assertContains( 'test-data-retrieval', $category_slugs );
		$this->assertContains( 'test-data-modification', $category_slugs );
		$this->assertContains( 'test-communication', $category_slugs );
	}

	/**
	 * Test getting a specific ability category.
	 *
	 * @ticket 64098
	 */
	public function test_get_item(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/test-data-retrieval' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'test-data-retrieval', $data['slug'] );
		$this->assertSame( 'Data Retrieval', $data['label'] );
		$this->assertSame( 'Abilities that retrieve and return data from the WordPress site.', $data['description'] );
		$this->assertArrayHasKey( 'meta', $data );
	}

	/**
	 * Test getting an ability category with meta.
	 *
	 * @ticket 64098
	 */
	public function test_get_item_with_meta(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/test-communication' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'test-communication', $data['slug'] );
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertIsArray( $data['meta'] );
		$this->assertSame( 'high', $data['meta']['priority'] );
	}

	/**
	 * Test getting a specific ability category with only selected fields.
	 *
	 * @ticket 64098
	 */
	public function test_get_item_with_selected_fields(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/test-data-retrieval' );
		$request->set_param( '_fields', 'slug,label' );
		$response = $this->server->dispatch( $request );
		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $this->server, $request );
		remove_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10 );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data, 'Response should only contain the requested fields.' );
		$this->assertSame( 'test-data-retrieval', $data['slug'] );
		$this->assertSame( 'Data Retrieval', $data['label'] );
	}

	/**
	 * Test getting a non-existent ability category returns 404.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::get_registered
	 */
	public function test_get_item_not_found(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/non-existent' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'rest_ability_category_not_found', $data['code'] );
	}

	/**
	 * Test permission check for listing ability categories.
	 *
	 * @ticket 64098
	 */
	public function test_get_items_permission_denied(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test permission check for single ability category.
	 *
	 * @ticket 64098
	 */
	public function test_get_item_permission_denied(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/test-data-retrieval' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test pagination headers.
	 *
	 * @ticket 64098
	 */
	public function test_pagination_headers(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories' );
		$request->set_param( 'per_page', 10 );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );

		$total_categories = count( wp_get_ability_categories() );
		$this->assertEquals( $total_categories, (int) $headers['X-WP-Total'] );
		$this->assertEquals( ceil( $total_categories / 10 ), (int) $headers['X-WP-TotalPages'] );
	}

	/**
	 * Test HEAD method returns empty body with proper headers.
	 *
	 * @ticket 64098
	 */
	public function test_head_request(): void {
		$request  = new WP_REST_Request( 'HEAD', '/wp-abilities/v1/categories' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertEmpty( $data );

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
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories' );
		$request->set_param( 'per_page', 10 );
		$request->set_param( 'page', 1 );
		$response = $this->server->dispatch( $request );

		$headers     = $response->get_headers();
		$link_header = $headers['Link'] ?? '';

		$this->assertStringContainsString( 'rel="next"', $link_header );
		$this->assertStringNotContainsString( 'rel="prev"', $link_header );

		$request->set_param( 'page', 3 );
		$response = $this->server->dispatch( $request );

		$headers     = $response->get_headers();
		$link_header = $headers['Link'] ?? '';

		$this->assertStringContainsString( 'rel="next"', $link_header );
		$this->assertStringContainsString( 'rel="prev"', $link_header );

		$total_categories = count( wp_get_ability_categories() );
		$last_page        = ceil( $total_categories / 10 );
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
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories' );
		$request->set_param( 'per_page', 5 );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertCount( 5, $data );
		$request->set_param( 'page', 2 );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 5, $data );

		$page1_request = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories' );
		$page1_request->set_param( 'per_page', 5 );
		$page1_request->set_param( 'page', 1 );
		$page1_response = $this->server->dispatch( $page1_request );
		$page1_slugs    = wp_list_pluck( $page1_response->get_data(), 'slug' );
		$page2_slugs    = wp_list_pluck( $data, 'slug' );

		$this->assertNotEquals( $page1_slugs, $page2_slugs );
	}

	/**
	 * Test response links for individual ability categories.
	 *
	 * @ticket 64098
	 */
	public function test_ability_category_response_links(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/test-data-retrieval' );
		$response = $this->server->dispatch( $request );

		$links = $response->get_links();
		$this->assertArrayHasKey( 'self', $links );
		$this->assertArrayHasKey( 'collection', $links );
		$this->assertArrayHasKey( 'abilities', $links );

		$self_link = $links['self'][0]['href'];
		$this->assertStringContainsString( '/wp-abilities/v1/categories/test-data-retrieval', $self_link );

		$collection_link = $links['collection'][0]['href'];
		$this->assertStringContainsString( '/wp-abilities/v1/categories', $collection_link );

		$abilities_link = $links['abilities'][0]['href'];
		$this->assertStringContainsString( '/wp-abilities/v1/abilities?category=test-data-retrieval', $abilities_link );
	}

	/**
	 * Test context parameter.
	 *
	 * @ticket 64098
	 */
	public function test_context_parameter(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/test-data-retrieval' );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'description', $data );

		$request->set_param( 'context', 'embed' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'slug', $data );
		$this->assertArrayHasKey( 'label', $data );
	}

	/**
	 * Test schema retrieval.
	 *
	 * @ticket 64098
	 */
	public function test_get_schema(): void {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp-abilities/v1/categories' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'schema', $data );
		$schema = $data['schema'];

		$this->assertSame( 'ability-category', $schema['title'] );
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );

		$properties = $schema['properties'];

		$this->assertCount( 4, $properties, 'Schema should have exactly 4 properties.' );

		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'label', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'meta', $properties );

		$slug_property = $properties['slug'];
		$this->assertSame( 'string', $slug_property['type'] );
		$this->assertTrue( $slug_property['readonly'] );
	}

	/**
	 * Test ability category slug with valid format.
	 *
	 * @ticket 64098
	 */
	public function test_ability_category_slug_with_valid_format(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/test-data-retrieval' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Data provider for invalid ability category slugs.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_invalid_ability_category_slugs_provider(): array {
		return array(
			'Uppercase'         => array( 'Data-Retrieval' ),
			'@ symbol'          => array( 'data@retrieval' ),
			'space'             => array( 'data retrieval' ),
			'dot'               => array( 'data.retrieval' ),
			'underscore'        => array( 'data_retrieval' ),
			'URL encoded space' => array( 'data%20retrieval' ),
		);
	}

	/**
	 * Test ability category slugs with invalid format.
	 *
	 * @ticket 64098
	 *
	 * @dataProvider data_invalid_ability_category_slugs_provider
	 *
	 * @param string $slug Invalid ability category slug to test.
	 */
	public function test_ability_category_slug_with_invalid_format( string $slug ): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/' . $slug );
		$response = $this->server->dispatch( $request );

		$this->assertContains( $response->get_status(), array( 400, 404 ) );
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
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/categories' );
		$request->set_query_params( $params );

		$response = $this->server->dispatch( $request );

		$this->assertContains( $response->get_status(), array( 200, 400 ) );

		if ( $response->get_status() !== 200 ) {
			return;
		}

		$data = $response->get_data();
		$this->assertIsArray( $data );
	}
}
