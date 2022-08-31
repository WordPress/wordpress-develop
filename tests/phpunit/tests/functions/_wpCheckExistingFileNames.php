<?php

/**
 * @group functions.php
 *
 * @covers ::_wp_check_existing_file_names
 */
class Tests_Functions__wp_check_existing_file_names extends WP_UnitTestCase {

	/**
	 * @ticket 55192
	 *
	 * @dataProvider data_wp_check_existing_file_names
	 *
	 * @param string $filename The file name to check.
	 * @param array  $files    An array of existing files in the directory.
	 * @param bool   $expected The expected result.
	 */
	public function test_wp_check_existing_file_names( $filename, $files, $expected ) {
		$this->assertSame( $expected, _wp_check_existing_file_names( $filename, $files ) );
	}

	/**
	 * Data provider for test_wp_check_existing_file_names().
	 *
	 * @return array[]
	 */
	public function data_wp_check_existing_file_names() {
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
			'lots'           => array(
				'filename' => 'filename.png',
				'files'    => array( 'filename-rotated.png' ),
				'expected' => true,
			),
		);
	}
}
