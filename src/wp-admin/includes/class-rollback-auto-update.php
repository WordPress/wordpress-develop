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
	 * Stores instance of Plugin_Upgrader.
	 *
	 * @since 6.4.0
	 *
	 * @var Plugin_Upgrader
	 */
	private static $plugin_upgrader;

	/**
	 * Stores update data for plugins with pending updates.
	 *
	 * @since 6.4.0
	 *
	 * @var stdClass
	 */
	private static $plugin_updates;

	/**
	 * Stores update data for themes with pending updates.
	 *
	 * @since 6.4.0
	 *
	 * @var stdClass
	 */
	private static $theme_updates;

	/**
	 * Stores plugins that were active before being updated.
	 *
	 * Used to reactivate plugins that were deactivated before testing.
	 *
	 * @since 6.4.0
	 *
	 * @var string[]
	 */
	private static $previously_active_plugins = array();

	/**
	 * Stores error codes to be detected by the error handler.
	 *
	 * @since 6.4.0
	 *
	 * @var int
	 */
	private static $error_types = E_ERROR | E_PARSE | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

	/**
	 * Stores a map of error numbers to their constant name.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	private static $error_number_to_constant_map = array(
		2     => 'E_WARNING',
		8     => 'E_NOTICE',
		512   => 'E_USER_WARNING',
		1024  => 'E_USER_NOTICE',
		4096  => 'E_RECOVERABLE_ERROR',
		8192  => 'E_DEPRECATED',
		16384 => 'E_USER_DEPRECATED',
	);

	/**
	 * Stores regex patterns to match acceptable error messages.
	 *
	 * Some of these errors can occur when an active plugin is loaded into memory prior to being tested.
	 * They cannot be verified as errors in the plugin file, and must therefore be treated
	 * as false positives.
	 *
	 * Manual updates do not experience these errors because the plugin is deactivated before
	 * a browser redirect to a file that performs the checks. This class runs during cron,
	 * where a browser redirect is not possible.
	 *
	 * Other errors may not be severe enough to roll back the plugin.
	 *
	 * @since 6.4.0
	 *
	 * @var string[]
	 */
	private static $acceptable_errors = array(
		// False positives.

		// A class is defined in the main plugin file.
		'Cannot declare class',
		// A constant is defined in the main plugin file.
		'Constant([ _A-Z]+)already defined',
		// A function is defined in the main plugin file.
		'Cannot redeclare',

		// Errors that should not cause the plugin to be rolled back.

		// An existing directory is created in the main plugin file.
		'mkdir\(\): File exists',

		// PHP8 deprecations.
		'Passing null to parameter(.*)of type(.*)is deprecated',
		'Trying to access array offset on value of type null',
		'ReturnTypeWillChange',
	);

	/**
	 * Stores the filepath of the current plugin being checked.
	 *
	 * The filepath is relative to the plugins directory.
	 *
	 * @since 6.4.0
	 *
	 * @var string
	 */
	private $current_plugin = '';

	/**
	 * Stores plugins and themes that have been processed.
	 *
	 * @since 6.4.0
	 *
	 * @var string[]
	 */
	private static $processed = array();

	/**
	 * Stores plugins that were rolled back.
	 *
	 * @since 6.4.0
	 *
	 * @var string[]
	 */
	private static $rolled_back = array();

	/**
	 * Stores whether an email was sent.
	 *
	 * @since 6.4.0
	 *
	 * @var bool
	 */
	private static $email_was_sent = false;

	/**
	 * Checks the updated plugin for errors.
	 *
	 * @since 6.4.0
	 *
	 * @param array           $plugin   Current plugin filepath from $hook_extra.
	 * @param Plugin_Upgrader $upgrader Plugin_Upgrader instance.
	 */
	public function check_plugin_for_errors( $plugin, $upgrader ) {
		// Already processed.
		if ( in_array( $plugin, array_diff( self::$processed, self::$rolled_back ), true ) ) {
			return;
		}

		self::$plugin_updates = get_site_transient( 'update_plugins' );
		self::$theme_updates  = get_site_transient( 'update_themes' );

		/*
		 * This possibly helps to avoid a potential race condition on servers that may start to
		 * process the next plugin for auto-updating before the handler can pick up an error from
		 * the previously processed plugin.
		 */
		sleep( 2 );

		static::$plugin_upgrader = $upgrader;
		$this->current_plugin    = $plugin;
		self::$processed[]       = $this->current_plugin;

		// Register exception and shutdown handlers.
		$this->initialize_handlers();

		if ( is_plugin_active( $this->current_plugin ) ) {
			self::$previously_active_plugins[] = $this->current_plugin;
			deactivate_plugins( $this->current_plugin );
		}

		/*
		 * Working parts of plugin_sandbox_scrape().
		 * Must use 'include()' instead of 'include_once()' to surface errors.
		 */
		wp_register_plugin_realpath( WP_PLUGIN_DIR . '/' . $this->current_plugin );
		include WP_PLUGIN_DIR . '/' . $this->current_plugin;

		activate_plugins( self::$previously_active_plugins );
	}

	/**
	 * Sets custom handlers for errors, exceptions and shutdown.
	 *
	 * @since 6.4.0
	 */
	private function initialize_handlers() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( array( $this, 'handle_error' ), ( E_ALL ^ self::$error_types ) );
		set_exception_handler( array( $this, 'handle_exception' ) );
		register_shutdown_function( array( $this, 'resume_or_roll_back' ) );
	}

	/**
	 * Checks whether an error is acceptable. otherwise rolls the plugin back to its
	 * temporary backup.
	 *
	 * @since 6.4.0
	 *
	 * @param int    $number  The error number.
	 * @param string $message The error message.
	 */
	public function handle_error( $number, $message ) {
		if ( $this->is_acceptable_error( $message ) ) {
			return;
		}

		$this->rollback( self::$error_number_to_constant_map[ $number ], $message );
	}

	/**
	 * Sends the plugin to be rolled back to its temporary backup
	 * due to an exception.
	 *
	 * @since 6.4.0
	 *
	 * @param Throwable $exception Exception object.
	 */
	public function handle_exception( Throwable $exception ) {
		$this->rollback( 'Exception', $exception->getMessage() );
	}

	/**
	 * Determines whether to resume updates or roll back the plugin on shutdown.
	 *
	 * @since 6.4.0
	 */
	public function resume_or_roll_back() {
		$last_error = error_get_last();
		if ( null === $last_error || $this->is_acceptable_error( $last_error['message'] ) ) {
			$this->resume_updates_and_send_email();
			exit();
		}

		$type = self::$error_number_to_constant_map[ $last_error['type'] ];
		$this->rollback( $type, $last_error['message'] );
	}

	/**
	 * Determines whether an error is acceptable.
	 *
	 * Acceptable errors are those that may be triggered when
	 * a plugin is already in memory, or errors that are not
	 * severe enough to cause a plugin to be rolled back.
	 *
	 * @since 6.4.0
	 *
	 * @param string $message Error message from handler.
	 * @return bool Whether the error is acceptable.
	 */
	private function is_acceptable_error( $message ) {
		return (bool) preg_match( '/(' . implode( '|', static::$acceptable_errors ) . ')/', $message, $matches );
	}

	/**
	 * Rolls back the plugin to its temporary backup.
	 *
	 * @since 6.4.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param string $error_type    The error type.
	 * @param string $error_message The error message.
	 */
	private function rollback( $error_type, $error_message ) {
		global $wp_filesystem;

		if ( WP_DEBUG ) {
			trigger_error(
				"$error_type - $error_message",
				E_USER_WARNING
			);
		}

		if ( in_array( $this->current_plugin, self::$rolled_back, true ) ) {
			return;
		}
		self::$rolled_back[] = $this->current_plugin;

		$temp_backup = array(
			'temp_backup' => array(
				'dir'  => 'plugins',
				'slug' => dirname( $this->current_plugin ),
				'src'  => $wp_filesystem->wp_plugins_dir(),
			),
		);

		/*
		 * The WP_Upgrader class uses these properties with private visibility.
		 *
		 * As this class is effectively a bridge between WP_Automatic_Updater
		 * and WP_Upgrader, the Reflection API is used to temporarily make these
		 * properties accessible.
		 *
		 * The plugin's temporary backup is added, before the rollback is performed.
		 */
		$rollback_updater = new WP_Upgrader();

		$temp_restores = new ReflectionProperty( $rollback_updater, 'temp_restores' );
		$temp_restores->setAccessible( true );
		$temp_restores->setValue( $rollback_updater, $temp_backup );
		$temp_restores->setAccessible( false );

		$temp_backups = new ReflectionProperty( $rollback_updater, 'temp_backups' );
		$temp_backups->setAccessible( true );
		$temp_backups->setValue( $rollback_updater, $temp_backup );
		$temp_backups->setAccessible( false );

		// Perform the rollback.
		$rollback_updater->restore_temp_backup();
		$rollback_updater->delete_temp_backup();

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

		$this->resume_updates_and_send_email();
	}

	/**
	 * Resumes the update process for plugins that remain after one is rolled back.
	 *
	 * @since 6.4.0
	 */
	private function resume_updates() {
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
	 * Resumes a core update, if present.
	 *
	 * @since 6.4.0
	 */
	private function resume_core_update() {
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
	 * Gets remaining plugin auto-updates.
	 *
	 * Excludes plugins that were rolled back.
	 *
	 * @since 6.4.0
	 *
	 * @return array The remaining updates, excluding plugins that were processed or rolled back.
	 */
	private function get_remaining_plugin_auto_updates() {
		// Get array of plugins set for auto-updating.
		$auto_updates    = (array) get_site_option( 'auto_update_plugins', array() );
		$current_plugins = array_keys( self::$plugin_updates->response );

		// Get all auto-updating plugins that have updates available.
		$current_auto_updates = array_intersect( $auto_updates, $current_plugins );

		// Get array of remaining auto-updates, excluding plugins that were processed or rolled back.
		$remaining_auto_updates = array_diff( $current_auto_updates, self::$processed, self::$rolled_back );

		return $remaining_auto_updates;
	}

	/**
	 * Gets remaining theme auto-updates.
	 *
	 * @since 6.4.0
	 *
	 * @return array An array of remaining theme auto-updates.
	 */
	private function get_remaining_theme_auto_updates() {
		// Get array of themes set for auto-updating.
		$auto_updates   = (array) get_site_option( 'auto_update_themes', array() );
		$current_themes = array_keys( self::$theme_updates->response );

		// Get all auto-updating themes that have updates available.
		$remaining_auto_updates = array_intersect( $auto_updates, $current_themes );

		return $remaining_auto_updates;
	}

	/**
	 * Resumes updates.
	 *
	 * Once updates have completed, previously active plugins will be reactivated,
	 * and an email with update results will be sent.
	 *
	 * @since 6.4.0
	 */
	private function resume_updates_and_send_email() {
		$this->resume_updates();
		$this->resume_core_update();

		/*
		 * The following commands only run once after the above commands have completed.
		 * Specifically, 'resume_updates()' will re-run until there are no further
		 * plugin or themes updates remaining.
		 */
		activate_plugins( self::$previously_active_plugins );

		$this->send_update_result_email();
	}

	/**
	 * Sends an email noting successful and failed updates, if one was not already sent.
	 *
	 * @since 6.4.0
	 */
	private function send_update_result_email() {
		if ( self::$email_was_sent ) {
			return;
		}

		$result         = true;
		$update_results = array();

		$plugin_theme_email_data = array(
			'plugin' => array( 'data' => get_plugins() ),
			'theme'  => array( 'data' => wp_get_themes() ),
		);

		foreach ( $plugin_theme_email_data as $type => $data ) {
			$current_items = 'plugin' === $type ? self::$plugin_updates : self::$theme_updates;

			foreach ( array_keys( $current_items->response ) as $file ) {
				if ( ! in_array( $file, self::$processed, true ) ) {
					continue;
				}

				$item            = $current_items->response[ $file ];
				$current_version = property_exists( $current_items, 'checked' ) ? $current_items->checked[ $file ] : __( 'unavailable' );
				$success         = array_diff( self::$processed, self::$rolled_back );

				if ( in_array( $file, $success, true ) ) {
					$result = true;
				} elseif ( in_array( $file, self::$rolled_back, true ) ) {
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

		// TODO: remove for PR.
		delete_option( 'auto_plugin_theme_update_emails' );

		add_filter( 'auto_plugin_theme_update_email', array( $this, 'append_auto_update_failure_message_to_email' ), 10, 4 );

		/*
		 * The WP_Automatic_Updater::after_plugin_theme_update() method has protected visibility.
		 *
		 * As this class is effectively a bridge between WP_Automatic_Updater and WP_Upgrader,
		 * the Reflection API is used to temporarily make this method accessible.
		 */
		$automatic_upgrader      = new WP_Automatic_Updater();
		$send_plugin_theme_email = new ReflectionMethod( $automatic_upgrader, 'after_plugin_theme_update' );
		$send_plugin_theme_email->setAccessible( true );
		$send_plugin_theme_email->invoke( $automatic_upgrader, $update_results );
		$send_plugin_theme_email->setAccessible( false );

		remove_filter( 'auto_plugin_theme_update_email', array( $this, 'append_auto_update_failure_message_to_email' ), 10 );

		self::$email_was_sent = true;
	}

	/**
	 * Inserts an auto-update failure message to the email being sent.
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
	 * @return array The email arguments with the auto-update failure message appended.
	 */
	public function append_auto_update_failure_message_to_email( $email, $type, $successful_updates, $failed_updates ) {
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
