<?php

require_once __DIR__ . '/base.php';

/**
 * Test the WP_Image_Editor base class
 *
 * @group image
 * @group media
 */
class Tests_Image_Editor extends WP_Image_UnitTestCase {
	public $editor_engine = 'WP_Image_Editor_Mock';

	/**
	 * Setup test fixture
	 */
	public function set_up() {
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';

		require_once DIR_TESTDATA . '/../includes/mock-image-editor.php';

		// This needs to come after the mock image editor class is loaded.
		parent::set_up();
	}

	/**
	 * Test wp_get_image_editor() where load returns true
	 *
	 * @ticket 6821
	 */
	public function test_get_editor_load_returns_true() {
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );

		$this->assertInstanceOf( 'WP_Image_Editor_Mock', $editor );
	}

	/**
	 * Test wp_get_image_editor() where load returns false
	 *
	 * @ticket 6821
	 */
	public function test_get_editor_load_returns_false() {
		WP_Image_Editor_Mock::$load_return = new WP_Error();

		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );

		$this->assertInstanceOf( 'WP_Error', $editor );

		WP_Image_Editor_Mock::$load_return = true;
	}

	/**
	 * Return integer of 95 for testing.
	 */
	public function return_integer_95() {
		return 95;
	}

	/**
	 * Return integer of 100 for testing.
	 */
	public function return_integer_100() {
		return 100;
	}

	/**
	 * Test test_quality
	 *
	 * @ticket 6821
	 */
	public function test_set_quality() {

		// Get an editor.
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );
		$editor->set_mime_type( 'image/jpeg' ); // Ensure mime-specific filters act properly.

		// Check default value.
		$this->assertSame( 82, $editor->get_quality() );

		// Ensure the quality filters do not have precedence if created after editor instantiation.
		$func_100_percent = array( $this, 'return_integer_100' );
		add_filter( 'wp_editor_set_quality', $func_100_percent );
		$this->assertSame( 82, $editor->get_quality() );

		$func_95_percent = array( $this, 'return_integer_95' );
		add_filter( 'jpeg_quality', $func_95_percent );
		$this->assertSame( 82, $editor->get_quality() );

		// Ensure set_quality() works and overrides the filters.
		$this->assertTrue( $editor->set_quality( 75 ) );
		$this->assertSame( 75, $editor->get_quality() );

		// Get a new editor to clear default quality state.
		unset( $editor );
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );
		$editor->set_mime_type( 'image/jpeg' ); // Ensure mime-specific filters act properly.

		// Ensure jpeg_quality filter applies if it exists before editor instantiation.
		$this->assertSame( 95, $editor->get_quality() );

		// Get a new editor to clear jpeg_quality state.
		remove_filter( 'jpeg_quality', $func_95_percent );
		unset( $editor );
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );

		// Ensure wp_editor_set_quality filter applies if it exists before editor instantiation.
		$this->assertSame( 100, $editor->get_quality() );

		// Clean up.
		remove_filter( 'wp_editor_set_quality', $func_100_percent );
	}

	/**
	 * Test test_quality when converting image
	 *
	 * @ticket 6821
	 */
	public function test_set_quality_with_image_conversion() {
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/test-image.png' );
		$editor->set_mime_type( 'image/png' ); // Ensure mime-specific filters act properly.

		// Set conversions for uploaded images.
		add_filter( 'image_editor_output_format', array( $this, 'image_editor_output_formats' ) );

		// Quality setting for the source image. For PNG the fallback default of 82 is used.
		$this->assertSame( 82, $editor->get_quality(), 'Default quality setting is 82.' );

		// Quality should change to the output format's value.
		// A PNG image will be converted to WEBP whose quialty should be 86.
		$editor->save();
		$this->assertSame( 86, $editor->get_quality(), 'Output image format is WEBP. Quality setting for it should be 86.' );

		// Removing PNG to WEBP conversion on save. Quality setting should reset to the default.
		remove_filter( 'image_editor_output_format', array( $this, 'image_editor_output_formats' ) );
		$editor->save();
		$this->assertSame( 82, $editor->get_quality(), 'After removing image conversion quality setting should reset to the default of 82.' );

		unset( $editor );

		// Set conversions for uploaded images.
		add_filter( 'image_editor_output_format', array( $this, 'image_editor_output_formats' ) );
		// Change the quality values.
		add_filter( 'wp_editor_set_quality', array( $this, 'image_editor_change_quality' ), 10, 2 );

		// Get a new editor to clear quality state.
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/test-image.jpg' );
		$editor->set_mime_type( 'image/jpeg' );

		$this->assertSame( 56, $editor->get_quality(), 'Filtered default quality for JPEG is 56.' );

		// Quality should change to the output format's value as filtered above.
		// A JPEG image will be converted to WEBP whose quialty should be 42.
		$editor->save();
		$this->assertSame( 42, $editor->get_quality(), 'Image conversion from JPEG to WEBP. Filtered WEBP quality shoild be 42.' );

		// After removing the conversion the quality setting should reset to the filtered value for the original image type, JPEG.
		remove_filter( 'image_editor_output_format', array( $this, 'image_editor_output_formats' ) );
		$editor->save();
		$this->assertSame(
			56,
			$editor->get_quality(),
			'After removing image conversion the quality setting should reset to the filtered value for JPEG, 56.'
		);

		remove_filter( 'wp_editor_set_quality', array( $this, 'image_editor_change_quality' ) );
	}

	/**
	 * Changes the output format when editing images. PNG and JPEG files
	 * will be converted to WEBP (if the image editor in PHP supports it).
	 *
	 * @param array $formats
	 *
	 * @return array
	 */
	public function image_editor_output_formats( $formats ) {
		$formats['image/png']  = 'image/webp';
		$formats['image/jpeg'] = 'image/webp';
		return $formats;
	}

	/**
	 * Changes the quality according to the mime-type.
	 *
	 * @param int    $quality   Default quality.
	 * @param string $mime_type Image mime-type.
	 * @return int The changed quality.
	 */
	public function image_editor_change_quality( $quality, $mime_type ) {
		if ( 'image/jpeg' === $mime_type ) {
			return 56;
		} elseif ( 'image/webp' === $mime_type ) {
			return 42;
		} else {
			return 30;
		}
	}

	/**
	 * Test generate_filename
	 *
	 * @ticket 6821
	 */
	public function test_generate_filename() {

		// Get an editor.
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );

		$property = new ReflectionProperty( $editor, 'size' );
		$property->setAccessible( true );
		$property->setValue(
			$editor,
			array(
				'height' => 50,
				'width'  => 100,
			)
		);

		// Test with no parameters.
		$this->assertSame( 'canola-100x50.jpg', wp_basename( $editor->generate_filename() ) );

		// Test with a suffix only.
		$this->assertSame( 'canola-new.jpg', wp_basename( $editor->generate_filename( 'new' ) ) );

		// Test with a destination dir only.
		$this->assertSame( trailingslashit( realpath( get_temp_dir() ) ), trailingslashit( realpath( dirname( $editor->generate_filename( null, get_temp_dir() ) ) ) ) );

		// Test with a suffix only.
		$this->assertSame( 'canola-100x50.png', wp_basename( $editor->generate_filename( null, null, 'png' ) ) );

		// Combo!
		$this->assertSame( trailingslashit( realpath( get_temp_dir() ) ) . 'canola-new.png', $editor->generate_filename( 'new', realpath( get_temp_dir() ), 'png' ) );

		// Test with a stream destination.
		$this->assertSame( 'file://testing/path/canola-100x50.jpg', $editor->generate_filename( null, 'file://testing/path' ) );
	}

	/**
	 * Test get_size
	 *
	 * @ticket 6821
	 */
	public function test_get_size() {

		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );

		// Size should be false by default.
		$this->assertNull( $editor->get_size() );

		// Set a size.
		$size     = array(
			'height' => 50,
			'width'  => 100,
		);
		$property = new ReflectionProperty( $editor, 'size' );
		$property->setAccessible( true );
		$property->setValue( $editor, $size );

		$this->assertSame( $size, $editor->get_size() );
	}

	/**
	 * Test get_suffix
	 *
	 * @ticket 6821
	 */
	public function test_get_suffix() {
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );

		// Size should be false by default.
		$this->assertFalse( $editor->get_suffix() );

		// Set a size.
		$size     = array(
			'height' => 50,
			'width'  => 100,
		);
		$property = new ReflectionProperty( $editor, 'size' );
		$property->setAccessible( true );
		$property->setValue( $editor, $size );

		$this->assertSame( '100x50', $editor->get_suffix() );
	}

	/**
	 * Test wp_get_webp_info.
	 *
	 * @ticket 35725
	 * @dataProvider _test_wp_get_webp_info
	 *
	 */
	public function test_wp_get_webp_info( $file, $expected ) {
		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) || ! $editor->supports_mime_type( 'image/webp' ) ) {
			$this->markTestSkipped( sprintf( 'No WebP support in the editor engine %s on this system.', $this->editor_engine ) );
		}

		$file_data = wp_get_webp_info( $file );
		$this->assertSame( $expected, $file_data );
	}

	/**
	 * Data provider for test_wp_get_webp_info().
	 */
	public function _test_wp_get_webp_info() {
		return array(
			// Standard JPEG.
			array(
				DIR_TESTDATA . '/images/test-image.jpg',
				array(
					'width'  => false,
					'height' => false,
					'type'   => false,
				),
			),
			// Standard GIF.
			array(
				DIR_TESTDATA . '/images/test-image.gif',
				array(
					'width'  => false,
					'height' => false,
					'type'   => false,
				),
			),
			// Animated WebP.
			array(
				DIR_TESTDATA . '/images/webp-animated.webp',
				array(
					'width'  => 100,
					'height' => 100,
					'type'   => 'animated-alpha',
				),
			),
			// Lossless WebP.
			array(
				DIR_TESTDATA . '/images/webp-lossless.webp',
				array(
					'width'  => 1200,
					'height' => 675,
					'type'   => 'lossless',
				),
			),
			// Lossy WebP.
			array(
				DIR_TESTDATA . '/images/webp-lossy.webp',
				array(
					'width'  => 1200,
					'height' => 675,
					'type'   => 'lossy',
				),
			),
			// Transparent WebP.
			array(
				DIR_TESTDATA . '/images/webp-transparent.webp',
				array(
					'width'  => 1200,
					'height' => 675,
					'type'   => 'animated-alpha',
				),
			),
		);
	}

}
