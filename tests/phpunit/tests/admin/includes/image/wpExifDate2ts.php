<?php
/**
 * Tests for wp_exif_date2ts().
 *
 * @group admin
 * @group media
 *
 * @covers ::wp_exif_date2ts
 */
class Tests_Admin_Includes_Image_WpExifDate2ts extends WP_UnitTestCase {

	/**
	 * Loads the tested file.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	/**
	 * @ticket 65819
	 * @dataProvider data_exif_datetimes
	 *
	 * @param string $input Exif datetime to convert.
	 */
	public function test_converts_exif_datetime_to_timestamp( $input ) {
		$this->assertSame(
			strtotime( '2000-01-02 03:04:05' ),
			wp_exif_date2ts( $input )
		);
	}

	/**
	 * Data provider for test_converts_exif_datetime_to_timestamp().
	 *
	 * @return array
	 */
	public static function data_exif_datetimes() {
		return array(
			'valid Exif datetime'    => array( '2000:01:02 03:04:05' ),
			'surrounding whitespace' => array( " \t2000:01:02 03:04:05\r\n " ),
		);
	}
}
