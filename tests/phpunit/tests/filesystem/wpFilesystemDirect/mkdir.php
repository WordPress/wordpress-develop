<?php
/**
 * Tests for the WP_Filesystem_Direct::mkdir() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::mkdir
 */
class Tests_Filesystem_WpFilesystemDirect_Mkdir extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::mkdir()` creates a directory.
	 *
	 * This test runs in a separate process so that it can define
	 * constants without impacting other tests.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed." when running in a
	 * separate process.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_should_create_directory
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param mixed $path The path to create.
	 */
	public function test_should_create_directory( $path ) {
		define( 'FS_CHMOD_DIR', 0755 );

		$path   = str_replace( 'TEST_DIR', self::$file_structure['test_dir']['path'], $path );
		$actual = self::$filesystem->mkdir( $path );

		if ( $path !== self::$file_structure['test_dir']['path'] && is_dir( $path ) ) {
			rmdir( $path );
		}

		$this->assertTrue( $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_create_directory() {
		return array(
			'no trailing slash' => array(
				'path' => 'TEST_DIR/directory-to-create',
			),
			'a trailing slash'  => array(
				'path' => 'TEST_DIR/directory-to-create/',
			),
		);
	}

	/**
	 * Tests that `WP_Filesystem_Direct::mkdir()` does not create a directory.
	 *
	 * This test runs in a separate process so that it can define
	 * constants without impacting other tests.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed." when running in a
	 * separate process.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_should_not_create_directory
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param mixed $path     The path to create.
	 */
	public function test_should_not_create_directory( $path ) {
		define( 'FS_CHMOD_DIR', 0755 );

		$path   = str_replace( 'TEST_DIR', self::$file_structure['test_dir']['path'], $path );
		$actual = self::$filesystem->mkdir( $path );

		if ( $path !== self::$file_structure['test_dir']['path'] && is_dir( $path ) ) {
			rmdir( $path );
		}

		$this->assertFalse( $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_not_create_directory() {
		return array(
			'empty path'         => array(
				'path' => '',
			),
			'a path that exists' => array(
				'path' => 'TEST_DIR',
			),
		);
	}

	/**
	 * Tests that `WP_Filesystem_Direct::mkdir()` sets chmod.
	 *
	 * @ticket 57774
	 */
	public function test_should_set_chmod() {
		$path = self::$file_structure['test_dir']['path'] . 'directory-to-create';

		$created = self::$filesystem->mkdir( $path, 0644 );
		$chmod   = substr( sprintf( '%o', fileperms( $path ) ), -4 );

		if ( $path !== self::$file_structure['test_dir']['path'] && is_dir( $path ) ) {
			rmdir( $path );
		}

		$expected_permissions = $this->is_windows() ? '0777' : '0644';

		$this->assertTrue( $created, 'The directory was not created.' );
		$this->assertSame( $expected_permissions, $chmod, 'The permissions are incorrect.' );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::mkdir()` sets the owner.
	 *
	 * This test runs in a separate process so that it can define
	 * constants without impacting other tests.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed." when running in a
	 * separate process.
	 *
	 * @ticket 57774
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_set_owner() {
		define( 'FS_CHMOD_DIR', 0755 );

		$path = self::$file_structure['test_dir']['path'] . 'directory-to-create';

		// Get the default owner.
		self::$filesystem->mkdir( $path );
		$original_owner = fileowner( $path );

		rmdir( $path );

		$created = self::$filesystem->mkdir( $path, 0755, $original_owner );
		$owner   = fileowner( $path );

		if ( $path !== self::$file_structure['test_dir']['path'] && is_dir( $path ) ) {
			rmdir( $path );
		}

		$this->assertTrue( $created, 'The directory was not created.' );
		$this->assertSame( $original_owner, $owner, 'The owner is incorrect.' );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::mkdir()` sets the group.
	 *
	 * This test runs in a separate process so that it can define
	 * constants without impacting other tests.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed." when running in a
	 * separate process.
	 *
	 * @ticket 57774
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_set_group() {
		define( 'FS_CHMOD_DIR', 0755 );

		$path = self::$file_structure['test_dir']['path'] . 'directory-to-create';

		// Get the default group.
		self::$filesystem->mkdir( $path );
		$original_group = filegroup( $path );

		rmdir( $path );

		$created = self::$filesystem->mkdir( $path, 0755, false, $original_group );
		$group   = filegroup( $path );

		if ( $path !== self::$file_structure['test_dir']['path'] && is_dir( $path ) ) {
			rmdir( $path );
		}

		$this->assertTrue( $created, 'The directory was not created.' );
		$this->assertSame( $original_group, $group, 'The group is incorrect.' );
	}
}
