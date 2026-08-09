<?php
/**
 * Configures the dependency-only Code Reference site.
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$active_plugins = array(
	'code-syntax-block/index.php',
	'phpdoc-parser/plugin.php',
	'posts-to-posts/posts-to-posts.php',
);

foreach ( $active_plugins as $plugin ) {
	$result = validate_plugin( $plugin );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
}

sort( $active_plugins );
update_option( 'active_plugins', $active_plugins );
switch_theme( 'wporg-developer-2023' );
update_option( 'permalink_structure', '/%year%/%monthnum%/%postname%/' );

$ensure_page = static function ( $slug, $title ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $page ) {
		return $page->ID;
	}
	$result = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_name'   => $slug,
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	return $result;
};

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ensure_page( 'home', 'Home' ) );
$ensure_page( 'reference', 'Reference' );

$navigation = '<!-- wp:navigation-link {"label":"Code Reference","type":"custom","url":"/reference/","kind":"custom","isTopLevelLink":true} /-->';
$existing   = get_page_by_path( 'reference-api-menu', OBJECT, 'wp_navigation' );
$nav_args   = array(
	'post_title'   => 'Reference API Menu',
	'post_name'    => 'reference-api-menu',
	'post_type'    => 'wp_navigation',
	'post_status'  => 'publish',
	'post_content' => $navigation,
);
if ( $existing ) {
	$nav_args['ID'] = $existing->ID;
	$result         = wp_update_post( $nav_args, true );
} else {
	$nav_args['import_id'] = 148843;
	$result                = wp_insert_post( $nav_args, true );
}
if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}

global $wpdb;
$reference_types = array(
	'wp-parser-class',
	'wp-parser-function',
	'wp-parser-hook',
	'wp-parser-method',
	'wp-parser-source-file',
);
$count           = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ( %s, %s, %s, %s, %s )",
		$reference_types[0],
		$reference_types[1],
		$reference_types[2],
		$reference_types[3],
		$reference_types[4]
	)
);
if ( 0 !== (int) $count ) {
	throw new RuntimeException( 'Invariant base contains generated reference posts.' );
}

flush_rewrite_rules( false );
