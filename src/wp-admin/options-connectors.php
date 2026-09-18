<?php
/**
 * Connectors administration screen.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 7.0.0
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die(
		'<p>' . __( 'Sorry, you are not allowed to manage connectors on this site.' ) . '</p>',
		'',
		array(
			'heading'  => __( 'You need a higher level of permission.' ),
			'response' => 503,
		)
	);
}

if ( ! class_exists( '\WordPress\AiClient\AiClient' ) || ! function_exists( 'wp_options_connectors_wp_admin_render_page' ) ) {
	wp_die(
		'<p>' . __( 'The Connectors page requires build files. Please build WordPress and try again.' ) . '</p>',
		'',
		array(
			'heading'  => __( 'The Connectors are not available.' ),
			'response' => 503,
		)
	);
}

// Set the page title.
$title = __( 'Connectors' );

// Set parent file for menu highlighting.
$parent_file = 'options-general.php';

require_once ABSPATH . 'wp-admin/admin-header.php';

// Render the Connectors page.
wp_options_connectors_wp_admin_render_page();

require_once ABSPATH . 'wp-admin/admin-footer.php';
