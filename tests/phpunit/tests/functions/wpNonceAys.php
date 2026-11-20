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
	 * Tests that a request discarded for exceeding post_max_size is not reported as an expired link.
	 *
	 * @ticket 18474
	 */
	public function test_wp_nonce_ays_post_max_size_exceeded() {
		$post_max_size = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
		if ( $post_max_size <= 0 ) {
			$this->markTestSkipped( 'post_max_size is unlimited in this environment.' );
		}

		$original_server = $_SERVER;
		$original_post   = $_POST;
		$original_files  = $_FILES;

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_LENGTH'] = (string) ( $post_max_size + 1 );
		$_POST                     = array();
		$_FILES                    = array();

		try {
			wp_nonce_ays( 'plugin-upload' );
			$this->fail( 'Expected WPDieException was not thrown.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 413, $e->getCode(), 'The response code should be 413.' );
			$this->assertStringContainsString( size_format( $post_max_size ), $e->getMessage(), 'The message should include the size limit.' );
			$this->assertStringNotContainsString( 'The link you followed has expired.', $e->getMessage(), 'The message should not claim the link has expired.' );

			if ( is_multisite() ) {
				$this->assertStringNotContainsString( 'post_max_size', $e->getMessage(), 'The directive should not be exposed on multisite.' );
			} else {
				$this->assertStringContainsString( '<code>post_max_size</code>', $e->getMessage(), 'The message should name the post_max_size directive.' );
			}
		} finally {
			$_SERVER = $original_server;
			$_POST   = $original_post;
			$_FILES  = $original_files;
		}
	}
}
