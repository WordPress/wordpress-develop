<?php

/**
 * @group link
 * @group comment
 * @covers ::get_next_comments_link
 * @covers ::next_comments_link
 */
class Tests_Link_GetNextCommentsLink extends WP_UnitTestCase {

	public function test_page_should_respect_value_of_cpage_query_var() {
		$p = self::factory()->post->create();
		$this->go_to( get_permalink( $p ) );

		$old_cpage = get_query_var( 'cpage' );
		set_query_var( 'cpage', 3 );

		$link = get_next_comments_link( 'Next', 5 );

		set_query_var( 'cpage', $old_cpage );

		$this->assertStringContainsString( 'cpage=4', $link );
	}

	/**
	 * @ticket 20319
	 */
	public function test_page_should_default_to_1_when_no_cpage_query_var_is_found() {
		$p = self::factory()->post->create();
		$this->go_to( get_permalink( $p ) );

		$old_cpage = get_query_var( 'cpage' );
		set_query_var( 'cpage', '' );

		$link = get_next_comments_link( 'Next', 5 );

		set_query_var( 'cpage', $old_cpage );

		$this->assertStringContainsString( 'cpage=2', $link );
	}

	/**
	 * @ticket 60806
	 */
	public function test_page_should_respect_value_of_page_argument() {
		$p = self::factory()->post->create();
		$this->go_to( get_permalink( $p ) );

		// Check setting the query var is ignored.
		$old_cpage = get_query_var( 'cpage' );
		set_query_var( 'cpage', 2 );

		$link = get_next_comments_link( 'Next', 5, 3 );

		set_query_var( 'cpage', $old_cpage );

		$this->assertStringContainsString( 'cpage=4', $link );
	}

	/**
	 * Tests that next_comments_link() echoes get_next_comments_link().
	 *
	 * @ticket 65819
	 */
	public function test_next_comments_link_echoes_get_next_comments_link() {
		$p = self::factory()->post->create();
		$this->go_to( get_permalink( $p ) );

		$old_cpage = get_query_var( 'cpage' );
		set_query_var( 'cpage', 1 );

		$expected = get_next_comments_link( 'Next Page', 3 );
		$actual   = get_echo( 'next_comments_link', array( 'Next Page', 3 ) );

		set_query_var( 'cpage', $old_cpage );

		$this->assertNotEmpty( $actual );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that next_comments_link() outputs nothing when on the last page.
	 *
	 * @ticket 65819
	 */
	public function test_next_comments_link_outputs_nothing_on_last_page() {
		$p = self::factory()->post->create();
		$this->go_to( get_permalink( $p ) );

		$old_cpage = get_query_var( 'cpage' );
		set_query_var( 'cpage', 3 );

		$actual = get_echo( 'next_comments_link', array( 'Next Page', 3 ) );

		set_query_var( 'cpage', $old_cpage );

		$this->assertSame( '', $actual );
	}
}
