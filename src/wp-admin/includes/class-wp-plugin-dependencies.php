<?php
/**
 * WordPress Plugin Administration API: WP_Plugin_Dependencies class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 6.3.0
 */

/**
 * Core class for installing plugin dependencies.
 *
 * It is designed to add plugin dependencies as designated in the
 * `Requires Plugins` header to a new view in the plugins install page.
 */
final class WP_Plugin_Dependencies {

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
	private $plugin_dirnames = array();

	/**
	 * Holds cached plugin directory names.
	 *
	 * @var array
	 */
	private $plugin_dirnames_cache = array();

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
			add_filter( 'plugins_api_result', array( $this, 'plugins_api_result' ), 10, 3 );
			add_filter( 'plugins_api_result', array( $this, 'empty_plugins_api_result' ), 10, 3 );
			add_filter( 'plugin_install_description', array( $this, 'plugin_install_description_installed' ), 10, 2 );
			add_filter( 'plugin_install_description', array( $this, 'plugin_install_description_uninstalled' ), 10, 2 );
			add_filter( 'plugin_install_action_links', array( $this, 'modify_plugin_install_action_links' ), 10, 2 );
			add_filter( 'plugin_install_action_links', array( $this, 'empty_package_remove_install_button' ), 10, 2 );

			add_action( 'admin_init', array( $this, 'modify_plugin_row' ), 15 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
		}

		$required_headers = $this->parse_plugin_headers();
		$this->slugs      = $this->sanitize_required_headers( $required_headers );
		$this->get_dot_org_data();
		$this->deactivate_unmet_dependencies();
	}

	/**
	 * Enqueues styles for plugin dependencies on the "Add New" plugins screen.
	 *
	 * @global string $wp_version The WordPress version string.
	 * @global string $pagenow    The filename of the current screen.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		global $wp_version, $pagenow;

		if ( 'plugin-install.php' === $pagenow ) {
			wp_enqueue_style(
				'wp-plugin-dependencies',
				plugins_url( 'wp-admin/css/wp-plugin-dependencies.css', 'wp-plugin-dependencies/plugin.php' ),
				array(),
				$wp_version
			);
		}
	}

	/**
	 * Run get_plugins() and store result.
	 *
	 * @return array
	 */
	private function get_plugins() {
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
	private function parse_plugin_headers() {
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
	private function sanitize_required_headers( $required_headers ) {
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
	private function deactivate_unmet_dependencies() {
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
	 * Modify plugins_api() response.
	 *
	 * @param stdClass $res    Object of results.
	 * @param string   $action Variable for plugins_api().
	 * @param stdClass $args   Object of plugins_api() args.
	 * @return stdClass
	 */
	public function plugins_api_result( $res, $action, $args ) {
		if ( property_exists( $args, 'browse' ) && 'dependencies' === $args->browse ) {
			$res->info = array(
				'page'    => 1,
				'pages'   => 1,
				'results' => count( (array) $this->plugin_data ),
			);

			$res->plugins = $this->plugin_data;
		}

		return $res;
	}

	/**
	 * Get default empty API response for non-dot org plugin.
	 *
	 * @param stdClass $res    Object of results.
	 * @param string   $action Variable for plugins_api().
	 * @param stdClass $args   Object of plugins_api() args.
	 * @return stdClass
	 */
	public function empty_plugins_api_result( $res, $action, $args ) {
		if ( is_wp_error( $res ) ) {
			$res = $this->get_empty_plugins_api_response( $res, (array) $args );
		}

		return $res;
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

		if ( ! in_array( $pagenow, array( 'plugin-install.php', 'plugins.php' ), true ) ) {
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
	 * Modify plugin install card for unmet dependencies
	 *
	 * @global $pagenow Current page.
	 *
	 * @param array $action_links Plugin install card action links.
	 * @param array $plugin       Plugin data.
	 * @return array
	 */
	public function modify_plugin_install_action_links( $action_links, $plugin ) {
		global $pagenow;

		$dependencies = $this->get_dependency_filepaths();
		if ( ! isset( $this->plugin_dirnames[ $plugin['slug'] ] ) ) {
			return $action_links;
		}
		$file = $this->plugin_dirnames[ $plugin['slug'] ];
		if ( ! isset( $this->requires_plugins[ $file ] ) ) {
			return $action_links;
		}
		$requires     = $this->requires_plugins[ $file ]['RequiresPlugins'];
		$requires_arr = explode( ',', $requires );
		foreach ( $requires_arr as $req ) {
			if ( ! $dependencies[ $req ] || is_plugin_inactive( $dependencies[ $req ] ) ) {
				if ( str_contains( $action_links[0], 'activate-now' ) ) {
					$action_links[0]  = str_replace( __( 'Network Activate' ), __( 'Activate' ), $action_links[0] );
					$action_links[0]  = str_replace( __( 'Activate' ), _x( 'Cannot Activate', 'plugin' ), $action_links[0] );
					$action_links[0] .= '<span class="screen-reader-text">' . __( 'Cannot activate due to unmet dependency' ) . '</span>';
					$action_links[0]  = str_replace( 'activate-now', 'button-disabled', $action_links[0] );
					if ( 'plugin-install.php' !== $pagenow ) {
						$action_links[] = $this->get_dependency_link();
					}
					break;
				}
			}
		}

		return $action_links;
	}

	/**
	 * Convert 'Install Now' into 'Cannot Install' for empty packages.
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
			|| ! isset( $_GET['tab'] ) || 'dependencies' !== $_GET['tab'] // phpcs:ignore WordPress.Security.NonceVerification
			|| ! empty( $plugin['download_link'] ) || ! str_contains( $action_links[0], 'install-now' )
		) {
			return $action_links;
		}
		$action_links[0]  = str_replace( __( 'Network Install' ), __( 'Install' ), $action_links[0] );
		$action_links[0]  = str_replace( __( 'Install Now' ), _x( 'Cannot Install', 'plugin' ), $action_links[0] );
		$action_links[0] .= '<span class="screen-reader-text">' . __( 'Cannot install due to empty package' ) . '</span>';
		$action_links[0]  = str_replace( 'install-now', 'button-disabled', $action_links[0] );

		return $action_links;
	}

	/**
	 * Add 'Required by: ...' and 'Requires: ...' to plugin install cards.
	 *
	 * @param string $description Short description of plugin.
	 * @param array  $plugin      Array of plugin data.
	 * @return string
	 */
	public function plugin_install_description_installed( $description, $plugin ) {
		$required = '';
		if ( in_array( $plugin['slug'], array_keys( $this->plugin_data ), true ) ) {
			$dependents  = $this->get_dependency_sources( $plugin );
			$required    = '<strong>' . __( 'Required by:' ) . '</strong> ' . $dependents;
			$description = $description . '<p>' . $required . '</p>';
		}

		if ( ! isset( $this->plugin_dirnames[ $plugin['slug'] ] ) ) {
			return $description;
		}

		$file = $this->plugin_dirnames[ $plugin['slug'] ];
		if ( in_array( $file, array_keys( $this->requires_plugins ), true ) ) {
			$require_names = $this->get_requires_plugins_names( $file );
			$requires      = '<strong>' . __( 'Requires:' ) . '</strong> ' . $require_names;
			$description   = $description . '<p>' . $requires . '</p>';
		}

		return $description;
	}

	/**
	 * Add 'Require Plugins: ...' to plugin install cards when dependent plugin not installed.
	 *
	 * @param string $description Short description of plugin.
	 * @param array  $plugin      Array of plugin data.
	 * @return string
	 */
	public function plugin_install_description_uninstalled( $description, $plugin ) {
		if ( str_contains( $description, 'Required by:' ) || str_contains( $description, 'Requires:' ) ) {
			return $description;
		}
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

		$required_names = array();
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

			// Check if plugin dependency is installed and active.
			$plugin_is_active = 'plugin-dependency-incompatible';
			$active_plugins   = get_option( 'active_plugins' );
			foreach ( $active_plugins as $plugin_file ) {
				if ( str_contains( $plugin_file, '/' ) && explode( '/', $plugin_file )[0] === $slug ) {
					$plugin_is_active = 'plugin-dependency-compatible';
					break;
				}
			}

			if ( isset( $plugin_data['name'] ) && ! empty( $plugin_data['version'] ) ) {
				$plugin_dependency_name = sprintf(
					'<span class="plugin-dependency-name %1$s">%2$s</span>',
					esc_attr( $plugin_is_active ),
					esc_html( $plugin_data['name'] )
				);

				$more_details_link = sprintf(
					'<a href="%1$s" class="thickbox open-plugin-details-modal" aria-label="%2$s" data-title="%3$s">%4$s</a>',
					esc_url( $url ),
					/* translators: %s: Plugin name. */
					esc_attr( sprintf( __( 'More information about %s' ), $plugin_data['name'] ) ),
					esc_attr( $plugin_data['name'] ),
					__( 'More details' )
				);

				$requires_php = isset( $plugin_data['requires_php'] ) ? $plugin_data['requires_php'] : '';
				$requires_wp  = isset( $plugin_data['requires'] ) ? $plugin_data['requires'] : '';

				$compatible_php = is_php_version_compatible( $requires_php );
				$compatible_wp  = is_wp_version_compatible( $requires_wp );

				$button = wp_get_plugin_action_button( $plugin_data['name'], $plugin_data, $compatible_php, $compatible_wp );

				$required_names[] = '<div class="plugin-dependency plugin-card-' . esc_attr( $slug ) . '">' . $plugin_dependency_name . ' ' . $button . ' ' . $more_details_link . '</div>';
			} else {
				$required_names[] = $slug;
			}
		}

		$requires  = '<strong>' . __( 'Additional plugins are required' ) . '</strong><br>';
		$requires .= __( 'The following plugins must also be installed and activated. This plugin will be deactivated if any of the required plugins is deactivated or deleted.' ) . '<br>';

		$required_names_count = count( $required_names );
		for ( $i = 0; $i < $required_names_count; ++$i ) {
			$requires .= $required_names[ $i ];
			if ( $i !== $required_names_count ) {
				$requires . '<br>';
			}
		}

		return $description . '<div class="plugin-dependencies"><p class="plugin-dependencies-explainer-text">' . $requires . '</p></div>';
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
	 * Exchange 'Activate' link for 'Cannot Activate' text if dependencies not met.
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
				$activate     = _x( 'Cannot Activate', 'plugin' );
				$activate    .= '<span class="screen-reader-text">' . __( 'Cannot activate due to unmet dependency' ) . '</span>';
				$dependencies = $this->get_dependency_link();
				unset( $actions['activate'] );
				$actions = array_merge(
					array(
						'activate'     => $activate,
						'dependencies' => $dependencies,
					),
					$actions
				);

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

			// Plugin deactivated if dependencies not met.
			// Transient on a 10 second timeout.
			$deactivate_requires = get_site_transient( 'wp_plugin_dependencies_deactivate_plugins' );
			if ( ! empty( $deactivate_requires ) ) {
				foreach ( $deactivate_requires as $deactivated ) {
					$deactivated_plugins[] = $this->plugins[ $deactivated ]['Name'];
				}
				$deactivated_plugins = implode( ', ', $deactivated_plugins );
				printf(
					'<div class="notice-error notice is-dismissible"><p>'
					/* translators: 1: plugin names, 2: link to Dependencies install page */
					. esc_html__( '%1$s plugin(s) have been deactivated. There are uninstalled or inactive dependencies. Go to the %2$s install page.' )
					. '</p></div>',
					'<strong>' . esc_html( $deactivated_plugins ) . '</strong>',
					wp_kses_post( $this->get_dependency_link( true ) )
				);
			} else {
				// More dependencies to install.
				$installed_slugs = array_map( 'dirname', array_keys( $this->plugins ) );
				$intersect       = array_intersect( $this->slugs, $installed_slugs );
				asort( $intersect );
				if ( $intersect !== $this->slugs ) {
					$message_html = __( 'There are additional plugins that must be installed.' );

					// Display link (if not already on Dependencies install page).
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$tab = isset( $_GET['tab'] ) ? sanitize_title_with_dashes( wp_unslash( $_GET['tab'] ) ) : '';
					if ( 'plugin-install.php' !== $pagenow || 'dependencies' !== $tab ) {
						$message_html .= ' ' . sprintf(
							/* translators: 1: link to Dependencies install page */
							__( 'Go to the %s install page.' ),
							wp_kses_post( $this->get_dependency_link( true ) ),
							'</a>'
						);
					}

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
	private function get_circular_dependencies() {
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
	private function get_dependency_filepaths() {
		$dependency_filepaths = array();

		if ( empty( $this->slugs ) || empty( $this->plugins ) ) {
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
	private function get_dependency_sources( $plugin_data ) {
		$sources = array();
		foreach ( $this->plugins as $plugin ) {
			if ( ! empty( $plugin['RequiresPlugins'] ) ) {
				// Default TextDomain derived from plugin directory name, should be slug equivalent.
				$plugin_data['slug'] = isset( $plugin_data['slug'] ) ? $plugin_data['slug'] : $plugin_data['TextDomain'];
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
	 * Get Dependencies link.
	 *
	 * @param bool $notice Usage in admin notice.
	 * @return string
	 */
	private function get_dependency_link( $notice = false ) {
		$link_text = $notice ? __( 'Dependencies' ) : __( 'Manage Dependencies' );
		$link      = sprintf(
			'<a href=' . esc_url( network_admin_url( 'plugin-install.php?tab=dependencies' ) ) . ' aria-label="' . __( 'Go to Dependencies tab of Add Plugins page.' ) . '">%s</a>',
			$link_text
		);

		return $link;
	}
	/**
	 * Get array of plugin requirement filepaths.
	 *
	 * @param array $plugin_data Array of plugin data.
	 * @return array
	 */
	private function get_requires_paths( $plugin_data ) {
		$paths = array();
		foreach ( $this->plugins as $filepath => $plugin ) {
			if ( ! empty( $plugin['RequiresPlugins'] ) ) {
				// Default TextDomain derived from plugin directory name, should be slug equivalent.
				$plugin_data['slug'] = isset( $plugin_data['slug'] ) ? $plugin_data['slug'] : $plugin_data['TextDomain'];
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
	 * Return empty plugins_api() response.
	 *
	 * @param stdClass|WP_Error $response Response from plugins_api().
	 * @param array             $args     Array of arguments passed to plugins_api().
	 * @return stdClass
	 */
	private function get_empty_plugins_api_response( $response, $args ) {
		$slug = $args['slug'];
		$args = array(
			'Name'        => $args['slug'],
			'Version'     => '',
			'Author'      => '',
			'Description' => '',
			'RequiresWP'  => '',
			'RequiresPHP' => '',
			'PluginURI'   => '',
		);
		if ( is_wp_error( $response ) || property_exists( $response, 'error' )
			|| ! property_exists( $response, 'slug' )
			|| ! property_exists( $response, 'short_description' )
		) {
			$dependencies      = $this->get_dependency_filepaths();
			$file              = $dependencies[ $slug ];
			$args              = $file ? $this->plugins[ $file ] : $args;
			$short_description = __( 'You will need to manually install this dependency. Please contact the plugin\'s developer and ask them to add plugin dependencies support and for information on how to install the this dependency.' );
			$response          = array(
				'name'              => $args['Name'],
				'slug'              => $slug,
				'version'           => $args['Version'],
				'author'            => $args['Author'],
				'contributors'      => array(),
				'requires'          => $args['RequiresWP'],
				'tested'            => '',
				'requires_php'      => $args['RequiresPHP'],
				'sections'          => array(
					'description'  => '<p>' . $args['Description'] . '</p>' . $short_description,
					'installation' => __( 'Ask the plugin developer where to download and install this plugin dependency.' ),
				),
				'short_description' => '<p>' . $args['Description'] . '</p>' . $short_description,
				'download_link'     => '',
				'banners'           => array(),
				'icons'             => array( 'default' => "https://s.w.org/plugins/geopattern-icon/{$slug}.svg" ),
				'last_updated'      => '',
				'num_ratings'       => 0,
				'rating'            => 0,
				'active_installs'   => 0,
				'homepage'          => $args['PluginURI'],
				'external'          => 'xxx',
			);
			$response          = (object) $response;
		}

		return $response;
	}

	/**
	 * Get names of required plugins.
	 *
	 * @param string $data Plugin file.
	 * @return string
	 */
	private function get_requires_plugins_names( $data ) {
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

		return isset( $names ) ? $names : '';
	}
}

( new WP_Plugin_Dependencies() )->start();
