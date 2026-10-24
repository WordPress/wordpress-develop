<?php

/**
 * Tests for the get_the_comments_pagination() and the_comments_pagination() functions.
 *
 * @group link
 * @group comment
 * @covers ::get_the_comments_pagination
 * @covers ::the_comments_pagination
 */
class Tests_Link_GetTheCommentsPagination extends WP_UnitTestCase {

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Set up test fixtures before class.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->go_to( get_permalink( self::$post_id ) );
	}

	/**
	 * Tests get_the_comments_pagination() when there is only one comment page or no comments.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_comments_pagination_empty_when_single_page() {
		global $wp_query;

		$wp_query->max_num_comment_pages = 1;

		$this->assertSame( '', get_the_comments_pagination() );
	}

	/**
	 * Tests that get_the_comments_pagination() returns expected markup when multiple pages exist.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_comments_pagination_returns_markup() {
		global $wp_query;

		$wp_query->max_num_comment_pages = 3;
		set_query_var( 'cpage', 2 );

		$pagination = get_the_comments_pagination();

		$this->assertStringContainsString( 'class="navigation comments-pagination"', $pagination );
		$this->assertStringContainsString( 'aria-label="Comments pagination"', $pagination );
		$this->assertStringContainsString( 'class="nav-links"', $pagination );
		$this->assertStringContainsString( 'aria-current="page"', $pagination );
		$this->assertStringContainsString( 'cpage=1', $pagination );
		$this->assertStringContainsString( 'cpage=3', $pagination );
	}

	/**
	 * Tests get_the_comments_pagination() with custom arguments.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_comments_pagination_custom_args() {
		global $wp_query;

		$wp_query->max_num_comment_pages = 3;
		set_query_var( 'cpage', 1 );

		$pagination = get_the_comments_pagination(
			array(
				'screen_reader_text' => 'Custom Navigation',
				'aria_label'         => 'Custom ARIA Label',
				'class'              => 'custom-pagination-class',
				'prev_text'          => 'Go Previous',
				'next_text'          => 'Go Next',
			)
		);

		$this->assertStringContainsString( 'class="navigation custom-pagination-class"', $pagination );
		$this->assertStringContainsString( 'aria-label="Custom ARIA Label"', $pagination );
		$this->assertStringContainsString( 'Custom Navigation', $pagination );
		$this->assertStringContainsString( 'Go Next', $pagination );
	}

	/**
	 * Tests that aria_label falls back to screen_reader_text when aria_label is omitted.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_comments_pagination_aria_label_fallback() {
		global $wp_query;

		$wp_query->max_num_comment_pages = 3;
		set_query_var( 'cpage', 1 );

		$pagination = get_the_comments_pagination(
			array(
				'screen_reader_text' => 'Fallback Screen Reader',
				'aria_label'         => '',
			)
		);

		$this->assertStringContainsString( 'aria-label="Fallback Screen Reader"', $pagination );
	}

	/**
	 * Tests that the_comments_pagination() echoes the pagination markup.
	 *
	 * @ticket 65819
	 */
	public function test_the_comments_pagination_echoes() {
		global $wp_query;

		$wp_query->max_num_comment_pages = 2;
		set_query_var( 'cpage', 1 );

		$expected = get_the_comments_pagination();

		$this->expectOutputString( $expected );
		the_comments_pagination();
	}
}
