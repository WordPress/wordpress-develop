<?php

/**
 * @group link
 * @group comment
 * @covers ::get_previous_comments_link
 */
class Tests_Link_GetPreviousCommentsLink extends WP_UnitTestCase {

	public function test_page_should_respect_value_of_cpage_query_var() {
		$p = self::factory()->post->create();
		$this->go_to( get_permalink( $p ) );

		$cpage = get_query_var( 'cpage' );
		set_query_var( 'cpage', 3 );

		$link = get_previous_comments_link( 'Previous' );

		$this->assertStringContainsString( 'cpage=2', $link );

		set_query_var( 'cpage', $cpage );
	}

	public function test_page_should_default_to_1_when_no_cpage_query_var_is_found() {
		$p = self::factory()->post->create();
		$this->go_to( get_permalink( $p ) );

		$cpage = get_query_var( 'cpage' );
		set_query_var( 'cpage', '' );

		$link = get_previous_comments_link( 'Previous' );

		// Technically, it returns null here.
		$this->assertNull( $link );

		set_query_var( 'cpage', $cpage );
	}

	/**
	 * @ticket 60806
	 */
	public function test_page_should_respect_value_of_page_argument() {
		$p = self::factory()->post->create();
		$this->go_to( get_permalink( $p ) );

		$link = get_previous_comments_link( 'Previous', 3 );

		$this->assertStringContainsString( 'cpage=2', $link );
	}
}
