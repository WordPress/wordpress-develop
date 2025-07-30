<?php
/**
 * Server-side rendering of the `core/terms-query` block.
 *
 * @package WordPress
 */

/**
 * Renders the `core/terms-query` block on the server.
 *
 * @since 6.9.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 *
 * @return string Returns the output of the query, structured using the layout defined by the block's inner blocks.
 */
function render_block_core_terms_query( $attributes, $content, $block ) {
	if ( ! isset( $attributes['termQuery'] ) || ! isset( $block ) ) {
		return '';
	}

	$classnames         = 'wp-block-terms-query';
	$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $classnames ) );

	return sprintf(
		'<div %1$s>%2$s</div>',
		$wrapper_attributes,
		$content
	);
}


/**
 * Registers the `core/terms-query` block on the server.
 *
 * @since 6.9.0
 */
function register_block_core_terms_query() {
	register_block_type_from_metadata(
		__DIR__ . '/terms-query',
		array(
			'render_callback' => 'render_block_core_terms_query',
		)
	);
}
add_action( 'init', 'register_block_core_terms_query' );
