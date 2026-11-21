<?php

/**
 * Tests for wp_expand_dimensions().
 *
 * @group media
 *
 * @covers ::wp_expand_dimensions
 */
class Tests_Media_WpExpandDimensions extends WP_UnitTestCase {

	/**
	 * Tests that the example aspect ratio is expanded to the largest size within the max bounds.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_expand_dimensions
	 *
	 * @param int|string $example_width  Example width.
	 * @param int|string $example_height Example height.
	 * @param int|string $max_width      Maximum width.
	 * @param int|string $max_height     Maximum height.
	 * @param int[]      $expected       Expected width and height.
	 */
	public function test_expands_example_to_max_bounds( $example_width, $example_height, $max_width, $max_height, $expected ) {
		$this->assertSame(
			$expected,
			wp_expand_dimensions( $example_width, $example_height, $max_width, $max_height )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_expand_dimensions() {
		return array(
			'matching 4:3 ratio fills the box'     => array( 4, 3, 1024, 768, array( 1024, 768 ) ),
			'landscape 16:9 limited by width'      => array( 16, 9, 1024, 768, array( 1024, 576 ) ),
			'portrait 9:16 limited by height'      => array( 9, 16, 1024, 768, array( 432, 768 ) ),
			'square limited by shorter max side'   => array( 1, 1, 800, 600, array( 600, 600 ) ),
			'numeric strings are cast to integers' => array( '16', '9', '1024', '768', array( 1024, 576 ) ),
		);
	}
}
