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
	 * Options that `core/settings-update` cannot write, whatever name exposes them.
	 *
	 * The site address and the administration email address are left to Settings > General, which
	 * confirms a new administration email address before it is used.
	 *
	 * @since 7.3.0
	 * @var string[]
	 */
	private const READ_ONLY_OPTIONS = array( 'siteurl', 'admin_email' );

	/**
	 * Settings exposed through the Abilities API, computed once at registration.
	 *
	 * @since 7.2.0
	 * @var array<string, array{option: string, group: string, default: mixed, schema: array<string, mixed>}>
	 */
	private $exposed_settings = array();

	/**
	 * Exposed settings that `core/settings-update` can write, computed once at registration.
	 *
	 * @since 7.3.0
	 * @var array<string, array{option: string, group: string, default: mixed, schema: array<string, mixed>}>
	 */
	private $writable_settings = array();

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
		 * before `core/settings-update` existed, except the options in READ_ONLY_OPTIONS. A
		 * setting that must stay read-only sets `readonly` to true in its schema.
		 *
		 * Intentionally strict: a setting whose schema is read-only cannot be updated, so naming
		 * it is refused like any unknown name. Only `readonly` at the top level of the schema
		 * counts: a read-only property inside an object setting is written with the rest of
		 * the object.
		 */
		$this->writable_settings = array_filter(
			$this->exposed_settings,
			static fn( array $setting ): bool => empty( $setting['schema']['readonly'] ) && ! in_array( $setting['option'], self::READ_ONLY_OPTIONS, true )
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
				'output_schema'         => $this->get_settings_output_schema( $this->exposed_settings, __( 'A map of setting name to its current value.' ) ),
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
	 * setting without a default, see execute_settings_update(). It is not marked idempotent,
	 * although repeating a call has no further effect: an ability that is both destructive and
	 * idempotent runs with the DELETE method, and this ability updates settings.
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
				'description'           => __( 'Updates WordPress settings and returns the saved values of the settings named in the call. A null value resets a setting to its default, or deletes its stored value when it has no default. Read-only settings, such as the site address and the administration email address, cannot be updated. Requires an authenticated user who can manage options, and who can also manage privacy options to set the privacy policy page.' ),
				'category'              => self::CATEGORY,
				'input_schema'          => $this->get_settings_update_input_schema(),
				'output_schema'         => $this->get_settings_output_schema( $this->writable_settings, __( 'A map of setting name to its value after the update.' ) ),
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
	 * Executes the `core/settings-update` ability.
	 *
	 * Every check runs before the first write, so a call that ends in an error changes nothing.
	 * The output holds only the settings named in the call, read the same way as for
	 * `core/settings-get`.
	 *
	 * @since 7.3.0
	 *
	 * @param array{data: array<int|string, mixed>} $input The ability input, as prepared by WP_Ability::execute().
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
		 * Intentionally strict: every value is checked before the first write, so a refused call
		 * changes nothing. A value is refused when the setting cannot use it, instead of being
		 * saved as sent or silently replaced with the stored value. A null value for a setting
		 * without a default is refused while its stored value does not match the setting's schema:
		 * `core/settings-get` leaves that value out, so the caller cannot see what would be deleted.
		 * Unusable values are reported first.
		 *
		 * Intentional: a null value stores the default of a setting that has one, and deletes the
		 * stored value of a setting without one. A registered default only applies in requests that
		 * register the setting, and Core registers its own settings only in REST and abilities
		 * requests, so other requests would read a deleted value as false. A setting without a
		 * default then has no value, and is left out of the output and of `core/settings-get`,
		 * until a value is written again.
		 */
		$invalid_params = array();
		$invalid_stored = null;
		foreach ( $named as $exposed_name => $setting ) {
			$value = $data[ $exposed_name ] ?? $setting['default'];

			/*
			 * English is stored as an empty string. `en_US` is what the language reads as when
			 * none is stored, and sanitize_option() would replace it with the stored value.
			 */
			if ( 'WPLANG' === $setting['option'] && 'en_US' === $value ) {
				$value = '';
			}

			if ( null !== $data[ $exposed_name ] ) {
				$message = $this->get_invalid_value_message( $setting['option'], $value );
				if ( null !== $message ) {
					$invalid_params[ "data[{$exposed_name}]" ] = $message;
				}
			} elseif ( null === $value && null === $invalid_stored ) {
				// The stored value reads as null when there is none, and then there is nothing to delete.
				$stored = get_option( $setting['option'], null );
				if ( null !== $stored && is_wp_error( rest_validate_value_from_schema( $stored, $setting['schema'] ) ) ) {
					$invalid_stored = $exposed_name;
				}
			}

			$data[ $exposed_name ] = $value;
		}

		if ( ! empty( $invalid_params ) ) {
			if ( 1 === count( $invalid_params ) ) {
				$message = reset( $invalid_params );
			} else {
				/* translators: %s: List of invalid parameters. */
				$message = sprintf( __( 'Invalid parameter(s): %s' ), implode( ', ', array_keys( $invalid_params ) ) );
			}

			// The error data maps the input path of each refused setting to its message, so callers can tell which settings failed.
			return new WP_Error(
				'settings_invalid_value',
				$message,
				array(
					'status' => 400,
					'params' => $invalid_params,
				)
			);
		}

		if ( null !== $invalid_stored ) {
			return new WP_Error(
				'settings_invalid_stored_value',
				/* translators: %s: Property name. */
				sprintf( __( 'The %s property has an invalid stored value, and cannot be updated to null.' ), $invalid_stored ),
				array( 'status' => 500 )
			);
		}

		foreach ( $named as $exposed_name => $setting ) {
			$value = $data[ $exposed_name ];
			if ( null === $value ) {
				delete_option( $setting['option'] );
				continue;
			}

			update_option( $setting['option'], $value );

			// update_option() stores nothing when no value is stored and the value matches the default get_option() reads.
			if ( false === get_option( $setting['option'], false ) && get_option( $setting['option'] ) === $value ) {
				add_option( $setting['option'], $value );
			}
		}

		return $this->get_setting_values( $named );
	}

	/**
	 * Checks a value that matches its schema against what the option can use.
	 *
	 * The rules belong to the option, whatever name exposes it: a timezone and a language must be
	 * empty or known to the site, a default post format must be `0` or a post format, a page
	 * setting must be 0 or the ID of a page, and the default category must be the ID of a category.
	 *
	 * @since 7.3.0
	 *
	 * @param string $option The option name.
	 * @param mixed  $value  The value to save, which is not null.
	 * @return string|null The error message when the option cannot use the value, null otherwise.
	 */
	private function get_invalid_value_message( string $option, $value ): ?string {
		switch ( $option ) {
			case 'timezone_string':
				if ( '' !== $value && ! in_array( $value, timezone_identifiers_list( DateTimeZone::ALL_WITH_BC ), true ) ) {
					return __( 'The timezone you have entered is not valid. Please select a valid timezone.' );
				}
				break;

			case 'WPLANG':
				if ( '' !== $value && ! in_array( $value, get_available_languages(), true ) ) {
					return __( 'The language is not installed.' );
				}
				break;

			case 'default_post_format':
				if ( '0' !== $value && ! in_array( $value, get_post_format_slugs(), true ) ) {
					return __( 'Invalid post format.' );
				}
				break;

			case 'page_on_front':
			case 'page_for_posts':
			case 'wp_page_for_privacy_policy':
				if ( 0 !== $value && ( ! is_int( $value ) || 'page' !== get_post_type( $value ) ) ) {
					return __( 'Invalid page ID.' );
				}
				break;

			case 'default_category':
				// A negative ID would be looked up as its absolute value.
				if ( ! is_int( $value ) || $value <= 0 || ! term_exists( $value, 'category' ) ) {
					return __( 'Invalid term ID.' );
				}
				break;
		}

		return null;
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
			$value = get_option( $setting['option'], $setting['default'] );
			if ( null === $value ) {
				continue;
			}

			// WordPress stores false as '', which the boolean schema rejects.
			if ( '' === $value && 'boolean' === $setting['schema']['type'] ) {
				$value = false;
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
					'description'          => __( 'Settings to update, keyed by setting name. A null value resets a setting to its default, or deletes its stored value when it has no default.' ),
					'properties'           => array_map( array( $this, 'nullable_schema' ), wp_list_pluck( $this->writable_settings, 'schema' ) ),
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
	 * Adds `null` to the type, and to the allowed values when the schema lists them. Removes
	 * the default, which is for reading a setting: a client that fills in defaults would
	 * otherwise write it on every call. Nested schemas are not changed.
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
		unset( $schema['default'] );

		return $schema;
	}

	/**
	 * Builds the output schema of a settings ability, from the settings it can return.
	 *
	 * No setting is marked required because both abilities return a subset: `core/settings-get`
	 * the settings its `group` and `fields` inputs ask for, and `core/settings-update` the settings
	 * named in the call. A setting without a value, or whose value does not match its schema, is
	 * also left out.
	 *
	 * @since 7.2.0
	 * @since 7.3.0 Renamed from get_settings_get_output_schema(). Added the `$settings` and
	 *              `$description` parameters.
	 *
	 * @param array<string, array{option: string, group: string, default: mixed, schema: array<string, mixed>}> $settings    Exposed settings the ability can return, keyed by exposed name.
	 * @param string                                                                                              $description The description of the output.
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_settings_output_schema( array $settings, string $description ): array {
		return array(
			'type'                 => 'object',
			'description'          => $description,
			'properties'           => wp_list_pluck( $settings, 'schema' ),
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
