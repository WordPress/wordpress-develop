<?php

/**
 * @group functions
 * @group upload
 *
 * @covers ::wp_unique_filename
 */
class Tests_Functions_WpUniqueFilename extends WP_UnitTestCase {

	/**
	 * @dataProvider data_wp_unique_filename
	 *
	 * @param string $filename Original filename.
	 * @param string $expected Expected unique filename.
	 * @param string $message  Assertion message.
	 */
	public function test_wp_unique_filename( $filename, $expected, $message ) {
		$testdir = DIR_TESTDATA . '/images/';

		$this->assertSame( $expected, wp_unique_filename( $testdir, $filename ), $message );
	}

	/**
	 * Data provider for test_wp_unique_filename.
	 *
	 * @return array[]
	 */
	public function data_wp_unique_filename() {
		return array(
			'non-existing file'                     => array(
				'filename' => 'abcdefg.png',
				'expected' => 'abcdefg.png',
				'message'  => 'Test non-existing file, file name should be unchanged.',
			),
			'file already exists'                   => array(
				'filename' => 'test-image.png',
				'expected' => 'test-image-1.png',
				'message'  => 'File name not unique, number not appended.',
			),
			'uppercase extension'                   => array(
				'filename' => 'test-image.PNG',
				'expected' => 'test-image-1.png',
				'message'  => 'File name with uppercase extension not unique, number not appended.',
			),
			'already added number'                  => array(
				'filename' => 'test-image-2.gif',
				'expected' => 'test-image-2-1.gif',
				'message'  => 'File name not unique, number not appended correctly.',
			),
			'special chars'                         => array(
				'filename' => 'testtést-imagé.png',
				'expected' => 'testtest-image.png',
				'message'  => 'Filename with special chars failed',
			),
			'special chars with potential conflict' => array(
				'filename' => 'tést-imagé.png',
				'expected' => 'test-image-1.png',
				'message'  => 'Filename with special chars failed',
			),
			'single quotes in name'                 => array(
				'filename' => "abcdefg'h.png",
				'expected' => 'abcdefgh.png',
				'message'  => 'File with quote failed',
			),
			'double quotes in name'                 => array(
				'filename' => 'abcdefg"h.png',
				'expected' => 'abcdefgh.png',
				'message'  => 'File with quote failed',
			),
			'crazy name'                            => array(
				'filename' => '12%af34567890#~!@#$..%^&*()|_+qwerty  fgh`jkl zx<>?:"{}[]="\'/?.png',
				'expected' => '12af34567890@.^_qwerty-fghjkl-zx.png',
				'message'  => 'Failed crazy file name',
			),
			'single slash'                          => array(
				'filename' => 'abcde\fg.png',
				'expected' => 'abcdefg.png',
				'message'  => 'Slash not removed',
			),
			'double slash'                          => array(
				'filename' => 'abcde\\fg.png',
				'expected' => 'abcdefg.png',
				'message'  => 'Double slashed not removed',
			),
			'triple slash'                          => array(
				'filename' => 'abcde\\\fg.png',
				'expected' => 'abcdefg.png',
				'message'  => 'Triple slashed not removed',
			),
		);
	}

	/**
	 * @ticket 42437
	 */
	public function test_unique_filename_with_dimension_like_filename() {
		$testdir = DIR_TESTDATA . '/images/';

		add_filter( 'upload_dir', array( $this, 'upload_dir_patch_basedir' ) );

		// Test collision with "dimension-like" original filename.
		$this->assertSame( 'one-blue-pixel-100x100-1.png', wp_unique_filename( $testdir, 'one-blue-pixel-100x100.png' ) );
		// Test collision with existing sub-size filename.
		// Existing files: one-blue-pixel-100x100.png, one-blue-pixel-1-100x100.png.
		$this->assertSame( 'one-blue-pixel-2.png', wp_unique_filename( $testdir, 'one-blue-pixel.png' ) );
		// Same as above with upper case extension.
		$this->assertSame( 'one-blue-pixel-2.png', wp_unique_filename( $testdir, 'one-blue-pixel.PNG' ) );

		remove_filter( 'upload_dir', array( $this, 'upload_dir_patch_basedir' ) );
	}

	// Callback to patch "basedir" when used in `wp_unique_filename()`.
	public function upload_dir_patch_basedir( $upload_dir ) {
		$upload_dir['basedir'] = DIR_TESTDATA . '/images/';
		return $upload_dir;
	}

	/**
	 * @ticket 53668
	 */
	public function test_wp_unique_filename_with_additional_image_extension() {
		$testdir = DIR_TESTDATA . '/images/';

		add_filter( 'upload_dir', array( $this, 'upload_dir_patch_basedir' ) );

		// Set conversions for uploaded images.
		add_filter( 'image_editor_output_format', array( $this, 'image_editor_output_format_handler' ) );

		// Ensure the test images exist.
		$this->assertFileExists( $testdir . 'test-image-1-100x100.jpg', 'test-image-1-100x100.jpg does not exist' );
		$this->assertFileExists( $testdir . 'test-image-2.gif', 'test-image-2.gif does not exist' );
		$this->assertFileExists( $testdir . 'test-image-3.jpg', 'test-image-3.jpg does not exist' );
		$this->assertFileExists( $testdir . 'test-image-4.png', 'test-image-4.png does not exist' );

		// Standard test: file does not exist and there are no possible intersections with other files.
		$this->assertSame(
			'abcdef.png',
			wp_unique_filename( $testdir, 'abcdef.png' ),
			'The abcdef.png, abcdef.gif, and abcdef.jpg images do not exist. The file name should not be changed.'
		);

		// Actual clash recognized.
		$this->assertSame(
			'canola-1.jpg',
			wp_unique_filename( $testdir, 'canola.jpg' ),
			'The canola.jpg image exists. The file name should be unique.'
		);

		// Same name with different uppercase extension and the image will be converted.
		$this->assertSame(
			'canola-1.png',
			wp_unique_filename( $testdir, 'canola.PNG' ),
			'The canola.jpg image exists. Uploading canola.PNG that will be converted to canola.jpg should produce unique file name.'
		);

		// Actual clash with several images with different extensions.
		$this->assertSame(
			'test-image-5.png',
			wp_unique_filename( $testdir, 'test-image.png' ),
			'The test-image.png, test-image-1-100x100.jpg, test-image-2.gif, test-image-3.jpg, and test-image-4.png images exist.' .
			'All of them may clash when creating sub-sizes or regenerating thumbnails in the future. The filename should be unique.'
		);

		// Possible clash with regenerated thumbnails in the future.
		$this->assertSame(
			'codeispoetry-1.jpg',
			wp_unique_filename( $testdir, 'codeispoetry.jpg' ),
			'The codeispoetry.png image exists. When regenerating thumbnails for it they will be converted to JPG.' .
			'The name of the newly uploaded codeispoetry.jpg should be made unique.'
		);

		remove_filter( 'image_editor_output_format', array( $this, 'image_editor_output_format_handler' ) );
		remove_filter( 'upload_dir', array( $this, 'upload_dir_patch_basedir' ) );
	}

	/**
	 * Changes the output format when editing images. When uploading a PNG file
	 * it will be converted to JPEG, GIF to JPEG, and PICT to BMP
	 * (if the image editor in PHP supports it).
	 *
	 * @param array $formats
	 *
	 * @return array
	 */
	public function image_editor_output_format_handler( $formats ) {
		$formats['image/png'] = 'image/jpeg';
		$formats['image/gif'] = 'image/jpeg';
		$formats['image/pct'] = 'image/bmp';

		return $formats;
	}
}
