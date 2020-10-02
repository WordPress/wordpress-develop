<?php
/**
 * HTTPS detection functions.
 *
 * @package WordPress
 * @since 5.7.0
 */

/**
 * Checks whether the website is using HTTPS.
 *
 * This is based on whether the home and site URL are using HTTPS.
 *
 * @since 5.7.0
 *
 * @return bool True if using HTTPS, false otherwise.
 */
function wp_is_using_https() {
	if ( 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
		return false;
	}

	if ( 'https' !== wp_parse_url( site_url(), PHP_URL_SCHEME ) ) {
		return false;
	}

	return true;
}

/**
 * Checks whether HTTPS is supported for the server and domain.
 *
 * @since 5.7.0
 *
 * @return bool True if HTTPS is supported, false otherwise.
 */
function wp_is_https_supported() {
	$https_detection_errors = get_option( 'https_detection_errors' );

	// If option has never been set by the Cron hook before, run it on-the-fly as fallback.
	if ( false === $https_detection_errors ) {
		wp_update_https_detection_errors();

		$https_detection_errors = get_option( 'https_detection_errors' );
	}

	// If there are no detection errors, HTTPS is supported.
	return empty( $https_detection_errors );
}

/**
 * Runs a remote HTTPS request to detect whether HTTPS supported, and stores potential errors.
 *
 * This internal function is called by a regular Cron hook to ensure HTTPS support is detected and maintained.
 *
 * @since 5.7.0
 * @access private
 */
function wp_update_https_detection_errors() {
	$support_errors = new WP_Error();

	$response = wp_remote_request(
		home_url( '/', 'https' ),
		array(
			'headers'   => array(
				'Cache-Control' => 'no-cache',
			),
			'sslverify' => true,
		)
	);

	if ( is_wp_error( $response ) ) {
		$unverified_response = wp_remote_request(
			home_url( '/', 'https' ),
			array(
				'headers'   => array(
					'Cache-Control' => 'no-cache',
				),
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $unverified_response ) ) {
			$support_errors->add(
				$unverified_response->get_error_code(),
				$unverified_response->get_error_message()
			);
		} else {
			$support_errors->add(
				'ssl_verification_failed',
				$response->get_error_message()
			);
		}

		$response = $unverified_response;
	}

	if ( ! is_wp_error( $response ) && 200 !== wp_remote_retrieve_response_code( $response ) ) {
		$support_errors->add( 'response_error', wp_remote_retrieve_response_message( $response ) );
	}

	update_option( 'https_detection_errors', $support_errors->errors );
}

/**
 * Schedules the Cron hook for detecting HTTPS support.
 *
 * @since 5.7.0
 * @access private
 */
function wp_cron_schedule_https_detection() {
	if ( ! wp_next_scheduled( 'wp_https_detection' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'wp_https_detection' );
	}
}

/**
 * Disables SSL verification if the 'cron_request' arguments include an HTTPS URL.
 *
 * This prevents an issue if HTTPS breaks, where there would be a failed attempt to verify HTTPS.
 *
 * @since 5.7.0
 * @access private
 *
 * @param array $request The Cron request arguments.
 * @return array $request The filtered Cron request arguments.
 */
function wp_cron_conditionally_prevent_sslverify( $request ) {
	if ( 'https' === wp_parse_url( $request['url'], PHP_URL_SCHEME ) ) {
		$request['args']['sslverify'] = false;
	}
	return $request;
}
