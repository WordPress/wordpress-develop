<?php
/**
 * Site State API: WP_Site_State class
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 6.9.0
 */

/**
 * Core class used for efficiently switching and restoring site state.
 *
 * @since 6.8.0
 */
#[AllowDynamicProperties]
class WP_Site_State {
	/**
	 * Current site ID.
	 *
	 * @since 6.9.0
	 * @var int
	 */
	private $site_id;

	/**
	 * The switched stack.
	 *
	 * @since 6.9.0
	 * @var array
	 */
	private $switched_stack = array();

	/**
	 * Whether or not we're currently switched.
	 *
	 * @since 6.9.0
	 * @var bool
	 */
	private $switched = false;

	/**
	 * Constructor.
	 *
	 * Stores the current site ID, the switched stack, and the switched state.
	 *
	 * @since 6.9.0
	 */
	public function __construct() {
		global $_wp_switched_stack, $switched;

		$this->site_id = get_current_blog_id();

		if ( ! empty( $_wp_switched_stack ) ) {
			$this->switched_stack = $_wp_switched_stack;
		}

		$this->switched = ! empty( $switched );
	}

	/**
	 * Restores the stored site state.
	 *
	 * @since 6.9.0
	 *
	 * @return bool True on success, false if no state change was needed.
	 */
	public function restore() {
		global $_wp_switched_stack, $switched, $wpdb, $blog_id, $table_prefix;

		$current_blog_id = get_current_blog_id();

		// If we're already on the target blog, just update the global state.
		if ( $current_blog_id === $this->site_id ) {
			$_wp_switched_stack = $this->switched_stack;
			$switched           = $this->switched;
			return true;
		}

		$wpdb->set_blog_id( $this->site_id );
		$table_prefix = $wpdb->get_blog_prefix();
		$blog_id      = $this->site_id;

		if ( function_exists( 'wp_cache_switch_to_blog' ) ) {
			wp_cache_switch_to_blog( $blog_id );
		}

		// Restore the switched stack and state.
		$_wp_switched_stack = $this->switched_stack;
		$switched           = $this->switched;

		/**
		 * Fires when the blog is switched.
		 *
		 * @since MU (3.0.0)
		 * @since 5.4.0 The `$context` parameter was added.
		 *
		 * @param int    $new_blog_id  New blog ID.
		 * @param int    $prev_blog_id Previous blog ID.
		 * @param string $context      Additional context. Accepts 'switch' when called from switch_to_blog()
		 *                             or 'restore' when called from restore_current_blog().
		 */
		do_action( 'switch_blog', $blog_id, $current_blog_id, 'restore' );

		return true;
	}

	/**
	 * Gets the site ID stored in this state.
	 *
	 * @since 6.9.0
	 *
	 * @return int The site ID.
	 */
	public function get_site_id() {
		return $this->site_id;
	}

	/**
	 * Gets the switched stack stored in this state.
	 *
	 * @since 6.9.0
	 *
	 * @return array The switched stack.
	 */
	public function get_switched_stack() {
		return $this->switched_stack;
	}

	/**
	 * Gets the switched status stored in this state.
	 *
	 * @since 6.9.0
	 *
	 * @return bool Whether the site was switched.
	 */
	public function is_switched() {
		return $this->switched;
	}
}
