<?php
/**
 * @group user
 *
 * @covers ::wp_list_users
 */
class Tests_User_wpListUsers extends WP_UnitTestCase {
	private static $user_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'zack',
				'display_name' => 'zack',
				'role'         => 'subscriber',
				'first_name'   => 'zack',
				'last_name'    => 'moon',
				'user_email'   => 'm.zack@example.com',
				'user_url'     => 'http://moonzack.fake',
			)
		);

		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'jane',
				'display_name' => 'jane',
				'role'         => 'contributor',
				'first_name'   => 'jane',
				'last_name'    => 'reno',
				'user_email'   => 'r.jane@example.com',
				'user_url'     => 'http://janereno.fake',
			)
		);

		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'michelle',
				'display_name' => 'michelle',
				'role'         => 'subscriber',
				'first_name'   => 'michelle',
				'last_name'    => 'jones',
				'user_email'   => 'j.michelle@example.com',
				'user_url'     => 'http://lemichellejones.fake',
			)
		);

		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'paul',
				'display_name' => 'paul',
				'role'         => 'subscriber',
				'first_name'   => 'paul',
				'last_name'    => 'norris',
				'user_email'   => 'n.paul@example.com',
				'user_url'     => 'http://awildpaulappeared.fake',
			)
		);

		foreach ( self::$user_ids as $user ) {
			$factory->post->create(
				array(
					'post_type'   => 'post',
					'post_author' => $user,
				)
			);
		}
	}

	/**
	 * Test that wp_list_users() creates the expected list of users.
	 *
	 * @dataProvider data_should_create_a_user_list
	 *
	 * @ticket 15145
	 *
	 * @param array|string $args     The arguments to create a list of users.
	 * @param string       $expected The expected result.
	 */
	public function test_should_create_a_user_list( $args, $expected ) {
		$actual = wp_list_users( $args );

		$expected = str_replace(
			array( 'AUTHOR_ID_zack', 'AUTHOR_ID_jane', 'AUTHOR_ID_michelle', 'AUTHOR_ID_paul' ),
			array( self::$user_ids[0], self::$user_ids[1], self::$user_ids[2], self::$user_ids[3] ),
			$expected
		);

		if ( null === $actual ) {
			$this->expectOutputString( $expected );
		} else {
			$this->assertSame( $expected, $actual );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_create_a_user_list() {
		return array(
			'defaults when no args are supplied' => array(
				'args'     => '',
				'expected' => '<li>jane</li><li>michelle</li><li>paul</li><li>zack</li>',
			),
			'the admin account included'         => array(
				'args'     => array(
					'exclude_admin' => false,
				),
				'expected' => '<li>admin</li><li>jane</li><li>michelle</li><li>paul</li><li>zack</li>',
			),
			'the full name of each user'         => array(
				'args'     => array(
					'show_fullname' => true,
				),
				'expected' => '<li>jane reno</li><li>michelle jones</li><li>paul norris</li><li>zack moon</li>',
			),
			'the feed of each user'              => array(
				'args'     => array(
					'feed' => 'User feed',
				),
				'expected' => '<li>jane (<a href="http://example.org/?feed=rss2&amp;author=AUTHOR_ID_jane">User feed</a>)</li>' .
						'<li>michelle (<a href="http://example.org/?feed=rss2&amp;author=AUTHOR_ID_michelle">User feed</a>)</li>' .
						'<li>paul (<a href="http://example.org/?feed=rss2&amp;author=AUTHOR_ID_paul">User feed</a>)</li>' .
						'<li>zack (<a href="http://example.org/?feed=rss2&amp;author=AUTHOR_ID_zack">User feed</a>)</li>',
			),
			'the feed of each user and an image' => array(
				'args'     => array(
					'feed'       => 'User feed with image',
					'feed_image' => 'http://example.org/image.jpg',
				),
				'expected' => '<li>jane <a href="http://example.org/?feed=rss2&amp;author=AUTHOR_ID_jane"><img src="http://example.org/image.jpg" style="border: none;" alt="User feed with image" /></a></li>' .
						'<li>michelle <a href="http://example.org/?feed=rss2&amp;author=AUTHOR_ID_michelle"><img src="http://example.org/image.jpg" style="border: none;" alt="User feed with image" /></a></li>' .
						'<li>paul <a href="http://example.org/?feed=rss2&amp;author=AUTHOR_ID_paul"><img src="http://example.org/image.jpg" style="border: none;" alt="User feed with image" /></a></li>' .
						'<li>zack <a href="http://example.org/?feed=rss2&amp;author=AUTHOR_ID_zack"><img src="http://example.org/image.jpg" style="border: none;" alt="User feed with image" /></a></li>',
			),
			'a feed of the specified type'       => array(
				'args'     => array(
					'feed'      => 'User feed as atom',
					'feed_type' => 'atom',
				),
				'expected' => '<li>jane (<a href="http://example.org/?feed=atom&amp;author=AUTHOR_ID_jane">User feed as atom</a>)</li>' .
						'<li>michelle (<a href="http://example.org/?feed=atom&amp;author=AUTHOR_ID_michelle">User feed as atom</a>)</li>' .
						'<li>paul (<a href="http://example.org/?feed=atom&amp;author=AUTHOR_ID_paul">User feed as atom</a>)</li>' .
						'<li>zack (<a href="http://example.org/?feed=atom&amp;author=AUTHOR_ID_zack">User feed as atom</a>)</li>',
			),
			'no output via echo'                 => array(
				'args'     => array(
					'echo' => false,
				),
				'expected' => '<li>jane</li><li>michelle</li><li>paul</li><li>zack</li>',
			),
			'commas separating each user'        => array(
				'args'     => array(
					'style' => '',
				),
				'expected' => 'jane, michelle, paul, zack',
			),
			'plain text format'                  => array(
				'args'     => array(
					'html' => false,
				),
				'expected' => 'jane, michelle, paul, zack',
			),
		);
	}

	/**
	 * Tests that wp_list_users() does not create a user list.
	 *
	 * @dataProvider data_should_not_create_a_user_list
	 *
	 * @ticket 15145
	 *
	 * @param array|string $args The arguments to create a list of users.
	 */
	public function test_should_not_create_a_user_list( $args ) {
		$actual = wp_list_users( $args );

		if ( null === $actual ) {
			$this->expectOutputString( '', 'wp_list_users() did not output an empty string.' );
		} else {
			$this->assertSame( $actual, 'wp_list_users() did not return an empty string.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_not_create_a_user_list() {
		return array(
			'an empty user query result' => array(
				'args'     => array(
					'include' => array( 9999 ),
				),
				'expected' => '',
			),
		);
	}
}
