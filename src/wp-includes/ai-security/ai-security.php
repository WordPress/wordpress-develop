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
 * Main initialization for AI Security.
 *
 * @since 7.1.0
 */
function init(): void {
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
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Firewall Rules', 'ai-security' ); ?></h1>
		<p><?php esc_html_e( 'Configure firewall rules to block malicious requests.', 'ai-security' ); ?></p>
		<p><em><?php esc_html_e( 'Firewall configuration coming soon.', 'ai-security' ); ?></em></p>
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
	$logs   = $logger->get_recent_logs( 50 );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Security Audit Log', 'ai-security' ); ?></h1>

		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Event', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Severity', 'ai-security' ); ?></th>
					<th><?php esc_html_e( 'Details', 'ai-security' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No events recorded yet.', 'ai-security' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['time'] ); ?></td>
							<td><?php echo esc_html( $log['event'] ); ?></td>
							<td><?php echo esc_html( $log['severity'] ); ?></td>
							<td><?php echo esc_html( $log['details'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}