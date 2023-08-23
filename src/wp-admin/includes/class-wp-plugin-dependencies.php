<?php
/**
 * WordPress Plugin Administration API: WP_Plugin_Dependencies class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 6.4.0
 */

/**
 * Core class for installing plugin dependencies.
 *
 * It is designed to add plugin dependencies as designated in the
 * `Requires Plugins` header to a new view in the plugins install page.
 */
class WP_Plugin_Dependencies {

	/**
	 * Holds 'get_plugins()'.
	 *
	 * @var array
	 */
	protected $plugins;

	/**
	 * Holds an array of sanitized plugin dependency slugs.
	 *
	 * @var array
	 */
	protected $slugs;

	/**
	 * Holds 'plugins_api()' data for plugin dependencies.
	 *
	 * @var array
	 */
	protected $plugin_data;

	/**
	 * Holds plugin filepath of plugins with dependencies.
	 *
	 * @var array
	 */
	protected $requires_plugins;

	/**
	 * Holds 'plugin_api()' data for uninstalled plugin dependencies.
	 *
	 * @var array
	 */
	protected $plugin_api_data;

	/**
	 * Holds plugin directory names to compare with cache.
	 *
	 * @var array
	 */
	protected $plugin_dirnames = array();

	/**
	 * Holds cached plugin directory names.
	 *
	 * @var array
	 */
	protected $plugin_dirnames_cache = array();

	/**
	 * Holds data for plugin card.
	 *
	 * @var array
	 */
	protected static $plugin_card_data = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->requires_plugins = array();
		$this->plugin_data      = array();
	}

	/**
	 * Initialize, load filters, and get started.
	 *
	 * @return void
	 */
	public function start() {
		if ( is_admin() ) {
			add_filter( 'plugin_install_description', array( $this, 'plugin_install_description_uninstalled' ), 10, 2 );
			add_filter( 'plugin_install_description', array( $this, 'set_plugin_card_data' ), 10, 1 );
			add_filter( 'plugin_install_action_links', array( $this, 'empty_package_remove_install_button' ), 10, 2 );

			add_action( 'admin_init', array( $this, 'modify_plugin_row' ), 15 );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );

			add_action( 'wp_ajax_check_plugin_dependencies', array( $this, 'check_plugin_dependencies' ) );
		}

		$required_headers = $this->parse_plugin_headers();
		$this->slugs      = $this->sanitize_required_headers( $required_headers );
		$this->get_dot_org_data();
		$this->deactivate_unmet_dependencies();
	}

	/**
	 * Run get_plugins() and store result.
	 *
	 * @return array
	 */
	protected function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$this->plugins = get_plugins();

		return $this->plugins;
	}

	/**
	 * Parse 'Requires Plugins' header.
	 * Store result with dependent plugin.
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @return array
	 */
	protected function parse_plugin_headers() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$this->get_plugins();
		$all_requires_headers = array();
		foreach ( array_keys( $this->plugins ) as $plugin ) {
			$temp_requires    = array();
			$requires_plugins = get_file_data( $wp_filesystem->wp_plugins_dir() . '/' . $plugin, array( 'RequiresPlugins' => 'Requires Plugins' ) );
			if ( ! empty( $requires_plugins['RequiresPlugins'] ) ) {
				$all_requires_headers[ $plugin ] = $requires_plugins;
				$temp_requires[ $plugin ]        = $requires_plugins;
				$sanitized_requires_slugs        = implode( ',', $this->sanitize_required_headers( $temp_requires ) );

				$this->requires_plugins[ $plugin ]['RequiresPlugins'] = $sanitized_requires_slugs;
			}
		}

		return $all_requires_headers;
	}

	/**
	 * Sanitize headers.
	 *
	 * @param array $required_headers Array of required plugin headers.
	 * @return array
	 */
	protected function sanitize_required_headers( $required_headers ) {
		$all_slugs = array();
		foreach ( $required_headers as $key => $headers ) {
			$sanitized_slugs = array();
			$exploded        = explode( ',', $headers['RequiresPlugins'] );
			foreach ( $exploded as $slug ) {
				$slug = trim( $slug );

				/**
				 * Filter $slug to allow for slug switching
				 * possibly between non-premium and premium plugins.
				 *
				 * @param array
				 */
				$slug = apply_filters( 'wp_plugin_dependencies_slug', $slug );

				// Match to dot org slug format.
				if ( preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/mu', $slug ) ) {
					$sanitized_slugs[] = $slug;
				}
			}
			$sanitized_slugs = array_unique( $sanitized_slugs );

			$this->plugins[ $key ]['RequiresPlugins'] = $sanitized_slugs;
			$all_slugs                                = array_merge( $all_slugs, $sanitized_slugs );
		}
		$all_slugs = array_unique( $all_slugs );
		sort( $all_slugs );

		return $all_slugs;
	}

	/**
	 * Deactivate plugins with unmet dependencies.
	 *
	 * @return void
	 */
	protected function deactivate_unmet_dependencies() {
		$dependencies        = $this->get_dependency_filepaths();
		$deactivate_requires = array();

		foreach ( array_keys( $this->requires_plugins ) as $requires ) {
			if ( array_key_exists( $requires, $this->plugins ) ) {
				$plugin_dependencies = $this->plugins[ $requires ]['RequiresPlugins'];
				foreach ( $plugin_dependencies as $plugin_dependency ) {
					if ( is_plugin_active( $requires ) ) {
						if ( ! $dependencies[ $plugin_dependency ] || is_plugin_inactive( $dependencies[ $plugin_dependency ] ) ) {
							$deactivate_requires[] = $requires;
						}
					}
				}
			}
		}

		$deactivate_requires = array_unique( $deactivate_requires );
		foreach ( $deactivate_requires as $key => $plugin ) {
			if ( in_array( $plugin, $this->get_circular_dependencies(), true ) ) {
				unset( $deactivate_requires[ $key ] );
			}
		}
		deactivate_plugins( $deactivate_requires );
		set_site_transient( 'wp_plugin_dependencies_deactivate_plugins', $deactivate_requires, 10 );
	}

	/**
	 * Get plugin data from WordPress API.
	 * Store result in $this->plugin_data.
	 *
	 * @global $pagenow Current page.
	 *
	 * @return void
	 */
	public function get_dot_org_data() {
		global $pagenow;

		if ( ! wp_doing_ajax() && ! in_array( $pagenow, array( 'plugin-install.php', 'plugins.php' ), true ) ) {
			return;
		}

		$this->plugin_data = (array) get_site_transient( 'wp_plugin_dependencies_plugin_data' );
		foreach ( $this->slugs as $slug ) {
			// Set transient for individual data, remove from $this->plugin_data if transient expired.
			if ( ! get_site_transient( "wp_plugin_dependencies_plugin_timeout_{$slug}" ) ) {
				unset( $this->plugin_data[ $slug ] );
				set_site_transient( "wp_plugin_dependencies_plugin_timeout_{$slug}", true, 12 * HOUR_IN_SECONDS );
			}

			// Check plugins API if generic data present.
			if ( empty( $this->plugin_data[ $slug ]['last_updated'] ) ) {
				unset( $this->plugin_data[ $slug ] );
			}

			// Don't hit plugins API if data exists.
			if ( array_key_exists( $slug, (array) $this->plugin_data ) ) {
				continue;
			}
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}
			$args     = array(
				'slug'   => $slug,
				'fields' => array(
					'short_description' => true,
					'icons'             => true,
				),
			);
			$response = plugins_api( 'plugin_information', $args );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$this->plugin_data[ $response->slug ] = (array) $response;
		}

		// Remove from $this->plugin_data if slug no longer a dependency.
		$differences = array_diff( array_keys( $this->plugin_data ), $this->slugs );
		if ( ! empty( $differences ) ) {
			foreach ( $differences as $difference ) {
				unset( $this->plugin_data[ $difference ] );
			}
		}

		ksort( $this->plugin_data );
		set_site_transient( 'wp_plugin_dependencies_plugin_data', $this->plugin_data, 0 );
	}

	/**
	 * Modify the plugin row.
	 *
	 * @global $pagenow Current page.
	 *
	 * @return void
	 */
	public function modify_plugin_row() {
		global $pagenow;
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		$dependency_paths = $this->get_dependency_filepaths();
		foreach ( $dependency_paths as $plugin_file ) {
			if ( $plugin_file ) {
				$this->modify_dependency_plugin_row( $plugin_file );
			}
		}
		foreach ( array_keys( $this->requires_plugins ) as $plugin_file ) {
			$this->modify_requires_plugin_row( $plugin_file );
		}
	}

	/**
	 * Actually make modifications to plugin row of plugin dependencies.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return void
	 */
	public function modify_dependency_plugin_row( $plugin_file ) {
		add_action( 'after_plugin_row_' . $plugin_file, array( $this, 'modify_plugin_row_elements' ), 10, 2 );
		add_filter( 'plugin_action_links_' . $plugin_file, array( $this, 'unset_action_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links_' . $plugin_file, array( $this, 'unset_action_links' ), 10, 2 );
	}

	/**
	 * Actually make modifications to plugin row of requiring plugin.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return void
	 */
	public function modify_requires_plugin_row( $plugin_file ) {
		add_action( 'after_plugin_row_' . $plugin_file, array( $this, 'modify_plugin_row_elements_requires' ), 10, 1 );
		add_filter( 'plugin_action_links_' . $plugin_file, array( $this, 'cannot_activate_unmet_dependencies' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links_' . $plugin_file, array( $this, 'cannot_activate_unmet_dependencies' ), 10, 2 );
	}

	/**
	 * Modify the plugin row elements.
	 * Removes plugin row checkbox.
	 * Adds 'Required by: ...' information.
	 *
	 * @param string $plugin_file Plugin file.
	 * @param array  $plugin_data Array of plugin data.
	 * @return void
	 */
	public function modify_plugin_row_elements( $plugin_file, $plugin_data ) {
		$sources            = $this->get_dependency_sources( $plugin_data );
		$requires_filepaths = $this->get_requires_paths( $plugin_data );
		print '<script>';
		print 'jQuery("tr[data-plugin=\'' . esc_attr( $plugin_file ) . '\'] .plugin-version-author-uri").append("<br><br><strong>' . esc_html__( 'Required by:' ) . '</strong> ' . esc_html( $sources ) . '");';
		foreach ( $requires_filepaths as $filepath ) {
			if ( is_plugin_active( $filepath ) ) {
				print 'jQuery(".active[data-plugin=\'' . esc_attr( $plugin_file ) . '\'] .check-column input").remove();';
				break;
			}
		}
		print '</script>';
	}

	/**
	 * Modify the plugin row elements.
	 * Add `Requires: ...` information
	 *
	 * @param string $plugin_file Plugin file.
	 * @return void
	 */
	public function modify_plugin_row_elements_requires( $plugin_file ) {
		$names = $this->get_requires_plugins_names( $plugin_file );

		if ( empty( $names ) ) {
			return;
		}

		$links = $this->get_view_details_links( $plugin_file, $names );

		print '<script>';
		print 'jQuery("tr[data-plugin=\'' . esc_attr( $plugin_file ) . '\'] .plugin-version-author-uri").append("<br><br><strong>' . esc_html__( 'Requires:' ) . '</strong> ' . wp_kses_post( $links ) . '");';
		print '</script>';
	}

	/**
	 * Disable 'Install Now' for empty packages.
	 *
	 * @global $pagenow Current page.
	 *
	 * @param array $action_links Array of plugin install action links.
	 * @param array $plugin       Array of plugin data.
	 * @return array
	 */
	public function empty_package_remove_install_button( $action_links, $plugin ) {
		global $pagenow;

		if ( 'plugin-install.php' !== $pagenow
			|| ! empty( $plugin['download_link'] ) || ! str_contains( $action_links[0], 'install-now' )
		) {
			return $action_links;
		}

		$action_links[0] .= '<span class="screen-reader-text">' . __( 'Cannot install due to empty package' ) . '</span>';
		$action_links[0]  = str_replace( 'install-now', 'install-now button-disabled', $action_links[0] );

		return $action_links;
	}

	/**
	 * Add 'Require Plugins: ...' to plugin install cards when dependent plugin not installed.
	 *
	 * @param string $description Short description of plugin.
	 * @param array  $plugin      Array of plugin data.
	 * @return string
	 */
	public function plugin_install_description_uninstalled( $description, $plugin ) {
		if ( empty( $plugin['requires_plugins'] ) ) {
			return $description;
		}

		$this->plugin_api_data = (array) get_site_transient( 'wp_plugin_dependencies_plugin_api_data' );
		foreach ( $plugin['requires_plugins'] as $slug ) {
			// Don't hit plugins API if data exists.
			if ( array_key_exists( $slug, (array) $this->plugin_api_data ) ) {
				continue;
			}

			$this->slugs[] = $slug;
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}
			$args     = array(
				'slug'   => $slug,
				'fields' => array(
					'short_description' => true,
					'icons'             => true,
				),
			);
			$response = plugins_api( 'plugin_information', $args );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$this->plugin_api_data[ $response->slug ] = (array) $response;
			ksort( $this->plugin_api_data );
			unset( $this->plugin_api_data[0] );
			set_site_transient( 'wp_plugin_dependencies_plugin_api_data', $this->plugin_api_data, WEEK_IN_SECONDS );
		}

		foreach ( $plugin['requires_plugins'] as $slug ) {
			$plugin_data = $this->plugin_api_data[ $slug ];
			$url         = network_admin_url( 'plugin-install.php' );
			$url         = add_query_arg(
				array(
					'tab'       => 'plugin-information',
					'plugin'    => $plugin_data['slug'],
					'TB_iframe' => 'true',
					'width'     => '600',
					'height'    => '550',
				),
				$url
			);

			if ( isset( $plugin_data['name'] ) && ! empty( $plugin_data['version'] ) ) {
				$more_details_link[ $slug ] = sprintf(
					'<a href="%1$s" class="alignright thickbox open-plugin-details-modal" aria-label="%2$s" data-title="%3$s">%4$s</a>',
					esc_url( $url ),
					/* translators: %s: Plugin name. */
					esc_attr( sprintf( __( 'More information about %s' ), $plugin_data['name'] ) ),
					esc_attr( $plugin_data['name'] ),
					__( 'More details' )
				);
				$more_details_link[ $slug ] = esc_attr( $plugin_data['name'] ) . '&nbsp' . $more_details_link[ $slug ];
			}
		}

		$header = '<strong>' . __( 'Additional plugins are required' ) . '</strong>';
		array_unshift( self::$plugin_card_data, $header );
		self::$plugin_card_data = array_merge( self::$plugin_card_data, $more_details_link );

		return $description;
	}

	/**
	 * Display plugin card data.
	 *
	 * @param string $description Plugin card description.
	 * @return string
	 */
	public function set_plugin_card_data( $description ) {
		if ( ! empty( self::$plugin_card_data ) ) {
			self::$plugin_card_data = array_filter( self::$plugin_card_data );
			$data                   = implode( '<br>', self::$plugin_card_data );
			$notice                 = '<div class="plugin-dependencies"><p class="plugin-dependencies-explainer-text">' . $data . '</p></div>';
			$description            = $description . $notice;
		}

		self::$plugin_card_data = array();

		return $description;
	}

	/**
	 * Create 'View details' like links for required plugins.
	 *
	 * @param string $plugin_file Plugin file name.
	 * @param string $names       Names of required plugins.
	 * @return string 'View details' like links for required plugins.
	 */
	private function get_view_details_links( $plugin_file, $names ) {
		$details_links = array();
		$names_arr     = explode( ', ', $names );
		$dependencies  = $this->requires_plugins[ $plugin_file ]['RequiresPlugins'];
		$dependencies  = explode( ',', $dependencies );

		foreach ( $dependencies as $dependency ) {
			$plugin_data = $this->plugin_data[ $dependency ];
			foreach ( $names_arr as $name ) {
				if ( $name !== $plugin_data['name'] ) {
					continue;
				}

				if ( empty( $plugin_data['version'] ) ) {
					$details_links[ $name ] = $name;
					continue;
				}

				$name_attr              = esc_attr( $name );
				$details_links[ $name ] = sprintf(
					"<a href='%s' class='thickbox open-plugin-details-modal' aria-label='%s' data-title='%s'>%s</a>",
					esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_data['slug'] . '&TB_iframe=true&width=600&height=550' ) ),
					/* translators: %s: Plugin name. */
					sprintf( __( 'More information about %s' ), $name_attr ),
					$name_attr,
					$name
				);
			}
		}

		$details_links = implode( ', ', $details_links );

		return $details_links;
	}

	/**
	 * Unset plugin action links so required plugins can't be removed or deactivated.
	 * Only when the requiring plugin is active.
	 *
	 * @param array  $actions     Action links.
	 * @param string $plugin_file Plugin file.
	 * @return array
	 */
	public function unset_action_links( $actions, $plugin_file ) {
		foreach ( $this->requires_plugins as $plugin => $requires ) {
			$dependents = explode( ',', $requires['RequiresPlugins'] );
			if ( is_plugin_active( $plugin ) && in_array( dirname( $plugin_file ), $dependents, true ) ) {
				unset( $actions['delete'], $actions['deactivate'] );
			}
		}

		return $actions;
	}

	/**
	 * Disable 'Activate' link if dependencies not met.
	 * Add 'Dependencies' link to install plugin tab.
	 *
	 * @param array  $actions     Plugin action links.
	 * @param string $plugin_file File name.
	 * @return array
	 */
	public function cannot_activate_unmet_dependencies( $actions, $plugin_file ) {
		$dependencies        = $this->get_dependency_filepaths();
		$plugin_dependencies = $this->plugins[ $plugin_file ]['RequiresPlugins'];

		if ( ! isset( $actions['activate'] ) ) {
			return $actions;
		}

		foreach ( $plugin_dependencies as $plugin_dependency ) {
			if ( ! $dependencies[ $plugin_dependency ] || is_plugin_inactive( $dependencies[ $plugin_dependency ] ) ) {
				$activate  = _x( 'Activate', 'plugin' );
				$activate .= '<span class="screen-reader-text">' . __( 'Cannot activate due to unmet dependency' ) . '</span>';
				unset( $actions['activate'] );
				$actions = array_merge( array( 'activate' => $activate ), $actions );

				add_action( 'after_plugin_row_' . $plugin_file, array( $this, 'hide_column_checkbox' ), 10, 1 );
				break;
			}
		}

		return $actions;
	}

	/**
	 * Hide plugin row column checkbox for plugins with unmet dependencies.
	 *
	 * @param string $plugin_file File name.
	 * @return void
	 */
	public function hide_column_checkbox( $plugin_file ) {
		print '<script>';
		print 'jQuery(".inactive[data-plugin=\'' . esc_attr( $plugin_file ) . '\'] .check-column input").remove();';
		print '</script>';
	}

	/**
	 * Display admin notice if dependencies not installed.
	 *
	 * @global $pagenow Current page.
	 *
	 * @return void
	 */
	public function admin_notices() {
		global $pagenow;

		// Exit early if user unable to act on notice.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		// Only display on specific pages.
		if ( in_array( $pagenow, array( 'plugin-install.php', 'plugins.php' ), true ) ) {
			/*
			 * Plugin deactivated if dependencies not met.
			 * Transient on a 10 second timeout.
			 */
			$deactivate_requires = get_site_transient( 'wp_plugin_dependencies_deactivate_plugins' );
			if ( ! empty( $deactivate_requires ) ) {
				foreach ( $deactivate_requires as $deactivated ) {
					$deactivated_plugins[] = $this->plugins[ $deactivated ]['Name'];
				}
				$deactivated_plugins = implode( ', ', $deactivated_plugins );
				printf(
					'<div class="notice-error notice is-dismissible"><p>'
					/* translators: 1: plugin names */
					. esc_html__( '%1$s plugin(s) have been deactivated. There are uninstalled or inactive dependencies.' )
					. '</p></div>',
					'<strong>' . esc_html( $deactivated_plugins ) . '</strong>'
				);
			} else {
				// More dependencies to install.
				$installed_slugs = array_map( 'dirname', array_keys( $this->plugins ) );
				$intersect       = array_intersect( $this->slugs, $installed_slugs );
				asort( $intersect );
				if ( $intersect !== $this->slugs ) {
					$message_html = __( 'There are additional plugin dependencies that must be installed.' );

					printf(
						'<div class="notice-warning notice is-dismissible"><p>%s</p></div>',
						wp_kses_post( $message_html )
					);
				}
			}

			$circular_dependencies = $this->get_circular_dependencies();
			if ( ! empty( $circular_dependencies ) && count( $circular_dependencies ) > 1 ) {
				/* translators: circular dependencies names */
				$messages  = sprintf( __( 'You have circular dependencies with the following plugins: %s' ), implode( ', ', $circular_dependencies['names'] ) );
				$messages .= '<br>' . __( 'Please contact the plugin developers and make them aware.' );
				printf(
					'<div class="notice-warning notice is-dismissible"><p>%s</p></div>',
					wp_kses_post( $messages )
				);
			}
		}
	}

	/**
	 * Get circular dependency data.
	 *
	 * @return array
	 */
	protected function get_circular_dependencies() {
		$circular_dependencies = array( 'names' => array() );
		foreach ( $this->requires_plugins as $file => $requires ) {
			if ( in_array( dirname( $file ), $this->slugs, true )
				&& in_array( $requires['RequiresPlugins'], $this->slugs, true )
				&& isset( $this->plugin_data[ $requires['RequiresPlugins'] ]['name'] ) // Needed for WP-CLI.
			) {
				$slug                                   = $requires['RequiresPlugins'];
				$circular_dependencies[ $slug ]['file'] = $file;
				$circular_dependencies[ $slug ]['name'] = $this->plugin_data[ $slug ]['name'];
				$circular_dependencies['names'][]       = $circular_dependencies[ $slug ]['name'];
			}
		}
		asort( $circular_dependencies['names'] );

		return $circular_dependencies;
	}

	/**
	 * Get filepath of installed dependencies.
	 * If dependency is not installed filepath defaults to false.
	 *
	 * @return array
	 */
	protected function get_dependency_filepaths() {
		$dependency_filepaths = array();

		if ( empty( $this->plugins ) ) {
			return $dependency_filepaths;
		}

		// Cache the plugin directory names.
		if ( empty( $this->plugin_dirnames )
			|| ( ! empty( $this->plugin_dirnames ) && $this->plugin_dirnames_cache !== $this->plugins )
		) {
			$this->plugin_dirnames       = array();
			$this->plugin_dirnames_cache = $this->plugins;

			foreach ( array_keys( $this->plugins ) as $plugin ) {
				$dirname = dirname( $plugin );

				if ( '.' !== $dirname ) {
					$this->plugin_dirnames[ $dirname ] = $plugin;
				}
			}
		}

		foreach ( $this->slugs as $slug ) {
			if ( isset( $this->plugin_dirnames[ $slug ] ) ) {
				$dependency_filepaths[ $slug ] = $this->plugin_dirnames[ $slug ];
				continue;
			}

			$dependency_filepaths[ $slug ] = false;
		}

		return $dependency_filepaths;
	}

	/**
	 * Get formatted string of dependent plugins.
	 *
	 * @param array $plugin_data Array of plugin data.
	 * @return string
	 */
	protected function get_dependency_sources( $plugin_data ) {
		$sources = array();
		foreach ( $this->plugins as $plugin ) {
			if ( ! empty( $plugin['RequiresPlugins'] ) ) {
				// Default TextDomain derived from plugin directory name, should be slug equivalent.
				$plugin_data['slug'] = $plugin_data['slug'] ?? $plugin_data['TextDomain'];
				if ( in_array( $plugin_data['slug'], $plugin['RequiresPlugins'], true ) ) {
					$sources[] = $plugin['Name'];
				}
			}
		}
		$sources = array_unique( $sources );
		sort( $sources );
		$sources = implode( ', ', $sources );

		return $sources;
	}

	/**
	 * Get array of plugin requirement filepaths.
	 *
	 * @param array $plugin_data Array of plugin data.
	 * @return array
	 */
	protected function get_requires_paths( $plugin_data ) {
		$paths = array();
		foreach ( $this->plugins as $filepath => $plugin ) {
			if ( ! empty( $plugin['RequiresPlugins'] ) ) {
				// Default TextDomain derived from plugin directory name, should be slug equivalent.
				$plugin_data['slug'] = $plugin_data['slug'] ?? $plugin_data['TextDomain'];
				if ( in_array( $plugin_data['slug'], $plugin['RequiresPlugins'], true ) ) {
					$paths[] = $filepath;
				}
			}
		}
		$paths = array_filter( $paths );
		$paths = array_unique( $paths );
		sort( $paths );

		return $paths;
	}

	/**
	 * Get names of required plugins.
	 *
	 * @param string $data Plugin file.
	 * @return string
	 */
	protected function get_requires_plugins_names( $data ) {
		$this->plugin_data = get_site_transient( 'wp_plugin_dependencies_plugin_data' );

		// Exit if no plugin data found.
		if ( empty( $this->plugin_data ) ) {
			return;
		}

		if ( str_contains( $data, '.php' ) ) {
			$requires = $this->plugins[ $data ]['RequiresPlugins'];
			sort( $requires );
		}
		foreach ( $requires as $require ) {
			if ( isset( $this->plugin_data[ $require ] ) ) {
				$names[] = $this->plugin_data[ $require ]['name'];
			}
		}
		if ( ! empty( $names ) ) {
			$names = implode( ', ', $names );
		}

		return $names ?? '';
	}

	/**
	 * Handles checking plugin dependencies after a plugin is installed via AJAX.
	 */
	public function check_plugin_dependencies() {
		check_ajax_referer( 'updates' );

		if ( empty( $_POST['slug'] ) ) {
			wp_send_json_error(
				array(
					'slug'         => '',
					'errorCode'    => 'no_plugin_specified',
					'errorMessage' => __( 'No plugin specified.' ),
				)
			);
		}

		$slug   = sanitize_key( wp_unslash( $_POST['slug'] ) );
		$status = array( 'slug' => $slug );

		if ( ! isset( $this->plugin_dirnames[ $slug ] ) ) {
			$status['errorCode']    = 'plugin_not_installed';
			$status['errorMessage'] = __( 'The plugin is not installed.' );
			wp_send_json_error( $status );
		}

		$plugin_file = $this->plugin_dirnames[ $slug ];

		if ( ! isset( $this->requires_plugins[ $plugin_file ]['RequiresPlugins'] ) ) {
			$status['message'] = __( 'The plugin has no required plugins.' );
			wp_send_json_success( $status );
		}

		$dependencies          = explode( ',', $this->requires_plugins[ $plugin_file ]['RequiresPlugins'] );
		$inactive_dependencies = array();
		foreach ( $dependencies as $dependency ) {
			if ( is_plugin_inactive( $this->plugin_dirnames[ $dependency ] ) ) {
				$inactive_dependencies[] = $dependency;
			}
		}

		if ( ! empty( $inactive_dependencies ) ) {
			$inactive_dependency_names = array_map(
				function( $dependency ) {
					return $this->plugin_data[ $dependency ]['name'];
				},
				$inactive_dependencies
			);

			$status['errorCode']    = 'inactive_dependencies';
			$status['errorMessage'] = sprintf(
				/* translators: %s: A list of inactive dependency plugin names. */
				__( 'The following plugins must be activated first: %s.' ),
				implode( ', ', $inactive_dependency_names )
			);
			$status['errorData'] = array_combine( $inactive_dependencies, $inactive_dependency_names );

			wp_send_json_error( $status );
		}

		$status['message'] = __( 'All required plugins are installed and activated.' );
		wp_send_json_success( $status );
	}
}

( new WP_Plugin_Dependencies() )->start();
