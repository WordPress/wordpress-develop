<?php

/**
 * Tests get_the_posts_navigation function.
 *
 * @ticket 55751
 * @since 6.1.0
 *
 * @covers ::get_the_posts_navigation
 */
class Tests_Functions_GetThePostsNavigation extends WP_UnitTestCase {

	/**
	 * Create posts for the tests.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->post->create_many( 3 );
	}

	/**
	 * Run tests on `get_the_posts_navigation()`.
	 *
	 * @dataProvider data_get_the_posts_navigation
	 *
	 * @param int    $post_per_page      Posts per page to be queried.
	 * @param int    $paged_num          Pagination page number.
	 * @param bool   $assert_older_posts Assert older posts nav string.
	 * @param bool   $assert_newer_posts Assert newer posts nav string.
	 * @param bool   $assert_empty       Assert empty posts nav string.
	 */
	public function test_get_the_posts_navigation(
		$post_per_page, $paged_num,
		$assert_older_posts,
		$assert_newer_posts,
		$assert_empty ) {
		global $wp_query, $paged;
		$paged    = $paged_num;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => $post_per_page,
				'paged'          => $paged,
			)
		);

		$actual = get_the_posts_navigation();

		if ( $assert_older_posts ) {
			$this->assertStringContainsString(
				'Older posts',
				$actual,
				'Posts navigation must contain string Older posts.'
			);
		}

		if ( $assert_newer_posts ) {
			$this->assertStringContainsString(
				'Newer posts',
				$actual,
				'Posts navigation must contain string Newer posts.'
			);
		}

		if ( $assert_empty ) {
			$this->assertEmpty(
				$actual,
				'Posts navigation must return empty string.'
			);
		}

	}

	/**
	 * Data provider method for testing `get_the_posts_navigation()`.
	 *
	 * @return array
	 */
	public function data_get_the_posts_navigation() {
		return array(
			'Assert Older posts navigation string' => array(
				'post_per_page'      => 1,
				'paged_num'          => 1,
				'assert_older_posts' => true,
				'assert_newer_posts' => false,
				'assert_empty'       => false,
			),
			'Assert Newer posts navigation string' => array(
				'post_per_page'      => 1,
				'paged_num'          => 3,
				'assert_older_posts' => false,
				'assert_newer_posts' => true,
				'assert_empty'       => false,
			),
			'Assert Newer posts and Older navigation string' => array(
				'post_per_page'      => 1,
				'paged_num'          => 2,
				'assert_older_posts' => true,
				'assert_newer_posts' => true,
				'assert_empty'       => false,
			),
			'Empty navigation string'              => array(
				'post_per_page'      => 3,
				'paged_num'          => 1,
				'assert_older_posts' => false,
				'assert_newer_posts' => false,
				'assert_empty'       => true,
			),
		);
	}

}
