<?php
/**
 * A theme template inheriting documentation from a sibling and from core.
 *
 * @package WordPress
 */

/** This action is documented in author.php */
do_action( 'wptests_theme_hook', $value );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( 'documented_filter', $value, $context );

/** This action is documented in author.php */
do_action( 'wptests_missing_theme_hook', $value );

/** This filter is documented in single.php */
$value = apply_filters( 'documented_filter', $value, $context );
