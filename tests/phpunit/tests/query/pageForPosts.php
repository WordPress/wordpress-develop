<?php

class Tests_Query_PageForPosts extends WP_UnitTestCase {

	private $posts_page_id;
	public function set_up() {
		parent::set_up();

		update_option( 'show_on_front', 'page' );
		$this->posts_page_id = self::factory()->post->create(
			array(
				'post_title' => 'blog-page',
				'post_type'  => 'page',
			)
		);
		update_option( 'page_for_posts', $this->posts_page_id );
		update_option(
			'page_on_front',
			self::factory()->post->create(
				array(
					'post_title'   => 'front-page',
					'post_type'    => 'page',
					'post_content' => "Page 1\n<!--nextpage-->\nPage 2",
				)
			)
		);
	}

	/**
	 * Ensure unpublished posts page returns 404.
	 *
	 * @ticket 60566
	 */
	public function test_unpublished_posts_page_returns_404() {

		wp_update_post(
			array(
				'ID'          => $this->posts_page_id,
				'post_status' => 'draft',
			)
		);

		$q = new WP_Query(
			array(
				'pagename' => 'blog-page',
			)
		);

		$this->assertTrue(
			$q->is_404(),
			'Unpublished posts page with status should return 404'
		);
	}
}
