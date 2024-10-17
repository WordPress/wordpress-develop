<?php

/**
 * @group file
 * @group admin
 */
class Tests_Admin_IncludesFile extends WP_UnitTestCase {

	/**
	 * @ticket 20449
	 *
	 * @covers ::get_home_path
	 */
	public function test_get_home_path() {
		$home    = get_option( 'home' );
		$siteurl = get_option( 'siteurl' );
		$sfn     = $_SERVER['SCRIPT_FILENAME'];
		$this->assertSamePathIgnoringDirectorySeparators( ABSPATH, get_home_path() );

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
	 *
	 * @covers ::download_url
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

	public function _fake_download_url_non_200_response_code( $response, $parsed_args, $url ) {
		file_put_contents( $parsed_args['filename'], 'This is an unexpected error message from your favorite server.' );
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
	 * @ticket 38231
	 * @dataProvider data_download_url_should_respect_filename_from_content_disposition_header
	 *
	 * @covers ::download_url
	 *
	 * @param $filter A callback containing a fake Content-Disposition header.
	 */
	public function test_download_url_should_respect_filename_from_content_disposition_header( $filter ) {
		add_filter( 'pre_http_request', array( $this, $filter ), 10, 3 );

		$filename = download_url( 'url_with_content_disposition_header' );
		$this->assertStringContainsString( 'filename-from-content-disposition-header', $filename );
		$this->assertFileExists( $filename );
		$this->unlink( $filename );

		remove_filter( 'pre_http_request', array( $this, $filter ) );
	}

	/**
	 * Data provider for test_download_url_should_respect_filename_from_content_disposition_header.
	 *
	 * @return array
	 */
	public function data_download_url_should_respect_filename_from_content_disposition_header() {
		return array(
			'valid parameters' => array( 'filter_content_disposition_header_with_filename' ),
			'path traversal'   => array( 'filter_content_disposition_header_with_filename_with_path_traversal' ),
			'no quotes'        => array( 'filter_content_disposition_header_with_filename_without_quotes' ),
		);
	}

	/**
	 * @ticket 55109
	 * @dataProvider data_save_to_temp_directory_when_getting_filename_from_content_disposition_header
	 *
	 * @covers ::download_url
	 *
	 * @param $filter A callback containing a fake Content-Disposition header.
	 */
	public function test_save_to_temp_directory_when_getting_filename_from_content_disposition_header( $filter ) {
		add_filter( 'pre_http_request', array( $this, $filter ), 10, 3 );

		$filename = download_url( 'url_with_content_disposition_header' );
		$this->assertStringContainsString( get_temp_dir(), $filename );
		$this->unlink( $filename );

		remove_filter( 'pre_http_request', array( $this, $filter ) );
	}

	/**
	 * Data provider for test_save_to_temp_directory_when_getting_filename_from_content_disposition_header.
	 *
	 * @return array
	 */
	public function data_save_to_temp_directory_when_getting_filename_from_content_disposition_header() {
		return array(
			'valid parameters' => array( 'filter_content_disposition_header_with_filename' ),
		);
	}

	/**
	 * Filter callback for data_download_url_should_respect_filename_from_content_disposition_header.
	 *
	 * @since 5.9.0
	 *
	 * @return array
	 */
	public function filter_content_disposition_header_with_filename( $response, $parsed_args, $url ) {
		return array(
			'response' => array(
				'code' => 200,
			),
			'headers'  => array(
				'Content-Disposition' => 'attachment; filename="filename-from-content-disposition-header.txt"',
			),
		);
	}

	/**
	 * Filter callback for data_download_url_should_respect_filename_from_content_disposition_header.
	 *
	 * @since 5.9.0
	 *
	 * @return array
	 */
	public function filter_content_disposition_header_with_filename_with_path_traversal( $response, $parsed_args, $url ) {
		return array(
			'response' => array(
				'code' => 200,
			),
			'headers'  => array(
				'Content-Disposition' => 'attachment; filename="../../filename-from-content-disposition-header.txt"',
			),
		);
	}

	/**
	 * Filter callback for data_download_url_should_respect_filename_from_content_disposition_header.
	 *
	 * @since 5.9.0
	 *
	 * @return array
	 */
	public function filter_content_disposition_header_with_filename_without_quotes( $response, $parsed_args, $url ) {
		return array(
			'response' => array(
				'code' => 200,
			),
			'headers'  => array(
				'Content-Disposition' => 'attachment; filename=filename-from-content-disposition-header.txt',
			),
		);
	}

	/**
	 * @ticket 38231
	 * @dataProvider data_download_url_should_reject_filename_from_invalid_content_disposition_header
	 *
	 * @covers ::download_url
	 *
	 * @param $filter A callback containing a fake Content-Disposition header.
	 */
	public function test_download_url_should_reject_filename_from_invalid_content_disposition_header( $filter ) {
		add_filter( 'pre_http_request', array( $this, $filter ), 10, 3 );

		$filename = download_url( 'url_with_content_disposition_header' );
		$this->assertStringContainsString( 'url_with_content_disposition_header', $filename );
		$this->unlink( $filename );

		remove_filter( 'pre_http_request', array( $this, $filter ) );
	}

	/**
	 * Data provider for test_download_url_should_reject_filename_from_invalid_content_disposition_header.
	 *
	 * @return array
	 */
	public function data_download_url_should_reject_filename_from_invalid_content_disposition_header() {
		return array(
			'no context'        => array( 'filter_content_disposition_header_with_filename_without_context' ),
			'inline context'    => array( 'filter_content_disposition_header_with_filename_with_inline_context' ),
			'form-data context' => array( 'filter_content_disposition_header_with_filename_with_form_data_context' ),
		);
	}

	/**
	 * Filter callback for data_download_url_should_reject_filename_from_invalid_content_disposition_header.
	 *
	 * @since 5.9.0
	 *
	 * @return array
	 */
	public function filter_content_disposition_header_with_filename_without_context( $response, $parsed_args, $url ) {
		return array(
			'response' => array(
				'code' => 200,
			),
			'headers'  => array(
				'Content-Disposition' => 'filename="filename-from-content-disposition-header.txt"',
			),
		);
	}

	/**
	 * Filter callback for data_download_url_should_reject_filename_from_invalid_content_disposition_header.
	 *
	 * @since 5.9.0
	 *
	 * @return array
	 */
	public function filter_content_disposition_header_with_filename_with_inline_context( $response, $parsed_args, $url ) {
		return array(
			'response' => array(
				'code' => 200,
			),
			'headers'  => array(
				'Content-Disposition' => 'inline; filename="filename-from-content-disposition-header.txt"',
			),
		);
	}

	/**
	 * Filter callback for data_download_url_should_reject_filename_from_invalid_content_disposition_header.
	 *
	 * @since 5.9.0
	 *
	 * @return array
	 */
	public function filter_content_disposition_header_with_filename_with_form_data_context( $response, $parsed_args, $url ) {
		return array(
			'response' => array(
				'code' => 200,
			),
			'headers'  => array(
				'Content-Disposition' => 'form-data; name="file"; filename="filename-from-content-disposition-header.txt"',
			),
		);
	}

	/**
	 * Verify that a WP_Error object is returned when invalid input is passed as the `$url` parameter.
	 *
	 * @covers ::download_url
	 * @dataProvider data_download_url_empty_url
	 *
	 * @param mixed $url Input URL.
	 */
	public function test_download_url_empty_url( $url ) {
		$error = download_url( $url );
		$this->assertWPError( $error );
		$this->assertSame( 'http_no_url', $error->get_error_code() );
		$this->assertSame( 'No URL Provided.', $error->get_error_message() );
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
	 * Test that PHP 8.1 "passing null to non-nullable" deprecation notice
	 * is not thrown when the `$url` does not have a path component.
	 *
	 * @ticket 53635
	 * @covers ::download_url
	 */
	public function test_download_url_no_warning_for_url_without_path() {
		// Hook a mocked HTTP request response.
		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );

		$result = download_url( 'https://example.com' );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result ); // File path will be generated, but will never be empty.
	}

	/**
	 * Test that PHP 8.1 "passing null to non-nullable" deprecation notice
	 * is not thrown when the `$url` does not have a path component,
	 * and signature verification via a local file is requested.
	 *
	 * @ticket 53635
	 * @covers ::download_url
	 */
	public function test_download_url_no_warning_for_url_without_path_with_signature_verification() {
		// Hook a mocked HTTP request response.
		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );

		add_filter(
			'wp_signature_hosts',
			static function ( $urls ) {
				$urls[] = 'example.com';
				return $urls;
			}
		);
		$error = download_url( 'https://example.com', 300, true );

		/*
		 * Note: This test is not testing the signature verification itself.
		 * There is no signature available for the domain used in the test,
		 * which is why an error is expected and that's fine.
		 * The point of the test is to verify that the call to `verify_file_signature()`
		 * is actually reached and that no PHP deprecation notice is thrown
		 * before this point.
		 */
		$this->assertWPError( $error );
		$this->assertSame( 'signature_verification_no_signature', $error->get_error_code() );
	}

	/**
	 * Mock the HTTP request response.
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request. Default false.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 * @return false|array|WP_Error Response data.
	 */
	public function mock_http_request( $response, $parsed_args, $url ) {
		if ( 'https://example.com' === $url ) {
			return array(
				'response' => array(
					'code' => 200,
				),
			);
		}

		return $response;
	}

	/**
	 * @ticket 54738
	 * @dataProvider data_download_url_should_use_the_content_type_header_to_set_extension_of_a_file_if_extension_was_not_determined
	 *
	 * @covers ::download_url
	 *
	 * @param $filter A callback containing a fake Content-Type header.
	 */
	public function test_download_url_should_use_the_content_type_header_to_set_extension_of_a_file_if_extension_was_not_determined( $filter, $extension ) {
		add_filter( 'pre_http_request', $filter );

		$filename = download_url( 'url_with_content_type_header' );
		$this->assertStringEndsWith( $extension, $filename );
		$this->assertFileExists( $filename );
		$this->unlink( $filename );
	}

	/**
	 * Data provider for test_download_url_should_use_the_content_type_header_to_set_extension_of_a_file_if_extension_was_not_determined
	 *
	 * @see test_download_url_should_use_the_content_type_header_to_set_extension_of_a_file_if_extension_was_not_determined()
	 * @test
	 * @ticket 54738
	 *
	 * @return Generator
	 */
	public function data_download_url_should_use_the_content_type_header_to_set_extension_of_a_file_if_extension_was_not_determined() {
		yield 'Content-Type header in the response' => array(
			function () {
				return array(
					'response' => array(
						'code' => 200,
					),
					'headers'  => array(
						'content-type' => 'image/jpeg',
					),
				);
			},
			'.jpg',
		);

		yield 'Invalid Content-Type header' => array(
			function () {
				return array(
					'response' => array(
						'code' => 200,
					),
					'headers'  => array(
						'content-type' => '../../filename-from-content-disposition-header.txt',
					),
				);
			},
			'.tmp',
		);

		yield 'Valid content type but not supported mime type' => array(
			function () {
				return array(
					'response' => array(
						'code' => 200,
					),
					'headers'  => array(
						'content-type' => 'image/avif',
					),
				);
			},
			'.tmp',
		);
	}
}
