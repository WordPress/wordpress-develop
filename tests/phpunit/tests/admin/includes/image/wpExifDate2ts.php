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
	 * @ticket 65819
	 */
	public function test_converts_exif_datetime_to_timestamp() {
		$this->assertSame(
			strtotime( '2000-01-02 03:04:05' ),
			wp_exif_date2ts( '2000:01:02 03:04:05' )
		);
	}
}
