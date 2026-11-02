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
	 * Whether the pagination global was set before the test.
	 *
	 * @var bool
	 */
	private $had_paged;

	/**
	 * The original pagination global.
	 *
	 * @var mixed
	 */
	private $original_paged;

	/**
	 * Saves the incoming pagination global before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->had_paged      = array_key_exists( 'paged', $GLOBALS );
		$this->original_paged = $this->had_paged ? $GLOBALS['paged'] : null;
	}

	/**
	 * Restores the incoming pagination global after each test.
	 */
	public function tear_down() {
		if ( $this->had_paged ) {
			$GLOBALS['paged'] = $this->original_paged;
		} else {
			unset( $GLOBALS['paged'] );
		}

		parent::tear_down();
	}

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

		$this->assertSame( get_pagenum_link( 2 ), get_next_posts_page_link( 2 ) );
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
	 * Tests that omitting max_page defaults to zero and permits the next page.
	 *
	 * @ticket 65819
	 */
	public function test_get_next_posts_page_link_with_default_max_page() {
		global $paged;

		$paged = 2;

		$this->assertSame( get_pagenum_link( 3 ), get_next_posts_page_link() );
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

		$paged         = 1;
		$expected_link = get_pagenum_link( 1 );

		$filtered_page_number = null;
		$filter               = static function ( $link, $page_number ) use ( &$filtered_page_number ) {
			$filtered_page_number = $page_number;
			return $link;
		};

		add_filter( 'get_pagenum_link', $filter, 10, 2 );
		$previous_page_link = get_previous_posts_page_link();
		remove_filter( 'get_pagenum_link', $filter, 10 );

		$this->assertSame( array( $expected_link, 1 ), array( $previous_page_link, $filtered_page_number ) );
	}
}
