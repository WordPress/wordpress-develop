<?php

require_once __DIR__ . '/Admin_WpAutomaticUpdater_TestCase.php';

/**
 * @group admin
 * @group upgrade
 *
 * @covers WP_Automatic_Updater::is_allowed_dir
 */
class Admin_WpAutomaticUpdater_IsAllowedDir_Test extends Admin_WpAutomaticUpdater_TestCase {

	/**
	 * Tests that `WP_Automatic_Updater::is_allowed_dir()` returns true
	 * when the `open_basedir` directive is not set.
	 *
	 * @ticket 42619
	 */
	public function test_is_allowed_dir_should_return_true_if_open_basedir_is_not_set() {
		$this->assertTrue( self::$updater->is_allowed_dir( ABSPATH ) );
	}

	/**
	 * Tests that `WP_Automatic_Updater::is_allowed_dir()` returns true
	 * when the `open_basedir` directive is set and the path is allowed.
	 *
	 * Runs in a separate process to ensure that `open_basedir` changes
	 * don't impact other tests should an error occur.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed" when running in
	 * a separate process.
	 *
	 * @ticket 42619
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_allowed_dir_should_return_true_if_open_basedir_is_set_and_path_is_allowed() {
		// The repository for PHPUnit and test suite resources.
		$abspath_parent      = trailingslashit( dirname( ABSPATH ) );
		$abspath_grandparent = trailingslashit( dirname( $abspath_parent ) );

		$open_basedir_backup = ini_get( 'open_basedir' );
		// Allow access to the directory one level above the repository.
		ini_set( 'open_basedir', sys_get_temp_dir() . PATH_SEPARATOR . wp_normalize_path( $abspath_grandparent ) );

		// Checking an allowed directory should succeed.
		$actual = self::$updater->is_allowed_dir( wp_normalize_path( ABSPATH ) );

		ini_set( 'open_basedir', $open_basedir_backup );

		$this->assertTrue( $actual );
	}

	/**
	 * Tests that `WP_Automatic_Updater::is_allowed_dir()` returns false
	 * when the `open_basedir` directive is set and the path is not allowed.
	 *
	 * Runs in a separate process to ensure that `open_basedir` changes
	 * don't impact other tests should an error occur.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed" when running in
	 * a separate process.
	 *
	 * @ticket 42619
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_allowed_dir_should_return_false_if_open_basedir_is_set_and_path_is_not_allowed() {
		// The repository for PHPUnit and test suite resources.
		$abspath_parent      = trailingslashit( dirname( ABSPATH ) );
		$abspath_grandparent = trailingslashit( dirname( $abspath_parent ) );

		$open_basedir_backup = ini_get( 'open_basedir' );
		// Allow access to the directory one level above the repository.
		ini_set( 'open_basedir', sys_get_temp_dir() . PATH_SEPARATOR . wp_normalize_path( $abspath_grandparent ) );

		// Checking a directory not within the allowed path should trigger an `open_basedir` warning.
		$actual = self::$updater->is_allowed_dir( '/.git' );

		ini_set( 'open_basedir', $open_basedir_backup );

		$this->assertFalse( $actual );
	}

	/**
	 * Tests that `WP_Automatic_Updater::is_allowed_dir()` throws `_doing_it_wrong()`
	 * when an invalid `$dir` argument is provided.
	 *
	 * @ticket 42619
	 *
	 * @expectedIncorrectUsage WP_Automatic_Updater::is_allowed_dir
	 *
	 * @dataProvider data_is_allowed_dir_should_throw_doing_it_wrong_with_invalid_dir
	 *
	 * @param mixed $dir The directory to check.
	 */
	public function test_is_allowed_dir_should_throw_doing_it_wrong_with_invalid_dir( $dir ) {
		$this->assertFalse( self::$updater->is_allowed_dir( $dir ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_is_allowed_dir_should_throw_doing_it_wrong_with_invalid_dir() {
		return array(
			// Type checks and boolean comparisons.
			'null'                              => array( 'dir' => null ),
			'(bool) false'                      => array( 'dir' => false ),
			'(bool) true'                       => array( 'dir' => true ),
			'(int) 0'                           => array( 'dir' => 0 ),
			'(int) -0'                          => array( 'dir' => -0 ),
			'(int) 1'                           => array( 'dir' => 1 ),
			'(int) -1'                          => array( 'dir' => -1 ),
			'(float) 0.0'                       => array( 'dir' => 0.0 ),
			'(float) -0.0'                      => array( 'dir' => -0.0 ),
			'(float) 1.0'                       => array( 'dir' => 1.0 ),
			'empty string'                      => array( 'dir' => '' ),
			'empty array'                       => array( 'dir' => array() ),
			'populated array'                   => array( 'dir' => array( ABSPATH ) ),
			'empty object'                      => array( 'dir' => new stdClass() ),
			'populated object'                  => array( 'dir' => (object) array( ABSPATH ) ),
			'INF'                               => array( 'dir' => INF ),
			'NAN'                               => array( 'dir' => NAN ),

			// Ensures that `trim()` has been called.
			'string with only spaces'           => array( 'dir' => '   ' ),
			'string with only tabs'             => array( 'dir' => "\t\t" ),
			'string with only newlines'         => array( 'dir' => "\n\n" ),
			'string with only carriage returns' => array( 'dir' => "\r\r" ),
		);
	}
}
