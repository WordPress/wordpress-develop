<?php

/**
 * Tests for link-template.php and related URL functions.
 *
 * @group url
 */
class Tests_URL extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$GLOBALS['pagenow'] = '';
	}

	/**
	 * @dataProvider data_is_ssl
	 *
	 * @covers ::is_ssl
	 */
	public function test_is_ssl( $value, $expected ) {
		$_SERVER['HTTPS'] = $value;

		$is_ssl = is_ssl();
		$this->assertSame( $expected, $is_ssl );
	}

	public function data_is_ssl() {
		return array(
			array(
				'on',
				true,
			),
			array(
				'ON',
				true,
			),
			array(
				'1',
				true,
			),
			array(
				'off',
				false,
			),
			array(
				'OFF',
				false,
			),
		);
	}

	/**
	 * @covers ::is_ssl
	 */
	public function test_is_ssl_by_port() {
		unset( $_SERVER['HTTPS'] );
		$_SERVER['SERVER_PORT'] = '443';

		$is_ssl = is_ssl();
		$this->assertTrue( $is_ssl );
	}

	/**
	 * @covers ::is_ssl
	 */
	public function test_is_ssl_with_no_value() {
		unset( $_SERVER['HTTPS'] );

		$is_ssl = is_ssl();
		$this->assertFalse( $is_ssl );
	}

	/**
	 * @dataProvider data_admin_urls
	 *
	 * @param string $url      Test URL.
	 * @param string $expected Expected result.
	 *
	 * @covers ::admin_url
	 */
	public function test_admin_url( $url, $expected ) {
		$siteurl_http   = get_option( 'siteurl' );
		$admin_url_http = admin_url( $url );

		$_SERVER['HTTPS'] = 'on';

		$siteurl_https   = set_url_scheme( $siteurl_http, 'https' );
		$admin_url_https = admin_url( $url );

		$this->assertSame( $siteurl_http . $expected, $admin_url_http );
		$this->assertSame( $siteurl_https . $expected, $admin_url_https );
	}

	public function data_admin_urls() {
		return array(
			array(
				null,
				'/wp-admin/',
			),
			array(
				0,
				'/wp-admin/',
			),
			array(
				-1,
				'/wp-admin/',
			),
			array(
				'///',
				'/wp-admin/',
			),
			array(
				'',
				'/wp-admin/',
			),
			array(
				'foo',
				'/wp-admin/foo',
			),
			array(
				'/foo',
				'/wp-admin/foo',
			),
			array(
				'/foo/',
				'/wp-admin/foo/',
			),
			array(
				'foo.php',
				'/wp-admin/foo.php',
			),
			array(
				'/foo.php',
				'/wp-admin/foo.php',
			),
			array(
				'/foo.php?bar=1',
				'/wp-admin/foo.php?bar=1',
			),
		);
	}

	/**
	 * @dataProvider data_home_urls
	 *
	 * @param string $url      Test URL.
	 * @param string $expected Expected result.
	 *
	 * @covers ::home_url
	 */
	public function test_home_url( $url, $expected ) {
		$homeurl_http  = get_option( 'home' );
		$home_url_http = home_url( $url );

		$_SERVER['HTTPS'] = 'on';

		$homeurl_https  = set_url_scheme( $homeurl_http, 'https' );
		$home_url_https = home_url( $url );

		$this->assertSame( $homeurl_http . $expected, $home_url_http );
		$this->assertSame( $homeurl_https . $expected, $home_url_https );
	}

	public function data_home_urls() {
		return array(
			array(
				null,
				'',
			),
			array(
				0,
				'',
			),
			array(
				-1,
				'',
			),
			array(
				'///',
				'/',
			),
			array(
				'',
				'',
			),
			array(
				'foo',
				'/foo',
			),
			array(
				'/foo',
				'/foo',
			),
			array(
				'/foo/',
				'/foo/',
			),
			array(
				'foo.php',
				'/foo.php',
			),
			array(
				'/foo.php',
				'/foo.php',
			),
			array(
				'/foo.php?bar=1',
				'/foo.php?bar=1',
			),
		);
	}

	/**
	 * @covers ::home_url
	 */
	public function test_home_url_from_admin() {
		// Pretend to be in the site admin.
		set_current_screen( 'dashboard' );
		$home       = get_option( 'home' );
		$home_https = str_replace( 'http://', 'https://', $home );

		// is_ssl() should determine the scheme in the admin.
		$_SERVER['HTTPS'] = 'on';
		$this->assertSame( $home_https, home_url() );

		$_SERVER['HTTPS'] = 'off';
		$this->assertSame( $home, home_url() );

		// is_ssl() should determine the scheme on front end too.
		set_current_screen( 'front' );
		$this->assertSame( $home, home_url() );

		$_SERVER['HTTPS'] = 'on';
		$this->assertSame( $home_https, home_url() );

		// Test with https in home.
		update_option( 'home', set_url_scheme( $home, 'https' ) );

		// Pretend to be in the site admin.
		set_current_screen( 'dashboard' );
		$home = get_option( 'home' );

		// home_url() should return whatever scheme is set in the home option when in the admin.
		$_SERVER['HTTPS'] = 'on';
		$this->assertSame( $home, home_url() );

		$_SERVER['HTTPS'] = 'off';
		$this->assertSame( $home, home_url() );

		// If not in the admin, is_ssl() should determine the scheme unless https hard-coded in home.
		set_current_screen( 'front' );
		$this->assertSame( $home, home_url() );
		$_SERVER['HTTPS'] = 'on';
		$this->assertSame( $home, home_url() );
		$_SERVER['HTTPS'] = 'off';
		$this->assertSame( $home, home_url() );

		update_option( 'home', set_url_scheme( $home, 'http' ) );
	}

	/**
	 * @covers ::network_home_url
	 */
	public function test_network_home_url_from_admin() {
		// Pretend to be in the site admin.
		set_current_screen( 'dashboard' );
		$home       = network_home_url();
		$home_https = str_replace( 'http://', 'https://', $home );

		// is_ssl() should determine the scheme in the admin.
		$this->assertStringStartsWith( 'http://', $home );
		$_SERVER['HTTPS'] = 'on';
		$this->assertSame( $home_https, network_home_url() );

		$_SERVER['HTTPS'] = 'off';
		$this->assertSame( $home, network_home_url() );

		// is_ssl() should determine the scheme on front end too.
		set_current_screen( 'front' );
		$this->assertSame( $home, network_home_url() );
		$_SERVER['HTTPS'] = 'on';
		$this->assertSame( $home_https, network_home_url() );
	}

	/**
	 * @covers ::set_url_scheme
	 */
	public function test_set_url_scheme() {
		$links = array(
			'http://wordpress.org/',
			'https://wordpress.org/',
			'http://wordpress.org/news/',
			'http://wordpress.org',
		);

		$https_links = array(
			'https://wordpress.org/',
			'https://wordpress.org/',
			'https://wordpress.org/news/',
			'https://wordpress.org',
		);

		$http_links = array(
			'http://wordpress.org/',
			'http://wordpress.org/',
			'http://wordpress.org/news/',
			'http://wordpress.org',
		);

		$relative_links = array(
			'/',
			'/',
			'/news/',
			'',
		);

		$forced_admin = force_ssl_admin();
		$i            = 0;
		foreach ( $links as $link ) {
			$this->assertSame( $https_links[ $i ], set_url_scheme( $link, 'https' ) );
			$this->assertSame( $http_links[ $i ], set_url_scheme( $link, 'http' ) );
			$this->assertSame( $relative_links[ $i ], set_url_scheme( $link, 'relative' ) );

			$_SERVER['HTTPS'] = 'on';
			$this->assertSame( $https_links[ $i ], set_url_scheme( $link ) );

			$_SERVER['HTTPS'] = 'off';
			$this->assertSame( $http_links[ $i ], set_url_scheme( $link ) );

			force_ssl_admin( true );
			$this->assertSame( $https_links[ $i ], set_url_scheme( $link, 'admin' ) );
			$this->assertSame( $https_links[ $i ], set_url_scheme( $link, 'login_post' ) );
			$this->assertSame( $https_links[ $i ], set_url_scheme( $link, 'login' ) );
			$this->assertSame( $https_links[ $i ], set_url_scheme( $link, 'rpc' ) );

			force_ssl_admin( false );
			$this->assertSame( $http_links[ $i ], set_url_scheme( $link, 'admin' ) );
			$this->assertSame( $http_links[ $i ], set_url_scheme( $link, 'login_post' ) );
			$this->assertSame( $http_links[ $i ], set_url_scheme( $link, 'login' ) );
			$this->assertSame( $http_links[ $i ], set_url_scheme( $link, 'rpc' ) );

			$i++;
		}

		force_ssl_admin( $forced_admin );
	}

	/**
	 * @covers ::get_adjacent_post
	 */
	public function test_get_adjacent_post() {
		$now      = time();
		$post_id  = self::factory()->post->create( array( 'post_date' => gmdate( 'Y-m-d H:i:s', $now - 1 ) ) );
		$post_id2 = self::factory()->post->create( array( 'post_date' => gmdate( 'Y-m-d H:i:s', $now ) ) );

		if ( ! isset( $GLOBALS['post'] ) ) {
			$GLOBALS['post'] = null;
		}
		$orig_post       = $GLOBALS['post'];
		$GLOBALS['post'] = get_post( $post_id2 );

		$p = get_adjacent_post();
		$this->assertInstanceOf( 'WP_Post', $p );
		$this->assertSame( $post_id, $p->ID );

		// The same again to make sure a cached query returns the same result.
		$p = get_adjacent_post();
		$this->assertInstanceOf( 'WP_Post', $p );
		$this->assertSame( $post_id, $p->ID );

		// Test next.
		$p = get_adjacent_post( false, '', false );
		$this->assertSame( '', $p );

		unset( $GLOBALS['post'] );
		$this->assertNull( get_adjacent_post() );

		$GLOBALS['post'] = $orig_post;
	}

	/**
	 * Test get_adjacent_post returns the next private post when the author is the currently logged in user.
	 *
	 * @ticket 30287
	 *
	 * @covers ::get_adjacent_post
	 */
	public function test_get_adjacent_post_should_return_private_posts_belonging_to_the_current_user() {
		$u       = self::factory()->user->create( array( 'role' => 'author' ) );
		$old_uid = get_current_user_id();
		wp_set_current_user( $u );

		$now = time();
		$p1  = self::factory()->post->create(
			array(
				'post_author' => $u,
				'post_status' => 'private',
				'post_date'   => gmdate( 'Y-m-d H:i:s', $now - 1 ),
			)
		);
		$p2  = self::factory()->post->create(
			array(
				'post_author' => $u,
				'post_date'   => gmdate( 'Y-m-d H:i:s', $now ),
			)
		);

		if ( ! isset( $GLOBALS['post'] ) ) {
			$GLOBALS['post'] = null;
		}
		$orig_post = $GLOBALS['post'];

		$GLOBALS['post'] = get_post( $p2 );

		$p = get_adjacent_post();
		$this->assertSame( $p1, $p->ID );

		$GLOBALS['post'] = $orig_post;
		wp_set_current_user( $old_uid );
	}

	/**
	 * @ticket 30287
	 *
	 * @covers ::get_adjacent_post
	 */
	public function test_get_adjacent_post_should_return_private_posts_belonging_to_other_users_if_the_current_user_can_read_private_posts() {
		$u1      = self::factory()->user->create( array( 'role' => 'author' ) );
		$u2      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$old_uid = get_current_user_id();
		wp_set_current_user( $u2 );

		$now = time();
		$p1  = self::factory()->post->create(
			array(
				'post_author' => $u1,
				'post_status' => 'private',
				'post_date'   => gmdate( 'Y-m-d H:i:s', $now - 1 ),
			)
		);
		$p2  = self::factory()->post->create(
			array(
				'post_author' => $u1,
				'post_date'   => gmdate( 'Y-m-d H:i:s', $now ),
			)
		);

		if ( ! isset( $GLOBALS['post'] ) ) {
			$GLOBALS['post'] = null;
		}
		$orig_post = $GLOBALS['post'];

		$GLOBALS['post'] = get_post( $p2 );

		$p = get_adjacent_post();
		$this->assertSame( $p1, $p->ID );

		$GLOBALS['post'] = $orig_post;
		wp_set_current_user( $old_uid );
	}

	/**
	 * @ticket 30287
	 *
	 * @covers ::get_adjacent_post
	 */
	public function test_get_adjacent_post_should_not_return_private_posts_belonging_to_other_users_if_the_current_user_cannot_read_private_posts() {
		$u1      = self::factory()->user->create( array( 'role' => 'author' ) );
		$u2      = self::factory()->user->create( array( 'role' => 'author' ) );
		$old_uid = get_current_user_id();
		wp_set_current_user( $u2 );

		$now = time();
		$p1  = self::factory()->post->create(
			array(
				'post_author' => $u1,
				'post_date'   => gmdate( 'Y-m-d H:i:s', $now - 2 ),
			)
		);
		$p2  = self::factory()->post->create(
			array(
				'post_author' => $u1,
				'post_status' => 'private',
				'post_date'   => gmdate( 'Y-m-d H:i:s', $now - 1 ),
			)
		);
		$p3  = self::factory()->post->create(
			array(
				'post_author' => $u1,
				'post_date'   => gmdate( 'Y-m-d H:i:s', $now ),
			)
		);

		if ( ! isset( $GLOBALS['post'] ) ) {
			$GLOBALS['post'] = null;
		}
		$orig_post = $GLOBALS['post'];

		$GLOBALS['post'] = get_post( $p3 );

		$p = get_adjacent_post();
		$this->assertSame( $p1, $p->ID );

		$GLOBALS['post'] = $orig_post;
		wp_set_current_user( $old_uid );
	}

	/**
	 * Test that *_url functions handle paths with ".."
	 *
	 * @ticket 19032
	 *
	 * @covers ::site_url
	 * @covers ::home_url
	 * @covers ::admin_url
	 * @covers ::network_admin_url
	 * @covers ::user_admin_url
	 * @covers ::includes_url
	 * @covers ::network_site_url
	 * @covers ::network_home_url
	 * @covers ::content_url
	 * @covers ::plugins_url
	 */
	public function test_url_functions_for_dots_in_paths() {
		$functions = array(
			'site_url',
			'home_url',
			'admin_url',
			'network_admin_url',
			'user_admin_url',
			'includes_url',
			'network_site_url',
			'network_home_url',
			'content_url',
			'plugins_url',
		);

		foreach ( $functions as $function ) {
			$this->assertSame(
				call_user_func( $function, '/' ) . '../',
				call_user_func( $function, '../' )
			);
			$this->assertSame(
				call_user_func( $function, '/' ) . 'something...here',
				call_user_func( $function, 'something...here' )
			);
		}

		// These functions accept a blog ID argument.
		foreach ( array( 'get_site_url', 'get_home_url', 'get_admin_url' ) as $function ) {
			$this->assertSame(
				call_user_func( $function, null, '/' ) . '../',
				call_user_func( $function, null, '../' )
			);
			$this->assertSame(
				call_user_func( $function, null, '/' ) . 'something...here',
				call_user_func( $function, null, 'something...here' )
			);
		}
	}
}
