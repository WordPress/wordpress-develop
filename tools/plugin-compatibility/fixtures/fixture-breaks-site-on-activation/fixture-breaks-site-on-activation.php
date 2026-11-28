<?php
/**
 * Plugin Name: Fixture: Breaks the site on activation
 * Description: Writes a must-use plugin that fatals when it is activated, so every request fails afterwards, even one that skips regular plugins. Expected to fail.
 * Version: 1.0.0
 */

register_activation_hook(
	__FILE__,
	static function () {
		wp_mkdir_p( WPMU_PLUGIN_DIR );
		file_put_contents( WPMU_PLUGIN_DIR . '/fixture-broken-loader.php', "<?php\nfixture_function_that_does_not_exist();\n" );
	}
);
