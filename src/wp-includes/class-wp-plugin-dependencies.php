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
	protected static $plugins = array();

	/**
	 * Holds plugin directory names to compare with cache.
	 *
	 * @var array
	 */
	protected static $plugin_dirnames = array();

	/**
	 * Holds cached plugin directory names.
	 *
	 * @var array
	 */
	protected static $plugin_dirnames_cache = array();

	/**
	 * Holds sanitized plugin dependency slugs,
	 * keyed on their dependent plugin file name.
	 *
	 * @var array
	 */
	protected static $dependencies = array();

	/**
	 * Holds an array of sanitized plugin dependency slugs.
	 *
	 * @var array
	 */
	protected static $dependency_slugs = array();

	/**
	 * Holds an array of dependent plugin slugs,
	 * keyed on the dependent plugin's file path.
	 *
	 * @var array
	 */
	protected static $dependent_slugs = array();

	/**
	 * Holds 'plugins_api()' data for plugin dependencies.
	 *
	 * @var array
	 */
	protected static $dependency_api_data = array();

	/**
	 * Holds 'plugin_api()' data for uninstalled plugin dependencies.
	 *
	 * @var array
	 */
	protected static $uninstalled_dependency_api_data = array();

	/**
	 * Holds data for plugin card.
	 *
	 * @var array
	 */
	protected static $plugin_card_data = array();

	/**
	 * Initializes by fetching plugin header and plugin API data,
	 * and deactivating dependents with unmet dependencies.
	 *
	 * @since 6.4.0
	 */
	public static function initialize() {
		self::read_dependencies_from_plugin_headers();
		self::get_dependency_api_data();
		self::deactivate_dependents_with_unmet_dependencies();
	}

	/**
	 * Stores the result of 'get_plugins()'.
	 *
	 * @since 6.4.0
	 */
	protected static function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		self::$plugins = get_plugins();
	}

	/**
	 * Reads and stores dependency slugs from a plugin's 'Requires Plugins' header.
	 *
	 * @since 6.4.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 */
	protected static function read_dependencies_from_plugin_headers() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		self::get_plugins();
		$plugins_dir     = trailingslashit( $wp_filesystem->wp_plugins_dir() );
		$default_headers = array( 'RequiresPlugins' => 'Requires Plugins' );

		foreach ( array_keys( self::$plugins ) as $plugin ) {
			$header = get_file_data( $plugins_dir . $plugin, $default_headers, 'plugin' );

			if ( '' === $header['RequiresPlugins'] ) {
				continue;
			}
			$dependency_slugs = self::sanitize_dependency_slugs( $header['RequiresPlugins'] );
			$requires         = $header['RequiresPlugins'];
			$requires         = array_filter( explode( ',', $requires ) );

			foreach ( $dependency_slugs as $slug ) {
				self::$dependencies[ $plugin ][] = $slug;
			}
			self::$plugins[ $plugin ]['RequiresPlugins'] = $dependency_slugs;
			self::$dependency_slugs                      = array_merge( self::$dependency_slugs, $dependency_slugs );
		}

		$dependent_keys         = array_keys( self::$dependencies );
		self::$dependent_slugs  = array_combine( $dependent_keys, array_map( 'dirname', $dependent_keys ) );
		self::$dependency_slugs = array_unique( self::$dependency_slugs );
	}

	/**
	 * Sanitizes plugin dependency slugs.
	 *
	 * @since 6.4.0
	 *
	 * @param string $slugs A comma-separated string of plugin dependency slugs.
	 * @return array An array of sanitized plugin dependency slugs.
	 */
	protected static function sanitize_dependency_slugs( $slugs ) {
		$sanitized_slugs = array();
		$slugs           = explode( ',', $slugs );

		foreach ( $slugs as $slug ) {
			$slug = trim( $slug );

			/**
			 * Filters a plugin dependency's slug before matching to
			 * the WordPress.org slug format.
			 *
			 * Can be used to switch between free and premium plugin slugs, for example.
			 *
			 * @since 6.4.0
			 *
			 * @param string $slug The slug.
			 */
			$slug = apply_filters( 'wp_plugin_dependencies_slug', $slug );

			// Match to WordPress.org slug format.
			if ( preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/mu', $slug ) ) {
				$sanitized_slugs[] = $slug;
			}
		}
		$sanitized_slugs = array_unique( $sanitized_slugs );
		sort( $sanitized_slugs );

		return $sanitized_slugs;
	}

	/**
	 * Retrieves and stores dependency plugin data from the WordPress.org Plugin API.
	 *
	 * @since 6.4.0
	 *
	 * @global $pagenow Current page.
	 */
	public static function get_dependency_api_data() {
		global $pagenow;

		if ( ! wp_doing_ajax() && ! in_array( $pagenow, array( 'plugin-install.php', 'plugins.php' ), true ) ) {
			return;
		}

		self::$dependency_api_data = (array) get_site_transient( 'wp_plugin_dependencies_plugin_data' );
		foreach ( self::$dependency_slugs as $slug ) {
			// Set transient for individual data, remove from self::$dependency_api_data if transient expired.
			if ( ! get_site_transient( "wp_plugin_dependencies_plugin_timeout_{$slug}" ) ) {
				unset( self::$dependency_api_data[ $slug ] );
				set_site_transient( "wp_plugin_dependencies_plugin_timeout_{$slug}", true, 12 * HOUR_IN_SECONDS );
			}

			if ( isset( self::$dependency_api_data[ $slug ] ) ) {
				if ( false === self::$dependency_api_data[ $slug ] ) {
					if ( empty( self::$plugin_dirnames ) ) {
						self::get_dependency_filepaths();
					}

					$dependency_file = ! empty( self::$plugin_dirnames[ $slug ] )
						? self::$plugin_dirnames[ $slug ]
						: $slug;
					if ( isset( self::$plugins[ $dependency_file ] ) ) {
						self::$dependency_api_data[ $slug ] = array( 'Name' => self::$plugins[ $dependency_file ]['Name'] );
					} else {
						self::$dependency_api_data[ $slug ] = array( 'Name' => $slug );
					}
					continue;
				}

				// Check the Plugin API if generic data is present.
				if ( empty( self::$dependency_api_data[ $slug ]['last_updated'] ) ) {
					unset( self::$dependency_api_data[ $slug ] );
				}

				// Don't hit the Plugin API if data exists.
				if ( ! empty( self::$dependency_api_data[ $slug ] ) ) {
					continue;
				}
			}

			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}

			$information = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array(
						'short_description' => true,
						'icons'             => true,
					),
				)
			);

			// Ensure `self::$dependency_api_data` has data, sometimes resets after `plugins_api()`.
			self::$dependency_api_data = (array) get_site_transient( 'wp_plugin_dependencies_plugin_data' );

			if ( is_wp_error( $information ) ) {
				continue;
			}

			// Ensure `self::$dependency_api_data` has data, sometimes resets after `plugins_api()`.
			self::$dependency_api_data          = (array) get_site_transient( 'wp_plugin_dependencies_plugin_data' );
			self::$dependency_api_data[ $slug ] = (array) $information;
			// plugins_api() returns 'name' not 'Name'.
			self::$dependency_api_data[ $information->slug ]['Name'] = self::$dependency_api_data[ $information->slug ]['name'];
			set_site_transient( 'wp_plugin_dependencies_plugin_data', self::$dependency_api_data, 0 );
		}

		// Remove from self::$dependency_api_data if slug no longer a dependency.
		$differences = array_diff( array_keys( self::$dependency_api_data ), self::$dependency_slugs );
		foreach ( $differences as $difference ) {
			unset( self::$dependency_api_data[ $difference ] );
		}

		ksort( self::$dependency_api_data );
		// Remove empty elements.
		self::$dependency_api_data = array_filter( self::$dependency_api_data );
		set_site_transient( 'wp_plugin_dependencies_plugin_data', self::$dependency_api_data, 0 );
	}

	/**
	 * Determines whether a plugin's row should be modified
	 * to include dependencies, dependents, or both.
	 *
	 * @since 6.4.0
	 *
	 * @global $pagenow Current page.
	 */
	public static function modify_plugin_row() {
		global $pagenow;
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		$dependency_paths = self::get_dependency_filepaths();
		foreach ( $dependency_paths as $plugin_file ) {
			if ( $plugin_file ) {
				self::add_dependency_plugin_row_hooks( $plugin_file );
			}
		}
		foreach ( array_keys( self::$dependencies ) as $plugin_file ) {
			self::add_dependent_plugin_row_hooks( $plugin_file );
		}
	}

	/**
	 * Adds hooks to modify a dependency's plugin row in 'plugins.php'.
	 *
	 * @since 6.4.0
	 *
	 * @param string $plugin_file Plugin file.
	 */
	public static function add_dependency_plugin_row_hooks( $plugin_file ) {
		add_action( 'after_plugin_row_meta', array( __CLASS__, 'add_dependents_to_dependency_plugin_row' ), 10, 3 );
		add_filter( 'plugin_row_hide_checkbox_' . $plugin_file, '__return_true', 10, 2 );
		add_filter( 'plugin_action_links_' . $plugin_file, array( __CLASS__, 'unset_dependency_action_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links_' . $plugin_file, array( __CLASS__, 'unset_dependency_action_links' ), 10, 2 );
	}

	/**
	 * Adds hooks to modify a dependent's plugin row in 'plugins.php'.
	 *
	 * @since 6.4.0
	 *
	 * @param string $plugin_file Plugin file.
	 */
	public static function add_dependent_plugin_row_hooks( $plugin_file ) {
		add_action( 'after_plugin_row_meta', array( __CLASS__, 'add_dependencies_to_dependent_plugin_row' ), 10, 2 );
		add_filter( 'plugin_action_links_' . $plugin_file, array( __CLASS__, 'disable_activate_for_dependents_with_unmet_dependencies' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links_' . $plugin_file, array( __CLASS__, 'disable_activate_for_dependents_with_unmet_dependencies' ), 10, 2 );
	}

	/**
	 * Adds dependents to a dependency's plugin row in 'plugins.php'.
	 *
	 * @since 6.4.0
	 *
	 * @param string $plugin_file Plugin file.
	 * @param array  $plugin_data Array of plugin data.
	 */
	public static function add_dependents_to_dependency_plugin_row( $plugin_file, $plugin_data ) {
		$dependent_names = self::get_dependent_names( $plugin_data );

		if ( array() === $dependent_names ) {
			return;
		}

		echo wp_kses_post( '<div class="required-by"><strong>' . __( 'Required by:' ) . '</strong> ' . implode( ', ', $dependent_names ) . '</div>' );
	}

	/**
	 * Adds dependencies to a dependent's plugin row in 'plugins.php'.
	 *
	 * @since 6.4.0
	 *
	 * @param string $plugin_file Plugin file.
	 */
	public static function add_dependencies_to_dependent_plugin_row( $plugin_file ) {
		$dependency_names = self::get_dependency_names( $plugin_file );

		if ( array() === $dependency_names ) {
			return;
		}

		$links = self::get_view_details_links( $plugin_file, $dependency_names );

		echo wp_kses_post( '<div class="requires"><strong>' . __( 'Requires:' ) . '</strong> ' . $links . '</div>' );
	}

	/**
	 * Adds a list of dependencies to a dependent's plugin card in 'plugin-install.php'.
	 *
	 * @since 6.4.0
	 *
	 * @param string $description Short description of plugin.
	 * @param array  $plugin      Array of plugin data.
	 * @return string The modified plugin card description.
	 */
	public static function plugin_install_description_uninstalled( $description, $plugin ) {
		// Skip plugins that don't have dependencies.
		if ( empty( $plugin['requires_plugins'] ) ) {
			return $description;
		}

		self::$uninstalled_dependency_api_data = (array) get_site_transient( 'wp_plugin_dependencies_plugin_api_data' );
		foreach ( $plugin['requires_plugins'] as $slug ) {
			// Don't hit the Plugins API if data exists.
			if ( array_key_exists( $slug, (array) self::$uninstalled_dependency_api_data ) ) {
				continue;
			}

			self::$dependency_slugs[] = $slug;
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}
			$information = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array(
						'short_description' => true,
						'icons'             => true,
					),
				)
			);

			if ( is_wp_error( $information ) ) {
				continue;
			}

			self::$uninstalled_dependency_api_data[ $information->slug ] = (array) $information;
			ksort( self::$uninstalled_dependency_api_data );
			unset( self::$uninstalled_dependency_api_data[0] );
			set_site_transient( 'wp_plugin_dependencies_plugin_api_data', self::$uninstalled_dependency_api_data, WEEK_IN_SECONDS );
		}

		foreach ( $plugin['requires_plugins'] as $slug ) {
			$plugin_data = self::$uninstalled_dependency_api_data[ $slug ];
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
					'<a href="%1$s" class="more-details-link thickbox open-plugin-details-modal" aria-label="%2$s" data-title="%3$s">%4$s</a>',
					esc_url( $url ),
					/* translators: %s: Plugin name. */
					esc_attr( sprintf( __( 'More information about %s' ), $plugin_data['name'] ) ),
					esc_attr( $plugin_data['name'] ),
					__( 'More Details' )
				);
				$more_details_link[ $slug ] = '<span class="plugin-dependency-name">' . esc_html( $plugin_data['name'] ) . '</span>' . $more_details_link[ $slug ];
			}
		}

		self::$plugin_card_data = array_merge( self::$plugin_card_data, $more_details_link );

		return $description;
	}

	/**
	 * Adds a list of dependencies to a plugin card's description in 'plugin-install.php'.
	 *
	 * @since 6.4.0
	 *
	 * @param string $description Plugin card description.
	 * @return string The modified plugin card description.
	 */
	public static function add_dependencies_to_dependent_plugin_card( $description ) {
		if ( empty( self::$plugin_card_data ) ) {
			return $description;
		}

		self::$plugin_card_data = array_filter( self::$plugin_card_data );

		$dependency_list = '';
		foreach ( self::$plugin_card_data as $data ) {
			$dependency_list .= '<div class="plugin-dependency">' . $data . '</div>';
		}

		$header       = __( 'Additional plugins are required' );
		$notice       = '<div class="plugin-dependencies">';
		$notice      .= '<p class="plugin-dependencies-explainer-text"><strong>' . $header . '</strong></p>';
		$notice      .= $dependency_list;
		$notice      .= '</div>';
		$description .= $notice;

		self::$plugin_card_data = array();

		return $description;
	}

	/**
	 * Creates 'View details' like links for dependencies.
	 *
	 * @since 6.4.0
	 *
	 * @param string $dependent   Dependent plugin file name.
	 * @param array  $names       An array of dependency names.
	 * @return string 'View details' like links for dependencies.
	 */
	private static function get_view_details_links( $dependent, $names ) {
		$details_links = array();
		$dependencies  = self::$dependencies[ $dependent ];

		foreach ( $dependencies as $dependency ) {
			if ( ! isset( self::$dependency_api_data[ $dependency ] ) ) {
				foreach ( $names as $name ) {
					$details_links[ $name ] = $name;
				}
				continue;
			}

			$plugin_data = self::$dependency_api_data[ $dependency ];

			foreach ( $names as $name ) {
				if ( $name !== $plugin_data['Name'] ) {
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
	 * Unsets plugin action links so dependencies cannot be removed or deactivated
	 * when the dependent is active.
	 *
	 * @since 6.4.0
	 *
	 * @param array  $actions     Action links.
	 * @param string $plugin_file Plugin file.
	 * @return array An array of modified action links.
	 */
	public static function unset_dependency_action_links( $actions, $plugin_file ) {
		foreach ( self::$dependencies as $dependent => $dependencies ) {
			if ( is_plugin_active( $dependent ) && in_array( dirname( $plugin_file ), $dependencies, true ) ) {
				unset( $actions['delete'], $actions['deactivate'] );
			}
		}

		return $actions;
	}

	/**
	 * Displays an admin notice if dependencies are not installed.
	 *
	 * @since 6.4.0
	 *
	 * @global $pagenow Current page.
	 */
	public static function display_admin_notice_for_unmet_dependencies() {
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
			$deactivate_requires = get_site_transient( 'wp_plugin_dependencies_deactivated_plugins' );
			if ( ! empty( $deactivate_requires ) ) {
				foreach ( $deactivate_requires as $deactivated ) {
					$deactivated_plugins[] = self::$plugins[ $deactivated ]['Name'];
				}
				$deactivated_plugins = implode( ', ', $deactivated_plugins );
				wp_admin_notice(
					sprintf(
						/* translators: 1: plugin names */
						esc_html__( '%1$s plugin(s) have been deactivated. There are uninstalled or inactive dependencies.' ),
						'<strong>' . esc_html( $deactivated_plugins ) . '</strong>'
					),
					array(
						'type'        => 'error',
						'dismissible' => true,
					)
				);
			} else {
				// More dependencies to install.
				$installed_slugs = array_map( 'dirname', array_keys( self::$plugins ) );
				$intersect       = array_intersect( self::$dependency_slugs, $installed_slugs );
				asort( $intersect );
				if ( $intersect !== self::$dependency_slugs ) {
					wp_admin_notice(
						__( 'There are additional plugin dependencies that must be installed.' ),
						array(
							'type'        => 'warning',
							'dismissible' => true,
						)
					);
				}
			}

			$circular_dependencies = self::get_circular_dependencies();
			if ( ! empty( $circular_dependencies ) && count( $circular_dependencies ) > 1 ) {
				$circular_dependencies = array_unique( $circular_dependencies, SORT_REGULAR );
				// Build output lines.
				$circular_dependency_lines = array();
				foreach ( $circular_dependencies as $circular_dependency ) {
					$first_filepath              = self::$plugin_dirnames[ $circular_dependency[0] ];
					$second_filepath             = self::$plugin_dirnames[ $circular_dependency[1] ];
					$circular_dependency_lines[] = sprintf(
						/* translators: 1: First plugin name, 2: Second plugin name. */
						__( '%1$s -> %2$s' ),
						'<strong>' . esc_html( self::$plugins[ $first_filepath ]['Name'] ) . '</strong>',
						'<strong>' . esc_html( self::$plugins[ $second_filepath ]['Name'] ) . '</strong>'
					);
				}

				wp_admin_notice(
					sprintf(
						/* translators: circular dependencies names */
						__( 'You have circular dependencies with the following plugins: %s' ),
						'<br>' . implode( '<br>', $circular_dependency_lines )
					) . '<br>' . __( 'Please contact the plugin developers and make them aware.' ),
					array(
						'type'        => 'warning',
						'dismissible' => true,
					)
				);
			}
		}
	}

	/**
	 * Deactivates dependent plugins with unmet dependencies.
	 *
	 * @since 6.4.0
	 */
	protected static function deactivate_dependents_with_unmet_dependencies() {
		$dependency_filepaths     = self::get_dependency_filepaths();
		$circular_dependencies    = self::get_circular_dependencies();
		$dependents_to_deactivate = array();

		foreach ( self::$dependencies as $dependent => $dependencies ) {
			// Skip dependents that are no longer installed.
			if ( ! array_key_exists( $dependent, self::$plugins ) ) {
				continue;
			}

			foreach ( $dependencies as $dependency ) {
				// Skip dependents already marked for deactivation.
				if ( isset( $dependents_to_deactivate[ $dependent ] ) ) {
					continue;
				}

				// Skip inactive dependents and dependents within a circular dependency tree.
				if ( is_plugin_inactive( $dependent ) || in_array( $dependent, $circular_dependencies, true ) ) {
					continue;
				}

				// Detect dependencies that are not installed or are inactive.
				if ( ! isset( $dependency_filepaths[ $dependency ] )
					|| is_plugin_inactive( $dependency_filepaths[ $dependency ] )
				) {
					$dependents_to_deactivate[ $dependent ] = $dependent;
				}
			}
		}

		deactivate_plugins( $dependents_to_deactivate );
		set_site_transient( 'wp_plugin_dependencies_deactivated_plugins', $dependents_to_deactivate, 10 );
	}

	/**
	 * Disables a dependent's 'Activate' link if dependencies are not met.
	 *
	 * @since 6.4.0
	 *
	 * @param array  $actions     Plugin action links.
	 * @param string $plugin_file Plugin file name.
	 * @return array An array of modified action links.
	 */
	public static function disable_activate_for_dependents_with_unmet_dependencies( $actions, $plugin_file ) {
		$dependencies        = self::get_dependency_filepaths();
		$plugin_dependencies = self::$dependencies[ $plugin_file ];

		if ( ! isset( $actions['activate'] ) ) {
			return $actions;
		}

		foreach ( $plugin_dependencies as $plugin_dependency ) {
			if ( ! $dependencies[ $plugin_dependency ] || is_plugin_inactive( $dependencies[ $plugin_dependency ] ) ) {
				$activate  = _x( 'Activate', 'plugin' );
				$activate .= '<span class="screen-reader-text">' . __( 'Cannot activate due to unmet dependency' ) . '</span>';
				unset( $actions['activate'] );
				$actions = array_merge( array( 'activate' => $activate ), $actions );

				add_filter( 'plugin_row_hide_checkbox_' . $plugin_file, '__return_true', 10, 2 );
				break;
			}
		}

		return $actions;
	}

	/**
	 * Gets dependent names.
	 *
	 * @since 6.4.0
	 *
	 * @param array $plugin_data Array of plugin data.
	 * @return array An array of dependent names.
	 */
	protected static function get_dependent_names( $plugin_data ) {
		$dependent_names = array();
		foreach ( self::$plugins as $plugin ) {
			if ( is_array( $plugin['RequiresPlugins'] ) ) {
				// Default TextDomain derived from plugin directory name, should be slug equivalent.
				if ( ! isset( $plugin_data['slug'] ) ) {
					$plugin_data['slug'] = $plugin_data['TextDomain'];
				}
				if ( in_array( $plugin_data['slug'], $plugin['RequiresPlugins'], true ) ) {
					$dependent_names[] = $plugin['Name'];
				}
			}
		}
		$dependent_names = array_unique( $dependent_names );
		sort( $dependent_names );

		return $dependent_names;
	}

	/**
	 * Gets dependency names.
	 *
	 * @since 6.4.0
	 *
	 * @param string $dependent Dependent plugin file.
	 * @return array An array of dependency names.
	 */
	protected static function get_dependency_names( $dependent ) {
		if ( empty( self::$dependency_api_data ) ) {
			self::get_dependency_api_data();
		}

		self::$dependency_api_data = get_site_transient( 'wp_plugin_dependencies_plugin_data' );

		// Bail for an invalid plugin file or a plugin that isn't a dependent.
		if ( ! str_contains( $dependent, '.php' ) || ! array_key_exists( $dependent, self::$dependencies ) ) {
			return array();
		}

		$dependencies = self::$dependencies[ $dependent ];

		// Bail if there are no dependencies.
		if ( ! is_array( $dependencies ) ) {
			return array();
		}

		$dependencies = array_unique( $dependencies );
		sort( $dependencies );

		$dependency_filepaths = self::get_dependency_filepaths();
		$dependency_names     = array();
		foreach ( $dependencies as $dependency ) {
			if ( isset( $dependency_filepaths[ $dependency ] ) ) {
				$filepath           = $dependency_filepaths[ $dependency ];
				$dependency_names[] = isset( self::$dependency_api_data[ $dependency ]['Name'] )
					? self::$dependency_api_data[ $dependency ]['Name']
					: $dependency;
			}
		}

		return $dependency_names;
	}

	/**
	 * Gets the filepath of installed dependencies.
	 * If a dependency is not installed, the filepath defaults to false.
	 *
	 * @since 6.4.0
	 *
	 * @return array An array of install dependencies filepaths.
	 */
	protected static function get_dependency_filepaths() {
		$dependency_filepaths = array();

		if ( empty( self::$plugins ) ) {
			return $dependency_filepaths;
		}

		// Cache the plugin directory names.
		if ( empty( self::$plugin_dirnames )
			|| ( ! empty( self::$plugin_dirnames ) && self::$plugin_dirnames_cache !== self::$plugins )
		) {
			self::$plugin_dirnames       = array();
			self::$plugin_dirnames_cache = self::$plugins;

			foreach ( array_keys( self::$plugins ) as $plugin ) {
				$dirname = dirname( $plugin );

				if ( '.' !== $dirname ) {
					self::$plugin_dirnames[ $dirname ] = $plugin;
				} else {
					// Single file plugin.
					self::$plugin_dirnames[ $plugin ] = $plugin;
				}
			}
		}

		foreach ( self::$dependency_slugs as $slug ) {
			if ( isset( self::$plugin_dirnames[ $slug ] ) ) {
				$dependency_filepaths[ $slug ] = self::$plugin_dirnames[ $slug ];
				continue;
			}

			$dependency_filepaths[ $slug ] = false;
		}

		return $dependency_filepaths;
	}

	/**
	 * Gets circular dependency data.
	 *
	 * @since 6.4.0
	 *
	 * @return array An array of circular dependencies.
	 */
	public static function get_circular_dependencies() {
		$circular_dependencies = array();
		foreach ( self::$dependencies as $dependent => $dependencies ) {
			/*
			 * $dependent is in 'a/a.php' format. Dependencies are stored as slugs, i.e. 'a'.
			 *
			 * Convert $dependent to slug format for checking.
			 */
			$dependent_slug = dirname( $dependent );

			$circular_dependencies = array_merge(
				$circular_dependencies,
				self::check_for_circular_dependencies( array( $dependent_slug ), $dependencies )
			);
		}

		if ( empty( $circular_dependencies ) ) {
			return $circular_dependencies;
		}

		return $circular_dependencies;
	}

	public static function check_for_circular_dependencies( $dependents, $dependencies ) {
		$circular_dependencies = array();

		// Check for a self-dependency.
		$dependents_location_in_its_own_dependencies = array_intersect( $dependents, $dependencies );
		if ( ! empty( $dependents_location_in_its_own_dependencies ) ) {
			foreach ( $dependents_location_in_its_own_dependencies as $self_dependency ) {
				$circular_dependencies[] = array( $self_dependency, $self_dependency );

				// No need to check for itself again.
				unset( $dependencies[ array_search( $self_dependency, $dependencies, true ) ] );
			}
		}

		/*
		 * Check each dependency to see:
		 * 1. If it has dependencies.
		 * 2. If its list of dependencies includes one of its own dependents.
		 */
		foreach ( $dependencies as $dependency ) {
			// Check if the dependency is also a dependent.
			$dependency_location_in_dependents = array_search( $dependency, self::$dependent_slugs, true );

			if ( false !== $dependency_location_in_dependents ) {
				$dependencies_of_the_dependency = self::$dependencies[ $dependency_location_in_dependents ];

				foreach ( $dependents as $dependent ) {
					// Check if its dependencies includes one of its own dependents.
					$dependent_location_in_dependency_dependencies = array_search(
						$dependent,
						$dependencies_of_the_dependency,
						true
					);

					if ( false !== $dependent_location_in_dependency_dependencies ) {
						$circular_dependencies[] = array( $dependent, $dependency );

						// Remove the dependent from its dependency's dependencies.
						unset( $dependencies_of_the_dependency[ $dependent_location_in_dependency_dependencies ] );
					}
				}

				$dependents[] = $dependency;

				/*
				 * Now check the dependencies of the dependency's dependencies for the dependent.
				 *
				 * Yes, that does make sense.
				 */
				$circular_dependencies = array_merge(
					$circular_dependencies,
					self::check_for_circular_dependencies( $dependents, array_unique( $dependencies_of_the_dependency ) )
				);
			}
		}

		return $circular_dependencies;
	}

	/**
	 * Checks plugin dependencies after a plugin is installed via AJAX.
	 *
	 * @since 6.4.0
	 */
	public static function check_plugin_dependencies() {
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

		if ( ! isset( self::$plugin_dirnames[ $slug ] ) ) {
			$status['errorCode']    = 'plugin_not_installed';
			$status['errorMessage'] = __( 'The plugin is not installed.' );
			wp_send_json_error( $status );
		}

		$plugin_file = self::$plugin_dirnames[ $slug ];

		if ( ! isset( self::$dependencies[ $plugin_file ]['RequiresPlugins'] ) ) {
			$status['message'] = __( 'The plugin has no required plugins.' );
			wp_send_json_success( $status );
		}

		$dependencies          = explode( ',', self::$dependencies[ $plugin_file ]['RequiresPlugins'] );
		$inactive_dependencies = array();
		foreach ( $dependencies as $dependency ) {
			if ( is_plugin_inactive( self::$plugin_dirnames[ $dependency ] ) ) {
				$inactive_dependencies[] = $dependency;
			}
		}

		if ( ! empty( $inactive_dependencies ) ) {
			$inactive_dependency_names = array_map(
				function ( $dependency ) {
					return self::$dependency_api_data[ $dependency ]['Name'];
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
