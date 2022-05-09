<?php
/**
 * Used to set up all core blocks used with the block editor.
 *
 * @package WordPress
 */

// Include files required for core blocks registration.
require ABSPATH . WPINC . '/blocks/legacy-widget.php';
require ABSPATH . WPINC . '/blocks/widget-group.php';
require __DIR__ . './require-blocks.php';

/**
 * Registers core block types using metadata files.
 * Dynamic core blocks are registered separately.
 *
 * @since 5.5.0
 */
function register_core_block_types_from_metadata() {
	$block_folders = array(
		'audio',
		'button',
		'buttons',
		'code',
		'column',
		'columns',
		'embed',
		'freeform',
		'group',
		'heading',
		'html',
		'list',
		'media-text',
		'missing',
		'more',
		'nextpage',
		'paragraph',
		'preformatted',
		'pullquote',
		'quote',
		'separator',
		'social-links',
		'spacer',
		'table',
		'text-columns',
		'verse',
		'video',
	);

	foreach ( $block_folders as $block_folder ) {
		register_block_type(
			ABSPATH . WPINC . '/blocks/' . $block_folder
		);
	}
}
add_action( 'init', 'register_core_block_types_from_metadata' );
