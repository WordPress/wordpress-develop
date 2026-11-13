<?php
/**
 * Tests for image_hwstring().
 *
 * @group media
 *
 * @covers ::image_hwstring
 */
class Tests_Media_ImageHwstring extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_image_hwstring
	 *
	 * @param int|string $width    Image width.
	 * @param int|string $height   Image height.
	 * @param string     $expected Expected attribute string.
	 */
	public function test_image_hwstring( $width, $height, $expected ) {
		$this->assertSame( $expected, image_hwstring( $width, $height ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_image_hwstring() {
		return array(
			'both dimensions'       => array(
				100,
				50,
				'width="100" height="50" ',
			),
			'width only'            => array(
				100,
				0,
				'width="100" ',
			),
			'height only'           => array(
				0,
				50,
				'height="50" ',
			),
			'both falsy'            => array(
				0,
				0,
				'',
			),
			'casts numeric strings' => array(
				'100.9',
				'50.1',
				'width="100" height="50" ',
			),
		);
	}
}
