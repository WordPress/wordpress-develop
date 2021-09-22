<?php

/**
 * @group image
 * @group media
 * @group upload
 */
class Tests_Image_Functions extends WP_UnitTestCase {

	/**
	 * Setup test fixture
	 */
	public function set_up() {
		parent::set_up();

		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

		require_once DIR_TESTDATA . '/../includes/mock-image-editor.php';

		// Ensure no legacy / failed tests detritus.
		$folder = get_temp_dir() . 'wordpress-gsoc-flyer*.*';

		foreach ( glob( $folder ) as $file ) {
			unlink( $file );
		}
	}

	/**
	 * Get the MIME type of a file
	 *
	 * @param string $filename
	 * @return string
	 */
	protected function get_mime_type( $filename ) {
		$mime_type = '';
		if ( extension_loaded( 'fileinfo' ) ) {
			$finfo     = new finfo();
			$mime_type = $finfo->file( $filename, FILEINFO_MIME );
		}
		if ( false !== strpos( $mime_type, ';' ) ) {
			list( $mime_type, $charset ) = explode( ';', $mime_type, 2 );
		}
		return $mime_type;
	}

	function test_is_image_positive() {
		// These are all image files recognized by PHP.
		$files = array(
			'test-image-cmyk.jpg',
			'test-image.bmp',
			'test-image-grayscale.jpg',
			'test-image.gif',
			'test-image.png',
			'test-image.tiff',
			'test-image-lzw.tiff',
			'test-image.jp2',
			'test-image.psd',
			'test-image-zip.tiff',
			'test-image.jpg',
			'webp-animated.webp',
			'webp-lossless.webp',
			'webp-lossy.webp',
			'webp-transparent.webp',
		);

		// IMAGETYPE_ICO is only defined in PHP 5.3+.
		if ( defined( 'IMAGETYPE_ICO' ) ) {
			$files[] = 'test-image.ico';
		}

		foreach ( $files as $file ) {
			$this->assertTrue( file_is_valid_image( DIR_TESTDATA . '/images/' . $file ), "file_is_valid_image($file) should return true" );
		}
	}

	function test_is_image_negative() {
		// These are actually image files but aren't recognized or usable by PHP.
		$files = array(
			'test-image.pct',
			'test-image.tga',
			'test-image.sgi',
		);

		foreach ( $files as $file ) {
			$this->assertFalse( file_is_valid_image( DIR_TESTDATA . '/images/' . $file ), "file_is_valid_image($file) should return false" );
		}
	}

	function test_is_displayable_image_positive() {
		// These are all usable in typical web browsers.
		$files = array(
			'test-image.gif',
			'test-image.png',
			'test-image.jpg',
		);

		// Add WebP images if the image editor supports them.
		$file   = DIR_TESTDATA . '/images/test-image.webp';
		$editor = wp_get_image_editor( $file );

		if ( ! is_wp_error( $editor ) && $editor->supports_mime_type( 'image/webp' ) ) {
			$files = array_merge(
				$files,
				array(
					'webp-animated.webp',
					'webp-lossless.webp',
					'webp-lossy.webp',
					'webp-transparent.webp',
				)
			);
		}

		// IMAGETYPE_ICO is only defined in PHP 5.3+.
		if ( defined( 'IMAGETYPE_ICO' ) ) {
			$files[] = 'test-image.ico';
		}

		foreach ( $files as $file ) {
			$this->assertTrue( file_is_displayable_image( DIR_TESTDATA . '/images/' . $file ), "file_is_valid_image($file) should return true" );
		}
	}

	function test_is_displayable_image_negative() {
		// These are image files but aren't suitable for web pages because of compatibility or size issues.
		$files = array(
			// 'test-image-cmyk.jpg',      Allowed in r9727.
			// 'test-image.bmp',           Allowed in r28589.
			// 'test-image-grayscale.jpg', Allowed in r9727.
			'test-image.pct',
			'test-image.tga',
			'test-image.sgi',
			'test-image.tiff',
			'test-image-lzw.tiff',
			'test-image.jp2',
			'test-image.psd',
			'test-image-zip.tiff',
		);

		foreach ( $files as $file ) {
			$this->assertFalse( file_is_displayable_image( DIR_TESTDATA . '/images/' . $file ), "file_is_valid_image($file) should return false" );
		}
	}


	/**
	 * @ticket 50833
	 */
	function test_is_gd_image_invalid_types() {
		$this->assertFalse( is_gd_image( new stdClass() ) );
		$this->assertFalse( is_gd_image( array() ) );
		$this->assertFalse( is_gd_image( null ) );

		$handle = fopen( __FILE__, 'r' );
		$this->assertFalse( is_gd_image( $handle ) );
		fclose( $handle );
	}

	/**
	 * @ticket 50833
	 * @requires extension gd
	 */
	function test_is_gd_image_valid_types() {
		$this->assertTrue( is_gd_image( imagecreate( 5, 5 ) ) );
	}

	/**
	 * Test save image file and mime_types
	 *
	 * @ticket 6821
	 * @requires extension fileinfo
	 */
	public function test_wp_save_image_file() {
		$classes = $this->get_image_editor_engine_classes();

		require_once ABSPATH . 'wp-admin/includes/image-edit.php';

		// Mime types.
		$mime_types = array(
			'image/jpeg',
			'image/gif',
			'image/png',
		);

		// Include WebP in tests when platform supports it.
		if ( function_exists( 'imagewebp' ) ) {
			array_push( $mime_types, 'image/webp' );
		}

		// Test each image editor engine.
		foreach ( $classes as $class ) {
			$img    = new $class( DIR_TESTDATA . '/images/canola.jpg' );
			$loaded = $img->load();

			// Save a file as each mime type, assert it works.
			foreach ( $mime_types as $mime_type ) {
				if ( ! $img->supports_mime_type( $mime_type ) ) {
					continue;
				}

				$file = wp_tempnam();
				$ret  = wp_save_image_file( $file, $img, $mime_type, 1 );
				$this->assertNotEmpty( $ret );
				$this->assertNotWPError( $ret );
				$this->assertSame( $mime_type, $this->get_mime_type( $ret['path'] ) );

				// Clean up.
				unlink( $file );
				unlink( $ret['path'] );
			}

			// Clean up.
			unset( $img );
		}
	}

	/**
	 * Test that a passed mime type overrides the extension in the filename
	 *
	 * @ticket 6821
	 * @requires extension fileinfo
	 */
	public function test_mime_overrides_filename() {
		$classes = $this->get_image_editor_engine_classes();

		// Test each image editor engine.
		foreach ( $classes as $class ) {
			$img    = new $class( DIR_TESTDATA . '/images/canola.jpg' );
			$loaded = $img->load();

			// Save the file.
			$mime_type = 'image/gif';
			$file      = wp_tempnam( 'tmp.jpg' );
			$ret       = $img->save( $file, $mime_type );

			// Make assertions.
			$this->assertNotEmpty( $ret );
			$this->assertNotWPError( $ret );
			$this->assertSame( $mime_type, $this->get_mime_type( $ret['path'] ) );

			// Clean up.
			unlink( $file );
			unlink( $ret['path'] );
			unset( $img );
		}
	}

	/**
	 * Test that mime types are correctly inferred from file extensions
	 *
	 * @ticket 6821
	 * @requires extension fileinfo
	 */
	public function test_inferred_mime_types() {
		$classes = $this->get_image_editor_engine_classes();

		// Mime types.
		$mime_types = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpe'  => 'image/jpeg',
			'gif'  => 'image/gif',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'unk'  => 'image/jpeg',   // Default, unknown.
		);

		// Test each image editor engine.
		foreach ( $classes as $class ) {
			$img    = new $class( DIR_TESTDATA . '/images/canola.jpg' );
			$loaded = $img->load();

			// Save the image as each file extension, check the mime type.
			$img = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );
			$this->assertNotWPError( $img );

			$temp = get_temp_dir();
			foreach ( $mime_types as $ext => $mime_type ) {
				if ( ! $img->supports_mime_type( $mime_type ) ) {
					continue;
				}

				$file = wp_unique_filename( $temp, uniqid() . ".$ext" );
				$ret  = $img->save( trailingslashit( $temp ) . $file );
				$this->assertNotEmpty( $ret );
				$this->assertNotWPError( $ret );
				$this->assertSame( $mime_type, $this->get_mime_type( $ret['path'] ) );
				unlink( $ret['path'] );
			}

			// Clean up.
			unset( $img );
		}
	}

	/**
	 * Try loading a directory
	 *
	 * @ticket 17814
	 * @expectedDeprecated wp_load_image
	 */
	public function test_load_directory() {

		// First, test with deprecated wp_load_image function.
		$editor1 = wp_load_image( DIR_TESTDATA );
		$this->assertIsString( $editor1 );

		$editor2 = wp_get_image_editor( DIR_TESTDATA );
		$this->assertInstanceOf( 'WP_Error', $editor2 );

		$classes = $this->get_image_editor_engine_classes();

		// Then, test with editors.
		foreach ( $classes as $class ) {
			$editor = new $class( DIR_TESTDATA );
			$loaded = $editor->load();

			$this->assertInstanceOf( 'WP_Error', $loaded );
			$this->assertSame( 'error_loading_image', $loaded->get_error_code() );
		}
	}

	/**
	 * Get the available image editor engine class(es).
	 *
	 * @return string[] Available image editor classes; empty array when none are avaialble.
	 */
	private function get_image_editor_engine_classes() {
		$classes = array( 'WP_Image_Editor_GD', 'WP_Image_Editor_Imagick' );

		foreach ( $classes as $key => $class ) {
			if ( ! call_user_func( array( $class, 'test' ) ) ) {
				// If the image editor isn't available, skip it.
				unset( $classes[ $key ] );
			}
		}

		if ( empty( $classes ) ) {
			$this->markTestSkipped( 'Image editor engines WP_Image_Editor_GD and WP_Image_Editor_Imagick are not supported on this system.' );
		}

		return $classes;
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_wp_crop_image_file() {
		$file = wp_crop_image(
			DIR_TESTDATA . '/images/canola.jpg',
			0,
			0,
			100,
			100,
			100,
			100
		);
		$this->assertNotWPError( $file );
		$this->assertFileExists( $file );
		$image = wp_get_image_editor( $file );
		$size  = $image->get_size();
		$this->assertSame( 100, $size['height'] );
		$this->assertSame( 100, $size['width'] );

		unlink( $file );
	}

	/**
	 * @requires function imagejpeg
	 * @requires extension openssl
	 */
	public function test_wp_crop_image_url() {
		$file = wp_crop_image(
			'https://asdftestblog1.files.wordpress.com/2008/04/canola.jpg',
			0,
			0,
			100,
			100,
			100,
			100,
			false,
			DIR_TESTDATA . '/images/' . __FUNCTION__ . '.jpg'
		);

		if ( is_wp_error( $file ) && $file->get_error_code() === 'invalid_image' ) {
			$this->markTestSkipped( 'Tests_Image_Functions::test_wp_crop_image_url() cannot access remote image.' );
		}

		$this->assertNotWPError( $file );
		$this->assertFileExists( $file );
		$image = wp_get_image_editor( $file );
		$size  = $image->get_size();
		$this->assertSame( 100, $size['height'] );
		$this->assertSame( 100, $size['width'] );

		unlink( $file );
	}

	public function test_wp_crop_image_file_not_exist() {
		$file = wp_crop_image(
			DIR_TESTDATA . '/images/canoladoesnotexist.jpg',
			0,
			0,
			100,
			100,
			100,
			100
		);
		$this->assertInstanceOf( 'WP_Error', $file );
	}

	/**
	 * @requires extension openssl
	 */
	public function test_wp_crop_image_url_not_exist() {
		$file = wp_crop_image(
			'https://asdftestblog1.files.wordpress.com/2008/04/canoladoesnotexist.jpg',
			0,
			0,
			100,
			100,
			100,
			100
		);
		$this->assertInstanceOf( 'WP_Error', $file );
	}

	function mock_image_editor( $editors ) {
		return array( 'WP_Image_Editor_Mock' );
	}

	/**
	 * @ticket 23325
	 */
	public function test_wp_crop_image_error_on_saving() {
		WP_Image_Editor_Mock::$save_return = new WP_Error();
		add_filter( 'wp_image_editors', array( $this, 'mock_image_editor' ) );

		$file = wp_crop_image(
			DIR_TESTDATA . '/images/canola.jpg',
			0,
			0,
			100,
			100,
			100,
			100
		);
		$this->assertInstanceOf( 'WP_Error', $file );

		remove_filter( 'wp_image_editors', array( $this, 'mock_image_editor' ) );
		WP_Image_Editor_Mock::$save_return = array();
	}

	/**
	 * @ticket 31050
	 */
	public function test_wp_generate_attachment_metadata_pdf() {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'application/pdf' ) ) ) {
			$this->markTestSkipped( 'Rendering PDFs is not supported on this system.' );
		}

		$orig_file = DIR_TESTDATA . '/images/wordpress-gsoc-flyer.pdf';
		$test_file = get_temp_dir() . 'wordpress-gsoc-flyer.pdf';
		copy( $orig_file, $test_file );

		$editor = wp_get_image_editor( $test_file );
		if ( is_wp_error( $editor ) ) {
			$this->markTestSkipped( $editor->get_error_message() );
		}

		$attachment_id = $this->factory->attachment->create_object(
			$test_file,
			0,
			array(
				'post_mime_type' => 'application/pdf',
			)
		);

		$this->assertNotEmpty( $attachment_id );

		$expected = array(
			'sizes' => array(
				'full'      => array(
					'file'      => 'wordpress-gsoc-flyer-pdf.jpg',
					'width'     => 1088,
					'height'    => 1408,
					'mime-type' => 'image/jpeg',
				),
				'medium'    => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-232x300.jpg',
					'width'     => 232,
					'height'    => 300,
					'mime-type' => 'image/jpeg',
				),
				'large'     => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-791x1024.jpg',
					'width'     => 791,
					'height'    => 1024,
					'mime-type' => 'image/jpeg',
				),
				'thumbnail' => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-116x150.jpg',
					'width'     => 116,
					'height'    => 150,
					'mime-type' => 'image/jpeg',
				),
			),
		);

		$metadata = wp_generate_attachment_metadata( $attachment_id, $test_file );
		$this->assertSame( $expected, $metadata );

		unlink( $test_file );
		$temp_dir = get_temp_dir();
		foreach ( $metadata['sizes'] as $size ) {
			unlink( $temp_dir . $size['file'] );
		}
	}

	/**
	 * Crop setting for PDF.
	 *
	 * @ticket 43226
	 */
	public function test_crop_setting_for_pdf() {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'application/pdf' ) ) ) {
			$this->markTestSkipped( 'Rendering PDFs is not supported on this system.' );
		}

		update_option( 'medium_crop', 1 );

		$orig_file = DIR_TESTDATA . '/images/wordpress-gsoc-flyer.pdf';
		$test_file = get_temp_dir() . 'wordpress-gsoc-flyer.pdf';
		copy( $orig_file, $test_file );

		$editor = wp_get_image_editor( $test_file );
		if ( is_wp_error( $editor ) ) {
			$this->markTestSkipped( $editor->get_error_message() );
		}

		$attachment_id = $this->factory->attachment->create_object(
			$test_file,
			0,
			array(
				'post_mime_type' => 'application/pdf',
			)
		);

		$this->assertNotEmpty( $attachment_id );

		$expected = array(
			'sizes' => array(
				'full'      => array(
					'file'      => 'wordpress-gsoc-flyer-pdf.jpg',
					'width'     => 1088,
					'height'    => 1408,
					'mime-type' => 'image/jpeg',
				),
				'medium'    => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-300x300.jpg',
					'width'     => 300,
					'height'    => 300,
					'mime-type' => 'image/jpeg',
				),
				'large'     => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-791x1024.jpg',
					'width'     => 791,
					'height'    => 1024,
					'mime-type' => 'image/jpeg',
				),
				'thumbnail' => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-116x150.jpg',
					'width'     => 116,
					'height'    => 150,
					'mime-type' => 'image/jpeg',
				),
			),
		);

		$metadata = wp_generate_attachment_metadata( $attachment_id, $test_file );
		$this->assertSame( $expected, $metadata );

		unlink( $test_file );
		foreach ( $metadata['sizes'] as $size ) {
			unlink( get_temp_dir() . $size['file'] );
		}
	}

	/**
	 * @ticket 39231
	 */
	public function test_fallback_intermediate_image_sizes() {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'application/pdf' ) ) ) {
			$this->markTestSkipped( 'Rendering PDFs is not supported on this system.' );
		}

		$orig_file = DIR_TESTDATA . '/images/wordpress-gsoc-flyer.pdf';
		$test_file = get_temp_dir() . 'wordpress-gsoc-flyer.pdf';
		copy( $orig_file, $test_file );

		$editor = wp_get_image_editor( $test_file );
		if ( is_wp_error( $editor ) ) {
			$this->markTestSkipped( $editor->get_error_message() );
		}

		$attachment_id = $this->factory->attachment->create_object(
			$test_file,
			0,
			array(
				'post_mime_type' => 'application/pdf',
			)
		);

		$this->assertNotEmpty( $attachment_id );

		add_image_size( 'test-size', 100, 100 );
		add_filter( 'fallback_intermediate_image_sizes', array( $this, 'filter_fallback_intermediate_image_sizes' ), 10, 2 );

		$expected = array(
			'file'      => 'wordpress-gsoc-flyer-pdf-77x100.jpg',
			'width'     => 77,
			'height'    => 100,
			'mime-type' => 'image/jpeg',
		);

		$metadata = wp_generate_attachment_metadata( $attachment_id, $test_file );
		$this->assertArrayHasKey( 'test-size', $metadata['sizes'], 'The `test-size` was not added to the metadata.' );
		$this->assertSame( $metadata['sizes']['test-size'], $expected );

		remove_image_size( 'test-size' );
		remove_filter( 'fallback_intermediate_image_sizes', array( $this, 'filter_fallback_intermediate_image_sizes' ), 10 );

		unlink( $test_file );
		$temp_dir = get_temp_dir();
		foreach ( $metadata['sizes'] as $size ) {
			unlink( $temp_dir . $size['file'] );
		}
	}

	function filter_fallback_intermediate_image_sizes( $fallback_sizes, $metadata ) {
		// Add the 'test-size' to the list of fallback sizes.
		$fallback_sizes[] = 'test-size';

		return $fallback_sizes;
	}

	/**
	 * Test PDF preview doesn't overwrite existing JPEG.
	 *
	 * @ticket 39875
	 */
	public function test_pdf_preview_doesnt_overwrite_existing_jpeg() {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'application/pdf' ) ) ) {
			$this->markTestSkipped( 'Rendering PDFs is not supported on this system.' );
		}

		$temp_dir = get_temp_dir();

		// Dummy JPEGs.
		$jpg1_path = $temp_dir . 'test.jpg'; // Straight.
		file_put_contents( $jpg1_path, 'asdf' );
		$jpg2_path = $temp_dir . 'test-pdf.jpg'; // With PDF marker.
		file_put_contents( $jpg2_path, 'fdsa' );

		// PDF with same name as JPEG.
		$pdf_path = $temp_dir . 'test.pdf';
		copy( DIR_TESTDATA . '/images/wordpress-gsoc-flyer.pdf', $pdf_path );

		$editor = wp_get_image_editor( $pdf_path );
		if ( is_wp_error( $editor ) ) {
			$this->markTestSkipped( $editor->get_error_message() );
		}

		$attachment_id = $this->factory->attachment->create_object(
			$pdf_path,
			0,
			array(
				'post_mime_type' => 'application/pdf',
			)
		);

		$metadata     = wp_generate_attachment_metadata( $attachment_id, $pdf_path );
		$preview_path = $temp_dir . $metadata['sizes']['full']['file'];

		// PDF preview didn't overwrite PDF.
		$this->assertNotEquals( $pdf_path, $preview_path );
		// PDF preview didn't overwrite JPG with same name.
		$this->assertNotEquals( $jpg1_path, $preview_path );
		$this->assertSame( 'asdf', file_get_contents( $jpg1_path ) );
		// PDF preview didn't overwrite PDF preview with same name.
		$this->assertNotEquals( $jpg2_path, $preview_path );
		$this->assertSame( 'fdsa', file_get_contents( $jpg2_path ) );

		// Cleanup.
		unlink( $jpg1_path );
		unlink( $jpg2_path );
		unlink( $pdf_path );
		foreach ( $metadata['sizes'] as $size ) {
			unlink( $temp_dir . $size['file'] );
		}
	}
}
