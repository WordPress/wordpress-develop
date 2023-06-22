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
	if ( ! wp_should_load_separate_core_block_assets() ) {
		return;
	}

	static $core_blocks_meta;
	if ( ! $core_blocks_meta ) {
		$core_blocks_meta = require ABSPATH . WPINC . '/blocks/blocks-json.php';
	}

	$includes_url = includes_url();
	$suffix       = wp_scripts_get_suffix();
	$wp_styles    = wp_styles();
	$style_fields = array(
		'style',
		'editorStyle',
	);
	$file_exists  = array();

	foreach ( $core_blocks_meta as $name => $schema ) {
		foreach ( $style_fields as $style_field ) {
			$style_path = "blocks/{$name}/style{$suffix}.css";
			$path       = ABSPATH . WPINC . '/' . $style_path;

			if ( ! isset( $schema[ $style_field ] ) ) {
				$style_handle = 'style' === $style_field ? "wp-block-{$name}" : "wp-block-{$name}-editor";
				if ( ! isset( $file_exists[ $path ] ) ) {
					$file_exists[ $path ] = file_exists( $path );
				}
				if ( ! $file_exists[ $path ] ) {
					$wp_styles->add(
						$style_handle,
						false
					);
					continue;
				}
			}else {
				$style_handle = $schema[$style_field];
			}
			if ( is_array( $style_handle ) ) {
				continue;
			}
			$wp_styles->add( $style_handle, $includes_url . $style_path );
			$wp_styles->add_data( $style_handle, 'path', $path );

			$rtl_file = str_replace( "{$suffix}.css", "-rtl{$suffix}.css", $path );
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
