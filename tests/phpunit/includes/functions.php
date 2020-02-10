<?php
require_once __DIR__ . '/class-basic-object.php';

/**
 * Retrieves PHPUnit runner version.
 *
 * @return double The version number.
 */
function tests_get_phpunit_version() {
	if ( class_exists( 'PHPUnit_Runner_Version' ) ) {
		$version = PHPUnit_Runner_Version::id();
	} elseif ( class_exists( 'PHPUnit\Runner\Version' ) ) {
		// Must be parsable by PHP 5.2.x.
		$version = call_user_func( 'PHPUnit\Runner\Version::id' );
	} else {
		$version = 0;
	}

	return $version;
}

/**
 * Resets various `$_SERVER` variables that can get altered during tests.
 */
function tests_reset__SERVER() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	$_SERVER['HTTP_HOST']       = WP_TESTS_DOMAIN;
	$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
	$_SERVER['REQUEST_METHOD']  = 'GET';
	$_SERVER['REQUEST_URI']     = '';
	$_SERVER['SERVER_NAME']     = WP_TESTS_DOMAIN;
	$_SERVER['SERVER_PORT']     = '80';
	$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

	unset( $_SERVER['HTTP_REFERER'] );
	unset( $_SERVER['HTTPS'] );
}

/**
 * Adds hooks before loading WP.
 *
 * @see add_filter()
 *
 * @param string   $tag             The name of the filter to hook the $function_to_add callback to.
 * @param callable $function_to_add The callback to be run when the filter is applied.
 * @param int      $priority        Optional. Used to specify the order in which the functions
 *                                  associated with a particular action are executed.
 *                                  Lower numbers correspond with earlier execution,
 *                                  and functions with the same priority are executed
 *                                  in the order in which they were added to the action. Default 10.
 * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
 * @return true
 */
function tests_add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
	global $wp_filter;

	if ( function_exists( 'add_filter' ) ) {
		add_filter( $tag, $function_to_add, $priority, $accepted_args );
	} else {
		$idx = _test_filter_build_unique_id( $tag, $function_to_add, $priority );

		$wp_filter[ $tag ][ $priority ][ $idx ] = array(
			'function'      => $function_to_add,
			'accepted_args' => $accepted_args,
		);
	}
	return true;
}

/**
 * Generates a unique function ID based on the given arguments.
 *
 * @see _wp_filter_build_unique_id()
 *
 * @param string   $tag      Unused. The name of the filter to build ID for.
 * @param callable $function The function to generate ID for.
 * @param int      $priority Unused. The order in which the functions
 *                           associated with a particular action are executed.
 * @return string Unique function ID for usage as array key.
 */
function _test_filter_build_unique_id( $tag, $function, $priority ) {
	if ( is_string( $function ) ) {
		return $function;
	}

	if ( is_object( $function ) ) {
		// Closures are currently implemented as objects.
		$function = array( $function, '' );
	} else {
		$function = (array) $function;
	}

	if ( is_object( $function[0] ) ) {
		// Object class calling.
		return spl_object_hash( $function[0] ) . $function[1];
	} elseif ( is_string( $function[0] ) ) {
		// Static calling.
		return $function[0] . '::' . $function[1];
	}
}

/**
 * Deletes all data from the database.
 */
function _delete_all_data() {
	global $wpdb;

	foreach ( array(
		$wpdb->posts,
		$wpdb->postmeta,
		$wpdb->comments,
		$wpdb->commentmeta,
		$wpdb->term_relationships,
		$wpdb->termmeta,
	) as $table ) {
		//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$table}" );
	}

	foreach ( array(
		$wpdb->terms,
		$wpdb->term_taxonomy,
	) as $table ) {
		//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$table} WHERE term_id != 1" );
	}

	$wpdb->query( "UPDATE {$wpdb->term_taxonomy} SET count = 0" );

	$wpdb->query( "DELETE FROM {$wpdb->users} WHERE ID != 1" );
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE user_id != 1" );
}

/**
 * Deletes all posts from the database.
 */
function _delete_all_posts() {
	global $wpdb;

	$all_posts = $wpdb->get_results( "SELECT ID, post_type from {$wpdb->posts}", ARRAY_A );
	if ( ! $all_posts ) {
		return;
	}

	foreach ( $all_posts as $data ) {
		if ( 'attachment' === $data['post_type'] ) {
			wp_delete_attachment( $data['ID'], true );
		} else {
			wp_delete_post( $data['ID'], true );
		}
	}
}

/**
 * Handles the WP die handler by outputting the given values as text.
 *
 * @param string $message The message.
 * @param string $title   The title.
 * @param array  $args    Array with arguments.
 */
function _wp_die_handler( $message, $title = '', $args = array() ) {
	if ( ! $GLOBALS['_wp_die_disabled'] ) {
		_wp_die_handler_txt( $message, $title, $args );
	} else {
		// Ignore at our peril.
	}
}

/**
 * Disables the WP die handler.
 */
function _disable_wp_die() {
	$GLOBALS['_wp_die_disabled'] = true;
}

/**
 * Enables the WP die handler.
 */
function _enable_wp_die() {
	$GLOBALS['_wp_die_disabled'] = false;
}

/**
 * Returns the die handler.
 *
 * @return string The die handler.
 */
function _wp_die_handler_filter() {
	return '_wp_die_handler';
}

/**
 * Returns the die handler.
 *
 * @return string The die handler.
 */
function _wp_die_handler_filter_exit() {
	return '_wp_die_handler_exit';
}

/**
 * Dies without an exit.
 *
 * @param string $message The message.
 * @param string $title   The title.
 * @param array  $args    Array with arguments.
 */
function _wp_die_handler_txt( $message, $title, $args ) {
	echo "\nwp_die called\n";
	echo "Message : $message\n";
	echo "Title : $title\n";
	if ( ! empty( $args ) ) {
		echo "Args: \n";
		foreach ( $args as $k => $v ) {
			echo "\t $k : $v\n";
		}
	}
}

/**
 * Dies with an exit.
 *
 * @param string $message The message.
 * @param string $title   The title.
 * @param array  $args    Array with arguments.
 */
function _wp_die_handler_exit( $message, $title, $args ) {
	echo "\nwp_die called\n";
	echo "Message : $message\n";
	echo "Title : $title\n";
	if ( ! empty( $args ) ) {
		echo "Args: \n";
		foreach ( $args as $k => $v ) {
			echo "\t $k : $v\n";
		}
	}
	exit( 1 );
}

/**
 * Set a permalink structure.
 *
 * Hooked as a callback to the 'populate_options' action, we use this function to set a permalink structure during
 * `wp_install()`, so that WP doesn't attempt to do a time-consuming remote request.
 *
 * @since 4.2.0
 */
function _set_default_permalink_structure_for_tests() {
	update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
}

/**
 * Helper used with the `upload_dir` filter to remove the /year/month sub directories from the uploads path and URL.
 *
 * @return array The altered array.
 */
function _upload_dir_no_subdir( $uploads ) {
	$subdir = $uploads['subdir'];

	$uploads['subdir'] = '';
	$uploads['path']   = str_replace( $subdir, '', $uploads['path'] );
	$uploads['url']    = str_replace( $subdir, '', $uploads['url'] );

	return $uploads;
}

/**
 * Helper used with the `upload_dir` filter to set https upload URL.
 *
 * @return array The altered array.
 */
function _upload_dir_https( $uploads ) {
	$uploads['url']     = str_replace( 'http://', 'https://', $uploads['url'] );
	$uploads['baseurl'] = str_replace( 'http://', 'https://', $uploads['baseurl'] );

	return $uploads;
}

/**
 * Use the Spy_REST_Server class for the REST server.
 *
 * @return string The server class name.
 */
function _wp_rest_server_class_filter() {
	return 'Spy_REST_Server';
}

// Skip `setcookie` calls in auth_cookie functions due to warning:
// Cannot modify header information - headers already sent by...
tests_add_filter( 'send_auth_cookies', '__return_false' );

/**
 * After the init action has been run once, trying to re-register block types can cause
 * _doing_it_wrong warnings. To avoid this, unhook the block registration functions.
 *
 * @since 5.0.0
 */
function _unhook_block_registration() {
	remove_action( 'init', 'register_block_core_archives' );
	remove_action( 'init', 'register_block_core_block' );
	remove_action( 'init', 'register_block_core_calendar' );
	remove_action( 'init', 'register_block_core_categories' );
	remove_action( 'init', 'register_block_core_latest_comments' );
	remove_action( 'init', 'register_block_core_latest_posts' );
	remove_action( 'init', 'register_block_core_rss' );
	remove_action( 'init', 'register_block_core_search' );
	remove_action( 'init', 'register_block_core_shortcode' );
	remove_action( 'init', 'register_block_core_social_link' );
	remove_action( 'init', 'register_block_core_social_link' );
	remove_action( 'init', 'register_block_core_tag_cloud' );
}
tests_add_filter( 'init', '_unhook_block_registration', 1000 );
