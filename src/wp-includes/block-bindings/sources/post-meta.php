<?php
/**
 * Add the post_meta source to the Block Bindings API.
 *
 * @since 6.5.0
 * @package WordPress
 */
function post_meta_source_callback( $source_attrs ) {
	if ( ! isset( $source_attrs['key'] ) ) {
		return null;
	}

	// Use the postId attribute if available
	if ( isset( $source_attrs['postId'] ) ) {
		$post_id = $source_attrs['postId'];
	} else {
		// $block_instance->context['postId'] is not available in the Image block.
		$post_id = get_the_ID();
	}

	// If a post isn't public, we need to prevent
	// unauthorized users from accessing the post meta.
	$post = get_post( $post_id );
	if ( ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) || post_password_required( $post ) ) {
		return null;
	}

	return get_post_meta( $post_id, $source_attrs['key'], true );
}

function gutenberg_register_block_bindings_post_meta_source() {
	register_block_bindings_source(
		'core/post-meta',
		array(
			'label'              => _x( 'Post Meta', 'block bindings source' ),
			'get_value_callback' => 'post_meta_source_callback',
		)
	);
}

add_action( 'init', 'gutenberg_register_block_bindings_post_meta_source' );
