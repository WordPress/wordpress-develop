<?php
/**
 * WordPress Plugin Administration API: WP_Rollback_Auto_Update class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 6.4.0
 */

/**
 * Core class for rolling back auto-update plugin failures.
 */
class WP_Rollback_Auto_Update {

	/**
	 * Stores handler parameters.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	private $handler_args = array();

	/**
	 * Stores successfully updated plugins.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	private static $processed = array();

	/**
	 * Stores fataling plugins.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	private static $fatals = array();

	/**
	 * Stores active state of plugins being updated.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	private static $is_active = array();

	/**
	 * Stores `update_plugins` transient.
	 *
	 * @since 6.4.0
	 *
	 * @var stdClass
	 */
	private static $current_plugins;

	/**
	 * Stores `update_themes` transient.
	 *
	 * @since 6.4.0
	 *
	 * @var stdClass
	 */
	private static $current_themes;

	/**
	 * Stores instance of Plugin_Upgrader.
	 *
	 * @since 6.4.0
	 *
	 * @var Plugin_Upgrader
	 */
	private static $plugin_upgrader;

	/**
	 * Stores error codes.
	 *
	 * @since 6.4.0
	 *
	 * @var int
	 */
	private static $error_types = E_ERROR | E_PARSE | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

	/**
	 * Stores array of regex for error exceptions.
	 *
	 * These errors occur because a plugin loaded in memory results in some errors
	 * during 'include()' that do not occur during manual updating as a browser
	 * redirect clears the memory.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	private static $error_exceptions = array(
		'Cannot declare class', // class defined in main plugin file.
		'Constant([ _A-Z]+)already defined', // constant defined in main plugin file.
		'Cannot redeclare', // function defined in main plugin file.
		'mkdir\(\): File exists', // constant defined in main plugin file.
		'Passing null to parameter(.*)of type(.*)is deprecated', // PHP8 deprecation error.
		'Trying to access array offset on value of type null', // PHP8 deprecation error.
		'ReturnTypeWillChange', // PHP8 deprecation error.
	);

	/**
	 * Stores bool if email has been sent.
	 *
	 * @since 6.4.0
	 *
	 * @var bool
	 */
	private static $email_sent = false;

	/**
	 * Checks the validity of the updated plugin.
	 *
	 * @since 6.4.0
	 *
	 * @param array|WP_Error $result     Result from WP_Upgrader::install_package().
	 * @param array          $hook_extra Extra arguments passed to hooked filters.
	 * @param WP_Upgrader    $upgrader   WP_Upgrader or child class instance.
	 * @return array|WP_Error
	 */
	public function auto_update_check( $result, $hook_extra, $upgrader ) {
		if ( is_wp_error( $result ) || ! wp_doing_cron() || ! isset( $hook_extra['plugin'] ) ) {
			return $result;
		}

		// Already processed.
		if ( in_array( $hook_extra['plugin'], array_diff( self::$processed, self::$fatals ), true ) ) {
			return $result;
		}

		self::$current_plugins = get_site_transient( 'update_plugins' );
		self::$current_themes  = get_site_transient( 'update_themes' );

		/*
		 * This possibly helps to avoid a potential race condition on servers that may start to
		 * process the next plugin for auto-updating before the handler can pick up an error from
		 * the previously processed plugin.
		 */
		sleep( 2 );

		static::$plugin_upgrader = $upgrader;
		$this->handler_args      = array(
			'handler_error' => '',
			'result'        => $result,
			'hook_extra'    => $hook_extra,
		);

		// Register exception and shutdown handlers.
		$this->initialize_handlers();

		self::$processed[] = $hook_extra['plugin'];
		if ( is_plugin_active( $hook_extra['plugin'] ) ) {
			self::$is_active[] = $hook_extra['plugin'];
			deactivate_plugins( $hook_extra['plugin'] );
		}

		/*
		 * Working parts of plugin_sandbox_scrape().
		 * Must use 'include()' instead of 'include_once()' to surface errors.
		 */
		wp_register_plugin_realpath( WP_PLUGIN_DIR . '/' . $hook_extra['plugin'] );
		include WP_PLUGIN_DIR . '/' . $hook_extra['plugin'];

		return $result;
	}

	/**
	 * Initializes handlers.
	 *
	 * @since 6.4.0
	 */
	private function initialize_handlers() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( array( $this, 'error_handler' ), ( E_ALL ^ self::$error_types ) );
		set_exception_handler( array( $this, 'exception_handler' ) );
		register_shutdown_function( array( $this, 'shutdown_function' ) );
	}

	/**
	 * Handles Errors.
	 *
	 * @since 6.4.0
	 *
	 * @param int    $errno  Error number.
	 * @param string $errstr Error message.
	 * @return array|void
	 */
	public function error_handler( $errno, $errstr ) {
		$int_to_type = array(
			2     => 'E_WARNING',
			8     => 'E_NOTICE',
			512   => 'E_USER_WARNING',
			1024  => 'E_USER_NOTICE',
			4096  => 'E_RECOVERABLE_ERROR',
			8192  => 'E_DEPRECATED',
			16384 => 'E_USER_DEPRECATED',
		);
		$result      = $this->check_passing_errors( $errstr );
		if ( is_array( $result ) ) {
			return $result;
		}
		$this->handler_args['handler_error'] = 'RAU Error Handler';
		$this->handler_args['error_msg']     = $int_to_type[ $errno ] . ': ' . $errstr;
		$this->handler();
	}

	/**
	 * Handles Exceptions.
	 *
	 * @since 6.4.0
	 *
	 * @param Throwable $exception Exception object.
	 * @return void
	 */
	public function exception_handler( Throwable $exception ) {
		$this->handler_args['handler_error'] = 'RAU Exception Handler';
		$this->handler_args['error_msg']     = $exception->getMessage();
		$this->handler();
	}

	/**
	 * Shutdown function.
	 *
	 * @return array|void
	 */
	public function shutdown_function() {
		$last_error = error_get_last();
		$result     = $this->check_passing_errors( $last_error['message'] );
		if ( is_array( $result ) ) {
			$this->restart_updates_and_send_email();
			exit();
		}
		$this->handler_args['handler_error'] = 'RAU Shutdown Function';
		$this->handler_args['error_msg']     = $last_error['message'];
		$this->handler();
	}

	/**
	 * Check for errors only caused by an active plugin using 'include()'.
	 *
	 * @since 6.4.0
	 *
	 * @param string $error_msg Error message from handler.
	 * @return array|bool
	 */
	private function check_passing_errors( $error_msg ) {
		if ( empty( $error_msg ) ) {
			return array();
		}
		preg_match( '/(' . implode( '|', static::$error_exceptions ) . ')/', $error_msg, $matches );
		if ( ! empty( $matches ) ) {
			return $this->handler_args['result'];
		}

		return false;
	}

	/**
	 * Handles errors by running Rollback.
	 *
	 * @since 6.4.0
	 */
	private function handler() {
		error_log( var_export( $this->handler_args['handler_error'] . ' - ' . $this->handler_args['error_msg'], true ) );
		if ( in_array( $this->handler_args['hook_extra']['plugin'], self::$fatals, true ) ) {
			return;
		}
		self::$fatals[] = $this->handler_args['hook_extra']['plugin'];

		$this->cron_rollback();

		/*
		 * This possibly helps to avoid a potential race condition on servers that may start to
		 * process the next plugin for auto-updating before the handler can pick up an error from
		 * the previously processed plugin.
		 */
		sleep( 2 );

		/*
		 * If a plugin upgrade fails prior to a theme upgrade running, the plugin upgrader will have
		 * hooked the 'Plugin_Upgrader::delete_old_plugin()' method to 'upgrader_clear_destination',
		 * which will return a `WP_Error` object and prevent the process from continuing.
		 *
		 * To resolve this, the hook must be removed using the original plugin upgrader instance.
		 */
		remove_filter( 'upgrader_clear_destination', array( static::$plugin_upgrader, 'delete_old_plugin' ) );

		$this->restart_updates_and_send_email();
	}

	/**
	 * Rolls back during cron.
	 *
	 * @since 6.4.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 */
	private function cron_rollback() {
		global $wp_filesystem;

		$temp_backup = array(
			'temp_backup' => array(
				'dir'  => 'plugins',
				'slug' => dirname( $this->handler_args['hook_extra']['plugin'] ),
				'src'  => $wp_filesystem->wp_plugins_dir(),
			),
		);

		$rollback_updater = new WP_Upgrader();

		// Set private $temp_restores variable.
		$ref_temp_restores = new ReflectionProperty( $rollback_updater, 'temp_restores' );
		$ref_temp_restores->setAccessible( true );
		$ref_temp_restores->setValue( $rollback_updater, $temp_backup );

		// Set private $temp_backups variable.
		$ref_temp_backups = new ReflectionProperty( $rollback_updater, 'temp_backups' );
		$ref_temp_backups->setAccessible( true );
		$ref_temp_backups->setValue( $rollback_updater, $temp_backup );

		// Call Rollback's restore_temp_backup().
		$rollback_updater->restore_temp_backup();

		// Call Rollback's delete_temp_backup().
		$rollback_updater->delete_temp_backup();
	}

	/**
	 * Restart update process for plugins that remain after a fatal.
	 *
	 * @since 6.4.0
	 */
	private function restart_updates() {
		$remaining_plugin_auto_updates = $this->get_remaining_plugin_auto_updates();
		$remaining_theme_auto_updates  = $this->get_remaining_theme_auto_updates();
		$skin                          = new Automatic_Upgrader_Skin();

		if ( ! empty( $remaining_plugin_auto_updates ) ) {
			$plugin_upgrader = new Plugin_Upgrader( $skin );
			$plugin_upgrader->bulk_upgrade( $remaining_plugin_auto_updates );
		}

		if ( ! empty( $remaining_theme_auto_updates ) ) {
			$theme_upgrader = new Theme_Upgrader( $skin );
			$results        = $theme_upgrader->bulk_upgrade( $remaining_theme_auto_updates );

			foreach ( array_keys( $results ) as $theme ) {
				if ( ! is_wp_error( $theme ) ) {
					self::$processed[] = $theme;
				}
			}
		}
	}

	/**
	 * Restart update process for core.
	 *
	 * @since 6.4.0
	 */
	private function restart_core_updates() {
		if ( ! function_exists( 'find_core_auto_update' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$core_update = find_core_auto_update();
		if ( $core_update ) {
			$core_updater = new WP_Automatic_Updater();
			$core_updater->update( 'core', $core_update );
		}
	}

	/**
	 * Get array of non-fataling plugin auto-updates remaining.
	 *
	 * @since 6.4.0
	 *
	 * @return array
	 */
	private function get_remaining_plugin_auto_updates() {
		if ( empty( $this->handler_args ) ) {
			return array();
		}

		// Get array of plugins set for auto-updating.
		$auto_updates    = (array) get_site_option( 'auto_update_plugins', array() );
		$current_plugins = array_keys( self::$current_plugins->response );

		// Get all auto-updating plugins that have updates available.
		$current_auto_updates = array_intersect( $auto_updates, $current_plugins );

		// Get array of non-fatal auto-updates remaining.
		$remaining_auto_updates = array_diff( $current_auto_updates, self::$processed, self::$fatals );

		return $remaining_auto_updates;
	}

	/**
	 * Get array of non-fataling theme auto-updates remaining.
	 *
	 * @since 6.4.0
	 *
	 * @return array
	 */
	private function get_remaining_theme_auto_updates() {
		if ( empty( $this->handler_args ) ) {
			return array();
		}

		// Get array of themes set for auto-updating.
		$auto_updates   = (array) get_site_option( 'auto_update_themes', array() );
		$current_themes = array_keys( self::$current_themes->response );

		// Get all auto-updating plugins that have updates available.
		$current_auto_updates = array_intersect( $auto_updates, $current_themes );

		// Get array of non-fatal auto-updates remaining.
		$remaining_auto_updates = array_diff( $current_auto_updates, self::$processed, self::$fatals );

		return $remaining_auto_updates;
	}

	/**
	 * Restart updates and send update result email.
	 *
	 * @since 6.4.0
	 *
	 * @return void
	 */
	private function restart_updates_and_send_email() {
		$this->restart_updates();
		$this->restart_core_updates();

		/*
		 * The following commands only run once after the above commands have completed.
		 * Specifically, 'restart_updates()' will re-run until there are no further
		 * plugin or themes updates remaining.
		 */
		activate_plugins( self::$is_active );
		$this->send_update_result_email();
	}

	/**
	 * Sends an email noting successful and failed updates.
	 *
	 * @since 6.4.0
	 */
	private function send_update_result_email() {
		if ( self::$email_sent ) {
			return;
		}
		$result         = true;
		$update_results = array();

		$plugin_theme_email_data = array(
			'plugin' => array( 'data' => get_plugins() ),
			'theme'  => array( 'data' => wp_get_themes() ),
		);

		foreach ( $plugin_theme_email_data as $type => $data ) {
			$current_items = 'plugin' === $type ? self::$current_plugins : self::$current_themes;

			foreach ( array_keys( $current_items->response ) as $file ) {
				if ( ! in_array( $file, self::$processed, true ) ) {
					continue;
				}

				$item            = $current_items->response[ $file ];
				$current_version = property_exists( $current_items, 'checked' ) ? $current_items->checked[ $file ] : __( 'unavailable' );
				$success         = array_diff( self::$processed, self::$fatals );

				if ( in_array( $file, $success, true ) ) {
					$result = true;
				} elseif ( in_array( $file, self::$fatals, true ) ) {
					$result = false;
				}

				if ( 'plugin' === $type ) {
					$name                  = $data['data'][ $file ]['Name'];
					$item->current_version = $current_version;
					$type_result           = (object) array(
						'name'   => $name,
						'item'   => $item,
						'result' => $result,
					);
				}

				if ( 'theme' === $type ) {
					$name                    = $data['data'][ $file ]->get( 'Name' );
					$item['current_version'] = $current_version;
					$type_result             = (object) array(
						'name'   => $name,
						'item'   => (object) $item,
						'result' => $result,
					);
				}

				$update_results[ $type ][] = $type_result;
			}
		}

		add_filter( 'auto_plugin_theme_update_email', array( $this, 'auto_update_rollback_message' ), 10, 4 );

		$automatic_upgrader      = new WP_Automatic_Updater();
		$send_plugin_theme_email = new ReflectionMethod( $automatic_upgrader, 'after_plugin_theme_update' );
		$send_plugin_theme_email->setAccessible( true );
		$send_plugin_theme_email->invoke( $automatic_upgrader, $update_results );

		remove_filter( 'auto_plugin_theme_update_email', array( $this, 'auto_update_rollback_message' ), 10 );
		self::$email_sent = true;
	}

	/**
	 * Add auto-update failure message to email.
	 *
	 * @since 6.4.0
	 *
	 * @param array  $email {
	 *     Array of email arguments that will be passed to wp_mail().
	 *
	 *     @type string $to      The email recipient. An array of emails
	 *                           can be returned, as handled by wp_mail().
	 *     @type string $subject The email's subject.
	 *     @type string $body    The email message body.
	 *     @type string $headers Any email headers, defaults to no headers.
	 * }
	 * @param string $type               The type of email being sent. Can be one of 'success', 'fail', 'mixed'.
	 * @param array  $successful_updates A list of updates that succeeded.
	 * @param array  $failed_updates     A list of updates that failed.
	 * @return array
	 */
	public function auto_update_rollback_message( $email, $type, $successful_updates, $failed_updates ) {
		if ( empty( $failed_updates ) || 'success' === $type ) {
			return $email;
		}
		$body   = explode( "\n", $email['body'] );
		$failed = __( 'These plugins failed to update and should have been restored from a temporary backup due to detection of a fatal error:' );
		array_splice( $body, 6, 1, $failed );
		$body          = implode( "\n", $body );
		$email['body'] = $body;

		return $email;
	}
}
