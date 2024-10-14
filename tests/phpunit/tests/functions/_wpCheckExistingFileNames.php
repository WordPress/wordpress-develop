<?php

/**
 * @group functions.php
 * @covers ::_wp_check_existing_file_names
 */
class Tests_Functions__WpCheckExistingFileNames extends WP_UnitTestCase {

	/**
	 * @dataProvider data_wp_check_existing_file_names
	 *
	 * @ticket 55192
	 *
	 * @param string  $filename The file name to check.
	 * @param array   $files    An array of existing files in the directory.
	 * @param boolean $expected Expected result.
	 */
	public function test_wp_check_existing_file_names( $filename, $files, $expected ) {

		$this->assertSame( $expected, _wp_check_existing_file_names( $filename, $files ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_check_existing_file_names() {
		return array(
			'no size'                      => array(
				'filename' => 'filename.php',
				'files'    => array( 'filename.php' ),
				'expected' => false,
			),
			'1x1'                          => array(
				'filename' => 'filename.png',
				'files'    => array( 'filename-1x1.png' ),
				'expected' => true,
			),
			'1x999999999999'               => array(
				'filename' => 'filename.png',
				'files'    => array( 'filename-1x999999999999.png' ),
				'expected' => true,
			),
			'scaled'                       => array(
				'filename' => 'filename.png',
				'files'    => array( 'filename-scaled.png' ),
				'expected' => true,
			),
			'three matches'                => array(
				'filename' => 'filename.ext',
				'files'    => array( 'filename-1x1.ext', 'filename-scaled.ext', 'filename-rotated.ext' ),
				'expected' => true,
			),
			'rotated'                      => array(
				'filename' => 'filename.png',
				'files'    => array( 'filename-rotated.png' ),
				'expected' => true,
			),
			'no extension'                 => array(
				'filename' => 'filename',
				'files'    => array( 'filename-1x1.png' ),
				'expected' => false,
			),
			'no filename'                  => array(
				'filename' => '.htaccess',
				'files'    => array( 'filename-1x1.png' ),
				'expected' => false,
			),
			'found file with no extension' => array(
				'filename' => 'filename',
				'files'    => array( 'filename' ),
				'expected' => false,
			),
			'found file with no filename'  => array(
				'filename' => '.htaccess',
				'files'    => array( '.htaccess' ),
				'expected' => false,
			),
		);
	}
}
