<?php
/**
 * Tests for wp_image_matches_ratio().
 *
 * @group media
 *
 * @covers ::wp_image_matches_ratio
 */
class Tests_Media_WpImageMatchesRatio extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_wp_image_matches_ratio
	 *
	 * @param int  $source_width  Source width.
	 * @param int  $source_height Source height.
	 * @param int  $target_width  Target width.
	 * @param int  $target_height Target height.
	 * @param bool $expected      Expected match result.
	 */
	public function test_wp_image_matches_ratio( $source_width, $source_height, $target_width, $target_height, $expected ) {
		$this->assertSame(
			$expected,
			wp_image_matches_ratio( $source_width, $source_height, $target_width, $target_height )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wp_image_matches_ratio() {
		return array(
			'same aspect larger source' => array( 400, 200, 200, 100, true ),
			'same aspect larger target' => array( 200, 100, 400, 200, true ),
			'different aspect'          => array( 400, 200, 400, 300, false ),
		);
	}
}
