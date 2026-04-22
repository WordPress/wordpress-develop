<?php
/**
 * For testing the On This Day dashboard widget.
 */
$posts = array(
	array(
		'date'    => '2019-04-22 09:12:00',
		'title'   => 'Hello, blogosphere',
		'content' => 'First post on a new blog. Today I spun up a tiny WordPress site and wrote my very first words. The plan: show up, write often, and see what happens. Small starts, long timelines.',
		'status'  => 'publish',
		'cats'    => array( 'personal' ),
	),
	array(
		'date'    => '2020-04-22 14:02:00',
		'title'   => 'Sourdough Diaries, Day 37',
		'content' => 'Another lockdown afternoon, another loaf. The crumb is finally opening up, the crust shatters, and the kitchen smells like a small victory. Recipe adjustments below for anyone still chasing their first ear.',
		'status'  => 'publish',
		'cats'    => array( 'cooking', 'journal' ),
	),
	array(
		'date'    => '2021-04-22 18:40:00',
		'title'   => 'Notes from my first 10k',
		'content' => 'I did not expect to finish, let alone enjoy it. Somewhere around kilometer seven the legs stopped complaining and the mind went quiet. I am writing this still a little sweaty, grinning.',
		'status'  => 'publish',
		'cats'    => array( 'running' ),
	),
	array(
		'date'    => '2022-04-22 10:30:00',
		'title'   => 'Earth Day: small rituals',
		'content' => 'Planted two tomato starts and a wall of basil on the balcony. Every year I forget how satisfying it is to put your hands in dirt. Here is a quick list of what I learned from last year that I am doing differently.',
		'status'  => 'publish',
		'cats'    => array( 'garden' ),
	),
	array(
		'date'    => '2022-04-22 21:15:00',
		'title'   => 'Late-night shipping log',
		'content' => 'Pushed v0.3 after a long weekend. Biggest change: the exporter no longer eats emoji. Smallest change: renamed a file I have hated for months. Both matter.',
		'status'  => 'publish',
		'cats'    => array( 'work', 'code' ),
	),
	array(
		'date'    => '2023-04-22 08:05:00',
		'title'   => 'A quiet morning in Kyoto',
		'content' => 'The rain is soft and the maples are bright. Coffee at a six-seat counter, then a slow walk along the Kamo river. Travel note to self: do less, walk more.',
		'status'  => 'publish',
		'cats'    => array( 'travel' ),
	),
	array(
		'date'    => '2024-04-22 16:48:00',
		'title'   => 'On rereading old posts',
		'content' => 'Went back through five years of this blog today. The embarrassing bits are fewer than I feared; the surprising joys are more than I remembered. Keep writing.',
		'status'  => 'publish',
		'cats'    => array( 'journal' ),
	),
	array(
		'date'    => '2024-04-22 22:11:00',
		'title'   => 'Draft ideas I might finish someday',
		'content' => 'A private stash of titles, notes, and half-thoughts. Mostly nonsense, occasionally a spark.',
		'status'  => 'private',
		'cats'    => array(),
	),
	array(
		'date'    => '2025-04-22 11:22:00',
		'title'   => 'Why I moved back to plain text',
		'content' => 'Fancy apps are lovely until they are not. Markdown files in a folder have outlasted every productivity tool I have tried. A short defense of the humble .md.',
		'status'  => 'publish',
		'cats'    => array( 'tools', 'writing' ),
	),
);

$existing = get_posts( array(
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'meta_key'       => '_otd_seed',
	'fields'         => 'ids',
	'post_type'      => 'post',
) );
if ( $existing ) {
	foreach ( $existing as $id ) {
		wp_delete_post( $id, true );
	}
	WP_CLI::log( 'Deleted ' . count( $existing ) . ' existing seed posts.' );
}

foreach ( $posts as $p ) {
	$cat_ids = array();
	foreach ( $p['cats'] as $cat_name ) {
		$term = term_exists( $cat_name, 'category' );
		if ( ! $term ) {
			$term = wp_insert_term( ucfirst( $cat_name ), 'category' );
		}
		if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
			$cat_ids[] = (int) $term['term_id'];
		}
	}

	$post_id = wp_insert_post( array(
		'post_author'   => 1,
		'post_title'    => $p['title'],
		'post_content'  => $p['content'],
		'post_status'   => $p['status'],
		'post_date'     => $p['date'],
		'post_date_gmt' => get_gmt_from_date( $p['date'] ),
		'post_type'     => 'post',
		'post_category' => $cat_ids,
		'meta_input'    => array( '_otd_seed' => '1' ),
	), true );

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( 'Failed: ' . $p['title'] . ' – ' . $post_id->get_error_message() );
	} else {
		WP_CLI::log( 'Created #' . $post_id . ' – ' . $p['date'] . ' – ' . $p['title'] );
	}
}
