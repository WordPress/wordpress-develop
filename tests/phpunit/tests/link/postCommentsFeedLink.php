<?php

/**
 * @group link
 *
 * @covers ::post_comments_feed_link
 */
class Tests_Link_PostCommentsFeedLink extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Set up before class.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Sample Post for Feed',
			)
		);
	}

	/**
	 * Tests post_comments_feed_link() with default link text on plain permalinks.
	 *
	 * @ticket 65819
	 */
	public function test_post_comments_feed_link_default_text() {
		$this->set_permalink_structure( '' );

		$expected_url = get_post_comments_feed_link( self::$post_id );
		$expected     = '<a href="' . esc_url( $expected_url ) . '">Comments Feed</a>';

		ob_start();
		post_comments_feed_link( '', self::$post_id );
		$output = ob_get_clean();

		$this->assertSame( $expected, $output );
	}

	/**
	 * Tests post_comments_feed_link() with custom link text.
	 *
	 * @ticket 65819
	 */
	public function test_post_comments_feed_link_custom_text() {
		$this->set_permalink_structure( '' );

		$expected_url = get_post_comments_feed_link( self::$post_id );
		$expected     = '<a href="' . esc_url( $expected_url ) . '">Subscribe via RSS</a>';

		ob_start();
		post_comments_feed_link( 'Subscribe via RSS', self::$post_id );
		$output = ob_get_clean();

		$this->assertSame( $expected, $output );
	}

	/**
	 * Tests post_comments_feed_link() with pretty permalink structure.
	 *
	 * @ticket 65819
	 */
	public function test_post_comments_feed_link_pretty_permastruct() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$expected_url = get_post_comments_feed_link( self::$post_id );
		$expected     = '<a href="' . esc_url( $expected_url ) . '">Comments Feed</a>';

		ob_start();
		post_comments_feed_link( '', self::$post_id );
		$output = ob_get_clean();

		$this->assertSame( $expected, $output );
	}

	/**
	 * Tests post_comments_feed_link() defaulting to global $post when post_id is 0.
	 *
	 * @ticket 65819
	 */
	public function test_post_comments_feed_link_defaults_to_global_post() {
		global $post;

		$this->set_permalink_structure( '' );

		$original_post = $post;
		$post          = get_post( self::$post_id );

		$expected_url = get_post_comments_feed_link( self::$post_id );
		$expected     = '<a href="' . esc_url( $expected_url ) . '">Comments Feed</a>';

		ob_start();
		post_comments_feed_link();
		$output = ob_get_clean();

		$post = $original_post;

		$this->assertSame( $expected, $output );
	}

	/**
	 * Tests post_comments_feed_link() with a custom feed type.
	 *
	 * @ticket 65819
	 */
	public function test_post_comments_feed_link_custom_feed_type() {
		$this->set_permalink_structure( '' );

		$expected_url = get_post_comments_feed_link( self::$post_id, 'atom' );
		$expected     = '<a href="' . esc_url( $expected_url ) . '">Atom Comments</a>';

		ob_start();
		post_comments_feed_link( 'Atom Comments', self::$post_id, 'atom' );
		$output = ob_get_clean();

		$this->assertSame( $expected, $output );
	}

	/**
	 * Tests the 'post_comments_feed_link_html' filter hook.
	 *
	 * @ticket 65819
	 */
	public function test_post_comments_feed_link_html_filter() {
		$this->set_permalink_structure( '' );

		add_filter(
			'post_comments_feed_link_html',
			static function ( $link, $post_id, $feed ) {
				return '<span class="post-feed" data-post="' . (int) $post_id . '" data-feed="' . esc_attr( $feed ) . '">' . $link . '</span>';
			},
			10,
			3
		);

		ob_start();
		post_comments_feed_link( 'Comments', self::$post_id, 'atom' );
		$output = ob_get_clean();

		$this->assertStringStartsWith( '<span class="post-feed" data-post="' . self::$post_id . '" data-feed="atom"><a', $output );
		$this->assertStringEndsWith( 'Comments</a></span>', $output );
	}
}
