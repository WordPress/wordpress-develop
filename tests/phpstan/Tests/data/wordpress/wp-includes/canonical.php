<?php
/**
 * Canonical hook documentation that the fixtures inherit through reference comments.
 *
 * @package WordPress
 */

/**
 * Fires the hooks documented here.
 *
 * @param string $value   Value.
 * @param string $context Context.
 * @param string $type    Type.
 */
function wp_tests_canonical_hooks( $value, $context, $type ) {
	/**
	 * Filters a value, documented with two parameters.
	 *
	 * @param string $value   The value.
	 * @param string $context The context.
	 */
	$value = apply_filters( 'documented_filter', $value, $context );

	/**
	 * Fires an action documented with one parameter.
	 *
	 * @param string $context The context.
	 */
	do_action( 'documented_action', $context );

	/**
	 * Filters the template hierarchy for a type. A dynamic canonical name.
	 *
	 * @param string[] $templates Templates.
	 */
	$templates = apply_filters( "{$type}_template_hierarchy", array() );

	/**
	 * Filters arguments documented with hash notation.
	 *
	 * @param array $args {
	 *     Arguments.
	 *
	 *     @type string $post_type Post type.
	 *     @type int    $per_page  Per page.
	 * }
	 */
	$args = apply_filters( 'documented_hash_filter', array() );

	/**
	 * A dynamic name anchored on nothing but a separator.
	 *
	 * @param string $value Value.
	 */
	$value = apply_filters( "{$context}_{$type}", $value );

	/** This filter is documented in wp-includes/elsewhere.php */
	$value = apply_filters( 'only_referenced_here', $value );

	return $value;
}
