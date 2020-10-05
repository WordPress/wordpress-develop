<?php
/**
 * REST API: WP_REST_Site_Health_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.6.0
 */

/**
 * Core class for interacting with Site Health elements.
 *
 * @since 5.6.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Site_Health_Controller extends WP_REST_Controller {

	/**
	 * An instance of the site health class.
	 *
	 * @var WP_Site_Health
	 */
	private $site_health;

	/**
	 * Constructor.
	 *
	 * @since 5.6.0
	 *
	 * @param WP_Site_Health $site_health An instance of the site health class.
	 */
	public function __construct( $site_health ) {
		$this->namespace = 'wp-site-health/v1';
		$this->rest_base = 'tests';

		$this->site_health = $site_health;
	}

	/**
	 * Register API routes.
	 *
	 * @since 5.6.0
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			sprintf(
				'/%s/%s',
				$this->rest_base,
				'background-updates'
			),
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'test_background_updates' ),
				'permission_callback' => function() {
					return $this->validate_request_permission( 'background_updates' );
				},
			)
		);

		register_rest_route(
			$this->namespace,
			sprintf(
				'/%s/%s',
				$this->rest_base,
				'loopback-requests'
			),
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'test_loopback_requests' ),
				'permission_callback' => function() {
					return $this->validate_request_permission( 'loopback_requests' );
				},
			)
		);

		register_rest_route(
			$this->namespace,
			sprintf(
				'/%s/%s',
				$this->rest_base,
				'dotorg-communication'
			),
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'test_dotorg_communication' ),
				'permission_callback' => function() {
					return $this->validate_request_permission( 'dotorg_communication' );
				},
			)
		);

		register_rest_route(
			$this->namespace,
			sprintf(
				'/%s/%s',
				$this->rest_base,
				'debug_enabled'
			),
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'test_is_in_debug_mode' ),
				'permission_callback' => function() {
					return $this->validate_request_permission( 'debug_enabled' );
				},
			)
		);

		register_rest_route(
			$this->namespace,
			sprintf(
				'/%s',
				'directory-sizes'
			),
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_directory_sizes' ),
				'permission_callback' => function() {
					return $this->validate_request_permission( 'debug_enabled' ) && ! is_multisite();
				},
			)
		);
	}

	/**
	 * Validate if the current user can request this REST endpoint.
	 *
	 * @since 5.6.0
	 *
	 * @param string $check The endpoint check being ran.
	 *
	 * @return bool
	 */
	protected function validate_request_permission( $check ) {
		$default_capability = 'view_site_health_checks';

		/**
		 * Filter the capability needed to run a given Site Health check.
		 *
		 * @since 5.6.0
		 *
		 * @param string $default_capability The default capability required for this check.
		 * @param string $check              The Site Health check being performed.
		 */
		$capability = apply_filters( "site_health_test_rest_capability_{$check}", $default_capability, $check );

		return current_user_can( $capability );
	}

	/**
	 * Check if background updates work as expected.
	 *
	 * @since 5.6.0
	 *
	 * @return array
	 */
	public function test_background_updates() {
		return $this->site_health->get_test_background_updates();
	}

	/**
	 * Check that the site can reach the WordPress.org API.
	 *
	 * @since 5.6.0
	 *
	 * @return array
	 */
	public function test_dotorg_communication() {
		return $this->site_health->get_test_dotorg_communication();
	}

	/**
	 * Check that loopbacks can be performed.
	 *
	 * @since 5.6.0
	 *
	 * @return array
	 */
	public function test_loopback_requests() {
		return $this->site_health->get_test_loopback_requests();
	}

	/**
	 * Check if the site has debug mode enabled.
	 *
	 * @since 5.6.0
	 *
	 * @return array
	 */
	public function test_is_in_debug_mode() {
		return $this->site_health->get_test_is_in_debug_mode();
	}

	/**
	 * Get the current directory sizes for this install.
	 *
	 * @since 5.6.0
	 *
	 * @return array|WP_Error
	 */
	public function get_directory_sizes() {
		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-debug-data.php' );
		}

		$sizes_data = WP_Debug_Data::get_sizes();
		$all_sizes  = array( 'raw' => 0 );

		foreach ( $sizes_data as $name => $value ) {
			$name = sanitize_text_field( $name );
			$data = array();

			if ( isset( $value['size'] ) ) {
				if ( is_string( $value['size'] ) ) {
					$data['size'] = sanitize_text_field( $value['size'] );
				} else {
					$data['size'] = (int) $value['size'];
				}
			}

			if ( isset( $value['debug'] ) ) {
				if ( is_string( $value['debug'] ) ) {
					$data['debug'] = sanitize_text_field( $value['debug'] );
				} else {
					$data['debug'] = (int) $value['debug'];
				}
			}

			if ( ! empty( $value['raw'] ) ) {
				$data['raw'] = (int) $value['raw'];
			}

			$all_sizes[ $name ] = $data;
		}

		if ( isset( $all_sizes['total_size']['debug'] ) && 'not available' === $all_sizes['total_size']['debug'] ) {
			return new WP_Error( 'not_available', __( 'Directory sizes could not be returned.' ), array( 'status' => 500 ) );
		}

		return $all_sizes;
	}
}
