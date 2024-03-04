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
		add_filter( 'pre_unzip_file', array( $filter, 'filter' ) );

		// Prepare test environment.
		$unzip_destination = self::$test_data_dir . 'archive/';
		mkdir( $unzip_destination );

		_unzip_file_pclzip( self::$test_data_dir . 'archive.zip', $unzip_destination );

		// Cleanup test environment.
		$this->rmdir( $unzip_destination );
		$this->delete_folders( $unzip_destination );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * Tests that _unzip_file_pclzip() applies "unzip_file" filters.
	 *
	 * @ticket 37719
	 */
	public function test_should_apply_unzip_file_filters() {
		$filter = new MockAction();
		add_filter( 'unzip_file', array( $filter, 'filter' ) );

		// Prepare test environment.
		$unzip_destination = self::$test_data_dir . 'archive/';
		mkdir( $unzip_destination );

		_unzip_file_pclzip( self::$test_data_dir . 'archive.zip', $unzip_destination );

		// Cleanup test environment.
		$this->rmdir( $unzip_destination );
		$this->delete_folders( $unzip_destination );

		$this->assertSame( 1, $filter->get_call_count() );
	}
}
