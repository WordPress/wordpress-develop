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
	 * @since 6.4.0
	 *
	 * @var array
	 */
	protected static $plugins = array();

	/**
	 * Holds plugin directory names to compare with cache.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	protected static $plugin_dirnames = array();

	/**
	 * Holds cached plugin directory names.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	protected static $plugin_dirnames_cache = array();

	/**
	 * Holds sanitized plugin dependency slugs.
	 *
	 * Keyed on the dependent plugin's filepath,
	 * relative to the plugins directory.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	protected static $dependencies = array();

	/**
	 * Holds an array of sanitized plugin dependency slugs.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	protected static $dependency_slugs = array();

	/**
	 * Holds an array of dependent plugin slugs.
	 *
	 * Keyed on the dependent plugin's filepath,
	 * relative to the plugins directory.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	protected static $dependent_slugs = array();

	/**
	 * Holds 'plugins_api()' data for plugin dependencies.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	protected static $dependency_api_data = array();

	/**
	 * Holds plugin dependency filepaths, relative to the plugins directory.
	 *
	 * Keyed on the dependency's slug.
	 *
	 * @since 6.4.0
	 *
	 * @var array
	 */
	protected static $dependency_filepaths = array();

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
	 * Determines whether the plugin has plugins that depend on it.
	 *
	 * @since 6.4.0
	 *
	 * @param string $plugin_file The plugin's filepath, relative to the plugins directory.
	 * @return bool Whether the plugin has plugins that depend on it.
	 */
	public static function has_dependents( $plugin_file ) {
		$slug = str_contains( $plugin_file, '/' ) ? dirname( $plugin_file ) : $plugin_file;
		return in_array( $slug, self::$dependency_slugs, true );
	}

	/**
	 * Determines whether the plugin has plugin dependencies.
	 *
	 * @since 6.4.0
	 *
	 * @param string $plugin_file The plugin's filepath, relative to the plugins directory.
	 * @return bool Whether a plugin has plugin dependencies.
	 */
	public static function has_dependencies( $plugin_file ) {
		return isset( self::$dependencies[ $plugin_file ] );
	}

	/**
	 * Determines whether the plugin has active dependents.
	 *
	 * @since 6.4.0
	 *
	 * @param string $plugin_file The plugin's filepath, relative to the plugins directory.
	 * @return bool Whether the plugin has active dependents.
	 */
	public static function has_active_dependents( $plugin_file ) {
		$dependents = self::get_dependents( dirname( $plugin_file ) );

		foreach ( $dependents as $dependent ) {
			if ( is_plugin_active( $dependent ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determines whether the plugin has unmet dependencies.
	 *
	 * @since 6.4.0
	 *
	 * @param string $plugin_file The plugin's filepath, relative to the plugins directory.
	 * @return bool Whether the plugin has unmet dependencies.
	 */
	public static function has_unmet_dependencies( $plugin_file ) {
		if ( ! isset( self::$dependencies[ $plugin_file ] ) ) {
			return false;
		}

		foreach ( self::$dependencies[ $plugin_file ] as $dependency ) {
			$dependency_filepath = self::get_dependency_filepath( $dependency );

			if ( false === $dependency_filepath || is_plugin_inactive( $dependency_filepath ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the names of plugins that require the plugin.
	 *
	 * @since 6.4.0
	 *
	 * @param array $plugin_data Array of plugin data.
	 * @return array An array of dependent names.
	 */
	public static function get_dependent_names( $plugin_file ) {
		$dependent_names = array();

		if ( empty( self::$plugins ) ) {
			self::$plugins = get_plugins();
		}

		$slug = str_contains( $plugin_file, '/' ) ? dirname( $plugin_file ) : $plugin_file;

		// Single file plugin.
		if ( '.' === $slug ) {
			$slug = basename( $plugin_file );
		}

		foreach ( self::get_dependents( $slug ) as $dependent ) {
			if ( ! isset( $dependent_names[ $dependent ] ) ) {
				$dependent_names[ $dependent ] = self::$plugins[ $dependent ]['Name'];
			}
		}
		sort( $dependent_names );

		return $dependent_names;
	}

	/**
	 * Gets the names of plugins required by the plugin.
	 *
	 * @since 6.4.0
	 *
	 * @param string $plugin_file The dependent plugin's filepath, relative to the plugins directory.
	 * @return array An array of dependency names.
	 */
	public static function get_dependency_names( $plugin_file ) {
		if ( empty( self::$dependency_api_data ) ) {
			self::get_dependency_api_data();
		}

		$dependencies         = self::get_dependencies( $plugin_file );
		$dependency_filepaths = self::get_dependency_filepaths();

		$dependency_names = array();
		foreach ( $dependencies as $dependency ) {
			if ( ! isset( $dependency_filepaths[ $dependency ] ) ) {
				continue;
			}

			// Use the name if it's available, otherwise fall back to the slug.
			if ( isset( self::$dependency_api_data[ $dependency ]['name'] ) ) {
				$name = self::$dependency_api_data[ $dependency ]['name'];
			} elseif ( isset( self::$plugin_dirnames[ $dependency ] ) ) {
				$dependency_filepath = self::get_dependency_filepath( $dependency );
				if ( false !== $dependency_filepath ) {
					$name = self::$plugins[ $dependency_filepath ]['Name'];
				}
			} else {
				$name = $dependency;
			}

			$dependency_names[ $dependency ] = $name;
		}

		return $dependency_names;
	}

	/**
	 * Gets the filepath for a dependency, relative to the plugin's directory.
	 *
	 * @since 6.4.0
	 *
	 * @param string $slug The dependency's slug.
	 * @return string|false If installed, the dependency's filepath relative to the plugins directory, otherwise false.
	 */
	public static function get_dependency_filepath( $slug ) {
		if ( empty( self::$dependency_filepaths ) ) {
			self::get_dependency_filepaths();
		}

		return self::$dependency_filepaths[ $slug ];
	}

	/**
	 * Returns API data for the dependency.
	 *
	 * @since 6.4.0
	 *
	 * @param string $slug The dependency's slug.
	 * @return array|false The dependency's API data on success, otherwise false.
	 */
	public static function get_dependency_data( $slug ) {
		if ( empty( self::$dependency_api_data ) ) {
			self::get_dependency_api_data();
		}

		if ( isset( self::$dependency_api_data[ $slug ] ) ) {
			return self::$dependency_api_data[ $slug ];
		}

		return false;
	}

	/**
	 * Displays an admin notice if dependencies are not installed.
	 *
	 * @since 6.4.0
	 */
	public static function display_admin_notice_for_unmet_dependencies() {
		/*
		 * Plugin deactivated if dependencies not met.
		 * Transient on a 10 second timeout.
		 */
		$deactivate_requires = get_site_transient( 'wp_plugin_dependencies_deactivated_plugins' );
		if ( ! empty( $deactivate_requires ) ) {
			$deactivated_plugins = '';
			foreach ( $deactivate_requires as $deactivated ) {
				$deactivated_plugins .= '<li>' . esc_html( self::$plugins[ $deactivated ]['Name'] ) . '</li>';
			}
			wp_admin_notice(
				sprintf(
					/* translators: 1: plugin names */
					__( 'The following plugin(s) have been deactivated due to uninstalled or inactive dependencies: %s' ),
					"<ul>$deactivated_plugins</ul>"
				),
				array(
					'type'        => 'error',
					'dismissible' => true,
				)
			);
		} else {
			// More dependencies to install.
			$installed_slugs = array();
			foreach ( array_keys( self::$plugins ) as $plugin ) {
				$installed_slugs[] = str_contains( $plugin, '/' ) ? dirname( $plugin ) : $plugin;
			}
			$intersect = array_intersect( self::$dependency_slugs, $installed_slugs );
			asort( $intersect );
			if ( $intersect !== self::$dependency_slugs ) {
				wp_admin_notice(
					__( 'There are additional plugin dependencies that must be installed.' ),
					array(
						'type' => 'info',
					)
				);
			}
		}

		$circular_dependencies = self::get_circular_dependencies();
		if ( ! empty( $circular_dependencies ) && count( $circular_dependencies ) > 1 ) {
			$circular_dependencies = array_unique( $circular_dependencies, SORT_REGULAR );

			if ( ! empty( $circular_dependencies ) && empty( self::$plugin_dirnames ) ) {
				self::get_dependency_filepaths();
			}

			// Build output lines.
			$circular_dependency_lines = '';
			foreach ( $circular_dependencies as $circular_dependency ) {
				$first_filepath             = self::$plugin_dirnames[ $circular_dependency[0] ];
				$second_filepath            = self::$plugin_dirnames[ $circular_dependency[1] ];
				$circular_dependency_lines .= sprintf(
					/* translators: 1: First plugin name, 2: Second plugin name. */
					'<li>' . _x( '%1$s -> %2$s', 'The first plugin requires the second plugin.' ) . '</li>',
					'<strong>' . esc_html( self::$plugins[ $first_filepath ]['Name'] ) . '</strong>',
					'<strong>' . esc_html( self::$plugins[ $second_filepath ]['Name'] ) . '</strong>'
				);
			}

			wp_admin_notice(
				sprintf(
					'<p>%1$s</p><ul>%2$s</ul><p>%3$s</p>',
					__( 'These plugins cannot be activated because their requirements form a loop: ' ),
					$circular_dependency_lines,
					__( 'Please contact the plugin developers and make them aware.' )
				),
				array(
					'type'           => 'warning',
					'paragraph_wrap' => false,
				)
			);
		}
	}

	/**
	 * Checks plugin dependencies after a plugin is installed via AJAX.
	 *
	 * @since 6.4.0
	 */
	public static function check_plugin_dependencies_during_ajax() {
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

		self::get_plugins();
		self::get_plugin_dirnames();

		if ( ! isset( self::$plugin_dirnames[ $slug ] ) ) {
			$status['errorCode']    = 'plugin_not_installed';
			$status['errorMessage'] = __( 'The plugin is not installed.' );
			wp_send_json_error( $status );
		}

		$plugin_file  = self::$plugin_dirnames[ $slug ];
		$dependencies = self::get_dependencies( $plugin_file );

		if ( empty( $dependencies ) ) {
			$status['message'] = __( 'The plugin has no required plugins.' );
			wp_send_json_success( $status );
		}

		$inactive_dependencies = array();
		foreach ( $dependencies as $dependency ) {
			if ( false === self::$plugin_dirnames[ $dependency ] || is_plugin_inactive( self::$plugin_dirnames[ $dependency ] ) ) {
				$inactive_dependencies[] = $dependency;
			}
		}

		if ( ! empty( $inactive_dependencies ) ) {
			$inactive_dependency_names = array_map(
				function ( $dependency ) {
					if ( isset( self::$dependency_api_data[ $dependency ]['Name'] ) ) {
						$inactive_dependency_name = self::$dependency_api_data[ $dependency ]['Name'];
					} else {
						$inactive_dependency_name = $dependency;
					}
					return $inactive_dependency_name;
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

			$dependency_slugs              = self::sanitize_dependency_slugs( $header['RequiresPlugins'] );
			self::$dependencies[ $plugin ] = $dependency_slugs;
			self::$dependency_slugs        = array_merge( self::$dependency_slugs, $dependency_slugs );
		}

		$dependent_keys = array();
		foreach ( array_keys( self::$dependencies ) as $dependency ) {
			$dependent_keys[] = str_contains( $dependency, '/' ) ? dirname( $dependency ) : $dependency;
		}
		self::$dependent_slugs  = array_combine( $dependent_keys, array_map( 'dirname', $dependent_keys ) );
		self::$dependency_slugs = array_unique( self::$dependency_slugs );
	}

	/**
	 * Sanitizes slugs.
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
	 * Gets the slugs of plugins that the dependent requires.
	 *
	 * @since 6.4.0
	 *
	 * @param string $plugin_file The dependent plugin's filepath, relative to the plugins directory.
	 * @return array An array of dependency plugin slugs.
	 */
	protected static function get_dependencies( $plugin_file ) {
		if ( isset( self::$dependencies[ $plugin_file ] ) ) {
			return self::$dependencies[ $plugin_file ];
		}

		return array();
	}

	/**
	 * Gets filepaths of plugins that require the dependency.
	 *
	 * @since 6.4.0
	 *
	 * @param string $slug The dependency's slug.
	 * @return array An array of dependent plugin filepaths, relative to the plugins directory.
	 */
	protected static function get_dependents( $slug ) {
		$dependents = array();

		foreach ( self::$dependencies as $dependent => $dependencies ) {
			if ( in_array( $slug, $dependencies, true ) ) {
				$dependents[] = $dependent;
			}
		}

		return $dependents;
	}

	/**
	 * Gets plugin filepaths for active plugins that depend on the dependency.
	 *
	 * Recurses for each dependent that is also a dependency.
	 *
	 * @param string $plugin_file The dependency's filepath, relative to the plugin directory.
	 * @return string[] An array of active dependent plugin filepaths, relative to the plugin directory.
	 */
	protected static function get_active_dependents_in_dependency_tree( $plugin_file ) {
		$all_dependents = array();
		$slug           = str_contains( $plugin_file, '/' ) ? dirname( $plugin_file ) : $plugin_file;

		$dependents = self::get_dependents( $slug );

		if ( empty( $dependents ) ) {
			return $all_dependents;
		}

		foreach ( $dependents as $dependent ) {
			if ( is_plugin_active( $dependent ) ) {
				$all_dependents[] = $dependent;
				$all_dependents   = array_merge(
					$all_dependents,
					self::get_active_dependents_in_dependency_tree( $dependent )
				);
			}
		}

		return $all_dependents;
	}

	/**
	 * Deactivates dependent plugins with unmet dependencies.
	 *
	 * @since 6.4.0
	 */
	protected static function deactivate_dependents_with_unmet_dependencies() {
		$dependents_to_deactivate = array();
		$circular_dependencies    = array_reduce(
			self::get_circular_dependencies(),
			function( $all_circular, $circular_pair ) {
				return array_merge( $all_circular, $circular_pair );
			},
			array()
		);

		foreach ( self::$dependencies as $dependent => $dependencies ) {
			// Skip dependents that are no longer installed or aren't active.
			if ( ! array_key_exists( $dependent, self::$plugins ) || is_plugin_inactive( $dependent ) ) {
				continue;
			}

			// Skip plugins within a circular dependency tree or plugins that have no unmet dependencies.
			if ( in_array( $dependent, $circular_dependencies, true ) || ! self::has_unmet_dependencies( $dependent ) ) {
				continue;
			}

			$dependents_to_deactivate[] = $dependent;

			// Also add any plugins that rely on any of this plugin's dependents.
			$dependents_to_deactivate = array_merge(
				$dependents_to_deactivate,
				self::get_active_dependents_in_dependency_tree( $dependent )
			);
		}

		$dependents_to_deactivate = array_unique( $dependents_to_deactivate );

		deactivate_plugins( $dependents_to_deactivate );
		set_site_transient( 'wp_plugin_dependencies_deactivated_plugins', $dependents_to_deactivate, 10 );
	}

	/**
	 * Gets the filepath of installed dependencies.
	 * If a dependency is not installed, the filepath defaults to false.
	 *
	 * @since 6.4.0
	 *
	 * @return array An array of install dependencies filepaths, relative to the plugins directory.
	 */
	protected static function get_dependency_filepaths() {
		if ( ! empty( self::$dependency_filepaths ) ) {
			return self::$dependency_filepaths;
		}

		$dependency_filepaths = array();

		if ( empty( self::$plugins ) ) {
			return $dependency_filepaths;
		}

		$plugin_dirnames = self::get_plugin_dirnames();
		if ( empty( $plugin_dirnames ) ) {
			return $dependency_filepaths;
		}

		foreach ( self::$dependency_slugs as $slug ) {
			if ( isset( $plugin_dirnames[ $slug ] ) ) {
				$dependency_filepaths[ $slug ] = self::$plugin_dirnames[ $slug ];
				continue;
			}

			$dependency_filepaths[ $slug ] = false;
		}

		self::$dependency_filepaths = $dependency_filepaths;

		return self::$dependency_filepaths;
	}

	/**
	 * Retrieves and stores dependency plugin data from the WordPress.org Plugin API.
	 *
	 * @since 6.4.0
	 */
	protected static function get_dependency_api_data() {
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
	 * Gets plugin directory names.
	 *
	 * @since 6.4.0
	 *
	 * @return array An array of plugin directory names.
	 */
	protected static function get_plugin_dirnames() {
		// Cache the plugin directory names.
		if ( empty( self::$plugin_dirnames ) || self::$plugin_dirnames_cache !== self::$plugins ) {
			self::$plugin_dirnames       = array();
			self::$plugin_dirnames_cache = self::$plugins;

			foreach ( array_keys( self::$plugins ) as $plugin ) {
				$slug                           = str_contains( $plugin, '/' ) ? dirname( $plugin ) : $plugin;
				self::$plugin_dirnames[ $slug ] = $plugin;
			}
		}

		return self::$plugin_dirnames;
	}

	/**
	 * Gets circular dependency data.
	 *
	 * @since 6.4.0
	 *
	 * @return array[] An array of circular dependency pairings.
	 */
	protected static function get_circular_dependencies() {
		$circular_dependencies = array();
		foreach ( self::$dependencies as $dependent => $dependencies ) {
			/*
			 * $dependent is in 'a/a.php' format. Dependencies are stored as slugs, i.e. 'a'.
			 *
			 * Convert $dependent to slug format for checking.
			 */
			$dependent_slug = str_contains( $dependent, '/' ) ? dirname( $dependent ) : $dependent;

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

	/**
	 * Checks for circular dependencies.
	 *
	 * @since 6.4.0
	 *
	 * @param array $dependents   Array of dependent plugins.
	 * @param array $dependencies Array of plugins dependencies.
	 * @return array A circular dependency pairing, or an empty array if none exists.
	 */
	protected static function check_for_circular_dependencies( $dependents, $dependencies ) {
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
}
