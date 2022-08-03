<?php


/**
 * looks in the cache for ids for the current contaxt and if found returns the options needed
 * Reurn null i
 *
 * @return object|void
 */
function performance_conditional_options_options_preload( $pre, $force_cache ) {
	global $wpdb,$alloptions_names,$alloptions_used;

	if ( ! wp_installing() || ! is_multisite() ) {
		$alloptions = wp_cache_get( 'alloptions', 'options', $force_cache );
		if ( false !== $alloptions ) {
			return $alloptions;
		}
	}

	if ( ! empty( $alloptions_names ) ) {
		return $pre;
	}
	$alloptions_names = array();
	if ( performance_conditional_has_persistent_caching() ) {
		$maybe_option_ids = wp_cache_get( performance_conditional_options_get_context(), 'wp_conditional_options' );
	} else {
		$maybe_option_ids = $wpdb->get_results( "SELECT option_value FROM `$wpdb->options` WHERE option_name = '" . performance_conditional_options_get_context() . "'" );
	}

	if ( empty( $maybe_option_ids ) ) {
		return $pre;
	}

	$key_string = $maybe_option_ids[0]->option_value;

	$alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM `$wpdb->options` WHERE option_id IN ( $key_string )" );

	$alloptions = array();
	foreach ( (array) $alloptions_db as $o ) {
		$alloptions[ $o->option_name ] = $o->option_value;
		$alloptions_names[]            = $o->option_name;
	}

	if ( ! empty( $alloptions ) ) {
		if ( ! is_array( $alloptions_used ) ) {
			$alloptions_used = array();
		}
		wp_cache_add( 'alloptions', $alloptions, 'options' );

		return $alloptions;
	}

	return $pre;
}
add_filter( 'pre_get_alloptions', 'performance_conditional_options_options_preload', 1, 2 );

/**
 * @param $option_name
 * @param $default
 *
 * @return false|mixed|void
 */
function performance_conditional_options_get_option( $pre, $option_name, $default = false ) {
	global $alloptions_used;
	if ( ! is_array( $alloptions_used ) ) {
		$alloptions_used = array();
	}

	if ( ! in_array( $option_name, $alloptions_used, true ) && performance_conditional_options_get_context() !== $option_name ) {
		$alloptions_used[] = $option_name;
	}

	return $pre;
}
add_filter( 'pre_option_all', 'performance_conditional_options_get_option', 10, 3 );

/**
 * @return void
 */
function performance_conditional_options_save_options_cache() {
	global $wpdb,$alloptions_names, $alloptions_used;

	if ( array_diff( $alloptions_used, $alloptions_names ) !== array() ) {

		$key_string = "'" . implode( "','", $alloptions_used ) . "'";

		$db_ids = $wpdb->get_results( "select option_id from $wpdb->options where option_name IN  ( $key_string ) order by option_id", ARRAY_A );

		$ids = implode( ',', wp_list_pluck( $db_ids, 'option_id' ) );

		if ( performance_conditional_has_persistent_caching() ) {
			wp_cache_add( performance_conditional_options_get_context(), $ids, 'wp_conditional_options', DAY_IN_SECONDS );
			delete_option( performance_conditional_options_get_context() );
		} else {
			$wpdb->replace(
				$wpdb->options,
				array(
					'option_name'  => performance_conditional_options_get_context(),
					'option_value' => $ids,
					'autoload'     => 'no',
				),
				array( '%s', '%s', '%s' )
			);
		}
		wp_cache_add( 'persistent_test', 'cache_active', 'wp_conditional_options', DAY_IN_SECONDS );
	}
}
add_action( 'shutdown', 'performance_conditional_options_save_options_cache' );

function performance_conditional_options_stats() {
	global $wpdb,$alloptions_names, $alloptions_used;

	$keys_count = count( $alloptions_used );
	$alloptions = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE autoload = 'yes'" );

	$options_keys = array();
	foreach ( $alloptions as $value ) {
		$options_keys[] = $value->option_name;
	}

	$diff_count = count( array_diff( $alloptions_names, $options_keys ) );

	$options_count = count( $alloptions );

	echo "<center>$keys_count options loaded/used instead of an all options count of $options_count</center>";
	echo "<center>Pluss the $keys_count included $diff_count options that were not set to be autoload</center>";
}
add_action( 'shutdown', 'performance_conditional_options_stats', 99 );

/**
 * lets work out the context
 * TODO: expand as needed
 *
 * @return string
 */
function performance_conditional_options_get_context() {
	global $wp_query,$coc;
	//  if ( $coc ) {
	//      return $coc;
	//  }

	$queryied_name = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

	// TODO: add logged in
	$coc = md5( $queryied_name );
	return md5( $coc );
}

function performance_conditional_has_persistent_caching() {
	$cached_value = wp_cache_get( 'persistent_test', 'wp_conditional_options' );

	if ( 'cache_active' == $cached_value ) {
		return true;
	}

	return false;
}
