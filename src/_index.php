<?php
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 */

/**
 * Tells WordPress to load the WordPress theme and output it.
 *
 * @var bool
 */
define( 'WP_USE_THEMES', true );

ob_start( 'dms_everything' );

function dms_parse_url( $url ) {
	$url                  = empty( $url ) ? '' : $url;
	$fragment_at          = strpos( $url, '#' );
	$fragment             = false !== $fragment_at ? substr( $url, $fragment_at + 1 ) : '';
	$url_without_fragment = false !== $fragment_at ? substr( $url, 0, $fragment_at ) : $url;
	$query_at             = strpos( $url_without_fragment, '?' );

	$query = false !== $query_at ? substr( $url_without_fragment, $query_at + 1 ) : '';
	$pairs = explode( '&', $query );
	$args  = [];
	foreach ( $pairs as $pair ) {
		if ( '' === $pair ) {
			continue;
		}
		if ( false === strpos( $pair, '=' ) ) {
			$args[ $pair ] = true;
		} else {
			list( $key, $value ) = explode( '=', $pair );
			$args[ $key ] = urldecode( $value );
		}
	}

	$url_without_query = false !== $query_at ? substr( $url_without_fragment, 0, $query_at ) : $url_without_fragment;
	$is_relative       = 0 === strlen( $url_without_query ) || '/' === $url_without_query[0];
	$has_schema        = preg_match( '~^([a-z]+)://~i', $url_without_query, $schema_match );
	$schema            = $has_schema ? $schema_match[1] : '';
	$domain_path       = $has_schema ? substr( $url_without_query, strlen( $schema_match[0] ) ) : $url_without_query;

	if ( ! $is_relative ) {
		$first_slash = strpos( $domain_path, '/' );
		$domain      = false !== $first_slash ? substr( $domain_path, 0, $first_slash ) : '';
		$path        = false !== $first_slash ? substr( $domain_path, $first_slash ) : $domain_path;
	} else {
		$domain      = '';
		$path        = $domain_path;
	}

	return [
		'schema'   => $schema,
		'domain'   => $domain,
		'path'     => $path,
		'query'    => count( $args ) > 0 ? $args : null,
		'fragment' => $fragment,
	];
}

function dms_everything( $html ) {
	$processor = new WP_HTML_Tag_Processor( $html );
	$tag_count = 0;
	$seen_ids = [];

	$errors = [];

	while ( $processor->next_tag() ) {
		$tag_count++;
		$tag_name = $processor->get_tag();

		$id = $processor->get_attribute( 'id' );
		if ( is_string( $id ) ) {
			if ( isset( $seen_ids[ $id ] ) ) {
				$errors[] = "Repeated id = <{$tag_name} id=\"{$id}\">; seen first in {$seen_ids[$id]} tag.";
			} else {
				$seen_ids[ $id ] = $tag_name;
			}
		}

		$src = $processor->get_attribute( 'src' );
		if ( is_string( $src ) ) {
			$url = dms_parse_url( $src );
			$errors[] = "Found URL in <{$tag_name} src=\"{$src}\">.";
			$errors[] = print_r( $url, true );
			$errors[] = "\n";
		}

		$href = $processor->get_attribute( 'href' );
		if ( is_string( $href ) ) {
			$url = dms_parse_url( $href );
			$errors[] = "Found URL in <{$tag_name} href=\"{$href}\">.";
			$errors[] = print_r( $url, true );
			$errors[] = "\n";
		}
	}

	return $html;
//	return count( $errors ) > 0
//		? '<plaintext>' . "Found {$tag_count} tags.\n\n" . implode( "\n", array_map( function( $e ) { return print_r( $e, true ); }, $errors ) )
//		: $html;
}

/** Loads the WordPress Environment and Template */
require __DIR__ . '/wp-blog-header.php';

ob_end_flush();
