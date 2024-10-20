<?php

/**
 * Test avatar related functions
 *
 * @group avatar
 *
 * @covers ::get_avatar_url
 */
class Functions_GetAvatarUrl_Test extends WP_UnitTestCase {
	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_gravatar_url() {
		$url = get_avatar_url( 1 );
		$this->assertSame( preg_match( '|^https?://secure.gravatar.com/avatar/[0-9a-f]{32}\?|', $url ), 1 );
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_size() {
		$url = get_avatar_url( 1 );
		$this->assertSame( preg_match( '|\?.*s=96|', $url ), 1 );

		$args = array( 'size' => 100 );
		$url  = get_avatar_url( 1, $args );
		$this->assertSame( preg_match( '|\?.*s=100|', $url ), 1 );
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_default() {
		$url = get_avatar_url( 1 );
		$this->assertSame( preg_match( '|\?.*d=mm|', $url ), 1 );

		$args = array( 'default' => 'wavatar' );
		$url  = get_avatar_url( 1, $args );
		$this->assertSame( preg_match( '|\?.*d=wavatar|', $url ), 1 );

		$this->assertSame( preg_match( '|\?.*f=y|', $url ), 0 );
		$args = array( 'force_default' => true );
		$url  = get_avatar_url( 1, $args );
		$this->assertSame( preg_match( '|\?.*f=y|', $url ), 1 );
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_rating() {
		$url = get_avatar_url( 1 );
		$this->assertSame( preg_match( '|\?.*r=g|', $url ), 1 );

		$args = array( 'rating' => 'M' );
		$url  = get_avatar_url( 1, $args );
		$this->assertSame( preg_match( '|\?.*r=m|', $url ), 1 );
	}

	/**
	 * Ensures the get_avatar_url always returns an HTTPS scheme for gravatars.
	 *
	 * @ticket 21195
	 * @ticket 37454
	 *
	 * @covers ::get_avatar_url
	 */
	public function test_get_avatar_url_scheme() {
		$url = get_avatar_url( 1 );
		$this->assertSame( preg_match( '|^https://|', $url ), 1, 'Avatars should default to the HTTPS scheme' );

		$args = array( 'scheme' => 'https' );
		$url  = get_avatar_url( 1, $args );
		$this->assertSame( preg_match( '|^https://|', $url ), 1, 'Requesting the HTTPS scheme should be respected' );

		$args = array( 'scheme' => 'http' );
		$url  = get_avatar_url( 1, $args );
		$this->assertSame( preg_match( '|^https://|', $url ), 1, 'Requesting the HTTP scheme should return an HTTPS URL to avoid redirects' );

		$args = array( 'scheme' => 'lolcat' );
		$url  = get_avatar_url( 1, $args );
		$this->assertSame( preg_match( '|^lolcat://|', $url ), 0, 'Unrecognized schemes should be ignored' );
		$this->assertSame( preg_match( '|^https://|', $url ), 1, 'Unrecognized schemes should return an HTTPS URL' );
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_user() {
		$url = get_avatar_url( 1 );

		$url2 = get_avatar_url( WP_TESTS_EMAIL );
		$this->assertSame( $url, $url2 );

		$url2 = get_avatar_url( md5( WP_TESTS_EMAIL ) . '@md5.gravatar.com' );
		$this->assertSame( $url, $url2 );

		$user = get_user_by( 'id', 1 );
		$url2 = get_avatar_url( $user );
		$this->assertSame( $url, $url2 );

		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );
		$post    = get_post( $post_id );
		$url2    = get_avatar_url( $post );
		$this->assertSame( $url, $url2 );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'user_id'         => 1,
			)
		);
		$comment    = get_comment( $comment_id );
		$url2       = get_avatar_url( $comment );
		$this->assertSame( $url, $url2 );
	}

	protected $fake_url;
	/**
	 * @ticket 21195
	 */
	public function test_pre_get_avatar_url_filter() {
		$this->fake_url = 'haha wat';

		add_filter( 'pre_get_avatar_data', array( $this, 'pre_get_avatar_url_filter' ), 10, 1 );
		$url = get_avatar_url( 1 );
		remove_filter( 'pre_get_avatar_data', array( $this, 'pre_get_avatar_url_filter' ), 10 );

		$this->assertSame( $url, $this->fake_url );
	}
	public function pre_get_avatar_url_filter( $args ) {
		$args['url'] = $this->fake_url;
		return $args;
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_filter() {
		$this->fake_url = 'omg lol';

		add_filter( 'get_avatar_url', array( $this, 'get_avatar_url_filter' ), 10, 1 );
		$url = get_avatar_url( 1 );
		remove_filter( 'get_avatar_url', array( $this, 'get_avatar_url_filter' ), 10 );

		$this->assertSame( $url, $this->fake_url );
	}
	public function get_avatar_url_filter( $url ) {
		return $this->fake_url;
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_comment_types_filter() {
		$url = get_avatar_url( 1 );

		$post_id    = self::factory()->post->create( array( 'post_author' => 1 ) );
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'user_id'         => 1,
				'comment_type'    => 'pingback',
			)
		);
		$comment    = get_comment( $comment_id );

		$url2 = get_avatar_url( $comment );
		$this->assertFalse( $url2 );

		add_filter( 'get_avatar_comment_types', array( $this, 'get_avatar_comment_types_filter' ), 10, 1 );
		$url2 = get_avatar_url( $comment );
		remove_filter( 'get_avatar_comment_types', array( $this, 'get_avatar_comment_types_filter' ), 10 );

		$this->assertSame( $url, $url2 );
	}
	public function get_avatar_comment_types_filter( $comment_types ) {
		$comment_types[] = 'pingback';
		return $comment_types;
	}
}
