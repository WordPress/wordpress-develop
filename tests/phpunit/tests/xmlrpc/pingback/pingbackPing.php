<?php

/**
 * @group xmlrpc
 * @group pingback
 */
class Tests_XMLRPC_pingback_pingbackPing extends WP_XMLRPC_UnitTestCase {
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_status'  => 'publish',
				'ping_status'  => 'open',
				'post_content' => 'Test content.',
			)
		);
	}

	/**
	 * @ticket 36765
	 */
	public function test_post_resolved_via_query_string() {
		$url = get_option( 'home' ) . '/?p=' . self::$post_id;

		$this->assertSame( self::$post_id, url_to_postid( $url ) );
	}

	/**
	 * @ticket 36765
	 */
	public function test_post_resolved_via_permalink() {
		$this->set_permalink_structure( '/%postname%/' );

		$url = get_permalink( self::$post_id );

		$this->assertSame( self::$post_id, url_to_postid( $url ) );

		$this->set_permalink_structure( '' );
	}

	/**
	 * @ticket 36765
	 */
	public function test_unknown_url_returns_zero() {
		$url = get_option( 'home' ) . '/this-does-not-exist/';

		$this->assertSame( 0, url_to_postid( $url ) );
	}
}
