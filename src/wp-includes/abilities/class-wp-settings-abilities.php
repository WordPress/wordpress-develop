<?php
/**
 * Abilities API: WP_Settings_Abilities class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.1.0
 */

declare( strict_types = 1 );

/**
 * Core class used to register settings-related abilities.
 *
 * Provides the read-only `core/read-settings` ability and the shared building blocks
 * (exposed-settings discovery, schema generation, and value casting) that are intended to
 * also back a future write-oriented `core/manage-settings` ability.
 *
 * Unlike the other core abilities, which are self-contained closures registered directly
 * in wp_register_core_abilities(), the settings abilities live in a dedicated class
 * because they share state: the set of exposed settings is computed once at registration
 * and reused by the input schema, the output schema, and the execute callback, and the
 * same helpers are meant to be shared with the future write ability.
 *
 * The exposed settings are captured when the ability registers on `wp_abilities_api_init`.
 * That hook fires lazily on first use of the abilities registry, which is not ordered
 * relative to `rest_api_init` (where core registers its own settings) and can happen
 * without it entirely, e.g. on cron or WP-CLI. register() therefore ensures core's
 * initial settings are registered before the snapshot is computed. Plugin settings
 * flagged with `show_in_abilities` must be registered before the abilities registry is
 * first used in a request; registering them on `init` is reliable.
 *
 * This class is part of WordPress' internal implementation of the core abilities and is
 * not part of the public API. It may be changed or removed at any time without notice.
 * Do not use it directly or rely on its existence.
 *
 * @since 7.1.0
 *
 * @access private
 */
final class WP_Settings_Abilities {

	/**
	 * The ability category used for settings abilities.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	private const CATEGORY = 'site';

	/**
	 * Settings exposed through the Abilities API, computed once at registration.
	 *
	 * Cached so the input/output schema and the executed result derive from the exact same
	 * structure, and {@see get_registered_settings()} is only walked once per request.
	 *
	 * @since 7.1.0
	 * @var array<string, array{option: string, group: string, default: mixed, schema: array<string, mixed>}>|null
	 */
	private $exposed_settings = null;

	/**
	 * Registers all settings abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.1.0
	 */
	public function register(): void {
		/*
		 * Core's initial settings register on `rest_api_init`, which fires lazily and
		 * independently of `wp_abilities_api_init`: on cron, WP-CLI, or any request where
		 * abilities are used before the REST server loads, it may not have fired — or may
		 * be mid-fire at a priority before register_initial_settings() runs. Ensure the
		 * core settings exist before the exposed-settings snapshot below is computed;
		 * re-registering them again later on `rest_api_init` is harmless.
		 */
		if ( ! did_action( 'rest_api_init' ) || doing_action( 'rest_api_init' ) ) {
			register_initial_settings();
		}

		$this->register_get_settings();

		/*
		 * A future write-oriented ability can be registered here, reusing the shared
		 * helpers below (get_exposed_settings(), value_schema(), cast_value()):
		 *
		 *     $this->register_manage_settings();
		 */
	}

	/**
	 * Registers the read-only `core/read-settings` ability.
	 *
	 * @since 7.1.0
	 */
	private function register_get_settings(): void {
		// Compute once; execute_get_settings() reuses this exact structure.
		$this->exposed_settings = $this->get_exposed_settings();

		$settings    = $this->exposed_settings;
		$field_names = array_keys( $settings );
		$groups      = array();
		$properties  = array();
		foreach ( $settings as $exposed_name => $setting ) {
			$properties[ $exposed_name ] = $setting['schema'];
			if ( '' === $setting['group'] || in_array( $setting['group'], $groups, true ) ) {
				continue;
			}
			$groups[] = $setting['group'];
		}

		wp_register_ability(
			'core/read-settings',
			array(
				'label'               => __( 'Read Settings' ),
				'description'         => __( 'Returns WordPress settings as a flat map of setting name to value. By default returns all settings exposed to abilities, or optionally a subset filtered by settings group, by setting name, or both.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_settings_input_schema( $groups, $field_names ),
				'output_schema'       => array(
					'type'                 => 'object',
					'description'          => __( 'A map of setting name to its current value.' ),
					'properties'           => $properties,
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'execute_get_settings' ),
				'permission_callback' => array( $this, 'has_permission' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the `core/read-settings` ability.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed> Map of exposed setting name to current value.
	 */
	public function execute_get_settings( $input = array() ): array {
		$input = is_array( $input ) ? $input : array();

		$settings = $this->exposed_settings;
		if ( null === $settings ) {
			// The cache is populated in register_get_settings() before the ability is
			// registered, so this is unreachable in practice; bail defensively otherwise.
			return array();
		}

		$group  = isset( $input['group'] ) && is_string( $input['group'] ) ? $input['group'] : '';
		$fields = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();

		$result = array();
		foreach ( $settings as $exposed_name => $setting ) {
			if ( '' !== $group && $setting['group'] !== $group ) {
				continue;
			}
			if ( ! empty( $fields ) && ! in_array( $exposed_name, $fields, true ) ) {
				continue;
			}

			$type  = isset( $setting['schema']['type'] ) && is_string( $setting['schema']['type'] ) ? $setting['schema']['type'] : 'string';
			$value = get_option( $setting['option'], $setting['default'] );

			$result[ $exposed_name ] = $this->cast_value( $value, $type );
		}

		return $result;
	}

	/**
	 * Checks whether the current user may use the settings abilities.
	 *
	 * @since 7.1.0
	 *
	 * @return bool True if the current user can manage options.
	 */
	public function has_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Builds the input schema for the get ability: optional filters by group and/or name.
	 *
	 * Both `group` and `fields` are optional; supplying both narrows the response to their
	 * intersection, and supplying neither returns every exposed setting.
	 *
	 * @since 7.1.0
	 *
	 * @param list<string> $groups      Available settings groups.
	 * @param list<string> $field_names Available exposed setting names.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_settings_input_schema( array $groups, array $field_names ): array {
		return array(
			'type'                 => 'object',
			// Object (not array()) so the serialized schema default is {}, consistent with type:object.
			'default'              => (object) array(),
			'properties'           => array(
				'group'  => array(
					'type'        => 'string',
					'enum'        => $groups,
					'description' => __( 'Return only settings that belong to this settings group.' ),
				),
				'fields' => array(
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
						'enum' => $field_names,
					),
					'description' => __( 'Return only the settings with these names.' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Returns the settings exposed through the Abilities API.
	 *
	 * Reads {@see get_registered_settings()} and keeps only settings flagged with a truthy
	 * `show_in_abilities` argument. Each entry is keyed by its exposed name and carries the
	 * underlying option name, the settings group, the registration default, and a JSON Schema
	 * describing the value.
	 *
	 * @since 7.1.0
	 *
	 * @return array<string, array{option: string, group: string, default: mixed, schema: array<string, mixed>}> Settings keyed by exposed name.
	 */
	private function get_exposed_settings(): array {
		$settings = array();

		foreach ( get_registered_settings() as $option_name => $args ) {
			$show = $args['show_in_abilities'] ?? false;
			if ( empty( $show ) ) {
				continue;
			}

			$option_name  = (string) $option_name;
			$exposed_name = is_array( $show ) && isset( $show['name'] ) && is_string( $show['name'] ) && '' !== $show['name'] ? $show['name'] : $option_name;

			$settings[ $exposed_name ] = array(
				'option'  => $option_name,
				'group'   => isset( $args['group'] ) && is_string( $args['group'] ) ? $args['group'] : '',
				'default' => array_key_exists( 'default', $args ) ? $args['default'] : false,
				'schema'  => $this->value_schema( $args, $show ),
			);
		}

		return $settings;
	}

	/**
	 * Builds the JSON Schema describing a single setting's value.
	 *
	 * @since 7.1.0
	 *
	 * @param array<string, mixed>      $args The setting registration arguments.
	 * @param bool|array<string, mixed> $show The setting's `show_in_abilities` value.
	 * @return array<string, mixed> The value JSON Schema.
	 */
	private function value_schema( array $args, $show ): array {
		$schema = array(
			'type' => isset( $args['type'] ) && is_string( $args['type'] ) ? $args['type'] : 'string',
		);
		if ( ! empty( $args['label'] ) ) {
			$schema['title'] = $args['label'];
		}
		if ( ! empty( $args['description'] ) ) {
			$schema['description'] = $args['description'];
		}
		if ( is_array( $show ) && isset( $show['schema'] ) && is_array( $show['schema'] ) ) {
			/** @var array<string, mixed> $show_schema */
			$show_schema = $show['schema'];
			$schema      = array_merge( $schema, $show_schema );
		}

		return $schema;
	}

	/**
	 * Casts a stored option value to the type declared in its settings registration.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed  $value The raw option value.
	 * @param string $type  The registered setting type.
	 * @return mixed The value cast to the declared type.
	 */
	private function cast_value( $value, string $type ) {
		switch ( $type ) {
			case 'boolean':
				return (bool) $value;
			case 'integer':
				return is_scalar( $value ) ? (int) $value : 0;
			case 'number':
				return is_scalar( $value ) ? (float) $value : 0.0;
			case 'array':
				return is_array( $value ) ? $value : array();
			case 'object':
				// Cast to object so an empty/non-array value serializes as {} (not []) and
				// satisfies the `object` output schema validated by execute().
				return (object) ( is_array( $value ) ? $value : array() );
			default:
				return is_scalar( $value ) ? (string) $value : $value;
		}
	}
}
