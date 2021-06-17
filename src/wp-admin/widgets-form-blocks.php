<?php
/**
 * The block-based widgets editor, for use in widgets.php.
 *
 * @package WordPress
 * @subpackage Administration
 */

// Don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

// Flag that we're loading the block editor.
$current_screen = get_current_screen();
$current_screen->is_block_editor( true );

$block_editor_context = new WP_Block_Editor_Context();

$preload_paths = array(
	array( '/wp/v2/media', 'OPTIONS' ),
	'/wp/v2/sidebars?context=edit&per_page=-1',
	'/wp/v2/widgets?context=edit&per_page=-1&_embed=about',
);
block_editor_rest_api_preload( $preload_paths, $block_editor_context );

$editor_settings = get_block_editor_settings(
	get_legacy_widget_block_editor_settings(),
	$block_editor_context
);

// The widgets editor doesn't support the Block Directory, so don't load any of
// its assets. This also prevents 'wp-editor' from being enqueued which we
// cannot load in the widgets screen because many widget scripts rely on
// `wp.editor`.
remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );

// Remove 'wp-editor' as a dependency of 'wp-block-library'. We must not load
// 'wp-editor' in the widgets screen because many widget scripts rely on
// `wp.editor`. The block library will still function without this dependency as
// it is only used by the Classic block which is disabled in the widgets editor.
$wp_block_library = wp_scripts()->query( 'wp-block-library' );
wp_scripts()->remove( $wp_block_library->handle );
wp_scripts()->add(
	$wp_block_library->handle,
	$wp_block_library->src,
	array_diff( $wp_block_library->deps, array( 'wp-editor' ) ),
	$wp_block_library->ver,
	$wp_block_library->args
);

wp_add_inline_script(
	'wp-edit-widgets',
	sprintf(
		'wp.domReady( function() {
			wp.editWidgets.initialize( "widgets-editor", %s );
		} );',
		wp_json_encode( $editor_settings )
	)
);

// Preload server-registered block schemas.
wp_add_inline_script(
	'wp-blocks',
	'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings() ) . ');'
);

wp_add_inline_script(
	'wp-blocks',
	sprintf( 'wp.blocks.setCategories( %s );', wp_json_encode( get_block_categories( 'widgets-editor' ) ) ),
	'after'
);

wp_enqueue_script( 'wp-edit-widgets' );
wp_enqueue_script( 'admin-widgets' );
wp_enqueue_style( 'wp-edit-widgets' );

/** This action is documented in wp-admin/edit-form-blocks.php */
do_action( 'enqueue_block_editor_assets' );

/** This action is documented in wp-admin/widgets-form.php */
do_action( 'sidebar_admin_setup' );

require_once ABSPATH . 'wp-admin/admin-header.php';

/** This action is documented in wp-admin/widgets-form.php */
do_action( 'widgets_admin_page' );
?>

<div id="widgets-editor" class="blocks-widgets-container"></div> 

<?php
/** This action is documented in wp-admin/widgets-form.php */
do_action( 'sidebar_admin_page' );

require_once ABSPATH . 'wp-admin/admin-footer.php';
