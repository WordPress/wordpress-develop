<?php
/**
 * Audit Logger - Security event logging.
 *
 * @package WordPress
 * @subpackage AI_Security
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AI_Security;

/**
 * Audit Logger class.
 *
 * @since 7.1.0
 */
class Audit_Logger {

	/**
	 * Instance of this class.
	 *
	 * @since 7.1.0
	 * @var Audit_Logger|null
	 */
	private static ?Audit_Logger $instance = null;

	/**
	 * Option name for logs.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	private const LOG_OPTION = 'ai_security_logs';

	/**
	 * Max logs to keep.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	private const MAX_LOGS = 1000;

	/**
	 * Get instance.
	 *
	 * @since 7.1.0
	 * @return Audit_Logger
	 */
	public static function get_instance(): Audit_Logger {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Log a security event.
	 *
	 * @since 7.1.0
	 * @param string $event Event type.
	 * @param string $severity Severity (critical, high, medium, low, info).
	 * @param string $details Event details.
	 * @param array $context Additional context.
	 */
	public function log( string $event, string $severity, string $details, array $context = array() ): void {
		$logs = $this->get_logs();

		$logs[] = array(
			'time'     => current_time( 'mysql' ),
			'event'    => $event,
			'severity' => $severity,
			'details'  => $details,
			'ip'       => $this->get_client_ip(),
			'user_id'  => get_current_user_id(),
			'context'  => $context,
		);

		// Trim old logs
		if ( count( $logs ) > self::MAX_LOGS ) {
			$logs = array_slice( $logs, -self::MAX_LOGS );
		}

		update_option( self::LOG_OPTION, $logs );
	}

	/**
	 * Get recent logs.
	 *
	 * @since 7.1.0
	 * @param int $limit Number of logs to return.
	 * @return array
	 */
	public function get_recent_logs( int $limit = 50 ): array {
		$logs = $this->get_logs();
		return array_slice( $logs, -$limit );
	}

	/**
	 * Get logs filtered by severity.
	 *
	 * @since 7.1.0
	 * @param string $severity Severity to filter.
	 * @param int $limit Number of logs.
	 * @return array
	 */
	public function get_logs_by_severity( string $severity, int $limit = 50 ): array {
		$logs = $this->get_logs();
		$filtered = array_filter(
			$logs,
			function ( $log ) use ( $severity ) {
				return isset( $log['severity'] ) && $log['severity'] === $severity;
			}
		);
		return array_slice( array_values( $filtered ), -$limit );
	}

	/**
	 * Get logs by event type.
	 *
	 * @since 7.1.0
	 * @param string $event Event type.
	 * @param int $limit Number of logs.
	 * @return array
	 */
	public function get_logs_by_event( string $event, int $limit = 50 ): array {
		$logs = $this->get_logs();
		$filtered = array_filter(
			$logs,
			function ( $log ) use ( $event ) {
				return isset( $log['event'] ) && $log['event'] === $event;
			}
		);
		return array_slice( array_values( $filtered ), -$limit );
	}

	/**
	 * Clear all logs.
	 *
	 * @since 7.1.0
	 */
	public function clear_logs(): void {
		delete_option( self::LOG_OPTION );
	}

	/**
	 * Export logs.
	 *
	 * @since 7.1.0
	 * @param string $format Format (json, csv).
	 * @return string
	 */
	public function export( string $format = 'json' ): string {
		$logs = $this->get_logs();

		if ( 'csv' === $format ) {
			$output = "Time,Event,Severity,Details,IP,User ID\n";
			foreach ( $logs as $log ) {
				$output .= sprintf(
					'"%s","%s","%s","%s","%s","%d"' . "\n",
					$log['time'],
					$log['event'],
					$log['severity'],
					str_replace( '"', '""', $log['details'] ),
					$log['ip'] ?? '',
					$log['user_id'] ?? 0
				);
			}
			return $output;
		}

		return json_encode( $logs, JSON_PRETTY_PRINT );
	}

	/**
	 * Get all logs.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	private function get_logs(): array {
		return get_option( self::LOG_OPTION, array() );
	}

	/**
	 * Get client IP address.
	 *
	 * @since 7.1.0
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = $_SERVER['HTTP_X_REAL_IP'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return explode( ',', $ip )[0];
	}

	/**
	 * Get log statistics.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	public function get_stats(): array {
		$logs = $this->get_logs();

		$stats = array(
			'total'   => count( $logs ),
			'critical' => 0,
			'high'    => 0,
			'medium'  => 0,
			'low'     => 0,
			'info'    => 0,
		);

		foreach ( $logs as $log ) {
			$sev = $log['severity'] ?? 'info';
			if ( isset( $stats[ $sev ] ) ) {
				$stats[ $sev ]++;
			} else {
				$stats['info']++;
			}
		}

		return $stats;
	}

	/**
	 * Generate PDF report.
	 *
	 * @since 7.1.0
	 * @param array $logs Logs to include.
	 * @return string HTML content for PDF.
	 */
	public function generate_report_html( array $logs ): string {
		$stats = $this->get_stats();
		$date  = current_time( 'mysql' );

		$html = '<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>Security Audit Report - ' . esc_html( $date ) . '</title>
<style>
	body { font-family: Arial, sans-serif; margin: 40px; }
	h1 { color: #333; }
	.stats { display: flex; gap: 20px; margin: 20px 0; }
	.stat-box { border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
	.stat-box.critical { border-color: red; }
	.stat-box.high { border-color: orange; }
	table { width: 100%; border-collapse: collapse; margin-top: 20px; }
	th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
	th { background: #f5f5f5; }
</style>
</head><body>
<h1>Security Audit Report</h1>
<p>Generated: ' . esc_html( $date ) . '</p>

<div class="stats">
	<div class="stat-box critical"><strong>Critical:</strong> ' . $stats['critical'] . '</div>
	<div class="stat-box high"><strong>High:</strong> ' . $stats['high'] . '</div>
	<div class="stat-box"><strong>Medium:</strong> ' . $stats['medium'] . '</div>
	<div class="stat-box"><strong>Low:</strong> ' . $stats['low'] . '</div>
	<div class="stat-box"><strong>Total:</strong> ' . $stats['total'] . '</div>
</div>

<h2>Recent Events</h2>
<table>
<tr><th>Time</th><th>Event</th><th>Severity</th><th>Details</th><th>IP</th></tr>
';

		foreach ( $logs as $log ) {
			$html .= '<tr>';
			$html .= '<td>' . esc_html( $log['time'] ) . '</td>';
			$html .= '<td>' . esc_html( $log['event'] ) . '</td>';
			$html .= '<td>' . esc_html( $log['severity'] ) . '</td>';
			$html .= '<td>' . esc_html( $log['details'] ) . '</td>';
			$html .= '<td>' . esc_html( $log['ip'] ?? '-' ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</table></body></html>';

		return $html;
	}

	/**
	 * Export as PDF (generates HTML that can be printed to PDF).
	 *
	 * @since 7.1.0
	 * @param int $limit Number of logs to include.
	 */
	public function export_pdf( int $limit = 100 ): void {
		$logs = $this->get_recent_logs( $limit );
		$html = $this->generate_report_html( $logs );

		header( 'Content-Type: text/html' );
		header( 'Content-Disposition: attachment; filename="security-report-' . date( 'Y-m-d' ) . '.html"' );
		echo $html;
		exit;
	}
}