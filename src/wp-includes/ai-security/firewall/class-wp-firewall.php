<?php
/**
 * Firewall - Request filtering and rules.
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
}