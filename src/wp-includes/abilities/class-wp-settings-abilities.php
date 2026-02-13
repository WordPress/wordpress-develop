<?php
/**
 * Registers core settings abilities.
 *
 * This is a utility class to encapsulate the registration of settings-related abilities.
 * It is not intended to be instantiated or consumed directly by any other code or plugin.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 7.0.0
 *
 * @internal This class is not part of the public API.
 * @access private
 */

declare( strict_types=1 );

/**
 * Registers core settings abilities.
 *
 * @since 7.0.0
 * @access private
 */
class WP_Settings_Abilities {

	/**
	 * Available setting groups with show_in_abilities enabled.
	 *
	 * @since 7.0.0
	 * @var string[]
	 */
	private static array $available_groups;

	/**
	 * Schema for settings grouped by registration group.
	 *
	 * @since 7.0.0
	 * @var array<string, mixed>
	 */
	private static array $settings_schema;

	/**
	 * Available setting slugs with show_in_abilities enabled.
	 *
	 * @since 7.0.0
	 * @var string[]
	 */
	private static array $available_slugs;

	/**
	 * Registers all settings abilities.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		self::init();
		self::register_get_settings();
		self::register_update_settings();
	}

	/**
	 * Initializes shared data for settings abilities.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function init(): void {
		self::$available_groups = self::get_available_groups();
		self::$available_slugs  = self::get_available_slugs();
		self::$settings_schema  = self::build_settings_schema();
	}

	/**
	 * Gets registered settings that have show_in_abilities enabled.
	 *
	 * @since 7.0.0
	 *
	 * @return array Associative array of option_name => args for allowed settings.
	 */
	private static function get_allowed_settings(): array {
		$settings = array();

		foreach ( get_registered_settings() as $option_name => $args ) {
			if ( ! empty( $args['show_in_abilities'] ) ) {
				$settings[ $option_name ] = $args;
			}
		}

		return $settings;
	}

	/**
	 * Gets unique setting groups that have show_in_abilities enabled.
	 *
	 * @since 7.0.0
	 *
	 * @return string[] List of unique group names.
	 */
	private static function get_available_groups(): array {
		$groups = array();

		foreach ( self::get_allowed_settings() as $args ) {
			$group = $args['group'] ?? 'general';
			if ( ! in_array( $group, $groups, true ) ) {
				$groups[] = $group;
			}
		}

		sort( $groups );

		return $groups;
	}

	/**
	 * Gets unique setting slugs that have show_in_abilities enabled.
	 *
	 * @since 7.0.0
	 *
	 * @return string[] List of unique setting slugs.
	 */
	private static function get_available_slugs(): array {
		$slugs = array_keys( self::get_allowed_settings() );
		sort( $slugs );
		return $slugs;
	}

	/**
	 * Builds a schema for settings grouped by registration group.
	 *
	 * Creates a JSON Schema that documents each setting group and its settings
	 * with their types, titles, descriptions, defaults, and any additional
	 * schema properties from show_in_abilities.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, mixed> JSON Schema for settings.
	 */
	private static function build_settings_schema(): array {
		$group_properties = array();

		foreach ( self::get_allowed_settings() as $option_name => $args ) {
			$group = $args['group'] ?? 'general';

			$setting_schema = array(
				'type' => $args['type'] ?? 'string',
			);

			if ( ! empty( $args['label'] ) ) {
				$setting_schema['title'] = $args['label'];
			}

			if ( ! empty( $args['description'] ) ) {
				$setting_schema['description'] = $args['description'];
			} elseif ( ! empty( $args['label'] ) ) {
				$setting_schema['description'] = $args['label'];
			}

			// Merge custom schema from show_in_abilities if provided as an array.
			if ( is_array( $args['show_in_abilities'] ) && ! empty( $args['show_in_abilities']['schema'] ) ) {
				$setting_schema = array_merge( $setting_schema, $args['show_in_abilities']['schema'] );
			}

			if ( ! isset( $group_properties[ $group ] ) ) {
				$group_properties[ $group ] = array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				);
			}

			$group_properties[ $group ]['properties'][ $option_name ] = $setting_schema;
		}

		ksort( $group_properties );

		return array(
			'type'                 => 'object',
			'description'          => __( 'Settings grouped by registration group. Each group contains settings with their current values.' ),
			'properties'           => $group_properties,
			'additionalProperties' => false,
		);
	}

	/**
	 * Registers the core/get-settings ability.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function register_get_settings(): void {
		wp_register_ability(
			'core/get-settings',
			array(
				'label'               => __( 'Get Settings' ),
				'description'         => __( 'Returns registered WordPress settings grouped by their registration group. Returns key-value pairs per setting.' ),
				'category'            => 'site',
				'input_schema'        => array(
					'default' => (object) array(),
					'oneOf'   => array(
						// Branch 1: No filter (empty object).
						array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'maxProperties'        => 0,
						),
						// Branch 2: Filter by group only.
						array(
							'type'                 => 'object',
							'properties'           => array(
								'group' => array(
									'type'        => 'string',
									'description' => __( 'Filter settings by group name.' ),
									'enum'        => self::$available_groups,
								),
							),
							'required'             => array( 'group' ),
							'additionalProperties' => false,
						),
						// Branch 3: Filter by slugs only.
						array(
							'type'                 => 'object',
							'properties'           => array(
								'slugs' => array(
									'type'        => 'array',
									'description' => __( 'Filter settings by specific setting slugs.' ),
									'items'       => array(
										'type' => 'string',
										'enum' => self::$available_slugs,
									),
								),
							),
							'required'             => array( 'slugs' ),
							'additionalProperties' => false,
						),
					),
				),
				'output_schema'       => self::$settings_schema,
				'execute_callback'    => array( __CLASS__, 'execute_get_settings' ),
				'permission_callback' => array( __CLASS__, 'check_manage_options' ),
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
	 * Registers the core/update-settings ability.
	 *
	 * @since 7.0.0
	 */
	private static function register_update_settings(): void {
		// Reuse settings schema with updated descriptions for input and output.
		$input_settings_schema                = self::$settings_schema;
		$input_settings_schema['description'] = __( 'Settings to update, grouped by registration group. Same structure as returned by core/get-settings.' );

		$output_settings_schema                = self::$settings_schema;
		$output_settings_schema['description'] = __( 'Settings that were successfully updated, grouped by registration group.' );

		wp_register_ability(
			'core/update-settings',
			array(
				'label'               => __( 'Update Settings' ),
				'description'         => __( 'Updates registered WordPress settings. Only settings with show_in_abilities enabled can be modified.' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'settings' ),
					'properties'           => array(
						'settings' => $input_settings_schema,
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'updated_settings' => $output_settings_schema,
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'execute_update_settings' ),
				'permission_callback' => array( __CLASS__, 'check_manage_options' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Permission callback for settings abilities.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if the current user can manage options, false otherwise.
	 */
	public static function check_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute callback for core/get-settings ability.
	 *
	 * Retrieves all registered settings that are exposed through the Abilities API,
	 * grouped by their registration group.
	 *
	 * @since 7.0.0
	 *
	 * @param array $input {
	 *     Optional. Input parameters.
	 *
	 *     @type string   $group Optional. Filter settings by group name. Cannot be used with slugs.
	 *     @type string[] $slugs Optional. Filter settings by specific setting slugs. Cannot be used with group.
	 * }
	 * @return array Settings grouped by registration group.
	 */
	public static function execute_get_settings( $input = array() ): array {
		$input        = is_array( $input ) ? $input : array();
		$filter_group = ! empty( $input['group'] ) ? $input['group'] : null;
		$filter_slugs = ! empty( $input['slugs'] ) ? $input['slugs'] : null;

		$settings_by_group = array();

		foreach ( self::get_allowed_settings() as $option_name => $args ) {
			$group = $args['group'] ?? 'general';

			if ( $filter_group && $group !== $filter_group ) {
				continue;
			}

			if ( $filter_slugs && ! in_array( $option_name, $filter_slugs, true ) ) {
				continue;
			}

			$default = $args['default'] ?? null;

			$value = get_option( $option_name, $default );
			$value = self::cast_value( $value, $args['type'] ?? 'string' );

			if ( ! isset( $settings_by_group[ $group ] ) ) {
				$settings_by_group[ $group ] = array();
			}

			$settings_by_group[ $group ][ $option_name ] = $value;
		}

		ksort( $settings_by_group );

		return $settings_by_group;
	}

	/**
	 * Execute callback for core/update-settings ability.
	 *
	 * Updates registered settings that are exposed through the Abilities API.
	 * Returns updated settings grouped by registration group.
	 *
	 * @since 7.0.0
	 *
	 * @param array $input {
	 *     Input parameters.
	 *
	 *     @type array $settings Settings to update, grouped by registration group.
	 * }
	 * @return array<string, array<string, mixed>|object>|WP_Error Updated settings on success, WP_Error on failure.
	 */
	public static function execute_update_settings( $input = array() ): array {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['settings'] ) || ! is_array( $input['settings'] ) ) {
			return array(
				'updated_settings' => (object) array(),
			);
		}

		$grouped_settings = $input['settings'];
		$allowed_settings = self::get_allowed_settings();

		$updated_settings = array();

		// Iterate through groups (general, reading, writing, etc.).
		foreach ( $grouped_settings as $group => $settings ) {
			if ( ! is_array( $settings ) ) {
				continue;
			}

			// Iterate through settings within each group.
			foreach ( $settings as $option_name => $value ) {
				if ( ! isset( $allowed_settings[ $option_name ] ) ) {
					continue;
				}

				$args = $allowed_settings[ $option_name ];

				$setting_group = $args['group'] ?? 'general';
				if ( $setting_group !== $group ) {
					continue;
				}

				$setting_type = $args['type'] ?? 'string';

				$schema = array(
					'type' => $setting_type,
				);
				if ( is_array( $args['show_in_rest'] ) && isset( $args['show_in_rest']['schema'] ) ) {
					$schema = array_merge( $schema, $args['show_in_rest']['schema'] );
				}

				$sanitized_value = rest_sanitize_value_from_schema( $value, $schema );

				if ( ! empty( $args['sanitize_callback'] ) && is_callable( $args['sanitize_callback'] ) ) {
					$sanitized_value = call_user_func( $args['sanitize_callback'], $sanitized_value );
				}

				$updated = update_option( $option_name, $sanitized_value );

				// Cast values for comparison (handles type mismatches from database and REST sanitization).
				$current_value   = self::cast_value( get_option( $option_name ), $setting_type );
				$sanitized_value = self::cast_value( $sanitized_value, $setting_type );

				if ( $updated || $current_value === $sanitized_value ) {
					if ( ! isset( $updated_settings[ $group ] ) ) {
						$updated_settings[ $group ] = array();
					}
					$updated_settings[ $group ][ $option_name ] = $current_value;
				} else {
					return new WP_Error(
						'rest_setting_update_failed',
						sprintf(
							/* translators: %s: Option name. */
							__( 'Failed to update setting: %s.' ),
							$option_name
						),
						array( 'status' => 500 )
					);
				}
			}
		}

		return array(
			'updated_settings' => ! empty( $updated_settings ) ? $updated_settings : (object) array(),
		);
	}

	/**
	 * Casts a value to the appropriate type based on the setting's registered type.
	 *
	 * @since 7.0.0
	 *
	 * @param mixed  $value The value to cast.
	 * @param string $type  The registered type (string, boolean, integer, number, array, object).
	 * @return string|bool|int|float|array The cast value.
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
