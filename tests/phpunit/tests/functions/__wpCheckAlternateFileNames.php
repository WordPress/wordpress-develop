<?php

/**
 * @group functions.php
 * @covers ::_wp_check_existing_file_names
 */
class Tests_Functions_WpCheckAlternateFileNames extends WP_UnitTestCase {

	/**
	 * @dataProvider data_wp_check_alternate_file_names
	 *
	 * @ticket 55192
	 *
	 * @param array  $filenames Array of filenames to check.
	 * @param string $dir       The directory to check.
	 * @param array  $files     An array of existing files in the directory.
	 * @param bool   $expected  Expected result.
	 */
	public function test_wp_check_alternate_file_names( $filenames, $dir, $files, $expected ) {
		$this->assertSame( $expected, _wp_check_alternate_file_names( $filenames, $dir, $files ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_check_alternate_file_names() {
		return array(
			'no file'          => array(
				'filename' => array( 'filename.php' ),
				'dir'      => DIR_TESTDATA . '/images/',
				'files'    => array( 'canola.jpg' ),
				'expected' => false,
			),
			'file'             => array(
				'filename' => array( 'canola.jpg' ),
				'dir'      => DIR_TESTDATA . '/images/',
				'files'    => array( 'filename-1x1.png' ),
				'expected' => true,
			),
			'in files'         => array(
				'filename' => array( 'canola.jpg' ),
				'dir'      => DIR_TESTDATA . '/functions/',
				'files'    => array( 'canola-1x1.jpg' ),
				'expected' => true,
			),
			'loop file exist'  => array(
				'filename' => array( 'canola.jpg', 'codeispoetry.png' ),
				'dir'      => DIR_TESTDATA . '/images/',
				'files'    => array( 'XXXX.png' ),
				'expected' => true,
			),
			'loop file mising' => array(
				'filename' => array( 'canola.jpg', 'codeispoetry.png' ),
				'dir'      => DIR_TESTDATA . '/functions/',
				'files'    => array( 'XXXX.png', 'canola-1x1.jpg', 'codeispoetry-1x1.jpg' ),
				'expected' => true,
			),
		);
	}
}
