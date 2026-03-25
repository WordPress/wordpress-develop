<?php
/**
 * Threat Detector - Real-time threat detection.
 *
 * @package WordPress
 * @subpackage AI_Security
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AI_Security;

/**
 * Threat Detector class.
 *
 * @since 7.1.0
 */
class Threat_Detector {

	/**
	 * Instance of this class.
	 *
	 * @since 7.1.0
	 * @var Threat_Detector|null
	 */
	private static ?Threat_Detector $instance = null;

	/**
	 * Failed login attempts tracking.
	 *
	 * @since 7.1.0
	 * @var array
	 */
	private array $failed_logins = array();

	/**
	 * Request rate tracking.
	 *
	 * @since 7.1.0
	 * @var array
	 */
	private array $request_rates = array();

	/**
	 * Known attack patterns.
	 *
	 * @since 7.1.0
	 * @var array
	 */
	private array $patterns = array(
		'sql_injection' => array(
			'pattern' => '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION)\b)|(--)|(\/\*)|(\*\/)|(\%27)|(\%22)/i',
			'severity' => 'high',
		),
		'xss' => array(
			'pattern' => '/(<script|<iframe|javascript:|on\w+\s*=|<object|<embed)/i',
			'severity' => 'high',
		),
		'command_injection' => array(
			'pattern' => '/(\||;|`|\$\(|&&|\|\|)/',
			'severity' => 'critical',
		),
		'path_traversal' => array(
			'pattern' => '/(\.\.\/|\.\.\\)/',
			'severity' => 'high',
		),
	);

	/**
	 * Get instance.
	 *
	 * @since 7.1.0
	 * @return Threat_Detector
	 */
	public static function get_instance(): Threat_Detector {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 7.1.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 7.1.0
	 */
	private function init_hooks(): void {
		// Check requests at init priority 1
		add_action( 'init', array( $this, 'check_request' ), 1 );

		// Track failed logins
		add_action( 'wp_login_failed', array( $this, 'track_failed_login' ) );

		// Clean old entries periodically
		if ( wp_next_scheduled( 'ai_security_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'ai_security_cleanup' );
		}
		add_action( 'ai_security_cleanup', array( $this, 'cleanup' ) );
	}

	/**
	 * Check incoming request for threats.
	 *
	 * @since 7.1.0
	 */
	public function check_request(): void {
		// Skip admin requests
		if ( is_admin() ) {
			return;
		}

		$request = $_REQUEST;

		// Pattern-based detection
		foreach ( $this->patterns as $name => $pattern ) {
			foreach ( $request as $key => $value ) {
				if ( is_string( $value ) && preg_match( $pattern['pattern'], $value ) ) {
					$this->handle_threat( $name, $pattern['severity'], 'Pattern detected: ' . $name );
				}
			}
		}

		// Rate limiting check
		if ( ! $this->check_rate_limit() ) {
			$this->handle_threat( 'rate_limit', 'medium', 'Rate limit exceeded' );
		}

		// AI-based analysis (optional, more expensive)
		$this->analyze_with_ai( $request );
	}

	/**
	 * Track failed login attempt.
	 *
	 * @since 7.1.0
	 * @param string $username Username that failed.
	 */
	public function track_failed_login( string $username ): void {
		$ip = $this->get_client_ip();

		if ( ! isset( $this->failed_logins[ $ip ] ) ) {
			$this->failed_logins[ $ip ] = array();
		}

		$this->failed_logins[ $ip ][] = time();

		// Block after threshold
		if ( count( $this->failed_logins[ $ip ] ) > 5 ) {
			$this->handle_threat( 'brute_force', 'high', 'Too many failed login attempts' );
		}
	}

	/**
	 * Check rate limit.
	 *
	 * @since 7.1.0
	 * @return bool
	 */
	private function check_rate_limit(): bool {
		$ip = $this->get_client_ip();
		$now = time();

		if ( ! isset( $this->request_rates[ $ip ] ) ) {
			$this->request_rates[ $ip ] = array();
		}

		// Remove entries older than 60 seconds
		$this->request_rates[ $ip ] = array_filter(
			$this->request_rates[ $ip ],
			function ( $time ) use ( $now ) {
				return ( $now - $time ) < 60;
			}
		);

		// Allow max 60 requests per minute
		if ( count( $this->request_rates[ $ip ] ) > 60 ) {
			return false;
		}

		$this->request_rates[ $ip ][] = $now;
		return true;
	}

	/**
	 * Analyze request with AI.
	 *
	 * @since 7.1.0
	 * @param array $request Request data.
	 */
	private function analyze_with_ai( array $request ): void {
		$client = Client::get_instance();
		$analysis = $client->analyze_request( $request );

		if ( $analysis && isset( $analysis['is_threat'] ) && $analysis['is_threat'] ) {
			$this->handle_threat(
				$analysis['threat_type'] ?? 'ai_detected',
				$analysis['confidence'] > 80 ? 'high' : 'medium',
				$analysis['details'] ?? 'AI detected potential threat'
			);
		}
	}

	/**
	 * Handle detected threat.
	 *
	 * @since 7.1.0
	 * @param string $type Threat type.
	 * @param string $severity Severity level.
	 * @param string $details Details.
	 */
	private function handle_threat( string $type, string $severity, string $details ): void {
		$logger = Audit_Logger::get_instance();
		$logger->log( 'threat_detected', $severity, $type . ': ' . $details );

		// Block critical threats
		if ( 'critical' === $severity ) {
			$this->block_ip( $this->get_client_ip(), $type );
		}

		/**
		 * Fire action for other plugins to respond.
		 *
		 * @since 7.1.0
		 * @param string $type Threat type.
		 * @param string $severity Severity.
		 * @param string $details Details.
		 */
		do_action( 'ai_security_threat_detected', $type, $severity, $details );
	}

	/**
	 * Block an IP address.
	 *
	 * @since 7.1.0
	 * @param string $ip IP address.
	 * @param string $reason Reason for blocking.
	 */
	private function block_ip( string $ip, string $reason ): void {
		// Store blocked IPs
		$blocked = get_option( 'ai_security_blocked_ips', array() );
		$blocked[ $ip ] = array(
			'reason'   => $reason,
			'blocked_at' => time(),
		);
		update_option( 'ai_security_blocked_ips', $blocked );

		// Log the block
		$logger = Audit_Logger::get_instance();
		$logger->log( 'ip_blocked', 'critical', 'Blocked: ' . $ip . ' - ' . $reason );

		// Send 403 Forbidden
		status_header( 403 );
		wp_die( 'Access blocked by AI Security' );
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
	 * Cleanup old data.
	 *
	 * @since 7.1.0
	 */
	public function cleanup(): void {
		$now = time();

		// Clean failed logins older than 1 hour
		foreach ( $this->failed_logins as $ip => $times ) {
			$this->failed_logins[ $ip ] = array_filter(
				$times,
				function ( $time ) use ( $now ) {
					return ( $now - $time ) < 3600;
				}
			);
		}

		// Clean old blocked IPs (after 24 hours)
		$blocked = get_option( 'ai_security_blocked_ips', array() );
		foreach ( $blocked as $ip => $data ) {
			if ( isset( $data['blocked_at'] ) && ( $now - $data['blocked_at'] ) > 86400 ) {
				unset( $blocked[ $ip ] );
			}
		}
		update_option( 'ai_security_blocked_ips', $blocked );
	}
}