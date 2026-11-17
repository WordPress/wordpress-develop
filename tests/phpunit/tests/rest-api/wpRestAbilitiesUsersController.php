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
	 * Administrator user ID, a super admin on multisite.
	 *
	 * @var int
	 */
	protected static $manager_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * The run route for the core/users-query ability.
	 *
	 * @var string
	 */
	const RUN_ROUTE = '/wp-abilities/v1/abilities/core/users-query/run';

	/**
	 * The run route for the core/user-create ability.
	 *
	 * @var string
	 */
	const CREATE_ROUTE = '/wp-abilities/v1/abilities/core/user-create/run';

	/**
	 * The run route for the core/user-update ability.
	 *
	 * @var string
	 */
	const UPDATE_ROUTE = '/wp-abilities/v1/abilities/core/user-update/run';

	/**
	 * The run route for the core/user-delete ability.
	 *
	 * @var string
	 */
	const DELETE_ROUTE = '/wp-abilities/v1/abilities/core/user-delete/run';

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
		self::$manager_id    = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_id     = $factory->user->create( array( 'role' => 'editor' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$manager_id );
		}

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
		if ( is_multisite() ) {
			revoke_super_admin( self::$manager_id );
		}

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

		$response = $this->server->dispatch( $this->run_request( array( 'where' => array( 'ids' => array( self::$admin_id ) ) ) ) );

		$this->assertSame( 401, $response->get_status(), 'A logged-out request should be rejected as unauthenticated.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_subscriber_filtering_by_roles_receives_403(): void {
		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch( $this->run_request( array( 'where' => array( 'roles' => array( 'administrator' ) ) ) ) );

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
					'where'  => array( 'ids' => array( $author_id ) ),
					'fields' => array( 'id', 'name', 'username', 'email', 'roles' ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'A subscriber should be able to read an author with a published post.' );
		$this->assertSame( $author_id, $data['items'][0]['id'], 'The lookup should return the requested author.' );
		$this->assertSame( 'Public REST Author', $data['items'][0]['name'], 'The display name should be returned to a subscriber.' );
		$this->assertArrayNotHasKey( 'username', $data['items'][0], 'The username should not be returned to a subscriber.' );
		$this->assertArrayNotHasKey( 'email', $data['items'][0], 'The email address should not be returned to a subscriber.' );
		$this->assertArrayNotHasKey( 'roles', $data['items'][0], 'The roles should not be returned to a subscriber.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_editor_reading_a_user_without_public_posts_receives_an_empty_list(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_nicename' => 'rest-users-pending-author',
				'description'   => 'Pending REST author biography.',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'pending',
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'where'  => array( 'slugs' => array( 'rest-users-pending-author' ) ),
					'fields' => array( 'id', 'name', 'description', 'email' ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'An editor should be able to query a user of the site without public posts.' );
		$this->assertSame( array(), $data['items'], 'A user without public posts should not be returned to an editor.' );
		$this->assertSame(
			array(
				'total'       => 0,
				'total_pages' => 0,
			),
			$data['pagination'],
			'The empty result should report zero totals.'
		);
	}

	/**
	 * @ticket 64657
	 */
	public function test_subscriber_filtering_a_public_author_by_email_or_username_is_rejected(): void {
		$this->create_public_author(
			array(
				'user_login' => 'rest_users_username_lookup',
				'user_email' => 'rest-users-email-lookup@example.org',
			)
		);

		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch( $this->run_request( array( 'where' => array( 'email' => 'rest-users-email-lookup@example.org' ) ) ) );

		$this->assertSame( 400, $response->get_status(), 'Filtering by email should fail validation, since email is not a filter.' );
		$this->assertSame( 'ability_invalid_input', $response->get_data()['code'], 'The email error should come from input validation.' );

		$response = $this->server->dispatch( $this->run_request( array( 'where' => array( 'usernames' => array( 'rest_users_username_lookup' ) ) ) ) );

		$this->assertSame( 403, $response->get_status(), 'Filtering by username should require permission to list users, even for a public author.' );
		$this->assertSame( 'rest_ability_cannot_execute', $response->get_data()['code'], 'The username error should come from the permission check.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_subscriber_query_only_lists_public_authors(): void {
		$author_id = $this->create_public_author();

		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch( $this->run_request( array( 'fields' => array( 'id' ) ) ) );
		$user_ids = wp_list_pluck( $response->get_data()['items'], 'id' );

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
		$this->assertArrayHasKey( 'items', $data, 'Collection mode should return the users as items.' );
		$this->assertContains( self::$subscriber_id, wp_list_pluck( $data['items'], 'id' ), 'A user without published posts should be listed for an administrator.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_admin_query_ids_limits_results(): void {
		$first  = self::factory()->user->create( array( 'user_login' => 'rest_users_include_a' ) );
		$second = self::factory()->user->create( array( 'user_login' => 'rest_users_include_b' ) );
		$third  = self::factory()->user->create( array( 'user_login' => 'rest_users_include_c' ) );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					// Deliberately pass IDs in the opposite of the expected login order.
					'where'  => array( 'ids' => array( $third, $first ) ),
					'fields' => array( 'id' ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The ID list query should succeed.' );
		$this->assertSame( array( $first, $third ), wp_list_pluck( $data['items'], 'id' ), 'Included users should be returned by login, regardless of the order of the ID list.' );
		$this->assertNotContains( $second, wp_list_pluck( $data['items'], 'id' ), 'Users outside the ID list should not be returned.' );
		$this->assertSame( 2, $data['pagination']['total'], 'The total should only count the included users.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_get_single_user_by_id(): void {
		$response = $this->server->dispatch( $this->run_request( array( 'where' => array( 'ids' => array( self::$subscriber_id ) ) ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The ID lookup should succeed.' );
		$this->assertSame( self::$subscriber_id, $data['items'][0]['id'], 'The ID lookup should return the requested user.' );
		$this->assertCount( 1, $data['items'], 'A single-user lookup should return only the requested user.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$data['pagination'],
			'A single-user lookup should report one user on one page.'
		);
	}

	/**
	 * @ticket 64657
	 */
	public function test_get_single_user_by_slug(): void {
		$user_id = self::factory()->user->create( array( 'user_nicename' => 'rest-users-slug' ) );

		$response = $this->server->dispatch( $this->run_request( array( 'where' => array( 'slugs' => array( 'rest-users-slug' ) ) ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The slug lookup should succeed.' );
		$this->assertSame( $user_id, $data['items'][0]['id'], 'The slug lookup should return the matching user.' );
		$this->assertSame( 'rest-users-slug', $data['items'][0]['slug'], 'The slug lookup should return the user slug.' );
		$this->assertCount( 1, $data['items'], 'A slug lookup should return only the matching user.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$data['pagination'],
			'A slug lookup should report one user on one page.'
		);
	}

	/**
	 * @ticket 64657
	 */
	public function test_wrong_http_method_returns_405(): void {
		$request = new WP_REST_Request( 'POST', self::RUN_ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => array( 'where' => array( 'ids' => array( self::$admin_id ) ) ) ) ) );

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
					'pagination' => array(
						'page'     => 1,
						'per_page' => 2,
					),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The paginated query should succeed.' );
		$this->assertCount( 2, $data['items'], 'The first page should be limited to per_page users.' );
		$this->assertGreaterThanOrEqual( 5, $data['pagination']['total'], 'The total should count users across all pages.' );
		$this->assertSame( (int) ceil( $data['pagination']['total'] / 2 ), $data['pagination']['total_pages'], 'The total pages should match the total and per_page.' );
	}

	/**
	 * @ticket 64657
	 */
	public function test_out_of_range_page_returns_404(): void {
		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'pagination' => array(
						'page'     => 999,
						'per_page' => 1,
					),
				)
			)
		);

		$this->assertSame( 404, $response->get_status(), 'Requesting a page past the last one should return a 404 error.' );
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
					'where'      => array( 'ids' => $second . ',' . $first ),
					'fields'     => 'id,name',
					'pagination' => array(
						'page'     => '2',
						'per_page' => '1',
					),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'String values from a query string should be accepted.' );
		$this->assertSame( array( $second ), wp_list_pluck( $data['items'], 'id' ), 'The second page of one user should hold the second included user by login.' );
		$this->assertSameSets( array( 'id', 'name' ), array_keys( $data['items'][0] ), 'Only the fields from the comma-separated list should be returned.' );
		$this->assertSame( 2, $data['pagination']['total'], 'The total should count both included users.' );
		$this->assertSame( 2, $data['pagination']['total_pages'], 'The total pages should be computed from the string per_page value.' );
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
					'where'  => array(
						'ids'                 => $author_id . ',' . $non_author_id,
						'has_published_posts' => 'true',
					),
					'fields' => 'id',
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The string "true" should be accepted for has_published_posts.' );
		$this->assertSame( array( $author_id ), wp_list_pluck( $data['items'], 'id' ), 'Only the included user with a published post should be returned.' );
	}

	/**
	 * Builds a run request: a POST request carries the input in a JSON body, and other
	 * requests carry it in the query string.
	 *
	 * @param string $method The HTTP method.
	 * @param string $route  The run route.
	 * @param mixed  $input  The ability input, or null to send none.
	 * @return WP_REST_Request The request.
	 */
	private function write_request( string $method, string $route, $input ): WP_REST_Request {
		$request = new WP_REST_Request( $method, $route );
		$params  = null === $input ? array() : array( 'input' => $input );

		if ( 'POST' === $method ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $params ) );
		} else {
			$request->set_query_params( $params );
		}

		return $request;
	}

	/**
	 * Creates an author with a published post.
	 *
	 * @param string $login The username.
	 * @return array{user: int, post: int} The IDs of the user and the post.
	 */
	private function create_author_with_post( string $login ): array {
		$user_id = self::factory()->user->create(
			array(
				'role'       => 'author',
				'user_login' => $login,
				'user_email' => "{$login}@example.test",
			)
		);

		return array(
			'user' => $user_id,
			'post' => self::factory()->post->create(
				array(
					'post_author' => $user_id,
					'post_status' => 'publish',
				)
			),
		);
	}

	/**
	 * A user is created through the run route with a POST request.
	 *
	 * @ticket XXXXX
	 * @since 7.3.0
	 */
	public function test_creates_a_user_with_a_post_request(): void {
		wp_set_current_user( self::$manager_id );

		$response = $this->server->dispatch(
			$this->write_request(
				'POST',
				self::CREATE_ROUTE,
				array(
					'data'   => array(
						'username' => 'uwtruncreated',
						'email'    => 'uwt-run-created@example.test',
					),
					'fields' => array( 'id', 'username' ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Creating a user should succeed.' );
		$this->assertSame( 'uwtruncreated', $data['username'] ?? null, 'The output should hold the new username.' );
		$this->assertSame( $data['id'] ?? null, get_user_by( 'login', 'uwtruncreated' )->ID ?? 0, 'The output ID should be the new user.' );
	}

	/**
	 * A user is updated through the run route with a POST request.
	 *
	 * @ticket XXXXX
	 * @since 7.3.0
	 */
	public function test_updates_a_user_with_a_post_request(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( self::$manager_id );

		$response = $this->server->dispatch(
			$this->write_request(
				'POST',
				self::UPDATE_ROUTE,
				array(
					'where' => array( 'id' => $user_id ),
					'data'  => array( 'name' => 'Updated By Run' ),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'Updating a user should succeed.' );
		$this->assertSame( $user_id, $response->get_data()['id'] ?? null, 'The output should hold the user ID.' );
		$this->assertSame( 'Updated By Run', get_userdata( $user_id )->display_name, 'The name should be stored.' );
	}

	/**
	 * A user is deleted through the run route with a POST request that carries the input
	 * in its body.
	 *
	 * @ticket XXXXX
	 * @since 7.3.0
	 */
	public function test_deletes_a_user_with_a_post_request(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( self::$manager_id );

		$response = $this->server->dispatch(
			$this->write_request(
				'POST',
				self::DELETE_ROUTE,
				array(
					'where'    => array( 'id' => $user_id ),
					'reassign' => false,
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'Deleting a user should succeed.' );
		$this->assertSame( array( 'outcome' => is_multisite() ? 'removed' : 'deleted' ), $response->get_data(), 'The output should give the outcome.' );
		$this->assertFalse( is_user_member_of_blog( $user_id, get_current_blog_id() ), 'The user should be gone from the site.' );
	}

	/**
	 * Each user write ability takes POST requests only: a GET request and a DELETE
	 * request, with the input in the query string, are refused.
	 *
	 * @ticket XXXXX
	 * @since 7.3.0
	 *
	 * @dataProvider data_wrong_methods
	 *
	 * @param string $method The HTTP method.
	 * @param string $route  The run route.
	 */
	public function test_wrong_method_returns_405( string $method, string $route ): void {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( self::$manager_id );

		$inputs = array(
			self::CREATE_ROUTE => array(
				'data' => array(
					'username' => 'uwtrunmethod',
					'email'    => 'uwt-run-method@example.test',
				),
			),
			self::UPDATE_ROUTE => array(
				'where' => array( 'id' => $user_id ),
				'data'  => array( 'name' => 'Wrong Method' ),
			),
			self::DELETE_ROUTE => array(
				'where'    => array( 'id' => (string) $user_id ),
				'reassign' => 'false',
			),
		);

		$response = $this->server->dispatch( $this->write_request( $method, $route, $inputs[ $route ] ) );

		$this->assertSame( 405, $response->get_status(), 'The method should be refused.' );
		$this->assertSame( 'rest_ability_invalid_method', $response->get_data()['code'] ?? null, 'The error should identify the invalid HTTP method.' );
		$this->assertFalse( get_user_by( 'login', 'uwtrunmethod' ), 'No user should be created.' );
		$this->assertSame( 'author', get_userdata( $user_id )->roles[0] ?? null, 'The user should stay.' );
		$this->assertNotSame( 'Wrong Method', get_userdata( $user_id )->display_name, 'The user should not be updated.' );
	}

	/**
	 * Returns HTTP methods the user write abilities do not take.
	 *
	 * @return array<string, array{method: string, route: string}> The method and the route.
	 */
	public static function data_wrong_methods(): array {
		return array(
			'create with GET'    => array( 'GET', self::CREATE_ROUTE ),
			'create with DELETE' => array( 'DELETE', self::CREATE_ROUTE ),
			'update with GET'    => array( 'GET', self::UPDATE_ROUTE ),
			'update with DELETE' => array( 'DELETE', self::UPDATE_ROUTE ),
			'delete with GET'    => array( 'GET', self::DELETE_ROUTE ),
			'delete with DELETE' => array( 'DELETE', self::DELETE_ROUTE ),
		);
	}

	/**
	 * Refused calls of the user write abilities carry their status.
	 *
	 * @ticket XXXXX
	 * @since 7.3.0
	 *
	 * @dataProvider data_refused_calls
	 *
	 * @param string      $route      The run route.
	 * @param string      $caller     The caller: `manager`, `editor`, `subscriber`, or an empty string to log out.
	 * @param string      $input_name The input to send.
	 * @param int         $status     The expected status.
	 * @param string|null $code       The expected error code, or null for any.
	 */
	public function test_refused_calls_carry_their_status( string $route, string $caller, string $input_name, int $status, ?string $code ): void {
		$user_id = self::factory()->user->create(
			array(
				'role'       => 'author',
				'user_login' => 'uwtruntaken',
				'user_email' => 'uwt-run-taken@example.test',
			)
		);
		$callers = array(
			''           => 0,
			'manager'    => self::$manager_id,
			'editor'     => self::$editor_id,
			'subscriber' => self::$subscriber_id,
		);
		$inputs  = array(
			'new user'       => array(
				'data' => array(
					'username' => 'uwtrunrefused',
					'email'    => 'uwt-run-refused@example.test',
				),
			),
			'no email'       => array( 'data' => array( 'username' => 'uwtrunrefused' ) ),
			'taken username' => array(
				'data' => array(
					'username' => 'uwtruntaken',
					'email'    => 'uwt-run-other@example.test',
				),
			),
			'missing user'   => array(
				'where' => array( 'id' => 987654321 ),
				'data'  => array( 'name' => 'Refused' ),
			),
			'author'         => array(
				'where' => array( 'id' => $user_id ),
				'data'  => array( 'name' => 'Refused' ),
			),
			'delete author'  => array(
				'where'    => array( 'id' => $user_id ),
				'reassign' => false,
			),
			'reassign true'  => array(
				'where'    => array( 'id' => $user_id ),
				'reassign' => 'true',
			),
		);

		wp_set_current_user( $callers[ $caller ] );

		$response = $this->server->dispatch( $this->write_request( 'POST', $route, $inputs[ $input_name ] ) );

		$this->assertSame( $status, $response->get_status(), "The call should be refused with status {$status}." );
		if ( null !== $code ) {
			$this->assertSame( $code, $response->get_data()['code'] ?? null, "The error code should be {$code}." );
		}
		$this->assertFalse( get_user_by( 'login', 'uwtrunrefused' ), 'No user should be created.' );
		$this->assertSame( 'uwtruntaken', get_userdata( $user_id )->user_login ?? null, 'The author should stay.' );
		$this->assertNotSame( 'Refused', get_userdata( $user_id )->display_name, 'The author should not be updated.' );
	}

	/**
	 * Returns calls of the user write abilities that are refused.
	 *
	 * @return array<string, array{route: string, caller: string, input_name: string, status: int, code: string|null}>
	 *         The route, the caller, the input, the status and the error code.
	 */
	public static function data_refused_calls(): array {
		return array(
			'create while logged out'        => array( self::CREATE_ROUTE, '', 'new user', 401, 'rest_ability_cannot_execute' ),
			'create by a subscriber'         => array( self::CREATE_ROUTE, 'subscriber', 'new user', 403, 'rest_ability_cannot_execute' ),
			'create with invalid input'      => array( self::CREATE_ROUTE, 'manager', 'no email', 400, null ),
			'create with a taken username'   => array( self::CREATE_ROUTE, 'manager', 'taken username', 400, 'user_exists' ),
			'update of a missing user'       => array( self::UPDATE_ROUTE, 'manager', 'missing user', 404, 'user_not_found' ),
			'update of another user'         => array( self::UPDATE_ROUTE, 'editor', 'author', 403, 'user_cannot_edit' ),
			'delete by a subscriber'         => array( self::DELETE_ROUTE, 'subscriber', 'delete author', 403, 'rest_ability_cannot_execute' ),
			'delete with reassign true text' => array( self::DELETE_ROUTE, 'manager', 'reassign true', 400, 'user_invalid_reassign' ),
		);
	}

	/**
	 * A POST request carries `reassign` in its JSON body with its type: false and a user
	 * ID are taken, as a boolean or an integer and as a string, and every other value is
	 * refused without deleting anything.
	 *
	 * @ticket XXXXX
	 * @since 7.3.0
	 *
	 * @dataProvider data_reassign_values_in_the_body
	 *
	 * @param mixed       $reassign The reassign value; `heir` stands for the editor's ID,
	 *                              `heir as text` for that ID as a string, and `absent`
	 *                              sends none.
	 * @param string|null $owner    Who owns the post afterwards: `heir`, `first` for user 1,
	 *                              `nobody` when no one is reassigned, or null when refused.
	 * @param string|null $code     The expected error code, or null when the user is deleted.
	 */
	public function test_delete_takes_reassign_from_the_json_body( $reassign, ?string $owner, ?string $code ): void {
		$target = $this->create_author_with_post( 'uwt_run_reassign' );
		$input  = array( 'where' => array( 'id' => $target['user'] ) );

		if ( 'heir' === $reassign ) {
			$input['reassign'] = self::$editor_id;
		} elseif ( 'heir as text' === $reassign ) {
			$input['reassign'] = (string) self::$editor_id;
		} elseif ( 'absent' !== $reassign ) {
			$input['reassign'] = $reassign;
		}

		wp_set_current_user( self::$manager_id );

		$response = $this->server->dispatch( $this->write_request( 'POST', self::DELETE_ROUTE, $input ) );
		$post     = get_post( $target['post'] );

		if ( null === $owner ) {
			$this->assertSame( 400, $response->get_status(), 'The reassign value should be refused.' );
			$this->assertSame( $code, $response->get_data()['code'] ?? null, "The error code should be {$code}." );
			$this->assertTrue( is_user_member_of_blog( $target['user'], get_current_blog_id() ), 'The user should stay.' );
			$this->assertSame( $target['user'], (int) $post->post_author, 'The post should keep its author.' );
			$this->assertSame( 'publish', $post->post_status, 'The post should stay published.' );
			return;
		}

		$this->assertSame( 200, $response->get_status(), 'The user should be deleted.' );

		$owners = array(
			'heir'   => self::$editor_id,
			'first'  => 1,
			'nobody' => $target['user'],
		);
		if ( 'nobody' === $owner && ! is_multisite() ) {
			$this->assertSame( 'trash', $post->post_status, 'The post should go to the trash.' );
		} else {
			$this->assertSame( $owners[ $owner ], (int) $post->post_author, 'The post should belong to the expected user.' );
			$this->assertSame( 'publish', $post->post_status, 'The post should stay published.' );
		}
	}

	/**
	 * Returns reassign values as a JSON body carries them.
	 *
	 * @return array<string, array{reassign: mixed, owner: string|null, code: string|null}>
	 *         The value, who owns the post afterwards, and the error code.
	 */
	public static function data_reassign_values_in_the_body(): array {
		return array(
			'false'         => array( false, 'nobody', null ),
			'false as text' => array( 'false', 'nobody', null ),
			'ID'            => array( 'heir', 'heir', null ),
			'ID as text'    => array( 'heir as text', 'heir', null ),
			'one'           => array( 1, 'first', null ),
			'one as text'   => array( '1', 'first', null ),
			'true'          => array( true, null, 'user_invalid_reassign' ),
			'true as text'  => array( 'true', null, 'user_invalid_reassign' ),
			'zero'          => array( 0, null, 'ability_invalid_input' ),
			'zero as text'  => array( '0', null, 'ability_invalid_input' ),
			'empty'         => array( '', null, 'ability_invalid_input' ),
			'negative'      => array( -1, null, 'ability_invalid_input' ),
			'fraction'      => array( 7.5, null, 'ability_invalid_input' ),
			'text'          => array( 'abc', null, 'ability_invalid_input' ),
			'null'          => array( null, null, 'ability_invalid_input' ),
			'list'          => array( array( 1 ), null, 'ability_invalid_input' ),
			'absent'        => array( 'absent', null, 'ability_invalid_input' ),
		);
	}
}
