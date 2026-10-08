<?php
/**
 * Tests for the `wp_read_image_metadata_allowed_keys` filter in `wp_read_image_metadata()`.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Tests for `wp_read_image_metadata_allowed_keys`.
 *
 * @group image
 * @group media
 * @ticket 66248
 *
 * @covers ::wp_read_image_metadata
 */
class Tests_Image_ReadImageMetadataAllowedKeys extends WP_UnitTestCase {

	/**
	 * Path to test image with EXIF metadata.
	 *
	 * @var string
	 */
	private static $exif_image;

	/**
	 * Path to test image with IPTC and XMP alt text metadata.
	 *
	 * @var string
	 */
	private static $iptc_xmp_image;

	/**
	 * Sets up test fixtures before running class tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$exif_image     = DIR_TESTDATA . '/images/2004-07-22-DSC_0008.jpg';
		self::$iptc_xmp_image = DIR_TESTDATA . '/images/IPTC-PhotometadataRef-Std2025.1.jpg';
	}

	/**
	 * Cleans up filters after each test.
	 */
	public function tear_down() {
		remove_all_filters( 'wp_read_image_metadata_allowed_keys' );

		parent::tear_down();
	}

	/**
	 * Tests that by default all metadata keys are allowed and extracted without modification.
	 *
	 * @ticket 66248
	 */
	public function test_default_allowed_keys_extracts_all_metadata() {
		$meta = wp_read_image_metadata( self::$exif_image );

		$this->assertIsArray( $meta );
		$this->assertSame( 'NIKON D70', $meta['camera'] );
		$this->assertSame( '6.3', $meta['aperture'] );
		$this->assertSame( '27', $meta['focal_length'] );
		$this->assertSame( '400', $meta['iso'] );
	}

	/**
	 * Tests that disallowing the 'alt' key prevents alt text extraction while allowing other keys.
	 *
	 * @ticket 66248
	 */
	public function test_disallowing_alt_key_skips_alt_extraction() {
		add_filter(
			'wp_read_image_metadata_allowed_keys',
			static function ( $keys ) {
				return array_diff( $keys, array( 'alt' ) );
			}
		);

		$meta = wp_read_image_metadata( self::$iptc_xmp_image );

		$this->assertIsArray( $meta );
		$this->assertSame( '', $meta['alt'], "Alt text should remain empty when 'alt' is not in allowed keys." );
		$this->assertNotEmpty( $meta['caption'], 'Caption should still be extracted from IPTC.' );
	}

	/**
	 * Tests that passing an empty array of allowed keys skips all metadata extraction
	 * while preserving the complete default array structure.
	 *
	 * @ticket 66248
	 */
	public function test_empty_allowed_keys_skips_all_extraction_and_preserves_structure() {
		add_filter( 'wp_read_image_metadata_allowed_keys', '__return_empty_array' );

		$meta = wp_read_image_metadata( self::$exif_image );

		$expected = array(
			'aperture'          => '0',
			'credit'            => '',
			'camera'            => '',
			'caption'           => '',
			'created_timestamp' => '0',
			'copyright'         => '',
			'focal_length'      => '0',
			'iso'               => '0',
			'shutter_speed'     => '0',
			'title'             => '',
			'orientation'       => '0',
			'keywords'          => array(),
			'alt'               => '',
		);

		$this->assertSame( $expected, $meta );
	}

	/**
	 * Tests that non-array return values from the filter are safely treated as an empty array.
	 *
	 * @ticket 66248
	 *
	 * @dataProvider data_non_array_allowed_keys
	 *
	 * @param mixed $non_array_value Non-array filter return value.
	 */
	public function test_non_array_allowed_keys_treated_as_empty_array( $non_array_value ) {
		add_filter(
			'wp_read_image_metadata_allowed_keys',
			static function () use ( $non_array_value ) {
				return $non_array_value;
			}
		);

		$meta = wp_read_image_metadata( self::$exif_image );

		$this->assertSame( '0', $meta['aperture'] );
		$this->assertSame( '', $meta['camera'] );
		$this->assertSame( '', $meta['alt'] );
	}

	/**
	 * Data provider for non-array allowed keys filter return values.
	 *
	 * @return array[]
	 */
	public static function data_non_array_allowed_keys() {
		return array(
			'false'  => array( false ),
			'null'   => array( null ),
			'string' => array( 'invalid' ),
			'int'    => array( 123 ),
		);
	}

	/**
	 * Tests whitelisting specific metadata keys only extracts those fields.
	 *
	 * @ticket 66248
	 */
	public function test_whitelist_specific_keys_only_extracts_those_fields() {
		add_filter(
			'wp_read_image_metadata_allowed_keys',
			static function () {
				return array( 'camera', 'iso' );
			}
		);

		$meta = wp_read_image_metadata( self::$exif_image );

		// Whitelisted fields should be extracted.
		$this->assertSame( 'NIKON D70', $meta['camera'] );
		$this->assertSame( '400', $meta['iso'] );

		// Non-whitelisted fields should remain defaults.
		$this->assertSame( '0', $meta['aperture'] );
		$this->assertSame( '0', $meta['focal_length'] );
		$this->assertSame( '0', $meta['shutter_speed'] );
		$this->assertSame( '', $meta['alt'] );
	}

	/**
	 * Tests that the filter receives all expected parameters.
	 *
	 * @ticket 66248
	 */
	public function test_allowed_keys_filter_receives_expected_arguments() {
		$captured_args = array();

		add_filter(
			'wp_read_image_metadata_allowed_keys',
			static function ( $allowed_keys, $file, $image_type ) use ( &$captured_args ) {
				$captured_args = array(
					'allowed_keys' => $allowed_keys,
					'file'         => $file,
					'image_type'   => $image_type,
				);
				return $allowed_keys;
			},
			10,
			3
		);

		wp_read_image_metadata( self::$exif_image );

		$expected_keys = array(
			'aperture',
			'credit',
			'camera',
			'caption',
			'created_timestamp',
			'copyright',
			'focal_length',
			'iso',
			'shutter_speed',
			'title',
			'orientation',
			'keywords',
			'alt',
		);

		$this->assertSame( $expected_keys, $captured_args['allowed_keys'] );
		$this->assertSame( self::$exif_image, $captured_args['file'] );
		$this->assertSame( IMAGETYPE_JPEG, $captured_args['image_type'] );
	}
}
