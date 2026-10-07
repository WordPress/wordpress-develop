<?php

declare( strict_types=1 );

/**
 * Tests for the core/settings-get ability shipped with the Abilities API.
 *
 * @covers wp_register_core_abilities
 * @covers _wp_register_initial_settings_for_abilities
 * @covers WP_Abilities_Settings
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreSettingsGetAbility extends WP_UnitTestCase {

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
	 * that uses the Abilities API before the REST server loads. Core must register its
	 * initial settings when abilities initialize (see _wp_register_initial_settings_for_abilities()).
	 *
	 * @since 7.2.0
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
			'core_settings_get_ability_test_option',
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
	 * @since 7.2.0
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

		unregister_setting( 'general', 'core_settings_get_ability_test_option' );

		global $wp_registered_settings, $wp_actions;
		$wp_registered_settings = self::$registered_settings_backup;
		if ( null !== self::$rest_api_init_count ) {
			$wp_actions['rest_api_init'] = self::$rest_api_init_count;
		}

		parent::tear_down_after_class();
	}

	/**
	 * Registers the core/settings-get ability again inside a faked init action.
	 *
	 * The class setup has already registered it through wp_register_core_abilities(), so
	 * the existing copy is unregistered first.
	 */
	private function register_ability(): void {
		global $wp_current_filter;

		if ( wp_has_ability( 'core/settings-get' ) ) {
			wp_unregister_ability( 'core/settings-get' );
		}

		$wp_current_filter[] = 'wp_abilities_api_init';
		try {
			( new WP_Abilities_Settings() )->register();
		} finally {
			array_pop( $wp_current_filter );
		}
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
	 * asserts that core registered its initial settings when abilities initialized.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_exposes_initial_settings_without_rest_api_init(): void {
		$ability = wp_get_ability( 'core/settings-get' );

		$this->assertArrayHasKey( 'blogname', $ability->get_output_schema()['properties'] );

		$this->become_admin();
		$result = $ability->execute( array( 'fields' => array( 'blogname' ) ) );

		$this->assertArrayHasKey( 'blogname', $result );
	}

	/**
	 * Tests that registering initial settings for abilities does not pollute $new_allowed_options.
	 *
	 * @ticket 64605
	 */
	public function test_register_preserves_new_allowed_options(): void {
		global $new_allowed_options;

		$prev_actions_count  = $GLOBALS['wp_actions']['rest_api_init'] ?? null;
		$prev_allowed_backup = $new_allowed_options;
		unset( $GLOBALS['wp_actions']['rest_api_init'] );

		// Simulate an existing custom setting already in $new_allowed_options.
		$new_allowed_options = array(
			'general' => array( 'my_custom_option' ),
		);

		try {
			_wp_register_initial_settings_for_abilities();

			// 'admin_email' must NOT be in $new_allowed_options['general'].
			$this->assertNotContains( 'admin_email', $new_allowed_options['general'] );
			// Prior allowed options must be preserved.
			$this->assertContains( 'my_custom_option', $new_allowed_options['general'] );
		} finally {
			$new_allowed_options = $prev_allowed_backup;
			if ( null === $prev_actions_count ) {
				unset( $GLOBALS['wp_actions']['rest_api_init'] );
			} else {
				$GLOBALS['wp_actions']['rest_api_init'] = $prev_actions_count;
			}
		}
	}

	/**
	 * Neither settings ability is registered when no setting is exposed to abilities.
	 *
	 * @ticket 64605
	 */
	public function test_settings_abilities_are_not_registered_without_exposed_settings(): void {
		global $wp_registered_settings;

		$registered_settings_backup = $wp_registered_settings;
		$wp_registered_settings     = array();

		try {
			$this->register_ability();

			$this->assertFalse( wp_has_ability( 'core/settings-get' ) );
		} finally {
			$wp_registered_settings = $registered_settings_backup;

			// Register the ability again for the tests that follow.
			$this->register_ability();
		}
	}

	/**
	 * The ability is registered in the `site` category and flagged read-only.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_ability_is_registered(): void {
		$ability = wp_get_ability( 'core/settings-get' );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( 'core/settings-get', $ability->get_name() );
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
	public function test_core_settings_get_input_schema_exposes_group_and_fields_filters(): void {
		$schema = wp_get_ability( 'core/settings-get' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertEquals( (object) array(), $schema['default'] );
		$this->assertArrayNotHasKey( 'oneOf', $schema );

		$this->assertContains( 'general', $schema['properties']['group']['enum'] );
		$this->assertContains( 'reading', $schema['properties']['group']['enum'] );

		$this->assertContains( 'blogname', $schema['properties']['fields']['items']['enum'] );
		$this->assertContains( 'posts_per_page', $schema['properties']['fields']['items']['enum'] );
		$this->assertContains( 'wp_page_for_privacy_policy', $schema['properties']['fields']['items']['enum'] );
	}

	/**
	 * Without input the ability returns a flat map of correctly typed setting values.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_returns_flat_typed_values(): void {
		$this->become_admin();

		update_option( 'blogname', 'My Test Site' );
		update_option( 'posts_per_page', 7 );
		update_option( 'use_smilies', '1' );

		$result = wp_get_ability( 'core/settings-get' )->execute( array() );

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
	public function test_core_settings_get_filters_by_group(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/settings-get' )->execute( array( 'group' => 'reading' ) );

		$this->assertArrayHasKey( 'posts_per_page', $result );
		$this->assertArrayNotHasKey( 'blogname', $result );
	}

	/**
	 * The `fields` filter narrows the response to the requested setting names.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_filters_by_fields(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/settings-get' )->execute( array( 'fields' => array( 'blogname', 'posts_per_page' ) ) );

		$this->assertEqualSets( array( 'blogname', 'posts_per_page' ), array_keys( $result ) );
	}

	/**
	 * Supplying both `group` and `fields` narrows the response to their intersection.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_combines_group_and_fields_filters(): void {
		$this->become_admin();

		// `blogname` is in the `general` group and `posts_per_page` in `reading`; only the
		// latter satisfies both filters.
		$result = wp_get_ability( 'core/settings-get' )->execute(
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
	public function test_core_settings_get_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$result = wp_get_ability( 'core/settings-get' )->execute( array() );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * A setting registered with `show_in_abilities` (for example by a plugin) is exposed by the ability.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_exposes_a_custom_registered_setting(): void {
		$ability = wp_get_ability( 'core/settings-get' );

		// Present in both the input `fields` enum and the output schema built at registration.
		$this->assertContains( 'core_settings_get_ability_test_option', $ability->get_input_schema()['properties']['fields']['items']['enum'] );
		$this->assertArrayHasKey( 'core_settings_get_ability_test_option', $ability->get_output_schema()['properties'] );

		// And returned, correctly typed, by execute.
		$this->become_admin();
		update_option( 'core_settings_get_ability_test_option', 7 );

		$result = $ability->execute( array( 'fields' => array( 'core_settings_get_ability_test_option' ) ) );

		$this->assertSame( array( 'core_settings_get_ability_test_option' => 7 ), $result );
	}

	/**
	 * A value that does not match its schema is left out instead of failing the whole call.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_drops_values_that_fail_their_schema(): void {
		$this->become_admin();

		// sanitize_option() only coerces '0' and '' to 'closed', so this out-of-enum value sticks.
		update_option( 'default_ping_status', 'not-a-valid-status' );

		$result = wp_get_ability( 'core/settings-get' )->execute( array() );

		$this->assertNotWPError( $result, 'One bad value must not fail the whole ability.' );
		$this->assertArrayHasKey( 'blogname', $result, 'The other settings should still be returned.' );
		$this->assertArrayNotHasKey( 'default_ping_status', $result, 'Only the bad value should be left out.' );
	}

	/**
	 * Stored values are read as the settings endpoint reads them: validated against their schema,
	 * left out when it rejects them, and sanitized otherwise.
	 *
	 * @ticket 64605
	 *
	 * @dataProvider data_stored_values
	 *
	 * @param string      $type     The setting type.
	 * @param mixed       $stored   The stored option value.
	 * @param string|null $expected The value as JSON, or null when it is left out.
	 */
	public function test_core_settings_get_reads_stored_values_as_the_settings_endpoint( string $type, $stored, ?string $expected ): void {
		$option = 'core_settings_get_ability_value_test_option';

		register_setting(
			'general',
			$option,
			array(
				'type'              => $type,
				'show_in_abilities' => true,
			)
		);
		update_option( $option, $stored );

		try {
			$this->register_ability();
			$this->become_admin();

			$result = wp_get_ability( 'core/settings-get' )->execute( array( 'fields' => array( $option ) ) );
		} finally {
			unregister_setting( 'general', $option );
			$this->register_ability();
		}

		$this->assertSame( $expected, isset( $result[ $option ] ) ? wp_json_encode( $result[ $option ] ) : null );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: mixed, 2: string|null}> Stored values, and the JSON they are read as.
	 */
	public static function data_stored_values(): array {
		return array(
			'"false" for a boolean'               => array( 'boolean', 'false', 'false' ),
			'an empty string for a boolean'       => array( 'boolean', '', 'false' ),
			'a stdClass for an object'            => array( 'object', (object) array( 'a' => 1 ), '{"a":1}' ),
			'an empty array for an object'        => array( 'object', array(), '{}' ),
			'a list with gaps for an array'       => array(
				'array',
				array(
					0 => 'a',
					2 => 'b',
				),
				'["a","b"]',
			),
			'a numeric string for an integer'     => array( 'integer', '7', '7' ),
			'a non-numeric string for an integer' => array( 'integer', 'abc', null ),
		);
	}

	/**
	 * A setting of a type the settings endpoint does not support is not exposed.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_skips_a_setting_with_an_unsupported_type(): void {
		$option = 'core_settings_get_ability_type_test_option';

		register_setting(
			'general',
			$option,
			array(
				'type'              => 'foo',
				'show_in_abilities' => true,
			)
		);
		update_option( $option, 'value' );

		try {
			$this->register_ability();
			$this->become_admin();

			$ability = wp_get_ability( 'core/settings-get' );

			$this->assertArrayNotHasKey( $option, $ability->get_output_schema()['properties'] );
			$this->assertArrayNotHasKey( $option, $ability->execute( array() ) );
		} finally {
			unregister_setting( 'general', $option );
			$this->register_ability();
		}
	}
}
