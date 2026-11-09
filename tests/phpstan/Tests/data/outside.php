<?php
/**
 * A file outside the WordPress root. A reference comment must never resolve to it.
 *
 * @package WordPress
 */

/**
 * Documents a hook where the analysis must not look.
 *
 * @param string $value Value.
 */
function wp_tests_outside_hooks( $value ) {
	/**
	 * Filters a value from outside the tree.
	 *
	 * @param string $value Value.
	 */
	return apply_filters( 'outside_filter', $value );
}
