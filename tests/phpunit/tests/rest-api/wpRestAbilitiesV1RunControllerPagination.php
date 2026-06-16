<?php

declare( strict_types=1 );

/**
 * Tests the REST-level pagination handling in the Abilities run controller.
 *
 * A registered ability opts into pagination by setting a truthy `pagination` meta value
 * and returning integer `total` and `total_pages` alongside its collection. The run
 * controller then emits the standard X-WP-Total / X-WP-TotalPages headers and rejects
 * out-of-range page requests with a 400.
 *
 * @covers WP_REST_Abilities_V1_Run_Controller::execute_ability
 *
 * @group abilities-api
 * @group rest-api
 */
class Tests_REST_API_WpRestAbilitiesV1RunControllerPagination extends WP_UnitTestCase {

	/**
	 * The REST server instance for the current test.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * The total number of items the test ability reports.
	 *
	 * @var int
	 */
	const TOTAL_ITEMS = 5;

	/**
	 * The run route for the paginated test ability.
	 *
	 * @var string
	 */
	const RUN_ROUTE = '/wp-abilities/v1/abilities/test/paginated/run';

	/**
	 * Registers a paginated test ability and its category.
	 *
	 * @since 7.1.0
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		global $wp_current_filter;

		$wp_current_filter[] = 'wp_abilities_api_categories_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		try {
			wp_register_ability_category(
				'test',
				array(
					'label'       => 'Test',
					'description' => 'Test abilities.',
				)
			);
		} finally {
			array_pop( $wp_current_filter );
		}

		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		try {
			wp_register_ability(
				'test/paginated',
				array(
					'label'               => 'Paginated',
					'description'         => 'A paginated test ability.',
					'category'            => 'test',
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'page'     => array(
								'type'    => 'integer',
								'minimum' => 1,
								'default' => 1,
							),
							'per_page' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'default' => 2,
							),
						),
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'items'       => array( 'type' => 'array' ),
							'total'       => array( 'type' => 'integer' ),
							'total_pages' => array( 'type' => 'integer' ),
						),
					),
					'execute_callback'    => static function ( $input = array() ): array {
						$per_page = isset( $input['per_page'] ) ? max( 1, (int) $input['per_page'] ) : 2;
						$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
						$total    = self::TOTAL_ITEMS;

						$offset = ( $page - 1 ) * $per_page;
						$count  = max( 0, min( $per_page, $total - $offset ) );

						return array(
							'items'       => array_fill( 0, $count, 'item' ),
							'total'       => $total,
							'total_pages' => (int) ceil( $total / $per_page ),
						);
					},
					'permission_callback' => '__return_true',
					'meta'                => array(
						'annotations'  => array(
							'readonly'    => true,
							'destructive' => false,
							'idempotent'  => true,
						),
						'show_in_rest' => true,
						'pagination'   => true,
					),
				)
			);
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * Unregisters the test ability and category.
	 *
	 * @since 7.1.0
	 */
	public static function tear_down_after_class(): void {
		if ( wp_has_ability( 'test/paginated' ) ) {
			wp_unregister_ability( 'test/paginated' );
		}
		if ( wp_has_ability_category( 'test' ) ) {
			wp_unregister_ability_category( 'test' );
		}

		parent::tear_down_after_class();
	}

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		wp_set_current_user( self::$admin_id );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Builds a GET run request with the given ability input.
	 *
	 * @param array<string, mixed> $input The ability input.
	 * @return WP_REST_Request The request.
	 */
	private function run_request( array $input ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', self::RUN_ROUTE );
		$request->set_query_params( array( 'input' => $input ) );
		return $request;
	}

	public function test_emits_total_headers(): void {
		$response = $this->server->dispatch( $this->run_request( array( 'per_page' => 2 ) ) );
		$headers  = $response->get_headers();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::TOTAL_ITEMS, (int) $headers['X-WP-Total'] );
		$this->assertSame( 3, (int) $headers['X-WP-TotalPages'] );
	}

	public function test_body_is_returned_unchanged(): void {
		$response = $this->server->dispatch( $this->run_request( array( 'per_page' => 2 ) ) );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'items', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertSame( self::TOTAL_ITEMS, $data['total'] );
	}

	public function test_out_of_range_page_returns_400(): void {
		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'per_page' => 2,
					'page'     => 99,
				)
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_ability_invalid_page_number', $response->get_data()['code'] );
	}

	public function test_last_page_is_allowed(): void {
		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'per_page' => 2,
					'page'     => 3,
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data()['items'] );
	}
}
