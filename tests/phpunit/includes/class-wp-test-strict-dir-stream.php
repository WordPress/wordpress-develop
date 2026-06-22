<?php

/**
 * Class WP_Test_Strict_Dir_Stream.
 *
 * A WP_Test_Stream variant that only treats stream-wrapper directories as
 * directories when the path ends in a trailing slash.
 */
class WP_Test_Strict_Dir_Stream extends WP_Test_Stream {

	/**
	 * Initializes internal state for the provided URL.
	 *
	 * @param string $url A URL of the form "protocol://bucket/path".
	 */
	private function open_strict( $url ) {
		$components = array_merge(
			array(
				'host' => '',
				'path' => '',
			),
			parse_url( $url )
		);

		$this->bucket = $components['host'];
		$this->file   = $components['path'] ? $components['path'] : '/';

		if ( empty( $this->bucket ) ) {
			throw new Exception( 'Cannot use an empty bucket name' );
		}

		if ( ! isset( WP_Test_Stream::$data[ $this->bucket ] ) ) {
			WP_Test_Stream::$data[ $this->bucket ] = array();
		}

		$this->data_ref = null;
		if ( array_key_exists( $this->file, WP_Test_Stream::$data[ $this->bucket ] ) ) {
			$this->data_ref =& WP_Test_Stream::$data[ $this->bucket ][ $this->file ];
		}

		$this->position = 0;
	}

	/**
	 * Creates a file metadata object, with defaults.
	 *
	 * @param array $stats Partial file metadata.
	 * @return array Complete file metadata.
	 */
	private function make_strict_stat( $stats ) {
		$defaults = array(
			'dev'     => 0,
			'ino'     => 0,
			'mode'    => 0,
			'nlink'   => 0,
			'uid'     => 0,
			'gid'     => 0,
			'rdev'    => 0,
			'size'    => 0,
			'atime'   => 0,
			'mtime'   => 0,
			'ctime'   => 0,
			'blksize' => 0,
			'blocks'  => 0,
		);

		return array_merge( $defaults, $stats );
	}

	/**
	 * Retrieves information about a file.
	 *
	 * @see WP_Test_Stream::stream_stat()
	 *
	 * @return array|false File stats on success, false on failure.
	 */
	public function stream_stat() {
		if ( '/' === substr( $this->file, -1 ) ) {
			if ( ! isset( WP_Test_Stream::$data[ $this->bucket ][ $this->file ] ) ) {
				return false;
			}

			return $this->make_strict_stat(
				array(
					'mode' => WP_Test_Stream::DIRECTORY_MODE,
				)
			);
		}

		if ( ! isset( $this->data_ref ) ) {
			return false;
		}

		return $this->make_strict_stat(
			array(
				'size' => strlen( $this->data_ref ),
				'mode' => WP_Test_Stream::FILE_MODE,
			)
		);
	}

	/**
	 * Retrieves information about a file.
	 *
	 * @see WP_Test_Stream::url_stat()
	 *
	 * @param string $path  Path to get information about.
	 * @param int    $flags Bitmask of STREAM_URL_STAT_* constants.
	 * @return array|false File stats on success, false on failure.
	 */
	public function url_stat( $path, $flags ) {
		$this->open_strict( $path );
		return $this->stream_stat();
	}
}
