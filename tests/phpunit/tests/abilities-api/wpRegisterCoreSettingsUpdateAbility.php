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
	);

	/**
	 * The message of the error for a user who cannot manage options.
	 *
	 * @var string
	 */
	const CANNOT_UPDATE_MESSAGE = 'Sorry, you are not allowed to manage options for this site.';

	/**
	 * The message of the error for a user who cannot manage privacy options.
	 *
	 * @var string
	 */
	const CANNOT_MANAGE_PRIVACY_MESSAGE = 'Sorry, you are not allowed to manage privacy options on this site.';

	/**
	 * The message of the error for a stored value that cannot be deleted.
	 *
	 * @var string
	 */
	const INVALID_STORED_VALUE_MESSAGE = 'The %s property has an invalid stored value, and cannot be updated to null.';

	/**
	 * User IDs keyed by role.
	 *
	 * @var array<string, int>
	 */
	private static $user_ids = array();

	/**
	 * Number of calls of the sanitize callback of the test setting.
	 *
	 * @var int
	 */
	private static $sanitize_calls = 0;

	/**
	 * Creates a user for each role without `manage_options`.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		parent::wpSetUpBeforeClass( $factory );

		foreach ( array( 'subscriber', 'contributor', 'author', 'editor' ) as $role ) {
			self::$user_ids[ $role ] = $factory->user->create( array( 'role' => $role ) );
		}
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
	}

	/**
	 * Unregisters the settings registered by register_test_settings().
	 *
	 * @since 7.3.0
	 */
	protected static function unregister_test_settings(): void {
		unregister_setting( 'general', 'settings_update_test_array' );
		unregister_setting( 'general', 'settings_update_test_object' );
		unregister_setting( 'general', 'settings_update_test_email_option' );
		unregister_setting( 'general', 'settings_update_test_uri' );
		unregister_setting( 'general', 'settings_update_test_numeric_option' );
		unregister_setting( 'general', 'settings_update_test_mixed_case_option' );
		unregister_setting( 'general', 'settings_update_test_readonly' );
		unregister_setting( 'general', 'settings_update_test_readonly_false' );
	}

	/**
	 * Sanitize callback of a test setting: counts its calls and upper-cases the value.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string The upper-cased value.
	 */
	public static function sanitize_test_value( $value ): string {
		++self::$sanitize_calls;
		return strtoupper( (string) $value );
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

		$snapshot = array_column( $rows, 'option_value', 'option_name' );
		ksort( $snapshot );
		return $snapshot;
	}

	/**
	 * Returns the raw stored value of one option, read from the database.
	 *
	 * @param string $option The option name.
	 * @return string|null The raw value, or null when there is no row.
	 */
	private function raw_row( string $option ): ?string {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
	}

	/**
	 * Asserts that two snapshots hold the same rows, whatever the order of their keys.
	 *
	 * @param array<string, string> $expected The expected rows.
	 * @param array<string, string> $actual   The actual rows.
	 * @param string                $message  The assertion message.
	 */
	private function assert_snapshot( array $expected, array $actual, string $message ): void {
		ksort( $expected );
		ksort( $actual );
		$this->assertSame( $expected, $actual, $message );
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
	 * Asserts that a result is an error with the given code, status and message.
	 *
	 * @param mixed  $result  The result of the ability.
	 * @param string $code    The expected error code.
	 * @param int    $status  The expected status.
	 * @param string $message The expected message.
	 */
	private function assert_error( $result, string $code, int $status, string $message ): void {
		$this->assert_error_code( $result, $code );
		$this->assertSame( $message, $result->get_error_message(), 'The error should use the expected message.' );
		$this->assertSame( array( 'status' => $status ), $result->get_error_data(), 'The error data should hold only the expected status.' );
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
		$this->assertTrue( $annotations['destructive'], 'The ability should be marked destructive, since a null value deletes a stored value.' );
		$this->assertFalse( $annotations['idempotent'], 'The ability should not be marked idempotent, since repeating a null value can fail.' );
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
		$this->assertSame( array( 'manage_options' ), wp_get_ability( self::ABILITY )->get_required_capabilities() );
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
			array_diff( array_map( 'strval', array_keys( wp_get_ability( 'core/settings-get' )->get_output_schema()['properties'] ) ), array( 'settings_update_test_readonly' ) ),
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
	 * The output describes the same settings as the output of core/settings-get.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_output_schema_matches_settings_get(): void {
		$schema = wp_get_ability( self::ABILITY )->get_output_schema();

		$this->assertSame( 'object', $schema['type'], 'The output should be an object.' );
		$this->assertFalse( $schema['additionalProperties'], 'The output should reject other keys.' );
		$this->assertArrayNotHasKey( 'required', $schema, 'No setting should be required in the output.' );
		$this->assertSame( wp_get_ability( 'core/settings-get' )->get_output_schema()['properties'], $schema['properties'], 'The output should describe the settings as core/settings-get does.' );
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

		$this->assertFalse( wp_has_ability( self::ABILITY ) );
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
	 * A read-only setting is left out of the input, but is still described in the output and read
	 * by core/settings-get.
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
		$this->assertArrayHasKey( $name, wp_get_ability( self::ABILITY )->get_output_schema()['properties'], 'The read-only setting should stay in the output schema of the update ability.' );
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
		$this->assert_snapshot( $before, $after, 'Nothing should be written.' );
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
		$this->assert_snapshot( $before, $after, 'Nothing should be written.' );
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
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string|null}> Roles without `manage_options`, null when logged out.
	 */
	public static function data_roles_without_manage_options(): array {
		return array(
			'subscriber'  => array( 'subscriber' ),
			'contributor' => array( 'contributor' ),
			'author'      => array( 'author' ),
			'editor'      => array( 'editor' ),
			'logged out'  => array( null ),
		);
	}

	/**
	 * The permission check refuses users without `manage_options` whatever the raw input.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_roles_and_raw_inputs
	 *
	 * @param string|null $role  The role of the user, or null when logged out.
	 * @param mixed       $input The raw input.
	 */
	public function test_check_permissions_refuses_raw_input( ?string $role, $input ): void {
		wp_set_current_user( null === $role ? 0 : self::$user_ids[ $role ] );

		$this->assertFalse( wp_get_ability( self::ABILITY )->check_permissions( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string|null, 1: mixed}> Roles and raw inputs.
	 */
	public static function data_roles_and_raw_inputs(): array {
		$inputs = array(
			'valid input'           => array( 'data' => array( 'title' => 'Changed title' ) ),
			'a string'              => 'not-an-object',
			'null'                  => null,
			'an option name inside' => array( 'data' => array( 'blogname' => 'Changed title' ) ),
		);

		$data = array();
		foreach ( self::data_roles_without_manage_options() as $role_label => $role ) {
			foreach ( $inputs as $input_label => $input ) {
				$data[ "{$role_label}, {$input_label}" ] = array( $role[0], $input );
			}
		}
		return $data;
	}

	/**
	 * An administrator passes the permission check whatever the raw input.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_check_permissions_allows_administrator_with_raw_input(): void {
		$this->become_admin();
		$ability = wp_get_ability( self::ABILITY );

		$this->assertTrue( $ability->check_permissions( 'not-an-object' ), 'A string input should not change the permission check.' );
		$this->assertTrue( $ability->check_permissions( null ), 'A null input should not change the permission check.' );
	}

	/**
	 * A user without `manage_options` cannot update settings even when a filter grants permission.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_users_granted_permission
	 *
	 * @param string|null $role   The role of the user, or null when logged out.
	 * @param int         $status The expected status.
	 */
	public function test_granted_permission_without_manage_options_cannot_update( ?string $role, int $status ): void {
		wp_set_current_user( null === $role ? 0 : self::$user_ids[ $role ] );
		add_filter( 'wp_ability_permission_result', '__return_true' );
		$before = $this->snapshot();

		$result = $this->update( array( 'title' => 'Changed title' ) );

		$this->assert_error( $result, 'settings_cannot_update', $status, self::CANNOT_UPDATE_MESSAGE );
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string|null, 1: int}> Roles, null when logged out, and the expected status.
	 */
	public static function data_users_granted_permission(): array {
		return array(
			'subscriber' => array( 'subscriber', 403 ),
			'editor'     => array( 'editor', 403 ),
			'logged out' => array( null, 401 ),
		);
	}

	/**
	 * A site administrator cannot set or reset the privacy policy page on multisite, and the other
	 * settings of the call are not written either.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_privacy_policy_page_values
	 *
	 * @param int|null $value The privacy policy page to set, or null to reset it.
	 */
	public function test_site_administrator_cannot_set_privacy_policy_page( ?int $value ): void {
		$this->become_admin();
		$before = $this->snapshot();

		$result = $this->update(
			array(
				'title'                   => 'Changed title',
				'page_for_privacy_policy' => $value,
			)
		);

		$this->assert_error( $result, 'settings_cannot_manage_privacy_options', 403, self::CANNOT_MANAGE_PRIVACY_MESSAGE );
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be written.' );
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
	 * @param int|null $value The privacy policy page to set, or null to reset it.
	 */
	public function test_privacy_policy_page_refused_without_capability( ?int $value ): void {
		$this->become_admin_for( 'page_for_privacy_policy' );
		$this->deny_privacy_capability();
		$before = $this->snapshot();

		$result = $this->update(
			array(
				'title'                   => 'Changed title',
				'page_for_privacy_policy' => $value,
			)
		);

		$this->assert_error( $result, 'settings_cannot_manage_privacy_options', 403, self::CANNOT_MANAGE_PRIVACY_MESSAGE );
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: int|null}> Privacy policy page values.
	 */
	public static function data_privacy_policy_page_values(): array {
		return array(
			'a page ID' => array( 5 ),
			'null'      => array( null ),
		);
	}

	/**
	 * The privacy policy page rule holds when the option is exposed under another name, and does
	 * not apply once the setting is unregistered, since nothing is written for it then.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_privacy_page_under_another_name
	 *
	 * @param bool        $unregister Whether the setting is unregistered before the call.
	 * @param string|null $code       The expected error code, or null when the call succeeds.
	 */
	public function test_privacy_rule_follows_the_option_under_another_name( bool $unregister, ?string $code ): void {
		register_setting(
			'reading',
			'wp_page_for_privacy_policy',
			array(
				'type'              => 'integer',
				'show_in_abilities' => array( 'name' => 'settings_update_test_privacy_page' ),
			)
		);
		$this->register_ability();
		if ( $unregister ) {
			unregister_setting( 'reading', 'wp_page_for_privacy_policy' );
		}
		$this->become_admin_for( 'page_for_privacy_policy' );
		$this->deny_privacy_capability();
		$before = $this->snapshot();

		$result = $this->update( array( 'settings_update_test_privacy_page' => 5 ) );
		$after  = $this->snapshot();

		if ( null === $code ) {
			$this->assertSame( array(), $result, 'The unregistered setting should be left out of the output.' );
		} else {
			$this->assert_error( $result, $code, 403, self::CANNOT_MANAGE_PRIVACY_MESSAGE );
		}
		$this->assert_snapshot( $before, $after, 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: bool, 1: string|null}> Whether the setting is unregistered, and the expected error code.
	 */
	public static function data_privacy_page_under_another_name(): array {
		return array(
			'registered'                   => array( false, 'settings_cannot_manage_privacy_options' ),
			'unregistered before the call' => array( true, null ),
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
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be written.' );
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
			'a mixed-case name in lower case'            => array( array( 'data' => array( 'mixed_case_name' => 'x' ) ) ),
			'a numeric key that is not a setting name'   => array( array( 'data' => array( 0 => 'x' ) ) ),
			'a nested key'                               => array( array( 'data' => array( 'title' => array( 'blogname' => 'x' ) ) ) ),
			'another top-level key'                      => array(
				array(
					'data'   => array( 'title' => 'x' ),
					'fields' => array( 'title' ),
				),
			),
			'a string input'                             => array( 'title' ),
			'no data'                                    => array( array() ),
			'null data'                                  => array( array( 'data' => null ) ),
			'a string for data'                          => array( array( 'data' => 'title' ) ),
			'an empty array for data'                    => array( array( 'data' => array() ) ),
			'an empty object for data'                   => array( array( 'data' => new stdClass() ) ),
			'an object for a string'                     => array( array( 'data' => array( 'title' => array( 'rendered' => 'x' ) ) ) ),
			'a float for an integer'                     => array( array( 'data' => array( 'posts_per_page' => 12.5 ) ) ),
			'true for an integer'                        => array( array( 'data' => array( 'posts_per_page' => true ) ) ),
			'an empty string for an integer'             => array( array( 'data' => array( 'posts_per_page' => '' ) ) ),
			'a word for a boolean'                       => array( array( 'data' => array( 'use_smilies' => 'yes' ) ) ),
			'an empty string for a boolean'              => array( array( 'data' => array( 'use_smilies' => '' ) ) ),
			'a value in another case for an enum'        => array( array( 'data' => array( 'default_ping_status' => 'OPEN' ) ) ),
			'an empty string for an enum'                => array( array( 'data' => array( 'default_ping_status' => '' ) ) ),
			'two values for an enum'                     => array( array( 'data' => array( 'default_ping_status' => 'open&closed' ) ) ),
			'an invalid email address'                   => array( array( 'data' => array( 'settings_update_test_email' => 'not-an-email' ) ) ),
			'an invalid array item'                      => array( array( 'data' => array( 'settings_update_test_array' => array( 'invalid' ) ) ) ),
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
			'an invalid object property'                 => array( array( 'data' => array( 'settings_update_test_object' => array( 'a' => 'invalid' ) ) ) ),
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
	 * The site address and the administration email address cannot be written on multisite.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_single_site_settings
	 *
	 * @param string $name  The setting name.
	 * @param string $value A valid value.
	 */
	public function test_rejects_url_and_email_on_multisite( string $name, string $value ): void {
		$this->become_admin();
		$before = $this->snapshot();

		$properties = wp_get_ability( self::ABILITY )->get_input_schema()['properties']['data']['properties'];
		$result     = $this->update( array( $name => $value ) );

		$this->assertArrayNotHasKey( $name, $properties, 'The setting should not be in the input schema.' );
		$this->assert_error_code( $result, 'ability_invalid_input' );
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string}> Setting names and valid values.
	 */
	public static function data_single_site_settings(): array {
		return array(
			'url'   => array( 'url', 'https://acme.example' ),
			'email' => array( 'email', 'owner@acme.example' ),
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
	 * @param string      $name   The exposed setting name.
	 * @param string      $option The option name.
	 * @param mixed       $value  A valid value.
	 * @param string      $output The expected output value as JSON.
	 * @param string      $raw    The expected raw stored value.
	 */
	public function test_updates_each_setting( string $name, string $option, $value, string $output, string $raw ): void {
		$this->become_admin_for( $name );
		$expected = $this->snapshot();

		$result = $this->update( array( $name => $value ) );

		$this->assertNotWPError( $result, 'The setting should be written.' );
		$this->assertSameSets( array( $name ), array_map( 'strval', array_keys( $result ) ), 'The output should hold only the setting named in the call.' );
		$this->assertSame( $output, wp_json_encode( $result[ $name ] ), 'The output should show the saved value.' );

		$read = wp_get_ability( 'core/settings-get' )->execute( array( 'fields' => array( $name ) ) );
		$this->assertSame( $output, wp_json_encode( $read[ $name ] ), 'The saved value should be read back the same way.' );

		wp_cache_delete( 'alloptions', 'options' );
		$expected[ $option ] = $raw;
		$this->assert_snapshot( $expected, $this->snapshot(), 'Only the option of the named setting should change.' );

		$read = wp_get_ability( 'core/settings-get' )->execute( array( 'fields' => array( $name ) ) );
		$this->assertSame( $output, wp_json_encode( $read[ $name ] ), 'The saved value should be read back the same way from the database.' );
	}

	/**
	 * A null value deletes the stored value of each setting, which then reads as its default or
	 * is left out.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_settings
	 *
	 * @param string      $name       The exposed setting name.
	 * @param string      $option     The option name.
	 * @param mixed       $value      A valid value.
	 * @param string      $output     The expected output value as JSON.
	 * @param string      $raw        The expected raw stored value.
	 * @param string|null $after_null The output value after a null value as JSON, or null when it is left out.
	 */
	public function test_null_deletes_each_setting( string $name, string $option, $value, string $output, string $raw, ?string $after_null ): void {
		update_option( $option, $value );
		$this->become_admin_for( $name );
		$expected = $this->snapshot();
		unset( $expected[ $option ] );

		$result = $this->update( array( $name => null ) );

		$this->assertNotWPError( $result, 'The stored value should be deleted.' );
		$this->assertSame( null === $after_null ? '[]' : '{"' . $name . '":' . $after_null . '}', wp_json_encode( $result ), 'The setting should read as its default, or be left out.' );
		$this->assert_snapshot( $expected, $this->snapshot(), 'Only the row of the named setting should be deleted.' );
	}

	/**
	 * Data provider.
	 *
	 * Every exposed core setting, plus the settings this class registers.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed, 3: string, 4: string, 5: string|null}> Settings with a valid value.
	 */
	public static function data_settings(): array {
		$data = array(
			'title'                          => array( 'title', 'blogname', 'Acme Robotics', '"Acme Robotics"', 'Acme Robotics', null ),
			'description'                    => array( 'description', 'blogdescription', 'Robots for everyone', '"Robots for everyone"', 'Robots for everyone', null ),
			'url'                            => array( 'url', 'siteurl', 'https://acme.example', '"https:\/\/acme.example"', 'https://acme.example', null ),
			'email'                          => array( 'email', 'admin_email', 'owner@acme.example', '"owner@acme.example"', 'owner@acme.example', null ),
			'timezone'                       => array( 'timezone', 'timezone_string', 'Europe/Lisbon', '"Europe\/Lisbon"', 'Europe/Lisbon', null ),
			'date_format'                    => array( 'date_format', 'date_format', 'Y-m-d', '"Y-m-d"', 'Y-m-d', null ),
			'time_format'                    => array( 'time_format', 'time_format', 'H:i', '"H:i"', 'H:i', null ),
			'start_of_week'                  => array( 'start_of_week', 'start_of_week', 3, '3', '3', null ),
			'language'                       => array( 'language', 'WPLANG', 'de_DE', '"de_DE"', 'de_DE', '"en_US"' ),
			'use_smilies'                    => array( 'use_smilies', 'use_smilies', false, 'false', '', 'true' ),
			'default_category'               => array( 'default_category', 'default_category', 7, '7', '7', null ),
			'default_post_format'            => array( 'default_post_format', 'default_post_format', 'aside', '"aside"', 'aside', null ),
			'posts_per_page'                 => array( 'posts_per_page', 'posts_per_page', 12, '12', '12', '10' ),
			'show_on_front'                  => array( 'show_on_front', 'show_on_front', 'page', '"page"', 'page', null ),
			'page_on_front'                  => array( 'page_on_front', 'page_on_front', 5, '5', '5', null ),
			'page_for_posts'                 => array( 'page_for_posts', 'page_for_posts', 6, '6', '6', null ),
			'page_for_privacy_policy'        => array( 'page_for_privacy_policy', 'wp_page_for_privacy_policy', 8, '8', '8', null ),
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

		if ( is_multisite() ) {
			unset( $data['url'], $data['email'] );
		}

		return $data;
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
	 * @param string      $name   The exposed setting name.
	 * @param string      $option The option name.
	 * @param mixed       $stored The raw value stored before the call.
	 * @param mixed       $value  The value to write.
	 * @param string      $output The expected output value as JSON.
	 * @param string      $raw    The expected raw stored value.
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
		$this->assert_snapshot( $expected, $this->snapshot(), 'Only the option of the named setting should hold the saved value.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed, 3: mixed, 4: string, 5: string}> Values that are converted or saved differently.
	 */
	public static function data_saved_values(): array {
		return array(
			'a numeric string for an integer'     => array( 'posts_per_page', 'posts_per_page', '10', '12', '12', '12' ),
			'a whole float for an integer'        => array( 'posts_per_page', 'posts_per_page', '10', 12.0, '12', '12' ),
			'"true" for a boolean'                => array( 'use_smilies', 'use_smilies', '', 'true', 'true', '1' ),
			'"false" for a boolean'               => array( 'use_smilies', 'use_smilies', '1', 'false', 'false', '' ),
			'1 for a boolean'                     => array( 'use_smilies', 'use_smilies', '', 1, 'true', '1' ),
			'0 for a boolean'                     => array( 'use_smilies', 'use_smilies', '1', 0, 'false', '' ),
			'"1" for a boolean'                   => array( 'use_smilies', 'use_smilies', '', '1', 'true', '1' ),
			'"0" for a boolean'                   => array( 'use_smilies', 'use_smilies', '1', '0', 'false', '' ),
			'an empty string for a string'        => array( 'title', 'blogname', 'Old title', '', '""', '' ),
			'a script URI for a URI'              => array( 'settings_update_test_uri', 'settings_update_test_uri', 'https://old.example', 'javascript:alert(1)', '""', '' ),
			'an ampersand in the title'           => array( 'title', 'blogname', 'Old title', 'A & B', '"A &amp; B"', 'A &amp; B' ),
			'tags in the title'                   => array( 'title', 'blogname', 'Old title', '<b>x</b>', '"&lt;b&gt;x&lt;\/b&gt;"', '&lt;b&gt;x&lt;/b&gt;' ),
			'zero posts per page'                 => array( 'posts_per_page', 'posts_per_page', '10', 0, '1', '1' ),
			'a negative number of posts per page' => array( 'posts_per_page', 'posts_per_page', '10', -5, '5', '5' ),
			'all posts per page'                  => array( 'posts_per_page', 'posts_per_page', '10', -1, '-1', '-1' ),
			'a negative start of the week'        => array( 'start_of_week', 'start_of_week', '1', -3, '3', '3' ),
			'tags in the date format'             => array( 'date_format', 'date_format', 'F j, Y', '<b>Y</b>', '"Y"', 'Y' ),
			'an unknown timezone'                 => array( 'timezone', 'timezone_string', 'Europe/Lisbon', 'Mars/Olympus', '"Europe\/Lisbon"', 'Europe/Lisbon' ),
			'a language that is not installed'    => array( 'language', 'WPLANG', 'de_DE', 'xx_XX', '"de_DE"', 'de_DE' ),
			'English as a locale'                 => array( 'language', 'WPLANG', 'de_DE', 'en_US', '"de_DE"', 'de_DE' ),
			'English as an empty language'        => array( 'language', 'WPLANG', 'de_DE', '', '""', '' ),
		);
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
	 * A call that names several settings writes and deletes exactly those, and returns only those.
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
		$this->assertTrue( $result['use_smilies'], 'The deleted setting should read as its default.' );

		$expected['blogname']       = 'Acme Robotics';
		$expected['posts_per_page'] = '12';
		unset( $expected['use_smilies'] );
		$this->assert_snapshot( $expected, $this->snapshot(), 'Only the named settings should change.' );
	}

	/**
	 * A `pre_option_{$option}` filter shows in the output, while the value is still written.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_pre_option_filter_shows_in_output(): void {
		$this->become_admin();
		add_filter(
			'pre_option_blogname',
			static function () {
				return 'Filtered title';
			}
		);
		$expected = $this->snapshot();

		$result = $this->update( array( 'title' => 'Written title' ) );

		$this->assertSame( array( 'title' => 'Filtered title' ), $result, 'The output should show the filtered value.' );
		$expected['blogname'] = 'Written title';
		$this->assert_snapshot( $expected, $this->snapshot(), 'The value should still be written.' );
	}

	/**
	 * A `pre_option_{$option}` filter counts as the stored value when a null value is checked.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_pre_option_filter_counts_for_the_stored_value_check(): void {
		$this->store_raw( 'posts_per_page', '12' );
		$this->become_admin();
		add_filter(
			'pre_option_posts_per_page',
			static function () {
				return 'ten';
			}
		);
		$before = $this->snapshot();

		$result = $this->update( array( 'posts_per_page' => null ) );

		$this->assert_error( $result, 'settings_invalid_stored_value', 500, sprintf( self::INVALID_STORED_VALUE_MESSAGE, 'posts_per_page' ) );
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be deleted.' );
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

		register_setting(
			'general',
			$option,
			array(
				'type'              => 'string',
				'show_in_abilities' => true,
				'sanitize_callback' => array( self::class, 'sanitize_test_value' ),
			)
		);
		$this->register_ability();
		$this->store_raw( $option, 'seed' );
		$this->become_admin();
		self::$sanitize_calls = 0;

		$result = $this->update( array( $option => 'abc' ) );
		$after  = $this->snapshot();

		$this->assertSame( array( $option => 'ABC' ), $result, 'The output should show the sanitized value.' );
		$this->assertSame( 1, self::$sanitize_calls, 'The sanitize callback should run once.' );
		$this->assertSame( 'ABC', $after[ $option ], 'The sanitized value should be stored.' );
	}

	/**
	 * A null value is refused when the stored value does not match the schema of the setting,
	 * and nothing is deleted.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_invalid_stored_values
	 *
	 * @param string $name   The exposed setting name.
	 * @param string $option The option name.
	 * @param mixed  $stored The raw value stored before the call, or null for no row.
	 */
	public function test_null_refuses_an_invalid_stored_value( string $name, string $option, $stored ): void {
		$this->store_raw( $option, $stored );
		$this->become_admin();
		$before = $this->snapshot();

		$result = $this->update( array( $name => null ) );

		$this->assert_error( $result, 'settings_invalid_stored_value', 500, sprintf( self::INVALID_STORED_VALUE_MESSAGE, $name ) );
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be deleted.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed}> Stored values that a null value cannot delete.
	 */
	public static function data_invalid_stored_values(): array {
		return array(
			'a word for an integer'                  => array( 'posts_per_page', 'posts_per_page', 'ten' ),
			'an empty string for an integer'         => array( 'posts_per_page', 'posts_per_page', '' ),
			'no value for an integer with a default' => array( 'posts_per_page', 'posts_per_page', null ),
			'another word for an enum'               => array( 'default_ping_status', 'default_ping_status', 'maybe' ),
			'an empty string for an enum'            => array( 'default_ping_status', 'default_ping_status', '' ),
			'an empty string for an email address'   => array( 'settings_update_test_email', 'settings_update_test_email_option', '' ),
			'an array for a string'                  => array( 'title', 'blogname', array( 'A sneaky array!' ) ),
		);
	}

	/**
	 * A null value deletes a stored value that matches the schema of the setting, including the
	 * forms in which a boolean is stored.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_valid_stored_values
	 *
	 * @param string      $name   The exposed setting name.
	 * @param string      $option The option name.
	 * @param mixed       $stored The raw value stored before the call, or null for no row.
	 * @param string      $output The expected output as JSON.
	 */
	public function test_null_accepts_a_valid_stored_value( string $name, string $option, $stored, string $output ): void {
		$this->store_raw( $option, $stored );
		$this->become_admin();
		$expected = $this->snapshot();
		unset( $expected[ $option ] );

		$result = $this->update( array( $name => null ) );

		$this->assertNotWPError( $result, 'The stored value should be deleted.' );
		$this->assertSame( $output, wp_json_encode( $result ), 'The setting should read as its default, or be left out.' );
		$this->assert_snapshot( $expected, $this->snapshot(), 'Only the row of the named setting should be deleted.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed, 3: string}> Stored values that a null value deletes, and the output as JSON.
	 */
	public static function data_valid_stored_values(): array {
		return array(
			'a numeric string for an integer' => array( 'posts_per_page', 'posts_per_page', '12', '{"posts_per_page":10}' ),
			'an empty string for a string'    => array( 'title', 'blogname', '', '[]' ),
			'an empty string for a boolean'   => array( 'use_smilies', 'use_smilies', '', '{"use_smilies":true}' ),
			'"0" for a boolean'               => array( 'use_smilies', 'use_smilies', '0', '{"use_smilies":true}' ),
			'"1" for a boolean'               => array( 'use_smilies', 'use_smilies', '1', '{"use_smilies":true}' ),
			'no value for a boolean'          => array( 'use_smilies', 'use_smilies', null, '{"use_smilies":true}' ),
		);
	}

	/**
	 * Repeating a null value fails for an integer setting, which has no stored value left.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_repeated_null_fails_for_an_integer(): void {
		$this->store_raw( 'posts_per_page', '12' );
		$this->become_admin();

		$first  = $this->update( array( 'posts_per_page' => null ) );
		$second = $this->update( array( 'posts_per_page' => null ) );

		$this->assertSame( array( 'posts_per_page' => 10 ), $first, 'The first call should delete the stored value.' );
		$this->assert_error( $second, 'settings_invalid_stored_value', 500, sprintf( self::INVALID_STORED_VALUE_MESSAGE, 'posts_per_page' ) );
	}

	/**
	 * The stored-value error names the first failing setting in registration order, whatever the
	 * order of the input.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_stored_value_error_names_the_first_in_registration_order(): void {
		$this->store_raw( 'start_of_week', 'ten' );
		$this->store_raw( 'posts_per_page', 'ten' );
		$this->become_admin();

		$result = $this->update(
			array(
				'posts_per_page' => null,
				'start_of_week'  => null,
			)
		);

		$this->assert_error( $result, 'settings_invalid_stored_value', 500, sprintf( self::INVALID_STORED_VALUE_MESSAGE, 'start_of_week' ) );
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
		$this->store_raw( 'use_smilies', '1' );
		$this->store_raw( 'posts_per_page', 'ten' );
		$this->become_admin();
		$before = $this->snapshot();

		$result = $this->update( $data );

		$this->assert_error_code( $result, 'settings_invalid_stored_value' );
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be written or deleted.' );
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
					'title'          => 'Changed title',
					'posts_per_page' => null,
				),
			),
			'a deletion first' => array(
				array(
					'use_smilies'    => null,
					'posts_per_page' => null,
				),
			),
		);
	}

	/**
	 * The capability check comes before the privacy policy page check.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_capability_check_comes_before_privacy_check(): void {
		wp_set_current_user( self::$user_ids['subscriber'] );
		add_filter( 'wp_ability_permission_result', '__return_true' );

		$result = $this->update( array( 'page_for_privacy_policy' => 5 ) );

		$this->assert_error( $result, 'settings_cannot_update', 403, self::CANNOT_UPDATE_MESSAGE );
	}

	/**
	 * The privacy policy page check comes before the stored-value check.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 */
	public function test_privacy_check_comes_before_stored_value_check(): void {
		$this->store_raw( 'posts_per_page', 'ten' );
		$this->become_admin_for( 'page_for_privacy_policy' );
		$this->deny_privacy_capability();

		$result = $this->update(
			array(
				'posts_per_page'          => null,
				'page_for_privacy_policy' => 5,
			)
		);

		$this->assert_error( $result, 'settings_cannot_manage_privacy_options', 403, self::CANNOT_MANAGE_PRIVACY_MESSAGE );
	}

	/**
	 * Invalid input is refused before the permission check, and nothing is written.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_roles_without_manage_options
	 *
	 * @param string|null $role The role of the user, or null when logged out.
	 */
	public function test_invalid_input_is_refused_before_the_permission_check( ?string $role ): void {
		wp_set_current_user( null === $role ? 0 : self::$user_ids[ $role ] );
		$before = $this->snapshot();

		$result = $this->update( array( 'posts_per_page' => 'abc' ) );

		$this->assert_error_code( $result, 'ability_invalid_input' );
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be written.' );
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
		$this->assert_snapshot( $before, $this->snapshot(), 'Nothing should be written.' );
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
				'page_for_privacy_policy' => 8,
			)
		);

		$this->assertNotWPError( $result, 'The settings should be written.' );
		$this->assertSameSets( array( 'title', 'page_for_privacy_policy' ), array_keys( $result ), 'The output should hold only the settings named in the call.' );
		$this->assertSame( 'Network title', $result['title'], 'The output should show the saved title.' );
		$this->assertSame( 8, $result['page_for_privacy_policy'], 'The output should show the saved privacy policy page.' );
		$expected['blogname']                   = 'Network title';
		$expected['wp_page_for_privacy_policy'] = '8';
		$this->assert_snapshot( $expected, $this->snapshot(), 'Both settings should be saved.' );
	}

	/**
	 * A setting that is unregistered after the abilities register is neither written nor deleted,
	 * and is left out of the output, while the other settings of the call are written.
	 *
	 * @ticket 64616
	 *
	 * @since 7.3.0
	 *
	 * @dataProvider data_unregistered_setting_calls
	 *
	 * @param array<string, mixed>  $data    The settings to update.
	 * @param mixed                 $stored  The raw value stored for the unregistered setting.
	 * @param string                $output  The expected output as JSON.
	 * @param array<string, string> $written The raw values expected to be written, keyed by option name.
	 */
	public function test_unregistered_setting_is_dropped( array $data, $stored, string $output, array $written ): void {
		$option = 'settings_update_test_unregistered';

		register_setting(
			'general',
			$option,
			array(
				'type'              => 'string',
				'show_in_abilities' => true,
			)
		);
		$this->register_ability();
		$this->store_raw( $option, $stored );
		unregister_setting( 'general', $option );
		$this->become_admin();
		$expected = $this->snapshot();
		$row      = $this->raw_row( $option );

		$result    = $this->update( $data );
		$after     = $this->snapshot();
		$after_row = $this->raw_row( $option );

		$this->assertNotWPError( $result, 'The call should succeed.' );
		$this->assertSame( $output, wp_json_encode( $result ), 'The output should hold only the settings that are still registered.' );
		$this->assertSame( $row, $after_row, 'The row of the unregistered setting should not change.' );
		$this->assert_snapshot( array_merge( $expected, $written ), $after, 'Only the settings that are still registered should be written.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: mixed, 2: string, 3: array<string, string>}> Calls naming an unregistered setting.
	 */
	public static function data_unregistered_setting_calls(): array {
		return array(
			'a value'                                     => array(
				array( 'settings_update_test_unregistered' => 'abc' ),
				'seed',
				'[]',
				array(),
			),
			'null with a stored value its schema rejects' => array(
				array( 'settings_update_test_unregistered' => null ),
				array( 'not', 'a', 'string' ),
				'[]',
				array(),
			),
			'a value with another setting'                => array(
				array(
					'settings_update_test_unregistered' => 'abc',
					'title'                             => 'Acme Robotics',
				),
				'seed',
				'{"title":"Acme Robotics"}',
				array( 'blogname' => 'Acme Robotics' ),
			),
		);
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
		$this->assert_snapshot( $expected, $this->snapshot(), 'Both settings should still be written.' );
	}
}
