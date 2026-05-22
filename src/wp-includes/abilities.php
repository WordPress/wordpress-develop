<?php
/**
 * Core Abilities registration.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 6.9.0
 */

declare( strict_types = 1 );

/**
 * Registers the core ability categories.
 *
 * @since 6.9.0
 */
function wp_register_core_ability_categories(): void {
	wp_register_ability_category(
		'site',
		array(
			'label'       => __( 'Site' ),
			'description' => __( 'Abilities that retrieve or modify site information and settings.' ),
		)
	);

	wp_register_ability_category(
		'user',
		array(
			'label'       => __( 'User' ),
			'description' => __( 'Abilities that retrieve or modify user information and settings.' ),
		)
	);
}

/**
 * Registers the default core abilities.
 *
 * @since 6.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wp_register_core_abilities(): void {
	$category_site = 'site';
	$category_user = 'user';

	$site_info_properties = array(
		'name'        => array(
			'type'        => 'string',
			'description' => __( 'The site title.' ),
		),
		'description' => array(
			'type'        => 'string',
			'description' => __( 'The site tagline.' ),
		),
		'url'         => array(
			'type'        => 'string',
			'description' => __( 'The site home URL.' ),
		),
		'wpurl'       => array(
			'type'        => 'string',
			'description' => __( 'The WordPress installation URL.' ),
		),
		'admin_email' => array(
			'type'        => 'string',
			'description' => __( 'The site administrator email address.' ),
		),
		'charset'     => array(
			'type'        => 'string',
			'description' => __( 'The site character encoding.' ),
		),
		'language'    => array(
			'type'        => 'string',
			'description' => __( 'The site language locale code.' ),
		),
		'version'     => array(
			'type'        => 'string',
			'description' => __( 'The WordPress version.' ),
		),
	);
	$site_info_fields     = array_keys( $site_info_properties );

	wp_register_ability(
		'core/get-site-info',
		array(
			'label'               => __( 'Get Site Information' ),
			'description'         => __( 'Returns site information configured in WordPress. By default returns all fields, or optionally a filtered subset.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'fields' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $site_info_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $site_info_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $site_info_fields ): array {
				$input = is_array( $input ) ? $input : array();
				$requested_fields = ! empty( $input['fields'] ) ? $input['fields'] : $site_info_fields;

				$result = array();
				foreach ( $requested_fields as $field ) {
					if ( 'language' === $field ) {
						$result[ $field ] = str_replace( '_', '-', get_locale() );
					} else {
						$result[ $field ] = get_bloginfo( $field );
					}
				}

				return $result;
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'manage_options' );
			},
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

	wp_register_ability(
		'core/get-user-info',
		array(
			'label'               => __( 'Get User Information' ),
			'description'         => __( 'Returns basic profile details for the current authenticated user to support personalization, auditing, and access-aware behavior.' ),
			'category'            => $category_user,
			'output_schema'       => array(
				'type'                 => 'object',
				'required'             => array( 'id', 'display_name', 'user_nicename', 'user_login', 'roles', 'locale' ),
				'properties'           => array(
					'id'            => array(
						'type'        => 'integer',
						'description' => __( 'The user ID.' ),
					),
					'display_name'  => array(
						'type'        => 'string',
						'description' => __( 'The display name of the user.' ),
					),
					'user_nicename' => array(
						'type'        => 'string',
						'description' => __( 'The URL-friendly name for the user.' ),
					),
					'user_login'    => array(
						'type'        => 'string',
						'description' => __( 'The login username for the user.' ),
					),
					'roles'         => array(
						'type'        => 'array',
						'description' => __( 'The roles assigned to the user.' ),
						'items'       => array(
							'type' => 'string',
						),
					),
					'locale'        => array(
						'type'        => 'string',
						'description' => __( 'The locale string for the user, such as en_US.' ),
					),
				),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function (): array {
				$current_user = wp_get_current_user();

				return array(
					'id'            => $current_user->ID,
					'display_name'  => $current_user->display_name,
					'user_nicename' => $current_user->user_nicename,
					'user_login'    => $current_user->user_login,
					'roles'         => $current_user->roles,
					'locale'        => get_user_locale( $current_user ),
				);
			},
			'permission_callback' => static function (): bool {
				return is_user_logged_in();
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => false,
			),
		)
	);

	$env_info_properties = array(
		'environment'    => array(
			'type'        => 'string',
			'description' => __( 'The site\'s runtime environment classification (can be one of these: production, staging, development, local).' ),
			'enum'        => array( 'production', 'staging', 'development', 'local' ),
		),
		'php_version'    => array(
			'type'        => 'string',
			'description' => __( 'The PHP runtime version executing WordPress.' ),
		),
		'db_server_info' => array(
			'type'        => 'string',
			'description' => __( 'The database server vendor and version string reported by the driver.' ),
		),
		'wp_version'     => array(
			'type'        => 'string',
			'description' => __( 'The WordPress core version running on this site.' ),
		),
		'site_health'    => array(
			'type'                 => 'object',
			'description'          => __( 'A high-level overview of the site\'s health, populated from cached data. Can vary across calls as the cache refreshes.' ),
			'properties'           => array(
				'status'    => array(
					'type'        => 'string',
					'title'       => __( 'Status' ),
					'description' => __( 'The overall health status of the site (e.g., good, recommended, critical, unknown).' ),
					'enum'        => array( 'unknown', 'good', 'recommended', 'critical' ),
				),
				'counts'    => array(
					'type'                 => 'object',
					'title'                => __( 'Counts' ),
					'description'          => __( 'The count of Site Health test results by severity.' ),
					'properties'           => array(
						'good'        => array(
							'type'        => 'integer',
							'title'       => __( 'Good' ),
							'description' => __( 'Number of passing tests.' ),
						),
						'recommended' => array(
							'type'        => 'integer',
							'title'       => __( 'Recommended' ),
							'description' => __( 'Number of recommended improvements.' ),
						),
						'critical'    => array(
							'type'        => 'integer',
							'title'       => __( 'Critical' ),
							'description' => __( 'Number of critical issues.' ),
						),
					),
					'additionalProperties' => false,
				),
				'issues'    => array(
					'type'        => 'array',
					'title'       => __( 'Issues' ),
					'description' => __( 'Actionable issues, capped at 10 items.' ),
					'items'       => array(
						'type'                 => 'object',
						'properties'           => array(
							'test'           => array(
								'type'        => 'string',
								'title'       => __( 'Test Identifier' ),
								'description' => __( 'The machine-readable name of the Site Health test.' ),
							),
							'label'          => array(
								'type'        => 'string',
								'title'       => __( 'Label' ),
								'description' => __( 'Short description of the issue.' ),
							),
							'severity'       => array(
								'type'        => 'string',
								'title'       => __( 'Severity' ),
								'description' => __( 'Severity level (recommended, critical).' ),
								'enum'        => array( 'recommended', 'critical' ),
							),
							'recommendation' => array(
								'type'        => 'string',
								'title'       => __( 'Recommendation' ),
								'description' => __( 'Guidance or description for resolving the issue.' ),
							),
						),
						'additionalProperties' => false,
					),
				),
				'truncated' => array(
					'type'        => 'boolean',
					'title'       => __( 'Truncated' ),
					'description' => __( 'Whether the list of issues has been truncated due to size limits.' ),
				),
				'timestamp' => array(
					'type'        => 'integer',
					'title'       => __( 'Timestamp' ),
					'description' => __( 'The Unix timestamp of when the Site Health data was last collected, or 0 when no cached data exists.' ),
				),
			),
			'additionalProperties' => false,
		),
	);
	$env_info_fields     = array_keys( $env_info_properties );

	wp_register_ability(
		'core/get-environment-info',
		array(
			'label'               => __( 'Get Environment Info' ),
			'description'         => __( 'Returns core details about the site\'s runtime context for diagnostics and compatibility (environment, PHP runtime, database server info, WordPress version).' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'fields' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $env_info_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $env_info_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $env_info_fields ): array {
				global $wpdb;

				$input            = is_array( $input ) ? $input : array();
				$requested_fields = ! empty( $input['fields'] ) ? $input['fields'] : $env_info_fields;

				$result = array();

				if ( in_array( 'environment', $requested_fields, true ) ) {
					$result['environment'] = wp_get_environment_type();
				}

				if ( in_array( 'php_version', $requested_fields, true ) ) {
					$result['php_version'] = phpversion();
				}

				if ( in_array( 'db_server_info', $requested_fields, true ) ) {
					$db_server_info = '';
					if ( method_exists( $wpdb, 'db_server_info' ) ) {
						$db_server_info = $wpdb->db_server_info() ?? '';
					}
					$result['db_server_info'] = $db_server_info;
				}

				if ( in_array( 'wp_version', $requested_fields, true ) ) {
					$result['wp_version'] = get_bloginfo( 'version' );
				}

				if ( in_array( 'site_health', $requested_fields, true ) ) {
					$result['site_health'] = wp_get_abilities_api_site_health_summary_from_cache();
				}

				return $result;
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'manage_options' );
			},
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
 * Builds the Site Health portion of `core/get-environment-info` from cached results only.
 *
 * @since 6.9.1
 *
 * @return array{
 *     status: 'unknown'|'good'|'recommended'|'critical',
 *     counts: array{good: int, recommended: int, critical: int},
 *     issues: array<int, array{test: string, label: string, severity: string, recommendation: string}>,
 *     truncated: bool,
 *     timestamp: int
 * }
 */
function wp_get_abilities_api_site_health_summary_from_cache(): array {
	$site_health = array(
		'status'    => 'unknown',
		'counts'    => array(
			'good'        => 0,
			'recommended' => 0,
			'critical'    => 0,
		),
		'issues'    => array(),
		'truncated' => false,
		'timestamp' => 0,
	);

	$cached_health = get_transient( 'health-check-site-status-result' );

	if ( false === $cached_health ) {
		return $site_health;
	}

	$health_data = json_decode( $cached_health, true );
	if ( ! is_array( $health_data ) ) {
		return $site_health;
	}

	if ( isset( $health_data['good'], $health_data['recommended'], $health_data['critical'] ) ) {
		$site_health['counts']['good']        = (int) $health_data['good'];
		$site_health['counts']['recommended'] = (int) $health_data['recommended'];
		$site_health['counts']['critical']    = (int) $health_data['critical'];

		if ( $site_health['counts']['critical'] > 0 ) {
			$site_health['status'] = 'critical';
		} elseif ( $site_health['counts']['recommended'] > 0 ) {
			$site_health['status'] = 'recommended';
		} else {
			$site_health['status'] = 'good';
		}
	}

	if ( isset( $health_data['timestamp'] ) ) {
		$site_health['timestamp'] = (int) $health_data['timestamp'];
	}

	if ( isset( $health_data['issues'] ) && is_array( $health_data['issues'] ) ) {
		$issues_count = 0;
		foreach ( $health_data['issues'] as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}

			$status = isset( $issue['status'] ) ? (string) $issue['status'] : '';
			if ( ! in_array( $status, array( 'recommended', 'critical' ), true ) ) {
				continue;
			}

			if ( $issues_count >= 10 ) {
				$site_health['truncated'] = true;
				break;
			}

			$site_health['issues'][] = array(
				'test'           => isset( $issue['test'] ) ? (string) $issue['test'] : '',
				'label'          => isset( $issue['label'] ) ? wp_strip_all_tags( (string) $issue['label'] ) : '',
				'severity'       => $status,
				'recommendation' => isset( $issue['description'] ) ? wp_strip_all_tags( (string) $issue['description'] ) : '',
			);
			++$issues_count;
		}
	}

	return $site_health;
}
