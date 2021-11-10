<?php
/**
 * Block template areas functions.
 *
 * @package WordPress
 */

/**
 * Registers the navigation areas supported by the current theme. The expected
 * shape of the argument is:
 * array(
 *     'primary'   => 'Primary',
 *     'secondary' => 'Secondary',
 *     'tertiary'  => 'Tertiary',
 * )
 *
 * @param array $new_areas Supported navigation areas.
 */
function register_navigation_areas( $new_areas ) {
	global $navigation_areas;
	$navigation_areas = $new_areas;
}

// Register the default navigation areas.
register_navigation_areas(
	array(
		'primary'   => 'Primary',
		'secondary' => 'Secondary',
		'tertiary'  => 'Tertiary',
	)
);

/**
 * Returns the available navigation areas.
 *
 * @return array Registered navigation areas.
 */
function get_navigation_areas() {
	global $navigation_areas;
	return $navigation_areas;
}
