<?php
/**
 * AI Security - Main Entry Point.
 *
 * Integrates AI-powered security into WordPress core.
 *
 * @package WordPress
 * @subpackage AI_Security
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AI_Security;

/**
 * Path to AI Security directory.
 *
 * @since 7.1.0
 */
define( 'AI_SECURITY_PATH', __DIR__ . '/' );

/**
 * Main initialization for AI Security.
 *
 * @since 7.1.0
 */
function init(): void {
	// Check if security is enabled
	if ( ! get_option( 'ai_security_enabled', true ) ) {
		return;
	}

	// Load security modules
	require_once AI_SECURITY_PATH . 'class-wp-ai-security-client.php';
	require_once AI_SECURITY_PATH . 'class-wp-security-analyzer.php';
	require_once AI_SECURITY_PATH . 'class-wp-threat-detector.php';
	require_once AI_SECURITY_PATH . 'class-wp-firewall.php';
	require_once AI_SECURITY_PATH . 'class-wp-audit-logger.php';

	// Register security abilities
	require_once AI_SECURITY_PATH . 'abilities/register-abilities.php';

	// Initialize components
	$detector   = \WordPress\AI_Security\Threat_Detector::get_instance();
	$firewall   = \WordPress\AI_Security\Firewall::get_instance();
	$audit_log  = \WordPress\AI_Security\Audit_Logger::get_instance();

	// Register admin menu
	add_action( 'admin_menu', __NAMESPACE__ . '\\add_admin_menu' );

	// Schedule weekly security report
	if ( ! wp_next_scheduled( 'ai_security_weekly_report' ) ) {
		wp_schedule_event( time(), 'weekly', 'ai_security_weekly_report' );
	}
	add_action( 'ai_security_weekly_report', __NAMESPACE__ . '\\send_weekly_security_report' );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init', 1 );

/**
 * Get the path to the AI Security directory.
 *
 * @since 7.1.0
 * @return string
 */
function get_path(): string {
	return plugin_dir_path( __FILE__ );
}

/**
 * Add AI Security admin menu.
 *
 * @since 7.1.0
 */
function add_admin_menu(): void {
	add_menu_page(
		__( 'AI Security', 'ai-security' ),
		__( 'AI Security', 'ai-security' ),
		'manage_options',
		'ai-security',
		__NAMESPACE__ . '\\render_admin_page',
		'dashicons-shield',
		99
	);

	add_submenu_page(
		'ai-security',
		__( 'Dashboard', 'ai-security' ),
		__( 'Dashboard', 'ai-security' ),
		'manage_options',
		'ai-security',
		__NAMESPACE__ . '\\render_admin_page'
	);

	add_submenu_page(
		'ai-security',
		__( 'Scanner', 'ai-security' ),
		__( 'Scanner', 'ai-security' ),
		'manage_options',
		'ai-security-scanner',
		__NAMESPACE__ . '\\render_scanner_page'
	);

	add_submenu_page(
		'ai-security',
		__( 'Firewall', 'ai-security' ),
		__( 'Firewall', 'ai-security' ),
		'manage_options',
		'ai-security-firewall',
		__NAMESPACE__ . '\\render_firewall_page'
	);

	add_submenu_page(
		'ai-security',
		__( 'Audit Log', 'ai-security' ),
		__( 'Audit Log', 'ai-security' ),
		'manage_options',
		'ai-security-logs',
		__NAMESPACE__ . '\\render_logs_page'
	);

	add_submenu_page(
		'ai-security',
		__( 'Settings', 'ai-security' ),
		__( 'Settings', 'ai-security' ),
		'manage_options',
		'ai-security-settings',
		__NAMESPACE__ . '\\render_settings_page'
	);
}

/**
 * Render main admin page.
 *
 * @since 7.1.0
 */
function render_admin_page(): void {
	$status = \WordPress\AI_Security\Client::get_status();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI Security Dashboard', 'ai-security' ); ?></h1>

		<div class="card" style="max-width: 100%; margin-top: 20px;">
			<h2><?php esc_html_e( 'AI Security Status', 'ai-security' ); ?></h2>
			<table class="widefat">
				<tr>
					<th><?php esc_html_e( 'Component', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ai-security' ); ?></th>
				</tr>
				<tr>
					<td><?php esc_html_e( 'AI Client', 'ai-security' ); ?></td>
					<td>
						<?php if ( $status['connected'] ) : ?>
							<span style="color: green;">✓ <?php esc_html_e( 'Connected', 'ai-security' ); ?></span>
						<?php else : ?>
							<span style="color: red;">✗ <?php esc_html_e( 'Not configured', 'ai-security' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Threat Detection', 'ai-security' ); ?></td>
					<td><span style="color: green;">✓ <?php esc_html_e( 'Active', 'ai-security' ); ?></span></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Firewall', 'ai-security' ); ?></td>
					<td><span style="color: green;">✓ <?php esc_html_e( 'Active', 'ai-security' ); ?></span></td>
				</tr>
			</table>
		</div>

		<div class="card" style="max-width: 100%; margin-top: 20px;">
			<h2><?php esc_html_e( 'Quick Actions', 'ai-security' ); ?></h2>
			<p>
				<a href="?page=ai-security-scanner" class="button button-primary">
					<?php esc_html_e( 'Run Security Scan', 'ai-security' ); ?>
				</a>
				<a href="?page=ai-security-logs" class="button">
					<?php esc_html_e( 'View Audit Log', 'ai-security' ); ?>
				</a>
			</p>
		</div>
	</div>
	<?php
}

/**
 * Render scanner page.
 *
 * @since 7.1.0
 */
function render_scanner_page(): void {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Plugin/Theme Scanner', 'ai-security' ); ?></h1>
		<p><?php esc_html_e( 'Scan all installed plugins and themes for security vulnerabilities using AI.', 'ai-security' ); ?></p>

		<form method="post">
			<?php wp_nonce_field( 'ai_security_scan', 'ai_security_scan_nonce' ); ?>
			<input type="submit" name="run_scan" class="button button-primary" value="<?php esc_attr_e( 'Start Scan', 'ai-security' ); ?>">
		</form>

		<?php
		if ( isset( $_POST['run_scan'] ) && wp_verify_nonce( $_POST['ai_security_scan_nonce'], 'ai_security_scan' ) ) {
			$scanner = \WordPress\AI_Security\Analyzer::get_instance();
			$results = $scanner->scan_all_extensions();
			echo '<pre>' . esc_html( print_r( $results, true ) ) . '</pre>';
		}
		?>
	</div>
	<?php
}

/**
 * Render firewall page.
 *
 * @since 7.1.0
 */
function render_firewall_page(): void {
	$firewall = \WordPress\AI_Security\Firewall::get_instance();

	// Handle form submissions
	if ( isset( $_POST['ai_security_add_rule'] ) && check_admin_referer( 'ai_security_firewall' ) ) {
		$rule = array(
			'pattern' => sanitize_text_field( $_POST['rule_pattern'] ?? '' ),
			'action' => sanitize_text_field( $_POST['rule_action'] ?? 'block' ),
		);
		if ( ! empty( $rule['pattern'] ) ) {
			$firewall->add_rule( $rule );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Rule added.', 'ai-security' ) . '</p></div>';
		}
	}

	if ( isset( $_POST['ai_security_delete_rule'] ) && check_admin_referer( 'ai_security_firewall' ) ) {
		$rule_id = sanitize_text_field( $_POST['rule_id'] ?? '' );
		if ( $rule_id ) {
			$firewall->delete_rule( $rule_id );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Rule deleted.', 'ai-security' ) . '</p></div>';
		}
	}

	if ( isset( $_POST['ai_security_toggle_rule'] ) && check_admin_referer( 'ai_security_firewall' ) ) {
		$rule_id = sanitize_text_field( $_POST['rule_id'] ?? '' );
		$enabled = isset( $_POST['rule_enabled'] );
		if ( $rule_id ) {
			$firewall->toggle_rule( $rule_id, $enabled );
		}
	}

	// Block IP form
	if ( isset( $_POST['ai_security_block_ip'] ) && check_admin_referer( 'ai_security_firewall' ) ) {
		$ip = sanitize_text_field( $_POST['block_ip'] ?? '' );
		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$firewall->block_ip( $ip, 'Manual block from admin' );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'IP blocked.', 'ai-security' ) . '</p></div>';
		}
	}

	// Unblock IP
	if ( isset( $_POST['ai_security_unblock_ip'] ) && check_admin_referer( 'ai_security_firewall' ) ) {
		$ip = sanitize_text_field( $_POST['unblock_ip'] ?? '' );
		if ( $ip ) {
			$firewall->unblock_ip( $ip );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'IP unblocked.', 'ai-security' ) . '</p></div>';
		}
	}

	$rules       = $firewall->get_rules();
	$blocked_ips = $firewall->get_blocked_ips();
	$blocked_countries = $firewall->get_blocked_countries();

	// Handle country blocking form
	if ( isset( $_POST['ai_security_block_countries'] ) && check_admin_referer( 'ai_security_firewall' ) ) {
		$countries = sanitize_text_field( $_POST['blocked_countries'] ?? '' );
		$country_list = array_filter( array_map( 'trim', explode( ',', $countries ) ) );
		$firewall->set_blocked_countries( $country_list );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Country blocking updated.', 'ai-security' ) . '</p></div>';
		$blocked_countries = $firewall->get_blocked_countries();
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Firewall', 'ai-security' ); ?></h1>

		<?php $stats = $firewall->get_stats(); ?>
		<div class="card" style="max-width: 100%; margin-bottom: 20px;">
			<h2><?php esc_html_e( 'Statistics', 'ai-security' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Blocked IPs:', 'ai-security' ); ?></strong> <?php echo esc_html( $stats['blocked_ips'] ); ?> |
				<strong><?php esc_html_e( 'Rules:', 'ai-security' ); ?></strong> <?php echo esc_html( $stats['enabled_rules'] ); ?>/<?php echo esc_html( $stats['total_rules']); ?> |
				<strong><?php esc_html_e( 'Countries:', 'ai-security' ); ?></strong> <?php echo esc_html( $stats['blocked_countries'] ); ?>
			</p>
		</div>

		<h2><?php esc_html_e( 'Country Blocking', 'ai-security' ); ?></h2>
		<form method="post" style="margin-bottom: 30px;">
			<?php wp_nonce_field( 'ai_security_firewall' ); ?>
			<p>
				<input type="text" name="blocked_countries" value="<?php echo esc_attr( implode( ', ', $blocked_countries ) ); ?>" class="regular-text" placeholder="CN, RU, KR, IR">
				<input type="submit" name="ai_security_block_countries" class="button button-primary" value="<?php esc_attr_e( 'Update', 'ai-security' ); ?>">
			</p>
			<p class="description"><?php esc_html_e( 'Comma-separated country codes to block (e.g., CN, RU, IR). Uses ip-api.com for geolocation.', 'ai-security' ); ?></p>
		</form>

		<h2><?php esc_html_e( 'Add Rule', 'ai-security' ); ?></h2>
		<form method="post" style="margin-bottom: 30px;">
			<?php wp_nonce_field( 'ai_security_firewall' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="rule_pattern"><?php esc_html_e( 'Pattern (regex)', 'ai-security' ); ?></label></th>
					<td>
						<input type="text" name="rule_pattern" id="rule_pattern" class="regular-text" placeholder="(?i)(union.*select|eval\(|base64_decode)">
						<p class="description"><?php esc_html_e( 'Regular expression to match request URI', 'ai-security' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rule_action"><?php esc_html_e( 'Action', 'ai-security' ); ?></label></th>
					<td>
						<select name="rule_action" id="rule_action">
							<option value="block"><?php esc_html_e( 'Block', 'ai-security' ); ?></option>
							<option value="log"><?php esc_html_e( 'Log Only', 'ai-security' ); ?></option>
							<option value="challenge"><?php esc_html_e( 'Challenge', 'ai-security' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="ai_security_add_rule" class="button button-primary" value="<?php esc_attr_e( 'Add Rule', 'ai-security' ); ?>">
			</p>
		</form>

		<h2><?php esc_html_e( 'Firewall Rules', 'ai-security' ); ?></h2>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Enabled', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Pattern', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Action', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Created', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ai-security' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rules ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No rules defined.', 'ai-security' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rules as $rule ) : ?>
						<tr>
							<td>
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'ai_security_firewall' ); ?>
									<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['id'] ?? '' ); ?>">
									<input type="checkbox" name="rule_enabled" <?php checked( $rule['enabled'] ?? true ); ?> onchange="this.form.submit()">
									<input type="hidden" name="ai_security_toggle_rule" value="1">
								</form>
							</td>
							<td><code><?php echo esc_html( $rule['pattern'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $rule['action'] ?? 'block' ); ?></td>
							<td><?php echo esc_html( date( 'Y-m-d H:i', $rule['created'] ?? time() ) ); ?></td>
							<td>
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'ai_security_firewall' ); ?>
									<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['id'] ?? '' ); ?>">
									<input type="submit" class="button" value="<?php esc_attr_e( 'Delete', 'ai-security' ); ?>" onclick="return confirm('Delete this rule?');">
									<input type="hidden" name="ai_security_delete_rule" value="1">
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Blocked IPs', 'ai-security' ); ?></h2>
		<form method="post" style="margin-bottom: 20px;">
			<?php wp_nonce_field( 'ai_security_firewall' ); ?>
			<input type="text" name="block_ip" placeholder="192.168.1.1">
			<input type="submit" name="ai_security_block_ip" class="button button-primary" value="<?php esc_attr_e( 'Block IP', 'ai-security' ); ?>">
		</form>

		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'IP Address', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Blocked At', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ai-security' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $blocked_ips ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No IPs blocked.', 'ai-security' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $blocked_ips as $ip => $data ) : ?>
						<tr>
							<td><?php echo esc_html( $ip ); ?></td>
							<td><?php echo esc_html( $data['reason'] ?? '' ); ?></td>
							<td><?php echo esc_html( date( 'Y-m-d H:i', $data['blocked_at'] ?? time() ) ); ?></td>
							<td>
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'ai_security_firewall' ); ?>
									<input type="hidden" name="unblock_ip" value="<?php echo esc_attr( $ip ); ?>">
									<input type="submit" class="button" value="<?php esc_attr_e( 'Unblock', 'ai-security' ); ?>">
									<input type="hidden" name="ai_security_unblock_ip" value="1">
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Render logs page.
 *
 * @since 7.1.0
 */
function render_logs_page(): void {
	$logger = \WordPress\AI_Security\Audit_Logger::get_instance();

	// Handle filter/ pagination
	$page     = isset( $_GET['log_page'] ) ? max( 1, absint( $_GET['log_page'] ) ) : 1;
	$per_page = 50;
	$severity = isset( $_GET['severity'] ) ? sanitize_text_field( $_GET['severity'] ) : '';
	$event    = isset( $_GET['event_type'] ) ? sanitize_text_field( $_GET['event_type'] ) : '';
	$search   = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

	// Get filtered logs
	if ( $severity ) {
		$all_logs = $logger->get_logs_by_severity( $severity, 1000 );
	} elseif ( $event ) {
		$all_logs = $logger->get_logs_by_event( $event, 1000 );
	} else {
		$all_logs = $logger->get_recent_logs( 1000 );
	}

	// Apply search filter
	if ( $search ) {
		$all_logs = array_filter(
			$all_logs,
			function ( $log ) use ( $search ) {
				$search_lower = strtolower( $search );
				return str_contains( strtolower( $log['details'] ?? '' ), $search_lower )
					|| str_contains( strtolower( $log['event'] ?? '' ), $search_lower )
					|| str_contains( strtolower( $log['ip'] ?? '' ), $search_lower );
			}
		);
	}

	// Paginate
	$total_logs = count( $all_logs );
	$total_pages = ceil( $total_logs / $per_page );
	$offset     = ( $page - 1 ) * $per_page;
	$logs       = array_slice( $all_logs, $offset, $per_page );

	// Export handling
	if ( isset( $_POST['ai_security_export_logs'] ) && check_admin_referer( 'ai_security_logs' ) ) {
		$format = sanitize_text_field( $_POST['export_format'] ?? 'json' );
		$export_data = $logger->export( $format );

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="security-logs-' . date( 'Y-m-d' ) . '.' . $format . '"' );
		echo $export_data;
		exit;
	}

	// Clear logs
	if ( isset( $_POST['ai_security_clear_logs'] ) && check_admin_referer( 'ai_security_logs' ) ) {
		$logger->clear_logs();
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs cleared.', 'ai-security' ) . '</p></div>';
		$logs = array();
	}

	// Generate PDF report
	if ( isset( $_POST['ai_security_generate_report'] ) && check_admin_referer( 'ai_security_logs' ) ) {
		$logger->export_pdf( 100 );
	}

	// Get stats for summary
	$stats = $logger->get_stats();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Security Audit Log', 'ai-security' ); ?></h1>

		<!-- Summary Stats -->
		<div class="card" style="max-width: 100%; margin-bottom: 20px;">
			<h2><?php esc_html_e( 'Summary', 'ai-security' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Total Events:', 'ai-security' ); ?></strong> <?php echo esc_html( $stats['total'] ); ?> |
				<strong><?php esc_html_e( 'Critical:', 'ai-security' ); ?></strong> <span style="color: red;"><?php echo esc_html( $stats['critical'] ); ?></span> |
				<strong><?php esc_html_e( 'High:', 'ai-security' ); ?></strong> <span style="color: orange;"><?php echo esc_html( $stats['high'] ); ?></span> |
				<strong><?php esc_html_e( 'Medium:', 'ai-security' ); ?></strong> <span style="color: yellow;"><?php echo esc_html( $stats['medium'] ); ?></span> |
				<strong><?php esc_html_e( 'Low:', 'ai-security' ); ?></strong> <span style="color: green;"><?php echo esc_html( $stats['low'] ); ?></span>
			</p>
		</div>

		<!-- Filters -->
		<form method="get" style="margin-bottom: 20px;">
			<input type="hidden" name="page" value="ai-security-logs">
			<table class="form-table">
				<tr>
					<td>
						<input type="text" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search logs...', 'ai-security' ); ?>" class="regular-text">
						<select name="severity">
							<option value=""><?php esc_html_e( 'All Severities', 'ai-security' ); ?></option>
							<option value="critical" <?php selected( $severity, 'critical' ); ?>><?php esc_html_e( 'Critical', 'ai-security' ); ?></option>
							<option value="high" <?php selected( $severity, 'high' ); ?>><?php esc_html_e( 'High', 'ai-security' ); ?></option>
							<option value="medium" <?php selected( $severity, 'medium' ); ?>><?php esc_html_e( 'Medium', 'ai-security' ); ?></option>
							<option value="low" <?php selected( $severity, 'low' ); ?>><?php esc_html_e( 'Low', 'ai-security' ); ?></option>
						</select>
						<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'ai-security' ); ?>">
						<?php if ( $search || $severity ) : ?>
							<a href="?page=ai-security-logs" class="button"><?php esc_html_e( 'Clear', 'ai-security' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</form>

		<!-- Export/Clear -->
		<div style="margin-bottom: 20px;">
			<form method="post" style="display: inline;">
				<?php wp_nonce_field( 'ai_security_logs' ); ?>
				<select name="export_format">
					<option value="json">JSON</option>
					<option value="csv">CSV</option>
				</select>
				<input type="submit" name="ai_security_export_logs" class="button button-primary" value="<?php esc_attr_e( 'Export Logs', 'ai-security' ); ?>">
			</form>
			<form method="post" style="display: inline;">
				<?php wp_nonce_field( 'ai_security_logs' ); ?>
				<input type="submit" name="ai_security_generate_report" class="button" value="<?php esc_attr_e( 'Generate PDF Report', 'ai-security' ); ?>">
			</form>
			<form method="post" style="display: inline;" onsubmit="return confirm('Clear all logs?');">
				<?php wp_nonce_field( 'ai_security_logs' ); ?>
				<input type="submit" name="ai_security_clear_logs" class="button" value="<?php esc_attr_e( 'Clear Logs', 'ai-security' ); ?>">
			</form>
		</div>

		<!-- Logs Table -->
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Event', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Severity', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Details', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'IP', 'ai-security' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No events recorded yet.', 'ai-security' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['time'] ); ?></td>
							<td><?php echo esc_html( $log['event'] ); ?></td>
							<td>
								<?php
								$sev = $log['severity'] ?? 'info';
								$colors = array(
									'critical' => 'red',
									'high'     => 'orange',
									'medium'   => 'blue',
									'low'      => 'green',
									'info'     => 'gray',
								);
								$color = $colors[ $sev ] ?? 'gray';
								?>
								<span style="color: <?php echo esc_attr( $color ); ?>;"><?php echo esc_html( strtoupper( $sev ) ); ?></span>
							</td>
							<td><?php echo esc_html( $log['details'] ); ?></td>
							<td><?php echo esc_html( $log['ip'] ?? '-' ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<p>
				<?php
				for ( $i = 1; $i <= $total_pages; $i++ ) {
					$query_args = array( 'page' => 'ai-security-logs', 'log_page' => $i );
					if ( $severity ) {
						$query_args['severity'] = $severity;
					}
					if ( $search ) {
						$query_args['search'] = $search;
					}
					echo '<a href="' . esc_url( add_query_arg( $query_args ) ) . '"';
					if ( $i === $page ) {
						echo ' style="font-weight: bold;"';
					}
					echo '>' . $i . '</a> ';
				}
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}
/**
 * Render settings page.
 *
 * @since 7.1.0
 */
function render_settings_page(): void {
	// Save settings if form submitted
	if ( isset( $_POST['ai_security_save_settings'] ) && check_admin_referer( 'ai_security_settings' ) ) {
		update_option( 'ai_security_enabled', isset( $_POST['ai_security_enabled'] ) );
		update_option( 'ai_security_auto_block', isset( $_POST['ai_security_auto_block'] ) );
		update_option( 'ai_security_rate_limit', absint( $_POST['ai_security_rate_limit'] ?? 60 ) );
		update_option( 'ai_security_failed_login_threshold', absint( $_POST['ai_security_failed_login_threshold'] ?? 5 ) );
		update_option( 'ai_security_notifications_enabled', isset( $_POST['ai_security_notifications_enabled'] ) );
		update_option( 'ai_security_notification_email', sanitize_email( $_POST['ai_security_notification_email'] ?? '' ) );
		update_option( 'ai_security_webhook_url', esc_url_raw( $_POST['ai_security_webhook_url'] ?? '' ) );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'ai-security' ) . '</p></div>';
	}

	$settings = array(
		'enabled'                   => get_option( 'ai_security_enabled', true ),
		'auto_block'               => get_option( 'ai_security_auto_block', true ),
		'rate_limit'               => get_option( 'ai_security_rate_limit', 60 ),
		'failed_login_threshold'   => get_option( 'ai_security_failed_login_threshold', 5 ),
		'notifications_enabled'    => get_option( 'ai_security_notifications_enabled', true ),
		'notification_email'       => get_option( 'ai_security_notification_email', get_option( 'admin_email' ) ),
		'webhook_url'             => get_option( 'ai_security_webhook_url', '' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI Security Settings', 'ai-security' ); ?></h1>

		<form method="post">
			<?php wp_nonce_field( 'ai_security_settings' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable AI Security', 'ai-security' ); ?></th>
					<td>
						<input type="checkbox" name="ai_security_enabled" id="ai_security_enabled" <?php checked( $settings['enabled'] ); ?>>
						<label for="ai_security_enabled"><?php esc_html_e( 'Enable real-time threat detection', 'ai-security' ); ?></label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-Block', 'ai-security' ); ?></th>
					<td>
						<input type="checkbox" name="ai_security_auto_block" id="ai_security_auto_block" <?php checked( $settings['auto_block'] ); ?>>
						<label for="ai_security_auto_block"><?php esc_html_e( 'Automatically block IPs on critical/high threats', 'ai-security' ); ?></label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ai_security_rate_limit"><?php esc_html_e( 'Rate Limit', 'ai-security' ); ?></label></th>
					<td>
						<input type="number" name="ai_security_rate_limit" id="ai_security_rate_limit" value="<?php echo esc_attr( $settings['rate_limit'] ); ?>" class="small-text">
						<span><?php esc_html_e( 'requests per minute', 'ai-security' ); ?></span>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ai_security_failed_login_threshold"><?php esc_html_e( 'Failed Login Threshold', 'ai-security' ); ?></label></th>
					<td>
						<input type="number" name="ai_security_failed_login_threshold" id="ai_security_failed_login_threshold" value="<?php echo esc_attr( $settings['failed_login_threshold'] ); ?>" class="small-text">
						<span><?php esc_html_e( 'attempts before blocking', 'ai-security' ); ?></span>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Email Notifications', 'ai-security' ); ?></th>
					<td>
						<input type="checkbox" name="ai_security_notifications_enabled" id="ai_security_notifications_enabled" <?php checked( $settings['notifications_enabled'] ); ?>>
						<label for="ai_security_notifications_enabled"><?php esc_html_e( 'Send email alerts for threats', 'ai-security' ); ?></label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ai_security_notification_email"><?php esc_html_e( 'Notification Email', 'ai-security' ); ?></label></th>
					<td>
						<input type="email" name="ai_security_notification_email" id="ai_security_notification_email" value="<?php echo esc_attr( $settings['notification_email'] ); ?>" class="regular-text">
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ai_security_webhook_url"><?php esc_html_e( 'Webhook URL', 'ai-security' ); ?></label></th>
					<td>
						<input type="url" name="ai_security_webhook_url" id="ai_security_webhook_url" value="<?php echo esc_attr( $settings['webhook_url'] ); ?>" class="regular-text" placeholder="https://...">
						<p class="description"><?php esc_html_e( 'Send JSON notifications to this URL when threats are detected.', 'ai-security' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="ai_security_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'ai-security' ); ?>">
			</p>
		</form>
	</div>
	<?php
}

/**
 * Send weekly security report via email.
 *
 * @since 7.1.0
 */
function send_weekly_security_report(): void {
	if ( ! get_option( 'ai_security_notifications_enabled', true ) ) {
		return;
	}

	$logger   = \WordPress\AI_Security\Audit_Logger::get_instance();
	$firewall = \WordPress\AI_Security\Firewall::get_instance();

	$stats    = $logger->get_stats();
	$blocked  = $firewall->get_blocked_ips();
	$rules    = $firewall->get_rules();

	$to      = get_option( 'ai_security_notification_email', get_option( 'admin_email' ) );
	$subject = __( 'Weekly Security Report', 'ai-security' ) . ' - ' . get_bloginfo( 'name' );

	$body = sprintf(
		__(
			'Security Report for %s

Summary:
- Total Events: %d
- Critical: %d
- High: %d
- Medium: %d
- Low: %d
- Blocked IPs: %d
- Firewall Rules: %d

Log in to your WordPress admin to view full details.

---
AI Security powered by WordPress AI Security Edition',
			'ai-security'
		),
		get_bloginfo( 'name' ),
		$stats['total'],
		$stats['critical'],
		$stats['high'],
		$stats['medium'],
		$stats['low'],
		count( $blocked ),
		count( $rules )
	);

	wp_mail( $to, $subject, $body );
}
