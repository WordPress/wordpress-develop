<?php

/**
 * @group functions.php
 * @covers ::wp_is_numeric_array
 */
class Tests_Functions__wp_check_existing_file_names extends WP_UnitTestCase {

	/**
	 * @dataProvider data__wp_check_existing_file_names
	 *
	 * @ticket 53971
	 *
	 * @param string $filename filename looked for.
	 * @param array $files files check against.
	 * @param boolean $expected Expected result.
	 */
	public function test__wp_check_existing_file_names( $filename, $files, $expected ) {

		$this->assertSame( $expected, _wp_check_existing_file_names( $filename, $files ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data__wp_check_existing_file_names() {
		return array(
			'no size'        => array(
				'filename' => 'filename.php',
				'files'    => array( 'filename.php' ),
				'expected' => false,
			),
			'1x1'            => array(
				'filename' => 'filename.png',
				'files'    => array( 'filename-1x1.png' ),
				'expected' => true,
			),
			'1x999999999999' => array(
				'filename' => 'filename.png',
				'files'    => array( 'filename-1x999999999999.png' ),
				'expected' => true,
			),
			'scaled'         => array(
				'filename' => 'filename.png',
				'files'    => array( 'filename-scaled.png' ),
				'expected' => true,
			),
			'rotated'        => array(
				'filename' => 'filename.ext',
				'files'    => array( 'filename-1x1.ext', 'filename-scaled.ext', 'filename-rotated.ext' ),
				'expected' => true,
			),

			'lots' => array(
				'filename' => 'filename.png',
				'files'    => array( 'filename-rotated.png' ),
				'expected' => true,
			),
		);
	}
}
