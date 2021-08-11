<?php

/**
 * @group file
 * @group admin
 */
class Tests_Admin_includesFile extends WP_UnitTestCase {

	/**
	 * @ticket 20449
	 */
	function test_get_home_path() {
		$home    = get_option( 'home' );
		$siteurl = get_option( 'siteurl' );
		$sfn     = $_SERVER['SCRIPT_FILENAME'];
		$this->assertSame( str_replace( '\\', '/', ABSPATH ), get_home_path() );

		update_option( 'home', 'http://localhost' );
		update_option( 'siteurl', 'http://localhost/wp' );

		$_SERVER['SCRIPT_FILENAME'] = 'D:\root\vhosts\site\httpdocs\wp\wp-admin\options-permalink.php';
		$this->assertSame( 'D:/root/vhosts/site/httpdocs/', get_home_path() );

		$_SERVER['SCRIPT_FILENAME'] = '/Users/foo/public_html/trunk/wp/wp-admin/options-permalink.php';
		$this->assertSame( '/Users/foo/public_html/trunk/', get_home_path() );

		$_SERVER['SCRIPT_FILENAME'] = 'S:/home/wordpress/trunk/wp/wp-admin/options-permalink.php';
		$this->assertSame( 'S:/home/wordpress/trunk/', get_home_path() );

		update_option( 'home', $home );
		update_option( 'siteurl', $siteurl );
		$_SERVER['SCRIPT_FILENAME'] = $sfn;
	}

	/**
	 * @ticket 43329
	 */
	public function test_download_url_non_200_response_code() {
		add_filter( 'pre_http_request', array( $this, '_fake_download_url_non_200_response_code' ), 10, 3 );

		$error = download_url( 'test_download_url_non_200' );
		$this->assertWPError( $error );
		$this->assertSame(
			array(
				'code' => 418,
				'body' => 'This is an unexpected error message from your favorite server.',
			),
			$error->get_error_data()
		);

		add_filter( 'download_url_error_max_body_size', array( $this, '__return_5' ) );

		$error = download_url( 'test_download_url_non_200' );
		$this->assertWPError( $error );
		$this->assertSame(
			array(
				'code' => 418,
				'body' => 'This ',
			),
			$error->get_error_data()
		);

		remove_filter( 'download_url_error_max_body_size', array( $this, '__return_5' ) );
		remove_filter( 'pre_http_request', array( $this, '_fake_download_url_non_200_response_code' ) );
	}

	public function _fake_download_url_non_200_response_code( $response, $args, $url ) {
		file_put_contents( $args['filename'], 'This is an unexpected error message from your favorite server.' );
		return array(
			'response' => array(
				'code'    => 418,
				'message' => "I'm a teapot!",
			),
		);
	}

	public function __return_5() {
		return 5;
	}

	/**
	 * Verify that a WP_Error object is returned when invalid input is passed as the $url parameter.
	 *
	 * @covers ::download_url
	 *
	 * @dataProvider data_download_url_empty_url
	 *
	 * @param mixed $url Input URL.
	 */
	public function test_download_url_empty_url( $url ) {
		$error = download_url( $url );
		$this->assertWPError( $error );
		$this->assertSame( 'http_no_url', $error->get_error_code() );
		$this->assertSame( 'Invalid URL Provided.', $error->get_error_message() );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_download_url_empty_url() {
		return array(
			'null'         => array( null ),
			'false'        => array( false ),
			'integer 0'    => array( 0 ),
			'empty string' => array( '' ),
			'string 0'     => array( '0' ),
		);
	}

	/**
	 * Verify that no "passing null to non-nullable" error is thrown on PHP 8.1,
	 * when the $url does not have a path component.
	 *
	 * @covers ::download_url
	 *
	 * @ticket 53635
	 */
	public function test_download_url_no_warning_with_url_without_path() {
		$result = download_url( 'https://example.com' );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result ); // File path will be generated, but will never be empty.
	}
}
