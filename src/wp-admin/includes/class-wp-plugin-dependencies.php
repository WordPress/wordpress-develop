<?php
/**
 * Dependencies manager for plugins.
 *
 * @package dependencies-manager.
 * @since 5.9.0
 */

/**
 * Plugins dependencies manager.
 *
 * @since 5.9.0
 */
class WP_Plugin_Dependencies {

	/**
	 * The database option where we store the array of plugins that should be active
	 * but are not due to unmet dependencies.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @var string
	 */
	const PENDING_PLUGIN_ACTIVATIONS_OPTION = 'pending_plugin_activations';

	/**
	 * Installed plugins.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @var array[]
	 */
	private $installed_plugins;

	/**
	 * An array of admin notices to show.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @var array<int, array>
	 */
	private $notices = array();

	/**
	 * Array of parents for dependencies.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @var array<string, array>
	 */
	private $dependencies_parents = array();

	/**
	 * An array of plugin dependencies.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @var array<string, array>
	 */
	private $plugin_dependencies = array();

	/**
	 * An array of plugins participating in a circular dependencies loop.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @var array<string, bool>
	 */
	private $circular_dependencies = array();

	/**
	 * Constructor.
	 *
	 * Add hooks.
	 *
	 * @since 5.9.0
	 */
	public function __construct() {

		// Early exit if DISALLOW_FILE_MODS or DISALLOW_PLUGIN_DEPENDENCIES is enabled.
		if (
			( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ||
			( defined( 'DISALLOW_PLUGIN_DEPENDENCIES' ) && DISALLOW_PLUGIN_DEPENDENCIES )
		) {
			return;
		}

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
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
	}

	/**
	 * Get an array of installed plugins and set it in the object's $installed_plugins prop.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @return array[]
	 */
	private function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! $this->installed_plugins ) {
			// Get an array of all plugins.
			$this->installed_plugins = get_plugins();
		}

		return $this->installed_plugins;
	}

	/**
	 * Loop installed plugins and process dependencies.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @return void
	 */
	private function loop_installed_plugins() {
		foreach ( $this->installed_plugins as $file => $plugin ) {
			$this->maybe_process_plugin_dependencies( $file );
		}
	}

	/**
	 * Check plugin dependencies.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @param string $file The plugin file.
	 *
	 * @return void
	 */
	private function maybe_process_plugin_dependencies( $file ) {

		$plugin_is_active           = is_plugin_active( $file );
		$plugin_awaiting_activation = in_array( $file, $this->get_plugins_to_activate(), true );

		// Early return if the plugin is not active, or we don't want to activate it.
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

			// Parse dependency.
			$dependency = $this->parse_dependency( $file, $dependency );

			// Check if dependency is installed. If not, add notice.
			if ( ! $dependency['installed'] ) {
				$this->add_notice_install( get_plugin_data( WP_PLUGIN_DIR . '/' . $file ), $dependency );
				$dependencies_met = false;
			}

			// Check if dependency is active. If not, add notice.
			if ( ! $dependency['active'] ) {
				$this->add_notice_activate( get_plugin_data( WP_PLUGIN_DIR . '/' . $file ), $dependency );
				$dependencies_met = false;
			}
		}

		$in_circular_dependency = $this->in_circular_dependency( $file );

		if ( ! $dependencies_met ) {

			// Make sure plugin is deactivated when its dependencies are not met.
			if ( $plugin_is_active && ! $in_circular_dependency ) {
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
	 * @return array<string, array>
	 */
	public function get_plugin_dependencies( $file ) {
		if ( ! isset( $this->plugin_dependencies[ $file ] ) ) {
			$this->plugin_dependencies[ $file ] = array();
			$plugin_dependencies                = get_plugin_data( WP_PLUGIN_DIR . '/' . $file )['RequiresPlugins'];
			if ( empty( $plugin_dependencies ) ) {
				$this->plugin_dependencies[ $file ] = array();
				return array();
			}

			$plugin_dependencies = str_getcsv( $plugin_dependencies );
			foreach ( $plugin_dependencies as $dependency ) {
				$this->plugin_dependencies[ $file ][] = array( 'slug' => trim( $dependency ) );
			}
		}

		return $this->plugin_dependencies[ $file ];
	}

	/**
	 * Parses a dependency and adds name, file, installed and active args.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @param string               $plugin The plugin defining the dependency.
	 * @param array<string, mixed> $dependency A dependency.
	 *
	 * @return array<string, mixed> Returns the dependency with extra args.
	 */
	private function parse_dependency( $plugin, $dependency ) {
		$dependency['installed'] = false;
		$dependency['active']    = false;

		foreach ( $this->installed_plugins as $file => $installed_plugin ) {
			if ( dirname( $file ) === $dependency['slug'] ) {
				$dependency['file']      = $file;
				$dependency['name']      = get_plugin_data( WP_PLUGIN_DIR . '/' . $file )['Name'];
				$dependency['installed'] = true;
				if ( is_plugin_active( $file ) ) {
					$dependency['active'] = true;
				}
				break;
			}
		}

		// Add item to the $dependencies_parents array.
		if ( empty( $this->dependencies_parents[ $dependency['file'] ] ) ) {
			$this->dependencies_parents[ $dependency['file'] ] = array();
		}
		$this->dependencies_parents[ $dependency['file'] ][] = $plugin;

		return $dependency;
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
			echo '<div class="notice notice-' . esc_attr( $notice['type'] ) . ' plugin-dependencies"><p>' . wp_kses_post( $notice['content'] ) . '</p></div>';
		}
	}

	/**
	 * Cancel plugin's activation request.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @return void
	 */
	private function cancel_activation_request() {
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
	 * @param string[] $actions     An array of plugin action links.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins' directory.
	 *
	 * @return string[]
	 * @since 5.9.0
	 * @access public
	 *
	 */
	public function plugin_action_links( $actions, $plugin_file ) {

		$pending_activation     = in_array( $plugin_file, $this->get_plugins_to_activate(), true );
		$has_dependencies       = ! empty( $this->get_plugin_dependencies( $plugin_file ) );
		$in_circular_dependency = $this->in_circular_dependency( $plugin_file );

		// Remove deactivation link from dependencies.
		if ( ! empty( $this->dependencies_parents[ $plugin_file ] ) && ! $in_circular_dependency ) {
			unset( $actions['deactivate'] );
		}

		// On plugins with unmet dependencies that the user has already requested for the plugin's activation,
		// removes the activation link from its actions and adds a "Cancel pending activation" link in its place.
		if ( $pending_activation && $has_dependencies ) {
			unset( $actions['activate'] );
			if ( current_user_can( 'activate_plugin', $plugin_file ) ) {
				$cancel_activation = sprintf(
					'<a href="%s" class="cancel-activate unmet-dependencies" aria-label="%s">%s</a>',
					wp_nonce_url( 'plugins.php?action=cancel-activate&amp;plugin=' . rawurlencode( $plugin_file ), 'cancel-activate-plugin_' . $plugin_file ),
					/* translators: %s: Plugin name. */
					esc_attr( sprintf( _x( 'Cancel activation of %s', 'plugin' ), get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file )['Name'] ) ),
					__( 'Cancel activation request' )
				);

				// Use `array_merge` to make sure the action is added as the 1st item in the array.
				$actions = array_merge( array( 'cancel-activation' => $cancel_activation ), $actions );
			}
		}
		return $actions;
	}

	/**
	 * Get a plugin's parents.
	 *
	 * @since 5.9.0
	 * @access public
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins' directory.
	 *
	 * @return array[] Returns an array of parent plugins.
	 */
	public function get_parents( $plugin_file ) {
		if ( ! isset( $this->dependencies_parents[ $plugin_file ] ) ) {
			return array();
		}
		return $this->dependencies_parents[ $plugin_file ];
	}

	/**
	 * Add dependencies info in plugins.
	 *
	 * @since 5.9.0
	 * @access public
	 *
	 * @param string               $plugin_file Path to the plugin file relative to the plugins' directory.
	 * @param array<string, mixed> $plugin_data An array of plugin data.
	 *
	 * @return void
	 */
	public function after_plugin_row( $plugin_file, $plugin_data ) {

		$pending_activation     = in_array( $plugin_file, $this->get_plugins_to_activate(), true );
		$dependencies           = $this->get_plugin_dependencies( $plugin_file );
		$has_dependencies       = ! empty( $dependencies );
		$in_circular_dependency = $this->in_circular_dependency( $plugin_file );
		$is_plugin_active       = is_plugin_active( $plugin_file );
		$parent_dependencies    = $this->get_parents( $plugin_file );

		// Add extra info to dependencies.
		// If the plugin is active and a dependency for another plugin, it cannot be deactivated.
		if ( ! empty( $parent_dependencies ) && $is_plugin_active ) {
			$parents_names = array();
			foreach ( $parent_dependencies as $parent ) {
				$parents_names[] = get_plugin_data( WP_PLUGIN_DIR . '/' . $parent )['Name'];
			}

			$notice_contents = sprintf(
				/* translators: %1$s: plugin name. %2$s: Parent plugin name. */
				esc_html__( 'Plugin %1$s cannot be deactivated because it is a dependency for the "%2$s" plugin.' ),
				esc_html( $plugin_data['Name'] ),
				esc_html( $parents_names[0] )
			);
			if ( 1 < count( $parents_names ) ) {
				$notice_contents = sprintf(
					/* translators: %1$s: plugin name. %2$s: Parent plugin names, comma-separated. */
					esc_html__( 'Plugin %1$s cannot be deactivated because it is a dependency for the following plugins: %2$s.' ),
					esc_html( $plugin_data['Name'] ),
					esc_html( implode( ', ', $parents_names ) )
				);
			}

			$this->inline_plugin_row_notice( $notice_contents, 'info', true );
		}

		// Early return if the plugin doesn't have dependencies.
		if ( ! $has_dependencies ) {
			return;
		}

		// Add a notice if the plugin is pending activation.
		if ( $pending_activation ) {
			if ( $in_circular_dependency ) {
				$this->inline_plugin_row_notice(
					sprintf(
						/* translators: %s: plugin name. */
						esc_html__( 'Warning: Circular dependencies detected. Plugin "%s" has unmet dependencies. Please contact the plugin author to report this circular dependencies issue.' ),
						esc_html( $plugin_data['Name'] ),
						false
					),
					'warning'
				);
				return;
			}
			$this->inline_plugin_row_notice(
				sprintf(
					/* translators: %s: plugin name. */
					esc_html__( 'Plugin "%s" has unmet dependencies. Once all required plugins are installed the plugin will be automatically activated. Alternatively you can cancel the activation of this plugin by clicking on the "cancel activation request" link above.' ),
					esc_html( $plugin_data['Name'] )
				),
				'warning'
			);
			return;
		}

		// Add a notice if the plugin is inactive and has dependencies.
		if ( ! $is_plugin_active ) {
			$dependencies_human_readable = array();
			foreach ( $dependencies as $dependency ) {
				$plugin_file = $this->get_plugin_file_from_slug( $dependency['slug'] );
				if ( $plugin_file ) {
					$dependencies_human_readable[] = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file )['Name'];
				} else {
					$dependencies_human_readable[] = $dependency['slug'];
				}
			}
			$this->inline_plugin_row_notice(
				sprintf(
					/* translators: %1$s: plugin name. %2$s: plugin requirements, comma-separated. */
					esc_html__( 'Plugin "%1$s" depends on the following plugin(s): %2$s' ),
					esc_html( $plugin_data['Name'] ),
					esc_html( implode( ', ', $dependencies_human_readable ) )
				)
			);
		}
	}

	/**
	 * Generate the contents of an inline plugin row notice.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @param string $contents         Content of the plugin row notice.
	 * @param string $notice_type      Type of the plugin notice. Default: 'info'.
	 * @param bool   $is_plugin_active If true will set 'active' modifier CSS class on the notice.
	 *
	 * @return void
	 */
	private function inline_plugin_row_notice( $contents = '', $notice_type = 'info', $is_plugin_active = false ) {
		$tr_class = $is_plugin_active ? 'plugin-dependencies-tr active' : 'plugin-dependencies-tr';
		$colspan  = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => get_current_screen() ) )->get_column_count();
		?>
		<tr class="<?php echo esc_attr( $tr_class ); ?>">
			<td class="plugin-dependencies colspanchange" colspan="<?php echo esc_attr( $colspan ); ?>">
				<div class="dependencies-message notice inline notice-<?php echo esc_attr( $notice_type ); ?> notice-alt">
					<p><?php echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput This output is escaped beforehand. ?></p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Show a notice to install a dependency.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @param array<string, mixed> $plugin     The plugin calling the dependencies.
	 * @param array<string, mixed> $dependency The plugin slug.
	 *
	 * @return void
	 */
	private function add_notice_install( $plugin, $dependency ) {
		if ( ! function_exists( 'install_plugin_install_status' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		$this->notices[] = array(
			'type'    => 'warning',
			'content' => sprintf(
				/* translators: %1$s: The plugin we want to activate. %2$s: The slug of the plugin to install. %3$s: "Install & Activate" button. */
				__( 'Plugin "%1$s" depends on plugin "%2$s" to be installed. %3$s' ),
				$plugin['Name'],
				$dependency['slug'],
				/* translators: %s: Plugin name. */
				'<a href="' . esc_url( install_plugin_install_status( array( 'slug' => $dependency['slug'] ) )['url'] ) . '">' . sprintf( __( 'Install and activate "%s"' ), $dependency['slug'] ) . '</a>'
			),
		);
	}

	/**
	 * Show a notice to activate a dependency.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @param array<string, mixed> $plugin     The plugin calling the dependencies.
	 * @param array<string, mixed> $dependency The plugin slug.
	 *
	 * @return void
	 */
	private function add_notice_activate( $plugin, $dependency ) {
		$activate_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . rawurlencode( $dependency['file'] ) . '&amp;plugin_status=all', 'activate-plugin_' . $dependency['file'] );

		$this->notices[] = array(
			'type'    => 'warning',
			'content' => sprintf(
				/* translators: %1$s: The plugin we want to activate. %2$s: The name of the plugin to install. %3$s: "Activate" button. */
				__( 'Plugin "%1$s" depends on plugin "%2$s" to be activated. %3$s' ),
				$plugin['Name'],
				$dependency['name'],
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
	 * @return mixed
	 */
	public function get_plugins_to_activate() {
		return get_option( self::PENDING_PLUGIN_ACTIVATIONS_OPTION, array() );
	}

	/**
	 * Set plugin to the to-be-activated queue.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @param string $plugin The plugin file.
	 *
	 * @return void
	 */
	private function add_plugin_to_queue( $plugin ) {
		$queue = $this->get_plugins_to_activate();
		if ( in_array( $plugin, $queue, true ) ) {
			return;
		}
		$queue[] = $plugin;

		update_option( self::PENDING_PLUGIN_ACTIVATIONS_OPTION, $queue );
	}

	/**
	 * Remove plugin from the to-be-activated queue.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @param string $plugin The plugin file.
	 *
	 * @return void
	 */
	private function remove_plugin_from_queue( $plugin ) {
		$queue = $this->get_plugins_to_activate();
		if ( ! in_array( $plugin, $queue, true ) ) {
			return;
		}

		update_option( self::PENDING_PLUGIN_ACTIVATIONS_OPTION, array_diff( $queue, array( $plugin ) ) );
	}

	/**
	 * Check if a plugin is part of a circular dependencies loop.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @param string                   $plugin_file The plugin file.
	 * @param array<int|string, mixed> $previous    If this is a dependency of a dependency,
	 *                                              this array contains all previous levels of dependencies.
	 *
	 * @return bool
	 */
	private function in_circular_dependency( $plugin_file, $previous = array() ) {
		if ( isset( $this->circular_dependencies[ $plugin_file ] ) ) {
			return $this->circular_dependencies[ $plugin_file ];
		}

		if ( in_array( $plugin_file, $previous, true ) ) {
			$this->circular_dependencies[ $plugin_file ] = true;
		}

		$plugin_dependencies = $this->get_plugin_dependencies( $plugin_file );

		foreach ( $plugin_dependencies as $dependency ) {
			$dependency_file = $this->get_plugin_file_from_slug( $dependency['slug'] );
			if ( $dependency_file && $this->in_circular_dependency( $dependency_file, array_merge( $previous, array( $plugin_file ) ) ) ) {
				$this->circular_dependencies[ $plugin_file ]     = true;
				$this->circular_dependencies[ $dependency_file ] = true;
			}
		}

		if ( ! isset( $this->circular_dependencies[ $plugin_file ] ) ) {
			$this->circular_dependencies[ $plugin_file ] = false;
		}

		return $this->circular_dependencies[ $plugin_file ];
	}

	/**
	 * Get plugin file from its slug.
	 *
	 * @since 5.9.0
	 * @access private
	 *
	 * @param string $slug The plugin slug.
	 *
	 * @return string|false Returns the plugin file on success, false on failure.
	 */
	private function get_plugin_file_from_slug( $slug ) {
		$plugins = $this->get_plugins();
		foreach ( array_keys( $plugins ) as $plugin ) {
			if ( 0 === strpos( $plugin, "$slug/" ) || 0 === strpos( $plugin, "$slug\\" ) ) {
				return $plugin;
			}
		}
		return false;
	}
}

global $plugin_dependencies;
$plugin_dependencies = new WP_Plugin_Dependencies();
