<?php
/**
 * WordPress Plugin Administration API: WP_Rollback_Auto_Update class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 6.2.0
 */

/**
 * Core class for rolling back auto-update plugin failures.
 */
class WP_Rollback_Auto_Update {

	/**
	 * Stores handler parameters.
	 *
	 * @var array
	 */
	private $handler_args = array();

	/**
	 * Stores successfully updated plugins.
	 *
	 * @var array
	 */
	private $processed = array();

	/**
	 * Stores fataling plugins.
	 *
	 * @var array
	 */
	private $fatals = array();

	/**
	 * Stores `update_plugins` transient.
	 *
	 * @var \stdClass
	 */
	private static $current;

	/**
	 * Stores boolean for no error from check_plugin_for_errors().
	 *
	 * @var bool
	 */
	private $no_error = false;

	/**
	 * Stores error codes.
	 *
	 * @var int
	 */
	public $error_types = E_ERROR | E_PARSE | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

	/**
	 * Checks the validity of the updated plugin.
	 *
	 * @param array|WP_Error $result     Result from WP_Upgrader::install_package().
	 * @param array          $hook_extra Extra arguments passed to hooked filters.
	 *
	 * @return array|WP_Error
	 */
	public function auto_update_check( $result, $hook_extra ) {
		if ( is_wp_error( $result ) || ! wp_doing_cron() || ! isset( $hook_extra['plugin'] ) ) {
			return $result;
		}

		error_log( $hook_extra['plugin'] . ' processing...' );

		// This possibly helps to avoid a potential race condition on servers that may start to
		// process the next plugin for auto-updating before the handler can pick up an error from
		// the previously processed plugin.
		sleep( 2 );

		$this->no_error     = false;
		static::$current    = get_site_transient( 'update_plugins' );
		$this->handler_args = array(
			'handler_error' => '',
			'result'        => $result,
			'hook_extra'    => $hook_extra,
		);

		// Register exception and shutdown handlers.
		$this->initialize_handlers();

		$this->processed[] = $hook_extra['plugin'];

		if ( is_plugin_inactive( $hook_extra['plugin'] ) ) {
			// Working parts of plugin_sandbox_scrape().
			wp_register_plugin_realpath( WP_PLUGIN_DIR . '/' . $hook_extra['plugin'] );
			include WP_PLUGIN_DIR . '/' . $hook_extra['plugin'];
		}

		// Needs to run for both active and inactive plugins. Don't ask why, just accept it.
		$this->check_plugin_for_errors( $hook_extra['plugin'] );
		error_log( $hook_extra['plugin'] . ' auto updated.' );

		return $result;
	}

	/**
	 * Checks a new plugin version for errors.
	 *
	 * If an error is found, the previously installed version will be reinstalled
	 * and an email will be sent to the site administrator.
	 *
	 * @param string $plugin The plugin to check.
	 *
	 * @throws \Exception If errors are present.
	 *
	 * @return void
	 */
	private function check_plugin_for_errors( $plugin ) {
		global $wp_filesystem;

		if ( file_exists( ABSPATH . '.maintenance' ) ) {
			$wp_filesystem->delete( ABSPATH . '.maintenance' );
		}

		$nonce    = wp_create_nonce( 'plugin-activation-error_' . $plugin );
		$response = wp_remote_get(
			add_query_arg(
				array(
					'action'   => 'error_scrape',
					'plugin'   => $plugin,
					'_wpnonce' => $nonce,
				),
				admin_url( 'plugins.php' )
			),
			array( 'timeout' => 60 )
		);

		if ( is_wp_error( $response ) ) {
			// If it isn't possible to run the check, assume an error.
			throw new \Exception( $response->get_error_message() );
		}

		$code           = wp_remote_retrieve_response_code( $response );
		$body           = wp_remote_retrieve_body( $response );
		$this->no_error = 200 === $code;

		if ( str_contains( $body, 'wp-die-message' ) || 200 !== $code ) {
			$error = new \WP_Error(
				'new_version_error',
				sprintf(
					/* translators: %s: The name of the plugin. */
					__( 'The new version of %s contains an error' ),
					\get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin )['Name']
				)
			);
			throw new \Exception( $error->get_error_message() );
		}
	}

	/**
	 * Initializes handlers.
	 */
	private function initialize_handlers() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( array( $this, 'error_handler' ), ( E_ALL ^ $this->error_types ) );
		set_exception_handler( array( $this, 'exception_handler' ) );
	}

	/**
	 * Handles Errors.
	 */
	public function error_handler() {
		$this->handler_args['handler_error'] = 'Error Caught';
		$this->handler( $this->non_fatal_errors() );
	}

	/**
	 * Handles Exceptions.
	 */
	public function exception_handler() {
		$this->handler_args['handler_error'] = 'Exception Caught';
		$this->handler( false );
	}

	/**
	 * Handles errors by running Rollback.
	 *
	 * @param bool $skip If false, assume fatal and process, default false.
	 */
	private function handler( $skip = false ) {
		if ( $skip ) {
			return;
		}
		$this->fatals[] = $this->handler_args['hook_extra']['plugin'];

		$this->cron_rollback();

		// Let's sleep for a couple of seconds here.
		// After the error handler and before restarting updates.
		sleep( 2 );

		$this->restart_updates();
		$this->restart_core_updates();
		$this->send_update_result_email();
	}

	/**
	 * Skip (exit handler() early) for non-fatal errors or non-errors.
	 *
	 * @return bool
	 */
	private function non_fatal_errors() {
		$e                = error_get_last();
		$non_fatal_errors = ( ! empty( $e ) && $this->error_types !== $e['type'] );
		$skip             = is_plugin_active( $this->handler_args['hook_extra']['plugin'] ) || $this->no_error;
		$skip             = $skip ? $skip : $non_fatal_errors;

		return $skip;
	}

	/**
	 * Rolls back during cron.
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

		include_once $wp_filesystem->wp_plugins_dir() . 'rollback-update-failure/wp-admin/includes/class-wp-upgrader.php';
		$rollback_updater = new \Rollback_Update_Failure\WP_Upgrader();

		// Set private $temp_restores variable.
		$ref_temp_restores = new \ReflectionProperty( $rollback_updater, 'temp_restores' );
		$ref_temp_restores->setAccessible( true );
		$ref_temp_restores->setValue( $rollback_updater, $temp_backup );

		// Set private $temp_backups variable.
		$ref_temp_backups = new \ReflectionProperty( $rollback_updater, 'temp_backups' );
		$ref_temp_backups->setAccessible( true );
		$ref_temp_backups->setValue( $rollback_updater, $temp_backup );

		// Call Rollback's restore_temp_backup().
		$restore_temp_backup = new \ReflectionMethod( $rollback_updater, 'restore_temp_backup' );
		$restore_temp_backup->invoke( $rollback_updater );

		// Call Rollback's delete_temp_backup().
		$delete_temp_backup = new \ReflectionMethod( $rollback_updater, 'delete_temp_backup' );
		$delete_temp_backup->invoke( $rollback_updater );

		error_log( $this->handler_args['hook_extra']['plugin'] . ' rolled back' );
	}

	/**
	 * Restart update process for plugins that remain after a fatal.
	 */
	private function restart_updates() {
		$remaining_auto_updates = $this->get_remaining_auto_updates();

		if ( empty( $remaining_auto_updates ) ) {
			return;
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$upgrader->bulk_upgrade( $remaining_auto_updates );
	}

	/**
	 * Restart update process for core.
	 */
	private function restart_core_updates() {
		$core_update = find_core_auto_update();
		if ( $core_update ) {
			$core_updater = new \WP_Automatic_Updater();
			$core_updater->update( 'core', $core_update );
		}
	}

	/**
	 * Get array of non-fataling auto-updates remaining.
	 *
	 * @return array
	 */
	private function get_remaining_auto_updates() {
		if ( empty( $this->handler_args ) ) {
			return array();
		}

		// Get array of plugins set for auto-updating.
		$auto_updates    = (array) get_site_option( 'auto_update_plugins', array() );
		$current_plugins = array_keys( static::$current->response );

		// Get all auto-updating plugins that have updates available.
		$current_auto_updates = array_intersect( $auto_updates, $current_plugins );

		// Get array of non-fatal auto-updates remaining.
		$remaining_auto_updates = array_diff( $current_auto_updates, $this->processed, $this->fatals );

		$this->processed = array_unique( array_merge( $this->processed, $remaining_auto_updates ) );

		return $remaining_auto_updates;
	}

	/**
	 * Sends an email noting successful and failed updates.
	 */
	private function send_update_result_email() {
		add_filter( 'auto_plugin_theme_update_email', array( $this, 'props' ), 10, 4 );
		$successful = array();
		$failed     = array();

		/*
		 * Using `get_plugin_data()` instead has produced warnings/errors
		 * as the files may not be in place at this time.
		 */
		$plugins = get_plugins();

		foreach ( static::$current->response as $k => $update ) {
			$item = static::$current->response[ $k ];
			$name = $plugins[ $update->plugin ]['Name'];

			/*
			 * This appears to be the only way to get a plugin's older version
			 * at this stage of an auto-update when not implementing this
			 * feature directly in Core.
			 */
			$current_version = static::$current->checked[ $update->plugin ];

			/*
			 * The `current_version` property does not exist yet. Add it.
			 *
			 * `static::$current->response[ $k ]` is an instance of `stdClass`,
			 * so this should not fall victim to PHP 8.2's deprecation of
			 * dynamic properties.
			 */
			$item->current_version = $current_version;

			$plugin_result = (object) array(
				'name' => $name,
				'item' => $item,
			);

			$success = array_diff( $this->processed, $this->fatals );

			if ( in_array( $update->plugin, $success, true ) ) {
				$successful['plugin'][] = $plugin_result;
				continue;
			}

			if ( in_array( $update->plugin, $this->fatals, true ) ) {
				$failed['plugin'][] = $plugin_result;
			}
		}

		$automatic_upgrader      = new \WP_Automatic_Updater();
		$send_plugin_theme_email = new \ReflectionMethod( $automatic_upgrader, 'send_plugin_theme_email' );
		$send_plugin_theme_email->setAccessible( true );
		$send_plugin_theme_email->invoke( $automatic_upgrader, 'mixed', $successful, $failed );
	}

	/**
	 * Credit where credit is due.
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
	 *
	 * @return array
	 */
	public function props( $email, $type, $successful_updates, $failed_updates ) {
		if ( empty( $failed_updates ) ) {
			return $email;
		}
		$body   = explode( "\n", $email['body'] );
		$failed = __( 'These plugins failed to update or may have been rolled back due to detection of a fatal error:' );
		array_splice( $body, 6, 1, $failed );
		$body          = implode( "\n", $body );
		$email['body'] = $body;

		return $email;
	}
}
