<?php

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-ftpext.php';

/**
 * Tests for the WP_Filesystem_FTPext::put_contents() method.
 *
 * @group filesystem
 *
 * @covers WP_Filesystem_FTPext::put_contents
 */
class Tests_Filesystem_WpFilesystemFtpext_PutContents extends WP_UnitTestCase {

	/**
	 * Returns the temporary files wp_tempnam() would create for the test file.
	 *
	 * @return string[] Paths of the matching temporary files.
	 */
	private function get_temp_files() {
		return glob( get_temp_dir() . 'wp-ftpext-64519-*' );
	}

	/**
	 * @ticket 64519
	 *
	 * @dataProvider data_put_contents_without_connection
	 *
	 * @param mixed $link The FTP connection value.
	 */
	public function test_put_contents_without_connection_leaves_no_temp_file( $link ) {
		$reflection       = new ReflectionClass( 'WP_Filesystem_FTPext' );
		$filesystem       = $reflection->newInstanceWithoutConstructor();
		$filesystem->link = $link;

		$before = $this->get_temp_files();

		$this->assertFalse( $filesystem->put_contents( '/var/www/wp-ftpext-64519.php', 'contents' ), 'put_contents() should return false without an FTP connection.' );
		$this->assertSame( $before, $this->get_temp_files(), 'put_contents() should not leave a temporary file behind.' );
	}

	/**
	 * Since PHP 8.1 an FTP connection is an FTP\Connection object, and using it after ftp_close() throws an Error.
	 * Before that it is a resource, and using a closed one only raises a warning.
	 *
	 * @ticket 64519
	 *
	 * @requires extension ftp
	 * @requires PHP >= 8.1
	 */
	public function test_put_contents_on_closed_connection_leaves_no_temp_file() {
		// A minimal FTP server that sends the greeting and answers QUIT, so a real connection can be opened and closed.
		$server = stream_socket_server( 'tcp://127.0.0.1:0', $errno, $errstr );
		$this->assertNotFalse( $server, "Could not start the test server: $errstr" );
		$port = (int) substr( strrchr( stream_socket_get_name( $server, false ), ':' ), 1 );
		fclose( $server );

		$script  = '$s = stream_socket_server( "tcp://127.0.0.1:' . $port . '" ); echo "ready\n";';
		$script .= '$c = stream_socket_accept( $s, 10 ); fwrite( $c, "220 ready\r\n" );';
		$script .= 'while ( false !== ( $line = fgets( $c ) ) ) { if ( 0 === stripos( $line, "QUIT" ) ) { fwrite( $c, "221 bye\r\n" ); break; } fwrite( $c, "502 no\r\n" ); }';
		$process = proc_open( array( PHP_BINARY, '-r', $script ), array( 1 => array( 'pipe', 'w' ) ), $pipes );
		$this->assertIsResource( $process, 'Could not start the test server process.' );
		fgets( $pipes[1] );

		$link = ftp_connect( '127.0.0.1', $port, 10 );
		$this->assertNotFalse( $link, 'Could not connect to the test server.' );
		ftp_close( $link );
		proc_close( $process );

		$reflection       = new ReflectionClass( 'WP_Filesystem_FTPext' );
		$filesystem       = $reflection->newInstanceWithoutConstructor();
		$filesystem->link = $link;

		$before = $this->get_temp_files();

		try {
			$filesystem->put_contents( '/var/www/wp-ftpext-64519.php', 'contents' );
		} catch ( Error $e ) {
			// A closed connection throws; the temporary file must still be removed.
			unset( $e );
		}

		$this->assertSame( $before, $this->get_temp_files(), 'put_contents() should not leave a temporary file behind when the upload throws.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_put_contents_without_connection() {
		return array(
			'null'  => array( null ),
			'false' => array( false ),
		);
	}
}
