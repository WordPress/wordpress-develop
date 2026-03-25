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
}