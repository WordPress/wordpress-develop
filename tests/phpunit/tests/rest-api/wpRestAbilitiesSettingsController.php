<?php

declare( strict_types=1 );

/**
 * Tests for the core/get-settings ability via REST API.
 *
 * @covers WP_Settings_Abilities
 *
 * @group abilities-api
 * @group rest-api
 */
class Tests_REST_API_WpRestAbilitiesSettingsController extends WP_UnitTestCase {

	/**
	 * REST Server instance.
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
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Register initial settings first so abilities can build schemas.
		register_initial_settings();

		// Ensure core abilities are registered for these tests.
		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
	}

	/**
	 * Tear down after class.
	 */
	public static function tear_down_after_class(): void {
		// Re-add the unhook functions for subsequent tests.
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		// Remove the core abilities and their categories.
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		parent::tear_down_after_class();
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

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Tests that unauthenticated users cannot access the get-settings ability.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_requires_authentication(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Tests that subscribers cannot access the get-settings ability.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_requires_manage_options_capability(): void {
		wp_set_current_user( self::$subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Tests that administrators can access the get-settings ability.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_allows_administrators(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Tests that the get-settings ability returns settings grouped by registration group.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_returns_grouped_settings(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'general', $data );
		$this->assertArrayHasKey( 'blogname', $data['general'] );
		$this->assertArrayHasKey( 'blogdescription', $data['general'] );
	}

	/**
	 * Tests that the get-settings ability can filter by group.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_filters_by_group(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'group' => 'general',
				),
			)
		);
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertCount( 1, $data );
		$this->assertArrayHasKey( 'general', $data );
	}

	/**
	 * Tests that the get-settings ability can filter by specific slugs.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_filters_by_slugs(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'slugs' => array( 'blogname', 'blogdescription' ),
				),
			)
		);
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'general', $data );
		$this->assertCount( 2, $data['general'] );
		$this->assertArrayHasKey( 'blogname', $data['general'] );
		$this->assertArrayHasKey( 'blogdescription', $data['general'] );
	}

	/**
	 * Tests that settings without show_in_abilities are excluded.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_excludes_settings_without_show_in_abilities(): void {
		register_setting(
			'general',
			'test_setting_excluded',
			array(
				'type'              => 'string',
				'default'           => 'test_value',
				'show_in_abilities' => false,
			)
		);
		update_option( 'test_setting_excluded', 'test_value' );

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayNotHasKey( 'test_setting_excluded', $data['general'] ?? array() );

		unregister_setting( 'general', 'test_setting_excluded' );
		delete_option( 'test_setting_excluded' );
	}

	/**
	 * Tests that core settings with show_in_abilities are included.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_includes_settings_with_show_in_abilities(): void {
		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		// blogname has show_in_abilities => true in register_initial_settings().
		$this->assertArrayHasKey( 'general', $data );
		$this->assertArrayHasKey( 'blogname', $data['general'] );

		// use_smilies has show_in_abilities => true.
		$this->assertArrayHasKey( 'writing', $data );
		$this->assertArrayHasKey( 'use_smilies', $data['writing'] );
	}

	/**
	 * Tests that boolean settings are cast to actual booleans.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_casts_boolean_values(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'slugs' => array( 'use_smilies' ),
				),
			)
		);
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'writing', $data );
		$this->assertArrayHasKey( 'use_smilies', $data['writing'] );
		$this->assertIsBool( $data['writing']['use_smilies'] );
	}

	/**
	 * Tests that integer settings are cast to actual integers.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_casts_integer_values(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'slugs' => array( 'start_of_week' ),
				),
			)
		);
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'general', $data );
		$this->assertArrayHasKey( 'start_of_week', $data['general'] );
		$this->assertIsInt( $data['general']['start_of_week'] );
	}

	/**
	 * Tests that the get-settings ability requires GET method (read-only).
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_requires_get_method(): void {
		$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => array() ) ) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'rest_ability_invalid_method', $data['code'] );
	}

	/**
	 * Tests that the get-settings ability returns correct values.
	 *
	 * @ticket 64605
	 */
	public function test_core_get_settings_returns_correct_values(): void {
		update_option( 'blogname', 'Test Site Name' );

		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/core/get-settings/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'slugs' => array( 'blogname' ),
				),
			)
		);
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame( 'Test Site Name', $data['general']['blogname'] );
	}
}
