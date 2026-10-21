<?php

/**
 * Tests for get_next_posts_page_link() and get_previous_posts_page_link().
 *
 * @group link
 * @covers ::get_next_posts_page_link
 * @covers ::get_previous_posts_page_link
 */
class Tests_Link_GetNextPreviousPostsPageLink extends WP_UnitTestCase {

	/**
	 * Tests that get_next_posts_page_link() returns null on single post view.
	 *
	 * @ticket 65819
	 */
	public function test_get_next_posts_page_link_null_on_single() {
		global $wp_query;

		$wp_query            = new WP_Query();
		$wp_query->is_single = true;

		$this->assertNull( get_next_posts_page_link() );
	}

	/**
	 * Tests that get_previous_posts_page_link() returns null on single post view.
	 *
	 * @ticket 65819
	 */
	public function test_get_previous_posts_page_link_null_on_single() {
		global $wp_query;

		$wp_query            = new WP_Query();
		$wp_query->is_single = true;

		$this->assertNull( get_previous_posts_page_link() );
	}

	/**
	 * Tests get_next_posts_page_link() returns the next page URL when more pages exist.
	 *
	 * @ticket 65819
	 */
	public function test_get_next_posts_page_link_returns_next_url() {
		global $paged;

		$paged = 1;

		$this->assertSame( get_pagenum_link( 2 ), get_next_posts_page_link( 3 ) );
	}

	/**
	 * Tests get_next_posts_page_link() returns null when already on or past the last page.
	 *
	 * @ticket 65819
	 */
	public function test_get_next_posts_page_link_null_at_last_page() {
		global $paged;

		$paged = 3;

		$this->assertNull( get_next_posts_page_link( 3 ) );
	}

	/**
	 * Tests get_next_posts_page_link() when max_page argument is omitted or zero.
	 *
	 * @ticket 65819
	 */
	public function test_get_next_posts_page_link_with_zero_max_page() {
		global $paged;

		$paged = 2;

		$this->assertSame( get_pagenum_link( 3 ), get_next_posts_page_link( 0 ) );
	}

	/**
	 * Tests get_previous_posts_page_link() returns the previous page URL.
	 *
	 * @ticket 65819
	 */
	public function test_get_previous_posts_page_link_returns_previous_url() {
		global $paged;

		$paged = 3;

		$this->assertSame( get_pagenum_link( 2 ), get_previous_posts_page_link() );
	}

	/**
	 * Tests get_previous_posts_page_link() clamps to page 1 when on the first page.
	 *
	 * @ticket 65819
	 */
	public function test_get_previous_posts_page_link_clamps_to_page_one() {
		global $paged;

		$paged = 1;

		$this->assertSame( get_pagenum_link( 1 ), get_previous_posts_page_link() );
	}
}
