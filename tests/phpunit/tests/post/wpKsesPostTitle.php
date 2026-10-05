<?php

/**
 * @group post
 * @group formatting
 *
 * @covers ::wp_kses_post_title
 */
class Tests_Post_WpKsesPostTitle extends WP_UnitTestCase {

	/**
	 * @ticket 66244
	 */
	public function test_allows_formatting_tags_and_class() {
		$title = 'The <em class="title">page</em> <strong>title</strong>';

		$this->assertSame( $title, wp_kses_post_title( $title ) );
	}

	/**
	 * @ticket 66244
	 */
	public function test_strips_disallowed_tags_and_encodes_ampersands() {
		$this->assertSame(
			'The page &amp; title alert(1)',
			wp_kses_post_title( 'The <a href="https://example.com">page</a> & title <script>alert(1)</script>' )
		);
	}

	/**
	 * @ticket 66244
	 */
	public function test_strips_disallowed_attributes() {
		$this->assertSame(
			'<em class="title">page</em>',
			wp_kses_post_title( '<em class="title" onclick="alert(1)">page</em>' )
		);
	}
}
