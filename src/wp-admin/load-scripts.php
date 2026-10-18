<?php
/**
 * Formerly concatenated the core scripts named in the `load` query parameter.
 *
 * Scripts are no longer concatenated, so this file now only responds with 410 Gone, for code
 * that still links to it directly.
 *
 * @package WordPress
 * @subpackage Administration
 * @deprecated 7.2.0 Scripts are no longer concatenated.
 */

/** Load WordPress Bootstrap */
require_once dirname( __DIR__ ) . '/wp-load.php';

// Send the status first, since a deprecation notice that is displayed would prevent wp_die() from sending it.
status_header( 410 );

_deprecated_file( basename( __FILE__ ), '7.2.0', '', __( 'Scripts are no longer concatenated.' ) );

wp_die( __( 'Scripts are no longer concatenated.' ), '', array( 'response' => 410 ) );
