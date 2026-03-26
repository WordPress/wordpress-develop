<?php
/**
 * Firewall - Request filtering, rules, and rate limiting.
 *
 * @package WordPress
 * @subpackage AI_Security
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AI_Security;

/**
 * Firewall class.
 *
 * @since 7.1.0
 */
class Firewall {

	/**
	 * Instance of this class.
	 *
	 * @since 7.1.0
	 * @var Firewall|null
	 */
	private static ?Firewall $instance = null;

	/**
	 * Rate limit cache key prefix.
	 *
	 * @since 7.1.0
	 */
	private const RATE_LIMIT_PREFIX = 'ai_sec_ratelimit_';

	/**
	 * Get instance.
	 *
	 * @since 7.1.0
	 * @return Firewall
	 */
	public static function get_instance(): Firewall {
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
		// Check blocked IPs at init priority 0 (before anything else)
		add_action( 'init', array( $this, 'check_blocked_ips' ), 0 );

		// Rate limiting check
		add_action( 'init', array( $this, 'check_rate_limit' ), 1 );

		// Apply firewall rules
		add_action( 'init', array( $this, 'apply_firewall_rules' ), 2 );

		// Country blocking check
		add_action( 'init', array( $this, 'check_country_block' ), 3 );
	}

	/**
	 * Check if current IP is blocked.
	 *
	 * @since 7.1.0
	 */
	public function check_blocked_ips(): void {
		$blocked = get_option( 'ai_security_blocked_ips', array() );
		$ip      = $this->get_client_ip();

		if ( isset( $blocked[ $ip ] ) ) {
			status_header( 403 );
			wp_die( 'Your IP has been blocked by AI Security.' );
		}
	}

	/**
	 * Block an IP.
	 *
	 * @since 7.1.0
	 * @param string $ip IP address.
	 * @param string $reason Reason.
	 * @return bool
	 */
	public function block_ip( string $ip, string $reason = 'Manual block' ): bool {
		$blocked = get_option( 'ai_security_blocked_ips', array() );
		$blocked[ $ip ] = array(
			'reason'     => $reason,
			'blocked_at' => time(),
			'manual'     => true,
		);
		return update_option( 'ai_security_blocked_ips', $blocked );
	}

	/**
	 * Unblock an IP.
	 *
	 * @since 7.1.0
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function unblock_ip( string $ip ): bool {
		$blocked = get_option( 'ai_security_blocked_ips', array() );
		unset( $blocked[ $ip ] );
		return update_option( 'ai_security_blocked_ips', $blocked );
	}

	/**
	 * Get all blocked IPs.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	public function get_blocked_ips(): array {
		return get_option( 'ai_security_blocked_ips', array() );
	}

	/**
	 * Add firewall rule.
	 *
	 * @since 7.1.0
	 * @param array $rule Rule configuration.
	 * @return bool
	 */
	public function add_rule( array $rule ): bool {
		$rules = get_option( 'ai_security_firewall_rules', array() );
		$rules[] = array(
			'id'       => uniqid( 'rule_' ),
			'pattern'  => $rule['pattern'] ?? '',
			'action'   => $rule['action'] ?? 'block',
			'enabled'  => true,
			'created' => time(),
		);
		return update_option( 'ai_security_firewall_rules', $rules );
	}

	/**
	 * Get all firewall rules.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	public function get_rules(): array {
		return get_option( 'ai_security_firewall_rules', array() );
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
	 * Check rate limits for current request.
	 *
	 * @since 7.1.0
	 */
	public function check_rate_limit(): void {
		$rate_limit = (int) get_option( 'ai_security_rate_limit', 60 );
		if ( $rate_limit <= 0 ) {
			return;
		}

		$ip      = $this->get_client_ip();
		$key     = self::RATE_LIMIT_PREFIX . md5( $ip );
		$request = $this->get_request_signature();

		$transient = get_transient( $key );

		if ( false === $transient ) {
			set_transient( $key, array( $request => 1 ), 60 );
			return;
		}

		// Count requests in last minute for this IP
		$request_count = 0;
		foreach ( $transient as $sig => $count ) {
			$request_count += $count;
		}

		if ( $request_count >= $rate_limit ) {
			$this->handle_rate_limit_exceeded( $ip );
		} else {
			$transient[ $request ] = ( $transient[ $request ] ?? 0 ) + 1;
			set_transient( $key, $transient, 60 );
		}
	}

	/**
	 * Get request signature for rate limiting.
	 *
	 * @since 7.1.0
	 * @return string
	 */
	private function get_request_signature(): string {
		$uri    = $_SERVER['REQUEST_URI'] ?? '';
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$query  = $_SERVER['QUERY_STRING'] ?? '';

		return md5( $method . '|' . $uri . '|' . $query );
	}

	/**
	 * Handle rate limit exceeded.
	 *
	 * @since 7.1.0
	 * @param string $ip IP address.
	 */
	private function handle_rate_limit_exceeded( string $ip ): void {
		$threshold = (int) get_option( 'ai_security_failed_login_threshold', 5 );

		// Track rate limit violations
		$violations = (int) get_transient( self::RATE_LIMIT_PREFIX . 'violations_' . md5( $ip ) );
		$violations++;
		set_transient( self::RATE_LIMIT_PREFIX . 'violations_' . md5( $ip ), $violations, 300 );

		// Log the event
		\WordPress\AI_Security\Audit_Logger::get_instance()->log(
			'rate_limit_exceeded',
			'high',
			'Rate limit exceeded for IP: ' . $ip . ' (' . $violations . ' violations)'
		);

		// Auto-block if threshold reached
		if ( $violations >= $threshold && get_option( 'ai_security_auto_block', true ) ) {
			$this->block_ip( $ip, 'Rate limit exceeded (' . $violations . ' violations)' );
		}

		status_header( 429 );
		wp_die(
			esc_html__( 'Rate limit exceeded. Please try again later.', 'ai-security' ),
			esc_html__( 'Too Many Requests', 'ai-security' ),
			array( 'response' => 429 )
		);
	}

	/**
	 * Apply firewall rules to current request.
	 *
	 * @since 7.1.0
	 */
	public function apply_firewall_rules(): void {
		$rules = $this->get_rules();
		if ( empty( $rules ) ) {
			return;
		}

		$request = $this->get_request_for_matching();

		foreach ( $rules as $rule ) {
			if ( ! $rule['enabled'] ?? true ) {
				continue;
			}

			$pattern = $rule['pattern'] ?? '';
			if ( empty( $pattern ) ) {
				continue;
			}

			// Simple pattern matching (supports regex)
			if ( @preg_match( '/' . $pattern . '/', $request['uri'] )
				|| @preg_match( '/' . $pattern . '/', $request['full'] ) ) {

				$action = $rule['action'] ?? 'block';

				switch ( $action ) {
					case 'block':
						$this->block_ip(
							$this->get_client_ip(),
							'Firewall rule matched: ' . ( $rule['id'] ?? 'unknown' )
						);
						status_header( 403 );
						wp_die( esc_html__( 'Request blocked by firewall.', 'ai-security' ) );
						break;

					case 'log':
						\WordPress\AI_Security\Audit_Logger::get_instance()->log(
							'firewall_block',
							'medium',
							'Firewall rule matched: ' . $pattern
						);
						break;

					case 'challenge':
						// Simple captcha challenge (could be enhanced)
						if ( ! isset( $_REQUEST['ai_security_challenge'] ) ) {
							wp_die(
								'<p>' . esc_html__( 'Security challenge. Please confirm you are human.', 'ai-security' ) . '</p>' .
								'<form method="get"><input type="hidden" name="ai_security_challenge" value="1">' .
								'<input type="submit" class="button button-primary" value="' . esc_attr__( 'Verify', 'ai-security' ) . '"></form>',
								esc_html__( 'Security Check', 'ai-security' ),
								array( 'response' => 403 )
							);
						}
						break;
				}
			}
		}
	}

	/**
	 * Get request data for rule matching.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	private function get_request_for_matching(): array {
		$uri  = $_SERVER['REQUEST_URI'] ?? '';
		$host = $_SERVER['HTTP_HOST'] ?? '';
		$ref  = $_SERVER['HTTP_REFERER'] ?? '';

		return array(
			'uri'   => $uri,
			'full'  => $host . $uri,
			'referer' => $ref,
			'query' => $_SERVER['QUERY_STRING'] ?? '',
		);
	}

	/**
	 * Delete a firewall rule.
	 *
	 * @since 7.1.0
	 * @param string $rule_id Rule ID.
	 * @return bool
	 */
	public function delete_rule( string $rule_id ): bool {
		$rules = get_option( 'ai_security_firewall_rules', array() );
		foreach ( $rules as $i => $rule ) {
			if ( ( $rule['id'] ?? '' ) === $rule_id ) {
				unset( $rules[ $i ] );
				return update_option( 'ai_security_firewall_rules', array_values( $rules ) );
			}
		}
		return false;
	}

	/**
	 * Toggle a firewall rule.
	 *
	 * @since 7.1.0
	 * @param string $rule_id Rule ID.
	 * @param bool   $enabled  Enable or disable.
	 * @return bool
	 */
	public function toggle_rule( string $rule_id, bool $enabled ): bool {
		$rules = get_option( 'ai_security_firewall_rules', array() );
		foreach ( $rules as $i => $rule ) {
			if ( ( $rule['id'] ?? '' ) === $rule_id ) {
				$rules[ $i ]['enabled'] = $enabled;
				return update_option( 'ai_security_firewall_rules', $rules );
			}
		}
		return false;
	}

	/**
	 * Get country code from IP using free GeoIP service.
	 *
	 * @since 7.1.0
	 * @param string $ip IP address.
	 * @return string|null Country code or null if lookup fails.
	 */
	public function get_country_from_ip( string $ip ): ?string {
		// Skip private/local IPs
		if ( $this->is_private_ip( $ip ) ) {
			return null;
		}

		// Use ip-api.com free service (100 requests/minute, 10k/month)
		$response = wp_remote_get(
			'http://ip-api.com/json/' . $ip . '?fields=status,countryCode',
			array( 'timeout' => 5 )
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['status'] ) && 'success' === $data['status'] ) {
			return $data['countryCode'] ?? null;
		}

		return null;
	}

	/**
	 * Check if IP is private/local.
	 *
	 * @since 7.1.0
	 * @param string $ip IP address.
	 * @return bool
	 */
	private function is_private_ip( string $ip ): bool {
		$private_ranges = array(
			'^10\.',
			'^172\.(1[6-9]|2[0-9]|3[0-1])\.',
			'^192\.168\.',
			'^127\.',
			'^localhost$',
		);

		foreach ( $private_ranges as $range ) {
			if ( preg_match( '/' . $range . '/', $ip ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Block countries.
	 *
	 * @since 7.1.0
	 * @param array $countries Array of country codes to block.
	 * @return bool
	 */
	public function set_blocked_countries( array $countries ): bool {
		return update_option( 'ai_security_blocked_countries', array_map( 'strtoupper', $countries ) );
	}

	/**
	 * Get blocked countries.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	public function get_blocked_countries(): array {
		return get_option( 'ai_security_blocked_countries', array() );
	}

	/**
	 * Check if current country is blocked.
	 *
	 * @since 7.1.0
	 */
	public function check_country_block(): void {
		$blocked_countries = $this->get_blocked_countries();
		if ( empty( $blocked_countries ) ) {
			return;
		}

		$ip = $this->get_client_ip();
		if ( $this->is_private_ip( $ip ) ) {
			return;
		}

		// Use cached country if available
		$cache_key = 'ai_sec_country_' . md5( $ip );
		$country   = get_transient( $cache_key );

		if ( false === $country ) {
			$country = $this->get_country_from_ip( $ip );
			// Cache for 24 hours (country doesn't change)
			set_transient( $cache_key, $country ?: 'unknown', DAY_IN_SECONDS );
		}

		if ( $country && in_array( strtoupper( $country ), array_map( 'strtoupper', $blocked_countries ), true ) ) {
			$this->block_ip( $ip, 'Country blocked: ' . $country );
			status_header( 403 );
			wp_die( 'Access from your country is not allowed.' );
		}
	}

	/**
	 * Get statistics for dashboard.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	public function get_stats(): array {
		$blocked_ips = $this->get_blocked_ips();
		$rules       = $this->get_rules();
		$countries   = $this->get_blocked_countries();

		$manual_blocks = 0;
		$auto_blocks   = 0;

		foreach ( $blocked_ips as $ip => $data ) {
			if ( isset( $data['manual'] ) && $data['manual'] ) {
				$manual_blocks++;
			} else {
				$auto_blocks++;
			}
		}

		$enabled_rules = 0;
		foreach ( $rules as $rule ) {
			if ( $rule['enabled'] ?? true ) {
				$enabled_rules++;
			}
		}

		return array(
			'blocked_ips'    => count( $blocked_ips ),
			'manual_blocks'  => $manual_blocks,
			'auto_blocks'    => $auto_blocks,
			'total_rules'    => count( $rules ),
			'enabled_rules'  => $enabled_rules,
			'blocked_countries' => count( $countries ),
		);
	}
}