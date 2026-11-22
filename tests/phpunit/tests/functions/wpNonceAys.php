<?php

/**
 * Tests for the wp_nonce_ays() function.
 *
 * @since 5.9.0
 *
 * @group functions
 *
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
		$this->expectExceptionMessageMatches( '#You are attempting to log out of Test Blog</p><p>Do you really want to <a href="http://' . WP_TESTS_DOMAIN . '/wp-login\.php\?action=logout&amp;_wpnonce=.{10}">log out</a>\?#m' );
		$this->expectExceptionCode( 403 );

		wp_nonce_ays( 'log-out' );
	}

	/**
	 * Tests that the log out URL keeps a string `redirect_to` request value.
	 *
	 * @ticket 66774
	 */
	public function test_wp_nonce_ays_log_out_keeps_string_redirect_to() {
		$_REQUEST['redirect_to'] = 'https://example.org/';

		try {
			wp_nonce_ays( 'log-out' );
			$this->fail( 'Expected a WPDieException.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'redirect_to=https%3A%2F%2Fexample.org%2F', $e->getMessage() );
		} finally {
			unset( $_REQUEST['redirect_to'] );
		}
	}

	/**
	 * Tests that a non-string `redirect_to` request value, such as an array, is ignored
	 * instead of causing a fatal error.
	 *
	 * @ticket 66774
	 *
	 * @dataProvider data_non_string_redirect_to
	 *
	 * @param mixed $redirect_to Value for the `redirect_to` request parameter.
	 */
	public function test_wp_nonce_ays_log_out_ignores_non_string_redirect_to( $redirect_to ) {
		$_REQUEST['redirect_to'] = $redirect_to;

		try {
			wp_nonce_ays( 'log-out' );
			$this->fail( 'Expected a WPDieException.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 403, $e->getCode(), 'The exception code should be 403.' );
			$this->assertStringContainsString( 'You are attempting to log out of Test Blog', $e->getMessage(), 'The log out confirmation should be shown.' );
			$this->assertStringNotContainsString( 'redirect_to', $e->getMessage(), 'The log out URL should not include the invalid redirect.' );
		} finally {
			unset( $_REQUEST['redirect_to'] );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_non_string_redirect_to() {
		return array(
			'an array'             => array( array( 'https://example.org/' ) ),
			'an empty array'       => array( array() ),
			'a nested array'       => array( array( array( 'https://example.org/' ) ) ),
			'an associative array' => array( array( 'a' => 'b' ) ),
		);
	}
}
