<?php

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

/**
 * Tests for the `media_sideload_image()` function.
 *
 * @group media
 * @covers ::media_sideload_image
 */
class Tests_Media_MediaSideloadImage extends WP_UnitTestCase {

	public function tear_down() {
		$this->remove_added_uploads();
		parent::tear_down();
	}

	/**
	 * Mocks an HTTP response returning a JPEG Content-Type for use with the `pre_http_request` filter.
	 *
	 * @param false|array|WP_Error $preempt Whether to preempt an HTTP request.
	 * @param array                $args    HTTP request arguments.
	 * @return array Mocked HTTP response.
	 */
	public function mock_jpeg_http_response( $preempt, $args ) {
		if ( ! empty( $args['filename'] ) ) {
			copy( DIR_TESTDATA . '/images/test-image.jpg', $args['filename'] );
		}

		return array(
			'headers'  => array( 'content-type' => 'image/jpeg' ),
			'body'     => '',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => $args['filename'] ?? '',
		);
	}

	/**
	 * Mocks an HTTP response returning an HTML Content-Type for use with the `pre_http_request` filter.
	 *
	 * @param false|array|WP_Error $preempt Whether to preempt an HTTP request.
	 * @param array                $args    HTTP request arguments.
	 * @return array Mocked HTTP response.
	 */
	public function mock_html_http_response( $preempt, $args ) {
		if ( ! empty( $args['filename'] ) ) {
			copy( DIR_TESTDATA . '/images/test-image.jpg', $args['filename'] );
		}

		return array(
			'headers'  => array( 'content-type' => 'text/html' ),
			'body'     => '',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => $args['filename'] ?? '',
		);
	}

	/**
	 * Tests that an image at a URL without a file extension is sideloaded when the server returns a valid image Content-Type.
	 *
	 * @ticket 18730
	 */
	public function test_sideload_extensionless_url_succeeds_when_content_type_is_valid_image() {
		add_filter( 'pre_http_request', array( $this, 'mock_jpeg_http_response' ), 10, 2 );

		$result = media_sideload_image( 'http://' . WP_TESTS_DOMAIN . '/photo/1280/10464566223/1/tumblr_lrum2xzkpC1r3z8e3', 0, null, 'id' );

		remove_filter( 'pre_http_request', array( $this, 'mock_jpeg_http_response' ), 10 );

		$this->assertNotWPError( $result );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * Tests that sideloading an extensionless URL fails when the server returns a non-image Content-Type.
	 *
	 * @ticket 18730
	 */
	public function test_sideload_extensionless_url_fails_when_content_type_is_not_image() {
		add_filter( 'pre_http_request', array( $this, 'mock_html_http_response' ), 10, 2 );

		$result = media_sideload_image( 'http://' . WP_TESTS_DOMAIN . '/dynamic-resource', 0, null, 'id' );

		remove_filter( 'pre_http_request', array( $this, 'mock_html_http_response' ), 10 );

		$this->assertWPError( $result );
		$this->assertSame( 'image_sideload_failed', $result->get_error_code() );
	}
}
