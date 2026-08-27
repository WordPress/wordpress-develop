<?php

declare( strict_types=1 );

/**
 * Tests for the core/read-settings ability shipped with the Abilities API.
 *
 * @covers wp_register_core_abilities
 * @covers WP_Settings_Abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreReadSettingsAbility extends WP_UnitTestCase {

	/**
	 * Backup of the `$wp_registered_settings` global, restored after the class.
	 *
	 * @var array|null
	 */
	private static $registered_settings_backup;

	/**
	 * Number of times `rest_api_init` had fired before the class ran, or null if never.
	 *
	 * @var int|null
	 */
	private static $rest_api_init_count;

	/**
	 * Set up before the class.
	 *
	 * The ability is registered under the ordering that used to break it: no settings
	 * registered yet and `rest_api_init` never fired, as on cron, WP-CLI, or any request
	 * that uses the Abilities API before the REST server loads. The ability must
	 * self-register core's initial settings (see WP_Settings_Abilities::register()).
	 *
	 * @since 7.1.0
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		global $wp_registered_settings, $wp_actions;
		self::$registered_settings_backup = $wp_registered_settings;
		self::$rest_api_init_count        = $wp_actions['rest_api_init'] ?? null;
		$wp_registered_settings           = array();
		unset( $wp_actions['rest_api_init'] );

		// A non-core setting flagged for the Abilities API, to verify that any registered
		// setting (not just the core ones) is exposed by the ability.
		register_setting(
			'general',
			'core_read_settings_ability_test_option',
			array(
				'type'              => 'integer',
				'label'             => 'Custom Ability Setting',
				'description'       => 'A custom setting exposed through the Abilities API.',
				'show_in_abilities' => true,
				'default'           => 42,
			)
		);

		// Temporarily remove the unhook functions so we can register core abilities.
		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
	}

	/**
	 * Tear down after the class.
	 *
	 * @since 7.1.0
	 */
	public static function tear_down_after_class(): void {
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		unregister_setting( 'general', 'core_read_settings_ability_test_option' );

		global $wp_registered_settings, $wp_actions;
		$wp_registered_settings = self::$registered_settings_backup;
		if ( null !== self::$rest_api_init_count ) {
			$wp_actions['rest_api_init'] = self::$rest_api_init_count;
		}

		parent::tear_down_after_class();
	}

	/**
	 * Logs in as an administrator so abilities gated behind `manage_options` can run.
	 */
	private function become_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Core settings are exposed even when the abilities registry initializes in a request
	 * where `rest_api_init` (which registers core's initial settings) has never fired.
	 *
	 * The class setup registers the ability with no settings registered up front, so this
	 * asserts that the ability took care of registering core's initial settings itself.
	 *
	 * @ticket 64605
	 */
	public function test_core_read_settings_registers_initial_settings_without_rest_api_init(): void {
		$ability = wp_get_ability( 'core/read-settings' );

		$this->assertArrayHasKey( 'blogname', $ability->get_output_schema()['properties'] );

		$this->become_admin();
		$result = $ability->execute( array( 'fields' => array( 'blogname' ) ) );

		$this->assertArrayHasKey( 'blogname', $result );
	}

	/**
	 * The ability is registered in the `site` category and flagged read-only.
	 *
	 * @ticket 64605
	 */
	public function test_core_read_settings_ability_is_registered(): void {
		$ability = wp_get_ability( 'core/read-settings' );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( 'core/read-settings', $ability->get_name() );
		$this->assertSame( 'site', $ability->get_category() );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ) );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
	}

	/**
	 * The input schema exposes optional `group` and `fields` filters.
	 *
	 * @ticket 64605
	 */
	public function test_core_read_settings_input_schema_exposes_group_and_fields_filters(): void {
		$schema = wp_get_ability( 'core/read-settings' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'default', $schema );
		$this->assertArrayNotHasKey( 'oneOf', $schema );

		$this->assertContains( 'general', $schema['properties']['group']['enum'] );
		$this->assertContains( 'reading', $schema['properties']['group']['enum'] );

		$this->assertContains( 'blogname', $schema['properties']['fields']['items']['enum'] );
		$this->assertContains( 'posts_per_page', $schema['properties']['fields']['items']['enum'] );
	}

	/**
	 * Without input the ability returns a flat map of correctly typed setting values.
	 *
	 * @ticket 64605
	 */
	public function test_core_read_settings_returns_flat_typed_values(): void {
		$this->become_admin();

		update_option( 'blogname', 'My Test Site' );
		update_option( 'posts_per_page', 7 );
		update_option( 'use_smilies', '1' );

		$result = wp_get_ability( 'core/read-settings' )->execute( array() );

		$this->assertIsArray( $result );
		$this->assertSame( 'My Test Site', $result['blogname'] );
		$this->assertSame( 7, $result['posts_per_page'] );
		$this->assertTrue( $result['use_smilies'] );
	}

	/**
	 * The `group` filter narrows the response to a single settings group.
	 *
	 * @ticket 64605
	 */
	public function test_core_read_settings_filters_by_group(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/read-settings' )->execute( array( 'group' => 'reading' ) );

		$this->assertArrayHasKey( 'posts_per_page', $result );
		$this->assertArrayNotHasKey( 'blogname', $result );
	}

	/**
	 * The `fields` filter narrows the response to the requested setting names.
	 *
	 * @ticket 64605
	 */
	public function test_core_read_settings_filters_by_fields(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/read-settings' )->execute( array( 'fields' => array( 'blogname', 'posts_per_page' ) ) );

		$this->assertEqualSets( array( 'blogname', 'posts_per_page' ), array_keys( $result ) );
	}

	/**
	 * Supplying both `group` and `fields` narrows the response to their intersection.
	 *
	 * @ticket 64605
	 */
	public function test_core_read_settings_combines_group_and_fields_filters(): void {
		$this->become_admin();

		// `blogname` is in the `general` group and `posts_per_page` in `reading`; only the
		// latter satisfies both filters.
		$result = wp_get_ability( 'core/read-settings' )->execute(
			array(
				'group'  => 'reading',
				'fields' => array( 'blogname', 'posts_per_page' ),
			)
		);

		$this->assertEqualSets( array( 'posts_per_page' ), array_keys( $result ) );
	}

	/**
	 * Users without `manage_options` cannot run the ability.
	 *
	 * @ticket 64605
	 */
	public function test_core_read_settings_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$result = wp_get_ability( 'core/read-settings' )->execute( array() );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * A setting registered with `show_in_abilities` (for example by a plugin) is exposed by the ability.
	 *
	 * @ticket 64605
	 */
	public function test_core_read_settings_exposes_a_custom_registered_setting(): void {
		$ability = wp_get_ability( 'core/read-settings' );

		// Present in both the input `fields` enum and the output schema built at registration.
		$this->assertContains( 'core_read_settings_ability_test_option', $ability->get_input_schema()['properties']['fields']['items']['enum'] );
		$this->assertArrayHasKey( 'core_read_settings_ability_test_option', $ability->get_output_schema()['properties'] );

		// And returned, correctly typed, by execute.
		$this->become_admin();
		update_option( 'core_read_settings_ability_test_option', 7 );

		$result = $ability->execute( array( 'fields' => array( 'core_read_settings_ability_test_option' ) ) );

		$this->assertSame( array( 'core_read_settings_ability_test_option' => 7 ), $result );
	}
}
