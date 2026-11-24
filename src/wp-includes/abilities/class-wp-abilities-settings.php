<?php
/**
 * Abilities API: WP_Abilities_Settings class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.2.0
 */

declare( strict_types = 1 );

/**
 * Core class used to register settings-related abilities.
 *
 * Provides the `core/settings-get` and `core/settings-update` abilities and the building
 * blocks they share (exposed-settings discovery, schema generation and reading the values).
 *
 * Unlike the core abilities registered directly in wp_register_core_abilities() as
 * self-contained closures, the settings abilities live in a dedicated class
 * because they share state: the set of exposed settings is computed once at registration
 * and reused by the input schemas, the output schemas, and the execute callbacks of both
 * abilities.
 *
 * The exposed settings are captured when the ability registers, the first time the abilities
 * registry is used in a request. Settings registered later in that request are not exposed.
 * Core registers its own settings in time, see _wp_register_initial_settings_for_abilities().
 *
 * Only register() is public. The ability callbacks are closures that call private
 * methods, so callers go through the Abilities API, such as
 * `wp_get_ability( 'core/settings-get' )->execute()`, which validates the input and
 * checks permissions before running them.
 *
 * @since 7.2.0
 * @since 7.3.0 Added the `core/settings-update` ability.
 *
 * @access private
 */
final class WP_Abilities_Settings {

	/**
	 * The ability category used for settings abilities.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	private const CATEGORY = 'site';

	/**
	 * Settings exposed through the Abilities API, computed once at registration.
	 *
	 * @since 7.2.0
	 * @var array<string, array{option: string, group: string, default: mixed, schema: array<string, mixed>}>
	 */
	private array $exposed_settings = array();

	/**
	 * Exposed settings that `core/settings-update` can write, computed once at registration.
	 *
	 * @since 7.3.0
	 * @var array<string, array{option: string, group: string, default: mixed, schema: array<string, mixed>}>
	 */
	private array $writable_settings = array();

	/**
	 * Registers all settings abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook. Registers nothing when no setting is
	 * exposed to abilities.
	 *
	 * @since 7.2.0
	 * @since 7.3.0 Registers the `core/settings-update` ability.
	 */
	public function register(): void {
		$this->exposed_settings = $this->get_exposed_settings();
		if ( empty( $this->exposed_settings ) ) {
			return;
		}

		/*
		 * Intentional: every exposed setting can be updated, including the settings exposed
		 * before `core/settings-update` existed. A setting that must stay read-only sets
		 * `readonly` to true in its schema.
		 *
		 * Intentionally strict: a setting whose schema is read-only cannot be updated, so naming
		 * it is refused like any unknown name.
		 */
		$this->writable_settings = array_filter(
			$this->exposed_settings,
			static fn( array $setting ): bool => empty( $setting['schema']['readonly'] )
		);

		$this->register_settings_get();
		$this->register_settings_update();
	}

	/**
	 * Registers the read-only `core/settings-get` ability.
	 *
	 * @since 7.2.0
	 */
	private function register_settings_get(): void {
		$groups = array_values( array_unique( array_filter( array_column( $this->exposed_settings, 'group' ) ) ) );

		wp_register_ability(
			'core/settings-get',
			array(
				'label'                 => __( 'Get Settings' ),
				'description'           => __( 'Returns WordPress settings as a flat map of setting name to value. By default returns all settings exposed to abilities, or optionally a subset filtered by settings group, by setting name, or both. A setting whose value does not match its schema is left out. Requires an authenticated user who can manage options.' ),
				'category'              => self::CATEGORY,
				'input_schema'          => $this->get_settings_get_input_schema( $groups, array_map( 'strval', array_keys( $this->exposed_settings ) ) ),
				'output_schema'         => $this->get_settings_output_schema( __( 'A map of setting name to its current value.' ) ),
				'execute_callback'      => function ( $input = array() ): array {
					return $this->execute_settings_get( $input );
				},
				'required_capabilities' => array( 'manage_options' ),
				'meta'                  => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Registers the `core/settings-update` ability.
	 *
	 * Registers nothing when no exposed setting is writable.
	 *
	 * The ability is marked destructive because a null value deletes the stored value of a
	 * setting, see execute_settings_update(). It is not marked idempotent because repeating
	 * a null value can fail: the second call finds no stored value left to delete.
	 *
	 * @since 7.3.0
	 */
	private function register_settings_update(): void {
		if ( empty( $this->writable_settings ) ) {
			return;
		}

		wp_register_ability(
			'core/settings-update',
			array(
				'label'                 => __( 'Update Settings' ),
				'description'           => __( 'Updates WordPress settings and returns the saved values of the settings named in the call. A null value deletes the stored value of a setting, which then reads as its default when it has one. Requires an authenticated user who can manage options.' ),
				'category'              => self::CATEGORY,
				'input_schema'          => $this->get_settings_update_input_schema(),
				'output_schema'         => $this->get_settings_output_schema( __( 'A map of setting name to its value after the update.' ) ),
				'execute_callback'      => function ( $input ) {
					return $this->execute_settings_update( $input );
				},
				'required_capabilities' => array( 'manage_options' ),
				'meta'                  => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Executes the `core/settings-update` ability.
	 *
	 * Every check runs before the first write, so a call that ends in an error changes nothing.
	 * The output holds only the settings named in the call, read the same way as for
	 * `core/settings-get`.
	 *
	 * @since 7.3.0
	 *
	 * @param array{data: array<string, mixed>} $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, mixed>|WP_Error Map of the named setting names to their values after the update,
	 *                                       or an error when the update is refused.
	 */
	private function execute_settings_update( array $input ) {
		// A permission filter can grant the call to a user who cannot manage options, who must still not write.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'settings_cannot_update',
				__( 'Sorry, you are not allowed to manage options for this site.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$data  = $input['data'];
		$named = array_intersect_key( $this->writable_settings, $data );

		// A setting unregistered since the abilities initialized is neither checked, written nor returned.
		$registered = get_registered_settings();
		$named      = array_filter(
			$named,
			static fn( array $setting ): bool => isset( $registered[ $setting['option'] ] )
		);

		/*
		 * Intentionally strict: the whole call is refused, instead of leaving this one setting out
		 * of the update. The rule belongs to the option, whatever name exposes it.
		 */
		if ( in_array( 'wp_page_for_privacy_policy', array_column( $named, 'option' ), true ) && ! current_user_can( 'manage_privacy_options' ) ) {
			return new WP_Error(
				'settings_cannot_manage_privacy_options',
				__( 'Sorry, you are not allowed to manage privacy options on this site.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		/*
		 * Intentionally strict: every null value is checked before the first write, so a refused
		 * call changes nothing. A null value is refused while the stored value does not match the
		 * setting's schema.
		 */
		foreach ( $named as $exposed_name => $setting ) {
			if ( null !== $data[ $exposed_name ] ) {
				continue;
			}

			if ( is_wp_error( rest_validate_value_from_schema( $this->get_stored_value( $setting, false ), $setting['schema'] ) ) ) {
				return new WP_Error(
					'settings_invalid_stored_value',
					/* translators: %s: Property name. */
					sprintf( __( 'The %s property has an invalid stored value, and cannot be updated to null.' ), $exposed_name ),
					array( 'status' => 500 )
				);
			}
		}

		/*
		 * Intentional: a null value deletes the stored value, whether or not the setting has a
		 * default. A setting without a default then has no value, and is left out of the output
		 * and of `core/settings-get`, until a value is written again.
		 */
		foreach ( $named as $exposed_name => $setting ) {
			if ( null === $data[ $exposed_name ] ) {
				delete_option( $setting['option'] );
				continue;
			}

			update_option( $setting['option'], $data[ $exposed_name ] );
		}

		return $this->get_setting_values( $named );
	}

	/**
	 * Executes the `core/settings-get` ability.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed> Map of exposed setting name to current value.
	 */
	private function execute_settings_get( $input = array() ): array {
		$input  = rest_sanitize_object( $input );
		$group  = isset( $input['group'] ) && is_string( $input['group'] ) ? $input['group'] : '';
		$fields = rest_sanitize_array( $input['fields'] ?? array() );

		$requested = array();
		foreach ( $this->exposed_settings as $exposed_name => $setting ) {
			if ( '' !== $group && $setting['group'] !== $group ) {
				continue;
			}
			if ( ! empty( $fields ) && ! in_array( (string) $exposed_name, $fields, true ) ) {
				continue;
			}

			$requested[ $exposed_name ] = $setting;
		}

		return $this->get_setting_values( $requested );
	}

	/**
	 * Reads the current values of the given exposed settings.
	 *
	 * A setting without a value, or whose value does not match its schema, is left out.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, array{option: string, group: string, default: mixed, schema: array<string, mixed>}> $settings Exposed settings, keyed by exposed name.
	 * @return array<string, mixed> Map of exposed setting name to current value.
	 */
	private function get_setting_values( array $settings ): array {
		$result = array();
		foreach ( $settings as $exposed_name => $setting ) {
			/*
			 * As the settings endpoint does, read a setting that was never saved as its registered
			 * default, or leave it out when it has none: get_option() would return false, which a
			 * boolean schema accepts as a value and an array schema reads as an empty list.
			 */
			$value = $this->get_stored_value( $setting, $setting['default'] );
			if ( null === $value ) {
				continue;
			}

			/*
			 * Leave out a value its schema rejects, before sanitizing (which could make it pass) or
			 * after (which could make it fail), instead of failing output validation for every setting.
			 */
			if ( is_wp_error( rest_validate_value_from_schema( $value, $setting['schema'] ) ) ) {
				continue;
			}

			$value = rest_sanitize_value_from_schema( $value, $setting['schema'] );
			if ( is_wp_error( rest_validate_value_from_schema( $value, $setting['schema'] ) ) ) {
				continue;
			}

			// Object (not array()) so an empty object value is serialized as {}, consistent with type:object.
			$result[ $exposed_name ] = 'object' === $setting['schema']['type'] ? (object) $value : $value;
		}

		return $result;
	}

	/**
	 * Reads the stored value of an exposed setting.
	 *
	 * @since 7.3.0
	 *
	 * @param array{option: string, group: string, default: mixed, schema: array<string, mixed>} $setting       The exposed setting.
	 * @param mixed                                                                                $default_value The value to return when the option does not exist.
	 * @return mixed The stored value, or the default value.
	 */
	private function get_stored_value( array $setting, $default_value ) {
		$value = get_option( $setting['option'], $default_value );

		// WordPress stores false as '', which the boolean schema rejects.
		if ( '' === $value && 'boolean' === $setting['schema']['type'] ) {
			return false;
		}

		return $value;
	}

	/**
	 * Builds the input schema for the `core/settings-update` ability.
	 *
	 * Each writable setting accepts a value of its own schema, or null.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_settings_update_input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'data' => array(
					'type'                 => 'object',
					'description'          => __( 'Settings to update, keyed by setting name. A null value deletes the stored value of a setting.' ),
					'properties'           => array_map(
						fn( array $schema ): array => $this->nullable_schema( $schema ),
						wp_list_pluck( $this->writable_settings, 'schema' )
					),
					'additionalProperties' => false,
					'minProperties'        => 1,
				),
			),
			'required'             => array( 'data' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Allows null in a setting's value schema.
	 *
	 * Adds `null` to the type, and to the allowed values when the schema lists them. Nested
	 * schemas are not changed.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $schema The value JSON Schema.
	 * @return array<string, mixed> The value JSON Schema that also accepts null.
	 */
	private function nullable_schema( array $schema ): array {
		$schema['type'] = array( $schema['type'], 'null' );
		if ( isset( $schema['enum'] ) ) {
			$schema['enum'][] = null;
		}

		return $schema;
	}

	/**
	 * Builds the input schema for the `core/settings-get` ability: optional filters by group and/or name.
	 *
	 * Both `group` and `fields` are optional; supplying both narrows the response to their
	 * intersection, and supplying neither returns every exposed setting.
	 *
	 * @since 7.2.0
	 *
	 * @param list<string> $groups      Available settings groups.
	 * @param list<string> $field_names Available exposed setting names.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_settings_get_input_schema( array $groups, array $field_names ): array {
		return array(
			'type'                 => 'object',
			'default'              => array(),
			'properties'           => array(
				'group'  => array(
					'type'        => 'string',
					'enum'        => $groups,
					'description' => __( 'Return only settings that belong to this settings group. If omitted, settings of every group are returned.' ),
				),
				'fields' => array(
					'type'        => 'array',
					'uniqueItems' => true,
					'items'       => array(
						'type' => 'string',
						'enum' => $field_names,
					),
					'description' => __( 'Return only the settings with these names. If omitted or empty, all settings allowed by the group filter are returned. When both filters are given, only settings matching both are returned.' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Builds the output schema shared by the settings abilities.
	 *
	 * No setting is marked required because both abilities return a subset: `core/settings-get`
	 * the settings its `group` and `fields` inputs ask for, and `core/settings-update` the settings
	 * named in the call. A setting without a value, or whose value does not match its schema, is
	 * also left out.
	 *
	 * @since 7.3.0
	 *
	 * @param string $description The description of the output.
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_settings_output_schema( string $description ): array {
		return array(
			'type'                 => 'object',
			'description'          => $description,
			'properties'           => wp_list_pluck( $this->exposed_settings, 'schema' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Returns the settings exposed through the Abilities API.
	 *
	 * Reads {@see get_registered_settings()} and keeps only settings flagged with a truthy
	 * `show_in_abilities` argument, of a type the settings endpoint supports. Each entry is
	 * keyed by its exposed name and carries the underlying option name, the settings group,
	 * the default (the exposure schema's default when it declares one, otherwise the registered
	 * default, or null when there is none), and a JSON Schema describing the value.
	 *
	 * Two settings exposed under the same name trigger a notice, and the later one is exposed.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, array{option: string, group: string, default: mixed, schema: array<string, mixed>}> Settings keyed by exposed name.
	 */
	private function get_exposed_settings(): array {
		$settings = array();

		foreach ( get_registered_settings() as $option_name => $args ) {
			if ( empty( $args['show_in_abilities'] ) ) {
				continue;
			}

			$show = $this->get_exposure_args( $args );

			$schema = $this->value_schema( $args, $show );
			if ( ! in_array( $schema['type'], array( 'number', 'integer', 'string', 'boolean', 'array', 'object' ), true ) ) {
				continue;
			}

			$option_name  = (string) $option_name;
			$exposed_name = empty( $show['name'] ) ? $option_name : (string) $show['name'];

			if ( isset( $settings[ $exposed_name ] ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: 1: Option name, 2: Setting name exposed to abilities, 3: Option name. */
						__( 'The "%1$s" setting is exposed to abilities as "%2$s", a name already used by the "%3$s" setting, which is no longer exposed.' ),
						esc_html( $option_name ),
						esc_html( $exposed_name ),
						esc_html( $settings[ $exposed_name ]['option'] )
					),
					'7.2.0'
				);
			}

			/*
			 * As the settings endpoint does, a default declared in the exposure schema
			 * overrides the registered default.
			 */
			$settings[ $exposed_name ] = array(
				'option'  => $option_name,
				'group'   => $args['group'] ?? '',
				'default' => array_key_exists( 'default', $schema ) ? $schema['default'] : ( $args['default'] ?? null ),
				'schema'  => $schema,
			);
		}

		return $settings;
	}

	/**
	 * Returns the name and schema overrides used to expose a setting to abilities.
	 *
	 * When `show_in_abilities` is `true`, the setting is exposed the same way as in the
	 * REST API: it uses the `name` and `schema` from `show_in_rest`. An array is used
	 * as is.
	 *
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $args The setting registration arguments.
	 * @return array<string, mixed> The exposure arguments, with optional `name` and `schema` keys.
	 */
	private function get_exposure_args( array $args ): array {
		if ( is_array( $args['show_in_abilities'] ) ) {
			return $args['show_in_abilities'];
		}

		return is_array( $args['show_in_rest'] ) ? $args['show_in_rest'] : array();
	}

	/**
	 * Builds the JSON Schema describing a single setting's value.
	 *
	 * As in the settings endpoint, objects in the schema reject properties they do not declare,
	 * unless the schema allows them.
	 *
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $args The setting registration arguments.
	 * @param array<string, mixed> $show The exposure arguments, see get_exposure_args().
	 * @return array<string, mixed> The value JSON Schema.
	 */
	private function value_schema( array $args, $show ): array {
		$schema = array(
			'type' => $args['type'],
		);
		if ( ! empty( $args['label'] ) ) {
			$schema['title'] = $args['label'];
		}
		if ( ! empty( $args['description'] ) ) {
			$schema['description'] = $args['description'];
		}
		if ( isset( $show['schema'] ) && is_array( $show['schema'] ) ) {
			/** @var array<string, mixed> $show_schema */
			$show_schema = $show['schema'];
			$schema      = array_merge( $schema, $show_schema );
		}

		return rest_default_additional_properties_to_false( $schema );
	}
}
