<?php

/**
 * Tests for the `wp_logout_url()`, `wp_lostpassword_url()` and `wp_registration_url()` functions.
 *
 * @group general
 * @group auth
 */
class Tests_General_WpLoginScreenUrls extends WP_UnitTestCase {

	/**
	 * The original forced admin SSL setting.
	 *
	 * @var bool
	 */
	private $original_force_ssl_admin;

	public function set_up() {
		parent::set_up();

		$this->original_force_ssl_admin = force_ssl_admin( false );
	}

	public function tear_down() {
		force_ssl_admin( $this->original_force_ssl_admin );

		parent::tear_down();
	}

	/**
	 * Tests the logout URL without a redirect.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_logout_url
	 */
	public function test_wp_logout_url() {
		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=logout&amp;_wpnonce=' . wp_create_nonce( 'log-out' ),
			wp_logout_url()
		);
	}

	/**
	 * Tests that the logout URL encodes the redirect URL.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_logout_url
	 */
	public function test_wp_logout_url_with_redirect() {
		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=logout&amp;redirect_to=https%3A%2F%2Fexample.org%2Fafter%2F%3Fa%3D1%26b%3D2&amp;_wpnonce=' . wp_create_nonce( 'log-out' ),
			wp_logout_url( 'https://example.org/after/?a=1&b=2' )
		);
	}

	/**
	 * Tests that the logout nonce is valid for the 'log-out' action.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_logout_url
	 */
	public function test_wp_logout_url_nonce_is_valid_for_log_out() {
		wp_set_current_user( self::factory()->user->create() );

		parse_str( html_entity_decode( wp_parse_url( wp_logout_url(), PHP_URL_QUERY ) ), $query );

		$this->assertSame( 1, wp_verify_nonce( $query['_wpnonce'], 'log-out' ) );
	}

	/**
	 * Tests the arguments and return value of the 'logout_url' filter.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_logout_url
	 */
	public function test_logout_url_filter() {
		$filter = new MockAction();
		add_filter( 'logout_url', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'logout_url',
			static function ( $logout_url ) {
				return $logout_url . '&amp;filtered=1';
			},
			20
		);

		$expected = 'http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=logout&amp;redirect_to=https%3A%2F%2Fexample.org%2Fafter%2F&amp;_wpnonce=' . wp_create_nonce( 'log-out' );

		$logout_url = wp_logout_url( 'https://example.org/after/' );

		$this->assertSame( array( $expected, 'https://example.org/after/' ), $filter->get_args()[0], 'The filter should receive the URL and the unencoded redirect.' );
		$this->assertSame( $expected . '&amp;filtered=1', $logout_url, 'The filtered URL should be returned.' );
	}

	/**
	 * Tests the lost password URL with and without a redirect.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_lostpassword_url
	 */
	public function test_wp_lostpassword_url() {
		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=lostpassword',
			wp_lostpassword_url(),
			'The URL without a redirect is not correct.'
		);
		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=lostpassword&redirect_to=https%3A%2F%2Fexample.org%2Fafter%2F%3Fa%3D1%26b%3D2',
			wp_lostpassword_url( 'https://example.org/after/?a=1&b=2' ),
			'The URL with a redirect is not correct.'
		);
	}

	/**
	 * Tests that the lost password URL uses the path of the current site on multisite.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 *
	 * @covers ::wp_lostpassword_url
	 */
	public function test_wp_lostpassword_url_uses_current_site_path() {
		$site_id = self::factory()->blog->create( array( 'path' => '/lost-site/' ) );

		switch_to_blog( $site_id );
		$url = wp_lostpassword_url();
		restore_current_blog();

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/lost-site/wp-login.php?action=lostpassword', $url );
	}

	/**
	 * Tests the arguments and return value of the 'lostpassword_url' filter.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_lostpassword_url
	 */
	public function test_lostpassword_url_filter() {
		$filter = new MockAction();
		add_filter( 'lostpassword_url', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'lostpassword_url',
			static function ( $url ) {
				return $url . '&filtered=1';
			},
			20
		);

		$expected = 'http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=lostpassword&redirect_to=https%3A%2F%2Fexample.org%2Fafter%2F';

		$url = wp_lostpassword_url( 'https://example.org/after/' );

		$this->assertSame( array( $expected, 'https://example.org/after/' ), $filter->get_args()[0], 'The filter should receive the URL and the unencoded redirect.' );
		$this->assertSame( $expected . '&filtered=1', $url, 'The filtered URL should be returned.' );
	}

	/**
	 * Tests the arguments and return value of the 'register_url' filter.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_registration_url
	 */
	public function test_register_url_filter() {
		$filter = new MockAction();
		add_filter( 'register_url', array( $filter, 'filter' ) );
		add_filter(
			'register_url',
			static function ( $url ) {
				return $url . '&filtered=1';
			},
			20
		);

		$url = wp_registration_url();

		$this->assertSame( array( 'http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=register' ), $filter->get_args()[0], 'The filter should receive the URL.' );
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=register&filtered=1', $url, 'The filtered URL should be returned.' );
	}

	/**
	 * Tests that the URLs use HTTPS when SSL is forced for the login screen.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_logout_url
	 * @covers ::wp_lostpassword_url
	 * @covers ::wp_registration_url
	 */
	public function test_urls_use_https_when_ssl_is_forced() {
		force_ssl_admin( true );

		$this->assertSame( 'https://' . WP_TESTS_DOMAIN . '/wp-login.php?action=logout&amp;_wpnonce=' . wp_create_nonce( 'log-out' ), wp_logout_url(), 'The logout URL should use HTTPS.' );
		$this->assertSame( 'https://' . WP_TESTS_DOMAIN . '/wp-login.php?action=lostpassword', wp_lostpassword_url(), 'The lost password URL should use HTTPS.' );
		$this->assertSame( 'https://' . WP_TESTS_DOMAIN . '/wp-login.php?action=register', wp_registration_url(), 'The registration URL should use HTTPS.' );
	}
}
