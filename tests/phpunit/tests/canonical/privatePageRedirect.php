<?php

/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_PrivatePageRedirect extends WP_UnitTestCase {
	protected static $private_page_id;
	protected static $public_post_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$private_page_id = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Private Test Page',
				'post_name'   => 'test-page',
				'post_status' => 'private',
			)
		);

		self::$public_post_id = $factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Public Test Post',
				'post_name'   => 'test-page-announcement',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * @ticket 62877
	 */
	public function test_private_page_should_not_redirect() {
		$private_page_url = get_permalink( self::$private_page_id );
		$public_post_url  = get_permalink( self::$public_post_id );

		$private_page_path = parse_url( $private_page_url, PHP_URL_PATH );
		$public_post_path  = parse_url( $public_post_url, PHP_URL_PATH );

		$this->assertFalse(
			redirect_guess_404_permalink(),
			'redirect_guess_404_permalink() should return false when a private page exists at the requested URL'
		);

		set_query_var( 'name', 'test-page' );

		$this->assertFalse(
			redirect_guess_404_permalink(),
			'redirect_guess_404_permalink() should not redirect when requesting a private page URL'
		);
	}
}
