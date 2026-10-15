<?php

declare( strict_types=1 );

/**
 * Tests dispatching the core/content-query ability through the Abilities REST run endpoint.
 *
 * @covers WP_Abilities_Content
 *
 * @group abilities-api
 * @group restapi
 */
class Tests_REST_API_WpRestAbilitiesContentController extends WP_UnitTestCase {

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
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * The run route for the core/content-query ability.
	 *
	 * @var string
	 */
	const RUN_ROUTE = '/wp-abilities/v1/abilities/core/content-query/run';

	/**
	 * Sets up users and registers the core abilities.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );

		/*
		 * Restore the hooks right away instead of after the class. The first test of a run
		 * snapshots the hooks and every test resets them to that snapshot, so changes left
		 * here would leak into every later test whenever this class runs first.
		 */
		remove_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		remove_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );
	}

	/**
	 * Cleans up registered abilities and categories.
	 *
	 * @since 7.2.0
	 */
	public static function wpTearDownAfterClass(): void {
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}
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

	/**
	 * @ticket 66268
	 */
	public function test_logged_out_user_receives_401(): void {
		wp_set_current_user( 0 );

		$response = $this->server->dispatch( $this->run_request( array( 'where' => array( 'type' => 'post' ) ) ) );

		$this->assertSame( 401, $response->get_status(), 'A logged-out user should be refused as unauthenticated.' );
	}

	/**
	 * @ticket 66268
	 */
	public function test_subscriber_requesting_drafts_receives_403(): void {
		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where' => array(
						'type'   => 'post',
						'status' => array( 'draft' ),
					),
				)
			)
		);

		$this->assertSame( 403, $response->get_status(), 'A subscriber should be forbidden from querying drafts.' );
	}

	/**
	 * @ticket 66268
	 */
	public function test_subscriber_requesting_published_posts_receives_readable_fields(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Published for subscriber via REST',
				'post_content' => 'Subscriber REST body.',
				'post_status'  => 'publish',
			)
		);

		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where'  => array( 'type' => 'post' ),
					'fields' => array( 'id', 'title', 'content' ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'A subscriber should be able to query published posts.' );
		$this->assertContains( $post_id, wp_list_pluck( $data['items'], 'id' ), 'The published post should be among the returned posts.' );

		$post_index = array_search( $post_id, wp_list_pluck( $data['items'], 'id' ), true );
		$this->assertIsInt( $post_index, 'Precondition: the published post should have a position in the posts list.' );

		$post = $data['items'][ $post_index ];
		$this->assertSame( array( 'rendered' => 'Published for subscriber via REST' ), $post['title'], 'Only the rendered title should be returned to a subscriber.' );
		$this->assertStringContainsString( 'Subscriber REST body.', $post['content']['rendered'], 'The rendered content should be returned to a subscriber.' );
		$this->assertSameSets( array( 'rendered', 'protected' ), array_keys( $post['content'] ), 'Raw content should not be returned to a subscriber.' );
	}

	/**
	 * @ticket 66268
	 */
	public function test_subscriber_requesting_raw_fields_receives_the_posts_without_them(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where'  => array( 'type' => 'post' ),
					'fields' => array( 'content.raw' ),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'A subscriber requesting raw fields should not be forbidden.' );
		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $post_id ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$response->get_data(),
			'The published post should be returned without the raw content.'
		);
	}

	/**
	 * @ticket 66268
	 */
	public function test_admin_query_returns_published_posts(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Published via REST',
				'post_status' => 'publish',
			)
		);

		$response = $this->server->dispatch( $this->run_request( array( 'where' => array( 'type' => 'post' ) ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'An administrator should be able to query published posts.' );
		$this->assertArrayHasKey( 'items', $data, 'The query should return the posts as items.' );
		$this->assertContains( $post_id, wp_list_pluck( $data['items'], 'id' ), 'The published post should be among the returned posts.' );
	}

	/**
	 * @ticket 66268
	 */
	public function test_admin_query_ids_limit_results(): void {
		$first = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-02-01 10:00:00',
			)
		);
		$third = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-03-01 10:00:00',
			)
		);

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where'  => array(
						'type' => 'post',
						// Deliberately pass IDs in the opposite of the expected date order.
						'ids'  => array( $first, $third ),
					),
					'fields' => array( 'id' ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The query by IDs should succeed.' );
		$this->assertSame( array( $third, $first ), wp_list_pluck( $data['items'], 'id' ), 'The requested posts should be returned newest first, regardless of the order of the IDs.' );
	}

	/**
	 * @ticket 66268
	 */
	public function test_query_by_id_returns_the_post(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where' => array(
						'type' => 'post',
						'ids'  => array( $post_id ),
					),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The query by ID should succeed.' );
		$this->assertSame( array( $post_id ), wp_list_pluck( $data['items'], 'id' ), 'A query by one ID should return only the requested post.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$data['pagination'],
			'A query by one ID should report one post on one page.'
		);
	}

	/**
	 * @ticket 66268
	 */
	public function test_query_by_slug_returns_the_post(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_name'   => 'rest-content-slug',
				'post_status' => 'publish',
			)
		);

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where' => array(
						'type'  => 'post',
						'slugs' => array( 'rest-content-slug' ),
					),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The query by slug should succeed.' );
		$this->assertSame( array( $post_id ), wp_list_pluck( $data['items'], 'id' ), 'A query by slug should return only the matching post.' );
		$this->assertSame( 'rest-content-slug', $data['items'][0]['slug'], 'The query by slug should return the post slug.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$data['pagination'],
			'A query by slug should report one post on one page.'
		);
	}

	/**
	 * @ticket 66268
	 */
	public function test_wrong_http_method_returns_405(): void {
		$request = new WP_REST_Request( 'POST', self::RUN_ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => array( 'where' => array( 'type' => 'post' ) ) ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status(), 'A POST request to the read-only ability should be rejected.' );
		$this->assertSame( 'rest_ability_invalid_method', $response->get_data()['code'], 'The error should identify the invalid HTTP method.' );
	}

	/**
	 * @ticket 66268
	 */
	public function test_pagination_returns_totals_in_body(): void {
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where'      => array( 'type' => 'post' ),
					'pagination' => array(
						'page'     => 1,
						'per_page' => 2,
					),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The paginated query should succeed.' );
		$this->assertCount( 2, $data['items'], 'The first page should be limited to per_page posts.' );
		$this->assertGreaterThanOrEqual( 3, $data['pagination']['total'], 'The total should count posts across all pages.' );
		$this->assertSame( (int) ceil( $data['pagination']['total'] / 2 ), $data['pagination']['total_pages'], 'The total pages should match the total and per_page.' );
	}

	/**
	 * @ticket 66268
	 */
	public function test_out_of_range_page_returns_404(): void {
		self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where'      => array( 'type' => 'post' ),
					'pagination' => array(
						'page'     => 999,
						'per_page' => 1,
					),
				)
			)
		);

		$this->assertSame( 404, $response->get_status(), 'Requesting a page past the last one should return a 404 error.' );
		$this->assertSame( 'content_invalid_page_number', $response->get_data()['code'], 'The error should identify the invalid page number.' );
	}

	/**
	 * A GET request delivers every value as a string, and a list as a comma-separated string.
	 *
	 * @ticket 66268
	 */
	public function test_query_string_values_are_accepted(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Query string lookup',
				'post_status' => 'publish',
			)
		);

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where'      => array(
						'type' => 'post',
						'ids'  => (string) $post_id,
					),
					'fields'     => 'id,title.rendered',
					'pagination' => array(
						'page'     => '1',
						'per_page' => '10',
					),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'String values for a query by one ID should be accepted.' );
		$this->assertSame(
			array(
				'items'      => array(
					array(
						'id'    => $post_id,
						'title' => array( 'rendered' => 'Query string lookup' ),
					),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$response->get_data(),
			'The query should return the requested post with the fields from the comma-separated list.'
		);

		$older = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$newer = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-02-01 10:00:00',
			)
		);

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where'      => array(
						'type' => 'post',
						'ids'  => $newer . ',' . $older,
					),
					'fields'     => 'id',
					'pagination' => array(
						'page'     => '2',
						'per_page' => '1',
					),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'String values for the second page of a query by two IDs should be accepted.' );
		$this->assertSame( array( $older ), wp_list_pluck( $data['items'], 'id' ), 'The second page, of one post each, should hold the older post.' );
		$this->assertSame( 2, $data['pagination']['total'], 'The total should count both requested posts.' );
		$this->assertSame( 2, $data['pagination']['total_pages'], 'The total pages should be computed from the string per_page value.' );
	}

	/**
	 * A GET request delivers `slugs` as a comma-separated string, alone or next to `ids`.
	 *
	 * @ticket 66268
	 */
	public function test_query_string_lists_of_slugs_are_accepted(): void {
		$first  = self::factory()->post->create(
			array(
				'post_name'   => 'rest-list-a',
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$second = self::factory()->post->create(
			array(
				'post_name'   => 'rest-list-b',
				'post_status' => 'publish',
				'post_date'   => '2026-02-01 10:00:00',
			)
		);
		$third  = self::factory()->post->create(
			array(
				'post_name'   => 'rest-list-c',
				'post_status' => 'publish',
				'post_date'   => '2026-03-01 10:00:00',
			)
		);

		$by_slugs = $this->server->dispatch(
			$this->run_request(
				array(
					'where'  => array(
						'type'  => 'post',
						'slugs' => 'rest-list-a,rest-list-c',
					),
					'fields' => 'id',
				)
			)
		);

		$this->assertSame( 200, $by_slugs->get_status(), 'A comma-separated slugs string from a query string should be accepted.' );
		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $third ),
					array( 'id' => $first ),
				),
				'pagination' => array(
					'total'       => 2,
					'total_pages' => 1,
				),
			),
			$by_slugs->get_data(),
			'A comma-separated slugs string should return every listed post, newest first, on one page.'
		);

		$by_both = $this->server->dispatch(
			$this->run_request(
				array(
					'where'  => array(
						'type'  => 'post',
						'ids'   => $first . ',' . $second,
						'slugs' => 'rest-list-b,rest-list-c',
					),
					'fields' => 'id',
				)
			)
		);

		$this->assertSame( 200, $by_both->get_status(), 'Comma-separated ids and slugs strings together should be accepted.' );
		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $second ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$by_both->get_data(),
			'Comma-separated ids and slugs strings together should return only the post in both lists.'
		);
	}

	/**
	 * A GET request with an `id` filter, which `ids` replaces, is rejected, even next to a post
	 * type.
	 *
	 * @ticket 66268
	 */
	public function test_id_filter_returns_400(): void {
		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where' => array(
						'type' => 'post',
						'id'   => '1',
					),
				)
			)
		);

		$this->assertSame( 400, $response->get_status(), 'An id filter should be rejected.' );
		$this->assertSame( 'ability_invalid_input', $response->get_data()['code'], 'An id filter should fail input validation.' );
	}

	/**
	 * A GET request in the old input shape, with the post type at the root, is rejected.
	 *
	 * @ticket 66268
	 */
	public function test_old_input_shape_returns_400(): void {
		$response = $this->server->dispatch( $this->run_request( array( 'type' => 'post' ) ) );

		$this->assertSame( 400, $response->get_status(), 'A post type at the root of the input should be rejected.' );
		$this->assertSame( 'ability_invalid_input', $response->get_data()['code'], 'The error should come from input validation.' );
	}
}
