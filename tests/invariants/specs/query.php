<?php
/**
 * Invariants of WP_Query.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'counts-consistent' )
		->describe( 'After WP_Query::get_posts(), post_count is the number of posts, and for a paged query which counts its matches, max_num_pages is found_posts over posts_per_page, rounded up.' )
		->include_test_callers()
		->after(
			'WP_Query::get_posts',
			static function ( WP_Invariant_Call $call ) {
				$query = $call->self();
				if ( ! is_array( $query->posts ) ) {
					return;
				}

				if ( ! $call->expect( count( $query->posts ) === $query->post_count, 'WP_Query found %d posts, but its post_count is %d.', count( $query->posts ), $query->post_count ) ) {
					return;
				}

				// A singular query is not paged, and filters and plugins may count posts their own way.
				$per_page = isset( $query->query_vars['posts_per_page'] ) ? (int) $query->query_vars['posts_per_page'] : 0;
				if (
					array() === $query->posts || $per_page < 1 || $query->is_singular || ! empty( $query->query_vars['no_found_rows'] ) || ! empty( $query->query_vars['nopaging'] ) ||
					WP_Invariant::has_callbacks( 'found_posts' ) || WP_Invariant::has_callbacks( 'found_posts_query' ) || WP_Invariant::has_callbacks( 'posts_pre_query' )
				) {
					return;
				}

				$expected = (int) ceil( $query->found_posts / $per_page );
				$call->expect( $expected === $query->max_num_pages, 'WP_Query found %d posts, %d per page, but its max_num_pages is %d.', $query->found_posts, $per_page, $query->max_num_pages );
			}
		),
);
