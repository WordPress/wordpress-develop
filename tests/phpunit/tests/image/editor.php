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
		$editor->reset_output_mime_type();
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
		$editor->reset_output_mime_type();
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
		$this->assertSame( 'canola-100x50-jpg.png', wp_basename( $editor->generate_filename( null, null, 'png' ) ) );

		// Combo!
		$this->assertSame( trailingslashit( realpath( get_temp_dir() ) ) . 'canola-new-jpg.png', $editor->generate_filename( 'new', realpath( get_temp_dir() ), 'png' ) );

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

	/**
	 * Test creating  the original image mime type when the image is uploaded.
	 *
	 * @ticket 55443
	 *
	 * @dataProvider provider_image_with_default_behaviors_during_upload
	 */
	public function it_should_create_the_original_image_mime_type_when_the_image_is_uploaded( $file_location, $expected_mime, $targeted_mime ) {
		$attachment_id = $this->factory->attachment->create_upload_object( $file_location );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayHasKey( 'sources', $properties );
			$this->assertIsArray( $properties['sources'] );
			$this->assertArrayHasKey( $expected_mime, $properties['sources'] );
			$this->assertArrayHasKey( 'filesize', $properties['sources'][ $expected_mime ] );
			$this->assertArrayHasKey( 'file', $properties['sources'][ $expected_mime ] );
			$this->assertArrayHasKey( $targeted_mime, $properties['sources'] );
			$this->assertArrayHasKey( 'filesize', $properties['sources'][ $targeted_mime ] );
			$this->assertArrayHasKey( 'file', $properties['sources'][ $targeted_mime ] );
		}
	}

	/**
	 * Data provider for it_should_create_the_original_image_mime_type_when_the_image_is_uploaded.
	 */
	public function provider_image_with_default_behaviors_during_upload() {
		yield 'JPEG image' => array(
			DIR_TESTDATA . '/images/test-image.jpg',
			'image/jpeg',
			'image/webp',
		);

		yield 'WebP image' => array(
			DIR_TESTDATA . '/images/webp-lossy.webp',
			'image/webp',
			'image/jpeg',
		);
	}

	/**
	 * Test Do not create the sources property if no transform is provided.
	 *
	 * @ticket 55443
	 */
	public function it_should_not_create_the_sources_property_if_no_transform_is_provided() {
		add_filter( 'wp_upload_image_mime_transforms', '__return_empty_array' );

		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayNotHasKey( 'sources', $properties );
		}
	}

	/**
	 * Test creating the sources property when no transform is available.
	 *
	 * @ticket 55443
	 */
	public function it_should_create_the_sources_property_when_no_transform_is_available() {
		add_filter(
			'wp_upload_image_mime_transforms',
			function () {
				return array( 'image/jpeg' => array() );
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayHasKey( 'sources', $properties );
			$this->assertIsArray( $properties['sources'] );
			$this->assertArrayHasKey( 'image/jpeg', $properties['sources'] );
			$this->assertArrayHasKey( 'filesize', $properties['sources']['image/jpeg'] );
			$this->assertArrayHasKey( 'file', $properties['sources']['image/jpeg'] );
			$this->assertArrayNotHasKey( 'image/webp', $properties['sources'] );
		}
	}

	/**
	 * Test not creating the sources property if the mime is not specified on the transforms images.
	 *
	 * @ticket 55443
	 */
	public function it_should_not_create_the_sources_property_if_the_mime_is_not_specified_on_the_transforms_images() {
		add_filter(
			'wp_upload_image_mime_transforms',
			function () {
				return array( 'image/jpeg' => array() );
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/webp-lossy.webp'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		foreach ( $metadata['sizes'] as $size_name => $properties ) {
			$this->assertArrayNotHasKey( 'sources', $properties );
		}
	}


	/**
	 * Test creating a WebP version with all the required properties.
	 *
	 * @ticket 55443
	 */
	public function it_should_create_a_webp_version_with_all_the_required_properties() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayHasKey( 'sources', $metadata['sizes']['thumbnail'] );
		$this->assertArrayHasKey( 'image/jpeg', $metadata['sizes']['thumbnail']['sources'] );
		$this->assertArrayHasKey( 'filesize', $metadata['sizes']['thumbnail']['sources']['image/jpeg'] );
		$this->assertArrayHasKey( 'file', $metadata['sizes']['thumbnail']['sources']['image/jpeg'] );
		$this->assertArrayHasKey( 'image/webp', $metadata['sizes']['thumbnail']['sources'] );
		$this->assertArrayHasKey( 'filesize', $metadata['sizes']['thumbnail']['sources']['image/webp'] );
		$this->assertArrayHasKey( 'file', $metadata['sizes']['thumbnail']['sources']['image/webp'] );
		$this->assertStringEndsNotWith( '.jpeg', $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] );
		$this->assertStringEndsWith( '.webp', $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] );
	}

	/**
	 * Test removing `scaled` suffix from the generated filename.
	 *
	 * @ticket 55443
	 */
	public function it_should_remove_scaled_suffix_from_the_generated_filename() {
		// The leafs image is 1080 pixels wide with this filter we ensure a -scaled version is created.
		add_filter(
			'big_image_size_threshold',
			function () {
				return 850;
			}
		);

		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$this->assertStringEndsWith( '-scaled.jpg', get_attached_file( $attachment_id ) );
		$this->assertArrayHasKey( 'image/webp', $metadata['sizes']['medium']['sources'] );
		$this->assertStringEndsNotWith( '-scaled.webp', $metadata['sizes']['medium']['sources']['image/webp']['file'] );
		$this->assertStringEndsWith( '-300x200.webp', $metadata['sizes']['medium']['sources']['image/webp']['file'] );
	}

	/**
	 * Test removing the generated webp images when the attachment is deleted.
	 *
	 * @ticket 55443
	 */
	public function it_should_remove_the_generated_webp_images_when_the_attachment_is_deleted() {
		// Make sure no editor is available.
		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$sizes    = array( 'thumbnail', 'medium' );

		foreach ( $sizes as $size_name ) {
			$this->assertArrayHasKey( 'image/webp', $metadata['sizes'][ $size_name ]['sources'] );
			$this->assertArrayHasKey( 'file', $metadata['sizes'][ $size_name ]['sources']['image/webp'] );
			$this->assertFileExists(
				path_join( $dirname, $metadata['sizes'][ $size_name ]['sources']['image/webp']['file'] )
			);
		}

		wp_delete_attachment( $attachment_id );

		foreach ( $sizes as $size_name ) {
			$this->assertFileDoesNotExist(
				path_join( $dirname, $metadata['sizes'][ $size_name ]['sources']['image/webp']['file'] )
			);
		}
	}

	/**
	 * Test removing the attached WebP version if the attachment is force deleted but empty trash day is not defined.
	 *
	 * @ticket 55443
	 */
	public function it_should_remove_the_attached_webp_version_if_the_attachment_is_force_deleted_but_empty_trash_day_is_not_defined() {
		// Make sure no editor is available.
		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertFileExists(
			path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] )
		);

		wp_delete_attachment( $attachment_id, true );

		$this->assertFileDoesNotExist(
			path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] )
		);
	}

	/**
	 * Test removing the WebP version of the image if the image is force deleted and empty trash days is set to zero.
	 *
	 * @ticket 55443
	 */
	public function it_should_remove_the_webp_version_of_the_image_if_the_image_is_force_deleted_and_empty_trash_days_is_set_to_zero() {
		// Make sure no editor is available.
		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);

		$file    = get_attached_file( $attachment_id, true );
		$dirname = pathinfo( $file, PATHINFO_DIRNAME );

		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertFileExists(
			path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] )
		);

		define( 'EMPTY_TRASH_DAYS', 0 );

		wp_delete_attachment( $attachment_id, true );

		$this->assertFileDoesNotExist(
			path_join( $dirname, $metadata['sizes']['thumbnail']['sources']['image/webp']['file'] )
		);
	}

	/**
	 * Test avoiding the change of URLs of images that are not part of the media library.
	 *
	 * @ticket 55443
	 */
	public function it_should_avoid_the_change_of_urls_of_images_that_are_not_part_of_the_media_library() {
		$paragraph = '<p>Donec accumsan, sapien et <img src="https://ia600200.us.archive.org/16/items/SPD-SLRSY-1867/hubblesite_2001_06.jpg">, id commodo nisi sapien et est. Mauris nisl odio, iaculis vitae pellentesque nec.</p>';

		$this->assertSame( $paragraph, webp_uploads_update_image_references( $paragraph ) );
	}

	/**
	 * Test avoiding replacing not existing attachment IDs.
	 *
	 * @ticket 55443
	 */
	public function it_should_avoid_replacing_not_existing_attachment_i_ds() {
		$paragraph = '<p>Donec accumsan, sapien et <img class="wp-image-0" src="https://ia600200.us.archive.org/16/items/SPD-SLRSY-1867/hubblesite_2001_06.jpg">, id commodo nisi sapien et est. Mauris nisl odio, iaculis vitae pellentesque nec.</p>';

		$this->assertSame( $paragraph, webp_uploads_update_image_references( $paragraph ) );
	}

	/**
	 * Test preventing replacing a WebP image.
	 *
	 * @ticket 55443
	 */
	public function it_should_test_preventing_replacing_a_webp_image() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/webp-lossy.webp'
		);

		$tag = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		$this->assertSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * Test preventing replacing a jpg image if the image does not have the target class name.
	 *
	 * @ticket 55443
	 */
	public function it_should_test_preventing_replacing_a_jpg_image_if_the_image_does_not_have_the_target_class_name() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);

		$tag = wp_get_attachment_image( $attachment_id, 'medium' );

		$this->assertSame( $tag, webp_uploads_update_image_references( $tag ) );
	}

	/**
	 * Test replacing the references to a JPG image to a WebP version.
	 *
	 * @dataProvider provider_replace_images_with_different_extensions
	 *
	 * @ticket 55443
	 */
	public function it_should_replace_the_references_to_a_jpg_image_to_a_webp_version( $image_path ) {
		$attachment_id = $this->factory->attachment->create_upload_object( $image_path );

		$tag          = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$expected_tag = $tag;
		$metadata     = wp_get_attachment_metadata( $attachment_id );
		foreach ( $metadata['sizes'] as $size => $properties ) {
			$expected_tag = str_replace( $properties['sources']['image/jpeg']['file'], $properties['sources']['image/webp']['file'], $expected_tag );
		}

		$this->assertNotEmpty( $expected_tag );
		$this->assertNotSame( $tag, $expected_tag );
		$this->assertSame( $expected_tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	public function provider_replace_images_with_different_extensions() {
		yield 'An image with a .jpg extension' => array( DIR_TESTDATA . '/images/test-image.jpg' );
		yield 'An image with a .jpeg extension' => array( DIR_TESTDATA . '/images/test-image.jpeg' );
	}

	/**
	 * Test the full image size from the original mime type.
	 *
	 * @ticket 55443
	 */
	public function it_should_contain_the_full_image_size_from_the_original_mime() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);

		$tag = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		$expected = array(
			'ext'  => 'jpg',
			'type' => 'image/jpeg',
		);
		$this->assertSame( $expected, wp_check_filetype( get_attached_file( $attachment_id ) ) );
		$this->assertContains( wp_basename( get_attached_file( $attachment_id ) ), webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * Test preventing replacing an image with no available sources.
	 *
	 * @ticket 55443
	 */
	public function it_should_prevent_replacing_an_image_with_no_available_sources() {
		add_filter( 'wp_upload_image_mime_transforms', '__return_empty_array' );

		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.jpg' );

		$tag = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );
		$this->assertSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * Test preventing update not supported images with no available sources.
	 *
	 * @dataProvider provider_it_should_prevent_update_not_supported_images_with_no_available_sources
	 *
	 * @ticket 55443
	 */
	public function it_should_prevent_update_not_supported_images_with_no_available_sources( $image_path ) {
		$attachment_id = $this->factory->attachment->create_upload_object( $image_path );

		$this->assertIsNumeric( $attachment_id );
		$tag = wp_get_attachment_image( $attachment_id, 'full', false, array( 'class' => "wp-image-{$attachment_id}" ) );

		$this->assertSame( $tag, webp_uploads_img_tag_update_mime_type( $tag, 'the_content', $attachment_id ) );
	}

	/**
	 * Data provider for it_should_prevent_update_not_supported_images_with_no_available_sources.
	 */
	public function provider_it_should_prevent_update_not_supported_images_with_no_available_sources() {
		yield 'PNG image' => array( DIR_TESTDATA . '/images/test-image.png' );
		yield 'GIFT image' => array( DIR_TESTDATA . '/images/test-image.gif' );
	}

}
