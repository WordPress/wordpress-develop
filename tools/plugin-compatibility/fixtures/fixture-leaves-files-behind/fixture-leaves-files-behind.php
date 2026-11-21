<?php
/**
 * Plugin Name: Fixture: Leaves files behind
 * Description: Writes a must-use plugin that loads this plugin, a root file, a table and an option. Expected to pass, and to leave nothing behind for the next plugin.
 * Version: 1.0.0
 */

register_activation_hook(
	__FILE__,
	static function () {
		global $wpdb;

		// Fatals on every request once this plugin's directory is gone, unless the must-use plugin goes too.
		wp_mkdir_p( WPMU_PLUGIN_DIR );
		file_put_contents( WPMU_PLUGIN_DIR . '/fixture-leftover-loader.php', "<?php\nrequire WP_PLUGIN_DIR . '/fixture-leaves-files-behind/fixture-leaves-files-behind.php';\n" );
		file_put_contents( ABSPATH . 'fixture-leftover.txt', 'left behind' );

		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fixture_leftover ( id INT )" );
		update_option( 'fixture_leftover', 'left behind' );
	}
);
