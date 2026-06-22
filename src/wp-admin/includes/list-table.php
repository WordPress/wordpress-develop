<?php
/**
 * Helper functions for displaying a list of items in an ajaxified HTML table.
 *
 * @package WordPress
 * @subpackage List_Table
 * @since 3.1.0
 */

/**
 * Fetches an instance of a WP_List_Table class.
 *
 * @since 3.1.0
 *
 * @global string $hook_suffix
 *
 * @param string $class_name The type of the list table, which is the class name.
 * @param array  $args       Optional. Arguments to pass to the class. Accepts 'screen'.
 * @return WP_List_Table|false List table object on success, false if the class does not exist.
 */
function _get_list_table( $class_name, $args = array() ) {
	$core_classes = array(
		// Site Admin.
		'WP_Posts_List_Table',
		'WP_Media_List_Table',
		'WP_Terms_List_Table',
		'WP_Users_List_Table',
		'WP_Comments_List_Table',
		'WP_Post_Comments_List_Table',
		'WP_Links_List_Table',
		'WP_Plugin_Install_List_Table',
		'WP_Themes_List_Table',
		'WP_Theme_Install_List_Table',
		'WP_Plugins_List_Table',
		'WP_Application_Passwords_List_Table',

		// Network Admin.
		'WP_MS_Sites_List_Table',
		'WP_MS_Users_List_Table',
		'WP_MS_Themes_List_Table',

		// Privacy requests tables.
		'WP_Privacy_Data_Export_Requests_List_Table',
		'WP_Privacy_Data_Removal_Requests_List_Table',
	);

	if ( in_array( $class_name, $core_classes ) ) {
		if ( isset( $args['screen'] ) ) {
			$args['screen'] = convert_to_screen( $args['screen'] );
		} elseif ( isset( $GLOBALS['hook_suffix'] ) ) {
			$args['screen'] = get_current_screen();
		} else {
			$args['screen'] = null;
		}

		/**
		 * Filters the list table class to instantiate.
		 *
		 * @since 6.1.0
		 *
		 * @param string $class_name The list table class to use.
		 * @param array  $args       An array containing _get_list_table() arguments.
		 */
		$custom_class_name = apply_filters( 'wp_list_table_class_name', $class_name, $args );

		if ( is_string( $custom_class_name ) && class_exists( $custom_class_name ) ) {
			$class_name = $custom_class_name;
		}

		return new $class_name( $args );
	}

	return false;
}

/**
 * Register column headers for a particular screen.
 *
 * @see get_column_headers(), print_column_headers(), get_hidden_columns()
 *
 * @since 2.7.0
 *
 * @param string    $screen The handle for the screen to register column headers for. This is
 *                          usually the hook name returned by the `add_*_page()` functions.
 * @param string[] $columns An array of columns with column IDs as the keys and translated
 *                          column names as the values.
 */
function register_column_headers( $screen, $columns ) {
	new _WP_List_Table_Compat( $screen, $columns );
}

/**
 * Prints column headers for a particular screen.
 *
 * @since 2.7.0
 *
 * @param string|WP_Screen $screen  The screen hook name or screen object.
 * @param bool             $with_id Whether to set the ID attribute or not.
 */
function print_column_headers( $screen, $with_id = true ) {
	$wp_list_table = new _WP_List_Table_Compat( $screen );

	$wp_list_table->print_column_headers( $with_id );
}
