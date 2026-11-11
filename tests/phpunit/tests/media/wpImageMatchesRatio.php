<?php

/**
 * Tests for the `wp_image_matches_ratio()` function.
 *
 * @group media
 *
 * @covers ::wp_image_matches_ratio
 */
class Tests_Media_WpImageMatchesRatio extends WP_UnitTestCase {

	/**
	 * Tests matching ratio with identical dimensions.
	 *
	 * @ticket 66619
	 */
	public function test_wp_image_matches_ratio_identical_dimensions() {
		$this->assertTrue( wp_image_matches_ratio( 800, 600, 800, 600 ) );
	}

	/**
	 * Tests matching ratio for proportionally scaled images.
	 *
	 * @ticket 66619
	 */
	public function test_wp_image_matches_ratio_scaled_proportional() {
		// Both 4:3 aspect ratio, source larger than target.
		$this->assertTrue( wp_image_matches_ratio( 1600, 1200, 800, 600 ) );

		// Both 4:3 aspect ratio, target larger than source.
		$this->assertTrue( wp_image_matches_ratio( 800, 600, 1600, 1200 ) );

		// 16:9 aspect ratio.
		$this->assertTrue( wp_image_matches_ratio( 1920, 1080, 1280, 720 ) );
	}

	/**
	 * Tests non-matching aspect ratios.
	 *
	 * @ticket 66619
	 */
	public function test_wp_image_matches_ratio_different_aspect_ratios() {
		// 16:9 vs 4:3.
		$this->assertFalse( wp_image_matches_ratio( 1920, 1080, 800, 600 ) );

		// Landscape vs portrait.
		$this->assertFalse( wp_image_matches_ratio( 1200, 800, 800, 1200 ) );
	}

	/**
	 * Tests that dimensions within 1px difference are considered a match.
	 *
	 * @ticket 66619
	 */
	public function test_wp_image_matches_ratio_within_one_pixel_tolerance() {
		// 300x200 scaled to width 150 yields 150x100.
		// Testing target height with 1px difference (101 or 99).
		$this->assertTrue( wp_image_matches_ratio( 300, 200, 150, 101 ) );
		$this->assertTrue( wp_image_matches_ratio( 300, 200, 150, 99 ) );
	}

	/**
	 * Tests that dimensions exceeding 1px tolerance return false.
	 *
	 * @ticket 66619
	 */
	public function test_wp_image_matches_ratio_beyond_tolerance() {
		// 300x200 scaled to width 150 yields 150x100.
		// Difference of 2px should fail.
		$this->assertFalse( wp_image_matches_ratio( 300, 200, 150, 102 ) );
		$this->assertFalse( wp_image_matches_ratio( 300, 200, 150, 98 ) );
	}
}
