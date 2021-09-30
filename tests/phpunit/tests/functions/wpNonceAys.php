<?php

/**
 * Tests for the wp_nonce_ays() function.
 *
 * @since 5.9.0
 *
 * @group functions.php
 * @covers ::wp_nonce_ays
 */
class Tests_Functions_wpNonceAys extends WP_UnitTestCase {

	/**
	 * @ticket 53882
	 */
	public function test_wp_nonce_ays() {
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		$this->expectExceptionCode( 403 );

		wp_nonce_ays( 'random_string' );
	}

	/**
	 * @ticket 53882
	 */
	public function test_wp_nonce_ays_log_out() {
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessageMatches( '#You are attempting to log out of Test Blog</p><p>Do you really want to <a href="http://example\.org/wp-login\.php\?action=logout&amp;_wpnonce=.{10}">log out</a>\?#m' );
		$this->expectExceptionCode( 403 );

		wp_nonce_ays( 'log-out' );
	}
}
