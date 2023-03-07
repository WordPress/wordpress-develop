<?php

/**
 * Tests for wp_check_filetype().
 *
 * @group functions.php
 * @group upload
 *
 * @covers ::wp_check_filetype
 */
class Tests_Functions_WpCheckFiletype extends WP_UnitTestCase {

	/**
	 * Tests that wp_check_filetype() returns the correct extension and MIME type.
	 *
	 * @ticket 57151
	 *
	 * @dataProvider data_wp_check_filetype
	 *
	 * @param string     $filename   The filename to check.
	 * @param array|null $mimes      An array of MIME types, or null.
	 * @param array      $expected   An array containing the expected extension and MIME type.
	 */
	public function test_wp_check_filetype( $filename, $mimes, $expected ) {
		$this->assertSame( $expected, wp_check_filetype( $filename, $mimes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return[]
	 */
	public function data_wp_check_filetype() {
		return array(
			'.jpg filename and default allowed'       => array(
				'filename' => 'canola.jpg',
				'mimes'    => null,
				'expected' => array(
					'ext'  => 'jpg',
					'type' => 'image/jpeg',
				),
			),
			'.jpg filename and jpg|jpeg|jpe'          => array(
				'filename' => 'canola.jpg',
				'mimes'    => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'gif'          => 'image/gif',
				),
				'expected' => array(
					'ext'  => 'jpg',
					'type' => 'image/jpeg',
				),
			),
			'.jpeg filename and jpg|jpeg|jpe'         => array(
				'filename' => 'canola.jpeg',
				'mimes'    => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'gif'          => 'image/gif',
				),
				'expected' => array(
					'ext'  => 'jpeg',
					'type' => 'image/jpeg',
				),
			),
			'.jpe filename and jpg|jpeg|jpe'          => array(
				'filename' => 'canola.jpe',
				'mimes'    => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'gif'          => 'image/gif',
				),
				'expected' => array(
					'ext'  => 'jpe',
					'type' => 'image/jpeg',
				),
			),
			'uppercase filename and jpg|jpeg|jpe'     => array(
				'filename' => 'canola.JPG',
				'mimes'    => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'gif'          => 'image/gif',
				),
				'expected' => array(
					'ext'  => 'JPG',
					'type' => 'image/jpeg',
				),
			),
			'.XXX filename and no matching MIME type' => array(
				'filename' => 'canola.XXX',
				'mimes'    => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'gif'          => 'image/gif',
				),
				'expected' => array(
					'ext'  => false,
					'type' => false,
				),
			),
			'.jpg filename but only gif allowed'      => array(
				'filename' => 'canola.jpg',
				'mimes'    => array(
					'gif' => 'image/gif',
				),
				'expected' => array(
					'ext'  => false,
					'type' => false,
				),
			),

		);
	}
}
