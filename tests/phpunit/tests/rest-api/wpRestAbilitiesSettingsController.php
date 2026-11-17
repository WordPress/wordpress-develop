<?php

declare( strict_types=1 );

/**
 * Tests for running the settings abilities through the REST API.
 *
 * @covers WP_Abilities_Settings
 *
 * @group abilities-api
 * @group restapi
 */
class Tests_REST_API_WpRestAbilitiesSettingsController extends WP_UnitTestCase {

	/**
	 * The REST server instance for the current test.
	 *
	 * @var Spy_REST_Server
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
	 * The run route for the core/settings-get ability.
	 *
	 * @var string
	 */
	const GET_RUN_ROUTE = '/wp-abilities/v1/abilities/core/settings-get/run';

	/**
	 * The run route for the core/settings-update ability.
	 *
	 * @var string
	 */
	const UPDATE_RUN_ROUTE = '/wp-abilities/v1/abilities/core/settings-update/run';

	/**
	 * Sets up users and registers the core abilities.
	 *
	 * The abilities are registered as on a request that uses the Abilities API before
	 * the REST server loads, so core must register its initial settings when abilities
	 * initialize (see _wp_register_initial_settings_for_abilities()).
	 *
	 * @since 7.2.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		global $wp_actions;

		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		$rest_api_init_count = $wp_actions['rest_api_init'] ?? null;
		unset( $wp_actions['rest_api_init'] );

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
		 * Restore the hooks and the `rest_api_init` count right away instead of after the
		 * class. The first test of a run snapshots the hooks and every test resets them to
		 * that snapshot, so changes left here would leak into every later test whenever this
		 * class runs first.
		 */
		remove_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		remove_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );
		if ( null !== $rest_api_init_count ) {
			$wp_actions['rest_api_init'] = $rest_api_init_count;
		}
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
		$wp_rest_server = new Spy_REST_Server();
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
		$request = new WP_REST_Request( 'GET', self::GET_RUN_ROUTE );
		$request->set_query_params( array( 'input' => $input ) );
		return $request;
	}

	/**
	 * Builds a run request for core/settings-update with the given ability input.
	 *
	 * A POST request carries the input in a JSON body, any other method in the query string.
	 *
	 * @param string               $method The HTTP method.
	 * @param array<string, mixed> $input  The ability input.
	 * @return WP_REST_Request The request.
	 */
	private function update_request( string $method, array $input ): WP_REST_Request {
		$request = new WP_REST_Request( $method, self::UPDATE_RUN_ROUTE );
		if ( 'POST' === $method ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( array( 'input' => $input ) ) );
		} else {
			$request->set_query_params( array( 'input' => $input ) );
		}
		return $request;
	}

	/**
	 * @ticket 64605
	 */
	public function test_logged_out_user_receives_401(): void {
		wp_set_current_user( 0 );

		$response = $this->server->dispatch( $this->run_request( array() ) );

		$this->assertSame( 401, $response->get_status(), 'A logged-out request should be rejected as unauthorized.' );
	}

	/**
	 * @ticket 64605
	 */
	public function test_subscriber_receives_403(): void {
		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch( $this->run_request( array() ) );

		$this->assertSame( 403, $response->get_status(), 'A user who cannot manage options should be rejected as forbidden.' );
	}

	/**
	 * @ticket 64605
	 */
	public function test_admin_receives_typed_settings(): void {
		update_option( 'blogname', 'REST Settings Site' );
		update_option( 'posts_per_page', 7 );

		$response = $this->server->dispatch( $this->run_request( array() ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'An administrator should read the settings.' );
		$this->assertSame( 'REST Settings Site', $data['title'], 'The site title should be returned under its REST API name.' );
		$this->assertSame( 7, $data['posts_per_page'], 'An integer setting should keep its type.' );
	}

	/**
	 * @ticket 64605
	 */
	public function test_group_filter_limits_settings(): void {
		$response = $this->server->dispatch( $this->run_request( array( 'group' => 'reading' ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'A group filter should be accepted.' );
		$this->assertArrayHasKey( 'posts_per_page', $data, 'A setting of the requested group should be returned.' );
		$this->assertArrayNotHasKey( 'title', $data, 'A setting of another group should be left out.' );
	}

	/**
	 * A GET request delivers a list as a comma-separated string.
	 *
	 * @ticket 64605
	 */
	public function test_fields_filter_from_query_string_limits_settings(): void {
		$response = $this->server->dispatch( $this->run_request( array( 'fields' => 'title,posts_per_page' ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'A comma-separated fields string should be accepted.' );
		$this->assertEqualSets( array( 'title', 'posts_per_page' ), array_keys( $data ), 'Only the settings from the comma-separated list should be returned.' );
	}

	/**
	 * An empty result is served as a JSON object, which the output schema requires.
	 *
	 * @ticket 66267
	 */
	public function test_no_matching_setting_is_encoded_as_an_empty_object(): void {
		$_GET = array(
			'input' => array(
				'group'  => 'reading',
				'fields' => 'title',
			),
		);

		$this->server->serve_request( self::GET_RUN_ROUTE );

		$this->assertSame( 200, $this->server->status, 'Filters that match no setting should be accepted.' );
		$this->assertSame( '{}', $this->server->sent_body, 'The empty result should be served as an empty object.' );
	}

	/**
	 * @ticket 64605
	 */
	public function test_unknown_group_receives_400(): void {
		$response = $this->server->dispatch( $this->run_request( array( 'group' => 'not-a-group' ) ) );

		$this->assertSame( 400, $response->get_status(), 'An unknown group should fail input validation.' );
		$this->assertSame( 'ability_invalid_input', $response->get_data()['code'], 'The error should identify the invalid input.' );
	}

	/**
	 * @ticket 64605
	 */
	public function test_wrong_http_method_returns_405(): void {
		$request = new WP_REST_Request( 'POST', self::GET_RUN_ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => array( 'group' => 'reading' ) ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status(), 'A POST request to the read-only ability should be rejected.' );
		$this->assertSame( 'rest_ability_invalid_method', $response->get_data()['code'], 'The error should identify the invalid HTTP method.' );
	}

	/**
	 * Users who cannot manage options cannot update settings, and nothing is written.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_update_refused_users
	 *
	 * @param string $user   The user: 'logged out' or 'subscriber'.
	 * @param int    $status The expected status.
	 */
	public function test_update_refused_for_users_without_manage_options( string $user, int $status ): void {
		wp_set_current_user( 'subscriber' === $user ? self::$subscriber_id : 0 );
		$before = get_option( 'blogname' );

		$response = $this->server->dispatch( $this->update_request( 'POST', array( 'data' => array( 'title' => 'Changed title' ) ) ) );

		$this->assertSame( $status, $response->get_status(), 'The update should be refused with the expected status.' );
		$this->assertSame( 'rest_ability_cannot_execute', $response->get_data()['code'], 'The error should identify the refused permission.' );
		$this->assertSame( $before, get_option( 'blogname' ), 'The site title should not change.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: int}> Users and the expected status.
	 */
	public static function data_update_refused_users(): array {
		return array(
			'logged out' => array( 'logged out', 401 ),
			'subscriber' => array( 'subscriber', 403 ),
		);
	}

	/**
	 * The update runs only through POST.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_update_wrong_methods
	 *
	 * @param string $method The HTTP method.
	 */
	public function test_update_wrong_http_method_returns_405( string $method ): void {
		$before = get_option( 'blogname' );

		$response = $this->server->dispatch( $this->update_request( $method, array( 'data' => array( 'title' => 'Changed title' ) ) ) );

		$this->assertSame( 405, $response->get_status(), 'The update should be rejected for this method.' );
		$this->assertSame( 'rest_ability_invalid_method', $response->get_data()['code'], 'The error should identify the invalid HTTP method.' );
		$this->assertSame( $before, get_option( 'blogname' ), 'The site title should not change.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}> HTTP methods other than POST.
	 */
	public static function data_update_wrong_methods(): array {
		return array(
			'GET'    => array( 'GET' ),
			'DELETE' => array( 'DELETE' ),
		);
	}

	/**
	 * Invalid update input is refused, and nothing is written.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_update_invalid_input_receives_400(): void {
		$before = get_option( 'blogname' );

		$response = $this->server->dispatch(
			$this->update_request(
				'POST',
				array(
					'data' => array(
						'title'          => 'Changed title',
						'posts_per_page' => 'abc',
					),
				)
			)
		);

		$this->assertSame( 400, $response->get_status(), 'Invalid input should be refused.' );
		$this->assertSame( 'ability_invalid_input', $response->get_data()['code'], 'The error should identify the invalid input.' );
		$this->assertSame( $before, get_option( 'blogname' ), 'The site title should not change.' );
	}

	/**
	 * An administrator updates settings and receives the saved values.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_update_admin_receives_saved_settings(): void {
		$response = $this->server->dispatch(
			$this->update_request(
				'POST',
				array(
					'data' => array(
						'title'          => 'Updated Site',
						'posts_per_page' => '12',
					),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'An administrator should update the settings.' );
		$this->assertSameSets( array( 'title', 'posts_per_page' ), array_keys( $data ), 'Only the updated settings should be returned.' );
		$this->assertSame( 'Updated Site', $data['title'], 'The saved site title should be returned.' );
		$this->assertSame( 12, $data['posts_per_page'], 'The saved number of posts per page should be returned as an integer.' );
		$this->assertSame( 'Updated Site', get_option( 'blogname' ), 'The site title should be saved.' );
	}

	/**
	 * A deleted setting without a default is left out, and the empty result is sent as an object.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_update_null_without_default_returns_an_empty_object(): void {
		update_option( 'blogname', 'Site to reset' );

		$response = $this->server->dispatch( $this->update_request( 'POST', array( 'data' => array( 'title' => null ) ) ) );

		$this->assertSame( 200, $response->get_status(), 'An administrator should delete the stored value.' );
		$this->assertSame( '{}', wp_json_encode( $response->get_data() ), 'The empty result should be sent as an empty object.' );
		$this->assertFalse( get_option( 'blogname' ), 'The stored site title should be deleted.' );
	}
}
