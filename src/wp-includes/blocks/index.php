<?php
/**
 * Used to set up all core blocks used with the block editor.
 *
 * @package WordPress
 */

// Include files required for core blocks registration.
require ABSPATH . WPINC . '/blocks/archives.php';
require ABSPATH . WPINC . '/blocks/block.php';
require ABSPATH . WPINC . '/blocks/calendar.php';
require ABSPATH . WPINC . '/blocks/categories.php';
require ABSPATH . WPINC . '/blocks/latest-comments.php';
require ABSPATH . WPINC . '/blocks/latest-posts.php';
require ABSPATH . WPINC . '/blocks/rss.php';
require ABSPATH . WPINC . '/blocks/search.php';
require ABSPATH . WPINC . '/blocks/shortcode.php';
require ABSPATH . WPINC . '/blocks/social-link.php';
require ABSPATH . WPINC . '/blocks/tag-cloud.php';

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
		'classic',
		'code',
		'column',
		'columns',
		'file',
		'gallery',
		'group',
		'heading',
		'html',
		'image',
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
		'subhead',
		'table',
		'text-columns',
		'verse',
		'video',
	);

	foreach ( $block_folders as $block_folder ) {
		register_block_type_from_metadata(
			ABSPATH . WPINC . '/blocks/' . $block_folder
		);
	}
}
add_action( 'init', 'register_core_block_types_from_metadata' );

/**
  * Given a registered block type settings array, assigns default attributes
  * based on the block supports configuration. It mirrors the same behavior
  * applied on the client using `blocks.registerBlockType` filter.
  *
  * @since 5.5.0
  *
  * @param array $args Block type settings.
  * @return array      Block type settings with default attributes applied.
  */
function register_block_type_add_default_attributes( $args ) {
	// This check accounts for a special case when `attributes` is not set
	// or the value provided is invalid.
	if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
		return $args;
	}

	$attributes = $args['attributes'];
	$supports   = isset( $args['supports'] ) ? $args['supports'] : array();

	if ( ! empty( $supports['align'] ) && empty( $attributes['align']['type'] ) ) {
		$args['attributes']['align'] = array(
			'type' => 'string',
		);
	}

	if ( ! empty( $supports['anchor'] ) && empty( $attributes['anchor']['type'] ) ) {
		$args['attributes']['anchor'] = array(
			'type'      => 'string',
			'source'    => 'attribute',
			'attribute' => 'id',
			'selector'  => '*',
		);
	}

	if (
		( ! isset( $supports['customClassName'] ) || false !== $supports['customClassName'] ) &&
		empty( $attributes['className']['type'] )
	) {
		$args['attributes']['className'] = array(
			'type' => 'string',
		);
	}

	return $args;
}
add_filter( 'register_block_type_args', 'register_block_type_add_default_attributes' );
