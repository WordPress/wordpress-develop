<?php
/**
 * Register the block patterns and block patterns categories
 *
 * @package WordPress
 * @since 5.5.0
 */

/**
 * Loads a Core block pattern.
 *
 * @since 5.5.0
 * @access private
 *
 * @param string $name Block pattern name.
 * @return array Block pattern settings.
 */
function _load_block_pattern( $name ) {
	return require( __DIR__ . '/block-patterns/' . $name . '.php' );
}

register_block_pattern( 'core/text-two-columns', _load_block_pattern( 'text-two-columns' ) );
register_block_pattern( 'core/two-buttons', _load_block_pattern( 'two-buttons' ) );
register_block_pattern( 'core/two-images', _load_block_pattern( 'two-images' ) );
register_block_pattern( 'core/text-two-columns-with-images', _load_block_pattern( 'text-two-columns-with-images' ) );
register_block_pattern( 'core/text-three-columns-buttons', _load_block_pattern( 'text-three-columns-buttons' ) );
register_block_pattern( 'core/large-header', _load_block_pattern( 'large-header' ) );
register_block_pattern( 'core/large-header-paragraph', _load_block_pattern( 'large-header-paragraph' ) );
register_block_pattern( 'core/three-buttons', _load_block_pattern( 'three-buttons' ) );
register_block_pattern( 'core/quote', _load_block_pattern( 'quote' ) );
register_block_pattern( 'core/testimonials', _load_block_pattern( 'testimonials' ) );

register_block_pattern_category( 'buttons', array( 'label' => _x( 'Buttons', 'Block pattern category', 'gutenberg' ) ) );
register_block_pattern_category( 'columns', array( 'label' => _x( 'Columns', 'Block pattern category', 'gutenberg' ) ) );
register_block_pattern_category( 'gallery', array( 'label' => _x( 'Gallery', 'Block pattern category', 'gutenberg' ) ) );
register_block_pattern_category( 'header', array( 'label' => _x( 'Headers', 'Block pattern category', 'gutenberg' ) ) );
register_block_pattern_category( 'text', array( 'label' => _x( 'Text', 'Block pattern category', 'gutenberg' ) ) );
