<?php

/**
 * @group media
 * @group admin
 */
class Tests_Admin_IncludesMedia extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	/**
	 * Tests that a `filesize` stored in the attachment metadata is normalized to a positive integer.
	 *
	 * When the stored value cannot be normalized, it should be treated as missing so that the
	 * filesystem fallback runs instead.
	 *
	 * @ticket 65686
	 *
	 * @covers ::attachment_submitbox_metadata
	 *
	 * @dataProvider data_attachment_submitbox_metadata_filesize
	 *
	 * @param mixed            $filesize The `filesize` value stored in the attachment metadata.
	 * @param int<0, max>|null $expected The expected file size in bytes, or null if none should be displayed.
	 */
	public function test_attachment_submitbox_metadata_filesize( $filesize, ?int $expected ) {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => 'test-image.jpg',
				'post_title'     => 'Attachment Title',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->assertIsInt( $id );

		wp_update_attachment_metadata(
			$id,
			array(
				'width'    => 50,
				'height'   => 50,
				'file'     => 'test-image.jpg',
				'filesize' => $filesize,
			)
		);

		$GLOBALS['post'] = get_post( $id );

		$output = get_echo( 'attachment_submitbox_metadata' );

		if ( null === $expected ) {
			$this->assertStringNotContainsString( 'misc-pub-filesize', $output, 'The file size should not have been displayed.' );
		} else {
			$this->assertStringContainsString( size_format( $expected ), $output, 'The displayed file size did not match the normalized file size.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ filesize: mixed, expected: int<0, max>|null }>
	 */
	public function data_attachment_submitbox_metadata_filesize(): array {
		return array(
			'an integer'                  => array(
				'filesize' => 12345,
				'expected' => 12345,
			),
			'a numeric string'            => array(
				'filesize' => '12345',
				'expected' => 12345,
			),
			'a float'                     => array(
				'filesize' => 12345.6,
				'expected' => 12345,
			),
			'a float as a string'         => array(
				'filesize' => '12345.6',
				'expected' => 12345,
			),
			'an exponential string'       => array(
				'filesize' => '1e3',
				'expected' => 1000,
			),
			'a value smaller than a byte' => array(
				'filesize' => 0.5,
				'expected' => null,
			),
			'zero'                        => array(
				'filesize' => 0,
				'expected' => null,
			),
			'a negative integer'          => array(
				'filesize' => -12345,
				'expected' => null,
			),
			'an empty string'             => array(
				'filesize' => '',
				'expected' => null,
			),
			'a non-numeric string'        => array(
				'filesize' => 'not-a-number',
				'expected' => null,
			),
			'an array'                    => array(
				'filesize' => array( 12345 ),
				'expected' => null,
			),
			'null'                        => array(
				'filesize' => null,
				'expected' => null,
			),
			'false'                       => array(
				'filesize' => false,
				'expected' => null,
			),
			'true'                        => array(
				'filesize' => true,
				'expected' => null,
			),
		);
	}

	/**
	 * Tests that an unusable `filesize` in the attachment metadata falls back to the size of the file.
	 *
	 * @ticket 65686
	 *
	 * @covers ::attachment_submitbox_metadata
	 *
	 * @dataProvider data_attachment_submitbox_metadata_filesize_falls_back_to_the_file
	 *
	 * @param mixed $filesize The `filesize` value stored in the attachment metadata.
	 */
	public function test_attachment_submitbox_metadata_filesize_falls_back_to_the_file( $filesize ) {
		$id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $id );
		$file = get_attached_file( $id );
		$this->assertIsString( $file );

		$meta = wp_get_attachment_metadata( $id );
		$this->assertIsArray( $meta );
		$meta['filesize'] = $filesize;
		wp_update_attachment_metadata( $id, $meta );

		$GLOBALS['post'] = get_post( $id );

		$output = get_echo( 'attachment_submitbox_metadata' );

		$filesize = wp_filesize( $file );
		$this->assertIsInt( $filesize );
		$this->assertStringContainsString( size_format( $filesize ), $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ filesize: mixed }>
	 */
	public function data_attachment_submitbox_metadata_filesize_falls_back_to_the_file(): array {
		return array(
			'a value smaller than a byte' => array( 'filesize' => 0.5 ),
			'zero'                        => array( 'filesize' => 0 ),
			'a negative integer'          => array( 'filesize' => -12345 ),
			'an empty string'             => array( 'filesize' => '' ),
			'a non-numeric string'        => array( 'filesize' => 'not-a-number' ),
			'an array'                    => array( 'filesize' => array( 12345 ) ),
			'null'                        => array( 'filesize' => null ),
			'false'                       => array( 'filesize' => false ),
			'true'                        => array( 'filesize' => true ),
		);
	}
}
