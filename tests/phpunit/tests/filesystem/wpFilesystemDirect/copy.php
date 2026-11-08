<?php
/**
 * Tests for the WP_Filesystem_Direct::copy() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::copy
 */
class Tests_Filesystem_WpFilesystemDirect_Copy extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::copy()` overwrites an existing
	 * destination when overwriting is enabled.
	 *
	 * @ticket 57774
	 */
	public function test_should_overwrite_an_existing_file_when_overwriting_is_enabled() {
		$source      = self::$file_structure['visible_file']['path'];
		$destination = self::$file_structure['test_dir']['path'] . 'a_file_that_exists.dest';

		if ( ! file_exists( $destination ) ) {
			touch( $destination );
		}

		$actual = self::$filesystem->copy( $source, $destination, true );

		unlink( $destination );

		$this->assertTrue( $actual );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::copy()` does not overwrite
	 * an existing destination when overwriting is disabled.
	 *
	 * @ticket 57774
	 */
	public function test_should_not_overwrite_an_existing_file_when_overwriting_is_disabled() {
		$source      = self::$file_structure['test_dir']['path'] . 'a_file_that_exists.txt';
		$destination = self::$file_structure['test_dir']['path'] . 'a_file_that_exists.dest';

		if ( ! file_exists( $destination ) ) {
			touch( $destination );
		}

		$actual = self::$filesystem->copy( $source, $destination );

		unlink( $destination );

		$this->assertFalse( $actual );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::copy()` does not overwrite an existing
	 * destination when overwriting is enabled and the source and destination
	 * are the same.
	 *
	 * @ticket 57774
	 */
	public function test_should_not_overwrite_when_overwriting_is_enabled_and_source_and_destination_are_the_same() {
		$source = self::$file_structure['test_dir']['path'] . 'a_file_that_exists.txt';
		$this->assertFalse( self::$filesystem->copy( $source, $source, true ) );
	}
}
