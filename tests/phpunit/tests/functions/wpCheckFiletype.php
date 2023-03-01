<?php

/**
 * Test the wp_upload_bits function
 *
 * @group Functions
 * @group Upload
 * @covers ::wp_check_filetype
 */
class Test_wp_check_filetype extends WP_UnitTestCase {

	/**
	 * @ticket 57151
	 * @dataProvider wp_check_filetype_dataset
	 */
	function test_wp_check_filetypes( $filename, $mines, $expected ) {
		$this->assertSame( $expected, wp_check_filetype( $filename, $mines ) );
	}

	function wp_check_filetype_dataset() {
		return array(
			'default'     => array(
				'filename' => 'canola.jpg',
				'mines'    => null,
				'expected' => array(
					'ext'  => 'jpg',
					'type' => 'image/jpeg',
				),
			),
			'short_mines' => array(
				'filename' => 'canola.jpg',
				'mines'    => array(
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
				'filename' => 'canola.XXX',
				'mines'    => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'gif'          => 'image/gif',
				),
				'expected' => array(
					'ext'  => false,
					'type' => false,
				),
			),
			'bad_mines'   => array(
				'filename' => 'canola.jpg',
				'mines'    => array(
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
