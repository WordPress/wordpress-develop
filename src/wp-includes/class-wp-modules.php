<?php
/**
 * Modules API: WP_Modules class.
 *
 * Native support for ES Modules and Import Maps.
 *
 * @package WordPress
 * @subpackage Modules
 */

/**
 * Core class used to register modules.
 */
class WP_Modules {
	/**
	 * Holds the registered modules, keyed by module identifier.
	 *
	 * @since 6.5.0
	 *
	 * @var array
	 */
	private static $registered = array();

	/**
	 * Holds the module identifiers that were enqueued before registered.
	 *
	 * @since 6.5.0
	 *
	 * @var array
	 */
	private static $enqueued_before_registered = array();

	/**
	 * Registers the module if no module with that module identifier has already
	 * been registered.
	 *
	 * @since 6.5.0
	 *
	 * @param string            $module_identifier The identifier of the module. Should be unique. It will be used in the final import map.
	 * @param string            $src               Full URL of the module, or path of the script relative to the WordPress root directory.
	 * @param array             $dependencies      Optional. An array of module identifiers of the dependencies of this module. The dependencies can be strings or arrays. If they are arrays, they need an `id` key with the module identifier, and can contain a `type` key with either `static` or `dynamic`. By default, dependencies that don't contain a type are considered static.
	 * @param string|false|null $version           Optional. String specifying module version number. Defaults to false. It is added to the URL as a query string for cache busting purposes. If SCRIPT_DEBUG is true, the version is the current timestamp. If $version is set to false, the version number is the currently installed WordPress version. If $version is set to null, no version is added.
	 */
	public static function register( $module_identifier, $src, $dependencies = array(), $version = false ) {
		if ( ! isset( self::$registered[ $module_identifier ] ) ) {
			$deps = array();
			foreach ( $dependencies as $dependency ) {
				if ( isset( $dependency['id'] ) ) {
					$deps[] = array(
						'id'   => $dependency['id'],
						'type' => isset( $dependency['type'] ) && 'dynamic' === $dependency['type'] ? 'dynamic' : 'static',
					);
				} elseif ( is_string( $dependency ) ) {
					$deps[] = array(
						'id'   => $dependency,
						'type' => 'static',
					);
				}
			}

			self::$registered[ $module_identifier ] = array(
				'src'          => $src,
				'version'      => $version,
				'enqueued'     => in_array( $module_identifier, self::$enqueued_before_registered, true ),
				'dependencies' => $deps,
			);
		}
	}

	/**
	 * Marks the module to be enqueued in the page.
	 *
	 * @since 6.5.0
	 *
	 * @param string $module_identifier The identifier of the module.
	 */
	public static function enqueue( $module_identifier ) {
		if ( isset( self::$registered[ $module_identifier ] ) ) {
			self::$registered[ $module_identifier ]['enqueued'] = true;
		} elseif ( ! in_array( $module_identifier, self::$enqueued_before_registered, true ) ) {
			self::$enqueued_before_registered[] = $module_identifier;
		}
	}

	/**
	 * Unmarks the module so it is no longer enqueued in the page.
	 *
	 * @since 6.5.0
	 *
	 * @param string $module_identifier The identifier of the module.
	 */
	public static function dequeue( $module_identifier ) {
		if ( isset( self::$registered[ $module_identifier ] ) ) {
			self::$registered[ $module_identifier ]['enqueued'] = false;
		}
		$key = array_search( $module_identifier, self::$enqueued_before_registered, true );
		if ( false !== $key ) {
			array_splice( self::$enqueued_before_registered, $key, 1 );
		}
	}

	/**
	 * Returns the import map array.
	 *
	 * @since 6.5.0
	 *
	 * @return array Array with an `imports` key mapping to an array of module identifiers and their respective URLs, including the version query.
	 */
	public static function get_import_map() {
		$imports = array();
		foreach ( self::get_dependencies( array_keys( self::get_enqueued() ) ) as $module_identifier => $module ) {
			$imports[ $module_identifier ] = $module['src'] . self::get_version_query_string( $module['version'] );
		}
		return array( 'imports' => $imports );
	}

	/**
	 * Prints the import map using a script tag with a type="importmap" attribute.
	 *
	 * @since 6.5.0
	 */
	public static function print_import_map() {
		$import_map = self::get_import_map();
		if ( ! empty( $import_map['imports'] ) ) {
			echo '<script type="importmap">' . wp_json_encode( self::get_import_map(), JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
		}
	}

	/**
	 * Prints all the enqueued modules using script tags with type="module" attributes.
	 *
	 * @since 6.5.0
	 */
	public static function print_enqueued_modules() {
		foreach ( self::get_enqueued() as $module_identifier => $module ) {
			wp_print_script_tag(
				array(
					'type' => 'module',
					'src'  => $module['src'] . self::get_version_query_string( $module['version'] ),
					'id'   => $module_identifier,
				)
			);
		}
	}

	/**
	 * Prints the the static dependencies of the enqueued modules using link tags
	 * with rel="modulepreload" attributes.
	 *
	 * If a module has already been enqueued, it will not be preloaded.
	 *
	 * @since 6.5.0
	 */
	public static function print_module_preloads() {
		foreach ( self::get_dependencies( array_keys( self::get_enqueued() ), array( 'static' ) ) as $module_identifier => $module ) {
			if ( true !== $module['enqueued'] ) {
				echo sprintf(
					'<link rel="modulepreload" href="%s" id="%s">',
					esc_attr( $module['src'] . self::get_version_query_string( $module['version'] ) ),
					esc_attr( $module_identifier )
				);
			}
		}
	}

	/**
	 * Gets the version of a module.
	 *
	 * If SCRIPT_DEBUG is true, the version is the current timestamp. If $version
	 * is set to false, the version number is the currently installed WordPress
	 * version. If $version is set to null, no version is added. Otherwise, the
	 * string passed in $version is used.
	 *
	 * @since 6.5.0
	 *
	 * @param string|false|null $version The version of the module.
	 * @return string A string with the version, prepended by `?ver=`, or an empty string if there is no version.
	 */
	private static function get_version_query_string( $version ) {
		if ( defined( 'SCRIPT_DEBUG ' ) && SCRIPT_DEBUG ) {
			return '?ver=' . time();
		} elseif ( false === $version ) {
			return '?ver=' . get_bloginfo( 'version' );
		} elseif ( null !== $version ) {
			return '?ver=' . $version;
		}
		return '';
	}

	/**
	 * Retrieves an array of enqueued modules.
	 *
	 * @since 6.5.0
	 *
	 * @return array Enqueued modules, keyed by module identifier.
	 */
	private static function get_enqueued() {
		$enqueued = array();
		foreach ( self::$registered as $module_identifier => $module ) {
			if ( true === $module['enqueued'] ) {
				$enqueued[ $module_identifier ] = $module;
			}
		}
		return $enqueued;
	}

	/**
	 * Retrieves all the dependencies for the given module identifiers, filtered
	 * by types.
	 *
	 * It will consolidate an array containing a set of unique dependencies based
	 * on the requested types: 'static', 'dynamic', or both. This method is
	 * recursive and also retrieves dependencies of the dependencies.
	 *
	 * @since 6.5.0
	 *
	 * @param array $module_identifiers The identifiers of the modules for which to gather dependencies.
	 * @param array $types              Optional. Types of dependencies to retrieve: 'static', 'dynamic', or both. Default is both.
	 * @return array List of dependencies, keyed by module identifier.
	 */
	private static function get_dependencies( $module_identifiers, $types = array( 'static', 'dynamic' ) ) {
		return array_reduce(
			$module_identifiers,
			function ( $dependency_modules, $module_identifier ) use ( $types ) {
				$dependencies = array();
				foreach ( self::$registered[ $module_identifier ]['dependencies'] as $dependency ) {
					if (
						in_array( $dependency['type'], $types, true ) &&
						isset( self::$registered[ $dependency['id'] ] ) &&
						! isset( $dependency_modules[ $dependency['id'] ] )
					) {
						$dependencies[ $dependency['id'] ] = self::$registered[ $dependency['id'] ];
					}
				}
				return array_merge( $dependency_modules, $dependencies, self::get_dependencies( array_keys( $dependencies ), $types ) );
			},
			array()
		);
	}
}

/**
 * Registers the module if no module with that module identifier has already
 * been registered.
 *
 * @since 6.5.0
 *
 * @param string            $module_identifier The identifier of the module. Should be unique. It will be used in the final import map.
 * @param string            $src               Full URL of the module, or path of the script relative to the WordPress root directory.
 * @param array             $dependencies      Optional. An array of module identifiers of the dependencies of this module. The dependencies can be strings or arrays. If they are arrays, they need an `id` key with the module identifier, and can contain a `type` key with either `static` or `dynamic`. By default, dependencies that don't contain a type are considered static.
 * @param string|false|null $version           Optional. String specifying module version number. Defaults to false. It is added to the URL as a query string for cache busting purposes. If SCRIPT_DEBUG is true, the version is the current timestamp. If $version is set to false, the version number is the currently installed WordPress version. If $version is set to null, no version is added.
 */
function wp_register_module( $module_identifier, $src, $dependencies = array(), $version = false ) {
	WP_Modules::register( $module_identifier, $src, $dependencies, $version );
}

/**
 * Marks the module to be enqueued in the page.
 *
 * @since 6.5.0
 *
 * @param string $module_identifier The identifier of the module.
 */
function wp_enqueue_module( $module_identifier ) {
	WP_Modules::enqueue( $module_identifier );
}

/**
 * Unmarks the module so it is no longer enqueued in the page.
 *
 * @since 6.5.0
 *
 * @param string $module_identifier The identifier of the module.
 */
function wp_dequeue_module( $module_identifier ) {
	WP_Modules::dequeue( $module_identifier );
}

/**
 * Prints the import map in the head tag.
 *
 * @since 6.5.0
 *
 */
add_action( 'wp_head', array( 'WP_Modules', 'print_import_map' ) );

/**
 * Prints the enqueued modules in the head tag.
 *
 * @since 6.5.0
 */
add_action( 'wp_head', array( 'WP_Modules', 'print_enqueued_modules' ) );

/**
 * Prints the preloaded modules in the head tag.
 *
 * @since 6.5.0
 */
add_action( 'wp_head', array( 'WP_Modules', 'print_module_preloads' ) );
