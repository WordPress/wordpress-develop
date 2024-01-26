<?php
/**
 * Add the post_meta source to the Block Bindings API.
 *
 * @since 6.5.0
 * @package WordPress
 */
function post_meta_source_callback( $source_attrs ) {
	// Use the postId attribute if available
	if ( isset( $source_attrs['postId'] ) ) {
		$post_id = $source_attrs['postId'];
	} else {
		// $block_instance->context['postId'] is not available in the Image block.
		$post_id = get_the_ID();
	}

	// If a post isn't public, we need to prevent
	// unauthorized users from accessing the post meta.
	$post = get_post($post_id);
	if ( ( $post && $post->post_status != 'publish' && ! current_user_can( 'read_post', $post_id ) ) || post_password_required( $post_id ) ) {
		return null;
	}

	return get_post_meta( $post_id, $source_attrs['value'], true );
}

wp_block_bindings_register_source(
	'core/post_meta',
	array(
		'label' => __( 'Post Meta' ),
		'get_value_callback' => 'post_meta_source_callback',
	),
);
