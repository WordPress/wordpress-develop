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


// Editor Styles.
$styles = array(
	array(
		'css'            => 'body { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif }',
		'__unstableType' => 'core',
	),
);
if ( $editor_styles && current_theme_supports( 'editor-styles' ) ) {
	foreach ( $editor_styles as $style ) {
		if ( preg_match( '~^(https?:)?//~', $style ) ) {
			$response = wp_remote_get( $style );
			if ( ! is_wp_error( $response ) ) {
				$styles[] = array(
					'css'            => wp_remote_retrieve_body( $response ),
					'__unstableType' => 'theme',
				);
			}
		} else {
			$file = get_theme_file_path( $style );
			if ( is_file( $file ) ) {
				$styles[] = array(
					'css'            => file_get_contents( $file ),
					'baseURL'        => get_theme_file_uri( $style ),
					'__unstableType' => 'theme',
				);
			}
		}
	}
}

$editor_settings = get_block_editor_settings(
	array_merge( get_legacy_widget_block_editor_settings(), array( 'styles' => $styles ) ),
	$block_editor_context
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
