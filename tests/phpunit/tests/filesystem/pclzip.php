<?php

/**
 * Tests the PclZip class.
 *
 * @group file
 * @group filesystem
 * @group pclzip
 */
class Tests_Filesystem_PclZip extends WP_UnitTestCase {

	/**
	 * The test data directory.
	 *
	 * @var string $test_data_dir
	 */
	private static $test_data_dir;

	/**
	 * Temporary files to remove after each test.
	 *
	 * @var string[]
	 */
	private $temp_files = array();

	/**
	 * Loads PclZip and sets the test data directory property
	 * before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		self::$test_data_dir = DIR_TESTDATA . '/filesystem/';
	}

	/**
	 * Removes temporary files created by a test.
	 */
	public function tear_down() {
		foreach ( $this->temp_files as $temp_file ) {
			if ( file_exists( $temp_file ) ) {
				unlink( $temp_file );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests that extracting an empty file to the output does not read from the archive.
	 *
	 * @ticket 52018
	 *
	 * @covers PclZip::extract
	 */
	public function test_extract_in_output_should_output_empty_file() {
		$archive = new PclZip( self::$test_data_dir . 'archive.zip' );

		$this->expectOutputString( '' );

		$result = $archive->extract( PCLZIP_OPT_BY_NAME, 'File.txt', PCLZIP_OPT_EXTRACT_IN_OUTPUT );

		$this->assertIsArray( $result, 'The extraction should return a list of entries.' );
		$this->assertCount( 1, $result, 'Only File.txt should be extracted.' );
		$this->assertSame( 'File.txt', $result[0]['filename'], 'The entry filename should be correct.' );
		$this->assertSame( 0, $result[0]['size'], 'The entry should be empty.' );
		$this->assertSame( 'ok', $result[0]['status'], 'The entry should be extracted.' );
	}

	/**
	 * Tests that an empty file can be added to an archive.
	 *
	 * @ticket 54036
	 * @ticket 52018
	 *
	 * @covers PclZip::create
	 */
	public function test_create_should_add_empty_file() {
		$empty_file = wp_tempnam( 'pclzip-empty' );
		$zip_path   = wp_tempnam( 'pclzip-archive' ) . '.zip';

		$this->temp_files = array( $empty_file, $zip_path );

		$archive = new PclZip( $zip_path );
		$result  = $archive->create( $empty_file, PCLZIP_OPT_REMOVE_ALL_PATH, PCLZIP_OPT_TEMP_FILE_OFF );

		$this->assertIsArray( $result, 'The archive should be created.' );
		$this->assertCount( 1, $result, 'The archive should contain one entry.' );
		$this->assertSame( 'ok', $result[0]['status'], 'The empty file should be added.' );

		$contents = $archive->extract( PCLZIP_OPT_EXTRACT_AS_STRING );

		$this->assertIsArray( $contents, 'The archive should be extractable.' );
		$this->assertSame( 0, $contents[0]['size'], 'The archived file should be empty.' );
		$this->assertSame( '', $contents[0]['content'], 'The archived file content should be empty.' );
	}
}
