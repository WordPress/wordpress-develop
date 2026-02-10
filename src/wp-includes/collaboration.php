<?php
/**
 * Bootstraps collaborative editing.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Registers collaboration-related post meta.
 *
 * @since 7.0.0
 *
 * @access private
 */
function wp_collaboration_register_meta() {
	$persisted_crdt_post_meta_key = '_crdt_document';

	register_meta(
		'post',
		$persisted_crdt_post_meta_key,
		array(
			'auth_callback'     => function ( bool $_allowed, string $_meta_key, int $object_id, int $user_id ): bool {
				return user_can( $user_id, 'edit_post', $object_id );
			},
			/*
			 * Revisions must be disabled because we always want to preserve
			 * the latest persisted CRDT document, even when a revision is restored.
			 * This ensures that we can continue to apply updates to a shared document
			 * and peers can simply merge the restored revision like any other incoming
			 * update.
			 *
			 * If we want to persist CRDT documents alongside revisions in the
			 * future, we should do so in a separate meta key.
			 */
			'revisions_enabled' => false,
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
		)
	);
}

/**
 * Injects the real-time collaboration setting into a global variable.
 *
 * @since 6.8.0
 *
 * @access private
 */
function wp_collaboration_inject_setting() {
	if ( get_option( 'enable_real_time_collaboration' ) ) {
		wp_add_inline_script(
			'wp-core-data',
			'window._wpCollaborationEnabled = true;',
			'after'
		);
	}
}
