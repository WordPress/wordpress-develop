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
	 * Dynamic output schema built from registered settings.
	 *
	 * @since 6.9.0
	 * @var array
	 */
	private static $output_schema;

	/**
	 * Available setting slugs with show_in_rest enabled.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private static $available_slugs;

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
		self::$available_slugs  = self::get_available_slugs();
		self::$output_schema    = self::build_output_schema();
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
	 * Gets unique setting slugs that have show_in_rest enabled.
	 *
	 * @since 7.0.0
	 *
	 * @return array List of unique setting slugs.
	 */
	private static function get_available_slugs(): array {
		$slugs = array();

		foreach ( get_registered_settings() as $option_name => $args ) {
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}

			$rest_name = $option_name;
			if ( is_array( $args['show_in_rest'] ) && ! empty( $args['show_in_rest']['name'] ) ) {
				$rest_name = $args['show_in_rest']['name'];
			}

			$slugs[] = $rest_name;
		}

		sort( $slugs );

		return $slugs;
	}

	/**
	 * Builds a rich output schema from registered settings metadata.
	 *
	 * Creates a JSON Schema that documents each setting group and its settings
	 * with their types, titles, descriptions, defaults, and any additional
	 * schema properties from show_in_rest.
	 *
	 * @since 6.9.0
	 *
	 * @return array JSON Schema for the output.
	 */
	private static function build_output_schema(): array {
		$group_properties = array();

		foreach ( get_registered_settings() as $option_name => $args ) {
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}

			$group = $args['group'] ?? 'general';

			$rest_name = $option_name;
			if ( is_array( $args['show_in_rest'] ) && ! empty( $args['show_in_rest']['name'] ) ) {
				$rest_name = $args['show_in_rest']['name'];
			}

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

			if ( isset( $args['default'] ) ) {
				$setting_schema['default'] = $args['default'];
			}

			if ( is_array( $args['show_in_rest'] ) && ! empty( $args['show_in_rest']['schema'] ) ) {
				$setting_schema = array_merge( $setting_schema, $args['show_in_rest']['schema'] );
			}

			if ( ! isset( $group_properties[ $group ] ) ) {
				$group_properties[ $group ] = array(
					'type'        => 'object',
					'description' => sprintf(
						/* translators: %s: Settings group name. */
						__( '%s settings.' ),
						ucfirst( $group )
					),
					'properties'  => array(),
				);
			}

			$group_properties[ $group ]['properties'][ $rest_name ] = $setting_schema;
		}

		ksort( $group_properties );

		return array(
			'type'        => 'object',
			'description' => __( 'Settings grouped by registration group. Each group contains settings with their current values.' ),
			'properties'  => $group_properties,
		);
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
				'description'         => __( 'Returns registered WordPress settings grouped by their registration group. Returns key-value pairs per setting.' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'group' => array(
							'type'        => 'string',
							'description' => __( 'Filter settings by group name. If omitted, returns all groups. Cannot be used with slugs.' ),
							'enum'        => self::$available_groups,
						),
						'slugs' => array(
							'type'        => 'array',
							'description' => __( 'Filter settings by specific setting slugs. If omitted, returns all settings. Cannot be used with group.' ),
							'items'       => array(
								'type' => 'string',
								'enum' => self::$available_slugs,
							),
						),
					),
					'oneOf'                => array(
						array( 'required' => array( 'group' ) ),
						array( 'required' => array( 'slugs' ) ),
						array( 'maxProperties' => 0 ),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => self::$output_schema,
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
	 *     @type string   $group Optional. Filter settings by group name. Cannot be used with slugs.
	 *     @type string[] $slugs Optional. Filter settings by specific setting slugs. Cannot be used with group.
	 * }
	 * @return array Settings grouped by registration group.
	 */
	public static function execute_get_settings( $input = array() ): array {
		$input        = is_array( $input ) ? $input : array();
		$filter_group = ! empty( $input['group'] ) ? $input['group'] : null;
		$filter_slugs = ! empty( $input['slugs'] ) ? $input['slugs'] : null;

		$registered_settings = get_registered_settings();
		$settings_by_group   = array();

		foreach ( $registered_settings as $option_name => $args ) {
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}

			$group = $args['group'] ?? 'general';

			if ( $filter_group && $group !== $filter_group ) {
				continue;
			}

			$rest_name = $option_name;
			if ( is_array( $args['show_in_rest'] ) && ! empty( $args['show_in_rest']['name'] ) ) {
				$rest_name = $args['show_in_rest']['name'];
			}

			if ( $filter_slugs && ! in_array( $rest_name, $filter_slugs, true ) ) {
				continue;
			}

			$default = $args['default'] ?? null;
			if ( is_array( $args['show_in_rest'] ) && isset( $args['show_in_rest']['schema']['default'] ) ) {
				$default = $args['show_in_rest']['schema']['default'];
			}

			$value = get_option( $option_name, $default );
			$value = self::cast_value( $value, $args['type'] ?? 'string' );

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
