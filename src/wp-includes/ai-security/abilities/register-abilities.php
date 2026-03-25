<?php
/**
 * Security Abilities - Register AI-callable security functions.
 *
 * @package WordPress
 * @subpackage AI_Security
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AI_Security\Abilities;

/**
 * Register security abilities with WordPress Abilities API.
 *
 * @since 7.1.0
 */
function register_abilities(): void {
	// Scan plugin for vulnerabilities
	wp_register_ability(
		'security/scan-plugin',
		array(
			'label'               => __( 'Scan Plugin for Vulnerabilities', 'ai-security' ),
			'description'        => __( 'Analyze a plugin directory for security vulnerabilities using AI.', 'ai-security' ),
			'category'            => 'security',
			'execute_callback'    => __NAMESPACE__ . '\\scan_plugin_ability',
			'permission_callback' => 'current_user_can_manage_options',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// Scan theme for vulnerabilities
	wp_register_ability(
		'security/scan-theme',
		array(
			'label'               => __( 'Scan Theme for Vulnerabilities', 'ai-security' ),
			'description'        => __( 'Analyze a theme directory for security vulnerabilities using AI.', 'ai-security' ),
			'category'            => 'security',
			'execute_callback'    => __NAMESPACE__ . '\\scan_theme_ability',
			'permission_callback' => 'current_user_can_manage_options',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// Get security status
	wp_register_ability(
		'security/get-status',
		array(
			'label'               => __( 'Get Security Status', 'ai-security' ),
			'description'        => __( 'Get current AI Security status, including connection state and threat levels.', 'ai-security' ),
			'category'            => 'security',
			'execute_callback'    => __NAMESPACE__ . '\\get_status_ability',
			'permission_callback' => 'current_user_can_manage_options',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// Get recent threats
	wp_register_ability(
		'security/get-threats',
		array(
			'label'               => __( 'Get Recent Threats', 'ai-security' ),
			'description'        => __( 'Get list of recent security threats and events.', 'ai-security' ),
			'category'            => 'security',
			'execute_callback'    => __NAMESPACE__ . '\\get_threats_ability',
			'permission_callback' => 'current_user_can_manage_options',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// Block IP
	wp_register_ability(
		'security/block-ip',
		array(
			'label'               => __( 'Block IP Address', 'ai-security' ),
			'description'        => __( 'Block a specific IP address from accessing the site.', 'ai-security' ),
			'category'            => 'security',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'ip' => array(
						'type'        => 'string',
						'description' => __( 'IP address to block', 'ai-security' ),
					),
					'reason' => array(
						'type'        => 'string',
						'description' => __( 'Reason for blocking', 'ai-security' ),
					),
				),
				'required'   => array( 'ip' ),
			),
			'execute_callback'    => __NAMESPACE__ . '\\block_ip_ability',
			'permission_callback' => 'current_user_can_manage_options',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_abilities', 20 );

/**
 * Scan plugin ability callback.
 *
 * @since 7.1.0
 * @param array $input Input parameters.
 * @return array
 */
function scan_plugin_ability( array $input = array() ): array {
	$plugin_slug = $input['plugin'] ?? '';

	if ( empty( $plugin_slug ) ) {
		return array(
			'success' => false,
			'error'   => 'Plugin slug is required',
		);
	}

	$analyzer = \WordPress\AI_Security\Analyzer::get_instance();
	$result   = $analyzer->scan_plugin( $plugin_slug );

	return array(
		'success'      => true,
		'plugin'       => $plugin_slug,
		'files_scanned' => $result['files_scanned'] ?? 0,
		'findings'     => $result['findings'] ?? array(),
		'status'       => $result['status'] ?? 'unknown',
	);
}

/**
 * Scan theme ability callback.
 *
 * @since 7.1.0
 * @param array $input Input parameters.
 * @return array
 */
function scan_theme_ability( array $input = array() ): array {
	$theme_slug = $input['theme'] ?? '';

	if ( empty( $theme_slug ) ) {
		return array(
			'success' => false,
			'error'   => 'Theme slug is required',
		);
	}

	$themes   = wp_get_themes();
	$theme    = $themes[ $theme_slug ] ?? null;

	if ( ! $theme ) {
		return array(
			'success' => false,
			'error'   => 'Theme not found',
		);
	}

	$analyzer = \WordPress\AI_Security\Analyzer::get_instance();
	$result   = $analyzer->scan_theme( $theme );

	return array(
		'success'      => true,
		'theme'        => $theme_slug,
		'files_scanned' => $result['files_scanned'] ?? 0,
		'findings'     => $result['findings'] ?? array(),
		'status'       => $result['status'] ?? 'unknown',
	);
}

/**
 * Get security status ability callback.
 *
 * @since 7.1.0
 * @return array
 */
function get_status_ability(): array {
	$client = \WordPress\AI_Security\Client::get_instance();
	$status = $client->get_status();

	$logger   = \WordPress\AI_Security\Audit_Logger::get_instance();
	$critical = $logger->get_logs_by_severity( 'critical', 10 );
	$high     = $logger->get_logs_by_severity( 'high', 10 );

	return array(
		'success'           => true,
		'ai_connected'      => $status['connected'],
		'ai_support'       => $status['ai_support'],
		'providers_count'   => $status['providers_count'],
		'critical_threats'  => count( $critical ),
		'high_threats'     => count( $high ),
		'blocked_ips'       => count( get_option( 'ai_security_blocked_ips', array() ) ),
	);
}

/**
 * Get recent threats ability callback.
 *
 * @since 7.1.0
 * @return array
 */
function get_threats_ability(): array {
	$logger = \WordPress\AI_Security\Audit_Logger::get_instance();
	$logs   = $logger->get_recent_logs( 20 );

	$threats = array_filter(
		$logs,
		function ( $log ) {
			return in_array( $log['severity'], array( 'critical', 'high', 'medium' ), true );
		}
	);

	return array(
		'success' => true,
		'count'   => count( $threats ),
		'threats' => array_values( $threats ),
	);
}

/**
 * Block IP ability callback.
 *
 * @since 7.1.0
 * @param array $input Input parameters.
 * @return array
 */
function block_ip_ability( array $input = array() ): array {
	$ip     = $input['ip'] ?? '';
	$reason = $input['reason'] ?? 'Blocked via AI ability';

	if ( empty( $ip ) ) {
		return array(
			'success' => false,
			'error'   => 'IP address is required',
		);
	}

	$firewall = \WordPress\AI_Security\Firewall::get_instance();
	$result   = $firewall->block_ip( $ip, $reason );

	return array(
		'success' => $result,
		'ip'     => $ip,
		'message' => $result ? 'IP blocked successfully' : 'Failed to block IP',
	);
}