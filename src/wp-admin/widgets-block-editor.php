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

/**
 * @global array $wp_widget_factory
 * @global array $wp_registered_widgets
 */
global $wp_widget_factory, $wp_registered_widgets;

// Handle requests made by the Legacy Block to widgets.php?widget-preview={}.
if ( isset( $_GET['widget-preview'] ) ) {
	define( 'IFRAME_REQUEST', true );
	wp_legacy_widget_block_preview( $_GET['widget-preview'] );
	exit;
}

// Flag that we're loading the block editor.
$current_screen = get_current_screen();
$current_screen->is_block_editor( true );

$max_upload_size = wp_max_upload_size();
if ( ! $max_upload_size ) {
	$max_upload_size = 0;
}

/** This filter is documented in wp-admin/includes/media.php */
$image_size_names = apply_filters(
	'image_size_names_choose',
	array(
		'thumbnail' => __( 'Thumbnail' ),
		'medium'    => __( 'Medium' ),
		'large'     => __( 'Large' ),
		'full'      => __( 'Full Size' ),
	)
);

$available_image_sizes = array();
foreach ( $image_size_names as $image_size_slug => $image_size_name ) {
	$available_image_sizes[] = array(
		'slug' => $image_size_slug,
		'name' => $image_size_name,
	);
}

/**
 * Filters the list of widget classes that should **not** be offered by the legacy widget block.
 *
 * Returning an empty array will make all the widgets available.
 *
 * @param array $widgets An array of excluded widgets classnames.
 *
 * @since 5.6.0
 */
$widgets_to_exclude_from_legacy_widget_block = apply_filters(
	'widgets_to_exclude_from_legacy_widget_block',
	array(
		'WP_Widget_Block',
		'WP_Widget_Pages',
		'WP_Widget_Calendar',
		'WP_Widget_Archives',
		'WP_Widget_Media_Audio',
		'WP_Widget_Media_Image',
		'WP_Widget_Media_Gallery',
		'WP_Widget_Media_Video',
		'WP_Widget_Meta',
		'WP_Widget_Search',
		'WP_Widget_Text',
		'WP_Widget_Categories',
		'WP_Widget_Recent_Posts',
		'WP_Widget_Recent_Comments',
		'WP_Widget_RSS',
		'WP_Widget_Tag_Cloud',
		'WP_Nav_Menu_Widget',
		'WP_Widget_Custom_HTML',
	)
);

$available_legacy_widgets = array();

if ( ! empty( $wp_widget_factory ) ) {
	foreach ( $wp_widget_factory->widgets as $class => $widget_obj ) {
		$available_legacy_widgets[ $class ] = array(
			'name'              => html_entity_decode( $widget_obj->name ),
			'id_base'           => $widget_obj->id_base,
			// wp_widget_description is not being used because its input parameter is a Widget Id.
			// Widgets id's reference to a specific widget instance.
			// Here we are iterating on all the available widget classes even if no widget instance exists for them.
			'description'       => isset( $widget_obj->widget_options['description'] ) ?
				html_entity_decode( $widget_obj->widget_options['description'] ) :
				null,
			'isReferenceWidget' => false,
			'isHidden'          => in_array( $class, $widgets_to_exclude_from_legacy_widget_block, true ),
		);
	}
}

if ( ! empty( $wp_registered_widgets ) ) {
	foreach ( $wp_registered_widgets as $widget_id => $widget_obj ) {
		$block_widget_start = 'blocks-widget-';
		if (
			( is_array( $widget_obj['callback'] ) &&
			isset( $widget_obj['callback'][0] ) &&
			( $widget_obj['callback'][0] instanceof WP_Widget ) ) ||
			// $widget_id starts with $block_widget_start.
			strncmp( $widget_id, $block_widget_start, strlen( $block_widget_start ) ) === 0
		) {
			continue;
		}
		$available_legacy_widgets[ $widget_id ] = array(
			'name'              => html_entity_decode( $widget_obj['name'] ),
			'description'       => html_entity_decode( wp_widget_description( $widget_id ) ),
			'isReferenceWidget' => true,
		);
	}
}

$editor_settings = array(
	'maxUploadFileSize'      => $max_upload_size,
	'imageSizes'             => $available_image_sizes,
	'availableLegacyWidgets' => $available_legacy_widgets,
	'isRTL'                  => is_rtl(),
);

/**
 * Filters the settings to pass to the widgets block editor.
 *
 * @since 5.6.0
 *
 * @param array $editor_settings Default editor settings.
 */
$editor_settings = apply_filters( 'widgets_block_editor_settings', $editor_settings );

wp_add_inline_script(
	'wp-edit-widgets',
	sprintf(
		'wp.domReady( function() {
			wp.editWidgets.initialize( "widgets-editor", %s );
		} );',
		wp_json_encode( $editor_settings )
	)
);

$preload_paths = array(
	array( '/wp/v2/media', 'OPTIONS' ),
	'/__experimental/sidebars?context=edit&per_page=-1',
);
$preload_data  = array_reduce(
	$preload_paths,
	'rest_preload_api_request',
	array()
);
wp_add_inline_script(
	'wp-api-fetch',
	sprintf(
		'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );',
		wp_json_encode( $preload_data )
	),
	'after'
);

wp_add_inline_script(
	'wp-blocks',
	'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings() ) . ');'
);

wp_enqueue_script( 'wp-edit-widgets' );
wp_enqueue_script( 'admin-widgets' );
wp_enqueue_script( 'wp-format-library' );
wp_enqueue_style( 'wp-edit-widgets' );
wp_enqueue_style( 'wp-format-library' );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div id="widgets-editor" class="blocks-widgets-container"></div>

<?php /* The Legacy Widget block requires this nonce */ ?>
<form method="post">
<?php wp_nonce_field( 'save-sidebar-widgets', '_wpnonce_widgets', false ); ?>
</form>

<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
