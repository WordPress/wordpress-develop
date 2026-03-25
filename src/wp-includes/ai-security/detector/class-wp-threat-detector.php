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
 * Real-time threat detection with pattern matching, AI analysis,
 * auto-blocking, and notification system.
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
			'pattern'  => '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|ALTER|CREATE|TRUNCATE)\b)|(--)|(\/\*)|(\*\/)|(\%27)|(\%22)|(\';)|(\"))/i',
			'severity' => 'high',
			'name'     => 'SQL Injection',
		),
		'xss' => array(
			'pattern'  => '/(<script|<iframe|javascript:|on\w+\s*=|<object|<embed|<svg|<img[^>]+src|<link|<meta)/i',
			'severity' => 'high',
			'name'     => 'Cross-Site Scripting (XSS)',
		),
		'command_injection' => array(
			'pattern'  => '/(\||;|`|\$\(|&&|\|\||\\\\r|\\\\n|\\\\t)/',
			'severity' => 'critical',
			'name'     => 'Command Injection',
		),
		'path_traversal' => array(
			'pattern'  => '/(\.\.\/|\.\.\\\\|%2e%2e%2f|%2e%2e\/|\.\.%00)/i',
			'severity' => 'high',
			'name'     => 'Path Traversal',
		),
		'xmlrpc' => array(
			'pattern'  => '/(system\.multicall|wp\.getUsersBlogs|pingback\.ping)/i',
			'severity' => 'medium',
			'name'     => 'XML-RPC Attack',
		),
		'csrf' => array(
			'pattern'  => '/(<form[^>]*action=|<input[^>]*name=[\'"]_wpnonce)/i',
			'severity' => 'low',
			'name'     => 'Potential CSRF',
		),
		'ssrf' => array(
			'pattern'  => '/(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\]|metadata\.google)/i',
			'severity' => 'medium',
			'name'     => 'Server-Side Request Forgery (SSRF)',
		),
		'ldap_injection' => array(
			'pattern'  => '/(\*\)|%28|%29|\(\||\(&|\)\(|\)\(|\(\))/i',
			'severity' => 'medium',
			'name'     => 'LDAP Injection',
		),
	);

	/**
	 * Blocked IPs cache.
	 *
	 * @since 7.1.0
	 * @var array
	 */
	private array $blocked_ips = array();

	/**
	 * Notification sent timestamps (to prevent spam).
	 *
	 * @since 7.1.0
	 * @var array
	 */
	private array $last_notification = array();

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
		$this->load_blocked_ips();
		$this->init_hooks();
	}

	/**
	 * Load blocked IPs from database.
	 *
	 * @since 7.1.0
	 */
	private function load_blocked_ips(): void {
		$this->blocked_ips = get_option( 'ai_security_blocked_ips', array() );
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

		// Schedule cleanup
		if ( ! wp_next_scheduled( 'ai_security_cleanup' ) ) {
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
		if ( is_admin() || wp_doing_cron() ) {
			return;
		}

		// Check if IP is blocked
		$ip = $this->get_client_ip();
		if ( $this->is_ip_blocked( $ip ) ) {
			$this->handle_blocked_ip( $ip );
		}

		$request = $_REQUEST;

		// Skip empty requests
		if ( empty( $request ) ) {
			return;
		}

		// Pattern-based detection
		foreach ( $this->patterns as $name => $pattern ) {
			foreach ( $request as $key => $value ) {
				if ( is_string( $value ) && $this->matches_pattern( $value, $pattern['pattern'] ) ) {
					$this->handle_threat(
						$name,
						$pattern['name'],
						$pattern['severity'],
						'Pattern detected in parameter: ' . sanitize_text_field( $key )
					);
				}
			}
		}

		// Rate limiting check
		if ( ! $this->check_rate_limit() ) {
			$this->handle_threat(
				'rate_limit_exceeded',
				'Rate Limit Exceeded',
				'high',
				'Too many requests from IP'
			);
		}

		// AI-based analysis for suspicious requests
		if ( $this->should_use_ai_analysis( $request ) ) {
			$this->analyze_with_ai( $request );
		}
	}

	/**
	 * Check if string matches pattern.
	 *
	 * @since 7.1.0
	 * @param string $value Value to check.
	 * @param string $pattern Regex pattern.
	 * @return bool
	 */
	private function matches_pattern( string $value, string $pattern ): bool {
		// Skip very long strings (likely false positives)
		if ( strlen( $value ) > 5000 ) {
			return false;
		}

		return preg_match( $pattern, $value ) === 1;
	}

	/**
	 * Determine if request should be analyzed with AI.
	 *
	 * @since 7.1.0
	 * @param array $request Request data.
	 * @return bool
	 */
	private function should_use_ai_analysis( array $request ): bool {
		// Only analyze if AI is available
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		// Check for suspicious indicators but not clear patterns
		$suspicious_indicators = array(
			'eval', 'base64', 'shell_exec', 'system', 'passthru',
			'file_get_contents', 'file_put_contents', 'fopen', 'fwrite',
			'../', '..\\', '/etc/', 'C:\\', 'concat', 'char',
		);

		foreach ( $request as $key => $value ) {
			if ( is_string( $value ) ) {
				$value_lower = strtolower( $value );
				foreach ( $suspicious_indicators as $indicator ) {
					if ( strpos( $value_lower, $indicator ) !== false ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Track failed login attempt.
	 *
	 * @since 7.1.0
	 * @param string $username Username that failed.
	 */
	public function track_failed_login( string $username ): void {
		$ip = $this->get_client_ip();
		$now = time();

		if ( ! isset( $this->failed_logins[ $ip ] ) ) {
			$this->failed_logins[ $ip ] = array();
		}

		// Clean old attempts (older than 1 hour)
		$this->failed_logins[ $ip ] = array_filter(
			$this->failed_logins[ $ip ],
			function ( $time ) use ( $now ) {
				return ( $now - $time ) < 3600;
			}
		);

		$this->failed_logins[ $ip ][] = $now;

		// Block after threshold
		$threshold = apply_filters( 'ai_security_failed_login_threshold', 5 );
		if ( count( $this->failed_logins[ $ip ] ) > $threshold ) {
			$this->handle_threat(
				'brute_force',
				'Brute Force Attack',
				'high',
				'Too many failed login attempts: ' . count( $this->failed_logins[ $ip ] )
			);
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

		// Allow max 60 requests per minute (configurable)
		$limit = apply_filters( 'ai_security_rate_limit', 60 );
		if ( count( $this->request_rates[ $ip ] ) > $limit ) {
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

		// Create summary of request (sanitized)
		$summary = array(
			'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
			'uri'    => $_SERVER['REQUEST_URI'] ?? '',
			'params' => array(),
		);

		// Add non-sensitive params
		foreach ( $request as $key => $value ) {
			if ( is_string( $value ) && strlen( $value ) < 200 ) {
				$summary['params'][ $key ] = $value;
			}
		}

		$analysis = $client->analyze_request( $summary );

		if ( $analysis && isset( $analysis['is_threat'] ) && $analysis['is_threat'] ) {
			$confidence = $analysis['confidence'] ?? 0;
			$severity   = $confidence > 80 ? 'high' : ( $confidence > 50 ? 'medium' : 'low' );

			$this->handle_threat(
				$analysis['threat_type'] ?? 'ai_detected',
				'AI-Detected Threat',
				$severity,
				$analysis['details'] ?? 'AI analysis flagged potential threat'
			);
		}
	}

	/**
	 * Handle detected threat.
	 *
	 * @since 7.1.0
	 * @param string $type Threat type.
	 * @param string $name Display name.
	 * @param string $severity Severity level.
	 * @param string $details Details.
	 */
	private function handle_threat( string $type, string $name, string $severity, string $details ): void {
		$ip = $this->get_client_ip();

		// Log the threat
		$logger = Audit_Logger::get_instance();
		$logger->log(
			'threat_detected',
			$severity,
			$type . ': ' . $details,
			array(
				'ip'       => $ip,
				'threat_type' => $type,
				'threat_name' => $name,
			)
		);

		// Block critical and high severity threats
		if ( in_array( $severity, array( 'critical', 'high' ), true ) ) {
			$this->block_ip( $ip, $type . ' - ' . $name );
			$this->send_notification( $type, $name, $severity, $details, $ip );
		} else {
			// Log but don't block medium/low
			$this->send_notification( $type, $name, $severity, $details, $ip );
		}

		/**
		 * Fire action for other plugins/themes to respond.
		 *
		 * @since 7.1.0
		 *
		 * @param string $type Threat type.
		 * @param string $name Threat name.
		 * @param string $severity Severity.
		 * @param string $details Details.
		 * @param string $ip Client IP.
		 */
		do_action( 'ai_security_threat_detected', $type, $name, $severity, $details, $ip );
	}

	/**
	 * Block an IP address.
	 *
	 * @since 7.1.0
	 * @param string $ip IP address.
	 * @param string $reason Reason for blocking.
	 */
	public function block_ip( string $ip, string $reason ): void {
		// Add to blocked IPs
		$this->blocked_ips[ $ip ] = array(
			'reason'     => $reason,
			'blocked_at' => time(),
			'automated'  => true,
		);

		update_option( 'ai_security_blocked_ips', $this->blocked_ips );

		// Log the block
		$logger = Audit_Logger::get_instance();
		$logger->log( 'ip_blocked', 'critical', 'Blocked: ' . $ip . ' - ' . $reason );

		/**
		 * Fire action when IP is blocked.
		 *
		 * @since 7.1.0
		 *
		 * @param string $ip Blocked IP.
		 * @param string $reason Reason.
		 */
		do_action( 'ai_security_ip_blocked', $ip, $reason );
	}

	/**
	 * Check if IP is blocked.
	 *
	 * @since 7.1.0
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function is_ip_blocked( string $ip ): bool {
		return isset( $this->blocked_ips[ $ip ] );
	}

	/**
	 * Handle blocked IP request.
	 *
	 * @since 7.1.0
	 * @param string $ip IP address.
	 */
	private function handle_blocked_ip( string $ip ): void {
		$reason = $this->blocked_ips[ $ip ]['reason'] ?? 'Blocked';

		$logger = Audit_Logger::get_instance();
		$logger->log( 'blocked_ip_request', 'medium', 'Blocked IP attempted access: ' . $ip );

		status_header( 403 );
		wp_die(
			esc_html__( 'Access blocked by AI Security. Contact administrator if this is an error.', 'ai-security' ),
			esc_html__( 'Access Blocked', 'ai-security' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @since 7.1.0
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip = '';

		// Check for proxies
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = $_SERVER['HTTP_X_REAL_IP'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		// Take first IP if multiple
		$ip = explode( ',', $ip )[0];
		$ip = trim( $ip );

		return $ip;
	}

	/**
	 * Send threat notification.
	 *
	 * @since 7.1.0
	 * @param string $type Threat type.
	 * @param string $name Threat name.
	 * @param string $severity Severity.
	 * @param string $details Details.
	 * @param string $ip Client IP.
	 */
	private function send_notification( string $type, string $name, string $severity, string $details, string $ip ): void {
		// Check if notifications are enabled
		$enabled = get_option( 'ai_security_notifications_enabled', true );
		if ( ! $enabled ) {
			return;
		}

		// Rate limit notifications (max 1 per hour per type)
		$notification_key = $type . '_' . $severity;
		$now               = time();
		$cooldown          = 3600; // 1 hour

		if ( isset( $this->last_notification[ $notification_key ] ) ) {
			if ( ( $now - $this->last_notification[ $notification_key ] ) < $cooldown ) {
				return;
			}
		}
		$this->last_notification[ $notification_key ] = $now;

		// Email notification
		$this->send_email_notification( $type, $name, $severity, $details, $ip );

		// Webhook notification
		$this->send_webhook_notification( $type, $name, $severity, $details, $ip );
	}

	/**
	 * Send email notification.
	 *
	 * @since 7.1.0
	 * @param string $type Threat type.
	 * @param string $name Threat name.
	 * @param string $severity Severity.
	 * @param string $details Details.
	 * @param string $ip Client IP.
	 */
	private function send_email_notification( string $type, string $name, string $severity, string $details, string $ip ): void {
		$email = get_option( 'ai_security_notification_email', get_option( 'admin_email' ) );

		if ( empty( $email ) ) {
			return;
		}

		$subject = sprintf(
			'[%s] AI Security Alert: %s',
			get_bloginfo( 'name' ),
			$name
		);

		$message = sprintf(
			"AI Security Threat Detected\n" .
			"==========================\n\n" .
			"Type: %s\n" .
			"Severity: %s\n" .
			"Details: %s\n" .
			"IP Address: %s\n" .
			"Time: %s\n\n" .
			"URL: %s\n" .
			"User: %s\n",
			$type,
			ucfirst( $severity ),
			$details,
			$ip,
			current_time( 'mysql' ),
			esc_url( home_url( $_SERVER['REQUEST_URI'] ?? '' ) ),
			esc_html( wp_get_current_user()->user_login ?? 'Guest' )
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Send webhook notification.
	 *
	 * @since 7.1.0
	 * @param string $type Threat type.
	 * @param string $name Threat name.
	 * @param string $severity Severity.
	 * @param string $details Details.
	 * @param string $ip Client IP.
	 */
	private function send_webhook_notification( string $type, string $name, string $severity, string $details, string $ip ): void {
		$webhook_url = get_option( 'ai_security_webhook_url', '' );

		if ( empty( $webhook_url ) ) {
			return;
		}

		$payload = array(
			'type'      => 'security_threat',
			'site_url'  => site_url(),
			'threat'    => array(
				'type'      => $type,
				'name'      => $name,
				'severity'  => $severity,
				'details'   => $details,
				'ip'        => $ip,
				'time'      => current_time( 'mysql' ),
			),
		);

		// Send async request
		wp_remote_post(
			$webhook_url,
			array(
				'method'  => 'POST',
				'body'    => json_encode( $payload ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'blocking' => false,
			)
		);
	}

	/**
	 * Cleanup old data.
	 *
	 * @since 7.1.0
	 */
	public function cleanup(): void {
		$now = time();

		// Clean old failed logins
		foreach ( $this->failed_logins as $ip => $times ) {
			$this->failed_logins[ $ip ] = array_filter(
				$times,
				function ( $time ) use ( $now ) {
					return ( $now - $time ) < 3600;
				}
			);
		}

		// Clean old blocked IPs (after 24 hours, unless manual)
		$changed = false;
		foreach ( $this->blocked_ips as $ip => $data ) {
			if ( isset( $data['blocked_at'] ) && isset( $data['automated'] ) && $data['automated'] ) {
				if ( ( $now - $data['blocked_at'] ) > 86400 ) {
					unset( $this->blocked_ips[ $ip ] );
					$changed = true;
				}
			}
		}

		if ( $changed ) {
			update_option( 'ai_security_blocked_ips', $this->blocked_ips );
		}
	}

	/**
	 * Get threat statistics.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	public function get_stats(): array {
		$logger = Audit_Logger::get_instance();

		return array(
			'threats_today'    => count( $logger->get_logs_by_event( 'threat_detected', 100 ) ),
			'blocked_ips'     => count( $this->blocked_ips ),
			'failed_logins'   => array_sum( array_map( 'count', $this->failed_logins ) ),
			'patterns_count'  => count( $this->patterns ),
		);
	}
}