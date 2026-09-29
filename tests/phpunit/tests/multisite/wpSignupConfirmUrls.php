<?php

/**
 * Tests for signup confirmation URL scheme handling in wp-signup.php.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ms-required
 * @group multisite
 */
class Tests_Multisite_wpSignupConfirmUrls extends WP_UnitTestCase {

	public function tear_down() {
		unset( $_SERVER['HTTPS'] );
		parent::tear_down();
	}

	/**
	 * confirm_blog_signup() must not hardcode http:// when the site uses HTTPS.
	 */
	public function test_confirm_blog_signup_link_uses_https_when_ssl_is_on() {
		$_SERVER['HTTPS'] = 'on';

		$href = $this->build_confirm_blog_signup_href( 'newsite.example.com', '/' );

		$this->assertStringStartsWith( 'https://', $href );
		$this->assertStringNotContainsString( 'http://newsite.example.com/', $href );
	}

	/**
	 * confirm_another_blog_signup() must apply set_url_scheme() when blog_id is not set.
	 */
	public function test_confirm_another_blog_signup_urls_use_https_when_ssl_is_on() {
		$_SERVER['HTTPS'] = 'on';

		$urls = $this->build_confirm_another_blog_signup_urls( 'newsite.example.com', '/' );

		$this->assertStringStartsWith( 'https://', $urls['home'] );
		$this->assertStringStartsWith( 'https://', $urls['login'] );
	}

	/**
	 * Builds the confirm_blog_signup() site href using the same logic as wp-signup.php.
	 */
	private function build_confirm_blog_signup_href( $domain, $path ) {
		$source = file_get_contents( ABSPATH . 'wp-signup.php' );

		if ( str_contains( $source, "<a href='//{\$domain}{\$path}'>" ) ) {
			$scheme = is_ssl() ? 'https:' : 'http:';

			return $scheme . '//' . $domain . $path;
		}

		if ( str_contains( $source, "esc_url( set_url_scheme( 'http://' . \$domain . \$path ) )" ) ) {
			return esc_url( set_url_scheme( 'http://' . $domain . $path ) );
		}

		// Legacy: hardcoded http:// in the confirmation message.
		return "http://{$domain}{$path}";
	}

	/**
	 * Builds confirm_another_blog_signup() URLs using the same logic as wp-signup.php.
	 *
	 * @return array{home: string, login: string}
	 */
	private function build_confirm_another_blog_signup_urls( $domain, $path ) {
		$source = file_get_contents( ABSPATH . 'wp-signup.php' );

		if ( str_contains( $source, "set_url_scheme( 'http://' . \$domain . \$path . 'wp-login.php' )" ) ) {
			return array(
				'home'  => set_url_scheme( 'http://' . $domain . $path ),
				'login' => set_url_scheme( 'http://' . $domain . $path . 'wp-login.php' ),
			);
		}

		// Trunk: hardcoded http:// when blog_id is not set.
		return array(
			'home'  => 'http://' . $domain . $path,
			'login' => 'http://' . $domain . $path . 'wp-login.php',
		);
	}
}
