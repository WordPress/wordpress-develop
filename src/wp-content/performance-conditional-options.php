<?php


/**
 * looks in the cache for ids for the current contaxt and if found returns the options needed
 * Reurn null i
 *
 * @return object|void
 */
function performance_conditional_options_options_preload( $pre, $force_cache ) {
	global $wpdb,$alloptions_names;

	if ( ! wp_installing() || ! is_multisite() ) {
		$alloptions = wp_cache_get( 'alloptions', 'options', $force_cache );
		if ( false !== $alloptions ) {
			return $alloptions;
		}
	}
	var_dump(performance_conditional_options_get_context());

	$alloptions_names = array();
	var_dump(   "SELECT option_value, option_id  FROM `$wpdb->options` WHERE option_name = '" . performance_conditional_options_get_context(). "'" );
	// $maybe_option_ids = wp_cache_get( performance_conditional_options_get_context(), 'wp_conditional_options' );
	$maybe_option_ids = $wpdb->get_results( "SELECT option_value FROM `$wpdb->options` WHERE option_name = '" . performance_conditional_options_get_context(). "'" );
	var_dump( $maybe_option_ids );
	if ( false !== $maybe_option_ids ) {
		//      $suppress      = $wpdb->suppress_errors();
		$key_string = "'" . implode( "','", $maybe_option_ids ) . "'";
		var_dump( $key_string );
		$alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM `$wpdb->options` WHERE option_id IN ( $key_string )", OBJECT );
		//      $wpdb->suppress_errors( $suppress );
		var_dump( $alloptions_db );
		$alloptions_names = array_map(
			static function( $o ) {
				return $o->option_name;
			},
			$alloptions_db
		);

		if ( ! empty( $alloptions_db ) ) {
			return $alloptions_db;
		}
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
	//  var_dump($alloptions_used);

	if ( array_diff( $alloptions_used, $alloptions_names ) !== array() ) {

		$key_string = "'" . implode( "','", $alloptions_used ) . "'";

		$DBids = $wpdb->get_results( "select option_id from $wpdb->options where option_name IN  ( $key_string )", ARRAY_A );

		$ids = implode( ',',  wp_list_pluck( $DBids, 'option_id' ) );
//var_dump( $ids );
		$result = $wpdb->insert( $wpdb->options, array( 'option_value' => $ids, 'autoload' => 'no' ), array( 'option_name' => performance_conditional_options_get_context() ) );
		var_dump( $result );
//		var_dump( update_option( performance_conditional_options_get_context(), 'wp_conditional_options', $ids ) );
		//wp_cache_add( performance_conditional_options_get_context(), $ids, 'wp_conditional_options' );

	}
}
add_action( 'shutdown', 'performance_conditional_options_save_options_cache' );

function performance_conditional_options_stats() {
	global $wpdb,$alloptions_names, $alloptions_used;

	$keys_count = count( $alloptions_names );
	$alloptions = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE autoload = 'yes'" );

	$options_keys = array();
	foreach ( $alloptions as $value ) {
		$options_keys[] = $value->option_name;
	}

	$diff_count = count( array_diff( array_keys( $alloptions_names ), $options_keys ) );

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
	global $wp_query;
	$queryied_name = '';
	if ( null !== $wp_query ) {
		$queryied_object = $wp_query->get_queried_object();
		if ( null !== $queryied_object ) {
			$queryied_name = get_class( $queryied_object );
		}
	} else {
		$queryied_name = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	}
	// TODO: add logged in

	return md5( $queryied_name );
}
