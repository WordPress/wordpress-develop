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
 * Registers core block types using metadata files.
 * Dynamic core blocks are registered separately.
 *
 * @since 5.5.0
 */
function register_core_block_types_from_metadata() {
	static $core_blocks_meta;
	if ( ! $core_blocks_meta ) {
		$core_blocks_meta = require ABSPATH . WPINC . '/blocks/blocks-json.php';
	}

	$suffix = SCRIPT_DEBUG ? '' : '.min';

	$style_fields = array(
		'editorStyle',
		'style',
	);

	foreach ( $core_blocks_meta as $name => $schema ) {
		foreach ( $style_fields as $style_field ) {
			if ( ! isset( $schema[ $style_field ] ) ) {
				$style_handle = 'style' === $style_field ? "wp-block-{$name}" : "wp-block-{$name}-editor";
				wp_register_style(
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
			wp_register_style( $style_handle, $path );
			wp_style_add_data( $style_handle, 'path', ABSPATH . $path );

			$rtl_file = str_replace( "{$suffix}.css", "-rtl{$suffix}.css", ABSPATH . $path );
			if ( is_rtl() && file_exists( $rtl_file ) ) {
				wp_style_add_data( $style_handle, 'rtl', 'replace' );
				wp_style_add_data( $style_handle, 'suffix', $suffix );
				wp_style_add_data( $style_handle, 'path', $rtl_file );
			}

		}
	}

	$block_folders = require BLOCKS_PATH . 'require-static-blocks.php';
	foreach ( $block_folders as $block_folder ) {
		register_block_type_from_metadata(
			BLOCKS_PATH . $block_folder
		);
	}
}
add_action( 'init', 'register_core_block_types_from_metadata' );
