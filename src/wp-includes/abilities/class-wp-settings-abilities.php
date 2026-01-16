<?php
/**
 * Registers core settings abilities.
 *
 * This is a utility class to encapsulate the registration of settings-related abilities.
 * It is not intended to be instantiated or consumed directly by any other code or plugin.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 6.9.0
 *
 * @internal This class is not part of the public API.
 * @access private
 */

declare( strict_types=1 );

/**
 * Registers core settings abilities.
 *
 * @since 6.9.0
 * @access private
 */
class WP_Settings_Abilities {

	/**
	 * Available setting groups with show_in_rest enabled.
	 *
	 * @since 6.9.0
	 * @var array
	 */
	private static $available_groups;

	/**
	 * Registers all settings abilities.
	 *
	 * @since 6.9.0
	 *
	 * @return void
	 */
	public static function register(): void {
		self::init();
		self::register_get_settings();
	}

	/**
	 * Initializes shared data for settings abilities.
	 *
	 * @since 6.9.0
	 *
	 * @return void
	 */
	private static function init(): void {
		self::$available_groups = self::get_available_groups();
	}

	/**
	 * Gets unique setting groups that have show_in_rest enabled.
	 *
	 * @since 6.9.0
	 *
	 * @return array List of unique group names.
	 */
	private static function get_available_groups(): array {
		$groups = array();

		foreach ( get_registered_settings() as $args ) {
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}

			$group = $args['group'] ?? 'general';
			if ( ! in_array( $group, $groups, true ) ) {
				$groups[] = $group;
			}
		}

		sort( $groups );

		return $groups;
	}

	/**
	 * Registers the core/get-settings ability.
	 *
	 * @since 6.9.0
	 *
	 * @return void
	 */
	private static function register_get_settings(): void {
		wp_register_ability(
			'core/get-settings',
			array(
				'label'               => __( 'Get Settings' ),
				'description'         => __( 'Returns registered WordPress settings exposed to the REST API, grouped by their registration group. Returns key-value pairs similar to the REST API settings endpoint.' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'group' => array(
							'type'        => 'string',
							'description' => __( 'Filter settings by group name. If omitted, returns all groups.' ),
							'enum'        => self::$available_groups,
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'description'          => __( 'Settings grouped by registration group. Each group contains key-value pairs where the key is the setting name (or REST alias) and the value is the current setting value.' ),
					'additionalProperties' => array(
						'type'                 => 'object',
						'description'          => __( 'A settings group containing setting name to value mappings.' ),
						'additionalProperties' => true,
					),
				),
				'execute_callback'    => array( __CLASS__, 'execute_get_settings' ),
				'permission_callback' => array( __CLASS__, 'check_manage_options' ),
				'meta'                => array(
					'annotations'  => array(
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Permission callback for settings abilities.
	 *
	 * @since 6.9.0
	 *
	 * @return bool True if the current user can manage options, false otherwise.
	 */
	public static function check_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute callback for core/get-settings ability.
	 *
	 * Retrieves all registered settings that are exposed to the REST API,
	 * grouped by their registration group.
	 *
	 * @since 6.9.0
	 *
	 * @param array $input {
	 *     Optional. Input parameters.
	 *
	 *     @type string $group Optional. Filter settings by group name.
	 * }
	 * @return array Settings grouped by registration group.
	 */
	public static function execute_get_settings( $input = array() ): array {
		$input        = is_array( $input ) ? $input : array();
		$filter_group = ! empty( $input['group'] ) ? $input['group'] : null;

		$registered_settings = get_registered_settings();
		$settings_by_group   = array();

		foreach ( $registered_settings as $option_name => $args ) {
			// Only include settings exposed to REST API.
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}

			$group = $args['group'] ?? 'general';

			// Skip if filtering by group and doesn't match.
			if ( $filter_group && $group !== $filter_group ) {
				continue;
			}

			// Determine the REST name (may be aliased via show_in_rest.name).
			$rest_name = $option_name;
			if ( is_array( $args['show_in_rest'] ) && ! empty( $args['show_in_rest']['name'] ) ) {
				$rest_name = $args['show_in_rest']['name'];
			}

			// Get default value.
			$default = $args['default'] ?? null;
			if ( is_array( $args['show_in_rest'] ) && isset( $args['show_in_rest']['schema']['default'] ) ) {
				$default = $args['show_in_rest']['schema']['default'];
			}

			// Get current value.
			$value = get_option( $option_name, $default );

			// Cast value to proper type.
			$value = self::cast_value( $value, $args['type'] ?? 'string' );

			// Initialize group if needed.
			if ( ! isset( $settings_by_group[ $group ] ) ) {
				$settings_by_group[ $group ] = array();
			}

			$settings_by_group[ $group ][ $rest_name ] = $value;
		}

		ksort( $settings_by_group );

		return $settings_by_group;
	}

	/**
	 * Casts a value to the appropriate type based on the setting's registered type.
	 *
	 * @since 6.9.0
	 *
	 * @param mixed  $value The value to cast.
	 * @param string $type  The registered type (string, boolean, integer, number, array, object).
	 * @return mixed The cast value.
	 */
	private static function cast_value( $value, string $type ) {
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
			case 'string':
			default:
				return (string) $value;
		}
	}
}
