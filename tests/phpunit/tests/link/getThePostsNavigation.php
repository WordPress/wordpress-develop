<?php

/**
 * Tests the `get_the_posts_navigation()` function.
 *
 * @since 6.2.0
 *
 * @group link
 *
 * @covers ::get_the_posts_navigation
 */
class Tests_Link_GetThePostsNavigation extends WP_UnitTestCase {

	/**
	 * Creates posts before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->post->create_many( 3 );
	}

	/**
	 * Tests that get_the_posts_navigation() only includes the "Older posts" and "Newer" posts
	 * links when appropriate.
	 *
	 * @ticket 55751
	 *
	 * @dataProvider data_get_the_posts_navigation
	 *
	 * @param int  $per_page  Posts per page to be queried.
	 * @param int  $paged_num Pagination page number.
	 * @param bool $older     Whether an "Older posts" link should be included.
	 * @param bool $newer     Whether a "Newer posts" link should be included.
	 */
	public function test_get_the_posts_navigation( $per_page, $paged_num, $older, $newer ) {
		global $wp_query, $paged;

		$paged    = $paged_num;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => $per_page,
				'paged'          => $paged,
			)
		);

		$actual = get_the_posts_navigation();

		if ( $older ) {
			$this->assertStringContainsString(
				'Older posts',
				$actual,
				'Posts navigation must contain an "Older posts" link.'
			);
		}

		if ( $newer ) {
			$this->assertStringContainsString(
				'Newer posts',
				$actual,
				'Posts navigation must contain a "Newer posts" link.'
			);
		}

		if ( ! $older && ! $newer ) {
			$this->assertEmpty(
				$actual,
				'Posts navigation must be an empty string.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_the_posts_navigation() {
		return array(
			'older posts'                 => array(
				'post_per_page' => 1,
				'paged_num'     => 1,
				'older'         => true,
				'newer'         => false,
			),
			'newer posts'                 => array(
				'post_per_page' => 1,
				'paged_num'     => 3,
				'older'         => false,
				'newer'         => true,
			),
			'newer posts and older posts' => array(
				'post_per_page' => 1,
				'paged_num'     => 2,
				'older'         => true,
				'newer'         => true,
			),
			'empty posts'                 => array(
				'post_per_page' => 3,
				'paged_num'     => 1,
				'older'         => false,
				'newer'         => false,
			),
		);
	}

}
