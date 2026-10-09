<?php
/**
 * Tests for `download_url_multiple()`.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Tests for the `download_url_multiple()` function.
 *
 * @group admin
 * @group file
 *
 * @covers ::download_url_multiple
 * @covers ::_wp_handle_download_response
 */
class Tests_Admin_DownloadUrlMultiple extends WP_UnitTestCase {

	/**
	 * Temporary files created during tests.
	 *
	 * @var string[]
	 */
	protected $test_files = array();

	/**
	 * Cleans up temporary files after each test.
	 */
	public function tear_down() {
		foreach ( $this->test_files as $file ) {
			if ( is_string( $file ) && file_exists( $file ) ) {
				unlink( $file );
			}
		}
		$this->test_files = array();

		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'download_url_error_max_body_size' );

		parent::tear_down();
	}

	/**
	 * Tests that passing an empty array of URLs returns an empty array.
	 *
	 * @ticket 37459
	 */
	public function test_download_url_multiple_empty_urls() {
		$results = download_url_multiple( array() );

		$this->assertSame( array(), $results );
	}

	/**
	 * Tests that invalid or empty URLs in the array produce WP_Error with code 'http_no_url'.
	 *
	 * @ticket 37459
	 */
	public function test_download_url_multiple_empty_url_in_list() {
		$urls = array(
			'first'  => '',
			'second' => false,
		);

		$results = download_url_multiple( $urls );

		$this->assertCount( 2, $results );
		$this->assertArrayHasKey( 'first', $results );
		$this->assertArrayHasKey( 'second', $results );
		$this->assertWPError( $results['first'] );
		$this->assertSame( 'http_no_url', $results['first']->get_error_code() );
		$this->assertWPError( $results['second'] );
		$this->assertSame( 'http_no_url', $results['second']->get_error_code() );
	}

	/**
	 * Tests that multiple concurrent downloads succeed, preserve array keys, and write content.
	 *
	 * @ticket 37459
	 */
	public function test_download_url_multiple_success_and_preserves_keys() {
		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], 'Payload for ' . $url );
				}

				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'application/zip',
					),
				);
			},
			10,
			3
		);

		$urls = array(
			'plugin_alpha' => 'https://example.org/plugin-alpha.zip',
			'plugin_beta'  => 'https://example.org/plugin-beta.zip',
			42             => 'https://example.org/plugin-numeric.zip',
		);

		$results = download_url_multiple( $urls );

		$this->assertSame( array_keys( $urls ), array_keys( $results ) );

		foreach ( $results as $key => $file_path ) {
			$this->assertIsString( $file_path, "Result for key '$key' should be a file path string." );
			$this->assertFileExists( $file_path );
			$this->test_files[] = $file_path;

			$content = file_get_contents( $file_path );
			$this->assertSame( 'Payload for ' . $urls[ $key ], $content );
		}
	}

	/**
	 * Tests partial batch failure where one URL succeeds and another fails with a 404 response.
	 *
	 * @ticket 37459
	 */
	public function test_download_url_multiple_partial_failure() {
		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) {
				if ( 'https://example.org/failing.zip' === $url ) {
					if ( ! empty( $parsed_args['filename'] ) ) {
						file_put_contents( $parsed_args['filename'], 'Not Found on server' );
					}
					return array(
						'response' => array(
							'code'    => 404,
							'message' => 'Not Found',
						),
						'headers'  => array(),
					);
				}

				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], 'Successful payload' );
				}

				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'application/zip',
					),
				);
			},
			10,
			3
		);

		$urls = array(
			'ok'   => 'https://example.org/successful.zip',
			'fail' => 'https://example.org/failing.zip',
		);

		$results = download_url_multiple( $urls );

		$this->assertIsString( $results['ok'] );
		$this->assertFileExists( $results['ok'] );
		$this->test_files[] = $results['ok'];

		$this->assertWPError( $results['fail'] );
		$this->assertSame( 'http_404', $results['fail']->get_error_code() );
		$this->assertSame( 404, $results['fail']->get_error_data()['code'] );
	}

	/**
	 * Tests that Content-Disposition header filenames are respected across batch requests.
	 *
	 * @ticket 37459
	 */
	public function test_download_url_multiple_respects_content_disposition() {
		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], 'Disposed content' );
				}

				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'Content-Disposition' => 'attachment; filename=custom-name.zip',
					),
				);
			},
			10,
			3
		);

		$results = download_url_multiple( array( 'item' => 'https://example.org/generic-download' ) );

		$this->assertIsString( $results['item'] );
		$this->assertFileExists( $results['item'] );
		$this->test_files[] = $results['item'];
		$this->assertStringEndsWith( 'custom-name.zip', $results['item'] );
	}

	/**
	 * Tests that Content-Type header determines file extension when temporary file lacks one.
	 *
	 * @ticket 37459
	 */
	public function test_download_url_multiple_respects_content_type() {
		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], 'Image content' );
				}

				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'image/png',
					),
				);
			},
			10,
			3
		);

		$results = download_url_multiple( array( 'image' => 'https://example.org/download-image' ) );

		$this->assertIsString( $results['image'] );
		$this->assertFileExists( $results['image'] );
		$this->test_files[] = $results['image'];
		$this->assertStringEndsWith( '.png', $results['image'] );
	}

	/**
	 * Tests Content-MD5 verification in batch downloads.
	 *
	 * @ticket 37459
	 */
	public function test_download_url_multiple_md5_verification() {
		$valid_content  = 'Verified content payload';
		$valid_md5      = base64_encode( md5( $valid_content, true ) );
		$mismatched_md5 = base64_encode( md5( 'Other content', true ) );

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) use ( $valid_content, $valid_md5, $mismatched_md5 ) {
				if ( 'https://example.org/good-md5.zip' === $url ) {
					file_put_contents( $parsed_args['filename'], $valid_content );
					return array(
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'headers'  => array(
							'Content-MD5' => $valid_md5,
						),
					);
				}

				file_put_contents( $parsed_args['filename'], $valid_content );
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'Content-MD5' => $mismatched_md5,
					),
				);
			},
			10,
			3
		);

		$urls = array(
			'good' => 'https://example.org/good-md5.zip',
			'bad'  => 'https://example.org/bad-md5.zip',
		);

		$results = download_url_multiple( $urls );

		$this->assertIsString( $results['good'] );
		$this->assertFileExists( $results['good'] );
		$this->test_files[] = $results['good'];

		$this->assertWPError( $results['bad'] );
		$this->assertSame( 'md5_mismatch', $results['bad']->get_error_code() );
	}
}
