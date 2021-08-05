<?php
/**
 * Dependencies manager for plugins.
 *
 * @package dependencies-manager.
 * @since 1.0
 */

/**
 * Plugins dependencies manager.
 *
 * @since 1.0.0
 */
class WP_Plugin_Dependencies {

	/**
	 * The database option where we store the array of plugins that should be active
	 * but are not due to unmet dependencies.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @var string
	 */
	protected $pending_plugin_activations_option = 'pending_plugin_activations';

	/**
	 * Installed plugins.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @var array
	 */
	protected $installed_plugins;

	/**
	 * An array of admin notices to show.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @var array
	 */
	protected $notices = array();

	/**
	 * Array of parents for dependencies.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @var array
	 */
	protected $dependencies_parents = array();

	/**
	 * An array of plugin dependencies.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @var array
	 */
	protected $plugin_dependencies = array();

	/**
	 * Constructor.
	 *
	 * Add hooks.
	 *
	 * @since 5.9.0
	 */
	public function __construct() {
		// Get an array of installed plugins and set it in the object's $installed_plugins prop.
		$this->get_plugins();

		// Add a hook to allow canceling an activation request.
		$this->cancel_activation_request();

		// Go through installed plugins and process their dependencies.
		$this->loop_installed_plugins();

		// Add the admin notices.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Add extra info below plugins that are dependencies.
		add_action( 'after_plugin_row', array( $this, 'after_plugin_row' ), 10, 2 );

		// Filter available plugin actions.
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 4 );
	}

	/**
	 * Get an array of installed plugins and set it in the object's $installed_plugins prop.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @return void
	 */
	protected function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Get an array of all plugins.
		$this->installed_plugins = get_plugins();
	}

	/**
	 * Loop installed plugins and process dependencies.
	 *
	 * @since 5.9.0
	 * @access public
	 *
	 * @return void
	 */
	public function loop_installed_plugins() {
		foreach ( $this->installed_plugins as $file => $plugin ) {
			$this->maybe_process_plugin_dependencies( $file );
		}
	}

	/**
	 * Check plugin dependencies.
	 *
	 * @since 5.9.0
	 * @access public
	 *
	 * @param string $file The plugin file.
	 *
	 * @return void
	 */
	public function maybe_process_plugin_dependencies( $file ) {

		$plugin_is_active           = is_plugin_active( $file );
		$plugin_awaiting_activation = in_array( $file, $this->get_plugins_to_activate(), true );

		// Early return if the plugin is not active or we don't want to activate it.
		if ( ! $plugin_is_active && ! $plugin_awaiting_activation ) {
			return;
		}

		// Get the dependencies.
		$dependencies = $this->get_plugin_dependencies( $file );

		// Early return if there are no dependencies.
		if ( empty( $dependencies ) ) {
			return;
		}

		// Loop dependencies.
		$dependencies_met = true;
		foreach ( $dependencies as $dependency ) {

			// Set $dependencies_met to false if one of the dependencies is not met.
			if ( ! $this->process_plugin_dependency( $file, $dependency ) ) {
				$dependencies_met = false;
			}
		}

		if ( ! $dependencies_met ) {

			// Make sure plugin is deactivated when its dependencies are not met.
			if ( $plugin_is_active ) {
				deactivate_plugins( $file );
			}

			// Add plugin to queue of plugins to be activated.
			$this->add_plugin_to_queue( $file );

		} elseif ( $plugin_awaiting_activation ) {
			activate_plugin( $file );
			$this->remove_plugin_from_queue( $file );
		}
	}

	/**
	 * Get an array of dependencies.
	 *
	 * @since 5.9.0
	 * @access public
	 *
	 * @param string $file The plugin file.
	 *
	 * @return array
	 */
	public function get_plugin_dependencies( $file ) {

		if ( ! isset( $this->plugin_dependencies[ $file ] ) ) {
			// Get the plugin directory.
			$plugin_dir = dirname( WP_PLUGIN_DIR . '/' . $file );

			$this->plugin_dependencies[ $file ] = array();
			if ( file_exists( "$plugin_dir/dependencies.json" ) ) {
				$this->plugin_dependencies[ $file ] = json_decode( file_get_contents( "$plugin_dir/dependencies.json" ) );
			}
		}

		return $this->plugin_dependencies[ $file ];
	}

	/**
	 * Processes a plugin dependency.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @param string   $plugin     The plugin defining the dependency.
	 * @param stdClass $dependency A dependency.
	 *
	 * @return bool
	 */
	protected function process_plugin_dependency( $plugin, $dependency ) {
		$dependency_is_installed = false;
		$dependency_is_active    = false;
		$dependency_needs_update = false;

		foreach ( $this->installed_plugins as $file => $installed_plugin ) {
			if ( dirname( $file ) === $dependency->slug ) {
				$dependency->file        = $file;
				$dependency_is_installed = true;
				if ( is_plugin_active( $file ) ) {
					$dependency_is_active = true;
				}
				$installed_version = get_plugin_data( WP_PLUGIN_DIR . '/' . $file )['Version'];
				if ( ! empty( $installed_version ) && ! empty( $dependency->version ) && version_compare( $installed_version, $dependency->version, '<' ) ) {
					$dependency_needs_update = true;
				}

				break;
			}
		}

		// If the dependency is not installed, install it, otherwise activate it.
		if ( ! $dependency_is_installed ) {
			$this->add_notice_install( get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin ), $dependency );
			return false;
		}

		// If the installed version is lower than the required version, update it.
		if ( $dependency_needs_update ) {
			$this->add_notice_update( get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin ), $dependency );
			return false;
		}

		// If the plugin is not activated, activate it.
		if ( ! $dependency_is_active ) {
			$this->add_notice_activate( get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin ), $dependency );
			return false;
		}

		// Add item to the $dependencies_parents array.
		if ( empty( $this->dependencies_parents[ $dependency->file ] ) ) {
			$this->dependencies_parents[ $dependency->file ] = array();
		}
		$this->dependencies_parents[ $dependency->file ][] = $plugin;

		return true;
	}

	/**
	 * Add notices.
	 *
	 * @since 5.9.0
	 * @access public
	 *
	 * @return void
	 */
	public function admin_notices() {
		// Early return if there are no notices to display.
		if ( empty( $this->notices ) ) {
			return;
		}

		foreach ( $this->notices as $notice ) {
			echo '<div class="notice notice-warning plugin-dependencies"><p>' . wp_kses_post( $notice['content'] ) . '</p></div>';
		}
	}

	/**
	 * Cancel plugin's activation request.
	 *
	 * @since 5.9.0
	 * @access public
	 *
	 * @return void
	 */
	public function cancel_activation_request() {
		if ( empty( $_GET['action'] ) || 'cancel-activate' !== $_GET['action'] || empty( $_GET['plugin'] ) ) {
			return;
		}
		$file = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
		check_admin_referer( 'cancel-activate-plugin_' . $file );

		$this->remove_plugin_from_queue( $file );
	}

	/**
	 * Filters the action links displayed for each plugin in the Plugins list table.
	 *
	 * @since 5.9.0
	 * @access public
	 *
	 * @param string[] $actions     An array of plugin action links.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array    $plugin_data An array of plugin data. See `get_plugin_data()`.
	 *
	 * @return string[]
	 */
	public function plugin_action_links( $actions, $plugin_file, $plugin_data ) {

		// Remove deactivation link from dependencies.
		if ( ! empty( $this->dependencies_parents[ $plugin_file ] ) ) {
			unset( $actions['deactivate'] );
		}

		// On plugins with unmet dependencies that the user has already requested for the plugin's activation,
		// removes the activation link from its actions and add a "Cancel pending activation" link in its place.
		if ( in_array( $plugin_file, $this->get_plugins_to_activate(), true ) && ! empty( $this->get_plugin_dependencies( $plugin_file ) ) ) {
			unset( $actions['activate'] );
			if ( current_user_can( 'activate_plugin', $plugin_file ) ) {
				$cancel_activation = sprintf(
					'<a href="%s" class="cancel-activate unmet-dependencies" aria-label="%s">%s</a>',
					wp_nonce_url( 'plugins.php?action=cancel-activate&amp;plugin=' . rawurlencode( $plugin_file ), 'cancel-activate-plugin_' . $plugin_file ),
					/* translators: %s: Plugin name. */
					esc_attr( sprintf( _x( 'Cancel activation of %s', 'plugin' ), get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file )['Name'] ) ),
					__( 'Cancel activation request' )
				);

				$actions = array_merge( array( 'cancel-activation' => $cancel_activation ), $actions );
			}
		}
		return $actions;
	}

	/**
	 * Add dependencies info in plugins.
	 *
	 * @since 5.9.0
	 * @access public
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 *
	 * @return void
	 */
	public function after_plugin_row( $plugin_file, $plugin_data ) {

		// Add extra info to dependencies.
		if ( ! empty( $this->dependencies_parents[ $plugin_file ] ) ) {
			$parents_names = array();
			foreach ( $this->dependencies_parents[ $plugin_file ] as $parent ) {
				$parents_names[] = get_plugin_data( WP_PLUGIN_DIR . '/' . $parent )['Name'];
			}

			$style = is_rtl() ? 'border-top:none;border-left:none' : 'border-top:none;border-right:none';
			echo '<tr><td colspan="5" class="notice notice-info notice-alt" style="' . esc_attr( $style ) . '">';
			if ( 1 < count( $parents_names ) ) {
				printf(
					/* translators: %1$s: plugin name. %2$s: Parent plugin names, comma-separated. */
					esc_html__( 'Plugin %1$s is a dependency for the following plugins: %2$s.' ),
					esc_html( $plugin_data['Name'] ),
					esc_html( implode( ', ', $parents_names ) )
				);
			} else {
				printf(
					/* translators: %1$s: plugin name. %2$s: Parent plugin name. */
					esc_html__( 'Plugin %1$s is a dependency for the "%2$s" plugin.' ),
					esc_html( $plugin_data['Name'] ),
					esc_html( $parents_names[0] )
				);
			}
			echo '</td></tr>';
		}

		// Add extra info to parents with unmet dependencies.
		if ( in_array( $plugin_file, $this->get_plugins_to_activate(), true ) && ! empty( $this->get_plugin_dependencies( $plugin_file ) ) ) {
			$style = is_rtl() ? 'border-top:none;border-left:none' : 'border-top:none;border-right:none';
			echo '<tr><td colspan="5" class="notice notice-warning notice-alt" style="' . esc_attr( $style ) . '">';
			printf(
				/* translators: %s: plugin name. */
				esc_html__( 'Plugin "%s" has unmet dependencies. Once all required plugins are installed the plugin will be automatically activated. Alternatively you can cancel the activation of this plugin by clicking on the "cancel activation request" link above.' ),
				esc_html( $plugin_data['Name'] )
			);
			echo '</td></tr>';
		}
	}

	/**
	 * Show a notice to install a dependency.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @param array    $plugin     The plugin calling the dependencies.
	 * @param stdClass $dependency The plugin slug.
	 *
	 * @return void
	 */
	protected function add_notice_install( $plugin, $dependency ) {
		if ( ! function_exists( 'install_plugin_install_status' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		$this->notices[] = array(
			'content' => sprintf(
				/* translators: %1$s: The plugin we want to activate. %2$s: The name of the plugin to install. %3$s: "Install & Activate" button. */
				__( 'Plugin "%1$s" depends on plugin "%2$s" to be installed. %3$s' ),
				$plugin['Name'],
				$dependency->name,
				/* translators: %s: Plugin name. */
				'<a href="' . esc_url( install_plugin_install_status( array( 'slug' => $dependency->name ) )['url'] ) . '">' . sprintf( __( 'Install and activate %s' ), $dependency->name ) . '</a>'
			),
		);
	}

	/**
	 * Show a notice to update a dependency.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @param array    $plugin     The plugin calling the dependencies.
	 * @param stdClass $dependency The plugin slug.
	 *
	 * @return void
	 */
	protected function add_notice_update( $plugin, $dependency ) {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$this->notices[] = array(
			'content' => sprintf(
				/* translators: %1$s: The plugin we want to activate. %2$s: The name of the plugin to install. %3$s: Minimum required version. %4$s: Currently installed version. %5$s: Update URL. */
				__( 'Plugin "%1$s" depends on plugin "%2$s" version %3$s or higher to be installed, and you currently have version %4$s installed. <a href="%5$s">Update %2$s</a>' ),
				$plugin['Name'],
				$dependency->name,
				$dependency->version,
				get_plugin_data( WP_PLUGIN_DIR . '/' . $dependency->file )['Version'],
				wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . $dependency->file ), 'upgrade-plugin_' . $dependency->file )
			),
		);
	}

	/**
	 * Show a notice to activate a dependency.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @param array    $plugin     The plugin calling the dependencies.
	 * @param stdClass $dependency The plugin slug.
	 *
	 * @return void
	 */
	protected function add_notice_activate( $plugin, $dependency ) {
		$activate_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . rawurlencode( $dependency->file ) . '&amp;plugin_status=all', 'activate-plugin_' . $dependency->file );

		$this->notices[] = array(
			'content' => sprintf(
				/* translators: %1$s: The plugin we want to activate. %2$s: The name of the plugin to install. %3$s: "Activate" button. */
				__( 'Plugin "%1$s" depends on plugin "%2$s" to be activated. %3$s' ),
				$plugin['Name'],
				$dependency->name,
				'<a href="' . $activate_url . '">' . __( 'Activate plugin' ) . '</a>'
			),
		);
	}

	/**
	 * Get an array of plugins that should be activated but are not,
	 * due to missing/unmet dependencies.
	 *
	 * @since 5.9.0
	 * @access public
	 *
	 * @return array
	 */
	public function get_plugins_to_activate() {
		return get_option( $this->pending_plugin_activations_option, array() );
	}

	/**
	 * Set plugin to the to-be-activated queue.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @param string $plugin The plugin file.
	 *
	 * @return bool
	 */
	protected function add_plugin_to_queue( $plugin ) {
		$queue = $this->get_plugins_to_activate();
		if ( in_array( $plugin, $queue, true ) ) {
			return true;
		}
		$queue[] = $plugin;
		return update_option( $this->pending_plugin_activations_option, $queue );
	}

	/**
	 * Remove plugin from the to-be-activated queue.
	 *
	 * @since 5.9.0
	 * @access protected
	 *
	 * @param string $plugin The plugin file.
	 *
	 * @return bool
	 */
	protected function remove_plugin_from_queue( $plugin ) {
		$queue = $this->get_plugins_to_activate();
		if ( ! in_array( $plugin, $queue, true ) ) {
			return true;
		}
		return update_option( $this->pending_plugin_activations_option, array_diff( $queue, array( $plugin ) ) );
	}
}

global $plugin_dependencies;
$plugin_dependencies = new WP_Plugin_Dependencies();
