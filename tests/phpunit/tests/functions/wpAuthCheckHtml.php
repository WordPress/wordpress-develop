<?php

/**
 * Tests for the wp_auth_check_html function.
 *
 * @group functions.php
 *
 * @covers ::wp_auth_check_html
 */
class Tests_functions_wpAuthCheckHtml extends WP_UnitTestCase {

	/**
	 * @ticket 59792
	 */
	public function test_wp_auth_check_html() {
		$html = get_echo( 'wp_auth_check_html' );

		$this->assertStringContainsString( '<div id="wp-auth-check-wrap" class="hidden">', $html );
		$this->assertStringContainsString( '<a href="http://example.org/wp-login.php" target="_blank">', $html );
		$this->assertStringContainsString( '<div id="wp-auth-check-form" class="loading" data-src="http://example.org/wp-login.php?interim-login=1&#038;wp_lang=en_US"></div>', $html );
	}

	/**
	 * @ticket 59792
	 */
	public function test_wp_auth_check_html_wrong_domain() {
		$_SERVER['HTTP_HOST'] = 'ddd.com';

		$html = get_echo( 'wp_auth_check_html' );

		$this->assertStringContainsString( '<div id="wp-auth-check-wrap" class="hidden fallback">', $html );
		$this->assertStringContainsString( '<a href="http://example.org/wp-login.php" target="_blank">', $html );
		$this->assertStringNotContainsString( '<div id="wp-auth-check-form" class="loading" data-src=', $html );
	}

	/**
	 * @ticket 59792
	 */
	public function test_wp_auth_check_html_filtered() {
		add_filter( 'wp_auth_check_same_domain', '__return_false' );

		$html = get_echo( 'wp_auth_check_html' );

		$this->assertStringContainsString( '<div id="wp-auth-check-wrap" class="hidden fallback">', $html );
		$this->assertStringContainsString( '<a href="http://example.org/wp-login.php" target="_blank">', $html );
		$this->assertStringNotContainsString( '<div id="wp-auth-check-form" class="loading" data-src=', $html );
	}
}
