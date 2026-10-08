<?php

/**
 * @group wp
 *
 * @covers WP::parse_request
 */
class Tests_WP_QueryPosts extends WP_UnitTestCase {

	/**
	 * @var WP
	 */
	protected $wp;

	public function set_up() {
		parent::set_up();
		$this->wp = new WP();
	}

	/**
	 * Tests that the existing category page has a higher priority than `query_vars["p"]`.
	 *
	 * @ticket 23602
	 */
	public function test_paged_request(): void {
		global $wp_the_query;
		$default_posts_limit  = 10;
		$posts_on_second_page = 2;
		$post_ids             = self::factory()->post->create_many( $default_posts_limit + $posts_on_second_page );
		$route_vars_mock      = array(
			'category_name' => 'uncategorized',
			'paged'         => 2,
		);

		// The last page should contain 2 posts
		$this->wp->main( $route_vars_mock );
		$this->assertSame( $posts_on_second_page, count( $wp_the_query->posts ) );

		// The query_vars["p"] should have no effect on the query if category page contains posts
		$route_vars_mock['p'] = $post_ids[5];
		$this->wp->main( $route_vars_mock );
		$this->assertSame( $posts_on_second_page, count( $wp_the_query->posts ) );
		$this->assertFalse( $wp_the_query->is_single );
		$this->assertTrue( $wp_the_query->is_category );

		// The query_vars["p"] should have effect on the query if category page does not contain posts
		$route_vars_mock['paged'] = 3;
		$this->wp->main( $route_vars_mock );
		$this->assertTrue( $wp_the_query->is_single );
		$this->assertSame( $wp_the_query->posts[0]->ID, $post_ids[5] );

		// If category page does not contain posts and query_vars["p"] is not a valid post ID, it should return 404
		$route_vars_mock['p'] = $post_ids[11] + 1;
		$this->wp->main( $route_vars_mock );
		$this->assertTrue( $wp_the_query->is_404 );
	}
}
