<?php

require_once __DIR__ . '/Admin_WpUpgrader_TestCase.php';

/**
 * @group admin
 * @group upgrade
 * @covers WP_Upgrader::maintenance_mode()
 */
class Admin_WpUpgrader_MaintenanceMode_Test extends Admin_WpUpgrader_TestCase {

	/**
	 * Tests that `WP_Upgrader::maintenance_mode()` removes the `.maintenance` file.
	 *
	 * @ticket 54245
	 */
	public function test_maintenance_mode_should_disable_maintenance_mode_if_maintenance_file_exists() {
		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'abspath' )
				->willReturn( '/' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'exists' )
				->with( '/.maintenance' )
				->willReturn( true );

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'feedback' )
				->with( 'maintenance_end' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'delete' )
				->with( '/.maintenance' );

		self::$instance->maintenance_mode();
	}

	/**
	 * Tests that `WP_Upgrader::maintenance_mode()` does nothing if
	 * the `.maintenance` file does not exist.
	 *
	 * @ticket 54245
	 */
	public function test_maintenance_mode_should_not_disable_maintenance_mode_if_no_maintenance_file_exists() {
		self::$upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'delete' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'abspath' )
				->willReturn( '/' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'exists' )
				->with( '/.maintenance' )
				->willReturn( false );

		self::$instance->maintenance_mode();
	}

	/**
	 * Tests that `WP_Upgrader::maintenance_mode()` creates
	 * a `.maintenance` file with a boolean `$enable` argument.
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
	public function test_maintenance_mode_should_create_maintenance_file_with_boolean() {
		define( 'FS_CHMOD_FILE', 0644 );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'abspath' )
				->willReturn( '/' );

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'feedback' )
				->with( 'maintenance_start' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'delete' )
				->with( '/.maintenance' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'put_contents' )
				->with(
					'/.maintenance',
					$this->stringContains( '<?php $upgrading =' ),
					FS_CHMOD_FILE
				);

		self::$instance->maintenance_mode( true );
	}
}
