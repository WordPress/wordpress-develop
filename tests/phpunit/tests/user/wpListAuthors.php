<?php
/**
 * @group author
 * @group user
 * @covers ::wp_list_authors
 */
class Tests_User_wpListAuthors extends WP_UnitTestCase {
	public static $user_ids = array();
	public static $fred_id;
	public static $posts     = array();
	public static $user_urls = array();

	/*
	 * Defaults:
	 * 'orderby'       => 'name',
	 * 'order'         => 'ASC',
	 * 'number'        => null,
	 * 'optioncount'   => false,
	 * 'exclude_admin' => true,
	 * 'show_fullname' => false,
	 * 'hide_empty'    => true,
	 * 'echo'          => true,
	 * 'feed'          => [empty string],
	 * 'feed_image'    => [empty string],
	 * 'feed_type'     => [empty string],
	 * 'style'         => 'list',
	 * 'html'          => true,
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wp_rewrite;

		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'zack',
				'display_name' => 'zack',
				'role'         => 'author',
				'first_name'   => 'zack',
				'last_name'    => 'moon',
			)
		);
		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'bob',
				'display_name' => 'bob',
				'role'         => 'author',
				'first_name'   => 'bob',
				'last_name'    => 'reno',
			)
		);
		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'paul',
				'display_name' => 'paul',
				'role'         => 'author',
				'first_name'   => 'paul',
				'last_name'    => 'norris',
			)
		);
		self::$fred_id    = $factory->user->create(
			array(
				'user_login' => 'fred',
				'role'       => 'author',
			)
		);

		/*
		 * Re-initialize WP_Rewrite, so that get_author_posts_url() uses
		 * the default permalink structure, not affected by other tests.
		 */
		$wp_rewrite->init();

		$count = 0;
		foreach ( self::$user_ids as $userid ) {
			$count = $count + 1;
			for ( $i = 0; $i < $count; $i++ ) {
				self::$posts[] = $factory->post->create(
					array(
						'post_type'   => 'post',
						'post_author' => $userid,
					)
				);
			}

			self::$user_urls[] = get_author_posts_url( $userid );
		}
	}

	public function test_wp_list_authors_default() {
		$expected['default'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>';

		$this->assertSame( $expected['default'], wp_list_authors( array( 'echo' => false ) ) );
	}

	public function test_wp_list_authors_orderby() {
		$expected['post_count'] =
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>' .
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>';

		$this->assertSame(
			$expected['post_count'],
			wp_list_authors(
				array(
					'echo'    => false,
					'orderby' => 'post_count',
				)
			)
		);
	}

	public function test_wp_list_authors_order() {
		$expected['id'] =
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>' .
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>';

		$this->assertSame(
			$expected['id'],
			wp_list_authors(
				array(
					'echo'    => false,
					'orderby' => 'id',
					'order'   => 'DESC',
				)
			)
		);
	}

	public function test_wp_list_authors_optioncount() {
		$expected['optioncount'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a> (2)</li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a> (3)</li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a> (1)</li>';

		$this->assertSame(
			$expected['optioncount'],
			wp_list_authors(
				array(
					'echo'        => false,
					'optioncount' => 1,
				)
			)
		);
	}

	public function test_wp_list_authors_exclude_admin() {
		self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_author' => 1,
			)
		);

		$expected['exclude_admin'] =
			'<li><a href="' . get_author_posts_url( 1 ) . '" title="Posts by admin">admin</a></li>' .
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>';

		$this->assertSame(
			$expected['exclude_admin'],
			wp_list_authors(
				array(
					'echo'          => false,
					'exclude_admin' => 0,
				)
			)
		);
	}

	public function test_wp_list_authors_show_fullname() {
		$expected['show_fullname'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob reno</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul norris</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack moon</a></li>';

		$this->assertSame(
			$expected['show_fullname'],
			wp_list_authors(
				array(
					'echo'          => false,
					'show_fullname' => 1,
				)
			)
		);
	}

	public function test_wp_list_authors_hide_empty() {
		$fred_id = self::$fred_id;

		$expected['hide_empty'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . get_author_posts_url( $fred_id ) . '" title="Posts by fred">fred</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>';

		$this->assertSame(
			$expected['hide_empty'],
			wp_list_authors(
				array(
					'echo'       => false,
					'hide_empty' => 0,
				)
			)
		);
	}

	public function test_wp_list_authors_echo() {
		$expected['echo'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>';

		$this->expectOutputString( $expected['echo'] );
		wp_list_authors( array( 'echo' => true ) );
	}

	public function test_wp_list_authors_feed() {
		$url0 = get_author_feed_link( self::$user_ids[0] );
		$url1 = get_author_feed_link( self::$user_ids[1] );
		$url2 = get_author_feed_link( self::$user_ids[2] );

		$expected['feed'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a> (<a href="' . $url1 . '">link to feed</a>)</li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a> (<a href="' . $url2 . '">link to feed</a>)</li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a> (<a href="' . $url0 . '">link to feed</a>)</li>';

		$this->assertSame(
			$expected['feed'],
			wp_list_authors(
				array(
					'echo' => false,
					'feed' => 'link to feed',
				)
			)
		);
	}

	public function test_wp_list_authors_feed_image() {
		$url0 = get_author_feed_link( self::$user_ids[0] );
		$url1 = get_author_feed_link( self::$user_ids[1] );
		$url2 = get_author_feed_link( self::$user_ids[2] );

		$expected['feed_image'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a> <a href="' . $url1 . '"><img src="http://' . WP_TESTS_DOMAIN . '/path/to/a/graphic.png" style="border: none;" /></a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a> <a href="' . $url2 . '"><img src="http://' . WP_TESTS_DOMAIN . '/path/to/a/graphic.png" style="border: none;" /></a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a> <a href="' . $url0 . '"><img src="http://' . WP_TESTS_DOMAIN . '/path/to/a/graphic.png" style="border: none;" /></a></li>';

		$this->assertSame(
			$expected['feed_image'],
			wp_list_authors(
				array(
					'echo'       => false,
					'feed_image' => WP_TESTS_DOMAIN . '/path/to/a/graphic.png',
				)
			)
		);
	}

	/**
	 * @ticket 26538
	 */
	public function test_wp_list_authors_feed_type() {
		$url0 = get_author_feed_link( self::$user_ids[0], 'atom' );
		$url1 = get_author_feed_link( self::$user_ids[1], 'atom' );
		$url2 = get_author_feed_link( self::$user_ids[2], 'atom' );

		$expected['feed_type'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a> (<a href="' . $url1 . '">link to feed</a>)</li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a> (<a href="' . $url2 . '">link to feed</a>)</li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a> (<a href="' . $url0 . '">link to feed</a>)</li>';

		$this->assertSame(
			$expected['feed_type'],
			wp_list_authors(
				array(
					'echo'      => false,
					'feed'      => 'link to feed',
					'feed_type' => 'atom',
				)
			)
		);
	}

	public function test_wp_list_authors_style() {
		$expected['style'] =
			'<a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a>, ' .
			'<a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a>, ' .
			'<a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a>';

		$this->assertSame(
			$expected['style'],
			wp_list_authors(
				array(
					'echo'  => false,
					'style' => 'none',
				)
			)
		);
	}

	public function test_wp_list_authors_html() {
		$expected['html'] = 'bob, paul, zack';

		$this->assertSame(
			$expected['html'],
			wp_list_authors(
				array(
					'echo' => false,
					'html' => 0,
				)
			)
		);
	}
}
