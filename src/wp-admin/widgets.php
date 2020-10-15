<?php
/**
 * Widget administration screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

/** WordPress Administration Widgets API */
require_once ABSPATH . 'wp-admin/includes/widgets.php';

if ( ! current_user_can( 'edit_theme_options' ) ) {
	wp_die(
		'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to edit theme options on this site.' ) . '</p>',
		403
	);
}

$title       = __( 'Widgets' );
$parent_file = 'themes.php';

/**
 * Filters whether or not to use the block editor to manage widgets.
 *
 * @since 5.6.0
 *
 * @param boolean $use_widgets_block_editor Whether or not to use the block editor to manage widgets.
 */
$use_widgets_block_editor = apply_filters(
	'use_widgets_block_editor',
	get_theme_support( 'widgets-block-editor' )
);

if ( $use_widgets_block_editor ) {
	require ABSPATH . 'wp-admin/widgets-block-editor.php';
} else {
	require ABSPATH . 'wp-admin/widgets-form.php';
}
