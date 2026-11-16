<?php

declare( strict_types=1 );

/**
 * Tests dispatching the content abilities through the Abilities REST run endpoint.
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
		$older = self::factory()->post->create(
			array(
				'post_title'  => 'Older post',
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
					'fields'     => 'id,title.rendered',
					'pagination' => array(
						'page'     => '2',
						'per_page' => '1',
					),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'String values for the second page of a query by two IDs should be accepted.' );
		$this->assertSame(
			array(
				'items'      => array(
					array(
						'id'    => $older,
						'title' => array( 'rendered' => 'Older post' ),
					),
				),
				'pagination' => array(
					'total'       => 2,
					'total_pages' => 2,
				),
			),
			$response->get_data(),
			'The second page, of one post each, should hold the older post with the fields from the comma-separated list.'
		);
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
	 * A GET request without `where` is rejected, also when it gives the post type at the root.
	 *
	 * @ticket 66268
	 */
	public function test_input_without_where_returns_400(): void {
		$response = $this->server->dispatch( $this->run_request( array( 'type' => 'post' ) ) );

		$this->assertSame( 400, $response->get_status(), 'Input without where should be rejected.' );
		$this->assertSame( 'ability_invalid_input', $response->get_data()['code'], 'The error should come from input validation.' );
	}

	/**
	 * Builds a run request for a content write ability.
	 *
	 * A POST request carries the input in a JSON body, any other request in the query string.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $method  The HTTP method.
	 * @param string               $ability The ability name.
	 * @param array<string, mixed> $input   The ability input.
	 * @return WP_REST_Request The request.
	 */
	private function write_request( string $method, string $ability, array $input ): WP_REST_Request {
		$request = new WP_REST_Request( $method, "/wp-abilities/v1/abilities/{$ability}/run" );

		if ( 'POST' === $method ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( array( 'input' => $input ) ) );
		} else {
			$request->set_query_params( array( 'input' => $input ) );
		}

		return $request;
	}

	/**
	 * Asserts that a response is an error with the given status and code.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_REST_Response $response The response.
	 * @param int              $status   The expected status.
	 * @param string           $code     The expected error code.
	 * @param string           $context  What the request was, for the assertion messages.
	 */
	private function assert_error_response( WP_REST_Response $response, int $status, string $code, string $context ): void {
		$this->assertSame( $status, $response->get_status(), "{$context}: the status should be {$status}." );
		$this->assertSame( $code, $response->get_data()['code'] ?? null, "{$context}: the error code should be {$code}." );
	}

	/**
	 * Takes a snapshot of the stored content: the number of posts per type and status, the
	 * number of meta rows and, when given, the row and meta of one post.
	 *
	 * @since 7.3.0
	 *
	 * @param int $post_id Optional. The post ID. Default 0.
	 * @return array<string, mixed> The snapshot.
	 */
	private function snapshot( int $post_id = 0 ): array {
		global $wpdb;

		return array(
			'posts'     => $wpdb->get_results( "SELECT post_type, post_status, COUNT(*) AS total FROM {$wpdb->posts} GROUP BY post_type, post_status ORDER BY post_type, post_status", ARRAY_A ),
			'meta'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ),
			'post'      => $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A ),
			'post_meta' => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $post_id ), ARRAY_A ),
		);
	}

	/**
	 * Create runs with POST and the input in a JSON body.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_create_runs_with_post_and_a_json_body(): void {
		$response = $this->server->dispatch(
			$this->write_request(
				'POST',
				'core/content-create',
				array(
					'data'   => array(
						'type'  => 'post',
						'title' => 'Over the run route',
					),
					'fields' => array( 'status', 'title.rendered' ),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The create request should succeed.' );

		$data = $response->get_data();

		$this->assertSame( array( 'id', 'status', 'title' ), array_keys( $data ), 'The created post should be returned in the chosen fields.' );
		$this->assertSame( 'draft', $data['status'], 'The created post should be a draft.' );
		$this->assertSame( 'Over the run route', get_post( $data['id'] )->post_title, 'The created post should be stored.' );
	}

	/**
	 * Update runs with POST and the input in a JSON body.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_update_runs_with_post_and_a_json_body(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$response = $this->server->dispatch(
			$this->write_request(
				'POST',
				'core/content-update',
				array(
					'where'  => array(
						'type' => 'post',
						'id'   => $post_id,
					),
					'data'   => array( 'title' => 'Updated over the run route' ),
					'fields' => array( 'title.rendered' ),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The update request should succeed.' );
		$this->assertSame(
			array(
				'id'    => $post_id,
				'title' => array( 'rendered' => 'Updated over the run route' ),
			),
			$response->get_data(),
			'The updated post should be returned in the chosen fields.'
		);
	}

	/**
	 * Delete runs with DELETE and the input in the query string, where the ID is a string.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_delete_runs_with_delete_and_the_input_in_the_query_string(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->server->dispatch(
			$this->write_request(
				'DELETE',
				'core/content-delete',
				array(
					'where' => array(
						'type' => 'post',
						'id'   => (string) $post_id,
					),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The delete request should succeed.' );
		$this->assertSame( array( 'outcome' => 'trashed' ), $response->get_data(), 'The post should be reported as trashed.' );
		$this->assertSame( 'trash', get_post_status( $post_id ), 'The post should be in the Trash.' );
	}

	/**
	 * Returns `permanent` values as they arrive in a query string, and the outcome.
	 *
	 * @return array<string, array{permanent: string, outcome: string}> The cases.
	 */
	public static function data_permanent_query_string_values(): array {
		return array(
			'true'       => array( 'true', 'deleted' ),
			'1'          => array( '1', 'deleted' ),
			'false'      => array( 'false', 'trashed' ),
			'0'          => array( '0', 'trashed' ),
			'upper case' => array( 'TRUE', 'deleted' ),
		);
	}

	/**
	 * `permanent` in the query string is read as a boolean.
	 *
	 * @dataProvider data_permanent_query_string_values
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 *
	 * @param string $permanent The value in the query string.
	 * @param string $outcome   The expected outcome.
	 */
	public function test_delete_reads_permanent_from_the_query_string( string $permanent, string $outcome ): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->server->dispatch(
			$this->write_request(
				'DELETE',
				'core/content-delete',
				array(
					'where'     => array(
						'type' => 'post',
						'id'   => (string) $post_id,
					),
					'permanent' => $permanent,
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The delete request should succeed.' );
		$this->assertSame( array( 'outcome' => $outcome ), $response->get_data(), 'The outcome should follow the permanent value.' );

		clean_post_cache( $post_id );

		if ( 'deleted' === $outcome ) {
			$this->assertNull( get_post( $post_id ), 'The post should no longer exist.' );
		} else {
			$this->assertSame( 'trash', get_post_status( $post_id ), 'The post should be in the Trash.' );
		}
	}

	/**
	 * Returns `permanent` values in a query string that are not booleans.
	 *
	 * @return array<string, array{permanent: string}> The values.
	 */
	public static function data_permanent_query_string_values_that_are_not_booleans(): array {
		return array(
			'yes'          => array( 'yes' ),
			'2'            => array( '2' ),
			'on'           => array( 'on' ),
			'empty string' => array( '' ),
		);
	}

	/**
	 * A `permanent` value in the query string that is not a boolean fails input validation, and
	 * the post is unchanged.
	 *
	 * Covers S12 (C5).
	 *
	 * @dataProvider data_permanent_query_string_values_that_are_not_booleans
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 *
	 * @param string $permanent The value in the query string.
	 */
	public function test_delete_with_a_permanent_value_that_is_not_a_boolean_returns_400( string $permanent ): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$before   = $this->snapshot( $post_id );
		$response = $this->server->dispatch(
			$this->write_request(
				'DELETE',
				'core/content-delete',
				array(
					'where'     => array(
						'type' => 'post',
						'id'   => (string) $post_id,
					),
					'permanent' => $permanent,
				)
			)
		);

		$this->assert_error_response( $response, 400, 'ability_invalid_input', 'A permanent value that is not a boolean' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'The post should not change.' );
	}

	/**
	 * A create sent with POST but with the input in the query string fails input validation.
	 *
	 * Covers C5.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_create_with_the_input_in_the_query_string_returns_400(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/core/content-create/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'data' => array(
						'type'  => 'post',
						'title' => 'Misplaced input',
					),
				),
			)
		);

		$before   = $this->snapshot();
		$response = $this->server->dispatch( $request );

		$this->assert_error_response( $response, 400, 'ability_invalid_input', 'A create with the input in the query string' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A delete sent with DELETE but with the input in a JSON body fails input validation.
	 *
	 * Covers C5.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_delete_with_the_input_in_a_json_body_returns_400(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$request = new WP_REST_Request( 'DELETE', '/wp-abilities/v1/abilities/core/content-delete/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'where' => array(
							'type' => 'post',
							'id'   => $post_id,
						),
					),
				)
			)
		);

		$before   = $this->snapshot( $post_id );
		$response = $this->server->dispatch( $request );

		$this->assert_error_response( $response, 400, 'ability_invalid_input', 'A delete with the input in a JSON body' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'The post should not change.' );
	}

	/**
	 * Returns write abilities with a method they do not take.
	 *
	 * @return array<string, array{ability: string, method: string}> The cases.
	 */
	public static function data_wrong_methods_for_write_abilities(): array {
		return array(
			'GET to create'    => array( 'core/content-create', 'GET' ),
			'DELETE to create' => array( 'core/content-create', 'DELETE' ),
			'GET to update'    => array( 'core/content-update', 'GET' ),
			'DELETE to update' => array( 'core/content-update', 'DELETE' ),
			'GET to delete'    => array( 'core/content-delete', 'GET' ),
			'POST to delete'   => array( 'core/content-delete', 'POST' ),
		);
	}

	/**
	 * A write ability called with a method it does not take is rejected and writes nothing.
	 *
	 * @dataProvider data_wrong_methods_for_write_abilities
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 *
	 * @param string $ability The ability name.
	 * @param string $method  The HTTP method.
	 */
	public function test_write_ability_with_a_wrong_method_returns_405( string $ability, string $method ): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$inputs  = array(
			'core/content-create' => array( 'data' => array( 'type' => 'post' ) ),
			'core/content-update' => array(
				'where' => array(
					'type' => 'post',
					'id'   => $post_id,
				),
				'data'  => array( 'title' => 'Wrong method' ),
			),
			'core/content-delete' => array(
				'where' => array(
					'type' => 'post',
					'id'   => $post_id,
				),
			),
		);
		$before  = get_post( $post_id )->to_array();

		$response = $this->server->dispatch( $this->write_request( $method, $ability, $inputs[ $ability ] ) );

		$this->assert_error_response( $response, 405, 'rest_ability_invalid_method', 'A wrong method' );
		$this->assertSame( $before, get_post( $post_id )->to_array(), 'The post should not change.' );
	}

	/**
	 * A logged-out user is refused as unauthenticated.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_logged_out_create_receives_401(): void {
		wp_set_current_user( 0 );

		$before   = $this->snapshot();
		$response = $this->server->dispatch( $this->write_request( 'POST', 'core/content-create', array( 'data' => array( 'type' => 'post' ) ) ) );

		$this->assert_error_response( $response, 401, 'rest_ability_cannot_execute', 'A logged-out create' );
		$this->assertSame( $before, $this->snapshot(), 'A logged-out create should write nothing.' );
	}

	/**
	 * A user the permission callback refuses is forbidden.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_subscriber_create_receives_403(): void {
		wp_set_current_user( self::$subscriber_id );

		$before   = $this->snapshot();
		$response = $this->server->dispatch( $this->write_request( 'POST', 'core/content-create', array( 'data' => array( 'type' => 'post' ) ) ) );

		$this->assert_error_response( $response, 403, 'rest_ability_cannot_execute', 'A subscriber create' );
		$this->assertSame( $before, $this->snapshot(), 'A subscriber create should write nothing.' );
	}

	/**
	 * Input that fails the schema is rejected.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_create_with_input_that_fails_the_schema_returns_400(): void {
		$before   = $this->snapshot();
		$response = $this->server->dispatch(
			$this->write_request(
				'POST',
				'core/content-create',
				array(
					'data' => array(
						'type'          => 'post',
						'post_password' => 'secret',
					),
				)
			)
		);

		$this->assert_error_response( $response, 400, 'ability_invalid_input', 'A create that fails the schema' );
		$this->assertSame( $before, $this->snapshot(), 'A create that fails the schema should write nothing.' );
	}

	/**
	 * The not-found error of an update reaches the response with its status.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_update_of_a_missing_post_returns_404(): void {
		$before   = $this->snapshot();
		$response = $this->server->dispatch(
			$this->write_request(
				'POST',
				'core/content-update',
				array(
					'where' => array(
						'type' => 'post',
						'id'   => 987654321,
					),
					'data'  => array( 'title' => 'Missing' ),
				)
			)
		);

		$this->assert_error_response( $response, 404, 'content_not_found', 'An update of a missing post' );
		$this->assertSame( $before, $this->snapshot(), 'An update of a missing post should write nothing.' );
	}

	/**
	 * The edit error of an update reaches the response with its status.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_update_of_a_post_the_user_cannot_edit_returns_403(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$admin_id,
				'post_status' => 'publish',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$before   = $this->snapshot( $post_id );
		$response = $this->server->dispatch(
			$this->write_request(
				'POST',
				'core/content-update',
				array(
					'where' => array(
						'type' => 'post',
						'id'   => $post_id,
					),
					'data'  => array( 'title' => 'Not mine' ),
				)
			)
		);

		$this->assert_error_response( $response, 403, 'content_cannot_edit', 'An update of a post the user cannot edit' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'The post the user cannot edit should not change.' );
	}

	/**
	 * The invalid attribute error of a create reaches the response with its status.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_create_with_an_unsupported_attribute_returns_400(): void {
		$before   = $this->snapshot();
		$response = $this->server->dispatch(
			$this->write_request(
				'POST',
				'core/content-create',
				array(
					'data' => array(
						'type'   => 'post',
						'title'  => 'With a parent',
						'parent' => 1,
					),
				)
			)
		);

		$this->assert_error_response( $response, 400, 'content_invalid_attribute', 'A create with an unsupported attribute' );
		$this->assertSame( array( 'data[parent]' => 'The post type is not hierarchical.' ), $response->get_data()['data']['params'] ?? null, 'The params should reach the response.' );
		$this->assertSame( $before, $this->snapshot(), 'A create with an unsupported attribute should write nothing.' );
	}

	/**
	 * The already-trashed error of a delete reaches the response with its status.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_trashing_a_trashed_post_returns_410(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_trash_post( $post_id );

		$before   = $this->snapshot( $post_id );
		$response = $this->server->dispatch(
			$this->write_request(
				'DELETE',
				'core/content-delete',
				array(
					'where' => array(
						'type' => 'post',
						'id'   => (string) $post_id,
					),
				)
			)
		);

		$this->assert_error_response( $response, 410, 'content_already_trashed', 'Trashing a trashed post' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'The trashed post should stay as it was.' );
	}
}
