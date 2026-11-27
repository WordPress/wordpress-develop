<?php
/**
 * Tests for the WP_Filesystem_Direct::put_contents() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';
require_once DIR_TESTROOT . '/includes/class-wp-test-stream.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::put_contents
 */
class Tests_Filesystem_WpFilesystemDirect_PutContents extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::put_contents()`
	 * returns false for a directory.
	 *
	 * @ticket 57774
	 */
	public function test_should_return_false_for_a_directory() {
		$this->assertFalse( self::$filesystem->put_contents( self::$file_structure['test_dir']['path'], 'New content.' ) );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::put_contents()` inserts
	 * content into the provided file.
	 *
	 * @ticket 57774
	 */
	public function test_should_insert_contents_into_file() {
		$file   = self::$file_structure['test_dir']['path'] . 'file-to-create.txt';
		$actual = self::$filesystem->put_contents( $file, 'New content.', 0644 );
		unlink( $file );

		$this->assertTrue( $actual, 'The contents were not inserted.' );
	}

	/**
	 * Tests that writing to a stream preserves successful and empty writes.
	 *
	 * @ticket 66784
	 *
	 * @dataProvider data_stream_contents
	 *
	 * @param string $contents Contents to write.
	 */
	public function test_should_write_contents_to_stream( $contents ) {
		$actual = $this->put_contents_to_stream( 'WP_Test_Stream', $contents, true );

		$this->assertTrue( $actual['result'], 'The stream write should succeed.' );
		$this->assertSame( $contents, $actual['contents'], 'The stream contents should match.' );
	}

	/**
	 * Provides contents for successful stream writes.
	 *
	 * @return array[]
	 */
	public function data_stream_contents() {
		return array(
			'non-empty' => array( 'New content.' ),
			'empty'     => array( '' ),
		);
	}

	/**
	 * Tests that an incomplete stream write is reported as a failure.
	 *
	 * @ticket 66784
	 */
	public function test_should_return_false_when_stream_write_fails() {
		$actual = $this->put_contents_to_stream( 'WP_Test_Stream_Filesystem_Write_Failure', 'New content.', false );

		$this->assertFalse( $actual['result'] );
	}

	/**
	 * Tests that a stream flush failure is reported even when all bytes were written.
	 *
	 * PHP 8.6 is the first version where fclose() reports flush and close failures.
	 *
	 * @ticket 66784
	 *
	 * @requires PHP >= 8.6
	 */
	public function test_should_return_false_when_stream_flush_fails() {
		$actual = $this->put_contents_to_stream( 'WP_Test_Stream_Filesystem_Flush_Failure', 'New content.', false );

		$this->assertFalse( $actual['result'] );
		$this->assertSame( 'New content.', $actual['contents'], 'The failure should occur after all bytes were written.' );
	}

	/**
	 * Writes through the actual filesystem method, observing permission changes.
	 *
	 * @param string $wrapper_class The stream wrapper class.
	 * @param string $contents      The contents to write.
	 * @param bool   $chmod         Whether permissions should be changed.
	 * @return array The result and the contents accepted by the stream.
	 */
	private function put_contents_to_stream( $wrapper_class, $contents, $chmod ) {
		$protocol   = 'wptestfilesystem';
		$filesystem = $this->getMockBuilder( 'WP_Filesystem_Direct' )
			->setConstructorArgs( array( null ) )
			->onlyMethods( array( 'chmod' ) )
			->getMock();

		$filesystem->expects( $chmod ? $this->once() : $this->never() )
			->method( 'chmod' )
			->with( $protocol . '://WP_Filesystem_Direct/write.txt', 0644 )
			->willReturn( true );

		stream_wrapper_register( $protocol, $wrapper_class );
		WP_Test_Stream::$data = array();

		try {
			$result = $filesystem->put_contents( $protocol . '://WP_Filesystem_Direct/write.txt', $contents, 0644 );

			return array(
				'result'   => $result,
				'contents' => WP_Test_Stream::$data['WP_Filesystem_Direct']['/write.txt'] ?? '',
			);
		} finally {
			stream_wrapper_unregister( $protocol );
			WP_Test_Stream::$data = array();
		}
	}
}

/**
 * A stream wrapper that does not accept data.
 */
class WP_Test_Stream_Filesystem_Write_Failure extends WP_Test_Stream {
	public function stream_write( $data ) {
		return 0;
	}
}

/**
 * A stream wrapper that accepts data but cannot flush it.
 */
class WP_Test_Stream_Filesystem_Flush_Failure extends WP_Test_Stream {
	public function stream_flush() {
		return false;
	}
}
