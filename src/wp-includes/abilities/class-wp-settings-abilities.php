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
 * Provides the read-only `core/settings` ability and the shared building blocks
 * (exposed-settings discovery, schema generation, value casting) that are intended to
 * also back a future write-oriented `core/manage-settings` ability.
 *
 * This class is part of WordPress' internal implementation of the core abilities and is
 * not part of the public API. It may be changed or removed at any time without notice.
 * Do not use it directly or rely on its existence.
 *
 * @since 7.1.0
 *
 * @access private
 */
class WP_Settings_Abilities {

	/**
	 * The ability category used for settings abilities.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	const CATEGORY = 'site';

	/**
	 * Registers all settings abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.1.0
	 */
	public static function register(): void {
		self::register_get_settings();

		/*
		 * A future write-oriented ability can be registered here, reusing the shared
		 * helpers below (get_exposed_settings(), value_schema(), cast_value()):
		 *
		 *     self::register_manage_settings();
		 */
	}

	/**
	 * Registers the read-only `core/settings` ability.
	 *
	 * @since 7.1.0
	 */
	public static function register_get_settings(): void {
		$settings   = self::get_exposed_settings();
		$groups     = array_values( array_unique( array_filter( wp_list_pluck( $settings, 'group' ) ) ) );
		$slugs      = array_keys( $settings );
		$properties = array();
		foreach ( $settings as $exposed_name => $setting ) {
			$properties[ $exposed_name ] = $setting['schema'];
		}

		wp_register_ability(
			'core/settings',
			array(
				'label'               => __( 'Get Settings' ),
				'description'         => __( 'Returns WordPress settings as a flat map of setting name to value. By default returns all settings exposed to abilities, or optionally a subset filtered by settings group or by setting name.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::get_settings_input_schema( $groups, $slugs ),
				'output_schema'       => array(
					'type'                 => 'object',
					'description'          => __( 'A map of setting name to its current value.' ),
					'properties'           => $properties,
					'additionalProperties' => false,
				),
				'execute_callback'    => array( self::class, 'execute_get_settings' ),
				'permission_callback' => array( self::class, 'has_permission' ),
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
	 * Executes the `core/settings` ability.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed> Map of exposed setting name to current value.
	 */
	public static function execute_get_settings( $input = array() ): array {
		$input = is_array( $input ) ? $input : array();

		$settings = self::get_exposed_settings();
		$group    = isset( $input['group'] ) ? (string) $input['group'] : '';
		$slugs    = isset( $input['slugs'] ) && is_array( $input['slugs'] ) ? $input['slugs'] : array();

		$result = array();
		foreach ( $settings as $exposed_name => $setting ) {
			if ( '' !== $group && $setting['group'] !== $group ) {
				continue;
			}
			if ( ! empty( $slugs ) && ! in_array( $exposed_name, $slugs, true ) ) {
				continue;
			}

			$type  = isset( $setting['schema']['type'] ) ? (string) $setting['schema']['type'] : 'string';
			$value = get_option( $setting['option'], $setting['default'] );

			$result[ $exposed_name ] = self::cast_value( $value, $type );
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
	public static function has_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Builds the input schema for the get ability: filter by group XOR by name.
	 *
	 * @since 7.1.0
	 *
	 * @param string[] $groups Available settings groups.
	 * @param string[] $slugs  Available exposed setting names.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	protected static function get_settings_input_schema( array $groups, array $slugs ): array {
		return array(
			'type'    => 'object',
			'default' => array(),
			// Filter by group OR by name, but not both at once.
			'oneOf'   => array(
				array(
					'title'                => __( 'All settings' ),
					'type'                 => 'object',
					'additionalProperties' => false,
				),
				array(
					'title'                => __( 'Filter by group' ),
					'type'                 => 'object',
					'required'             => array( 'group' ),
					'properties'           => array(
						'group' => array(
							'type'        => 'string',
							'enum'        => $groups,
							'description' => __( 'Return only settings that belong to this settings group.' ),
						),
					),
					'additionalProperties' => false,
				),
				array(
					'title'                => __( 'Filter by name' ),
					'type'                 => 'object',
					'required'             => array( 'slugs' ),
					'properties'           => array(
						'slugs' => array(
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => $slugs,
							),
							'description' => __( 'Return only the settings with these names.' ),
						),
					),
					'additionalProperties' => false,
				),
			),
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
	protected static function get_exposed_settings(): array {
		$settings = array();

		foreach ( get_registered_settings() as $option_name => $args ) {
			$show = $args['show_in_abilities'] ?? false;
			if ( empty( $show ) ) {
				continue;
			}

			$option_name  = (string) $option_name;
			$exposed_name = is_array( $show ) && ! empty( $show['name'] ) ? (string) $show['name'] : $option_name;

			$settings[ $exposed_name ] = array(
				'option'  => $option_name,
				'group'   => isset( $args['group'] ) ? (string) $args['group'] : '',
				'default' => array_key_exists( 'default', $args ) ? $args['default'] : false,
				'schema'  => self::value_schema( $args, $show ),
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
	protected static function value_schema( array $args, $show ): array {
		$schema = array(
			'type' => isset( $args['type'] ) ? (string) $args['type'] : 'string',
		);
		if ( ! empty( $args['label'] ) ) {
			$schema['title'] = $args['label'];
		}
		if ( ! empty( $args['description'] ) ) {
			$schema['description'] = $args['description'];
		}
		if ( is_array( $show ) && isset( $show['schema'] ) && is_array( $show['schema'] ) ) {
			$schema = array_merge( $schema, $show['schema'] );
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
	protected static function cast_value( $value, string $type ) {
		switch ( $type ) {
			case 'boolean':
				return (bool) $value;
			case 'integer':
				return (int) $value;
			case 'number':
				return (float) $value;
			case 'array':
			case 'object':
				return is_array( $value ) ? $value : array();
			default:
				return is_scalar( $value ) ? (string) $value : $value;
		}
	}
}
