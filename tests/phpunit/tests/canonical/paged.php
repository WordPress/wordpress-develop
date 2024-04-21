<?php
/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_Paged extends WP_Canonical_UnitTestCase {

	public function test_redirect_canonical_with_nextpage_pagination() {
		$para = 'This is a paragraph.
			This is a paragraph.
			This is a paragraph.';
		$next = '<!--nextpage-->';

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => "{$para}{$next}{$para}{$next}{$para}",
			)
		);

		$link = parse_url( get_permalink( $post_id ), PHP_URL_PATH );

		// Existing page should be displayed as is.
		$this->assertCanonical( $link . '3/', $link . '3/' );
		// Non-existing page should redirect to the permalink.
		$this->assertCanonical( $link . '4/', $link );
	}

	/**
	* Test if it redirects to the front page for non-existing pagination canonical.
	*
	* @ticket 50163
	*/
	public function test_redirect_missing_front_page_pagination_canonical() {

		update_option( 'show_on_front', 'page' );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'front-page-1',
				'post_type'    => 'page',
				'post_content' => "Front Page 1\n<!--nextpage-->\nPage 2",
			)
		);

		update_option( 'page_on_front', $post_id );

		$link = parse_url( get_permalink( $post_id ), PHP_URL_PATH );

		// Non-existing front page canonical should redirect to the front page.
		$this->assertCanonical( $link . '/page/3/', $link );
	}
}
