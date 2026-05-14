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
					$result[ $field ] = get_bloginfo( $field );
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

	$site_health_issue_properties = array(
		'test'        => array(
			'type'        => 'string',
			'title'       => __( 'Site Health test identifier' ),
			'description' => __( 'The machine identifier for the Site Health test that produced this entry.' ),
		),
		'label'       => array(
			'type'        => 'string',
			'title'       => __( 'Site Health issue label' ),
			'description' => __( 'A short title describing the Site Health issue.' ),
		),
		'status'      => array(
			'type'        => 'string',
			'title'       => __( 'Site Health issue severity' ),
			'description' => __( 'Whether this entry is a recommended improvement or a critical issue.' ),
			'enum'        => array( 'recommended', 'critical' ),
		),
		'description' => array(
			'type'        => 'string',
			'title'       => __( 'Site Health issue description' ),
			'description' => __( 'Plain text details for the Site Health issue, sourced from cached results.' ),
		),
	);

	$environment_info_properties = array(
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
			'description'          => __( 'A cached-only Site Health summary for agents: overall status, counts, and actionable issues.' ),
			'properties'           => array(
				'status'    => array(
					'type'        => 'string',
					'title'       => __( 'Site Health overall status' ),
					'description' => __( 'unknown: no cached Site Health results yet. good: no recommended or critical findings. recommended or critical: matching severity is present in cached counts.' ),
					'enum'        => array( 'unknown', 'good', 'recommended', 'critical' ),
				),
				'counts'    => array(
					'type'                 => 'object',
					'title'                => __( 'Site Health result counts' ),
					'description'          => __( 'How many Site Health tests reported each status in the cached run.' ),
					'properties'           => array(
						'good'        => array(
							'type'        => 'integer',
							'title'       => __( 'Good results' ),
							'description' => __( 'Number of tests reporting a good status.' ),
						),
						'recommended' => array(
							'type'        => 'integer',
							'title'       => __( 'Recommended improvements' ),
							'description' => __( 'Number of tests recommending an improvement.' ),
						),
						'critical'    => array(
							'type'        => 'integer',
							'title'       => __( 'Critical issues' ),
							'description' => __( 'Number of tests reporting a critical issue.' ),
						),
					),
					'additionalProperties' => false,
				),
				'issues'    => array(
					'type'        => 'array',
					'title'       => __( 'Actionable Site Health issues' ),
					'description' => __( 'Up to ten recommended or critical issues from the cached Site Health run.' ),
					'items'       => array(
						'type'                 => 'object',
						'properties'           => $site_health_issue_properties,
						'additionalProperties' => false,
					),
				),
				'truncated' => array(
					'type'        => 'boolean',
					'title'       => __( 'Issues list truncated' ),
					'description' => __( 'True when more than ten actionable issues exist in the cache.' ),
				),
			),
			'additionalProperties' => false,
		),
	);
	$environment_info_fields     = array_keys( $environment_info_properties );

	wp_register_ability(
		'core/get-environment-info',
		array(
			'label'               => __( 'Get Environment Info' ),
			'description'         => __( 'Returns core details about the site\'s runtime context for diagnostics and compatibility (environment, PHP runtime, database server info, WordPress version), plus an optional cached Site Health summary.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'fields' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $environment_info_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $environment_info_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $environment_info_fields ): array {
				global $wpdb;

				$input     = is_array( $input ) ? $input : array();
				$requested = ! empty( $input['fields'] ) ? $input['fields'] : $environment_info_fields;

				$result = array();

				if ( in_array( 'environment', $requested, true ) ) {
					$result['environment'] = wp_get_environment_type();
				}

				if ( in_array( 'php_version', $requested, true ) ) {
					$result['php_version'] = phpversion();
				}

				if ( in_array( 'db_server_info', $requested, true ) ) {
					$db_server_info = '';
					if ( method_exists( $wpdb, 'db_server_info' ) ) {
						$db_server_info = $wpdb->db_server_info() ?? '';
					}
					$result['db_server_info'] = $db_server_info;
				}

				if ( in_array( 'wp_version', $requested, true ) ) {
					$result['wp_version'] = get_bloginfo( 'version' );
				}

				if ( in_array( 'site_health', $requested, true ) ) {
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
 * @since tbd
 *
 * @return array{
 *     status: 'unknown'|'good'|'recommended'|'critical',
 *     counts: array{good: int, recommended: int, critical: int},
 *     issues: array<int, array{test: string, label: string, status: string, description: string}>,
 *     truncated: bool
 * }
 */
function wp_get_abilities_api_site_health_summary_from_cache(): array {
	$unknown = array(
		'status'    => 'unknown',
		'counts'    => array(
			'good'        => 0,
			'recommended' => 0,
			'critical'    => 0,
		),
		'issues'    => array(),
		'truncated' => false,
	);

	$cached = get_transient( 'health-check-site-status-result' );

	if ( false === $cached ) {
		return $unknown;
	}

	$data = json_decode( $cached, true );
	if ( ! is_array( $data ) ) {
		return $unknown;
	}

	$counts = array(
		'good'        => isset( $data['good'] ) ? (int) $data['good'] : 0,
		'recommended' => isset( $data['recommended'] ) ? (int) $data['recommended'] : 0,
		'critical'    => isset( $data['critical'] ) ? (int) $data['critical'] : 0,
	);

	$stored = array();
	if ( isset( $data['issues'] ) && is_array( $data['issues'] ) ) {
		foreach ( $data['issues'] as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}

			$status = isset( $issue['status'] ) ? (string) $issue['status'] : '';
			if ( ! in_array( $status, array( 'recommended', 'critical' ), true ) ) {
				continue;
			}

			$stored[] = array(
				'test'        => isset( $issue['test'] ) ? (string) $issue['test'] : '',
				'label'       => isset( $issue['label'] ) ? (string) $issue['label'] : '',
				'status'      => $status,
				'description' => isset( $issue['description'] ) ? (string) $issue['description'] : '',
			);
		}
	}

	$total_stored = count( $stored );
	$truncated    = $total_stored > 10;
	$issues       = array_slice( $stored, 0, 10 );

	$status = 'good';
	if ( $counts['critical'] > 0 ) {
		$status = 'critical';
	} elseif ( $counts['recommended'] > 0 ) {
		$status = 'recommended';
	}

	return array(
		'status'    => $status,
		'counts'    => $counts,
		'issues'    => $issues,
		'truncated' => $truncated,
	);
}
