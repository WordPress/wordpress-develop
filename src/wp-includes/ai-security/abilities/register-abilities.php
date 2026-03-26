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

/**
 * Unblock IP ability callback.
 *
 * @since 7.1.0
 * @param array $input Input parameters.
 * @return array
 */
function unblock_ip_ability( array $input = array() ): array {
	$ip = $input['ip'] ?? '';

	if ( empty( $ip ) ) {
		return array(
			'success' => false,
			'error'   => 'IP address is required',
		);
	}

	$firewall = \WordPress\AI_Security\Firewall::get_instance();
	$result   = $firewall->unblock_ip( $ip );

	return array(
		'success' => $result,
		'ip'     => $ip,
		'message' => $result ? 'IP unblocked successfully' : 'Failed to unblock IP',
	);
}

/**
 * Get firewall rules ability callback.
 *
 * @since 7.1.0
 * @return array
 */
function get_firewall_rules_ability(): array {
	$firewall = \WordPress\AI_Security\Firewall::get_instance();
	$rules    = $firewall->get_rules();
	$blocked  = $firewall->get_blocked_ips();
	$stats    = $firewall->get_stats();

	return array(
		'success'          => true,
		'rules_count'      => count( $rules ),
		'enabled_rules'    => $stats['enabled_rules'],
		'blocked_ips'      => count( $blocked ),
		'rules'           => $rules,
		'recently_blocked' => array_slice( $blocked, -10, true ),
	);
}

/**
 * Add firewall rule ability callback.
 *
 * @since 7.1.0
 * @param array $input Input parameters.
 * @return array
 */
function add_firewall_rule_ability( array $input = array() ): array {
	$pattern = $input['pattern'] ?? '';
	$action  = $input['action'] ?? 'block';

	if ( empty( $pattern ) ) {
		return array(
			'success' => false,
			'error'   => 'Pattern is required',
		);
	}

	if ( ! in_array( $action, array( 'block', 'log', 'challenge' ), true ) ) {
		return array(
			'success' => false,
			'error'   => 'Invalid action. Must be block, log, or challenge',
		);
	}

	$firewall = \WordPress\AI_Security\Firewall::get_instance();
	$result   = $firewall->add_rule(
		array(
			'pattern' => $pattern,
			'action'  => $action,
		)
	);

	return array(
		'success'  => $result,
		'pattern'  => $pattern,
		'action'   => $action,
		'message' => $result ? 'Rule added successfully' : 'Failed to add rule',
	);
}

/**
 * Get audit log summary ability callback.
 *
 * @since 7.1.0
 * @return array
 */
function get_audit_summary_ability(): array {
	$logger = \WordPress\AI_Security\Audit_Logger::get_instance();
	$stats  = $logger->get_stats();

	$recent_critical = $logger->get_logs_by_severity( 'critical', 5 );
	$recent_high     = $logger->get_logs_by_severity( 'high', 5 );

	// Group by event type
	$all_logs   = $logger->get_recent_logs( 500 );
	$eventTypes = array();
	foreach ( $all_logs as $log ) {
		$event           = $log['event'] ?? 'unknown';
		$eventTypes[ $event ] = ( $eventTypes[ $event ] ?? 0 ) + 1;
	}

	// Sort by count
	arsort( $eventTypes );

	return array(
		'success'           => true,
		'summary'          => $stats,
		'critical_events'  => $recent_critical,
		'high_events'      => $recent_high,
		'top_event_types'  => array_slice( $eventTypes, 0, 10, true ),
		'total_events'     => $stats['total'],
	);
}

/**
 * Generate security report ability callback.
 *
 * @since 7.1.0
 * @param array $input Input parameters.
 * @return array
 */
function generate_report_ability( array $input = array() ): array {
	$period = $input['period'] ?? 'weekly';
	$format = $input['format'] ?? 'summary';

	$logger   = \WordPress\AI_Security\Audit_Logger::get_instance();
	$firewall = \WordPress\AI_Security\Firewall::get_instance();

	$stats    = $logger->get_stats();
	$logs     = $logger->get_recent_logs( 100 );
	$blocked  = $firewall->get_blocked_ips();
	$rules    = $firewall->get_rules();

	$report = array(
		'generated_at' => current_time( 'mysql' ),
		'period'       => $period,
		'security_summary' => array(
			'total_events'   => $stats['total'],
			'critical'       => $stats['critical'],
			'high'           => $stats['high'],
			'medium'         => $stats['medium'],
			'low'            => $stats['low'],
			'blocked_ips'    => count( $blocked ),
			'firewall_rules' => count( $rules ),
		),
		'recent_threats' => array_filter(
			$logs,
			function ( $log ) {
				return in_array( $log['severity'], array( 'critical', 'high' ), true );
			}
		),
		'recommendations' => generate_recommendations( $stats, $blocked, $rules ),
	);

	if ( 'json' === $format ) {
		return array(
			'success' => true,
			'format'  => 'json',
			'report'  => json_encode( $report, JSON_PRETTY_PRINT ),
		);
	} elseif ( 'html' === $format ) {
		$html = $logger->generate_report_html( $logs );
		return array(
			'success' => true,
			'format'  => 'html',
			'html'    => $html,
		);
	}

	// Summary format
	return array(
		'success'   => true,
		'format'    => 'summary',
		'summary'   => $report['security_summary'],
		'top_threats' => array_slice( $report['recent_threats'], 0, 5 ),
		'recommendations' => $report['recommendations'],
	);
}

/**
 * Generate security recommendations based on data.
 *
 * @since 7.1.0
 * @param array $stats Statistics.
 * @param array $blocked Blocked IPs.
 * @param array $rules Firewall rules.
 * @return array
 */
function generate_recommendations( array $stats, array $blocked, array $rules ): array {
	$recommendations = array();

	if ( $stats['critical'] > 5 ) {
		$recommendations[] = 'High number of critical events. Consider reviewing recent threats immediately.';
	}

	if ( $stats['high'] > 20 ) {
		$recommendations[] = 'Elevated high-severity events detected. Review firewall rules and blocked IPs.';
	}

	if ( count( $blocked ) > 50 ) {
		$recommendations[] = 'Large number of blocked IPs detected. Consider enabling country blocking for known malicious regions.';
	}

	if ( count( $rules ) < 3 ) {
		$recommendations[] = 'Few firewall rules configured. Consider adding rules for common attack patterns (SQL injection, XSS).';
	}

	if ( empty( $recommendations ) ) {
		$recommendations[] = 'Security status looks good. Continue monitoring regularly.';
	}

	return $recommendations;
}

/**
 * Autonomous response ability callback.
 *
 * @since 7.1.0
 * @param array $input Input parameters.
 * @return array
 */
function autonomous_response_ability( array $input = array() ): array {
	$level = $input['action_level'] ?? 'analyze';

	$logger  = \WordPress\AI_Security\Audit_Logger::get_instance();
	$firewall = \WordPress\AI_Security\Firewall::get_instance();

	$recent_critical = $logger->get_logs_by_severity( 'critical', 20 );
	$recent_high    = $logger->get_logs_by_severity( 'high', 20 );

	$actions_taken = array();

	if ( 'analyze' === $level ) {
		// Just analyze and report
		return array(
			'success'      => true,
			'action_level' => 'analyze',
			'critical_count' => count( $recent_critical ),
			'high_count'    => count( $recent_high ),
			'analysis'      => 'Analysis complete. No actions taken (analyze mode).',
			'recommendations' => generate_recommendations(
				$logger->get_stats(),
				$firewall->get_blocked_ips(),
				$firewall->get_rules()
			),
		);
	}

	// Respond mode - block high-threat IPs
	if ( 'respond' === $level || 'full' === $level ) {
		// Extract IPs from recent critical/high threats and block them
		$ips_to_block = array();
		$threats      = array_merge( $recent_critical, $recent_high );

		foreach ( $threats as $threat ) {
			$ip = $threat['ip'] ?? '';
			if ( $ip && ! isset( $ips_to_block[ $ip ] ) ) {
				$ips_to_block[ $ip ] = $threat['severity'];
			}
		}

		// Block top 10 most threatening IPs
		$blocked_count = 0;
		foreach ( $ips_to_block as $ip => $severity ) {
			if ( $blocked_count >= 10 ) {
				break;
			}
			// Skip private IPs
			if ( preg_match( '/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.|127\.)/', $ip ) ) {
				continue;
			}
			$firewall->block_ip( $ip, 'Auto-blocked by AI: ' . $severity . ' threat' );
			$actions_taken[] = 'Blocked IP: ' . $ip . ' (' . $severity . ')';
			$blocked_count++;
		}
	}

	// Full mode - also add firewall rules
	if ( 'full' === $level ) {
		// Add common attack pattern rules
		$existing_rules = $firewall->get_rules();
		$has_sql_injection_rule = false;
		$has_xss_rule = false;

		foreach ( $existing_rules as $rule ) {
			if ( strpos( $rule['pattern'], 'union.*select' ) !== false ) {
				$has_sql_injection_rule = true;
			}
			if ( strpos( $rule['pattern'], 'script|' ) !== false ) {
				$has_xss_rule = true;
			}
		}

		if ( ! $has_sql_injection_rule ) {
			$firewall->add_rule(
				array(
					'pattern' => '(?i)(union.*select|union.*all.*select|--|\/\*|\*\/)',
					'action'  => 'block',
				)
			);
			$actions_taken[] = 'Added firewall rule: SQL injection patterns';
		}

		if ( ! $has_xss_rule ) {
			$firewall->add_rule(
				array(
					'pattern' => '(?i)(<script|javascript:|onerror=|onclick=)',
					'action'  => 'block',
				)
			);
			$actions_taken[] = 'Added firewall rule: XSS patterns';
		}

		$actions_taken[] = 'Full autonomous response completed';
	}

	return array(
		'success'       => true,
		'action_level'  => $level,
		'actions_taken' => $actions_taken,
		'summary'       => array(
			'critical_analyzed' => count( $recent_critical ),
			'high_analyzed'     => count( $recent_high ),
			'ips_blocked'      => count( $actions_taken ),
		),
	);
}

/**
 * Unblock IP ability.
 *
 * @since 7.1.0
 */
wp_register_ability(
	'security/unblock-ip',
	array(
		'label'               => __( 'Unblock IP Address', 'ai-security' ),
		'description'        => __( 'Unblock a previously blocked IP address.', 'ai-security' ),
		'category'            => 'security',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'ip' => array(
					'type'        => 'string',
					'description' => __( 'IP address to unblock', 'ai-security' ),
				),
			),
			'required'   => array( 'ip' ),
		),
		'execute_callback'    => __NAMESPACE__ . '\\unblock_ip_ability',
		'permission_callback' => 'current_user_can_manage_options',
		'meta'                => array(
			'annotations' => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			),
		),
	)
);

/**
 * Get firewall rules ability.
 *
 * @since 7.1.0
 */
wp_register_ability(
	'security/get-firewall-rules',
	array(
		'label'               => __( 'Get Firewall Rules', 'ai-security' ),
		'description'        => __( 'List all firewall rules and their status.', 'ai-security' ),
		'category'            => 'security',
		'execute_callback'    => __NAMESPACE__ . '\\get_firewall_rules_ability',
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

/**
 * Add firewall rule ability.
 *
 * @since 7.1.0
 */
wp_register_ability(
	'security/add-firewall-rule',
	array(
		'label'               => __( 'Add Firewall Rule', 'ai-security' ),
		'description'        => __( 'Add a new firewall rule to block or log requests matching a pattern.', 'ai-security' ),
		'category'            => 'security',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'pattern' => array(
					'type'        => 'string',
					'description' => __( 'Regex pattern to match', 'ai-security' ),
				),
				'action' => array(
					'type'        => 'string',
					'description' => __( 'Action: block, log, or challenge', 'ai-security' ),
					'enum'        => array( 'block', 'log', 'challenge' ),
				),
			),
			'required'   => array( 'pattern', 'action' ),
		),
		'execute_callback'    => __NAMESPACE__ . '\\add_firewall_rule_ability',
		'permission_callback' => 'current_user_can_manage_options',
		'meta'                => array(
			'annotations' => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			),
		),
	)
);

/**
 * Get audit log summary ability.
 *
 * @since 7.1.0
 */
wp_register_ability(
	'security/get-audit-summary',
	array(
		'label'               => __( 'Get Audit Log Summary', 'ai-security' ),
		'description'        => __( 'Get a summary of security events grouped by type and severity.', 'ai-security' ),
		'category'            => 'security',
		'execute_callback'    => __NAMESPACE__ . '\\get_audit_summary_ability',
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

/**
 * Generate security report ability.
 *
 * @since 7.1.0
 */
wp_register_ability(
	'security/generate-report',
	array(
		'label'               => __( 'Generate Security Report', 'ai-security' ),
		'description'        => __( 'Generate a security report covering the specified time period.', 'ai-security' ),
		'category'            => 'security',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'period' => array(
					'type'        => 'string',
					'description' => __( 'Time period: daily, weekly, or monthly', 'ai-security' ),
					'enum'        => array( 'daily', 'weekly', 'monthly' ),
				),
				'format' => array(
					'type'        => 'string',
					'description' => __( 'Format: json, html, or summary', 'ai-security' ),
					'enum'        => array( 'json', 'html', 'summary' ),
				),
			),
			'required'   => array( 'period' ),
		),
		'execute_callback'    => __NAMESPACE__ . '\\generate_report_ability',
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

/**
 * Autonomous threat response ability.
 *
 * @since 7.1.0
 */
wp_register_ability(
	'security/autonomous-response',
	array(
		'label'               => __( 'Execute Autonomous Response', 'ai-security' ),
		'description'        => __( 'Analyze recent threats and automatically take protective actions.', 'ai-security' ),
		'category'            => 'security',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'action_level' => array(
					'type'        => 'string',
					'description' => __( 'Response level: analyze, respond, or full', 'ai-security' ),
					'enum'        => array( 'analyze', 'respond', 'full' ),
				),
			),
			'required'   => array( 'action_level' ),
		),
		'execute_callback'    => __NAMESPACE__ . '\\autonomous_response_ability',
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