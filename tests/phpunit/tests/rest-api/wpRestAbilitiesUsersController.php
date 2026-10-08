<?php

declare( strict_types=1 );

/**
 * Tests dispatching the core/users-query ability through the Abilities REST run endpoint.
 *
 * @covers WP_Abilities_Users
 *
 * @group abilities-api
 * @group restapi
 */
class Tests_REST_API_WpRestAbilitiesUsersController extends WP_UnitTestCase {

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
	 * The run route for the core/users-query ability.
	 *
	 * @var string
	 */
	const RUN_ROUTE = '/wp-abilities/v1/abilities/core/users-query/run';

	/**
	 * Sets up users and registers the core abilities.
	 *
	 * @since 7.2.0
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

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
	public static function tear_down_after_class(): void {
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
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

	/**
	 * Creates an author with a published post.
	 *
	 * @param array<string, mixed> $args Optional. Arguments for the user. Default empty array.
	 * @return int The author's user ID.
	 */
	private function create_public_author( array $args = array() ): int {
		$author_id = self::factory()->user->create( array_merge( array( 'role' => 'author' ), $args ) );
		self::factory()->post->create(
			array(
				'post_author' => $author_id,
				'post_status' => 'publish',
			)
		);

		return $author_id;
	}

	/**
	 * @ticket 64657
	 */
	public function test_logged_out_user_receives_401(): void {
		wp_set_current_user( 0 );

		$response = $this->server->dispatch( $this->run_request( array( 'id' => self::$admin_id ) ) );

		$this->assertSame( 401, $response->get_status(), 'A logged-out request should be rejected as unauthenticated.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_subscriber_filtering_by_roles_receives_403(): void {
		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch( $this->run_request( array( 'roles' => array( 'administrator' ) ) ) );

		$this->assertSame( 403, $response->get_status(), 'Filtering by role should require permission to list users.' );
		$this->assertSame( 'rest_ability_cannot_execute', $response->get_data()['code'], 'The error should come from the permission check.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_subscriber_reading_a_public_author_receives_public_fields(): void {
		$author_id = $this->create_public_author( array( 'display_name' => 'Public REST Author' ) );

		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'id'     => $author_id,
					'fields' => array( 'id', 'name', 'username', 'email', 'roles' ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'A subscriber should be able to read an author with a published post.' );
		$this->assertSame( $author_id, $data['id'], 'The lookup should return the requested author.' );
		$this->assertSame( 'Public REST Author', $data['name'], 'The display name should be returned to a subscriber.' );
		$this->assertArrayNotHasKey( 'username', $data, 'The username should not be returned to a subscriber.' );
		$this->assertArrayNotHasKey( 'email', $data, 'The email address should not be returned to a subscriber.' );
		$this->assertArrayNotHasKey( 'roles', $data, 'The roles should not be returned to a subscriber.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_subscriber_looking_up_a_public_author_by_email_receives_403(): void {
		$this->create_public_author( array( 'user_email' => 'rest-users-email-lookup@example.org' ) );

		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch( $this->run_request( array( 'email' => 'rest-users-email-lookup@example.org' ) ) );

		$this->assertSame( 403, $response->get_status(), 'Looking up another user by email should require permission to list or edit users, even for a public author.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_subscriber_query_only_lists_public_authors(): void {
		$author_id = $this->create_public_author();

		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch( $this->run_request( array( 'fields' => array( 'id' ) ) ) );
		$user_ids = wp_list_pluck( $response->get_data()['users'], 'id' );

		$this->assertSame( 200, $response->get_status(), 'A subscriber should be able to query users.' );
		$this->assertContains( $author_id, $user_ids, 'An author with a published post should be listed for a subscriber.' );
		$this->assertNotContains( self::$admin_id, $user_ids, 'A user without published posts should not be listed for a subscriber.' );
		$this->assertNotContains( self::$subscriber_id, $user_ids, 'The subscriber should not be listed without published posts.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_admin_query_lists_users_without_published_posts(): void {
		$response = $this->server->dispatch( $this->run_request( array( 'fields' => array( 'id' ) ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'An administrator should be able to query users.' );
		$this->assertArrayHasKey( 'users', $data, 'Collection mode should return a users list.' );
		$this->assertContains( self::$subscriber_id, wp_list_pluck( $data['users'], 'id' ), 'A user without published posts should be listed for an administrator.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_admin_query_include_limits_results(): void {
		$first  = self::factory()->user->create( array( 'user_login' => 'rest_users_include_a' ) );
		$second = self::factory()->user->create( array( 'user_login' => 'rest_users_include_b' ) );
		$third  = self::factory()->user->create( array( 'user_login' => 'rest_users_include_c' ) );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					// Deliberately pass IDs in the opposite of the expected login order.
					'include' => array( $third, $first ),
					'fields'  => array( 'id' ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The include query should succeed.' );
		$this->assertSame( array( $first, $third ), wp_list_pluck( $data['users'], 'id' ), 'Included users should be returned by login, regardless of the include order.' );
		$this->assertNotContains( $second, wp_list_pluck( $data['users'], 'id' ), 'Users outside the include list should not be returned.' );
		$this->assertSame( 2, $data['total'], 'The total should only count the included users.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_get_single_user_by_id(): void {
		$response = $this->server->dispatch( $this->run_request( array( 'id' => self::$subscriber_id ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The ID lookup should succeed.' );
		$this->assertSame( self::$subscriber_id, $data['id'], 'The ID lookup should return the requested user.' );
		$this->assertArrayNotHasKey( 'users', $data, 'A single-user lookup should not return a users list.' );
		$this->assertArrayNotHasKey( 'total', $data, 'A single-user lookup should not return query totals.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_get_single_user_by_slug(): void {
		$user_id = self::factory()->user->create( array( 'user_nicename' => 'rest-users-slug' ) );

		$response = $this->server->dispatch( $this->run_request( array( 'slug' => 'rest-users-slug' ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The slug lookup should succeed.' );
		$this->assertSame( $user_id, $data['id'], 'The slug lookup should return the matching user.' );
		$this->assertSame( 'rest-users-slug', $data['slug'], 'The slug lookup should return the user slug.' );
		$this->assertArrayNotHasKey( 'users', $data, 'A slug lookup should not return a users list.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_wrong_http_method_returns_405(): void {
		$request = new WP_REST_Request( 'POST', self::RUN_ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => array( 'id' => self::$admin_id ) ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status(), 'A POST request to the read-only ability should be rejected.' );
		$this->assertSame( 'rest_ability_invalid_method', $response->get_data()['code'], 'The error should identify the invalid HTTP method.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_pagination_returns_totals_in_body(): void {
		self::factory()->user->create_many( 3 );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'per_page' => 2,
					'page'     => 1,
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The paginated query should succeed.' );
		$this->assertCount( 2, $data['users'], 'The first page should be limited to per_page users.' );
		$this->assertGreaterThanOrEqual( 5, $data['total'], 'The total should count users across all pages.' );
		$this->assertSame( (int) ceil( $data['total'] / 2 ), $data['total_pages'], 'The total pages should match the total and per_page.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_out_of_range_page_returns_400(): void {
		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'per_page' => 1,
					'page'     => 999,
				)
			)
		);

		$this->assertSame( 400, $response->get_status(), 'Requesting a page past the last one should return a 400 error.' );
		$this->assertSame( 'users_invalid_page_number', $response->get_data()['code'], 'The error should identify the invalid page number.' );
	}

	/**
	 * A GET request delivers every value as a string, and a list as a comma-separated string.
	 *
	 * @ticket 64657
	 */
	public function test_query_string_values_are_accepted(): void {
		$first  = self::factory()->user->create( array( 'user_login' => 'rest_users_query_string_a' ) );
		$second = self::factory()->user->create( array( 'user_login' => 'rest_users_query_string_b' ) );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'include'  => $second . ',' . $first,
					'fields'   => 'id,name',
					'per_page' => '1',
					'page'     => '2',
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'String values from a query string should be accepted.' );
		$this->assertSame( array( $second ), wp_list_pluck( $data['users'], 'id' ), 'The second page of one user should hold the second included user by login.' );
		$this->assertSame( array( 'id', 'name' ), array_keys( $data['users'][0] ), 'Only the fields from the comma-separated list should be returned.' );
		$this->assertSame( 2, $data['total'], 'The total should count both included users.' );
		$this->assertSame( 2, $data['total_pages'], 'The total pages should be computed from the string per_page value.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_has_published_posts_accepts_the_string_true(): void {
		$author_id     = $this->create_public_author();
		$non_author_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'include'             => $author_id . ',' . $non_author_id,
					'has_published_posts' => 'true',
					'fields'              => 'id',
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The string "true" should be accepted for has_published_posts.' );
		$this->assertSame( array( $author_id ), wp_list_pluck( $data['users'], 'id' ), 'Only the included user with a published post should be returned.' );
	}
}
