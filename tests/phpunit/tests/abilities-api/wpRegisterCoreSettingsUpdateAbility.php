<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-settings-ability.php';

/**
 * Tests for the core/settings-update ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Settings
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreSettingsUpdateAbility extends WP_Abilities_Settings_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @var string
	 */
	const ABILITY = 'core/settings-update';

	/**
	 * Names under which the settings registered by this class are exposed.
	 *
	 * @var string[]
	 */
	const TEST_SETTING_NAMES = array(
		'settings_update_test_array',
		'settings_update_test_object',
		'settings_update_test_email',
		'settings_update_test_uri',
		'456',
		'Mixed_Case_Name',
		'settings_update_test_readonly',
		'settings_update_test_readonly_false',
		'settings_update_test_boolean',
	);

	/**
	 * Exposed names that core/settings-get reads but this ability cannot write.
	 *
	 * @var string[]
	 */
	const READ_ONLY_NAMES = array( 'settings_update_test_readonly', 'url', 'email' );

	/**
	 * Options that hold the ID of a page or a category.
	 *
	 * @var string[]
	 */
	const ID_OPTIONS = array( 'default_category', 'page_on_front', 'page_for_posts', 'wp_page_for_privacy_policy' );

	/**
	 * Raw values a null value stores, keyed by option: the default of each core setting that has one.
	 *
	 * @var array<string, string>
	 */
	const STORED_DEFAULTS = array(
		'WPLANG'         => '',
		'use_smilies'    => '1',
		'posts_per_page' => '10',
	);

	/**
	 * User IDs keyed by role.
	 *
	 * @var array<string, int>
	 */
	private static $user_ids = array();

	/**
	 * IDs of a page, a post, a category and a tag, keyed by kind.
	 *
	 * @var array<string, int>
	 */
	private static $fixture_ids = array();

	/**
	 * Creates a subscriber and an editor, who cannot manage options, and the content the ID settings point to.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		parent::wpSetUpBeforeClass( $factory );

		foreach ( array( 'subscriber', 'editor' ) as $role ) {
			self::$user_ids[ $role ] = $factory->user->create( array( 'role' => $role ) );
		}

		self::$fixture_ids = array(
			'page'     => $factory->post->create( array( 'post_type' => 'page' ) ),
			'post'     => $factory->post->create(),
			'category' => $factory->category->create(),
			'tag'      => $factory->tag->create(),
		);
	}

	/**
	 * Registers plugin settings exposed to abilities before the abilities register.
	 *
	 * @since 7.3.0
	 */
	protected static function register_test_settings(): void {
		register_setting(
			'general',
			'settings_update_test_array',
			array(
				'type'              => 'array',
				'show_in_abilities' => array(
					'schema' => array(
						'items' => array( 'type' => 'integer' ),
					),
				),
			)
		);
		register_setting(
			'general',
			'settings_update_test_object',
			array(
				'type'              => 'object',
				'show_in_abilities' => array(
					'schema' => array(
						'properties' => array(
							'a' => array( 'type' => 'integer' ),
							'n' => array(
								'type'       => 'object',
								'properties' => array(
									'b' => array( 'type' => 'number' ),
								),
							),
						),
					),
				),
			)
		);
		register_setting(
			'general',
			'settings_update_test_email_option',
			array(
				'type'              => 'string',
				'show_in_abilities' => array(
					'name'   => 'settings_update_test_email',
					'schema' => array( 'format' => 'email' ),
				),
			)
		);
		register_setting(
			'general',
			'settings_update_test_uri',
			array(
				'type'              => 'string',
				'show_in_abilities' => array(
					'schema' => array( 'format' => 'uri' ),
				),
			)
		);
		register_setting(
			'general',
			'settings_update_test_numeric_option',
			array(
				'type'              => 'integer',
				'show_in_abilities' => array( 'name' => '456' ),
			)
		);
		register_setting(
			'general',
			'settings_update_test_mixed_case_option',
			array(
				'type'              => 'string',
				'show_in_abilities' => array( 'name' => 'Mixed_Case_Name' ),
			)
		);
		register_setting(
			'general',
			'settings_update_test_readonly',
			array(
				'type'              => 'string',
				'show_in_abilities' => array(
					'schema' => array( 'readonly' => true ),
				),
			)
		);
		register_setting(
			'general',
			'settings_update_test_readonly_false',
			array(
				'type'              => 'string',
				'show_in_abilities' => array(
					'schema' => array( 'readonly' => false ),
				),
			)
		);
		register_setting(
			'general',
			'settings_update_test_boolean',
			array(
				'type'              => 'boolean',
				'show_in_abilities' => true,
			)
		);
	}

	/**
	 * Runs the ability with the given `data`.
	 *
	 * @param array<string|int, mixed> $data The settings to update.
	 * @return mixed The result of the ability.
	 */
	private function update( array $data ) {
		return wp_get_ability( self::ABILITY )->execute( array( 'data' => $data ) );
	}

	/**
	 * Returns the stored rows of every registered setting, read from the database.
	 *
	 * An option without a row is absent from the result.
	 *
	 * @return array<string, string> Raw option values keyed by option name.
	 */
	private function snapshot(): array {
		global $wpdb;

		$options = array_map( 'strval', array_keys( get_registered_settings() ) );
		$in      = implode( ', ', array_fill( 0, count( $options ), '%s' ) );
		$rows    = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholders are built above.
			$wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ( {$in} )", $options ),
			ARRAY_A
		);

		return array_column( $rows, 'option_value', 'option_name' );
	}

	/**
	 * Stores a raw value for an option directly in the database, or removes its row.
	 *
	 * @param string $option The option name.
	 * @param mixed  $value  The value to store, or null to remove the row.
	 */
	private function store_raw( string $option, $value ): void {
		global $wpdb;

		$wpdb->delete( $wpdb->options, array( 'option_name' => $option ) );
		if ( null !== $value ) {
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => $option,
					'option_value' => maybe_serialize( $value ),
					'autoload'     => 'on',
				)
			);
		}

		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( $option, 'options' );
	}

	/**
	 * Logs in as an administrator who can also set the privacy policy page when the setting needs it.
	 *
	 * @param string $name The exposed setting name the test writes.
	 */
	private function become_admin_for( string $name ): void {
		$this->become_admin();
		if ( is_multisite() && 'page_for_privacy_policy' === $name ) {
			grant_super_admin( get_current_user_id() );
		}
	}

	/**
	 * Adds a filter that takes `manage_privacy_options` away from every user.
	 */
	private function deny_privacy_capability(): void {
		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap ) {
				return 'manage_privacy_options' === $cap ? array( 'do_not_allow' ) : $caps;
			},
			10,
			2
		);
	}

	/**
	 * Asserts that a result is an error with the given code.
	 *
	 * @param mixed  $result The result of the ability.
	 * @param string $code   The expected error code.
	 */
	private function assert_error_code( $result, string $code ): void {
		$this->assertWPError( $result, "The call should fail with {$code}." );
		$this->assertSame( $code, $result->get_error_code(), 'The call should fail with the expected error code.' );
	}

	/**
	 * Asserts that a result is the error for a value a setting cannot use, which names the setting.
	 *
	 * @param mixed  $result The result of the ability.
	 * @param string $name   The exposed setting name.
	 */
	private function assert_invalid_value_error( $result, string $name ): void {
		$this->assert_refused_setting_error( $result, 'settings_invalid_value', 400, $name );
	}

	/**
	 * Asserts that a result is an error that refuses one setting, and names it.
	 *
	 * @param mixed  $result The result of the ability.
	 * @param string $code   The expected error code.
	 * @param int    $status The expected status.
	 * @param string $name   The exposed setting name.
	 */
	private function assert_refused_setting_error( $result, string $code, int $status, string $name ): void {
		$this->assert_error_code( $result, $code );
		$this->assertNotSame( '', $result->get_error_message(), "The error for {$name} should have a message." );
		$this->assertSame(
			array(
				'status' => $status,
				'params' => array( "data[{$name}]" => $result->get_error_message() ),
			),
			$result->get_error_data(),
			"The error data should map {$name} to the message."
		);
	}

	/**
	 * The ability is registered with its label and category.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_is_registered_with_label_and_category(): void {
		$ability = wp_get_ability( self::ABILITY );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The ability should be registered.' );
		$this->assertSame( 'Update Settings', $ability->get_label(), 'The ability should use a verb-first label.' );
		$this->assertSame( 'site', $ability->get_category(), 'The ability should use the site category.' );
		$this->assertNotEmpty( $ability->get_description(), 'The ability should have a description.' );
		$this->assertStringNotContainsString( '`', $ability->get_description(), 'The description should not use backticks.' );
	}

	/**
	 * The ability is flagged as a destructive write that is not idempotent, with the other meta
	 * of core/settings-get.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_annotations_and_meta(): void {
		$meta        = wp_get_ability( self::ABILITY )->get_meta();
		$annotations = $meta['annotations'];
		$get_meta    = wp_get_ability( 'core/settings-get' )->get_meta();
		unset( $meta['annotations'], $get_meta['annotations'] );

		$this->assertFalse( $annotations['readonly'], 'The ability should not be marked read-only.' );
		$this->assertTrue( $annotations['destructive'], 'The ability should be marked destructive, since a null value deletes the stored value of a setting without a default.' );
		$this->assertFalse( $annotations['idempotent'], 'The ability should not be marked idempotent, since a destructive and idempotent ability runs with the DELETE method.' );
		$this->assertSame( $get_meta, $meta, 'The other meta should match core/settings-get.' );
	}

	/**
	 * The ability requires `manage_options`.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_requires_manage_options(): void {
		$this->assertSame( array( 'manage_options' ), wp_get_ability( self::ABILITY )->get_required_capabilities(), 'The ability should require the manage_options capability.' );
	}

	/**
	 * The input is an object with a required, non-empty `data` object keyed by the exposed names.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_input_schema_shape(): void {
		$schema = wp_get_ability( self::ABILITY )->get_input_schema();
		$data   = $schema['properties']['data'];

		$this->assertSame( 'object', $schema['type'], 'The input should be an object.' );
		$this->assertFalse( $schema['additionalProperties'], 'The input should reject other keys.' );
		$this->assertSame( array( 'data' ), $schema['required'], 'The input should require data.' );
		$this->assertSame( array( 'data' ), array_keys( $schema['properties'] ), 'The input should only accept data.' );
		$this->assertArrayNotHasKey( 'default', $schema, 'The input should have no default.' );
		$this->assertSame( 'object', $data['type'], 'The data should be an object.' );
		$this->assertFalse( $data['additionalProperties'], 'The data should reject names that are not exposed.' );
		$this->assertSame( 1, $data['minProperties'], 'The data should name at least one setting.' );
		$this->assertArrayNotHasKey( 'default', $data, 'The data should have no default.' );
		$this->assertSameSets(
			array_diff( array_map( 'strval', array_keys( wp_get_ability( 'core/settings-get' )->get_output_schema()['properties'] ) ), self::READ_ONLY_NAMES ),
			array_map( 'strval', array_keys( $data['properties'] ) ),
			'The data should accept exactly the exposed settings that are not read-only.'
		);
	}

	/**
	 * Each setting in the input accepts its value schema or null.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_input_schema_property_allows_null(): void {
		$properties = wp_get_ability( self::ABILITY )->get_input_schema()['properties']['data']['properties'];

		$this->assertSame(
			array(
				'type'   => array( 'string', 'null' ),
				'format' => 'uri',
			),
			$properties['settings_update_test_uri'],
			'A setting should accept a value of its schema or null.'
		);
		$this->assertSame( array( 'string', 'null' ), $properties['default_ping_status']['type'], 'A setting that lists its allowed values should accept null too.' );
		$this->assertSame( array( 'open', 'closed', null ), $properties['default_ping_status']['enum'], 'Null should be added to the allowed values.' );
	}

	/**
	 * A setting whose schema lists its allowed schemas in a top-level `anyOf` or `oneOf` accepts
	 * a value of one of them or null, and refuses any other value.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_combining_keywords
	 *
	 * @param string $keyword The keyword that lists the allowed schemas.
	 */
	public function test_setting_with_allowed_schemas_accepts_null( string $keyword ): void {
		$option = 'settings_update_test_allowed_schemas';
		$schema = array(
			$keyword => array(
				array(
					'type' => 'string',
					'enum' => array( 'a', 'b' ),
				),
				array(
					'type'    => 'string',
					'pattern' => '^x',
				),
			),
		);

		register_setting(
			'general',
			$option,
			array(
				'type'              => 'string',
				'show_in_abilities' => array( 'schema' => $schema ),
			)
		);
		$this->register_ability();
		$this->become_admin();

		$property = wp_get_ability( self::ABILITY )->get_input_schema()['properties']['data']['properties'][ $option ];
		$value    = $this->update( array( $option => 'a' ) );
		$stored   = $this->snapshot()[ $option ] ?? null;
		$other    = $this->update( array( $option => 'c' ) );
		$reset    = $this->update( array( $option => null ) );

		$this->assertSame(
			array(
				'anyOf' => array(
					array( 'type' => 'string' ) + $schema,
					array( 'type' => 'null' ),
				),
			),
			$property,
			'The input should list the schema of the setting and a null schema.'
		);
		$this->assertSame( array( $option => 'a' ), $value, 'A value of an allowed schema should be saved.' );
		$this->assertSame( 'a', $stored, 'The value should be stored.' );
		$this->assert_error_code( $other, 'ability_invalid_input' );
		$this->assertSame( array(), $reset, 'Null should delete the stored value of a setting without a default.' );
		$this->assertArrayNotHasKey( $option, $this->snapshot(), 'The stored value should be deleted.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}> Keywords that list allowed schemas.
	 */
	public static function data_combining_keywords(): array {
		return array(
			'anyOf' => array( 'anyOf' ),
			'oneOf' => array( 'oneOf' ),
		);
	}

	/**
	 * The default of a setting's schema is left out of the input, so a client that fills in
	 * defaults does not write it on every call.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_input_schema_property_drops_the_default(): void {
		$option = 'settings_update_test_default';

		register_setting(
			'general',
			$option,
			array(
				'type'              => 'string',
				'show_in_abilities' => array(
					'schema' => array( 'default' => 'Fallback' ),
				),
			)
		);
		$this->register_ability();
		$ability = wp_get_ability( self::ABILITY );

		$this->assertSame( array( 'type' => array( 'string', 'null' ) ), $ability->get_input_schema()['properties']['data']['properties'][ $option ], 'The input should not hold the default.' );
		$this->assertSame( 'Fallback', $ability->get_output_schema()['properties'][ $option ]['default'], 'The output should still describe the default.' );
	}

	/**
	 * The output describes the settings the ability can write, as core/settings-get describes them.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_output_schema_describes_the_writable_settings(): void {
		$schema   = wp_get_ability( self::ABILITY )->get_output_schema();
		$expected = array_diff_key( wp_get_ability( 'core/settings-get' )->get_output_schema()['properties'], array_flip( self::READ_ONLY_NAMES ) );

		$this->assertSame( 'object', $schema['type'], 'The output should be an object.' );
		$this->assertFalse( $schema['additionalProperties'], 'The output should reject other keys.' );
		$this->assertArrayNotHasKey( 'required', $schema, 'No setting should be required in the output.' );
		$this->assertSame( $expected, $schema['properties'], 'The output should describe every setting but the read-only ones, as core/settings-get does.' );
	}

	/**
	 * The ability is not registered when no setting is exposed to abilities.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_not_registered_without_exposed_settings(): void {
		global $wp_registered_settings;

		$wp_registered_settings = array();
		$this->register_ability();

		$this->assertFalse( wp_has_ability( self::ABILITY ), 'The update ability should not be registered when no setting is exposed.' );
	}

	/**
	 * The update ability is not registered when every exposed setting is read-only, while
	 * core/settings-get still is.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_not_registered_with_only_read_only_settings(): void {
		global $wp_registered_settings;

		$wp_registered_settings = array(
			'settings_update_test_readonly' => $wp_registered_settings['settings_update_test_readonly'],
		);
		$this->register_ability();

		$this->assertFalse( wp_has_ability( self::ABILITY ), 'The update ability should not be registered without a writable setting.' );
		$this->assertTrue( wp_has_ability( 'core/settings-get' ), 'The get ability should still be registered.' );
	}

	/**
	 * A read-only setting is left out of the input and the output of the update ability, but is
	 * still described and read by core/settings-get.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_read_only_setting_is_not_writable_but_still_read(): void {
		$name = 'settings_update_test_readonly';
		$this->store_raw( $name, 'Locked' );
		$this->become_admin();

		$input_properties = wp_get_ability( self::ABILITY )->get_input_schema()['properties']['data']['properties'];
		$read             = wp_get_ability( 'core/settings-get' )->execute( array( 'fields' => array( $name ) ) );

		$this->assertArrayNotHasKey( $name, $input_properties, 'The read-only setting should not be in the input schema.' );
		$this->assertArrayNotHasKey( $name, wp_get_ability( self::ABILITY )->get_output_schema()['properties'], 'The read-only setting should not be in the output schema of the update ability.' );
		$this->assertArrayHasKey( $name, wp_get_ability( 'core/settings-get' )->get_output_schema()['properties'], 'The read-only setting should stay in the output schema of the get ability.' );
		$this->assertSame( array( $name => 'Locked' ), $read, 'The get ability should still return the read-only setting.' );
	}

	/**
	 * A setting registered without `show_in_abilities` cannot be written.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_setting_without_show_in_abilities_is_not_accepted(): void {
		$option = 'settings_update_test_hidden';

		register_setting( 'general', $option, array( 'type' => 'string' ) );
		$this->register_ability();
		$this->become_admin();
		$before = $this->snapshot();

		$properties = wp_get_ability( self::ABILITY )->get_input_schema()['properties']['data']['properties'];
		$result     = $this->update( array( $option => 'x' ) );
		$after      = $this->snapshot();

		$this->assertArrayNotHasKey( $option, $properties, 'The setting should not be in the input schema.' );
		$this->assert_error_code( $result, 'ability_invalid_input' );
		$this->assertSameSetsWithIndex( $before, $after, 'Nothing should be written.' );
	}

	/**
	 * A setting registered after the ability cannot be written.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_setting_registered_after_the_ability_is_not_accepted(): void {
		$option = 'settings_update_test_late';

		register_setting(
			'general',
			$option,
			array(
				'type'              => 'string',
				'show_in_abilities' => true,
			)
		);
		$this->become_admin();
		$before = $this->snapshot();

		$properties = wp_get_ability( self::ABILITY )->get_input_schema()['properties']['data']['properties'];
		$result     = $this->update( array( $option => 'x' ) );
		$after      = $this->snapshot();

		$this->assertArrayNotHasKey( $option, $properties, 'The setting should not be in the input schema.' );
		$this->assert_error_code( $result, 'ability_invalid_input' );
		$this->assertSameSetsWithIndex( $before, $after, 'Nothing should be written.' );
	}

	/**
	 * The provider of settings covers every exposed core setting.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_provider_covers_every_exposed_core_setting(): void {
		$exposed = array_diff(
			array_map( 'strval', array_keys( wp_get_ability( self::ABILITY )->get_input_schema()['properties']['data']['properties'] ) ),
			self::TEST_SETTING_NAMES
		);
		$covered = array_diff( array_column( self::data_settings(), 0 ), self::TEST_SETTING_NAMES );

		$this->assertSameSets( $exposed, $covered, 'Every exposed core setting should be covered.' );
	}

	/**
	 * Users without `manage_options` cannot update settings.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_roles_without_manage_options
	 *
	 * @param string|null $role The role of the user, or null when logged out.
	 */
	public function test_users_without_manage_options_cannot_update( ?string $role ): void {
		wp_set_current_user( null === $role ? 0 : self::$user_ids[ $role ] );
		$before = $this->snapshot();

		$result = $this->update(
			array(
				'title'          => 'Changed title',
				'posts_per_page' => 3,
			)
		);

		$this->assert_error_code( $result, 'ability_invalid_permissions' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string|null}> Roles without `manage_options`, null when logged out.
	 */
	public static function data_roles_without_manage_options(): array {
		return array(
			'subscriber' => array( 'subscriber' ),
			'editor'     => array( 'editor' ),
			'logged out' => array( null ),
		);
	}

	/**
	 * An administrator without `manage_privacy_options` cannot set or reset the privacy policy page,
	 * and the other settings of the call are not written either.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_privacy_policy_page_values
	 *
	 * @param string|null $kind The kind of content whose ID is the privacy policy page, or null to reset it.
	 */
	public function test_privacy_policy_page_refused_without_capability( ?string $kind ): void {
		// On multisite, a site administrator lacks the capability even without the filter.
		$this->become_admin();
		$this->deny_privacy_capability();
		$before = $this->snapshot();
		$data   = array(
			'title'                   => 'Changed title',
			'page_for_privacy_policy' => null === $kind ? null : self::$fixture_ids[ $kind ],
		);

		$permission = wp_get_ability( self::ABILITY )->check_permissions( array( 'data' => $data ) );
		$result     = $this->update( $data );

		$this->assertFalse( $permission, 'The permission check should refuse the call.' );
		$this->assert_error_code( $result, 'ability_invalid_permissions' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string|null}> Kinds of content whose ID is the privacy policy page, or null.
	 */
	public static function data_privacy_policy_page_values(): array {
		return array(
			'a page ID' => array( 'page' ),
			'null'      => array( null ),
		);
	}

	/**
	 * The privacy policy page rule holds when the option is exposed under another name.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_privacy_rule_follows_the_option_under_another_name(): void {
		register_setting(
			'reading',
			'wp_page_for_privacy_policy',
			array(
				'type'              => 'integer',
				'show_in_abilities' => array( 'name' => 'settings_update_test_privacy_page' ),
			)
		);
		$this->register_ability();
		$this->become_admin();
		$this->deny_privacy_capability();
		$before = $this->snapshot();

		$result = $this->update( array( 'settings_update_test_privacy_page' => self::$fixture_ids['page'] ) );

		$this->assert_error_code( $result, 'ability_invalid_permissions' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A `wp_ability_permission_result` filter that grants the call lets a user without
	 * `manage_options` update settings.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_permission_filter_grants_the_update(): void {
		wp_set_current_user( self::$user_ids['subscriber'] );
		add_filter( 'wp_ability_permission_result', '__return_true' );
		$expected             = $this->snapshot();
		$expected['blogname'] = 'Granted title';

		$result = $this->update( array( 'title' => 'Granted title' ) );

		$this->assertSame( array( 'title' => 'Granted title' ), $result, 'The output should show the saved value.' );
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'The setting should be stored.' );
	}

	/**
	 * A `wp_ability_permission_result` filter that grants the call lets a user without
	 * `manage_privacy_options` set the privacy policy page.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_permission_filter_grants_the_privacy_policy_page(): void {
		$this->become_admin();
		$this->deny_privacy_capability();
		add_filter( 'wp_ability_permission_result', '__return_true' );
		$page_id = self::$fixture_ids['page'];

		$result = $this->update( array( 'page_for_privacy_policy' => $page_id ) );

		$this->assertSame( array( 'page_for_privacy_policy' => $page_id ), $result, 'The output should show the saved page.' );
		$this->assertSame( (string) $page_id, $this->snapshot()['wp_page_for_privacy_policy'], 'The page should be stored.' );
	}

	/**
	 * The permission check accepts input that was not prepared, as a direct call to
	 * check_permissions() may pass, and leaves the refusal of invalid input to execute().
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_unprepared_input
	 *
	 * @param mixed $input The input.
	 */
	public function test_permission_check_accepts_unprepared_input( $input ): void {
		$this->become_admin();

		$this->assertTrue( wp_get_ability( self::ABILITY )->check_permissions( $input ), 'The permission check should allow an administrator.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: mixed}> Inputs that were not prepared.
	 */
	public static function data_unprepared_input(): array {
		return array(
			'no input'          => array( null ),
			'a string input'    => array( 'title' ),
			'a string for data' => array( array( 'data' => 'title' ) ),
			'an object input'   => array( (object) array( 'data' => (object) array( 'title' => 'x' ) ) ),
		);
	}

	/**
	 * Input that does not match the input schema is refused and nothing is written.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_invalid_input
	 *
	 * @param mixed $input The input.
	 */
	public function test_rejects_invalid_input( $input ): void {
		$this->store_raw( 'settings_update_test_readonly', 'Locked' );
		$this->become_admin();
		$before = $this->snapshot();

		$result = wp_get_ability( self::ABILITY )->execute( $input );

		$this->assert_error_code( $result, 'ability_invalid_input' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: mixed}> Invalid inputs.
	 */
	public static function data_invalid_input(): array {
		return array(
			'an unknown name'                            => array( array( 'data' => array( 'settings_update_test_unknown' => 'x' ) ) ),
			'an option name instead of the setting name' => array( array( 'data' => array( 'blogname' => 'x' ) ) ),
			'a name in another case'                     => array( array( 'data' => array( 'Title' => 'x' ) ) ),
			'a read-only setting'                        => array( array( 'data' => array( 'settings_update_test_readonly' => 'x' ) ) ),
			'null for a read-only setting'               => array( array( 'data' => array( 'settings_update_test_readonly' => null ) ) ),
			'a read-only setting with a writable one'    => array(
				array(
					'data' => array(
						'title'                         => 'Changed title',
						'settings_update_test_readonly' => 'x',
					),
				),
			),
			'a numeric key that is not a setting name'   => array( array( 'data' => array( 0 => 'x' ) ) ),
			'another top-level key'                      => array(
				array(
					'data'   => array( 'title' => 'x' ),
					'fields' => array( 'title' ),
				),
			),
			'a string input'                             => array( 'title' ),
			'no data'                                    => array( array() ),
			'null data'                                  => array( array( 'data' => null ) ),
			'an empty array for data'                    => array( array( 'data' => array() ) ),
			'an empty object for data'                   => array( array( 'data' => new stdClass() ) ),
			'an empty string for an integer'             => array( array( 'data' => array( 'posts_per_page' => '' ) ) ),
			'an empty string for a boolean'              => array( array( 'data' => array( 'use_smilies' => '' ) ) ),
			'an empty string for an enum'                => array( array( 'data' => array( 'default_ping_status' => '' ) ) ),
			'an unknown object property'                 => array(
				array(
					'data' => array(
						'settings_update_test_object' => array(
							'a' => 1,
							'b' => 2,
						),
					),
				),
			),
			'an unknown nested object property'          => array(
				array(
					'data' => array(
						'settings_update_test_object' => array(
							'n' => array(
								'b' => 1,
								'c' => 1,
							),
						),
					),
				),
			),
			'a valid setting before an invalid one'      => array(
				array(
					'data' => array(
						'title'          => 'Changed title',
						'posts_per_page' => 'abc',
					),
				),
			),
		);
	}

	/**
	 * The site address and the administration email address cannot be updated. On single site
	 * core/settings-get still reads them, and on multisite they are not exposed.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_read_only_core_settings
	 *
	 * @param string      $name  The setting name.
	 * @param string|null $value A valid value, or null.
	 */
	public function test_url_and_email_cannot_be_updated( string $name, ?string $value ): void {
		$this->become_admin();
		$before = $this->snapshot();

		$properties = wp_get_ability( self::ABILITY )->get_input_schema()['properties']['data']['properties'];
		$result     = $this->update( array( $name => $value ) );
		$read       = wp_get_ability( 'core/settings-get' )->execute( array() );

		$this->assertArrayNotHasKey( $name, $properties, 'The setting should not be in the input schema.' );
		$this->assert_error_code( $result, 'ability_invalid_input' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
		$this->assertSame( ! is_multisite(), isset( $read[ $name ] ), 'core/settings-get should read the setting on single site only.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string|null}> Setting names, and valid values or null.
	 */
	public static function data_read_only_core_settings(): array {
		return array(
			'url'            => array( 'url', 'https://acme.example' ),
			'null for url'   => array( 'url', null ),
			'email'          => array( 'email', 'owner@acme.example' ),
			'null for email' => array( 'email', null ),
		);
	}

	/**
	 * The read-only rule holds for each read-only option, also when a plugin exposes it under
	 * another name, or registers its name with surrounding spaces, which the options API trims.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_read_only_options
	 *
	 * @param string $option The name the option is registered with.
	 */
	public function test_read_only_rule_follows_the_option_under_another_name( string $option ): void {
		$name = 'settings_update_test_read_only_option';

		register_setting(
			'general',
			$option,
			array(
				'type'              => 'string',
				'show_in_abilities' => array( 'name' => $name ),
			)
		);
		$this->register_ability();
		$this->become_admin();
		$before = $this->snapshot();

		$result = $this->update( array( $name => 'https://acme.example' ) );

		$this->assertArrayNotHasKey( $name, wp_get_ability( self::ABILITY )->get_input_schema()['properties']['data']['properties'], 'The option should not be writable under another name.' );
		$this->assertArrayHasKey( $name, wp_get_ability( 'core/settings-get' )->get_output_schema()['properties'], 'The option should still be read under another name.' );
		$this->assert_error_code( $result, 'ability_invalid_input' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}> Names that read-only options are registered with.
	 */
	public static function data_read_only_options(): array {
		return array(
			'the WordPress address'                => array( 'siteurl' ),
			'the site address'                     => array( 'home' ),
			'the administration email address'     => array( 'admin_email' ),
			'the WordPress address with spaces'    => array( ' siteurl ' ),
			'the site address with a trailing tab' => array( "home\t" ),
		);
	}

	/**
	 * Each setting is written, read back, and stored.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_settings
	 *
	 * @param string $name   The exposed setting name.
	 * @param string $option The option name.
	 * @param mixed  $value  A valid value, or the kind of content whose ID is the value.
	 * @param string $output The expected output value as JSON.
	 * @param string $raw    The expected raw stored value.
	 */
	public function test_updates_each_setting( string $name, string $option, $value, string $output, string $raw ): void {
		// A data provider runs before the content exists, so it names the kind of content instead of its ID.
		if ( in_array( $option, self::ID_OPTIONS, true ) ) {
			$value  = self::$fixture_ids[ $value ];
			$output = (string) $value;
			$raw    = (string) $value;
		}

		$this->become_admin_for( $name );
		$expected = $this->snapshot();

		$result = $this->update( array( $name => $value ) );

		$this->assertNotWPError( $result, 'The setting should be written.' );
		$this->assertSameSets( array( $name ), array_map( 'strval', array_keys( $result ) ), 'The output should hold only the setting named in the call.' );
		$this->assertSame( $output, wp_json_encode( $result[ $name ] ), 'The output should show the saved value.' );

		$expected[ $option ] = $raw;
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'Only the option of the named setting should change.' );

		wp_cache_delete( 'alloptions', 'options' );
		$read = wp_get_ability( 'core/settings-get' )->execute( array( 'fields' => array( $name ) ) );
		$this->assertSame( $output, wp_json_encode( $read[ $name ] ), 'The saved value should be read back the same way from the database.' );
	}

	/**
	 * A null value stores the default of each setting that has one, and deletes the stored value
	 * of the others, which are then left out.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_settings
	 *
	 * @param string      $name       The exposed setting name.
	 * @param string      $option     The option name.
	 * @param mixed       $value      A valid value, or the kind of content whose ID is the value.
	 * @param string      $output     The expected output value as JSON.
	 * @param string      $raw        The expected raw stored value.
	 * @param string|null $after_null The output value after a null value as JSON, or null when it is left out.
	 */
	public function test_null_resets_each_setting( string $name, string $option, $value, string $output, string $raw, ?string $after_null ): void {
		update_option( $option, in_array( $option, self::ID_OPTIONS, true ) ? self::$fixture_ids[ $value ] : $value );
		$this->become_admin_for( $name );
		$expected = $this->expected_after_null( $option );

		$result = $this->update( array( $name => null ) );

		$this->assertNotWPError( $result, 'The setting should be reset.' );
		$this->assertSame( null === $after_null ? '[]' : '{"' . $name . '":' . $after_null . '}', wp_json_encode( $result ), 'The setting should read as its default, or be left out.' );
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'Only the row of the named setting should hold its default or be deleted.' );
	}

	/**
	 * Returns the stored rows expected after a null value for the given option.
	 *
	 * @param string $option The option name.
	 * @return array<string, string> Raw option values keyed by option name.
	 */
	private function expected_after_null( string $option ): array {
		$expected = $this->snapshot();
		unset( $expected[ $option ] );
		if ( isset( self::STORED_DEFAULTS[ $option ] ) ) {
			$expected[ $option ] = self::STORED_DEFAULTS[ $option ];
		}

		return $expected;
	}

	/**
	 * Data provider.
	 *
	 * Every core setting the ability can update, plus the settings this class registers. A setting that holds the
	 * ID of a page or a category names the kind of content, and the tests fill in the ID.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed, 3: string, 4: string, 5: string|null}> Settings with a valid value.
	 */
	public static function data_settings(): array {
		return array(
			'title'                          => array( 'title', 'blogname', 'Acme Robotics', '"Acme Robotics"', 'Acme Robotics', null ),
			'description'                    => array( 'description', 'blogdescription', 'Robots for everyone', '"Robots for everyone"', 'Robots for everyone', null ),
			'timezone'                       => array( 'timezone', 'timezone_string', 'Europe/Lisbon', '"Europe\/Lisbon"', 'Europe/Lisbon', null ),
			'date_format'                    => array( 'date_format', 'date_format', 'Y-m-d', '"Y-m-d"', 'Y-m-d', null ),
			'time_format'                    => array( 'time_format', 'time_format', 'H:i', '"H:i"', 'H:i', null ),
			'start_of_week'                  => array( 'start_of_week', 'start_of_week', 3, '3', '3', null ),
			'language'                       => array( 'language', 'WPLANG', 'de_DE', '"de_DE"', 'de_DE', '""' ),
			'use_smilies'                    => array( 'use_smilies', 'use_smilies', false, 'false', '', 'true' ),
			'default_category'               => array( 'default_category', 'default_category', 'category', '', '', null ),
			'default_post_format'            => array( 'default_post_format', 'default_post_format', 'aside', '"aside"', 'aside', null ),
			'posts_per_page'                 => array( 'posts_per_page', 'posts_per_page', 12, '12', '12', '10' ),
			'show_on_front'                  => array( 'show_on_front', 'show_on_front', 'page', '"page"', 'page', null ),
			'page_on_front'                  => array( 'page_on_front', 'page_on_front', 'page', '', '', null ),
			'page_for_posts'                 => array( 'page_for_posts', 'page_for_posts', 'page', '', '', null ),
			'page_for_privacy_policy'        => array( 'page_for_privacy_policy', 'wp_page_for_privacy_policy', 'page', '', '', null ),
			'default_ping_status'            => array( 'default_ping_status', 'default_ping_status', 'closed', '"closed"', 'closed', null ),
			'default_comment_status'         => array( 'default_comment_status', 'default_comment_status', 'closed', '"closed"', 'closed', null ),
			'an array setting'               => array( 'settings_update_test_array', 'settings_update_test_array', array( '1', '2' ), '[1,2]', 'a:2:{i:0;i:1;i:1;i:2;}', null ),
			'an empty array setting'         => array( 'settings_update_test_array', 'settings_update_test_array', array(), '[]', 'a:0:{}', null ),
			'an object setting'              => array( 'settings_update_test_object', 'settings_update_test_object', array( 'a' => 1 ), '{"a":1}', 'a:1:{s:1:"a";i:1;}', null ),
			'an empty object setting'        => array( 'settings_update_test_object', 'settings_update_test_object', array(), '{}', 'a:0:{}', null ),
			'a setting with numeric name'    => array( '456', 'settings_update_test_numeric_option', 9, '9', '9', null ),
			'a setting with mixed-case name' => array( 'Mixed_Case_Name', 'settings_update_test_mixed_case_option', 'Mixed', '"Mixed"', 'Mixed', null ),
			'a setting with readonly false'  => array( 'settings_update_test_readonly_false', 'settings_update_test_readonly_false', 'Open', '"Open"', 'Open', null ),
		);
	}

	/**
	 * Input values are converted to the type of the setting and sanitized when they are saved, and
	 * the output shows the value that was saved, not the value that was sent.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_saved_values
	 *
	 * @param string $name   The exposed setting name.
	 * @param string $option The option name.
	 * @param mixed  $stored The raw value stored before the call.
	 * @param mixed  $value  The value to write.
	 * @param string $output The expected output value as JSON.
	 * @param string $raw    The expected raw stored value.
	 */
	public function test_output_shows_the_saved_value( string $name, string $option, $stored, $value, string $output, string $raw ): void {
		$this->store_raw( $option, $stored );
		$this->become_admin();
		$expected = $this->snapshot();

		$result = $this->update( array( $name => $value ) );

		$this->assertNotWPError( $result, 'The setting should be written.' );
		$this->assertSameSets( array( $name ), array_map( 'strval', array_keys( $result ) ), 'The output should hold only the setting named in the call.' );
		$this->assertSame( $output, wp_json_encode( $result[ $name ] ), 'The output should show the value that was saved.' );

		$expected[ $option ] = $raw;
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'Only the option of the named setting should hold the saved value.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed, 3: mixed, 4: string, 5: string}> Values that are converted or saved differently.
	 */
	public static function data_saved_values(): array {
		return array(
			'a numeric string for an integer' => array( 'posts_per_page', 'posts_per_page', '10', '12', '12', '12' ),
			'"false" for a boolean'           => array( 'use_smilies', 'use_smilies', '1', 'false', 'false', '' ),
			'an empty string for a string'    => array( 'title', 'blogname', 'Old title', '', '""', '' ),
			'a script URI for a URI'          => array( 'settings_update_test_uri', 'settings_update_test_uri', 'https://old.example', 'javascript:alert(1)', '""', '' ),
			'an ampersand in the title'       => array( 'title', 'blogname', 'Old title', 'A & B', '"A &amp; B"', 'A &amp; B' ),
			'zero posts per page'             => array( 'posts_per_page', 'posts_per_page', '10', 0, '1', '1' ),
			'English as an empty language'    => array( 'language', 'WPLANG', 'de_DE', '', '""', '' ),
		);
	}

	/**
	 * A value that matches the schema, but that the setting cannot use, is refused. The error names
	 * the setting, and the other settings of the call are not written either.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_unusable_values
	 *
	 * @param string     $name  The exposed setting name.
	 * @param string|int $value The value to write.
	 */
	public function test_refuses_a_value_the_setting_cannot_use( string $name, $value ): void {
		$this->become_admin_for( $name );
		$before = $this->snapshot();

		$result = $this->update(
			array(
				'title' => 'Changed title',
				$name   => $value,
			)
		);

		$this->assert_invalid_value_error( $result, $name );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string|int}> Setting names, and values that match the schema but cannot be used.
	 */
	public static function data_unusable_values(): array {
		return array(
			'an unknown timezone'                       => array( 'timezone', 'Mars/Olympus' ),
			'a UTC offset as the timezone'              => array( 'timezone', 'UTC+2' ),
			'a language that is not installed'          => array( 'language', 'xx_XX' ),
			'an unknown post format'                    => array( 'default_post_format', 'banana' ),
			'a front page that does not exist'          => array( 'page_on_front', 999999 ),
			'a negative front page'                     => array( 'page_on_front', -1 ),
			'a page for posts that does not exist'      => array( 'page_for_posts', 999999 ),
			'a privacy policy page that does not exist' => array( 'page_for_privacy_policy', 999999 ),
			'a default category that does not exist'    => array( 'default_category', 999999 ),
			'no default category'                       => array( 'default_category', 0 ),
			'a negative default category'               => array( 'default_category', -1 ),
		);
	}

	/**
	 * Every value the settings cannot use is reported, before an invalid stored value.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_reports_every_value_the_settings_cannot_use(): void {
		$this->store_raw( 'start_of_week', 'Monday' );
		$this->become_admin();
		$before = $this->snapshot();

		$result = $this->update(
			array(
				'timezone'      => 'Mars/Olympus',
				'start_of_week' => null,
				'language'      => 'xx_XX',
			)
		);

		$this->assert_error_code( $result, 'settings_invalid_value' );
		$this->assertSame( 'Invalid parameter(s): data[timezone], data[language]', $result->get_error_message(), 'The message should name every refused setting.' );
		$this->assertSame( 400, $result->get_error_data()['status'], 'The error should be a client error.' );
		$this->assertSameSets( array( 'data[timezone]', 'data[language]' ), array_keys( $result->get_error_data()['params'] ), 'The error data should name every refused setting.' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * The ID of content of another kind is refused: a post is not a page, and a tag is not a category.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_refuses_the_id_of_another_kind_of_content(): void {
		$this->become_admin();
		$before = $this->snapshot();

		$post = $this->update( array( 'page_on_front' => self::$fixture_ids['post'] ) );
		$tag  = $this->update( array( 'default_category' => self::$fixture_ids['tag'] ) );

		$this->assert_invalid_value_error( $post, 'page_on_front' );
		$this->assert_invalid_value_error( $tag, 'default_category' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A page in the trash or an auto-draft is refused for each page setting, and the other
	 * settings of the call are not written either.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_unusable_page_statuses
	 *
	 * @param string $name   The exposed setting name.
	 * @param string $status The status of the page.
	 */
	public function test_refuses_a_page_in_the_trash_or_an_auto_draft( string $name, string $status ): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => $status,
			)
		);
		$this->become_admin_for( $name );
		$before = $this->snapshot();

		$result = $this->update(
			array(
				'title' => 'Changed title',
				$name   => $page_id,
			)
		);

		$this->assert_invalid_value_error( $result, $name );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string}> Page setting names and page statuses that cannot be used.
	 */
	public static function data_unusable_page_statuses(): array {
		return array(
			'a front page in the trash'          => array( 'page_on_front', 'trash' ),
			'an auto-draft front page'           => array( 'page_on_front', 'auto-draft' ),
			'a page for posts in the trash'      => array( 'page_for_posts', 'trash' ),
			'an auto-draft page for posts'       => array( 'page_for_posts', 'auto-draft' ),
			'a privacy policy page in the trash' => array( 'page_for_privacy_policy', 'trash' ),
			'an auto-draft privacy policy page'  => array( 'page_for_privacy_policy', 'auto-draft' ),
		);
	}

	/**
	 * A draft page can be the privacy policy page, as Settings > Privacy offers drafts.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_accepts_a_draft_privacy_policy_page(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		$this->become_admin_for( 'page_for_privacy_policy' );

		$result = $this->update( array( 'page_for_privacy_policy' => $page_id ) );

		$this->assertSame( array( 'page_for_privacy_policy' => $page_id ), $result, 'The output should show the saved page.' );
		$this->assertSame( (string) $page_id, $this->snapshot()['wp_page_for_privacy_policy'], 'The draft page should be stored.' );
	}

	/**
	 * The value rules hold when the option is exposed under another name.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_value_rule_follows_the_option_under_another_name(): void {
		$name = 'settings_update_test_timezone';

		register_setting(
			'general',
			'timezone_string',
			array(
				'type'              => 'string',
				'show_in_abilities' => array( 'name' => $name ),
			)
		);
		$this->register_ability();
		$this->become_admin();
		$before = $this->snapshot();

		$result = $this->update( array( $name => 'Mars/Olympus' ) );

		$this->assert_invalid_value_error( $result, $name );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * The values that stand for "none" and the other forms a setting can use are saved.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_usable_values
	 *
	 * @param string     $name   The exposed setting name.
	 * @param string     $option The option name.
	 * @param string|int $stored The raw value stored before the call.
	 * @param string|int $value  The value to write.
	 */
	public function test_accepts_a_value_the_setting_can_use( string $name, string $option, $stored, $value ): void {
		$this->store_raw( $option, $stored );
		$this->become_admin_for( $name );
		$expected = $this->snapshot();

		$result = $this->update( array( $name => $value ) );

		$this->assertSame( array( $name => $value ), $result, 'The output should show the saved value.' );
		$expected[ $option ] = (string) $value;
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'Only the option of the named setting should hold the saved value.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string|int, 3: string|int}> Setting names, option names, stored values and values to write.
	 */
	public static function data_usable_values(): array {
		return array(
			'no timezone'                  => array( 'timezone', 'timezone_string', 'Europe/Lisbon', '' ),
			'UTC as the timezone'          => array( 'timezone', 'timezone_string', 'Europe/Lisbon', 'UTC' ),
			'a timezone under an old name' => array( 'timezone', 'timezone_string', 'Europe/Lisbon', 'US/Pacific' ),
			'no default post format'       => array( 'default_post_format', 'default_post_format', 'aside', '0' ),
			'the standard post format'     => array( 'default_post_format', 'default_post_format', 'aside', 'standard' ),
			'an empty default post format' => array( 'default_post_format', 'default_post_format', 'aside', '' ),
			'no front page'                => array( 'page_on_front', 'page_on_front', '5', 0 ),
			'no page for posts'            => array( 'page_for_posts', 'page_for_posts', '5', 0 ),
			'no privacy policy page'       => array( 'page_for_privacy_policy', 'wp_page_for_privacy_policy', '5', 0 ),
		);
	}

	/**
	 * The language that core/settings-get reads when none is stored, `en_US`, can be written back,
	 * and is saved as English.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_en_us_language_is_saved_as_english(): void {
		$this->store_raw( 'WPLANG', null );
		$this->become_admin();
		$read = wp_get_ability( 'core/settings-get' )->execute( array( 'fields' => array( 'language' ) ) );
		$this->store_raw( 'WPLANG', 'de_DE' );
		$expected = $this->snapshot();

		$result = $this->update( $read );

		$this->assertSame( array( 'language' => 'en_US' ), $read, 'The language should read as en_US when none is stored.' );
		$this->assertSame( array( 'language' => '' ), $result, 'en_US should be saved as English.' );
		$expected['WPLANG'] = '';
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'English should be stored as an empty string.' );
	}

	/**
	 * The same call twice succeeds and returns the same output.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_same_call_twice(): void {
		$this->become_admin();
		$data = array(
			'title'          => 'Twice',
			'posts_per_page' => 12,
		);

		$first  = $this->update( $data );
		$second = $this->update( $data );

		$this->assertNotWPError( $second, 'Writing unchanged values should not fail.' );
		$this->assertSame( $first, $second, 'The second call should return the same output.' );
	}

	/**
	 * A call that names several settings writes and resets exactly those, and returns only those.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_updates_several_settings(): void {
		$this->store_raw( 'use_smilies', '' );
		$this->become_admin();
		$expected = $this->snapshot();

		$result = $this->update(
			array(
				'title'          => 'Acme Robotics',
				'posts_per_page' => 12,
				'use_smilies'    => null,
			)
		);

		$this->assertNotWPError( $result, 'The settings should be written.' );
		$this->assertSameSets( array( 'title', 'posts_per_page', 'use_smilies' ), array_keys( $result ), 'The output should hold only the settings named in the call.' );
		$this->assertSame( 'Acme Robotics', $result['title'], 'The output should show the new title.' );
		$this->assertSame( 12, $result['posts_per_page'], 'The output should show the new number of posts per page.' );
		$this->assertTrue( $result['use_smilies'], 'The reset setting should read as its default.' );

		$expected['blogname']       = 'Acme Robotics';
		$expected['posts_per_page'] = '12';
		$expected['use_smilies']    = '1';
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'Only the named settings should change.' );
	}

	/**
	 * The sanitize callback of a setting runs once, while the value is saved.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_sanitize_callback_runs_inside_the_save(): void {
		$option = 'settings_update_test_sanitized';
		$calls  = 0;

		register_setting(
			'general',
			$option,
			array(
				'type'              => 'string',
				'show_in_abilities' => true,
				'sanitize_callback' => static function ( $value ) use ( &$calls ): string {
					++$calls;
					return strtoupper( (string) $value );
				},
			)
		);
		$this->register_ability();
		$this->store_raw( $option, 'seed' );
		$this->become_admin();

		$result = $this->update( array( $option => 'abc' ) );
		$after  = $this->snapshot();

		$this->assertSame( array( $option => 'ABC' ), $result, 'The output should show the sanitized value.' );
		$this->assertSame( 1, $calls, 'The sanitize callback should run once.' );
		$this->assertSame( 'ABC', $after[ $option ], 'The sanitized value should be stored.' );
	}

	/**
	 * A null value is refused when the stored value does not match the schema of the setting,
	 * with or without a default, and nothing is replaced or deleted. The error names the setting.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_invalid_stored_values
	 *
	 * @param string $name   The exposed setting name.
	 * @param string $option The option name.
	 * @param mixed  $stored The raw value stored before the call.
	 */
	public function test_null_refuses_an_invalid_stored_value( string $name, string $option, $stored ): void {
		$this->store_raw( $option, $stored );
		$this->become_admin();
		$before = $this->snapshot();

		$result = $this->update( array( $name => null ) );

		$this->assert_refused_setting_error( $result, 'settings_invalid_stored_value', 500, $name );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be replaced or deleted.' );
	}

	/**
	 * Every setting whose stored value a null value cannot replace or delete is reported.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_reports_every_invalid_stored_value(): void {
		$this->store_raw( 'start_of_week', 'Monday' );
		$this->store_raw( 'default_ping_status', 'maybe' );
		$this->become_admin();
		$before = $this->snapshot();

		$result = $this->update(
			array(
				'start_of_week'       => null,
				'default_ping_status' => null,
			)
		);

		$this->assert_error_code( $result, 'settings_invalid_stored_value' );
		$this->assertSame( 'Invalid parameter(s): data[start_of_week], data[default_ping_status]', $result->get_error_message(), 'The message should name every refused setting.' );
		$this->assertSame( 500, $result->get_error_data()['status'], 'The error should be a server error.' );
		$this->assertSameSets( array( 'data[start_of_week]', 'data[default_ping_status]' ), array_keys( $result->get_error_data()['params'] ), 'The error data should name every refused setting.' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be deleted.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed}> Stored values that a null value cannot replace or delete.
	 */
	public static function data_invalid_stored_values(): array {
		return array(
			'a word for an integer'                => array( 'start_of_week', 'start_of_week', 'Monday' ),
			'an empty string for an integer'       => array( 'start_of_week', 'start_of_week', '' ),
			'another word for an enum'             => array( 'default_ping_status', 'default_ping_status', 'maybe' ),
			'an empty string for an enum'          => array( 'default_ping_status', 'default_ping_status', '' ),
			'an empty string for an email address' => array( 'settings_update_test_email', 'settings_update_test_email_option', '' ),
			'an array for a string'                => array( 'title', 'blogname', array( 'A sneaky array!' ) ),
			'a word for an integer with a default' => array( 'posts_per_page', 'posts_per_page', 'ten' ),
			'a word for a boolean with a default'  => array( 'use_smilies', 'use_smilies', 'maybe' ),
		);
	}

	/**
	 * A null value resets a stored value that matches the schema of the setting, including the
	 * forms in which a boolean is stored, and a setting with no stored value.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_valid_stored_values
	 *
	 * @param string $name   The exposed setting name.
	 * @param string $option The option name.
	 * @param mixed  $stored The raw value stored before the call, or null for no row.
	 * @param string $output The expected output as JSON.
	 */
	public function test_null_accepts_a_valid_stored_value( string $name, string $option, $stored, string $output ): void {
		$this->store_raw( $option, $stored );
		$this->become_admin();
		$expected = $this->expected_after_null( $option );

		$result = $this->update( array( $name => null ) );

		$this->assertNotWPError( $result, 'The setting should be reset.' );
		$this->assertSame( $output, wp_json_encode( $result ), 'The setting should read as its default, or be left out.' );
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'Only the row of the named setting should hold its default or be deleted.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed, 3: string}> Stored values that a null value resets, and the output as JSON.
	 */
	public static function data_valid_stored_values(): array {
		return array(
			'a numeric string for an integer'         => array( 'posts_per_page', 'posts_per_page', '12', '{"posts_per_page":10}' ),
			'an empty string for a string'            => array( 'title', 'blogname', '', '[]' ),
			'an empty string for a boolean'           => array( 'use_smilies', 'use_smilies', '', '{"use_smilies":true}' ),
			'"0" for a boolean'                       => array( 'use_smilies', 'use_smilies', '0', '{"use_smilies":true}' ),
			'"1" for a boolean'                       => array( 'use_smilies', 'use_smilies', '1', '{"use_smilies":true}' ),
			'no value for a boolean'                  => array( 'use_smilies', 'use_smilies', null, '{"use_smilies":true}' ),
			'no value for an integer with a default'  => array( 'posts_per_page', 'posts_per_page', null, '{"posts_per_page":10}' ),
			'no value for the language'               => array( 'language', 'WPLANG', null, '{"language":""}' ),
			'no value for a string without a default' => array( 'title', 'blogname', null, '[]' ),
			'false for a boolean without a default'   => array( 'settings_update_test_boolean', 'settings_update_test_boolean', '', '[]' ),
			'"0" for a boolean without a default'     => array( 'settings_update_test_boolean', 'settings_update_test_boolean', '0', '[]' ),
			'"1" for a boolean without a default'     => array( 'settings_update_test_boolean', 'settings_update_test_boolean', '1', '[]' ),
		);
	}

	/**
	 * Repeating a null value stores the default of a setting with one again, and changes nothing
	 * for a setting without one, which has no stored value left.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_repeated_null(): void {
		$this->store_raw( 'posts_per_page', '12' );
		$this->store_raw( 'blogname', 'Acme Robotics' );
		$this->become_admin();

		$this->assertSame( array( 'posts_per_page' => 10 ), $this->update( array( 'posts_per_page' => null ) ), 'The first call should store the default.' );
		$this->assertSame( array( 'posts_per_page' => 10 ), $this->update( array( 'posts_per_page' => null ) ), 'The second call should store the default again.' );

		$this->assertSame( array(), $this->update( array( 'title' => null ) ), 'The first call should delete the stored value.' );
		$this->assertSame( array(), $this->update( array( 'title' => null ) ), 'The second call should change nothing.' );
		$this->assertArrayNotHasKey( 'blogname', $this->snapshot(), 'The stored value should stay deleted.' );
	}

	/**
	 * The default is stored when it is written to a setting that has no stored value, so requests
	 * that do not register the setting read it too.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_default_is_stored_without_a_stored_value(): void {
		$this->store_raw( 'use_smilies', null );
		$this->store_raw( 'posts_per_page', null );
		$this->become_admin();
		$expected                   = $this->snapshot();
		$expected['use_smilies']    = '1';
		$expected['posts_per_page'] = '10';

		$result = $this->update(
			array(
				'use_smilies'    => true,
				'posts_per_page' => 10,
			)
		);

		$this->assertNotWPError( $result, 'The defaults should be written.' );
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'The defaults should be stored.' );
	}

	/**
	 * A value equal to the default that get_option() reads is stored when the setting has no
	 * stored value, also when the setting is exposed with another default or has none.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_values_equal_to_the_read_default
	 *
	 * @param array<string, mixed> $args   The setting registration arguments.
	 * @param mixed                $value  The value to write.
	 * @param string               $output The expected output value as JSON.
	 * @param string               $raw    The expected raw stored value.
	 */
	public function test_value_equal_to_the_read_default_is_stored( array $args, $value, string $output, string $raw ): void {
		$option = 'settings_update_test_read_default';
		register_setting( 'general', $option, $args );
		$this->register_ability();
		$this->become_admin();

		$result = $this->update( array( $option => $value ) );

		$this->assertSame( '{"' . $option . '":' . $output . '}', wp_json_encode( $result ), 'The output should show the written value.' );
		$this->assertSame( $raw, $this->snapshot()[ $option ] ?? null, 'The value should be stored.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: mixed, 2: string, 3: string}> Settings and a value equal to the default get_option() reads.
	 */
	public static function data_values_equal_to_the_read_default(): array {
		return array(
			'the registered default under another exposed default' => array(
				array(
					'type'              => 'string',
					'default'           => 'Registered',
					'show_in_abilities' => array(
						'schema' => array( 'default' => 'Exposed' ),
					),
				),
				'Registered',
				'"Registered"',
				'Registered',
			),
			'false for a boolean without a default' => array(
				array(
					'type'              => 'boolean',
					'show_in_abilities' => true,
				),
				false,
				'false',
				'',
			),
		);
	}

	/**
	 * A false value written over a stored value updates the option without adding it again, also
	 * when false is the default get_option() reads.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_false_values_over_a_stored_value
	 *
	 * @param array<string, mixed> $args  The setting registration arguments.
	 * @param bool|null            $value The value to write.
	 */
	public function test_false_over_a_stored_value_does_not_add_the_option( array $args, ?bool $value ): void {
		global $wpdb;

		$option = 'settings_update_test_stored_boolean';
		register_setting( 'general', $option, $args );
		$this->register_ability();
		$this->store_raw( $option, '1' );
		$this->become_admin();

		$added = array();
		add_action(
			'add_option',
			static function ( $name ) use ( &$added ): void {
				$added[] = $name;
			}
		);

		$result = $this->update( array( $option => $value ) );

		$this->assertSame( array( $option => false ), $result, 'The output should show the written value.' );
		$this->assertSame( '', $this->snapshot()[ $option ] ?? null, 'The value should be stored.' );
		$this->assertNotContains( $option, $added, 'The stored option should not be added again.' );
		$this->assertSame(
			'on',
			$wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option ) ),
			'The autoload value of the stored option should not change.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: bool|null}> Boolean settings and a value that is saved as false.
	 */
	public static function data_false_values_over_a_stored_value(): array {
		return array(
			'false for a boolean without a default'      => array(
				array(
					'type'              => 'boolean',
					'show_in_abilities' => true,
				),
				false,
			),
			'false for a boolean that defaults to false' => array(
				array(
					'type'              => 'boolean',
					'default'           => false,
					'show_in_abilities' => true,
				),
				false,
			),
			'null for a boolean that defaults to false'  => array(
				array(
					'type'              => 'boolean',
					'default'           => false,
					'show_in_abilities' => true,
				),
				null,
			),
		);
	}

	/**
	 * Nothing is written or deleted when a later setting of the call fails its stored-value check.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_calls_failing_late
	 *
	 * @param array<string, mixed> $data The settings to update.
	 */
	public function test_nothing_is_written_when_a_later_check_fails( array $data ): void {
		$this->store_raw( 'use_smilies', '0' );
		$this->store_raw( 'start_of_week', 'Monday' );
		$this->become_admin();
		$before = $this->snapshot();

		$result = $this->update( $data );

		$this->assert_error_code( $result, 'settings_invalid_stored_value' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written or deleted.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: array<string, mixed>}> Calls whose last setting has an invalid stored value.
	 */
	public static function data_calls_failing_late(): array {
		return array(
			'a value first'    => array(
				array(
					'title'         => 'Changed title',
					'start_of_week' => null,
				),
			),
			'a reset first'    => array(
				array(
					'use_smilies'   => null,
					'start_of_week' => null,
				),
			),
			'a deletion first' => array(
				array(
					'description'   => null,
					'start_of_week' => null,
				),
			),
		);
	}

	/**
	 * An administrator of another site of the network, who is not a member of the current site,
	 * cannot update its settings.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @group ms-required
	 */
	public function test_administrator_of_another_site_cannot_update(): void {
		$user_id = self::factory()->user->create();
		remove_user_from_blog( $user_id, get_current_blog_id() );
		add_user_to_blog( self::factory()->blog->create(), $user_id, 'administrator' );
		wp_set_current_user( $user_id );
		$before = $this->snapshot();

		$result = $this->update( array( 'title' => 'Changed title' ) );

		$this->assert_error_code( $result, 'ability_invalid_permissions' );
		$this->assertSameSetsWithIndex( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A network administrator who is not a member of the current site can update its settings,
	 * including the privacy policy page.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @group ms-required
	 */
	public function test_network_administrator_without_membership_can_update(): void {
		$user_id = self::factory()->user->create();
		remove_user_from_blog( $user_id, get_current_blog_id() );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );
		$expected = $this->snapshot();

		$result = $this->update(
			array(
				'title'                   => 'Network title',
				'page_for_privacy_policy' => self::$fixture_ids['page'],
			)
		);

		$this->assertNotWPError( $result, 'The settings should be written.' );
		$this->assertSameSets( array( 'title', 'page_for_privacy_policy' ), array_keys( $result ), 'The output should hold only the settings named in the call.' );
		$this->assertSame( 'Network title', $result['title'], 'The output should show the saved title.' );
		$this->assertSame( self::$fixture_ids['page'], $result['page_for_privacy_policy'], 'The output should show the saved privacy policy page.' );
		$expected['blogname']                   = 'Network title';
		$expected['wp_page_for_privacy_policy'] = (string) self::$fixture_ids['page'];
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'Both settings should be saved.' );
	}

	/**
	 * A setting whose value read after the call does not match its schema is left out of the output,
	 * while the other settings of the call are present.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_output_leaves_out_a_value_its_schema_rejects(): void {
		$this->become_admin();
		add_filter(
			'option_posts_per_page',
			static function () {
				return 'ten';
			}
		);
		$expected = $this->snapshot();

		$result = $this->update(
			array(
				'title'          => 'Acme Robotics',
				'posts_per_page' => 12,
			)
		);

		$this->assertSame( array( 'title' => 'Acme Robotics' ), $result, 'The rejected value should be left out of the output.' );
		$expected['blogname']       = 'Acme Robotics';
		$expected['posts_per_page'] = '12';
		$this->assertSameSetsWithIndex( $expected, $this->snapshot(), 'Both settings should still be written.' );
	}
}
