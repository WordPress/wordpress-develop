<?php

/**
 * Tests for query result ordering, specifically indeterminate ordering.
 *
 * @group query
 *
 * @covers WP_Query::posts
 */
class Tests_Query_Orderby extends WP_UnitTestCase {
	function test_order_by_comment_count_does_not_cause_duplication_when_paginating() {
		global $wpdb;

		$posts = self::factory()->post->create_many( 5 );

		$wpdb->update(
			$wpdb->posts,
			array(
				'comment_count' => 50,
			),
			array(
				'ID' => $posts[2],
			)
		);
		$wpdb->update(
			$wpdb->posts,
			array(
				'comment_count' => 100,
			),
			array(
				'ID' => $posts[4],
			)
		);
		$args = array(
			'posts_per_page' => 1,
			'paged'          => 1,
			'orderby'        => 'comment_count',
			'order'          => 'desc',
		);

		$query    = new WP_Query( $args );
		$actual   = array();
		$expected = array(
			$posts[4], // 100 comments
			$posts[2], // 50 comments
			$posts[3], // 0 comments
			$posts[1], // 0 comments
			$posts[0], // 0 comments
		);

		while ( $query->have_posts() ) {
			$actual = array_merge( $actual, wp_list_pluck( $query->posts, 'ID' ) );
			$args['paged']++;
			$query = new WP_Query( $args );
		}

		$this->assertSame( $expected, $actual );
	}
}
