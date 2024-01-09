<?php
/**
 * WordPress incompatible "for Core" plugins handler class.
 *
 * @package WordPress
 * @since 6.5.0
 */

/**
 * Incompatibility "fore Core" plugins handler.
 *
 * @since 6.5.0
 */
class WP_Incompatible_Plugins_Handler {

	const PLUGINS = array(
		'gutenberg/gutenberg.php' => array(
			'name'                       => 'Gutenberg',
			'minimum_compatible_version' => '16.5',
		),
	);

	/**
	 * Plugins for compatibility checks.
	 *
	 * @var array
	 */
	private static $plugins_for_compat_check = array();

	public static function reset() {
		static::$plugins_for_compat_check = array();
	}

	/**
	 * Flags the plugin for compatibility check if it's in the PLUGINS list.
	 *
	 * @since 6.5.0
	 *
	 * @param string  $plugin      The plugin to check.
	 * @param string  $plugin_file The plugin's file.
	 * @param array   $plugins     The list of active and valid plugins to be loaded.
	 */
	public static function maybe_flag_for_compat_check( $plugin, $plugin_file, $plugins ) {
		if ( ! isset( static::PLUGINS[ $plugin ] ) ) {
			return;
		}

		// Get the last index value, as this is where this plugin is stored in the $plugins array.
		end( $plugins );
		$plugins_index = key( $plugins );

		static::$plugins_for_compat_check[ $plugin ] = array(
			'file'                  => $plugin_file,
			'deactivated_version'   => '',
			'index_plugins_to_load' => $plugins_index,
		);
	}

	/**
	 * On WordPress load, deactivate the incompatible plugins.
	 *
	 * Also removes the incompatible plugins from the given $plugins_to_load to
	 * prevent these plugins from loading.
	 *
	 * @since 6.5.0
	 *
	 * @param array $plugins_to_load Activate and valid plugins to load. (Passed by reference.)
	 */
	public static function deactivate_on_wp_load( &$plugins_to_load ) {
		// Bail out if there are no active plugins for compatibility check.
		if ( empty( static::$plugins_for_compat_check ) || empty( $plugins_to_load ) ) {
			return;
		}

		$active_plugins           = (array) get_option( 'active_plugins', array() );
		$active_plugins_by_plugin = array_flip( $active_plugins );
		$found_incompatibles      = array();

		/*
		 * Loop through the plugins to do the compatibility check.
		 * Deactivate each incompatible plugin.
		 */
		foreach ( static::$plugins_for_compat_check as $plugin => $plugin_info ) {

			$index_plugins_to_load = $plugin_info['index_plugins_to_load'];

			if ( ! isset( $plugins_to_load[ $index_plugins_to_load ] ) ) {
				return;
			}

			// Get the Name and Version from the plugin's header.
			$plugin_data = get_file_data(
				$plugins_to_load[ $index_plugins_to_load ],
				array(
					'Name'    => 'Plugin Name',
					'Version' => 'Version',
				),
				'plugin'
			);

			// Whoops, something went wrong. Bail out.
			if ( ! ( isset( $plugin_data['Version'] ) && isset( $plugin_data['Name'] ) ) ) {
				return;
			}

			// Check if compatible. If yes, bail out.
			$min_compat_version = static::PLUGINS[ $plugin ]['minimum_compatible_version'];
			if ( version_compare( $plugin_data['Version'], $min_compat_version, '>=' ) ) {
				return;
			}

			// Add the plugin to found incompatibles.
			$found_incompatibles[ $plugin ] = array(
				'plugin_name'         => $plugin_data['Name'],
				'version_deactivated' => $plugin_data['Version'],
				'version_compatible'  => $min_compat_version,
			);

			// Remove it from the 'active_plugins' option.
			unset( $active_plugins[ $active_plugins_by_plugin[ $plugin ] ] );

			// Remove it from the plugins to be loaded.
			unset( $plugins_to_load[ $index_plugins_to_load ] );
		}

		if ( empty( $found_incompatibles ) ) {
			return;
		}

		// Update the 'active plugins' option, which no longer includes the incompatible plugins.
		update_option( 'active_plugins', $active_plugins );

		// Update the list of incompatible plugins to notify user in the admin.
		$incompatible_plugins = (array) get_option( 'wp_force_deactivation_incompatible_plugins', array() );
		$incompatible_plugins = array_merge( $incompatible_plugins, $found_incompatibles );
		update_option( 'wp_force_deactivation_incompatible_plugins', $incompatible_plugins );
	}

	/**
	 * During Core's update, handles deactivate incompatible plugins.
	 *
	 * @since 6.5.0
	 */
	public static function deactivate_on_core_upgrade() {
		// the code currently exists _upgrade_core_deactivate_incompatible_plugins().
		// Consider either moving to here or modifying the function to use this class.
	}
}
