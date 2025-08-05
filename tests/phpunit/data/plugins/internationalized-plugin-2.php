<?php
/*
Plugin Name: Dummy Plugin 2
Plugin URI: https://wordpress.org/
Description: For testing purposes only. Only has an .l10n.php translation file.
Version: 1.0.0
Text Domain: internationalized-plugin
*/

function i18n_plugin_2_test() {
	return __( 'This is a dummy plugin', 'internationalized-plugin-2' );
}
