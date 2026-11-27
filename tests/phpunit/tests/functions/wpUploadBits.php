<?php

require_once DIR_TESTROOT . '/includes/class-wp-test-stream.php';

/**
 * Tests for wp_upload_bits().
 *
 * @group functions
 * @group upload
 *
 * @covers ::wp_upload_bits
 */
class Tests_Functions_wpUploadBits extends WP_UnitTestCase {

	/**
	 * Stream protocol used for the upload directory.
	 *
	 * @var string
	 */
	const PROTOCOL = 'wptestuploadbits';

	public function tear_down() {
		if ( in_array( self::PROTOCOL, stream_get_wrappers(), true ) ) {
			stream_wrapper_unregister( self::PROTOCOL );
		}

		WP_Test_Stream::$data = array();

		parent::tear_down();
	}

	/**
	 * Tests that a complete write is reported as a success.
	 *
	 * @ticket 66786
	 */
	public function test_should_return_the_file_when_all_bytes_are_written() {
		$actual = $this->upload_bits_to_stream( 'WP_Test_Stream', 'File contents.' );

		$this->assertFalse( $actual['error'], 'The upload should not report an error.' );
		$this->assertSame( self::PROTOCOL . '://uploads/test.txt', $actual['file'], 'The file path should be returned.' );
		$this->assertSame( 'File contents.', WP_Test_Stream::$data['uploads']['/test.txt'], 'The contents should be stored.' );
	}

	/**
	 * Tests that an incomplete write is reported as an error.
	 *
	 * @ticket 66786
	 */
	public function test_should_return_an_error_when_not_all_bytes_are_written() {
		$actual = $this->upload_bits_to_stream( 'WP_Test_Stream_Upload_Bits_Short_Write', 'File contents.' );

		$this->assertIsString( $actual['error'], 'The upload should report an error.' );
		$this->assertArrayNotHasKey( 'file', $actual, 'No file should be returned.' );
	}

	/**
	 * Tests that a failure while flushing the stream is reported as an error.
	 *
	 * PHP 8.6 is the first version where fclose() reports flush and close failures.
	 *
	 * @ticket 66786
	 *
	 * @requires PHP >= 8.6
	 */
	public function test_should_return_an_error_when_the_stream_cannot_be_flushed() {
		$actual = $this->upload_bits_to_stream( 'WP_Test_Stream_Upload_Bits_Flush_Failure', 'File contents.' );

		$this->assertIsString( $actual['error'], 'The upload should report an error.' );
		$this->assertArrayNotHasKey( 'file', $actual, 'No file should be returned.' );
	}

	/**
	 * Uploads bits to an in-memory stream.
	 *
	 * @param string $wrapper_class The stream wrapper class.
	 * @param string $bits          The file contents.
	 * @return array The result of wp_upload_bits().
	 */
	private function upload_bits_to_stream( $wrapper_class, $bits ) {
		stream_wrapper_register( self::PROTOCOL, $wrapper_class );
		WP_Test_Stream::$data = array(
			'uploads' => array(
				'/' => 'DIRECTORY',
			),
		);

		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );

		return wp_upload_bits( 'test.txt', null, $bits );
	}

	/**
	 * Points the upload directory at the in-memory stream.
	 *
	 * @param array $uploads Upload directory data.
	 * @return array Filtered upload directory data.
	 */
	public function filter_upload_dir( $uploads ) {
		$uploads['path']    = self::PROTOCOL . '://uploads';
		$uploads['basedir'] = self::PROTOCOL . '://uploads';
		$uploads['subdir']  = '';
		$uploads['url']     = 'http://example.org/uploads';
		$uploads['baseurl'] = 'http://example.org/uploads';
		$uploads['error']   = false;

		return $uploads;
	}
}

/**
 * A stream wrapper that accepts only part of the data.
 */
class WP_Test_Stream_Upload_Bits_Short_Write extends WP_Test_Stream {

	/**
	 * Whether the first chunk has been written.
	 *
	 * @var bool
	 */
	private $written = false;

	public function stream_write( $data ) {
		if ( $this->written ) {
			return 0;
		}

		$this->written = true;

		return parent::stream_write( substr( $data, 0, 4 ) );
	}
}

/**
 * A stream wrapper that accepts data but cannot flush it.
 */
class WP_Test_Stream_Upload_Bits_Flush_Failure extends WP_Test_Stream {
	public function stream_flush() {
		return false;
	}
}
