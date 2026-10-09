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
	 * Registers the core abilities before the class.
	 *
	 * The ability is registered under the ordering that used to break it: no settings
	 * registered yet and `rest_api_init` never fired, as on cron, WP-CLI, or any request
	 * that uses the Abilities API before the REST server loads. Core must register its
	 * initial settings when abilities initialize (see _wp_register_initial_settings_for_abilities()).
	 *
	 * @since 7.2.0
	 */
	public static function wpSetUpBeforeClass(): void {
		global $wp_registered_settings, $wp_actions;
		self::$registered_settings_backup = $wp_registered_settings;
		$rest_api_init_count              = $wp_actions['rest_api_init'] ?? null;
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

		/*
		 * Restore the hooks and the `rest_api_init` count right away instead of after the class.
		 * The first test of a run snapshots the hooks and every test resets them to that snapshot,
		 * so changes left here would leak into every later test whenever this class runs first.
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
	 * Cleans up registered abilities, categories and settings after the class.
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

		unregister_setting( 'general', 'core_settings_get_ability_test_option' );

		global $wp_registered_settings;
		$wp_registered_settings = self::$registered_settings_backup;
	}

	/**
	 * Registers the core/settings-get ability again inside a faked init action.
	 *
	 * The class setup has already registered it through wp_register_core_abilities(), so
	 * the existing copy is unregistered first.
	 */
	private function register_ability(): void {
		if ( wp_has_ability( 'core/settings-get' ) ) {
			wp_unregister_ability( 'core/settings-get' );
		}

		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		( new WP_Abilities_Settings() )->register();
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

		$this->assertArrayHasKey( 'title', $ability->get_output_schema()['properties'], 'The output schema should describe the site title, registered when abilities initialized.' );

		$this->become_admin();
		$result = $ability->execute( array( 'fields' => array( 'title' ) ) );

		$this->assertArrayHasKey( 'title', $result, 'The site title should be returned, registered when abilities initialized.' );
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
			$this->assertNotContains( 'admin_email', $new_allowed_options['general'], 'Registering the initial settings for abilities should not allow admin_email on the general options screen.' );
			// Prior allowed options must be preserved.
			$this->assertContains( 'my_custom_option', $new_allowed_options['general'], 'The options allowed before should still be allowed.' );
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

			$this->assertFalse( wp_has_ability( 'core/settings-get' ), 'The settings ability should not be registered when no setting is exposed.' );
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

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The settings ability should be registered.' );
		$this->assertSame( 'core/settings-get', $ability->get_name(), 'The registered ability should use the expected name.' );
		$this->assertSame( 'Get Settings', $ability->get_label(), 'The settings ability should use a verb-first label.' );
		$this->assertSame( 'site', $ability->get_category(), 'The settings ability should use the site category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The settings ability should be marked public.' );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ), 'The settings ability should be exposed over REST.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'], 'The settings ability should be marked read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The settings ability should not be marked destructive.' );
	}

	/**
	 * Settings exposed with `show_in_abilities => true` use the same names as in the
	 * REST API settings endpoint.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_uses_rest_api_setting_names(): void {
		$properties = wp_get_ability( 'core/settings-get' )->get_output_schema()['properties'];

		foreach ( get_registered_settings() as $option_name => $args ) {
			if ( empty( $args['show_in_abilities'] ) || empty( $args['show_in_rest'] ) ) {
				continue;
			}

			$rest_name = is_array( $args['show_in_rest'] ) && ! empty( $args['show_in_rest']['name'] ) ? $args['show_in_rest']['name'] : $option_name;
			$this->assertArrayHasKey( $rest_name, $properties, "The {$option_name} setting should use its REST API name." );
		}
	}

	/**
	 * A setting exposed with `show_in_abilities => true` reuses its REST API name and schema,
	 * while an array is used instead of the REST API arguments.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_inherits_rest_api_exposure(): void {
		register_setting(
			'general',
			'core_settings_get_inherit_test_option',
			array(
				'show_in_rest'      => array(
					'name'   => 'inherited_name',
					'schema' => array( 'enum' => array( 'a', 'b' ) ),
				),
				'show_in_abilities' => true,
			)
		);
		register_setting(
			'general',
			'core_settings_get_override_test_option',
			array(
				'show_in_rest'      => array(
					'name' => 'rest_name',
				),
				'show_in_abilities' => array(
					'name' => 'ability_name',
				),
			)
		);

		try {
			$this->register_ability();
			$properties = wp_get_ability( 'core/settings-get' )->get_output_schema()['properties'];

			$this->assertSame( array( 'a', 'b' ), $properties['inherited_name']['enum'], 'A setting exposed with true should reuse its REST API schema.' );
			$this->assertArrayHasKey( 'ability_name', $properties, 'A setting exposed with an array should use the name from that array.' );
			$this->assertArrayNotHasKey( 'rest_name', $properties, 'A setting exposed with an array should not use its REST API name.' );
		} finally {
			unregister_setting( 'general', 'core_settings_get_inherit_test_option' );
			unregister_setting( 'general', 'core_settings_get_override_test_option' );
			$this->register_ability();
		}
	}

	/**
	 * Two settings exposed under the same name trigger a notice, and the later one is exposed.
	 *
	 * @ticket 64605
	 *
	 * @expectedIncorrectUsage WP_Abilities_Settings::get_exposed_settings
	 */
	public function test_core_settings_get_warns_about_a_duplicate_exposed_name(): void {
		register_setting(
			'general',
			'core_settings_get_ability_duplicate_test_option_a',
			array(
				'type'              => 'string',
				'show_in_abilities' => array( 'name' => 'core_settings_get_ability_duplicate_name' ),
			)
		);
		register_setting(
			'general',
			'core_settings_get_ability_duplicate_test_option_b',
			array(
				'type'              => 'integer',
				'show_in_abilities' => array( 'name' => 'core_settings_get_ability_duplicate_name' ),
			)
		);

		try {
			$this->register_ability();
			$properties = wp_get_ability( 'core/settings-get' )->get_output_schema()['properties'];

			$this->assertSame( 'integer', $properties['core_settings_get_ability_duplicate_name']['type'], 'The setting registered later should be the one exposed under the shared name.' );
		} finally {
			unregister_setting( 'general', 'core_settings_get_ability_duplicate_test_option_a' );
			unregister_setting( 'general', 'core_settings_get_ability_duplicate_test_option_b' );
			$this->register_ability();
		}
	}

	/**
	 * The input schema exposes optional `group` and `fields` filters.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_input_schema_exposes_group_and_fields_filters(): void {
		$schema = wp_get_ability( 'core/settings-get' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'], 'The settings ability input schema should describe an object.' );
		$this->assertSame( array(), $schema['default'], 'The input should default to empty, which returns every exposed setting.' );
		$this->assertArrayNotHasKey( 'oneOf', $schema, 'The input schema should not model exclusive modes.' );

		$this->assertContains( 'general', $schema['properties']['group']['enum'], 'The group enum should offer the general group.' );
		$this->assertContains( 'reading', $schema['properties']['group']['enum'], 'The group enum should offer the reading group.' );

		$this->assertContains( 'title', $schema['properties']['fields']['items']['enum'], 'The fields enum should offer the site title.' );
		$this->assertContains( 'posts_per_page', $schema['properties']['fields']['items']['enum'], 'The fields enum should offer posts_per_page.' );
		$this->assertContains( 'page_for_privacy_policy', $schema['properties']['fields']['items']['enum'], 'The fields enum should offer page_for_privacy_policy.' );
		$this->assertTrue( $schema['properties']['fields']['uniqueItems'], 'The fields option should reject duplicate names.' );
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

		$this->assertIsArray( $result, 'The ability should return the settings.' );
		$this->assertSame( 'My Test Site', $result['title'], 'The site title should be returned as a string under its REST API name.' );
		$this->assertSame( 7, $result['posts_per_page'], 'An integer setting should be returned as an integer.' );
		$this->assertTrue( $result['use_smilies'], 'A boolean setting should be returned as a boolean.' );
	}

	/**
	 * The `group` filter narrows the response to a single settings group.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_filters_by_group(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/settings-get' )->execute( array( 'group' => 'reading' ) );

		$this->assertArrayHasKey( 'posts_per_page', $result, 'A setting of the requested group should be returned.' );
		$this->assertArrayNotHasKey( 'title', $result, 'A setting of another group should be left out.' );
	}

	/**
	 * The `fields` filter narrows the response to the requested setting names.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_filters_by_fields(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/settings-get' )->execute( array( 'fields' => array( 'title', 'posts_per_page' ) ) );

		$this->assertEqualSets( array( 'title', 'posts_per_page' ), array_keys( $result ), 'Only the requested settings should be returned.' );
	}

	/**
	 * Supplying both `group` and `fields` narrows the response to their intersection.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_combines_group_and_fields_filters(): void {
		$this->become_admin();

		// `title` is in the `general` group and `posts_per_page` in `reading`; only the
		// latter satisfies both filters.
		$result = wp_get_ability( 'core/settings-get' )->execute(
			array(
				'group'  => 'reading',
				'fields' => array( 'title', 'posts_per_page' ),
			)
		);

		$this->assertEqualSets( array( 'posts_per_page' ), array_keys( $result ), 'Only the requested setting of the requested group should be returned.' );
	}

	/**
	 * Input passed as an object is filtered like input passed as an array.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_filters_object_input(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/settings-get' )->execute( (object) array( 'group' => 'reading' ) );

		$this->assertArrayHasKey( 'posts_per_page', $result, 'A setting of the requested group should be returned for object input.' );
		$this->assertArrayNotHasKey( 'title', $result, 'A setting of another group should be left out for object input.' );
	}

	/**
	 * A `fields` list passed as a comma-separated string is filtered like a `fields` array.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_filters_fields_passed_as_a_string(): void {
		$this->become_admin();

		$result = wp_get_ability( 'core/settings-get' )->execute( array( 'fields' => 'title,posts_per_page' ) );

		$this->assertEqualSets( array( 'title', 'posts_per_page' ), array_keys( $result ), 'A comma-separated fields string should select the requested settings.' );
	}

	/**
	 * Users without `manage_options` cannot run the ability.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$result = wp_get_ability( 'core/settings-get' )->execute( array() );

		$this->assertWPError( $result, 'A user without manage_options should be refused.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'The refusal should use the invalid permissions error.' );
	}

	/**
	 * A setting registered with `show_in_abilities` (for example by a plugin) is exposed by the ability.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_exposes_a_custom_registered_setting(): void {
		$ability = wp_get_ability( 'core/settings-get' );

		// Present in both the input `fields` enum and the output schema built at registration.
		$this->assertContains( 'core_settings_get_ability_test_option', $ability->get_input_schema()['properties']['fields']['items']['enum'], 'A custom setting should be offered in the fields enum.' );
		$this->assertArrayHasKey( 'core_settings_get_ability_test_option', $ability->get_output_schema()['properties'], 'A custom setting should be described in the output schema.' );

		// And returned, correctly typed, by execute.
		$this->become_admin();
		update_option( 'core_settings_get_ability_test_option', 7 );

		$result = $ability->execute( array( 'fields' => array( 'core_settings_get_ability_test_option' ) ) );

		$this->assertSame( array( 'core_settings_get_ability_test_option' => 7 ), $result, 'A custom setting should be returned with its typed value.' );
	}

	/**
	 * A setting shown in the REST API without `show_in_abilities` is not exposed.
	 *
	 * @ticket 64605
	 */
	public function test_core_settings_get_skips_a_setting_only_shown_in_rest(): void {
		$option = 'core_settings_get_ability_rest_only_test_option';

		register_setting(
			'general',
			$option,
			array(
				'show_in_rest' => true,
			)
		);

		try {
			$this->register_ability();

			$this->assertArrayNotHasKey( $option, wp_get_ability( 'core/settings-get' )->get_output_schema()['properties'], 'A setting only shown in the REST API should not be exposed.' );
		} finally {
			unregister_setting( 'general', $option );
			$this->register_ability();
		}
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
		$this->assertArrayHasKey( 'title', $result, 'The other settings should still be returned.' );
		$this->assertArrayNotHasKey( 'default_ping_status', $result, 'Only the bad value should be left out.' );
	}

	/**
	 * Stored values are validated against their schema before and after sanitizing, and left out
	 * when it rejects them.
	 *
	 * @ticket 64605
	 *
	 * @dataProvider data_stored_values
	 *
	 * @param string      $type     The setting type.
	 * @param mixed       $stored   The stored option value.
	 * @param string|null $expected The value as JSON, or null when it is left out.
	 * @param array       $schema   Optional. The `show_in_abilities` schema of the setting. Default empty array.
	 */
	public function test_core_settings_get_reads_stored_values( string $type, $stored, ?string $expected, array $schema = array() ): void {
		// A numeric name, which PHP turns into an integer array key, must still match `fields`.
		$option = '123';

		register_setting(
			'general',
			$option,
			array(
				'type'              => $type,
				'show_in_abilities' => array( 'schema' => $schema ),
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

		$this->assertSame( $expected, isset( $result[ $option ] ) ? wp_json_encode( $result[ $option ] ) : null, 'The stored value should be read as expected, or left out.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: mixed, 2: string|null, 3?: array<string, mixed>}> Stored values, and the JSON they are read as.
	 */
	public static function data_stored_values(): array {
		return array(
			'"false" for a boolean'               => array( 'boolean', 'false', 'false' ),
			'an empty string for a boolean'       => array( 'boolean', '', 'false' ),
			'a stdClass for an object'            => array(
				'object',
				(object) array( 'a' => 1 ),
				'{"a":1}',
				array( 'properties' => array( 'a' => array( 'type' => 'integer' ) ) ),
			),
			'an undeclared property in an object' => array( 'object', array( 'a' => 1 ), null ),
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
			'an email that sanitizing breaks'     => array( 'string', '%ab@x.co', null, array( 'format' => 'email' ) ),
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

			$this->assertArrayNotHasKey( $option, $ability->get_output_schema()['properties'], 'A setting of an unsupported type should not be described in the output schema.' );
			$this->assertArrayNotHasKey( $option, $ability->execute( array() ), 'A setting of an unsupported type should not be returned.' );
		} finally {
			unregister_setting( 'general', $option );
			$this->register_ability();
		}
	}
}
