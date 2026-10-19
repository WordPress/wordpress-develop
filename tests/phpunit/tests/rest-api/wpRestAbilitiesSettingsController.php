<?php

declare( strict_types=1 );

/**
 * Tests for running the core/settings-get ability through the REST API.
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
	 * The run route for the core/settings-get ability.
	 *
	 * @var string
	 */
	const RUN_ROUTE = '/wp-abilities/v1/abilities/core/settings-get/run';

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
		$request = new WP_REST_Request( 'POST', self::RUN_ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => array( 'group' => 'reading' ) ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status(), 'A POST request to the read-only ability should be rejected.' );
		$this->assertSame( 'rest_ability_invalid_method', $response->get_data()['code'], 'The error should identify the invalid HTTP method.' );
	}
}
