<?php

/**
 * Tests get_the_posts_navigation function
 *
 * @ticket 55751
 * @since 6.1
 *
 * @covers ::get_the_posts_navigation
 */
class Tests_Functions_GetThePostsNavigation extends WP_UnitTestCase {

	/**
	 * Create the posts the tests.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpsetupBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->post->create_many( 3 );
	}


	/**
	 * Data provider method for testing `get_the_posts_navigation()`.
	 *
	 * @return array
	 */
	public function data_get_the_posts_navigation() {
		return array(
			array( 1, 1, true ),
			array( 3, 1, false, true ),
			array( 2, 1, true, true ),
			array( 1, 3, false, false, true ),
		);
	}

	/**
	 * Run tests on `get_the_posts_navigation()`.
	 *
	 * @dataProvider data_get_the_posts_navigation
	 *
	 * @param int       $paged_num          Pagination page number.
	 * @param int       $post_per_page      Posts per page to be queried.
	 * @param boolean   $assert_older_posts Assert older posts nav string.
	 * @param boolean   $assert_newer_posts Assert newer posts nav string.
	 * @param boolean   $assert_empty       Assert empty posts nav string.
	 */
	public function test_get_the_posts_navigation( $paged_num, $post_per_page, $assert_older_posts = false, $assert_newer_posts = false, $assert_empty = false ) {
		global $wp_query;
		global $paged;
		$paged    = $paged_num;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => $post_per_page,
				'paged'          => $paged,
			)
		);

		if ( $assert_older_posts ) {
			$this->assertStringContainsString( 'Older posts', get_the_posts_navigation(), 'Posts navigation must contain string Older posts.' );
		}

		if ( $assert_newer_posts ) {
			$this->assertStringContainsString( 'Newer posts', get_the_posts_navigation(), 'Posts navigation must contain string Older posts.' );
		}

		if ( $assert_empty ) {
			$this->assertEmpty( get_the_posts_navigation(), 'Posts navigation must return empty string.' );
		}

	}
}
