<?php
/**
 * HTML parsing and modification API
 *
 * @since 6.2
 *
 * @package WordPress
 * @subpackage HTML
 */

/*
 * These helper classes are used by the Tag Processor for tracking
 * content as it parses HTML documents. Using these helper classes
 * instead of PHP arrays has a dramatic impact on performance, in
 * terms of speed as well as memory use.
 */

if ( ! class_exists( 'WP_HTML_Attribute_Token' ) ) {
	/** WP_HTML_Attribute_Token class */
	require_once ABSPATH . WPINC . '/class-wp-html-attribute-token.php';
}

if ( ! class_exists( 'WP_HTML_Span' ) ) {
	/** WP_HTML_Span class */
	require_once ABSPATH . WPINC . '/class-wp-html-span.php';
}

if ( ! class_exists( 'WP_HTML_Text_Replacement' ) ) {
	/** WP_HTML_Text_Replacement class */
	require_once ABSPATH . WPINC . '/class-wp-html-text-replacement.php';
}

/*
 * The WP_HTML_Tag_Processor is intended for linearly scanning through
 * an HTML document, searching for HTML tags matching a given query,
 * and adding, removing, or modifying attributes on those tags.
 */

if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
	/** WP_HTML_Tag_Processor class */
	require_once ABSPATH . WPINC . '/class-wp-html-tag-processor.php';
}
