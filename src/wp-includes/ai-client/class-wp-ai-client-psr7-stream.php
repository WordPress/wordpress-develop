<?php
/**
 * WP AI Client: WP_AI_Client_PSR7_Stream class
 *
 * @package WordPress
 * @subpackage AI
 * @since 6.8.0
 */

use Psr\Http\Message\StreamInterface;

/**
 * Minimal string-backed PSR-7 stream implementation.
 *
 * Provides the StreamInterface methods needed by the AI Client HTTP transport
 * layer without requiring PHP stream resources.
 *
 * @since 6.8.0
 */
class WP_AI_Client_PSR7_Stream implements StreamInterface {

	/**
	 * The string content of the stream.
	 *
	 * @since 6.8.0
	 * @var string
	 */
	private $content;

	/**
	 * Current read/write offset position.
	 *
	 * @since 6.8.0
	 * @var int
	 */
	private $offset = 0;

	/**
	 * Constructor.
	 *
	 * @since 6.8.0
	 *
	 * @param string $content Initial content for the stream.
	 */
	public function __construct( string $content = '' ) {
		$this->content = $content;
	}

	/**
	 * Reads all data from the stream into a string.
	 *
	 * @since 6.8.0
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->content;
	}

	/**
	 * Closes the stream. No-op for string-backed streams.
	 *
	 * @since 6.8.0
	 */
	public function close(): void {
		// No-op.
	}

	/**
	 * Separates any underlying resources from the stream.
	 *
	 * @since 6.8.0
	 *
	 * @return resource|null Always null for string-backed streams.
	 */
	public function detach() {
		return null;
	}

	/**
	 * Gets the size of the stream.
	 *
	 * @since 6.8.0
	 *
	 * @return int|null The size in bytes.
	 */
	public function getSize(): ?int {
		return strlen( $this->content );
	}

	/**
	 * Returns the current position of the read/write pointer.
	 *
	 * @since 6.8.0
	 *
	 * @return int Position of the pointer.
	 */
	public function tell(): int {
		return $this->offset;
	}

	/**
	 * Returns true if the stream is at the end.
	 *
	 * @since 6.8.0
	 *
	 * @return bool
	 */
	public function eof(): bool {
		return $this->offset >= strlen( $this->content );
	}

	/**
	 * Returns whether the stream is seekable.
	 *
	 * @since 6.8.0
	 *
	 * @return bool Always true.
	 */
	public function isSeekable(): bool {
		return true;
	}

	/**
	 * Seeks to a position in the stream.
	 *
	 * @since 6.8.0
	 *
	 * @param int $offset Stream offset.
	 * @param int $whence One of SEEK_SET, SEEK_CUR, or SEEK_END.
	 */
	public function seek( int $offset, int $whence = SEEK_SET ): void {
		$length = strlen( $this->content );

		switch ( $whence ) {
			case SEEK_SET:
				$this->offset = $offset;
				break;
			case SEEK_CUR:
				$this->offset += $offset;
				break;
			case SEEK_END:
				$this->offset = $length + $offset;
				break;
		}

		if ( $this->offset < 0 ) {
			$this->offset = 0;
		}
	}

	/**
	 * Seeks to the beginning of the stream.
	 *
	 * @since 6.8.0
	 */
	public function rewind(): void {
		$this->offset = 0;
	}

	/**
	 * Returns whether the stream is writable.
	 *
	 * @since 6.8.0
	 *
	 * @return bool Always true.
	 */
	public function isWritable(): bool {
		return true;
	}

	/**
	 * Writes data to the stream.
	 *
	 * @since 6.8.0
	 *
	 * @param string $string The string to write.
	 * @return int Number of bytes written.
	 */
	public function write( string $string ): int {
		$this->content .= $string;
		$length         = strlen( $string );
		$this->offset  += $length;

		return $length;
	}

	/**
	 * Returns whether the stream is readable.
	 *
	 * @since 6.8.0
	 *
	 * @return bool Always true.
	 */
	public function isReadable(): bool {
		return true;
	}

	/**
	 * Reads data from the stream.
	 *
	 * @since 6.8.0
	 *
	 * @param int $length Number of bytes to read.
	 * @return string Data read from the stream.
	 */
	public function read( int $length ): string {
		$data          = substr( $this->content, $this->offset, $length );
		$this->offset += strlen( $data );

		return $data;
	}

	/**
	 * Returns the remaining contents of the stream.
	 *
	 * @since 6.8.0
	 *
	 * @return string
	 */
	public function getContents(): string {
		$remaining    = substr( $this->content, $this->offset );
		$this->offset = strlen( $this->content );

		return $remaining;
	}

	/**
	 * Gets stream metadata.
	 *
	 * @since 6.8.0
	 *
	 * @param string|null $key Specific metadata to retrieve.
	 * @return array|mixed|null Returns null for specific keys, empty array otherwise.
	 */
	public function getMetadata( ?string $key = null ) {
		if ( null !== $key ) {
			return null;
		}

		return array();
	}
}
