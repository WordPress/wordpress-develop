<?php
/**
 * @group link
 * @covers ::get_post_comments_feed_link
 */
class Tests_Link_GetPostCommentsFeedLink extends WP_UnitTestCase {

	public function test_post_link() {
		$post_id = self::factory()->post->create();

		$link     = get_post_comments_feed_link( $post_id );
		$expected = add_query_arg(
			array(
				'feed' => get_default_feed(),
				'p'    => $post_id,
			),
			home_url( '/' )
		);

		$this->assertSame( $expected, $link );
	}

	public function test_post_pretty_link() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$post_id = self::factory()->post->create();

		$link     = get_post_comments_feed_link( $post_id );
		$expected = get_permalink( $post_id ) . 'feed/';

		$this->assertSame( $expected, $link );
	}

	public function test_attachment_link() {
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$link     = get_post_comments_feed_link( $attachment_id );
		$expected = add_query_arg(
			array(
				'feed' => get_default_feed(),
				'p'    => $attachment_id,
			),
			home_url( '/' )
		);

		$this->assertSame( $expected, $link );
	}

	public function test_attachment_pretty_link_attachment_pages_on() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		update_option( 'wp_attachment_pages_enabled', 1 );

		$post_id       = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);
		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'Burrito',
			)
		);

		$p = get_post( $post_id );

		$link     = get_post_comments_feed_link( $attachment_id );
		$expected = get_permalink( $post_id ) . 'burrito/feed/';

		$this->assertSame( $expected, $link );
	}

	public function test_attachment_pretty_link_attachment_pages_off() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		update_option( 'wp_attachment_pages_enabled', 0 );

		$post_id       = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);
		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'Burrito',
			)
		);

		$p = get_post( $post_id );

		$link     = get_post_comments_feed_link( $attachment_id );
		$expected = get_permalink( $post_id ) . sha1( 'image.jpg' ) . '/feed/';

		$this->assertSame( $expected, $link );
	}

	public function test_attachment_no_name_pretty_link_attachment_pages_on() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		update_option( 'wp_attachment_pages_enabled', 1 );

		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$link     = get_post_comments_feed_link( $attachment_id );
		$expected = get_permalink( $post_id ) . 'attachment/' . $attachment_id . '/feed/';

		$this->assertSame( $expected, $link );
	}

	public function test_attachment_no_name_pretty_link_attachment_pages_off() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		update_option( 'wp_attachment_pages_enabled', 0 );

		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$link     = get_post_comments_feed_link( $attachment_id );
		$expected = get_permalink( $post_id ) . sha1( 'image.jpg' ) . '/feed/';

		$this->assertSame( $expected, $link );
	}

	public function test_unattached_link() {
		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$link     = get_post_comments_feed_link( $attachment_id );
		$expected = add_query_arg(
			array(
				'feed'          => get_default_feed(),
				'attachment_id' => $attachment_id,
			),
			home_url( '/' )
		);

		$this->assertSame( $expected, $link );
	}

	public function test_unattached_pretty_link() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$link     = get_post_comments_feed_link( $attachment_id );
		$expected = add_query_arg( 'attachment_id', $attachment_id, home_url( '/feed/' ) );

		$this->assertSame( $expected, $link );
	}

	/**
	 * @ticket 52814
	 */
	public function test_nonexistent_page() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		// Use the largest integer to ensure the post does not exist.
		$post_id = PHP_INT_MAX;
		$link    = get_post_comments_feed_link( $post_id );

		$this->assertEmpty( $link );
	}
}
