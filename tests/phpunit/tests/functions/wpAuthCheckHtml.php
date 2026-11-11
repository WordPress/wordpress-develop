<?php

/**
 * Tests for the wp_auth_check_html function.
 *
 * @group functions
 *
 * @covers ::wp_auth_check_html
 */
class Tests_Functions_WpAuthCheckHtml extends WP_UnitTestCase {

	/**
	 * The original forced admin SSL setting.
	 *
	 * @var bool
	 */
	protected $original_force_ssl_admin;

	/**
	 * Original HTTP_HOST server variable.
	 *
	 * @var string|null
	 */
	protected $original_http_host;

	/**
	 * Sets up the test fixture.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_force_ssl_admin = force_ssl_admin( false );

		$this->original_http_host = $_SERVER['HTTP_HOST'] ?? null;

		$login_url = wp_login_url();
		$host      = wp_parse_url( $login_url, PHP_URL_HOST );
		$port      = wp_parse_url( $login_url, PHP_URL_PORT );

		if ( $port ) {
			$host .= ":$port";
		}

		$_SERVER['HTTP_HOST'] = $host;
	}

	/**
	 * Tears down the test fixture.
	 */
	public function tear_down() {
		force_ssl_admin( $this->original_force_ssl_admin );

		if ( null === $this->original_http_host ) {
			unset( $_SERVER['HTTP_HOST'] );
		} else {
			$_SERVER['HTTP_HOST'] = $this->original_http_host;
		}

		parent::tear_down();
	}

	/**
	 * Tests that wp_auth_check_html() outputs the expected dialog HTML for the same domain.
	 *
	 * @ticket 59792
	 */
	public function test_wp_auth_check_html() {
		$login_url = wp_login_url();
		$login_src = add_query_arg(
			array(
				'interim-login' => '1',
				'wp_lang'       => get_user_locale(),
			),
			$login_url
		);

		$html = get_echo( 'wp_auth_check_html' );

		$this->assertStringContainsString( '<div id="wp-auth-check-wrap" class="hidden">', $html );
		$this->assertStringContainsString( '<a href="' . esc_url( $login_url ) . '" target="_blank">', $html );
		$this->assertStringContainsString( '<div id="wp-auth-check-form" class="loading" data-src="' . esc_url( $login_src ) . '"></div>', $html );
	}

	/**
	 * Tests that wp_auth_check_html() outputs the fallback dialog when on a different domain.
	 *
	 * @ticket 59792
	 */
	public function test_wp_auth_check_html_wrong_domain() {
		$_SERVER['HTTP_HOST'] = 'ddd.com';

		$login_url = wp_login_url();
		$html      = get_echo( 'wp_auth_check_html' );

		$this->assertStringContainsString( '<div id="wp-auth-check-wrap" class="hidden fallback">', $html );
		$this->assertStringContainsString( '<a href="' . esc_url( $login_url ) . '" target="_blank">', $html );
		$this->assertStringNotContainsString( '<div id="wp-auth-check-form" class="loading" data-src=', $html );
	}

	/**
	 * Tests that wp_auth_check_html() respects the 'wp_auth_check_same_domain' filter.
	 *
	 * @ticket 59792
	 */
	public function test_wp_auth_check_html_filtered() {
		add_filter( 'wp_auth_check_same_domain', '__return_false' );

		$login_url = wp_login_url();
		$html      = get_echo( 'wp_auth_check_html' );

		$this->assertStringContainsString( '<div id="wp-auth-check-wrap" class="hidden fallback">', $html );
		$this->assertStringContainsString( '<a href="' . esc_url( $login_url ) . '" target="_blank">', $html );
		$this->assertStringNotContainsString( '<div id="wp-auth-check-form" class="loading" data-src=', $html );
	}
}
