<?php

/**
 * @group image
 * @group media
 * @group upload
 */
class Tests_Image_Functions extends WP_UnitTestCase {

	/**
	 * Includes the required files.
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
	 * Gets the available image editor engine classes.
	 *
	 * @return string[] Available image editor classes; empty array when none are available.
	 */
	private function get_image_editor_engine_classes() {
		$classes = array( 'WP_Image_Editor_GD', 'WP_Image_Editor_Imagick' );

		foreach ( $classes as $key => $class ) {
			if ( ! call_user_func( array( $class, 'test' ) ) ) {
				// If the image editor isn't available, skip it.
				unset( $classes[ $key ] );
			}
		}

		return $classes;
	}

	/**
	 * Data provider with the available image editor engine classes.
	 *
	 * @return array
	 */
	public function data_image_editor_engine_classes() {
		return $this->text_array_to_dataprovider( $this->get_image_editor_engine_classes() );
	}

	/**
	 * Gets the MIME type of a file.
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

	/**
	 * @dataProvider data_file_is_valid_image_positive
	 *
	 * @covers ::file_is_valid_image
	 * @covers ::wp_getimagesize
	 *
	 * @param string $file File name.
	 */
	public function test_file_is_valid_image_positive( $file ) {
		$this->assertTrue(
			file_is_valid_image( DIR_TESTDATA . '/images/' . $file ),
			"file_is_valid_image( '$file' ) should return true."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_file_is_valid_image_positive() {
		// These are all image files recognized by PHP.
		$files = array(
			'test-image-cmyk.jpg',
			'test-image-grayscale.jpg',
			'test-image.bmp',
			'test-image.gif',
			'test-image.png',
			'test-image.tiff',
			'test-image-lzw.tiff',
			'test-image.jp2',
			'test-image.psd',
			'test-image-zip.tiff',
			'test-image.jpg',
			'test-image.ico',
			'webp-animated.webp',
			'webp-lossless.webp',
			'webp-lossy.webp',
			'webp-transparent.webp',
		);

		return $this->text_array_to_dataprovider( $files );
	}

	/**
	 * @dataProvider data_file_is_valid_image_negative
	 *
	 * @covers ::file_is_valid_image
	 * @covers ::wp_getimagesize
	 *
	 * @param string $file File name.
	 */
	public function test_file_is_valid_image_negative( $file ) {
		$this->assertFalse(
			file_is_valid_image( DIR_TESTDATA . '/images/' . $file ),
			"file_is_valid_image( '$file' ) should return false."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_file_is_valid_image_negative() {
		// These are actually image files but aren't recognized or usable by PHP.
		$files = array(
			'test-image.pct',
			'test-image.tga',
			'test-image.sgi',
		);

		return $this->text_array_to_dataprovider( $files );
	}

	/**
	 * @dataProvider data_file_is_displayable_image_positive
	 *
	 * @covers ::file_is_displayable_image
	 *
	 * @param string $file File name.
	 */
	public function test_file_is_displayable_image_positive( $file ) {
		$this->assertTrue(
			file_is_displayable_image( DIR_TESTDATA . '/images/' . $file ),
			"file_is_displayable_image( '$file' ) should return true."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_file_is_displayable_image_positive() {
		// These are all usable in typical web browsers.
		$files = array(
			'test-image.gif',
			'test-image.png',
			'test-image.jpg',
			'test-image.ico',
		);

		// Add WebP images if the image editor supports them.
		$file   = DIR_TESTDATA . '/images/test-image.webp';
		$editor = wp_get_image_editor( $file );

		if ( ! is_wp_error( $editor ) && $editor->supports_mime_type( 'image/webp' ) ) {
			$files[] = 'webp-animated.webp';
			$files[] = 'webp-lossless.webp';
			$files[] = 'webp-lossy.webp';
			$files[] = 'webp-transparent.webp';
		}

		return $this->text_array_to_dataprovider( $files );
	}

	/**
	 * @dataProvider data_file_is_displayable_image_negative
	 *
	 * @covers ::file_is_displayable_image
	 *
	 * @param string $file File name.
	 */
	public function test_file_is_displayable_image_negative( $file ) {
		$this->assertFalse(
			file_is_displayable_image( DIR_TESTDATA . '/images/' . $file ),
			"file_is_displayable_image( '$file' ) should return false."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_file_is_displayable_image_negative() {
		// These are image files but aren't suitable for web pages because of compatibility or size issues.
		$files = array(
			// 'test-image-cmyk.jpg',      Allowed in r9727.
			// 'test-image-grayscale.jpg', Allowed in r9727.
			// 'test-image.bmp',           Allowed in r28589.
			'test-image.pct',
			'test-image.tga',
			'test-image.sgi',
			'test-image.tiff',
			'test-image-lzw.tiff',
			'test-image.jp2',
			'test-image.psd',
			'test-image-zip.tiff',
		);

		return $this->text_array_to_dataprovider( $files );
	}

	/**
	 * @ticket 50833
	 * @requires extension gd
	 */
	public function test_is_gd_image_valid_types() {
		$this->assertTrue( is_gd_image( imagecreate( 5, 5 ) ) );
	}

	/**
	 * @ticket 50833
	 */
	public function test_is_gd_image_invalid_types() {
		$this->assertFalse( is_gd_image( new stdClass() ) );
		$this->assertFalse( is_gd_image( array() ) );
		$this->assertFalse( is_gd_image( null ) );

		$handle = fopen( __FILE__, 'r' );
		$this->assertFalse( is_gd_image( $handle ) );
		fclose( $handle );
	}

	/**
	 * Tests wp_save_image_file() and mime types.
	 *
	 * @dataProvider data_wp_save_image_file
	 *
	 * @ticket 6821
	 * @covers ::wp_save_image_file
	 * @requires extension fileinfo
	 *
	 * @param string $class_name Name of the image editor engine class to be tested.
	 * @param string $mime_type  The mime type to test.
	 */
	public function test_wp_save_image_file( $class_name, $mime_type ) {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$img    = new $class_name( DIR_TESTDATA . '/images/canola.jpg' );
		$loaded = $img->load();

		$this->assertNotWPError( $loaded, 'Image failed to load - WP_Error returned.' );

		if ( ! $img->supports_mime_type( $mime_type ) ) {
			$this->markTestSkipped(
				sprintf(
					'The %s mime type is not supported by the %s engine.',
					$mime_type,
					str_replace( 'WP_Image_Editor_', '', $class_name )
				)
			);
		}

		// Save the file.
		$file = wp_tempnam();
		$ret  = wp_save_image_file( $file, $img, $mime_type, 1 );

		// Make assertions.
		$this->assertNotEmpty( $ret, 'Image failed to save - "empty" response returned.' );
		$this->assertNotWPError( $ret, 'Image failed to save - WP_Error returned.' );
		$this->assertSame( $mime_type, $this->get_mime_type( $ret['path'] ), 'Mime type of the saved image does not match.' );

		// Clean up.
		unlink( $file );
		unlink( $ret['path'] );
		unset( $img );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_wp_save_image_file() {
		$classes = $this->get_image_editor_engine_classes();

		// Mime types.
		$mime_types = array(
			'image/jpeg',
			'image/gif',
			'image/png',
		);

		// Include WebP in tests when platform supports it.
		if ( function_exists( 'imagewebp' ) ) {
			$mime_types[] = 'image/webp';
		}

		$data = array();

		foreach ( $classes as $class ) {
			foreach ( $mime_types as $mime_type ) {
				$data[ $class . '; ' . $mime_type ] = array(
					'class_name' => $class,
					'mime_type'  => $mime_type,
				);
			}
		}

		return $data;
	}

	/**
	 * Tests that a passed mime type overrides the extension in the filename when saving an image.
	 *
	 * @dataProvider data_image_editor_engine_classes
	 *
	 * @ticket 6821
	 * @covers WP_Image_Editor::get_mime_type
	 * @covers WP_Image_Editor::get_output_format
	 * @requires extension fileinfo
	 *
	 * @param string $class_name Name of the image editor engine class to be tested.
	 */
	public function test_mime_overrides_filename_when_saving_an_image( $class_name ) {
		$img    = new $class_name( DIR_TESTDATA . '/images/canola.jpg' );
		$loaded = $img->load();

		$this->assertNotWPError( $loaded, 'Image failed to load - WP_Error returned.' );

		// Save the file.
		$mime_type = 'image/gif';
		$file      = wp_tempnam( 'tmp.jpg' );
		$ret       = $img->save( $file, $mime_type );

		// Make assertions.
		$this->assertNotEmpty( $ret, 'Image failed to save - "empty" response returned.' );
		$this->assertNotWPError( $ret, 'Image failed to save - WP_Error returned.' );
		$this->assertSame( $mime_type, $this->get_mime_type( $ret['path'] ), 'Mime type of the saved image did not override file name.' );

		// Clean up.
		unlink( $file );
		unlink( $ret['path'] );
		unset( $img );
	}

	/**
	 * Tests that mime types are correctly inferred from file extensions when saving an image.
	 *
	 * @dataProvider data_inferred_mime_types_when_saving_an_image
	 *
	 * @ticket 6821
	 * @covers WP_Image_Editor::get_mime_type
	 * @covers WP_Image_Editor::get_output_format
	 * @requires extension fileinfo
	 *
	 * @param string $class_name Name of the image editor engine class to be tested.
	 * @param string $extension  File extension.
	 * @param string $mime_type  The mime type to test.
	 */
	public function test_inferred_mime_types_when_saving_an_image( $class_name, $extension, $mime_type ) {
		$img    = new $class_name( DIR_TESTDATA . '/images/canola.jpg' );
		$loaded = $img->load();

		$this->assertNotWPError( $loaded, 'Image failed to load - WP_Error returned.' );

		if ( ! $img->supports_mime_type( $mime_type ) ) {
			$this->markTestSkipped(
				sprintf(
					'The %s mime type is not supported by the %s engine.',
					$mime_type,
					str_replace( 'WP_Image_Editor_', '', $class_name )
				)
			);
		}

		// Save the file.
		$temp = get_temp_dir();
		$file = wp_unique_filename( $temp, uniqid() . ".$extension" );
		$ret  = $img->save( trailingslashit( $temp ) . $file );

		// Make assertions.
		$this->assertNotEmpty( $ret, 'Image failed to save - "empty" response returned.' );
		$this->assertNotWPError( $ret, 'Image failed to save - WP Error returned.' );
		$this->assertSame( $mime_type, $this->get_mime_type( $ret['path'] ), 'Mime type of the saved image was not inferred correctly.' );

		// Clean up.
		unlink( $ret['path'] );
		unset( $img );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_inferred_mime_types_when_saving_an_image() {
		$classes = $this->get_image_editor_engine_classes();

		// Mime types.
		$mime_types = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpe'  => 'image/jpeg',
			'gif'  => 'image/gif',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'unk'  => 'image/jpeg', // Default, unknown.
		);

		$data = array();

		foreach ( $classes as $class ) {
			foreach ( $mime_types as $ext => $mime_type ) {
				$data[ $class . '; Extension: ' . $ext . '; Mime type: ' . $mime_type ] = array(
					'class_name' => $class,
					'extension'  => $ext,
					'mime_type'  => $mime_type,
				);
			}
		}

		return $data;
	}

	/**
	 * Tests that the deprecated wp_load_image() function fails when loading a directory.
	 *
	 * @ticket 17814
	 * @covers ::wp_load_image
	 * @expectedDeprecated wp_load_image
	 */
	public function test_wp_load_image_should_fail_with_error_message_when_loading_a_directory() {
		$editor = wp_load_image( DIR_TESTDATA );
		$this->assertIsString( $editor );
	}

	/**
	 * Tests that the wp_get_image_editor() function fails when loading a directory.
	 *
	 * @ticket 17814
	 * @covers ::wp_get_image_editor
	 */
	public function test_wp_get_image_editor_should_fail_with_wp_error_object_when_loading_a_directory() {
		$editor = wp_get_image_editor( DIR_TESTDATA );
		$this->assertInstanceOf( 'WP_Error', $editor );
	}

	/**
	 * Tests that the load() method in an image editor class fails when loading a directory.
	 *
	 * @dataProvider data_image_editor_engine_classes
	 *
	 * @ticket 17814
	 * @covers WP_Image_Editor_GD::load
	 * @covers WP_Image_Editor_Imagick::load
	 *
	 * @param string $class_name Name of the image editor engine class to be tested.
	 */
	public function test_image_editor_classes_should_fail_with_wp_error_object_when_loading_a_directory( $class_name ) {
		$editor = new $class_name( DIR_TESTDATA );
		$loaded = $editor->load();

		$this->assertInstanceOf( 'WP_Error', $loaded, 'Loading a directory did not result in a WP_Error.' );
		$this->assertSame( 'error_loading_image', $loaded->get_error_code(), 'Error code from WP_Error did not match expectation.' );
	}

	/**
	 * @covers ::wp_crop_image
	 * @requires function imagejpeg
	 */
	public function test_wp_crop_image_with_file() {
		$file = wp_crop_image(
			DIR_TESTDATA . '/images/canola.jpg',
			0,
			0,
			100,
			100,
			100,
			100
		);
		$this->assertNotWPError( $file, 'Cropping the image resulted in a WP_Error.' );
		$this->assertFileExists( $file, "The file $file does not exist." );

		$image = wp_get_image_editor( $file );
		$size  = $image->get_size();

		$this->assertSame( 100, $size['height'], 'Cropped image height does not match expectation.' );
		$this->assertSame( 100, $size['width'], 'Cropped image width does not match expectation.' );

		unlink( $file );
	}

	/**
	 * @covers ::wp_crop_image
	 * @requires function imagejpeg
	 * @requires extension openssl
	 */
	public function test_wp_crop_image_with_url() {
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

		$this->assertNotWPError( $file, 'Cropping the image resulted in a WP_Error.' );
		$this->assertFileExists( $file, "The file $file does not exist." );

		$image = wp_get_image_editor( $file );
		$size  = $image->get_size();

		$this->assertSame( 100, $size['height'], 'Cropped image height does not match expectation.' );
		$this->assertSame( 100, $size['width'], 'Cropped image width does not match expectation.' );

		unlink( $file );
	}

	/**
	 * @covers ::wp_crop_image
	 */
	public function test_wp_crop_image_should_fail_with_wp_error_object_if_file_does_not_exist() {
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
	 * @covers ::wp_crop_image
	 * @requires extension openssl
	 */
	public function test_wp_crop_image_should_fail_with_wp_error_object_if_url_does_not_exist() {
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

	/**
	 * @ticket 23325
	 * @covers ::wp_crop_image
	 */
	public function test_wp_crop_image_should_fail_with_wp_error_object_if_there_was_an_error_on_saving() {
		WP_Image_Editor_Mock::$save_return = new WP_Error();

		add_filter(
			'wp_image_editors',
			static function( $editors ) {
				return array( 'WP_Image_Editor_Mock' );
			}
		);

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

		WP_Image_Editor_Mock::$save_return = array();
	}

	/**
	 * @ticket 55403
	 * @covers ::wp_crop_image
	 */
	public function test_wp_crop_image_should_return_correct_file_extension_if_output_format_was_modified() {
		add_filter(
			'image_editor_output_format',
			static function() {
				return array_fill_keys( array( 'image/jpg', 'image/jpeg', 'image/png' ), 'image/webp' );
			}
		);

		$file = wp_crop_image(
			DIR_TESTDATA . '/images/canola.jpg',
			0,
			0,
			100,
			100,
			100,
			100
		);

		$this->assertNotWPError( $file, 'Cropping the image resulted in a WP_Error.' );
		$this->assertFileExists( $file, "The file $file does not exist." );

		unlink( $file );
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

		$attachment_id = self::factory()->attachment->create_object(
			$test_file,
			0,
			array(
				'post_mime_type' => 'application/pdf',
			)
		);

		$this->assertNotEmpty( $attachment_id );

		$temp_dir = get_temp_dir();

		$metadata = wp_generate_attachment_metadata( $attachment_id, $test_file );

		$expected = array(
			'sizes'    => array(
				'full'      => array(
					'file'      => 'wordpress-gsoc-flyer-pdf.jpg',
					'width'     => 1088,
					'height'    => 1408,
					'mime-type' => 'image/jpeg',
					'filesize'  => wp_filesize( $temp_dir . 'wordpress-gsoc-flyer-pdf.jpg' ),
				),
				'medium'    => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-232x300.jpg',
					'width'     => 232,
					'height'    => 300,
					'mime-type' => 'image/jpeg',
					'filesize'  => wp_filesize( $temp_dir . 'wordpress-gsoc-flyer-pdf-232x300.jpg' ),
				),
				'large'     => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-791x1024.jpg',
					'width'     => 791,
					'height'    => 1024,
					'mime-type' => 'image/jpeg',
					'filesize'  => wp_filesize( $temp_dir . 'wordpress-gsoc-flyer-pdf-791x1024.jpg' ),
				),
				'thumbnail' => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-116x150.jpg',
					'width'     => 116,
					'height'    => 150,
					'mime-type' => 'image/jpeg',
					'filesize'  => wp_filesize( $temp_dir . 'wordpress-gsoc-flyer-pdf-116x150.jpg' ),
				),
			),
			'filesize' => wp_filesize( $test_file ),
		);

		$this->assertSame( $expected, $metadata );

		unlink( $test_file );
		foreach ( $metadata['sizes'] as $size ) {
			unlink( $temp_dir . $size['file'] );
		}
	}

	/**
	 * Tests crop setting for PDF.
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

		$attachment_id = self::factory()->attachment->create_object(
			$test_file,
			0,
			array(
				'post_mime_type' => 'application/pdf',
			)
		);

		$this->assertNotEmpty( $attachment_id );

		$temp_dir = get_temp_dir();

		$metadata = wp_generate_attachment_metadata( $attachment_id, $test_file );

		$expected = array(
			'sizes'    => array(
				'full'      => array(
					'file'      => 'wordpress-gsoc-flyer-pdf.jpg',
					'width'     => 1088,
					'height'    => 1408,
					'mime-type' => 'image/jpeg',
					'filesize'  => wp_filesize( $temp_dir . 'wordpress-gsoc-flyer-pdf.jpg' ),
				),
				'medium'    => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-300x300.jpg',
					'width'     => 300,
					'height'    => 300,
					'mime-type' => 'image/jpeg',
					'filesize'  => wp_filesize( $temp_dir . 'wordpress-gsoc-flyer-pdf-300x300.jpg' ),
				),
				'large'     => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-791x1024.jpg',
					'width'     => 791,
					'height'    => 1024,
					'mime-type' => 'image/jpeg',
					'filesize'  => wp_filesize( $temp_dir . 'wordpress-gsoc-flyer-pdf-791x1024.jpg' ),
				),
				'thumbnail' => array(
					'file'      => 'wordpress-gsoc-flyer-pdf-116x150.jpg',
					'width'     => 116,
					'height'    => 150,
					'mime-type' => 'image/jpeg',
					'filesize'  => wp_filesize( $temp_dir . 'wordpress-gsoc-flyer-pdf-116x150.jpg' ),
				),
			),
			'filesize' => wp_filesize( $test_file ),
		);

		$this->assertSame( $expected, $metadata );

		unlink( $test_file );
		foreach ( $metadata['sizes'] as $size ) {
			unlink( $temp_dir . $size['file'] );
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

		$attachment_id = self::factory()->attachment->create_object(
			$test_file,
			0,
			array(
				'post_mime_type' => 'application/pdf',
			)
		);

		$this->assertNotEmpty( $attachment_id );

		add_image_size( 'test-size', 100, 100 );
		add_filter( 'fallback_intermediate_image_sizes', array( $this, 'filter_fallback_intermediate_image_sizes' ), 10, 2 );

		$metadata = wp_generate_attachment_metadata( $attachment_id, $test_file );

		$temp_dir = get_temp_dir();

		$expected = array(
			'file'      => 'wordpress-gsoc-flyer-pdf-77x100.jpg',
			'width'     => 77,
			'height'    => 100,
			'mime-type' => 'image/jpeg',
			'filesize'  => wp_filesize( $temp_dir . 'wordpress-gsoc-flyer-pdf-77x100.jpg' ),
		);

		// Different environments produce slightly different filesize results.
		$this->assertSame( $metadata['sizes']['test-size'], $expected );

		$this->assertArrayHasKey( 'test-size', $metadata['sizes'], 'The `test-size` was not added to the metadata.' );
		$this->assertSame( $expected, $metadata['sizes']['test-size'] );

		remove_image_size( 'test-size' );
		remove_filter( 'fallback_intermediate_image_sizes', array( $this, 'filter_fallback_intermediate_image_sizes' ), 10 );

		unlink( $test_file );
		foreach ( $metadata['sizes'] as $size ) {
			unlink( $temp_dir . $size['file'] );
		}
	}

	public function filter_fallback_intermediate_image_sizes( $fallback_sizes, $metadata ) {
		// Add the 'test-size' to the list of fallback sizes.
		$fallback_sizes[] = 'test-size';

		return $fallback_sizes;
	}

	/**
	 * Tests that PDF preview does not overwrite existing JPEG.
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

		$attachment_id = self::factory()->attachment->create_object(
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

	/**
	 * Tests that wp_exif_frac2dec() properly handles edge cases
	 * and always returns an int or float, or 0 for failures.
	 *
	 * @param mixed     $fraction The fraction to convert.
	 * @param int|float $expect   The expected result.
	 *
	 * @ticket 54385
	 * @dataProvider data_wp_exif_frac2dec
	 *
	 * @covers ::wp_exif_frac2dec
	 */
	public function test_wp_exif_frac2dec( $fraction, $expect ) {
		$this->assertSame( $expect, wp_exif_frac2dec( $fraction ) );
	}

	/**
	 * Data provider for testing `wp_exif_frac2dec()`.
	 *
	 * @return array
	 */
	public function data_wp_exif_frac2dec() {
		return array(
			'invalid input: null'              => array(
				'fraction' => null,
				'expect'   => 0,
			),
			'invalid input: boolean true'      => array(
				'fraction' => null,
				'expect'   => 0,
			),
			'invalid input: empty array value' => array(
				'fraction' => array(),
				'expect'   => 0,
			),
			'input is already integer'         => array(
				'fraction' => 12,
				'expect'   => 12,
			),
			'input is already float'           => array(
				'fraction' => 10.123,
				'expect'   => 10.123,
			),
			'string input is not a fraction - no slash, not numeric' => array(
				'fraction' => '123notafraction',
				'expect'   => 0,
			),
			'string input is not a fraction - no slash, numeric integer' => array(
				'fraction' => '48',
				'expect'   => 48.0,
			),
			'string input is not a fraction - no slash, numeric integer (integer 0)' => array(
				'fraction' => '0',
				'expect'   => 0.0,
			),
			'string input is not a fraction - no slash, octal numeric integer' => array(
				'fraction' => '010',
				'expect'   => 10.0,
			),
			'string input is not a fraction - no slash, numeric float (float 0)' => array(
				'fraction' => '0.0',
				'expect'   => 0.0,
			),
			'string input is not a fraction - no slash, numeric float (typical fnumber)' => array(
				'fraction' => '4.8',
				'expect'   => 4.8,
			),
			'string input is not a fraction - more than 1 slash with text' => array(
				'fraction' => 'path/to/file',
				'expect'   => 0,
			),
			'string input is not a fraction - more than 1 slash with numbers' => array(
				'fraction' => '1/2/3',
				'expect'   => 0,
			),
			'string input is not a fraction - only a slash' => array(
				'fraction' => '/',
				'expect'   => 0,
			),
			'string input is not a fraction - only slashes' => array(
				'fraction' => '///',
				'expect'   => 0,
			),
			'string input is not a fraction - left/right is not numeric' => array(
				'fraction' => 'path/to',
				'expect'   => 0,
			),
			'string input is not a fraction - left is not numeric' => array(
				'fraction' => 'path/10',
				'expect'   => 0,
			),
			'string input is not a fraction - right is not numeric' => array(
				'fraction' => '0/abc',
				'expect'   => 0,
			),
			'division by zero is prevented 1'  => array(
				'fraction' => '0/0',
				'expect'   => 0,
			),
			'division by zero is prevented 2'  => array(
				'fraction' => '100/0.0',
				'expect'   => 0,
			),
			'typical focal length'             => array(
				'fraction' => '37 mm',
				'expect'   => 0,
			),
			'typical exposure time'            => array(
				'fraction' => '1/350',
				'expect'   => 0.002857142857142857,
			),
			'valid fraction 1'                 => array(
				'fraction' => '50/100',
				'expect'   => 0.5,
			),
			'valid fraction 2'                 => array(
				'fraction' => '25/100',
				'expect'   => .25,
			),
			'valid fraction 3'                 => array(
				'fraction' => '4/2',
				'expect'   => 2,
			),
		);
	}
}
