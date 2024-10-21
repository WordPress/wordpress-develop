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
	 * Ensures canonical redirects are performed for sites with a static front page.
	 *
	 * @ticket 50163
	 */
	public function test_redirect_missing_front_page_pagination_canonical() {
		update_option( 'show_on_front', 'page' );

		$page_id = self::factory()->post->create(
			array(
				'post_title'   => 'front-page-1',
				'post_type'    => 'page',
				'post_content' => "Front Page 1\n<!--nextpage-->\nPage 2",
			)
		);

		update_option( 'page_on_front', $page_id );

		$link = parse_url( get_permalink( $page_id ), PHP_URL_PATH );

		// Valid page numbers should not redirect.
		$this->assertCanonical( $link, $link, 'The home page is not expected to redirect.' );
		$this->assertCanonical( $link . 'page/2/', $link . 'page/2/', 'Page 2 exists and is not expected to redirect.' );

		// Invalid page numbers should redirect to the front page.
		$this->assertCanonical( $link . 'page/3/', $link, 'Page 3 does not exist and is expected to redirect to the home page.' );
	}

	/**
	 * Ensures that canonical redirects are not performed for sites with a blog listing home page.
	 *
	 * @ticket 50163
	 */
	public function test_redirect_missing_front_page_pagination_does_not_affect_posts_canonical() {
		self::factory()->post->create_many( 3 );
		update_option( 'posts_per_page', 2 );

		// Valid page numbers should not redirect.
		$this->assertCanonical( '/', '/', 'Page one of the blog archive should not redirect.' );
		$this->assertCanonical( '/page/2/', '/page/2/', 'Page 2 of the blog archive exists and is not expected to redirect.' );

		// Neither should invalid page numbers.
		$this->assertCanonical( '/page/3/', '/page/3/', 'Page 3 of the blog archive is not populated but is not expected to redirect.' );
	}
}
