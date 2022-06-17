<?php
/**
 * Server-side rendering of the `core/comments-query-loop` block.
 *
 * @package WordPress
 */

/**
 * Registers the `core/comments-query-loop` block on the server.
 * We need this file in order to have the title and description for the block translations.
 * More info in the issue: https://github.com/WordPress/gutenberg/issues/41292
 */
function register_block_core_comments_query_loop() {
	register_block_type_from_metadata(
		__DIR__ . '/comments-query-loop'
	);
}
add_action( 'init', 'register_block_core_comments_query_loop' );