<?php
/**
 * Completes the Code Reference import before final runtime policy is applied.
 */

if ( ! class_exists( 'DevHub_Parser' ) ) {
	throw new RuntimeException( 'DevHub_Parser is unavailable after import.' );
}

update_option( 'wp_parser_root_import_dir', '/tmp/docs-preview-source' );
if ( ! DevHub_Parser::cache_source_code() ) {
	throw new RuntimeException( 'Source-code caching did not complete.' );
}

$active = array_values(
	array_diff(
		get_option( 'active_plugins', array() ),
		array( 'phpdoc-parser/plugin.php' )
	)
);
sort( $active );
update_option( 'active_plugins', $active );

$post_types = array(
	'classes'   => 'wp-parser-class',
	'methods'   => 'wp-parser-method',
	'functions' => 'wp-parser-function',
	'hooks'     => 'wp-parser-hook',
);
$counts     = array();
foreach ( $post_types as $name => $post_type ) {
	$counts[ $name ] = (int) wp_count_posts( $post_type )->publish;
}

$marker  = array(
	'schemaVersion' => 1,
	'stage'         => 'complete-import',
	'counts'        => $counts,
);
$written = file_put_contents(
	WP_CONTENT_DIR . '/docs-preview-import.json',
	wp_json_encode( $marker, JSON_PRETTY_PRINT ) . "\n"
);
if ( false === $written ) {
	throw new RuntimeException( 'Unable to record the completed import.' );
}

flush_rewrite_rules( false );
