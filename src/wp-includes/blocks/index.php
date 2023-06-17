<?php
/**
 * Used to set up all core blocks used with the block editor.
 *
 * @package WordPress
 */

define( 'BLOCKS_PATH', ABSPATH . WPINC . '/blocks/' );

// Include files required for core blocks registration.
require BLOCKS_PATH . 'legacy-widget.php';
require BLOCKS_PATH . 'widget-group.php';
require BLOCKS_PATH . 'require-dynamic-blocks.php';

/**
 * Registers core block style handles.
 *
 * While {@see register_block_style_handle()} is typically used for that, the way it is
 * implemented is inefficient for core block styles. Registering those style handles here
 * avoids unnecessary logic and filesystem lookups in the other function.
 *
 * @since 6.3.0
 */
function register_core_block_style_handles() {
	static $core_blocks_meta;
	if ( ! $core_blocks_meta ) {
		$core_blocks_meta = require ABSPATH . WPINC . '/blocks/blocks-json.php';
	}

	$suffix = SCRIPT_DEBUG ? '' : '.min';

	$style_fields = array(
		'editorStyle',
		'style',
	);

	$wp_styles = wp_styles();

	foreach ( $core_blocks_meta as $name => $schema ) {
		foreach ( $style_fields as $style_field ) {
			if ( ! isset( $schema[ $style_field ] ) ) {
				$style_handle = 'style' === $style_field ? "wp-block-{$name}" : "wp-block-{$name}-editor";
				$wp_styles->add(
					$style_handle,
					false
				);
				continue;
			}
			$style_handle = $schema[ $style_field ];
			if ( is_array( $style_handle ) ) {
				continue;
			}
			$path = "/wp-includes/blocks/$name/style{$suffix}.css";
			$wp_styles->add( $style_handle, $path );
			$wp_styles->add_data( $style_handle, 'path', ABSPATH . $path );

			$rtl_file = str_replace( "{$suffix}.css", "-rtl{$suffix}.css", ABSPATH . $path );
			if ( is_rtl() && file_exists( $rtl_file ) ) {
				$wp_styles->add_data( $style_handle, 'rtl', 'replace' );
				$wp_styles->add_data( $style_handle, 'suffix', $suffix );
				$wp_styles->add_data( $style_handle, 'path', $rtl_file );
			}

		}
	}
}
add_action( 'init', 'register_core_block_style_handles', 9 );

/**
 * Registers core block types using metadata files.
 * Dynamic core blocks are registered separately.
 *
 * @since 5.5.0
 */
function register_core_block_types_from_metadata() {
	$block_folders = require BLOCKS_PATH . 'require-static-blocks.php';
	foreach ( $block_folders as $block_folder ) {
		register_block_type_from_metadata(
			BLOCKS_PATH . $block_folder
		);
	}
}
add_action( 'init', 'register_core_block_types_from_metadata' );
