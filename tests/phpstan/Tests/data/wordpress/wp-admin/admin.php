<?php
/**
 * An admin file referencing documentation with a dot segment, as some plugins do.
 *
 * @package WordPress
 */

/**
 * References documentation relative to this file.
 *
 * @param string $value   Value.
 * @param string $context Context.
 */
function wp_tests_admin_hooks( $value, $context ) {
	/** This filter is documented in ../wp-includes/canonical.php */
	$value = apply_filters( 'documented_filter', $value, $context );

	/** This filter is documented in ../outside.php */
	$value = apply_filters( 'outside_filter', $value );

	return $value;
}
