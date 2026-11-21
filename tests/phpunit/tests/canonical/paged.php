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
	 * Tests that a paged singular request without a queried object does not throw warnings.
	 *
	 * @ticket 63522
	 */
	public function test_redirect_canonical_paged_singular_without_queried_object() {
		add_filter( 'pre_handle_404', '__return_true' );
		$this->go_to( home_url( '/?pagename=non-existent-page&page=2' ) );

		$this->assertTrue( is_singular(), 'The request should be singular.' );
		$this->assertNull( get_queried_object(), 'The queried object should be null.' );

		$this->assertNull( redirect_canonical( home_url( '/?pagename=non-existent-page&page=2' ), false ) );
	}
}
