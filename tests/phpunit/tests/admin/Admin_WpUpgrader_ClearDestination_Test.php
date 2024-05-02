<?php

require_once __DIR__ . '/Admin_WpUpgrader_TestCase.php';

/**
 * @group admin
 * @group upgrade
 * @covers WP_Upgrader::clear_destination()
 */
class Admin_WpUpgrader_ClearDestination_Test extends Admin_WpUpgrader_TestCase {

	/**
	 * Tests that `WP_Upgrader::clear_destination()` returns early with `true`
	 * when the destination does not exist.
	 *
	 * @ticket 54245
	 */
	public function test_clear_destination_should_return_early_when_the_destination_does_not_exist() {
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'is_writable' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'chmod' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'delete' );

		$destination = DIR_TESTDATA . '/upgrade/';

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'dirlist' )
				->with( $destination )
				->willReturn( false );

		$this->assertTrue( self::$instance->clear_destination( $destination ) );
	}

	/**
	 * Tests that `WP_Upgrader::clear_destination()` clears
	 * the destination directory.
	 *
	 * @ticket 54245
	 */
	public function test_clear_destination_should_clear_the_destination_directory() {
		$destination = DIR_TESTDATA . '/upgrade/';

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'dirlist' )
				->with( $destination )
				->willReturn( array() );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'delete' )
				->with( $destination )
				->willReturn( true );

		$this->assertTrue( self::$instance->clear_destination( $destination ) );
	}

	/**
	 * Tests that `WP_Upgrader::clear_destination()` returns a WP_Error object
	 * if files are not writable.
	 *
	 * This test runs in a separate process so that it can define
	 * constants without impacting other tests.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed." when running in a
	 * separate process.
	 *
	 * @ticket 54245
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_clear_destination_should_return_wp_error_if_files_are_not_writable() {
		define( 'FS_CHMOD_FILE', 0644 );
		define( 'FS_CHMOD_DIR', 0755 );

		self::$instance->generic_strings();

		self::$wp_filesystem_mock->expects( $this->never() )->method( 'delete' );

		$destination = DIR_TESTDATA . '/upgrade/';
		$dirlist     = array(
			'file1.php' => array(
				'name' => 'file1.php',
				'type' => 'f',
			),
			'subdir'    => array(
				'name' => 'subdir',
				'type' => 'd',
			),
		);

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'dirlist' )
				->with( $destination )
				->willReturn( $dirlist );

		$unwritable_checks = array(
			array( $destination . 'file1.php' ),
			array( $destination . 'file1.php' ),
			array( $destination . 'subdir' ),
			array( $destination . 'subdir' ),
		);

		self::$wp_filesystem_mock
				->expects( $this->exactly( 4 ) )
				->method( 'is_writable' )
				->withConsecutive( ...$unwritable_checks )
				->willReturn( false );

		$actual = self::$instance->clear_destination( $destination );

		$this->assertWPError(
			$actual,
			'WP_Upgrader::clear_destination() did not return a WP_Error object'
		);

		$this->assertSame(
			'files_not_writable',
			$actual->get_error_code(),
			'Unexpected WP_Error code'
		);

		$this->assertSameSets(
			array( 'file1.php, subdir' ),
			$actual->get_all_error_data(),
			'Unexpected WP_Error data'
		);
	}
}
