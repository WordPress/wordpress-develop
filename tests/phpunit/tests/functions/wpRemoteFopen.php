<?php
/**
 * @group http
 * @group external-http
 * @group functions.php
 */
class Tests_Functions_wpRemoteFopen extends WP_UnitTestCase {

	/**
	 * @ticket 48845
	 */
	public function test_wp_remote_fopen_empty() {
		$this->assertFalse( wp_remote_fopen( '' ) );
	}

	/**
	 * @ticket 48845
	 */
	public function test_wp_remote_fopen_bad_url() {
		$this->assertFalse( wp_remote_fopen( 'wp.com' ) );
	}

	/**
	 * @ticket 48845
	 */
	public function test_wp_remote_fopen() {
		// This URL gives a direct 200 response.
		$url      = 'https://asdftestblog1.files.wordpress.com/2007/09/2007-06-30-dsc_4700-1.jpg';
		$response = wp_remote_fopen( $url );

		$this->assertInternalType( 'string', $response );
		$this->assertSame( 40148, strlen( $response ) );
	}
}
