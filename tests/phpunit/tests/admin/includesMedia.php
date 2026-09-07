<?php

/**
 * @group media
 * @group admin
 */
class Tests_Admin_IncludesMedia extends WP_UnitTestCase {

	/**
	 * The local fixture that the mocked HTTP download writes into the temporary file.
	 *
	 * @var string
	 */
	protected $mock_download_fixture = 'canola.jpg';

	/**
	 * The response that the mocked HTTP download returns, or null for a 200 response.
	 *
	 * @var array|WP_Error|null
	 */
	protected $mock_download_response = null;

	/**
	 * The URLs requested through the mocked HTTP download, in request order.
	 *
	 * @var string[]
	 */
	protected $mock_download_urls = array();

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	public function tear_down() {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Short-circuits the HTTP request made by download_url().
	 *
	 * download_url() streams the response to the temporary file named in the request
	 * arguments, so a preempted response must write that file itself for the rest of
	 * media_sideload_image() to have something to work with.
	 *
	 * @param false|array|WP_Error $response    Whether to preempt an HTTP request's return value.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 * @return array|WP_Error The faked response.
	 */
	public function mock_image_download( $response, $parsed_args, $url ) {
		$this->mock_download_urls[] = $url;

		if ( null !== $this->mock_download_response ) {
			return $this->mock_download_response;
		}

		if ( ! empty( $parsed_args['filename'] ) ) {
			copy( DIR_TESTDATA . '/images/' . $this->mock_download_fixture, $parsed_args['filename'] );
		}

		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => isset( $parsed_args['filename'] ) ? $parsed_args['filename'] : null,
		);
	}

	/**
	 * Registers the mocked HTTP download for the duration of a test.
	 */
	protected function mock_image_downloads() {
		add_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10, 3 );
	}

	/**
	 * Returns the ID of the attachment sideloaded onto a post.
	 *
	 * @param int $post_id The parent post ID.
	 * @return int The attachment ID, or 0 if no attachment was created.
	 */
	protected function get_sideloaded_attachment_id( $post_id ) {
		$attachments = get_children(
			array(
				'post_parent' => $post_id,
				'post_type'   => 'attachment',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		return $attachments ? (int) reset( $attachments ) : 0;
	}

	/**
	 * Tests that a `filesize` stored in the attachment metadata is normalized to a positive integer.
	 *
	 * When the stored value cannot be normalized, it should be treated as missing so that the
	 * filesystem fallback runs instead.
	 *
	 * @ticket 65686
	 *
	 * @covers ::attachment_submitbox_metadata
	 *
	 * @dataProvider data_attachment_submitbox_metadata_filesize
	 *
	 * @param mixed            $filesize The `filesize` value stored in the attachment metadata.
	 * @param int<0, max>|null $expected The expected file size in bytes, or null if none should be displayed.
	 */
	public function test_attachment_submitbox_metadata_filesize( $filesize, ?int $expected ) {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => 'test-image.jpg',
				'post_title'     => 'Attachment Title',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->assertIsInt( $id );

		wp_update_attachment_metadata(
			$id,
			array(
				'width'    => 50,
				'height'   => 50,
				'file'     => 'test-image.jpg',
				'filesize' => $filesize,
			)
		);

		$GLOBALS['post'] = get_post( $id );

		$output = get_echo( 'attachment_submitbox_metadata' );

		if ( null === $expected ) {
			$this->assertStringNotContainsString( 'misc-pub-filesize', $output, 'The file size should not have been displayed.' );
		} else {
			$this->assertStringContainsString( size_format( $expected ), $output, 'The displayed file size did not match the normalized file size.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ filesize: mixed, expected: int<0, max>|null }>
	 */
	public function data_attachment_submitbox_metadata_filesize(): array {
		return array(
			'an integer'                  => array(
				'filesize' => 12345,
				'expected' => 12345,
			),
			'a numeric string'            => array(
				'filesize' => '12345',
				'expected' => 12345,
			),
			'a float'                     => array(
				'filesize' => 12345.6,
				'expected' => 12345,
			),
			'a float as a string'         => array(
				'filesize' => '12345.6',
				'expected' => 12345,
			),
			'an exponential string'       => array(
				'filesize' => '1e3',
				'expected' => 1000,
			),
			'a value smaller than a byte' => array(
				'filesize' => 0.5,
				'expected' => null,
			),
			'zero'                        => array(
				'filesize' => 0,
				'expected' => null,
			),
			'a negative integer'          => array(
				'filesize' => -12345,
				'expected' => null,
			),
			'an empty string'             => array(
				'filesize' => '',
				'expected' => null,
			),
			'a non-numeric string'        => array(
				'filesize' => 'not-a-number',
				'expected' => null,
			),
			'an array'                    => array(
				'filesize' => array( 12345 ),
				'expected' => null,
			),
			'null'                        => array(
				'filesize' => null,
				'expected' => null,
			),
			'false'                       => array(
				'filesize' => false,
				'expected' => null,
			),
			'true'                        => array(
				'filesize' => true,
				'expected' => null,
			),
		);
	}

	/**
	 * Tests that an unusable `filesize` in the attachment metadata falls back to the size of the file.
	 *
	 * @ticket 65686
	 *
	 * @covers ::attachment_submitbox_metadata
	 *
	 * @dataProvider data_attachment_submitbox_metadata_filesize_falls_back_to_the_file
	 *
	 * @param mixed $filesize The `filesize` value stored in the attachment metadata.
	 */
	public function test_attachment_submitbox_metadata_filesize_falls_back_to_the_file( $filesize ) {
		$id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $id );
		$file = get_attached_file( $id );
		$this->assertIsString( $file );

		$meta = wp_get_attachment_metadata( $id );
		$this->assertIsArray( $meta );
		$meta['filesize'] = $filesize;
		wp_update_attachment_metadata( $id, $meta );

		$GLOBALS['post'] = get_post( $id );

		$output = get_echo( 'attachment_submitbox_metadata' );

		$filesize = wp_filesize( $file );
		$this->assertIsInt( $filesize );
		$this->assertStringContainsString( size_format( $filesize ), $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ filesize: mixed }>
	 */
	public function data_attachment_submitbox_metadata_filesize_falls_back_to_the_file(): array {
		return array(
			'a value smaller than a byte' => array( 'filesize' => 0.5 ),
			'zero'                        => array( 'filesize' => 0 ),
			'a negative integer'          => array( 'filesize' => -12345 ),
			'an empty string'             => array( 'filesize' => '' ),
			'a non-numeric string'        => array( 'filesize' => 'not-a-number' ),
			'an array'                    => array( 'filesize' => array( 12345 ) ),
			'null'                        => array( 'filesize' => null ),
			'false'                       => array( 'filesize' => false ),
			'true'                        => array( 'filesize' => true ),
		);
	}

	/**
	 * Tests that the URL an image was sideloaded from is stored in the `_source_url` post meta.
	 *
	 * @ticket 49631
	 * @ticket 48164
	 *
	 * @covers ::media_sideload_image
	 */
	public function test_media_sideload_image_stores_the_source_url_in_post_meta() {
		$this->mock_image_downloads();

		$post_id = self::factory()->post->create();
		$url     = 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.jpg';

		$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );

		$this->assertNotWPError( $attachment_id, 'The image should have been sideloaded.' );
		$this->assertSame(
			$url,
			get_post_meta( $attachment_id, '_source_url', true ),
			'The original URL should have been stored in the `_source_url` post meta.'
		);
	}

	/**
	 * Tests that the `_source_url` post meta records the URL that was requested, query string included.
	 *
	 * The file name is derived from the path, but the meta should store the URL as it was passed in.
	 *
	 * @ticket 49631
	 *
	 * @covers ::media_sideload_image
	 */
	public function test_media_sideload_image_stores_the_source_url_including_the_query_string() {
		$this->mock_image_downloads();

		$post_id = self::factory()->post->create();
		$url     = 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.jpg?ver=1.2.3';

		$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );

		$this->assertNotWPError( $attachment_id, 'The image should have been sideloaded.' );
		$this->assertSame(
			$url,
			get_post_meta( $attachment_id, '_source_url', true ),
			'The `_source_url` post meta should store the URL as passed, query string included.'
		);
		$this->assertSame(
			'canola.jpg',
			wp_basename( get_attached_file( $attachment_id ) ),
			'The query string should have been stripped from the file name.'
		);
	}

	/**
	 * Tests that a sideloaded image is attached to the given post.
	 *
	 * @ticket 49631
	 *
	 * @covers ::media_sideload_image
	 */
	public function test_media_sideload_image_attaches_the_attachment_to_the_post() {
		$this->mock_image_downloads();

		$post_id = self::factory()->post->create();

		$attachment_id = media_sideload_image( 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.jpg', $post_id, null, 'id' );

		$this->assertNotWPError( $attachment_id, 'The image should have been sideloaded.' );
		$this->assertSame(
			$post_id,
			(int) get_post_field( 'post_parent', $attachment_id ),
			'The attachment should have been attached to the post.'
		);
	}

	/**
	 * Tests that the requested return type determines what is returned on success.
	 *
	 * @ticket 49631
	 *
	 * @covers ::media_sideload_image
	 *
	 * @dataProvider data_media_sideload_image_return_types
	 *
	 * @param string|null $return_type The `$return_type` passed to media_sideload_image().
	 */
	public function test_media_sideload_image_return_types( $return_type ) {
		$this->mock_image_downloads();

		$post_id = self::factory()->post->create();
		$url     = 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.jpg';

		if ( null === $return_type ) {
			$actual = media_sideload_image( $url, $post_id );
		} else {
			$actual = media_sideload_image( $url, $post_id, null, $return_type );
		}

		$this->assertNotWPError( $actual, 'The image should have been sideloaded.' );

		$attachment_id = $this->get_sideloaded_attachment_id( $post_id );
		$this->assertNotSame( 0, $attachment_id, 'An attachment should have been created.' );

		if ( 'id' === $return_type ) {
			$this->assertSame( $attachment_id, $actual, 'The attachment ID should have been returned.' );
		} elseif ( 'src' === $return_type ) {
			$this->assertSame( wp_get_attachment_url( $attachment_id ), $actual, 'The attachment URL should have been returned.' );
		} else {
			$this->assertSame(
				sprintf( "<img src='%s' alt='' />", wp_get_attachment_url( $attachment_id ) ),
				$actual,
				'The image tag should have been returned.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ return_type: string|null }>
	 */
	public function data_media_sideload_image_return_types() {
		return array(
			'no return type'   => array( 'return_type' => null ),
			'"html"'           => array( 'return_type' => 'html' ),
			'an unknown value' => array( 'return_type' => 'not-a-return-type' ),
			'"src"'            => array( 'return_type' => 'src' ),
			'"id"'             => array( 'return_type' => 'id' ),
		);
	}

	/**
	 * Tests that the description is used as the alt attribute of the returned image tag.
	 *
	 * @ticket 49631
	 *
	 * @covers ::media_sideload_image
	 */
	public function test_media_sideload_image_uses_the_description_as_alt_text() {
		$this->mock_image_downloads();

		$post_id = self::factory()->post->create();

		$html = media_sideload_image( 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.jpg', $post_id, 'A "field" of canola' );

		$this->assertNotWPError( $html, 'The image should have been sideloaded.' );

		$attachment_id = $this->get_sideloaded_attachment_id( $post_id );
		$this->assertSame(
			sprintf(
				"<img src='%s' alt='%s' />",
				wp_get_attachment_url( $attachment_id ),
				esc_attr( 'A "field" of canola' )
			),
			$html,
			'The description should have been used as escaped alt text.'
		);
	}

	/**
	 * Tests that a URL without an allowed image extension is rejected before any HTTP request is made.
	 *
	 * @ticket 49631
	 *
	 * @covers ::media_sideload_image
	 *
	 * @dataProvider data_media_sideload_image_invalid_urls
	 *
	 * @param string $url The URL to sideload.
	 */
	public function test_media_sideload_image_rejects_urls_without_an_allowed_extension( $url ) {
		$this->mock_image_downloads();

		$actual = media_sideload_image( $url, self::factory()->post->create() );

		$this->assertWPError( $actual, 'A WP_Error should have been returned.' );
		$this->assertSame( 'image_sideload_failed', $actual->get_error_code(), 'The error code did not match.' );
		$this->assertSame( 'Invalid image URL.', $actual->get_error_message(), 'The error message did not match.' );
		$this->assertSame( array(), $this->mock_download_urls, 'No HTTP request should have been made.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ url: string }>
	 */
	public function data_media_sideload_image_invalid_urls() {
		return array(
			'a disallowed extension' => array( 'url' => 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.txt' ),
			'an executable file'     => array( 'url' => 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.php' ),
			'no extension'           => array( 'url' => 'http://' . WP_TESTS_DOMAIN . '/external/source/canola' ),
			'a partial extension'    => array( 'url' => 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.jpgx' ),
		);
	}

	/**
	 * Tests that an empty URL is rejected.
	 *
	 * @ticket 49631
	 *
	 * @covers ::media_sideload_image
	 */
	public function test_media_sideload_image_rejects_an_empty_url() {
		$this->mock_image_downloads();

		$actual = media_sideload_image( '' );

		$this->assertWPError( $actual, 'A WP_Error should have been returned.' );
		$this->assertSame( 'image_sideload_failed', $actual->get_error_code(), 'The error code did not match.' );
		$this->assertSame( array(), $this->mock_download_urls, 'No HTTP request should have been made.' );
	}

	/**
	 * Tests that extensions added through the `image_sideload_extensions` filter are allowed.
	 *
	 * @ticket 49631
	 *
	 * @covers ::media_sideload_image
	 */
	public function test_media_sideload_image_allows_extensions_added_by_filter() {
		$this->mock_download_fixture = 'test-image.bmp';
		$this->mock_image_downloads();

		add_filter(
			'image_sideload_extensions',
			static function ( $allowed_extensions ) {
				$allowed_extensions[] = 'bmp';

				return $allowed_extensions;
			}
		);

		$post_id = self::factory()->post->create();
		$url     = 'http://' . WP_TESTS_DOMAIN . '/external/source/test-image.bmp';

		$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );

		$this->assertNotWPError( $attachment_id, 'The image should have been sideloaded.' );
		$this->assertSame( 'image/bmp', get_post_mime_type( $attachment_id ), 'The attachment mime type did not match.' );
		$this->assertSame(
			$url,
			get_post_meta( $attachment_id, '_source_url', true ),
			'The original URL should have been stored in the `_source_url` post meta.'
		);
	}

	/**
	 * Tests that extensions removed through the `image_sideload_extensions` filter are rejected.
	 *
	 * @ticket 49631
	 *
	 * @covers ::media_sideload_image
	 */
	public function test_media_sideload_image_rejects_extensions_removed_by_filter() {
		$this->mock_image_downloads();

		add_filter(
			'image_sideload_extensions',
			static function ( $allowed_extensions ) {
				return array_values( array_diff( $allowed_extensions, array( 'jpg', 'jpeg', 'jpe' ) ) );
			}
		);

		$actual = media_sideload_image( 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.jpg' );

		$this->assertWPError( $actual, 'A WP_Error should have been returned.' );
		$this->assertSame( 'image_sideload_failed', $actual->get_error_code(), 'The error code did not match.' );
		$this->assertSame( array(), $this->mock_download_urls, 'No HTTP request should have been made.' );
	}

	/**
	 * Tests that the filtered list of allowed extensions is passed the URL being sideloaded.
	 *
	 * @ticket 49631
	 *
	 * @covers ::media_sideload_image
	 */
	public function test_media_sideload_image_passes_the_url_to_the_extensions_filter() {
		$this->mock_image_downloads();

		$url      = 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.jpg';
		$filtered = array();

		add_filter(
			'image_sideload_extensions',
			static function ( $allowed_extensions, $file ) use ( &$filtered ) {
				$filtered[] = $file;

				return $allowed_extensions;
			},
			10,
			2
		);

		media_sideload_image( $url, self::factory()->post->create(), null, 'id' );

		$this->assertSame( array( $url ), $filtered, 'The URL should have been passed to the filter.' );
	}

	/**
	 * Tests that a download failure is returned to the caller.
	 *
	 * @ticket 49631
	 *
	 * @covers ::media_sideload_image
	 *
	 * @dataProvider data_media_sideload_image_download_failures
	 *
	 * @param array|WP_Error $response   The response returned by the mocked HTTP request.
	 * @param string         $error_code The expected error code.
	 */
	public function test_media_sideload_image_returns_download_errors( $response, $error_code ) {
		$this->mock_download_response = $response;
		$this->mock_image_downloads();

		$post_id = self::factory()->post->create();

		$actual = media_sideload_image( 'http://' . WP_TESTS_DOMAIN . '/external/source/canola.jpg', $post_id );

		$this->assertWPError( $actual, 'A WP_Error should have been returned.' );
		$this->assertSame( $error_code, $actual->get_error_code(), 'The error code did not match.' );
		$this->assertSame( 0, $this->get_sideloaded_attachment_id( $post_id ), 'No attachment should have been created.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ response: array|WP_Error, error_code: string }>
	 */
	public function data_media_sideload_image_download_failures() {
		return array(
			'a transport error' => array(
				'response'   => new WP_Error( 'http_request_failed', 'A valid URL was not provided.' ),
				'error_code' => 'http_request_failed',
			),
			'a 404 response'    => array(
				'response'   => array(
					'headers'  => array(),
					'body'     => '',
					'response' => array(
						'code'    => 404,
						'message' => 'Not Found',
					),
					'cookies'  => array(),
					'filename' => null,
				),
				'error_code' => 'http_404',
			),
		);
	}
}
