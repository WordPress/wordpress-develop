<?php

/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_PageOnFront extends WP_Canonical_UnitTestCase {

	public function set_up() {
		parent::set_up();

		update_option( 'show_on_front', 'page' );
		update_option(
			'page_for_posts',
			self::factory()->post->create(
				array(
					'post_title' => 'blog-page',
					'post_type'  => 'page',
				)
			)
		);
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
	 * @dataProvider data
	 */
	public function test( $test_url, $expected, $ticket = 0, $expected_doing_it_wrong = array() ) {
		$this->assertCanonical( $test_url, $expected, $ticket, $expected_doing_it_wrong );
	}

	public function data() {
		/*
		 * Data format:
		 * [0]: Test URL.
		 * [1]: Expected results: Any of the following can be used.
		 *      array( 'url': expected redirection location, 'qv': expected query vars to be set via the rewrite AND $_GET );
		 *      array( expected query vars to be set, same as 'qv' above )
		 *      (string) expected redirect location
		 * [3]: (optional) The ticket the test refers to, Can be skipped if unknown.
		 */
		return array(
			// Check against an odd redirect.
			array( '/page/2/', '/page/2/', 20385 ),
			array( '/?page=2', '/page/2/', 35344 ),
			array( '/page/1/', '/', 35344 ),
			array( '/?page=1', '/', 35344 ),

			// The page designated as the front page should redirect to the front of the site.
			array( '/front-page/', '/', 20385 ),
			// The front page supports the <!--nextpage--> pagination.
			array( '/front-page/2/', '/page/2/', 35344 ),
			array( '/front-page/?page=2', '/page/2/', 35344 ),
			// The posts page does not support the <!--nextpage--> pagination.
			array( '/blog-page/2/', '/blog-page/', 45337 ),
			array( '/blog-page/?page=2', '/blog-page/', 45337 ),
			// The posts page supports regular pagination.
			array( '/blog-page/?paged=2', '/blog-page/page/2/', 20385 ),
		);
	}
}
