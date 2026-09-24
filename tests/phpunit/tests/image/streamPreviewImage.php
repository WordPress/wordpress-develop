<?php

/**
 * @group image
 * @group media
 * @group upload
 */
class StreamPreviewImage extends WP_UnitTestCase {
	protected static $attachment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';
		self::$attachment_id = $factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.jpg' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_attachment( self::$attachment_id, true );
	}

	/**
	 * Test stream_preview_image function with invalid post ID.
	 */
	public function test_stream_preview_image_invalid_id() {
		$result = stream_preview_image( 0 );

		$this->assertFalse( $result );
	}

	/**
	 * Test stream_preview_image function with non-image attachment.
	 */
	public function test_stream_preview_image_non_image() {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/functions/dummy.txt' );

		$result = stream_preview_image( $attachment_id );

		$this->assertFalse( $result );

		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test stream_preview_image function with error.
	 */
	public function test_stream_preview_image() {
		$attachment_id = self::$attachment_id;
		$mime_type     = 'image/jpeg';

		// Get the image editor.
		$image_path = get_attached_file( $attachment_id );
		$image      = wp_get_image_editor( $image_path );

		if ( is_wp_error( $image ) ) {
			$this->markTestSkipped( 'WP_Image_Editor not available.' );
		}

		// Create a custom mock class that extends the actual WP_Image_Editor class.
		$mock_class = new class( $image_path ) extends WP_Image_Editor_Imagick {

			public function __construct( $image_path ) {
				parent::__construct( $image_path );
				$this->load();
			}

			// Mocked method to stream image content.
			//header( "Content-Type: $mime_type" );
			//print $this->image->getImageBlob();
			public function stream( $mime_type = null ) {
				list( $filename, $extension, $mime_type ) = $this->get_output_format( null, $mime_type );

				try {
					// Temporarily change format for stream.
					$this->image->setImageFormat( strtoupper( $extension ) );

					// Output stream of image content.
					//header( "Content-Type: $mime_type" );
					print $this->image->getImageBlob();

					// Reset image to original format.
					$this->image->setImageFormat( $this->get_extension( $this->mime_type ) );
				} catch ( Exception $e ) {
					return new WP_Error( 'image_stream_error', $e->getMessage() );
				}
			}

		};

		// Apply the filter to replace the image editor with our mock.
		add_filter(
			'image_editor_save_pre',
			function ( $img ) use ( $mock_class ) {
				return $mock_class;
			}
		);
		// Backup the current output buffering level
		$ob_level = ob_get_level();
		// Capture the output.
		ob_start();
		$result = wp_stream_image( $mock_class, $mime_type, $attachment_id );
		// Get the output
		$output = ob_get_clean();

		// Restore output buffering to its original state
		while ( ob_get_level() > $ob_level ) {
			ob_end_clean();
		}

		// Assert that the function returned true.
		$this->assertTrue( $result );

		// Assert that output is not empty
		$this->assertNotEmpty( $output );

		// Assert that the output starts with the JPEG file signature
		$this->assertStringStartsWith( "\xFF\xD8", $output );

		// Remove the filter.
		remove_all_filters( 'image_editor_save_pre' );
	}
}
