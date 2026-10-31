<?php

/**
 * Tests for the get_the_comments_navigation() and the_comments_navigation() functions.
 *
 * @group link
 * @group comment
 * @covers ::get_the_comments_navigation
 * @covers ::the_comments_navigation
 */
class Tests_Link_GetTheCommentsNavigation extends WP_UnitTestCase {

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
	 * Tests get_the_comments_navigation() when there is only one comment page.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_comments_navigation_empty_when_single_page() {
		global $wp_query;

		$wp_query->max_num_comment_pages = 1;

		$this->assertSame( '', get_the_comments_navigation() );
	}

	/**
	 * Tests get_the_comments_navigation() pagination links on different pages.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_get_the_comments_navigation_pages
	 *
	 * @param int         $cpage          Current comment page.
	 * @param bool        $has_previous   Whether previous link should exist.
	 * @param bool        $has_next       Whether next link should exist.
	 * @param string|null $previous_query Escaped query suffix for the previous page.
	 * @param string|null $next_query     Escaped query suffix for the next page.
	 */
	public function test_get_the_comments_navigation_pages( $cpage, $has_previous, $has_next, $previous_query, $next_query ) {
		global $wp_query;

		$this->set_permalink_structure( '' );
		update_option( 'default_comments_page', 'oldest' );

		$wp_query->max_num_comment_pages = 3;
		set_query_var( 'cpage', $cpage );

		$nav = get_the_comments_navigation();

		if ( $has_previous ) {
			$this->assertStringContainsString(
				'<div class="nav-previous"><a href="' . home_url( '?p=' . self::$post_id ) . $previous_query . '#comments" >Older comments</a></div>',
				$nav
			);
		} else {
			$this->assertStringNotContainsString( 'class="nav-previous"', $nav );
		}

		if ( $has_next ) {
			$this->assertStringContainsString(
				'<div class="nav-next"><a href="' . home_url( '?p=' . self::$post_id ) . $next_query . '#comments" >Newer comments</a></div>',
				$nav
			);
		} else {
			$this->assertStringNotContainsString( 'class="nav-next"', $nav );
		}
	}

	/**
	 * Data provider for test_get_the_comments_navigation_pages.
	 *
	 * @return array[]
	 */
	public static function data_get_the_comments_navigation_pages() {
		return array(
			'first page'  => array(
				'cpage'          => 1,
				'has_previous'   => false,
				'has_next'       => true,
				'previous_query' => null,
				'next_query'     => '&#038;cpage=2',
			),
			'middle page' => array(
				'cpage'          => 2,
				'has_previous'   => true,
				'has_next'       => true,
				'previous_query' => '',
				'next_query'     => '&#038;cpage=3',
			),
			'last page'   => array(
				'cpage'          => 3,
				'has_previous'   => true,
				'has_next'       => false,
				'previous_query' => '&#038;cpage=2',
				'next_query'     => null,
			),
		);
	}

	/**
	 * Tests custom arguments for get_the_comments_navigation().
	 *
	 * @ticket 65819
	 */
	public function test_get_the_comments_navigation_custom_arguments() {
		global $wp_query;

		$wp_query->max_num_comment_pages = 3;
		set_query_var( 'cpage', 2 );

		$nav = get_the_comments_navigation(
			array(
				'prev_text'          => 'Custom Older',
				'next_text'          => 'Custom Newer',
				'screen_reader_text' => 'Custom Screen Reader',
				'aria_label'         => 'Custom ARIA Label',
				'class'              => 'custom-comment-navigation',
			)
		);

		$this->assertStringContainsString( 'Custom Older', $nav );
		$this->assertStringContainsString( 'Custom Newer', $nav );
		$this->assertStringContainsString( 'Custom Screen Reader', $nav );
		$this->assertStringContainsString( 'aria-label="Custom ARIA Label"', $nav );
		$this->assertStringContainsString( 'class="navigation custom-comment-navigation"', $nav );
	}

	/**
	 * Tests that aria_label falls back to screen_reader_text when aria_label is omitted.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_comments_navigation_aria_label_fallback() {
		global $wp_query;

		$wp_query->max_num_comment_pages = 3;
		set_query_var( 'cpage', 2 );

		$nav = get_the_comments_navigation(
			array(
				'screen_reader_text' => 'Fallback Screen Reader',
				'aria_label'         => '',
			)
		);

		$this->assertStringContainsString( 'aria-label="Fallback Screen Reader"', $nav );

		$omitted_label = get_the_comments_navigation(
			array( 'screen_reader_text' => 'Fallback Screen Reader' )
		);

		$this->assertStringContainsString( 'aria-label="Fallback Screen Reader"', $omitted_label );
	}

	/**
	 * Tests that the_comments_navigation() echoes the navigation markup.
	 *
	 * @ticket 65819
	 */
	public function test_the_comments_navigation_echoes() {
		$this->set_permalink_structure( '' );
		$this->go_to( get_permalink( self::$post_id ) );

		global $wp_query;

		update_option( 'default_comments_page', 'oldest' );

		$wp_query->max_num_comment_pages = 2;
		set_query_var( 'cpage', 1 );

		add_filter(
			'navigation_markup_template',
			static function () {
				return '<nav class="%1$s" aria-label="%4$s"><h2>%2$s</h2>%3$s</nav>';
			}
		);

		$expected = '<nav class="echo-comment-navigation" aria-label="Echo ARIA"><h2>Echo Reader</h2>' .
			'<div class="nav-next"><a href="' . home_url( '?p=' . self::$post_id ) . '&#038;cpage=2#comments" >Echo Newer</a></div></nav>';

		$this->expectOutputString( $expected );
		the_comments_navigation(
			array(
				'class'              => 'echo-comment-navigation',
				'screen_reader_text' => 'Echo Reader',
				'aria_label'         => 'Echo ARIA',
				'prev_text'          => 'Echo Older',
				'next_text'          => 'Echo Newer',
			)
		);
	}
}
