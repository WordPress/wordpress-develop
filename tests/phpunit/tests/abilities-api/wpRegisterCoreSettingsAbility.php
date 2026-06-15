<?php

declare( strict_types=1 );

/**
 * Tests for the core/settings ability shipped with the Abilities API.
 *
 * @covers wp_register_core_abilities
 * @covers WP_Settings_Abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreSettingsAbility extends WP_UnitTestCase {

	/**
	 * Set up before the class.
	 *
	 * The core settings are registered on `rest_api_init`, so register them up front to
	 * mirror the request context in which the ability builds its schema and runs.
	 *
	 * @since 7.1.0
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		register_initial_settings();

		// A non-core setting flagged for the Abilities API, to verify that any registered
		// setting (not just the core ones) is exposed by the ability.
		register_setting(
			'general',
			'core_settings_ability_test_option',
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

		unregister_setting( 'general', 'core_settings_ability_test_option' );

		parent::tear_down_after_class();
	}

	/**
	 * Logs in as an administrator so abilities gated behind `manage_options` can run.
	 */
	private function become_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * The ability is registered in the `site` category and flagged read-only.
	 *
	 * @ticket 64146
	 */
	public function test_core_settings_ability_is_registered(): void {
		$ability = wp_get_ability( 'core/settings' );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( 'site', $ability->get_category() );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ) );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
	}

	/**
	 * The input schema exposes mutually exclusive `group` and `settings` filters.
	 *
	 * @ticket 64146
	 */
	public function test_core_settings_input_schema_is_one_of_group_or_settings(): void {
		$schema = wp_get_ability( 'core/settings' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'default', $schema );
		$this->assertCount( 3, $schema['oneOf'] );

		$group_branch = $schema['oneOf'][1];
		$this->assertSame( array( 'group' ), $group_branch['required'] );
		$this->assertContains( 'general', $group_branch['properties']['group']['enum'] );
		$this->assertContains( 'reading', $group_branch['properties']['group']['enum'] );

		$settings_branch = $schema['oneOf'][2];
		$this->assertSame( array( 'settings' ), $settings_branch['required'] );
		$this->assertContains( 'blogname', $settings_branch['properties']['settings']['items']['enum'] );
		$this->assertContains( 'posts_per_page', $settings_branch['properties']['settings']['items']['enum'] );
	}

	/**
	 * Without input the ability returns a flat map of correctly typed setting values.
	 *
	 * @ticket 64146
	 */
	public function test_core_settings_returns_flat_typed_values(): void {
		$this->become_admin();

		update_option( 'blogname', 'My Test Site' );
		update_option( 'posts_per_page', 7 );
		update_option( 'use_smilies', '1' );

		$result = wp_get_ability( 'core/settings' )->execute( array() );

		$this->assertIsArray( $result );
		$this->assertSame( 'My Test Site', $result['blogname'] );
		$this->assertSame( 7, $result['posts_per_page'] );
		$this->assertTrue( $result['use_smilies'] );
	}

	/**
	 * The `group` filter narrows the response to a single settings group.
	 *
	 * @ticket 64146
	 */
	public function test_core_settings_filters_by_group(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/settings' )->execute( array( 'group' => 'reading' ) );

		$this->assertArrayHasKey( 'posts_per_page', $result );
		$this->assertArrayNotHasKey( 'blogname', $result );
	}

	/**
	 * The `settings` filter narrows the response to the requested setting names.
	 *
	 * @ticket 64146
	 */
	public function test_core_settings_filters_by_settings(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/settings' )->execute( array( 'settings' => array( 'blogname', 'posts_per_page' ) ) );

		$this->assertSame( array( 'blogname', 'posts_per_page' ), array_keys( $result ) );
	}

	/**
	 * Supplying both `group` and `settings` violates the `oneOf` and is rejected.
	 *
	 * @ticket 64146
	 */
	public function test_core_settings_rejects_group_and_settings_together(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/settings' )->execute(
			array(
				'group'    => 'reading',
				'settings' => array( 'blogname' ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Users without `manage_options` cannot run the ability.
	 *
	 * @ticket 64146
	 */
	public function test_core_settings_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$result = wp_get_ability( 'core/settings' )->execute( array() );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * A setting registered with `show_in_abilities` (for example by a plugin) is exposed by the ability.
	 *
	 * @ticket 64146
	 */
	public function test_core_settings_exposes_a_custom_registered_setting(): void {
		$ability = wp_get_ability( 'core/settings' );

		// Present in both the input `settings` enum and the output schema built at registration.
		$settings_branch = $ability->get_input_schema()['oneOf'][2];
		$this->assertContains( 'core_settings_ability_test_option', $settings_branch['properties']['settings']['items']['enum'] );
		$this->assertArrayHasKey( 'core_settings_ability_test_option', $ability->get_output_schema()['properties'] );

		// And returned, correctly typed, by execute.
		$this->become_admin();
		update_option( 'core_settings_ability_test_option', 7 );

		$result = $ability->execute( array( 'settings' => array( 'core_settings_ability_test_option' ) ) );

		$this->assertSame( array( 'core_settings_ability_test_option' => 7 ), $result );
	}
}
