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
 * Provides the read-only `core/settings-get` ability and the shared building blocks
 * (exposed-settings discovery and schema generation) that are intended to also back a
 * future write-oriented `core/settings-update` ability.
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
 * without it entirely, e.g. on cron or WP-CLI. Core therefore also registers its initial
 * settings on that hook, before the core abilities register (see
 * _wp_register_initial_settings_for_abilities()). Plugin settings flagged with
 * `show_in_abilities` must be registered before the abilities registry is first used in
 * a request; registering them on `init` is reliable.
 *
 * This class is part of WordPress' internal implementation of the core abilities and is
 * not part of the public API. It may be changed or removed at any time without notice.
 * Do not use it directly or rely on its existence.
 *
 * @since 7.2.0
 *
 * @access private
 */
final class WP_Abilities_Settings {

	/**
	 * Settings exposed through the Abilities API, computed once at registration.
	 *
	 * Cached so the input/output schema and the executed result derive from the exact same
	 * structure, and {@see get_registered_settings()} is only walked once per request.
	 *
	 * @since 7.2.0
	 * @var array<string, array{option: string, group: string, schema: array<string, mixed>}>
	 */
	private $exposed_settings = array();

	/**
	 * Registers all settings abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook. Registers nothing when no setting is
	 * exposed to abilities.
	 *
	 * @since 7.2.0
	 */
	public function register(): void {
		// Compute once; execute_get_settings() reuses this exact structure.
		$this->exposed_settings = $this->get_exposed_settings();
		if ( empty( $this->exposed_settings ) ) {
			return;
		}

		$this->register_get_settings();
	}

	/**
	 * Registers the read-only `core/settings-get` ability.
	 *
	 * @since 7.2.0
	 */
	private function register_get_settings(): void {
		$groups = array_values( array_unique( array_filter( array_column( $this->exposed_settings, 'group' ) ) ) );

		wp_register_ability(
			'core/settings-get',
			array(
				'label'               => __( 'Settings Get' ),
				'description'         => __( 'Returns WordPress settings as a flat map of setting name to value. By default returns all settings exposed to abilities, or optionally a subset filtered by settings group, by setting name, or both. A setting whose value does not match its schema is left out.' ),
				'category'            => 'site',
				'input_schema'        => $this->get_settings_input_schema( $groups, array_keys( $this->exposed_settings ) ),
				'output_schema'       => array(
					'type'                 => 'object',
					'description'          => __( 'A map of setting name to its current value.' ),
					'properties'           => wp_list_pluck( $this->exposed_settings, 'schema' ),
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
	 * Executes the `core/settings-get` ability.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed> Map of exposed setting name to current value.
	 */
	public function execute_get_settings( $input = array() ): array {
		$input  = is_array( $input ) ? $input : array();
		$group  = isset( $input['group'] ) && is_string( $input['group'] ) ? $input['group'] : '';
		$fields = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();

		$result = array();
		foreach ( $this->exposed_settings as $exposed_name => $setting ) {
			if ( '' !== $group && $setting['group'] !== $group ) {
				continue;
			}
			if ( ! empty( $fields ) && ! in_array( $exposed_name, $fields, true ) ) {
				continue;
			}

			$value = get_option( $setting['option'] );

			// WordPress stores false as '', which the boolean schema rejects, while the settings endpoint answers null for it.
			if ( '' === $value && 'boolean' === $setting['schema']['type'] ) {
				$value = false;
			}

			/*
			 * As the settings endpoint does, validate the stored value before sanitizing it, and
			 * leave out a value its schema rejects instead of failing output validation for every
			 * setting; the settings endpoint answers null for it.
			 */
			if ( is_wp_error( rest_validate_value_from_schema( $value, $setting['schema'] ) ) ) {
				continue;
			}

			$value = rest_sanitize_value_from_schema( $value, $setting['schema'] );

			// Object (not array()) so an empty object value is serialized as {}, consistent with type:object.
			$result[ $exposed_name ] = 'object' === $setting['schema']['type'] ? (object) $value : $value;
		}

		return $result;
	}

	/**
	 * Checks whether the current user may use the settings abilities.
	 *
	 * @since 7.2.0
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
	 * @since 7.2.0
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
	 * `show_in_abilities` argument, of a type the settings endpoint supports. Each entry is
	 * keyed by its exposed name and carries the underlying option name, the settings group,
	 * and a JSON Schema describing the value.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, array{option: string, group: string, schema: array<string, mixed>}> Settings keyed by exposed name.
	 */
	private function get_exposed_settings(): array {
		$settings = array();

		foreach ( get_registered_settings() as $option_name => $args ) {
			$show = $args['show_in_abilities'] ?? false;
			if ( empty( $show ) ) {
				continue;
			}

			$schema = $this->value_schema( $args, $show );
			if ( ! in_array( $schema['type'], array( 'number', 'integer', 'string', 'boolean', 'array', 'object' ), true ) ) {
				continue;
			}

			$option_name = (string) $option_name;

			$settings[ empty( $show['name'] ) ? $option_name : $show['name'] ] = array(
				'option' => $option_name,
				'group'  => $args['group'] ?? '',
				'schema' => $schema,
			);
		}

		return $settings;
	}

	/**
	 * Builds the JSON Schema describing a single setting's value.
	 *
	 * As in the settings endpoint, objects in the schema reject properties they do not declare,
	 * unless the schema allows them.
	 *
	 * @since 7.2.0
	 *
	 * @param array<string, mixed>      $args The setting registration arguments.
	 * @param bool|array<string, mixed> $show The setting's `show_in_abilities` value.
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
