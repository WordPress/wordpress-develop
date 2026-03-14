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
		'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to manage connectors on this site.' ) . '</p>',
		403
	);
}

if ( ! class_exists( '\WordPress\AiClient\AiClient' ) || ! function_exists( 'wp_options_connectors_wp_admin_render_page' ) ) {
	wp_die(
		'<h1>' . __( 'Connectors are not available.' ) . '</h1>' .
		'<p>' . __( 'The Connectors page requires build files. Please run <code>npm install</code> to build the necessary files.' ) . '</p>',
		503
	);
}

// Set the page title.
$title = __( 'Connectors' );

// Set parent file for menu highlighting.
$parent_file  = 'options-general.php';
$submenu_file = 'options-connectors.php';

require_once ABSPATH . 'wp-admin/admin-header.php';

// Render the Connectors page.
wp_options_connectors_wp_admin_render_page();

if ( is_rtl() ) {
	?>
	<style>
		.rtl .admin-ui-page__header > div > div:first-child {
			justify-content: start;
		}

		.rtl ul#adminmenu a.wp-has-current-submenu::after,
		.rtl ul#adminmenu > li.current > a.current::after {
			border-right-color: transparent;
			border-left-color: #fff;
		}
	</style>
	<?php
}

require_once ABSPATH . 'wp-admin/admin-footer.php';
