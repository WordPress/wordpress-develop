<?php
/**
 * WP Dismiss Notice.
 *
 * @package dependencies-manager.
 * @since 1.0
 *
 * @see https://github.com/w3guy/persist-admin-notices-dismissal
 */

/**
 * Class WP_Dismiss_Notice
 *
 * To initialize dismissible admin notices the following commands are needed.
 *
 * Load the class.
 * require_once ABSPATH . 'wp-admin/includes/class-wp-dismiss-notice.php';
 *
 * Initialize the class.
 * add_action( 'admin_init', array( 'WP_Dismiss_Notice', 'init' ) );
 */
class WP_Dismiss_Notice {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'load_script' ) );
		add_action( 'wp_ajax_dismiss_admin_notice', array( __CLASS__, 'dismiss_admin_notice' ) );
	}

	/**
	 * Enqueue javascript and variables.
	 */
	public static function load_script() {

		if ( is_customize_preview() ) {
			return;
		}

		wp_enqueue_script(
			'dismissible-notices',
			__DIR__ . '/js/dismiss-notice.js',
			array( 'jquery', 'common' ),
			false,
			true
		);

		wp_localize_script(
			'dismissible-notices',
			'dismissible_notice',
			array(
				'nonce' => wp_create_nonce( 'dismissible-notice' ),
			)
		);
	}

	/**
	 * Handles Ajax request to persist notices dismissal.
	 * Uses check_ajax_referer to verify nonce.
	 */
	public static function dismiss_admin_notice() {
		$option_name        = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : false;
		$dismissible_length = isset( $_POST['dismissible_length'] ) ? sanitize_text_field( wp_unslash( $_POST['dismissible_length'] ) ) : 1;

		if ( 'forever' !== $dismissible_length ) {
			// If $dismissible_length is not an integer default to 1.
			$dismissible_length = ( 0 === absint( $dismissible_length ) ) ? 1 : $dismissible_length;
			$dismissible_length = strtotime( absint( $dismissible_length ) . ' days' );
		}

		check_ajax_referer( 'dismissible-notice', 'nonce' );
		self::set_admin_notice_cache( $option_name, $dismissible_length );
		wp_die();
	}

	/**
	 * Is admin notice active?
	 *
	 * @param string $arg data-dismissible content of notice.
	 *
	 * @return bool
	 */
	public static function is_admin_notice_active( $arg ) {
		$array = explode( '-', $arg );
		array_pop( $array );
		$option_name = implode( '-', $array );
		$db_record   = self::get_admin_notice_cache( $option_name );

		if ( 'forever' === $db_record ) {
			return false;
		} elseif ( absint( $db_record ) >= time() ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Returns admin notice cached timeout.
	 *
	 * @access public
	 *
	 * @param string|bool $id admin notice name or false.
	 *
	 * @return array|bool The timeout. False if expired.
	 */
	public static function get_admin_notice_cache( $id = false ) {
		if ( ! $id ) {
			return false;
		}
		$cache_key = 'wpdn-' . md5( $id );
		$timeout   = get_site_option( $cache_key );
		$timeout   = 'forever' === $timeout ? time() + 60 : $timeout;

		if ( empty( $timeout ) || time() > $timeout ) {
			return false;
		}

		return $timeout;
	}

	/**
	 * Sets admin notice timeout in site option.
	 *
	 * @access public
	 *
	 * @param string      $id       Data Identifier.
	 * @param string|bool $timeout  Timeout for admin notice.
	 *
	 * @return bool
	 */
	public static function set_admin_notice_cache( $id, $timeout ) {
		$cache_key = 'wpdn-' . md5( $id );
		update_site_option( $cache_key, $timeout );

		return true;
	}
}

// Initialize WP_Dismiss_Notice dependency.
add_action( 'admin_init', array( 'WP_Dismiss_Notice', 'init' ) );
