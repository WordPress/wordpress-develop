<?php
/**
 * Error Protection API: WP_Fatal_Error_Handler class
 *
 * @package WordPress
 * @since 5.2.0
 */

/**
 * Core class used as the default shutdown handler for fatal errors.
 *
 * A drop-in 'fatal-error-handler.php' can be used to override the instance of this class and use a custom
 * implementation for the fatal error handler that WordPress registers. The custom class should extend this class and
 * can override its methods individually as necessary. The file must return the instance of the class that should be
 * registered.
 *
 * @since 5.2.0
 */
class WP_Fatal_Error_Handler {

	/**
	 * Runs the shutdown handler.
	 *
	 * This method is registered via `register_shutdown_function()`.
	 *
	 * @since 5.2.0
	 */
	public function handle() {
		if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
			return;
		}

		// Do not trigger the fatal error handler while updates are being installed.
		if ( wp_is_maintenance_mode() ) {
			return;
		}

		try {
			// Bail if no error found.
			$error = $this->detect_error();
			if ( ! $error ) {
				return;
			}

			if ( ! isset( $GLOBALS['wp_locale'] ) && function_exists( 'load_default_textdomain' ) ) {
				load_default_textdomain();
			}

			$handled = false;

			if ( ! is_multisite() && wp_recovery_mode()->is_initialized() ) {
				$handled = wp_recovery_mode()->handle_error( $error );
			}

			/**
			 * Handle REST API fatal error.
			 *
			 * @since 5.7.0
			 */
			if ( wp_is_json_request() ) {
				$this->send_rest_api_error_response( $error, $handled );
				return;
			}

			// Display the PHP error template, or JSON error, if headers not sent or is JSON request.
			if ( is_admin() || ! headers_sent() ) {
				$this->display_error_template( $error, $handled );
			}
		} catch ( Exception $e ) {
			// Catch exceptions and remain silent.
		}
	}

	/**
	 * Detects the error causing the crash if it should be handled.
	 *
	 * @since 5.2.0
	 *
	 * @return array|null Error that was triggered, or null if no error received or if the error should not be handled.
	 */
	protected function detect_error() {
		$error = error_get_last();

		// No error, just skip the error handling code.
		if ( null === $error ) {
			return null;
		}

		// Bail if this error should not be handled.
		if ( ! $this->should_handle_error( $error ) ) {
			return null;
		}

		return $error;
	}

	/**
	 * Determines whether we are dealing with an error that WordPress should handle
	 * in order to protect the admin backend against WSODs.
	 *
	 * @since 5.2.0
	 *
	 * @param array $error Error information retrieved from error_get_last().
	 * @return bool Whether WordPress should handle this error.
	 */
	protected function should_handle_error( $error ) {
		$error_types_to_handle = array(
			E_ERROR,
			E_PARSE,
			E_USER_ERROR,
			E_COMPILE_ERROR,
			E_RECOVERABLE_ERROR,
		);

		if ( isset( $error['type'] ) && in_array( $error['type'], $error_types_to_handle, true ) ) {
			return true;
		}

		/**
		 * Filters whether a given thrown error should be handled by the fatal error handler.
		 *
		 * This filter is only fired if the error is not already configured to be handled by WordPress core. As such,
		 * it exclusively allows adding further rules for which errors should be handled, but not removing existing
		 * ones.
		 *
		 * @since 5.2.0
		 *
		 * @param bool  $should_handle_error Whether the error should be handled by the fatal error handler.
		 * @param array $error               Error information retrieved from error_get_last().
		 */
		return (bool) apply_filters( 'wp_should_handle_php_error', false, $error );
	}

	/**
	 * Displays the PHP error template and sends the HTTP status code, typically 500.
	 *
	 * A drop-in 'php-error.php' can be used as a custom template. This drop-in should control the HTTP status code and
	 * print the HTML markup indicating that a PHP error occurred. Note that this drop-in may potentially be executed
	 * very early in the WordPress bootstrap process, so any core functions used that are not part of
	 * `wp-includes/load.php` should be checked for before being called.
	 *
	 * If no such drop-in is available, this will call {@see WP_Fatal_Error_Handler::display_default_error_template()}.
	 *
	 * @since 5.2.0
	 * @since 5.3.0 The `$handled` parameter was added.
	 *
	 * @param array         $error   Error information retrieved from `error_get_last()`.
	 * @param true|WP_Error $handled Whether Recovery Mode handled the fatal error.
	 */
	protected function display_error_template( $error, $handled ) {
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			// Load custom PHP error template, if present.
			$php_error_pluggable = WP_CONTENT_DIR . '/php-error.php';
			if ( is_readable( $php_error_pluggable ) ) {
				require_once $php_error_pluggable;

				return;
			}
		}

		// Otherwise, display the default error template.
		$this->display_default_error_template( $error, $handled );
	}

	/**
	 * Displays the default PHP error template.
	 *
	 * This method is called conditionally if no 'php-error.php' drop-in is available.
	 *
	 * It calls {@see wp_die()} with a message indicating that the site is experiencing technical difficulties and a
	 * login link to the admin backend. The {@see 'wp_php_error_message'} and {@see 'wp_php_error_args'} filters can
	 * be used to modify these parameters.
	 *
	 * @since 5.2.0
	 * @since 5.3.0 The `$handled` parameter was added.
	 *
	 * @param array         $error   Error information retrieved from `error_get_last()`.
	 * @param true|WP_Error $handled Whether Recovery Mode handled the fatal error.
	 */
	protected function display_default_error_template( $error, $handled ) {

		$this->maybe_load_error_dependencies();

		$message = $this->get_error_message( $handled );

		$message = sprintf(
			'<p>%s</p><p><a href="%s">%s</a></p>',
			$message,
			/* translators: Documentation explaining debugging in WordPress. */
			__( 'https://wordpress.org/support/article/debugging-in-wordpress/' ),
			__( 'Learn more about debugging in WordPress.' )
		);

		$args = array(
			'response' => 500,
			'exit'     => false,
		);

		/**
		 * Filters the message that the default PHP error template displays.
		 *
		 * @since 5.2.0
		 *
		 * @param string $message HTML error message to display.
		 * @param array  $error   Error information retrieved from `error_get_last()`.
		 */
		$message = apply_filters( 'wp_php_error_message', $message, $error );

		/**
		 * Filters the arguments passed to {@see wp_die()} for the default PHP error template.
		 *
		 * @since 5.2.0
		 *
		 * @param array $args Associative array of arguments passed to `wp_die()`. By default these contain a
		 *                    'response' key, and optionally 'link_url' and 'link_text' keys.
		 * @param array $error Error information retrieved from `error_get_last()`.
		 */
		$args = apply_filters( 'wp_php_error_args', $args, $error );

		$wp_error = new WP_Error(
			'internal_server_error',
			$message,
			array(
				'error' => $error,
			)
		);

		wp_die( $wp_error, '', $args );
	}

	/**
	 * Sends default error response to fatal error that occurred during REST API request.
	 *
	 * It calls {@see wp_die()} with a message indicating that the site is experiencing technical difficulties.
	 *
	 * The {@see 'wp_rest_error_message'} and {@see 'wp_rest_error_args'} filters can
	 * be used to modify these parameters.
	 *
	 * @since 5.7.0
	 *
	 * @param array         $error   Error information retrieved from `error_get_last()`.
	 * @param true|WP_Error $handled Whether Recovery Mode handled the fatal error.
	 */
	protected function send_rest_api_error_response( $error, $handled ) {

		$this->maybe_load_error_dependencies();

		$message = $this->get_error_message( $handled );

		$args = array(
			'response' => 500,
			'exit'     => true,
		);

		/**
		 * Filters the message that the default REST API fatal error handler displays.
		 *
		 * @since 5.7.0
		 *
		 * @param string $message Error message to display.
		 * @param array  $error   Error information retrieved from `error_get_last()`.
		 */
		$message = apply_filters( 'wp_rest_api_error_message', $message, $error );

		/**
		 * Filters the arguments passed to {@see wp_die()} for the default REST API fatal error handler.
		 *
		 * @since 5.7.0
		 *
		 * @param array $args  Associative array of arguments passed to `wp_die()`. By default these contain a
		 *                     'response' and 'exit' keys.
		 * @param array $error Error information retrieved from `error_get_last()`.
		 */
		$args = apply_filters( 'wp_rest_api_error_args', $args, $error );

		/**
		 * Return entire error in the REST API response if WP_DEBUG is set to true.
		 */
		if ( WP_DEBUG ) {
			$args['error_details'] = $error;
		}

		$wp_error = new WP_Error(
			'internal_server_error',
			$message,
			array(
				'error' => $error,
			)
		);

		wp_die( $wp_error, '', $args );
	}

	/**
	 * Ensure that fatal error messaging dependencies are properly loaded.
	 *
	 * @since 5.7.0
	 */
	protected function maybe_load_error_dependencies() {
		if ( ! function_exists( '__' ) ) {
			wp_load_translations_early();
		}

		if ( ! function_exists( 'wp_die' ) ) {
			require_once ABSPATH . WPINC . '/functions.php';
		}

		if ( ! class_exists( 'WP_Error' ) ) {
			require_once ABSPATH . WPINC . '/class-wp-error.php';
		}
	}

	/**
	 * Get error message used for handling fatal errors either for classic or REST API request.
	 *
	 * @since 5.7.0
	 *
	 * @param true|WP_Error $handled Whether Recovery Mode handled the fatal error.
	 *
	 * @return string
	 */
	protected function get_error_message( $handled ) {
		if ( true === $handled && wp_is_recovery_mode() ) {
			$message = __( 'There has been a critical error on this website, putting it in recovery mode. Please check the Themes and Plugins screens for more details. If you just installed or updated a theme or plugin, check the relevant page for that first.' );
		} elseif ( is_protected_endpoint() ) {
			$message = __( 'There has been a critical error on this website. Please check your site admin email inbox for instructions.' );
		} else {
			$message = __( 'There has been a critical error on this website.' );
		}

		return $message;
	}
}
