<?php

/**
 * Tests _unzip_file_pclzip().
 *
 * @group file
 * @group filesystem
 *
 * @covers ::_unzip_file_pclzip
 */
class Tests_Filesystem_UnzipFilePclzip extends WP_UnitTestCase {

	/**
	 * The test data directory.
	 *
	 * @var string $test_data_dir
	 */
	private static $test_data_dir;

	/**
	 * Sets up the filesystem and test data directory property
	 * before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		self::$test_data_dir = DIR_TESTDATA . '/filesystem/';
	}

	/**
	 * Tests that _unzip_file_pclzip() applies "pre_unzip_file" filters.
	 *
	 * @ticket 37719
	 */
	public function test_should_apply_pre_unzip_file_filters() {
		$filter = new MockAction();
		add_filter( 'pre_unzip_file', array( $filter, 'filter' ), 10, 2 );

		// Prepare test environment.
		$unzip_destination = self::$test_data_dir . 'archive/';
		if ( file_exists( $unzip_destination ) ) {
			$this->rmdir( $unzip_destination );
			$this->delete_folders( $unzip_destination );
		}
		mkdir( $unzip_destination );

		_unzip_file_pclzip( self::$test_data_dir . 'archive.zip', $unzip_destination );

		// Clean up test environment.
		$this->rmdir( $unzip_destination );
		$this->delete_folders( $unzip_destination );

		$this->assertSame( 1, $filter->get_call_count(), 'The filter should be called once.' );
		$this->assertSame( self::$test_data_dir . 'archive.zip', $filter->get_args()[0][1], 'The $file parameter should be correct.' );
	}

	/**
	 * Tests that _unzip_file_pclzip() applies "unzip_file" filters.
	 *
	 * @ticket 37719
	 */
	public function test_should_apply_unzip_file_filters() {
		$filter = new MockAction();
		add_filter( 'unzip_file', array( $filter, 'filter' ), 10, 2 );

		// Prepare test environment.
		$unzip_destination = self::$test_data_dir . 'archive/';
		if ( file_exists( $unzip_destination ) ) {
			$this->rmdir( $unzip_destination );
			$this->delete_folders( $unzip_destination );
		}
		mkdir( $unzip_destination );

		_unzip_file_pclzip( self::$test_data_dir . 'archive.zip', $unzip_destination );

		// Clean up test environment.
		$this->rmdir( $unzip_destination );
		$this->delete_folders( $unzip_destination );

		$this->assertSame( 1, $filter->get_call_count(), 'The filter should be called once.' );
		$this->assertSame( self::$test_data_dir . 'archive.zip', $filter->get_args()[0][1], 'The $file parameter should be correct.' );
	}

	/**
	 * Tests that _unzip_file_pclzip() extracts zero-byte files.
	 *
	 * @ticket 52018
	 */
	public function test_should_extract_empty_files() {
		// Prepare test environment.
		$unzip_destination = self::$test_data_dir . 'archive/';
		if ( file_exists( $unzip_destination ) ) {
			$this->rmdir( $unzip_destination );
			$this->delete_folders( $unzip_destination );
		}
		mkdir( $unzip_destination );

		$result = _unzip_file_pclzip( self::$test_data_dir . 'archive.zip', $unzip_destination );

		$file_exists      = file_exists( $unzip_destination . 'File.txt' );
		$file_contents    = $file_exists ? file_get_contents( $unzip_destination . 'File.txt' ) : null;
		$subfile_exists   = file_exists( $unzip_destination . 'Subdirectory/Subfile.txt' );
		$subfile_contents = $subfile_exists ? file_get_contents( $unzip_destination . 'Subdirectory/Subfile.txt' ) : null;

		// Clean up test environment.
		$this->rmdir( $unzip_destination );
		$this->delete_folders( $unzip_destination );

		$this->assertTrue( $result, 'The archive should be extracted.' );
		$this->assertTrue( $file_exists, 'File.txt should be extracted.' );
		$this->assertSame( '', $file_contents, 'File.txt should be empty.' );
		$this->assertTrue( $subfile_exists, 'Subdirectory/Subfile.txt should be extracted.' );
		$this->assertSame( '', $subfile_contents, 'Subdirectory/Subfile.txt should be empty.' );
	}
}
