<?php

/**
 * @group functions.php
 * @covers ::_wp_check_alternate_file_names
 */
class Tests_Functions_WpCheckAlternateFileNames extends WP_UnitTestCase {

	/**
	 * @dataProvider data_wp_check_alternate_file_names
	 *
	 * @ticket 55199
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
			'an existing file'                         => array(
				'filenames' => array( 'canola.jpg' ),
				'dir'       => DIR_TESTDATA . '/images/',
				'files'     => array(),
				'expected'  => true,
			),
			'multiple existing files'                  => array(
				'filenames' => array( 'canola.jpg', 'codeispoetry.png' ),
				'dir'       => DIR_TESTDATA . '/images/',
				'files'     => array(),
				'expected'  => true,
			),
			'a non-existent file and an existing file' => array(
				'filenames' => array( 'an-image.jpg', 'codeispoetry.png' ),
				'dir'       => DIR_TESTDATA . '/images/',
				'files'     => array(),
				'expected'  => true,
			),
			'a non-existent file and an existing image sub-size file' => array(
				'filenames' => array( 'one-blue-pixel.png' ),
				'dir'       => DIR_TESTDATA . '/images/',
				'files'     => array( 'one-blue-pixel-100x100.png' ),
				'expected'  => true,
			),
			'a non-existent file and no other existing files' => array(
				'filenames' => array( 'filename.php' ),
				'dir'       => DIR_TESTDATA . '/images/',
				'files'     => array(),
				'expected'  => false,
			),
			'multiple non-existent files and no existing image sub-size files' => array(
				'filenames' => array( 'canola.jpg', 'codeispoetry.png' ),
				'dir'       => DIR_TESTDATA . '/functions/',
				'files'     => array( 'an-image-100x100.jpg', 'another-image-100x100.png' ),
				'expected'  => false,
			),
		);
	}
}
