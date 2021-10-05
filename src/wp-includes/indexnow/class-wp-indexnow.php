<?php
/**
 * IndexNow: WP_IndexNow class
 *
 * This is the main IndexNow class.
 *
 * @package WordPress
 * @subpackage IndexNow
 * @since 5.9.0
 */

/**
 * Class WP_IndexNow.
 *
 * @since 5.9.0
 */

class WP_IndexNow {

	const WP_IN_PREFIX  = 'wp_indexnow_';
	const WP_IN_KEY_SET = self::WP_IN_PREFIX . 'key_set';
	const WP_IN_API_KEY = self::WP_IN_PREFIX . 'api_key';
	const WP_IN_ENABLED = 'blog_public';

	/**
	 * Array to store the instances of available indexnow complaint search engines.
	 *
	 * @since 5.9.0
	 *
	 * @var array of WP_IndexNow_Provider class instances.
	 */
	private $search_engines;

	 /**
	 * Array to store the excluded paths.
	 *
	 * @since 5.9.0
	 *
	 * @var array of excluded paths.
	 */
	private $excluded_paths;

	/**
	 * Timestamp of latest key refresh.
	 *
	 * @since 5.9.0
	 *
	 * @var int
	 */
	private $key_refresh_timestamp;

	/**
	 * WP_IndexNow constructor.
	 *
	 * @since 5.9.0
	 */
	public function __construct() {
		$this->excluded_paths        = array();
		$this->search_engines        = array();
		$this->key_refresh_timestamp = date_timestamp_get( date_create() );
	}

	/**
	 * Checks if IndexNow is enabled.
	 *
	 * @since 5.9.0
	 *
	 * @return bool Whether IndexNow is enabled or not.
	 */
	public function wp_indexnow_enabled() {
		$is_enabled = (bool) get_option( self::WP_IN_ENABLED ) && defined( 'WP_INDEXNOW' ) && true == WP_INDEXNOW;
		return $is_enabled;
	}

	/**
	 * Initiates indexnow.
	 *
	 * @since 5.9.0
	 */
	public function init() {
		if ( ! $this->wp_indexnow_enabled() ) {
			return;
		}

		add_action( 'transition_post_status', array( $this, 'on_post_published' ), 1, 3 );
		add_action( 'template_redirect', array( $this, 'check_for_indexnow_page' ) );

		$is_key_set = get_option( self::WP_IN_KEY_SET );

		//generating IndexNow key if not already set.
		if ( ! $is_key_set ) {
			$this->generate_indexnow_key();
		}
	}

	/**
	 * Add the subpath to be ignored.
	 *
	 * @since 5.9.0
	 *
	 */
	public function ignore_path( $exclude ) {
		if ( ! $this->wp_indexnow_enabled() || in_array( $exclude, $this->excluded_paths ) ) {
			return false;
		}

		array_push( $this->excluded_paths, $exclude );
		return true;
	}

	/**
	 * Remove the subpath from excluded array.
	 *
	 * @since 5.9.0
	 *
	 */
	public function remove_path( $path ) {
		if ( ! $this->wp_indexnow_enabled() ) {
			return false;
		}

		$idx = array_search( $this->excluded_paths, $path );
		if ( false !== $idx ) {
			return false;
		}
		array_splice( $this->excluded_paths, $idx, $idx );
		return true;
	}

	/**
	 * Returns the current IndexNow key.
	 *
	 * @since 5.9.0
	 *
	 * @return string Current IndexNow key.
	 */
	public function get_api_key() {
		if ( ! $this->wp_indexnow_enabled() ) {
			return null;
		}

		$this->refresh_indexnow_key_if_expired();
		$admin_api_key = get_option( self::WP_IN_API_KEY );
		$api_key       = base64_decode( $admin_api_key );
		return $api_key;
	}

	/**
	 * Initializes $search_engines array with WP_IndexNow_Provider class instances
	 * of complaint search engines from WP_INDEXNOW_PROVIDERS.
	 *
	 * @since 5.9.0
	 *
	 */
	public function init_search_engines() {

		if ( ! defined( 'WP_INDEXNOW_PROVIDERS' ) || ! $this->wp_indexnow_enabled() ) {
			return;
		}
		$providers = unserialize( WP_INDEXNOW_PROVIDERS );
		foreach ( $providers as $name => $url ) {
			if ( null !== $name && null !== $url ) {
				$this->search_engines[ $name ] = new WP_IndexNow_Provider( $url );
			}
		}
	}

	/**
	 * Generates the IndexNow api key and stores it in options on init.
	 *
	 * @since 5.9.0
	 *
	 */
	private function generate_indexnow_key() {
		$api_key = wp_generate_uuid4();
		$api_key = preg_replace( '[-]', '', $api_key );

		$is_key_set = get_option( self::WP_IN_KEY_SET );
		if ( ! $is_key_set ) {
			error_log( $api_key );

			update_option( self::WP_IN_API_KEY, base64_encode( $api_key ) );
			update_option( self::WP_IN_KEY_SET, true );
			$this->key_refresh_timestamp = date_timestamp_get( date_create() );
		}
	}

	/**
	 * Regenerates the IndexNow api key if expired.
	 *
	 * @since 5.9.0
	 *
	 */
	private function refresh_indexnow_key_if_expired() {
		$current_timestamp    = date_timestamp_get( date_create() );
		$time_elapsed_in_days = ( $current_timestamp - $this->key_refresh_timestamp ) / ( 60 * 60 * 24 );

		//refreshing key after 7 days
		if ( $time_elapsed_in_days >= 7 ) {
			$this->refresh_indexnow_key();
			$this->key_refresh_timestamp = $current_timestamp;
		}
	}


	/**
	 * Refreshes the IndexNow api key and updates it in options.
	 *
	 * @since 5.9.0
	 *
	 * @return string|null
	 */
	public function refresh_indexnow_key() {
		if ( ! $this->wp_indexnow_enabled() ) {
			return null;
		}

		update_option( self::WP_IN_KEY_SET, false );
		$this->generate_indexnow_key();
		return $this->get_api_key();
	}

	/**
	 *  Renders the IndexNow page for path site_url/{apikey}.txt.
	 *
	 * @since 5.9.0
	 *
	 */
	public function check_for_indexnow_page() {
		$admin_api_key = get_option( self::WP_IN_API_KEY );
		$api_key       = base64_decode( $admin_api_key );
		global $wp;
		$current_url = home_url( $wp->request );

		if ( isset( $current_url ) && trailingslashit( get_home_url() ) . $api_key . '.txt' === $current_url ) {
			header( 'Content-Type: text/plain' );
			header( 'X-Robots-Tag: noindex' );
			status_header( 200 );
			echo $api_key;

			exit();
		}
	}

	/**
	 *  Checks if the regex $pattern satisfies $original_string .
	 *
	 * @since 5.9.0
	 *
	 * @return bool
	 */
	public function starts_with( $original_string, $pattern ) {
		$home_url_len = strlen( get_home_url() );
		$path         = substr( $original_string, $home_url_len );
		return preg_match( $pattern, $path );
	}

	/**
	 *  Submits the relevant updated post to the complaint search engines.
	 *
	 * @since 5.9.0
	 *
	 */
	public function on_post_published( $new_status, $old_status, $post ) {
		remove_action( 'transition_post_status', array( $this, 'on_post_published' ), 1, 3 );
		$this->refresh_indexnow_key_if_expired();
		$admin_api_key    = get_option( self::WP_IN_API_KEY );
		$is_valid_api_key = get_option( self::WP_IN_KEY_SET );
		$is_change        = false;
		$type             = 'add';
		if ( 'publish' === $old_status && 'publish' === $new_status ) {
			$is_change = true;
			$type      = 'update';
		} elseif ( 'publish' !== $old_status && 'publish' === $new_status ) {
			$is_change = true;
			$type      = 'add';
		} elseif ( 'publish' === $old_status && 'trash' === $new_status ) {
			$is_change = true;
			$type      = 'delete';
		}
		// error_log( __METHOD__ . ' link ' . $new_status . ' ' . $post->post_name );
		if ( $is_change ) {
			$api_key = base64_decode( $admin_api_key );
			if ( isset( $post ) && $post->post_name !== $api_key ) {
				$link = get_permalink( $post->ID );
				foreach ( $this->excluded_paths as $path ) {
					if ( $this->starts_with( $link, $path ) ) {
						// error_log( 'path is exluded ' . $link );
						return;
					}
				}
				// remove __trashed from page url
				if ( strpos( $link, '__trashed' ) > 0 ) {
					$link = substr( $link, 0, strlen( $link ) - 10 ) . '/';
				}

				if ( empty( $link ) ) {
					add_action( 'transition_post_status', array( $this, 'on_post_published' ), 1, 3 );
					return;
				}

				$is_public_post = is_post_publicly_viewable( $post );

				if ( ! $is_public_post && 'delete' !== $type ) {
					add_action( 'transition_post_status', array( $this, 'on_post_published' ), 1, 3 );
					return;
				}

				$siteUrl = get_home_url();

				$engine_status_codes = array();
				foreach ( $this->search_engines as $name => $engine ) {

					$output                       = $engine->submit_url( $siteUrl, $link, $api_key );
					$engine_status_codes[ $name ] = $output;
					//error_log( $name . ' status message ' . $output );
				}
			}
		}
		add_action( 'transition_post_status', array( $this, 'on_post_published' ), 1, 3 );
	}
}
