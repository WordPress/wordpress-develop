<?php

/**
 * @group functions.php
 * @group query
 * @covers ::wp_nonce_ays
 */
class Tests_Functions_wp_nonce_ays extends WP_UnitTestCase {

	public function test_wp_nonce_ays() {
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		$this->expectExceptionCode( 403 );

		wp_nonce_ays( 'random_string' );

	}

	/**
	 *
	 */
	public function test_wp_nonce_ays_log_out() {
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessageRegExp( '/You are attempting to log out of Test Blog<\/p><p>Do you really want to <a href="http:\/\/example\.org\/wp-login\.php\?action=logout&amp;_wpnonce=.{10}">log out<\/a>\?/m' );
		$this->expectExceptionCode( 403 );

		wp_nonce_ays( 'log-out' );
	}
}
