<?php
/**
 * Tests for the WP_Filesystem_Direct::move() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::move
 */
class Tests_Filesystem_WpFilesystemDirect_Move extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::copy()` overwrites an existing
	 * destination when overwriting is enabled.
	 *
	 * @ticket 57774
	 */
	public function test_should_overwrite_an_existing_file_when_overwriting_is_enabled() {
		$source      = self::$file_structure['visible_file']['path'];
		$destination = self::$file_structure['test_dir']['path'] . 'a_file_that_exists.dest';
		$actual      = self::$filesystem->move( $source, $destination, true );

		rename( $destination, $source );

		$this->assertTrue( $actual );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::move()` does not overwrite
	 * an existing destination when overwriting is disabled.
	 *
	 * @ticket 57774
	 */
	public function test_should_not_overwrite_an_existing_file_when_overwriting_is_disabled() {
		$source      = self::$file_structure['visible_file']['path'];
		$destination = self::$file_structure['subfile']['path'];
		$actual      = self::$filesystem->move( $source, $destination );

		$this->assertFalse( $actual );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::move()` moves directories.
	 *
	 * @ticket 57774
	 */
	public function test_should_move_directories() {
		$source      = self::$file_structure['test_dir']['path'];
		$destination = untrailingslashit( self::$file_structure['test_dir']['path'] ) . '-dest';
		$actual      = self::$filesystem->move( $source, $destination, true );

		$source_exists      = is_dir( $source );
		$destination_exists = is_dir( $destination );

		if ( $actual ) {
			$restored = rename( $destination, $source );
		}

		$this->assertTrue( $actual, 'The directory was not moved.' );
		$this->assertFalse( $source_exists, 'The source still exists.' );
		$this->assertTrue( $destination_exists, 'The destination does not exist.' );
		$this->assertTrue( $restored, 'The test assets were not cleaned up after the test.' );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::move()` returns false for an
	 * invalid destination.
	 *
	 * @ticket 57774
	 */
	public function test_should_return_false_for_invalid_destination() {
		$source      = self::$file_structure['test_dir']['path'];
		$destination = 'http://example.org';

		$this->assertFalse( self::$filesystem->move( $source, $destination, true ) );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::move()` returns false for an
	 * invalid destination.
	 *
	 * @ticket 57774
	 */
	public function test_should_return_false_when_overwriting_is_enabled_the_destination_exists_but_cannot_be_deleted() {
		global $wp_filesystem;
		$wpfilesystem_backup = $wp_filesystem;

		// Force failure conditions.
		$filesystem_mock = $this->getMockBuilder( 'WP_Filesystem_Direct' )
								// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
								->setMethods( array( 'exists', 'delete' ) )
								->setConstructorArgs( array( null ) )
								->getMock();

		$filesystem_mock->expects( $this->once() )->method( 'exists' )->willReturn( true );
		$filesystem_mock->expects( $this->once() )->method( 'delete' )->willReturn( false );
		$wp_filesystem = $filesystem_mock;

		$actual = $wp_filesystem->move(
			self::$file_structure['test_dir']['path'],
			self::$file_structure['subdir']['path'],
			true
		);

		// Restore the filesystem.
		$wp_filesystem = $wpfilesystem_backup;

		$this->assertFalse( $actual );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::move()` falls back to a single
	 * file copy when the source and destination do not exist.
	 *
	 * @ticket 57774
	 */
	public function test_should_fall_back_to_single_file_copy_when_source_and_destination_do_not_exist() {
		global $wp_filesystem;

		$source      = self::$file_structure['test_dir']['path'] . 'a_file_that_does_not_exist.txt';
		$destination = self::$file_structure['test_dir']['path'] . 'another_file_that_does_not_exist.txt';

		// Set up mock filesystem.
		$filesystem_mock = $this->getMockBuilder( 'WP_Filesystem_Direct' )
								->setConstructorArgs( array( null ) )
								// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
								->setMethods( array( 'exists', 'delete', 'is_file', 'copy' ) )
								->getMock();

		$filesystem_mock->expects( $this->exactly( 2 ) )->method( 'exists' )->willReturn( array( true, true ) );
		$filesystem_mock->expects( $this->exactly( 2 ) )->method( 'delete' )->willReturn( array( true, false ) );
		$filesystem_mock->expects( $this->once() )->method( 'is_file' )->willReturn( true );
		$filesystem_mock->expects( $this->once() )->method( 'copy' )->willReturn( true );

		$wp_filesystem_backup = $wp_filesystem;
		$wp_filesystem        = $filesystem_mock;

		$actual        = $filesystem_mock->move( $source, $destination, true );
		$wp_filesystem = $wp_filesystem_backup;

		$this->assertTrue( $actual );
	}
}
