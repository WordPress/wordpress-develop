<?php

/**
 * Tests for wp_delete_file_from_directory().
 *
 * @group functions
 *
 * @covers ::wp_delete_file_from_directory
 */
class Tests_Functions_WpDeleteFileFromDirectory extends WP_UnitTestCase {

	/**
	 * Paths passed to the test stream wrapper's unlink() handler.
	 *
	 * @var string[]
	 */
	public static $unlinked = array();

	public function set_up() {
		parent::set_up();
		self::$unlinked = array();
		stream_wrapper_register( 'wpdeletetest', WpDeleteFileFromDirectory_Stream::class );
	}

	public function tear_down() {
		stream_wrapper_unregister( 'wpdeletetest' );
		parent::tear_down();
	}

	/**
	 * A stream-wrapped path inside the directory is deleted.
	 */
	public function test_deletes_contained_stream_path() {
		$directory = 'wpdeletetest://bucket/uploads';
		$file      = 'wpdeletetest://bucket/uploads/2024/image.jpg';

		$this->assertTrue( wp_delete_file_from_directory( $file, $directory ) );
		$this->assertSame( array( $file ), self::$unlinked );
	}

	/**
	 * A `..` segment must not let a stream-wrapped path escape the directory.
	 *
	 * realpath() resolves `..` for real filesystem paths, but is skipped for
	 * stream wrappers, so the containment check has to reject the traversal
	 * itself rather than delete a file outside the directory.
	 */
	public function test_rejects_stream_path_traversal() {
		$directory = 'wpdeletetest://bucket/uploads';
		$file      = 'wpdeletetest://bucket/uploads/../../secret/keys.json';

		$this->assertFalse( wp_delete_file_from_directory( $file, $directory ) );
		$this->assertSame( array(), self::$unlinked );
	}

	/**
	 * A trailing `..` segment is also rejected.
	 */
	public function test_rejects_trailing_stream_path_traversal() {
		$directory = 'wpdeletetest://bucket/uploads';
		$file      = 'wpdeletetest://bucket/uploads/subdir/..';

		$this->assertFalse( wp_delete_file_from_directory( $file, $directory ) );
		$this->assertSame( array(), self::$unlinked );
	}

	/**
	 * Dots inside a filename are not treated as a traversal.
	 */
	public function test_allows_dots_within_stream_filename() {
		$directory = 'wpdeletetest://bucket/uploads';
		$file      = 'wpdeletetest://bucket/uploads/my..archive.zip';

		$this->assertTrue( wp_delete_file_from_directory( $file, $directory ) );
		$this->assertSame( array( $file ), self::$unlinked );
	}
}

/**
 * Minimal stream wrapper that records the paths passed to unlink().
 */
class WpDeleteFileFromDirectory_Stream {

	public $context;

	public function unlink( $path ) {
		Tests_Functions_WpDeleteFileFromDirectory::$unlinked[] = $path;
		return true;
	}

	public function url_stat( $path, $flags ) {
		return array();
	}

	public function stream_open( $path, $mode, $options, &$opened_path ) {
		return true;
	}
}
