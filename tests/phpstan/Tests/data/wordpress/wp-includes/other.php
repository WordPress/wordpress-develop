<?php
/**
 * A core file that documents a hook of its own, and none of those in canonical.php.
 *
 * @package WordPress
 */

/**
 * Fires an unrelated hook.
 *
 * @param string $value Value.
 */
function wp_tests_other_hooks( $value ) {
	/**
	 * Filters something else.
	 *
	 * @param string $value Value.
	 */
	return apply_filters( 'other_filter', $value );
}
