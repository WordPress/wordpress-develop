<?php

/**
 * Tests wp_zip_file_is_valid().
 *
 * @group file
 * @group filesystem
 *
 * @covers ::wp_zip_file_is_valid
 */
class Tests_Filesystem_WpZipFileIsValid extends WP_UnitTestCase {

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
	 * Tests ZIP file validity is correctly determined.
	 *
	 * @ticket 60398
	 *
	 * @dataProvider data_zip_file_validity
	 *
	 * @param string $file     The ZIP file to test.
	 * @param bool   $expected Whether the ZIP file is expected to be valid.
	 */
	public function test_zip_file_validity( $file, $expected ) {
		$zip_file = self::$test_data_dir . $file;

		$expected_message = $expected ? 'valid' : 'invalid';
		$this->assertSame( $expected, wp_zip_file_is_valid( $zip_file ), "Expected archive to be {$expected_message}." );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_zip_file_validity() {
		return array(
			'valid zip file'                               => array( 'archive.zip', true ),
			'valid zip file created by macOS context menu' => array( 'archive-macos.zip', true ),
			'invalid zip file'                             => array( 'archive-invalid.zip', false ),
			'invalid file extension'                       => array( 'archive-invalid-ext.md', false ),
		);
	}
}
