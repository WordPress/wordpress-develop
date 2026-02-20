<?php
/**
 * Tests for WP_AI_Client_PSR7_Stream.
 *
 * @group ai-client
 * @covers WP_AI_Client_PSR7_Stream
 */
class Tests_AI_Client_PSR7_Stream extends WP_UnitTestCase {

	/**
	 * Test that the stream implements the scoped PSR-7 StreamInterface.
	 *
	 * @ticket 64591
	 */
	public function test_implements_stream_interface() {
		$stream = new WP_AI_Client_PSR7_Stream();
		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface::class,
			$stream
		);
	}

	/**
	 * Test __toString returns the full content.
	 *
	 * @ticket 64591
	 */
	public function test_to_string() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello world' );
		$this->assertSame( 'hello world', (string) $stream );
	}

	/**
	 * Test close is a no-op and does not error.
	 *
	 * @ticket 64591
	 */
	public function test_close_noop() {
		$stream = new WP_AI_Client_PSR7_Stream( 'data' );
		$stream->close();
		$this->assertSame( 'data', (string) $stream );
	}

	/**
	 * Test detach returns null.
	 *
	 * @ticket 64591
	 */
	public function test_detach_returns_null() {
		$stream = new WP_AI_Client_PSR7_Stream( 'data' );
		$this->assertNull( $stream->detach() );
	}

	/**
	 * Test getSize returns the string length.
	 *
	 * @ticket 64591
	 */
	public function test_get_size() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello' );
		$this->assertSame( 5, $stream->getSize() );
	}

	/**
	 * Test getSize returns 0 for empty stream.
	 *
	 * @ticket 64591
	 */
	public function test_get_size_empty() {
		$stream = new WP_AI_Client_PSR7_Stream();
		$this->assertSame( 0, $stream->getSize() );
	}

	/**
	 * Test tell returns 0 initially.
	 *
	 * @ticket 64591
	 */
	public function test_tell_initial() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello' );
		$this->assertSame( 0, $stream->tell() );
	}

	/**
	 * Test tell advances after read.
	 *
	 * @ticket 64591
	 */
	public function test_tell_after_read() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello' );
		$stream->read( 3 );
		$this->assertSame( 3, $stream->tell() );
	}

	/**
	 * Test eof returns false initially for non-empty stream.
	 *
	 * @ticket 64591
	 */
	public function test_eof_false_initially() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello' );
		$this->assertFalse( $stream->eof() );
	}

	/**
	 * Test eof returns true at end of stream.
	 *
	 * @ticket 64591
	 */
	public function test_eof_true_at_end() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hi' );
		$stream->read( 2 );
		$this->assertTrue( $stream->eof() );
	}

	/**
	 * Test eof returns true for empty stream.
	 *
	 * @ticket 64591
	 */
	public function test_eof_true_for_empty() {
		$stream = new WP_AI_Client_PSR7_Stream();
		$this->assertTrue( $stream->eof() );
	}

	/**
	 * Test isSeekable returns true.
	 *
	 * @ticket 64591
	 */
	public function test_is_seekable() {
		$stream = new WP_AI_Client_PSR7_Stream();
		$this->assertTrue( $stream->isSeekable() );
	}

	/**
	 * Test seek with SEEK_SET.
	 *
	 * @ticket 64591
	 */
	public function test_seek_set() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello world' );
		$stream->seek( 6 );
		$this->assertSame( 6, $stream->tell() );
		$this->assertSame( 'world', $stream->getContents() );
	}

	/**
	 * Test seek with SEEK_CUR.
	 *
	 * @ticket 64591
	 */
	public function test_seek_cur() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello world' );
		$stream->read( 3 );
		$stream->seek( 2, SEEK_CUR );
		$this->assertSame( 5, $stream->tell() );
	}

	/**
	 * Test seek with SEEK_END.
	 *
	 * @ticket 64591
	 */
	public function test_seek_end() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello' );
		$stream->seek( -2, SEEK_END );
		$this->assertSame( 3, $stream->tell() );
		$this->assertSame( 'lo', $stream->getContents() );
	}

	/**
	 * Test negative seek clamps to 0.
	 *
	 * @ticket 64591
	 */
	public function test_seek_negative_clamps_to_zero() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello' );
		$stream->seek( -100 );
		$this->assertSame( 0, $stream->tell() );
	}

	/**
	 * Test rewind resets offset to 0.
	 *
	 * @ticket 64591
	 */
	public function test_rewind() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello' );
		$stream->read( 3 );
		$stream->rewind();
		$this->assertSame( 0, $stream->tell() );
	}

	/**
	 * Test isWritable returns true.
	 *
	 * @ticket 64591
	 */
	public function test_is_writable() {
		$stream = new WP_AI_Client_PSR7_Stream();
		$this->assertTrue( $stream->isWritable() );
	}

	/**
	 * Test write appends data and returns byte count.
	 *
	 * @ticket 64591
	 */
	public function test_write() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello' );
		$bytes  = $stream->write( ' world' );

		$this->assertSame( 6, $bytes );
		$this->assertSame( 'hello world', (string) $stream );
	}

	/**
	 * Test write advances offset.
	 *
	 * @ticket 64591
	 */
	public function test_write_advances_offset() {
		$stream = new WP_AI_Client_PSR7_Stream();
		$stream->write( 'abc' );
		$this->assertSame( 3, $stream->tell() );
	}

	/**
	 * Test isReadable returns true.
	 *
	 * @ticket 64591
	 */
	public function test_is_readable() {
		$stream = new WP_AI_Client_PSR7_Stream();
		$this->assertTrue( $stream->isReadable() );
	}

	/**
	 * Test read returns correct bytes and advances offset.
	 *
	 * @ticket 64591
	 */
	public function test_read() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello world' );
		$data   = $stream->read( 5 );

		$this->assertSame( 'hello', $data );
		$this->assertSame( 5, $stream->tell() );
	}

	/**
	 * Test read at end of stream returns empty string.
	 *
	 * @ticket 64591
	 */
	public function test_read_at_end_returns_empty() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hi' );
		$stream->read( 2 );
		$this->assertSame( '', $stream->read( 5 ) );
	}

	/**
	 * Test getContents returns remaining data.
	 *
	 * @ticket 64591
	 */
	public function test_get_contents() {
		$stream = new WP_AI_Client_PSR7_Stream( 'hello world' );
		$stream->read( 6 );
		$this->assertSame( 'world', $stream->getContents() );
	}

	/**
	 * Test getMetadata returns empty array without key.
	 *
	 * @ticket 64591
	 */
	public function test_get_metadata_no_key() {
		$stream = new WP_AI_Client_PSR7_Stream();
		$this->assertSame( array(), $stream->getMetadata() );
	}

	/**
	 * Test getMetadata returns null for any key.
	 *
	 * @ticket 64591
	 */
	public function test_get_metadata_with_key() {
		$stream = new WP_AI_Client_PSR7_Stream();
		$this->assertNull( $stream->getMetadata( 'mode' ) );
	}
}
