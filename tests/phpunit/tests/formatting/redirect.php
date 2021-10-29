<?php

/**
 * @group pluggable
 * @group formatting
 * @group redirect
 */
class Tests_Formatting_Redirect extends WP_UnitTestCase {
	function set_up() {
		parent::set_up();
		add_filter( 'home_url', array( $this, 'home_url' ) );
	}

	function home_url() {
		return 'http://example.com/';
	}

	/**
	 * @ticket 44317
	 *
	 * @dataProvider get_bad_status_codes
	 *
	 * @param string $location The path or URL to redirect to.
	 * @param int    $status   HTTP response status code to use.
	 */
	public function test_wp_redirect_bad_status_code( $location, $status ) {
		$this->expectException( 'WPDieException' );

		wp_redirect( $location, $status );
	}

	public function get_bad_status_codes() {
		return array(
			// Tests for bad arguments.
			array( '/wp-admin', 404 ),
			array( '/wp-admin', 410 ),
			array( '/wp-admin', 500 ),
			// Tests for condition.
			array( '/wp-admin', 299 ),
			array( '/wp-admin', 400 ),
		);
	}

	function test_wp_sanitize_redirect() {
		$this->assertSame( 'http://example.com/watchthelinefeedgo', wp_sanitize_redirect( 'http://example.com/watchthelinefeed%0Ago' ) );
		$this->assertSame( 'http://example.com/watchthelinefeedgo', wp_sanitize_redirect( 'http://example.com/watchthelinefeed%0ago' ) );
		$this->assertSame( 'http://example.com/watchthecarriagereturngo', wp_sanitize_redirect( 'http://example.com/watchthecarriagereturn%0Dgo' ) );
		$this->assertSame( 'http://example.com/watchthecarriagereturngo', wp_sanitize_redirect( 'http://example.com/watchthecarriagereturn%0dgo' ) );
		$this->assertSame( 'http://example.com/watchtheallowedcharacters-~+_.?#=&;,/:%!*stay', wp_sanitize_redirect( 'http://example.com/watchtheallowedcharacters-~+_.?#=&;,/:%!*stay' ) );
		$this->assertSame( 'http://example.com/watchtheutf8convert%F0%9D%8C%86', wp_sanitize_redirect( "http://example.com/watchtheutf8convert\xf0\x9d\x8c\x86" ) );
		// Nesting checks.
		$this->assertSame( 'http://example.com/watchthecarriagereturngo', wp_sanitize_redirect( 'http://example.com/watchthecarriagereturn%0%0ddgo' ) );
		$this->assertSame( 'http://example.com/watchthecarriagereturngo', wp_sanitize_redirect( 'http://example.com/watchthecarriagereturn%0%0DDgo' ) );
		$this->assertSame( 'http://example.com/whyisthisintheurl/?param[1]=foo', wp_sanitize_redirect( 'http://example.com/whyisthisintheurl/?param[1]=foo' ) );
		$this->assertSame( 'http://[2606:2800:220:6d:26bf:1447:aa7]/', wp_sanitize_redirect( 'http://[2606:2800:220:6d:26bf:1447:aa7]/' ) );
		$this->assertSame( 'http://example.com/search.php?search=(amistillhere)', wp_sanitize_redirect( 'http://example.com/search.php?search=(amistillhere)' ) );
		$this->assertSame( 'http://example.com/@username', wp_sanitize_redirect( 'http://example.com/@username' ) );
	}

	/**
	 * @ticket 36998
	 */
	function test_wp_sanitize_redirect_should_encode_spaces() {
		$this->assertSame( 'http://example.com/test%20spaces', wp_sanitize_redirect( 'http://example.com/test%20spaces' ) );
		$this->assertSame( 'http://example.com/test%20spaces%20in%20url', wp_sanitize_redirect( 'http://example.com/test spaces in url' ) );
	}

	/**
	 * @dataProvider valid_url_provider
	 */
	function test_wp_validate_redirect_valid_url( $url, $expected ) {
		$this->assertSame( $expected, wp_validate_redirect( $url ) );
	}

	/**
	 * @dataProvider invalid_url_provider
	 */
	function test_wp_validate_redirect_invalid_url( $url ) {
		$this->assertEquals( false, wp_validate_redirect( $url, false ) );
	}

	function valid_url_provider() {
		return array(
			array( 'http://example.com', 'http://example.com' ),
			array( 'http://example.com/', 'http://example.com/' ),
			array( 'https://example.com/', 'https://example.com/' ),
			array( '//example.com', 'http://example.com' ),
			array( '//example.com/', 'http://example.com/' ),
			array( 'http://example.com/?foo=http://example.com/', 'http://example.com/?foo=http://example.com/' ),
			array( 'http://user@example.com/', 'http://user@example.com/' ),
			array( 'http://user:@example.com/', 'http://user:@example.com/' ),
			array( 'http://user:pass@example.com/', 'http://user:pass@example.com/' ),
			array( " \t\n\r\0\x08\x0Bhttp://example.com", 'http://example.com' ),
			array( " \t\n\r\0\x08\x0B//example.com", 'http://example.com' ),
		);
	}

	function invalid_url_provider() {
		return array(
			// parse_url() fails.
			array( '' ),
			array( 'http://:' ),

			// Non-safelisted domain.
			array( 'http://non-safelisted.example/' ),

			// Non-safelisted domain (leading whitespace).
			array( " \t\n\r\0\x08\x0Bhttp://non-safelisted.example.com" ),
			array( " \t\n\r\0\x08\x0B//non-safelisted.example.com" ),

			// Unsupported schemes.
			array( 'data:text/plain;charset=utf-8,Hello%20World!' ),
			array( 'file:///etc/passwd' ),
			array( 'ftp://example.com/' ),

			// Malformed input.
			array( 'http:example.com' ),
			array( 'http:80' ),
			array( 'http://example.com:1234:5678/' ),
			array( 'http://user:pa:ss@example.com/' ),

			array( 'http://user@@example.com' ),
			array( 'http://user@:example.com' ),
			array( 'http://user?@example.com' ),
			array( 'http://user@?example.com' ),
			array( 'http://user#@example.com' ),
			array( 'http://user@#example.com' ),

			array( 'http://user@@example.com/' ),
			array( 'http://user@:example.com/' ),
			array( 'http://user?@example.com/' ),
			array( 'http://user@?example.com/' ),
			array( 'http://user#@example.com/' ),
			array( 'http://user@#example.com/' ),

			array( 'http://user:pass@@example.com' ),
			array( 'http://user:pass@:example.com' ),
			array( 'http://user:pass?@example.com' ),
			array( 'http://user:pass@?example.com' ),
			array( 'http://user:pass#@example.com' ),
			array( 'http://user:pass@#example.com' ),

			array( 'http://user:pass@@example.com/' ),
			array( 'http://user:pass@:example.com/' ),
			array( 'http://user:pass?@example.com/' ),
			array( 'http://user:pass@?example.com/' ),
			array( 'http://user:pass#@example.com/' ),
			array( 'http://user:pass@#example.com/' ),

			array( 'http://user.pass@@example.com' ),
			array( 'http://user.pass@:example.com' ),
			array( 'http://user.pass?@example.com' ),
			array( 'http://user.pass@?example.com' ),
			array( 'http://user.pass#@example.com' ),
			array( 'http://user.pass@#example.com' ),

			array( 'http://user.pass@@example.com/' ),
			array( 'http://user.pass@:example.com/' ),
			array( 'http://user.pass?@example.com/' ),
			array( 'http://user.pass@?example.com/' ),
			array( 'http://user.pass#@example.com/' ),
			array( 'http://user.pass@#example.com/' ),
		);
	}

	/**
	 * @ticket 47980
	 * @dataProvider relative_url_provider
	 */
	function test_wp_validate_redirect_relative_url( $current_uri, $url, $expected ) {
		// Backup the global.
		$unset = false;
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			$unset = true;
		} else {
			$backup_request_uri = $_SERVER['REQUEST_URI'];
		}

		// Set the global to current URI.
		$_SERVER['REQUEST_URI'] = $current_uri;

		$this->assertSame( $expected, wp_validate_redirect( $url, false ) );

		// Delete or reset the global as required.
		if ( $unset ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $backup_request_uri;
		}
	}

	/**
	 * Data provider for test_wp_validate_redirect_relative_url.
	 *
	 * @return array[] {
	 *      string Current URI (i.e. path and query string only).
	 *      string Redirect requested.
	 *      string Expected destination.
	 * }
	 */
	function relative_url_provider() {
		return array(
			array(
				'/',
				'wp-login.php?loggedout=true',
				'/wp-login.php?loggedout=true',
			),
			array(
				'/src/',
				'wp-login.php?loggedout=true',
				'/src/wp-login.php?loggedout=true',
			),
			array(
				'/wp-admin/settings.php?page=my-plugin',
				'./settings.php?page=my-plugin',
				'/wp-admin/./settings.php?page=my-plugin',
			),
			array(
				'/wp-admin/settings.php?page=my-plugin',
				'/wp-login.php',
				'/wp-login.php',
			),
			array(
				'/wp-admin/settings.php?page=my-plugin',
				'../wp-admin/admin.php?page=my-plugin',
				'/wp-admin/../wp-admin/admin.php?page=my-plugin',
			),
			array(
				'/2019/10/13/my-post',
				'../../',
				'/2019/10/13/../../',
			),
			array(
				'/2019/10/13/my-post',
				'/',
				'/',
			),
		);
	}
}
