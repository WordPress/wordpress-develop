<?php

/**
 * Tests wp_mkdir_p().
 *
 * @group functions.php
 *
 * @covers ::wp_mkdir_p
 */
class Tests_Filesystem_WpMkdirP extends WP_UnitTestCase {
	/**
	 * The directory in which to create other directories.
	 *
	 * @var string
	 */
	private static $test_directory;

	/**
	 * Sets and creates the test directory before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$test_directory = realpath( DIR_TESTDATA ) . '/test_wp_mkdir_p/';
		mkdir( self::$test_directory );
	}

	/**
	 * Deletes the contents of the test directory after each test runs.
	 */
	public function tear_down() {
		foreach ( $this->files_in_dir( self::$test_directory ) as $file ) {
			$this->unlink( $file );
		}

		$matched_dirs = $this->scandir( self::$test_directory );
		foreach ( array_reverse( $matched_dirs ) as $dir ) {
			rmdir( $dir );
		}

		parent::tear_down();
	}

	/**
	 * Deletes the test directory after all tests have run.
	 */
	public static function tear_down_after_class() {
		rmdir( self::$test_directory );

		parent::tear_down_after_class();
	}

	/**
	 * Tests that `wp_mkdir_p()` fires an action.
	 *
	 * @ticket 44083
	 *
	 * @dataProvider data_actions
	 *
	 * @param string $hook_name The name of the action hook.
	 */
	public function test_wp_mkdir_p_should_fire_action( $hook_name ) {
		$action = new MockAction();
		add_action( $hook_name, array( $action, 'action' ) );

		wp_mkdir_p( self::$test_directory . $hook_name );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * Tests that `wp_mkdir_p()` does not fire an action when a file exists of the same name.
	 *
	 * @ticket 44083
	 *
	 * @dataProvider data_actions
	 *
	 * @param string $hook_name The name of the action hook.
	 */
	public function test_wp_mkdir_p_should_not_fire_action_when_a_file_exists_of_the_same_name( $hook_name ) {
		$action = new MockAction();
		add_action( $hook_name, array( $action, 'action' ) );

		$target = self::$test_directory . $hook_name;

		// Force a failure.
		$this->touch( $target );

		wp_mkdir_p( $target );

		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * Tests that `wp_mkdir_p()` does not fire an action when the target directory contains '../'.
	 *
	 * @ticket 44083
	 *
	 * @dataProvider data_actions
	 *
	 * @param string $hook_name The name of the action hook.
	 */
	public function test_wp_mkdir_p_should_not_fire_action_when_the_target_contains_path_traversal( $hook_name ) {
		$action = new MockAction();
		add_action( $hook_name, array( $action, 'action' ) );

		// Force a failure by not using `realpath()`.
		$target = DIR_TESTDATA . "/test_wp_mkdir_p/$hook_name/";

		wp_mkdir_p( $target );

		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * Tests that `wp_mkdir_p()` does not fire an action when the target directory contains '..DIRECTORY_SEPARATOR'.
	 *
	 * @ticket 44083
	 *
	 * @dataProvider data_actions
	 *
	 * @param string $hook_name The name of the action hook.
	 */
	public function test_wp_mkdir_p_should_not_fire_action_when_the_target_contains_path_traversal_with_directory_separator( $hook_name ) {
		$action = new MockAction();
		add_action( $hook_name, array( $action, 'action' ) );

		// Force a failure.
		$target = str_replace( '../', '..' . DIRECTORY_SEPARATOR, DIR_TESTDATA ) . "/test_wp_mkdir_p/$hook_name";

		wp_mkdir_p( $target );

		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * Data provider.
	 *
	 * @throws Exception
	 *
	 * @return array[]
	 */
	public function data_actions() {
		return self::text_array_to_dataprovider( array( 'before_create_directory', 'after_create_directory' ) );
	}

	/**
	 * Tests that `wp_mkdir_p()` does not fire the 'after_create_directory' action when `mkdir()` fails.
	 *
	 * @ticket 44083
	 */
	public function test_wp_mkdir_p_should_not_fire_the_after_create_directory_action_when_mkdir_fails() {
		$action = new MockAction();
		add_action( 'after_create_directory', array( $action, 'action' ) );

		add_action(
			'before_create_directory',
			function ( $target ) {
				/*
				 * Force a failure by creating a file of the same name
				 * just before `mkdir()` runs.
				 */
				$this->touch( $target );
			}
		);

		wp_mkdir_p( self::$test_directory . 'after_create_directory' );

		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * Tests that `wp_mkdir_p()` fires the 'create_directory_failed' action when `mkdir()` fails.
	 *
	 * @ticket 44083
	 */
	public function test_wp_mkdir_p_should_fire_the_create_directory_failed_action_when_mkdir_fails() {
		$action = new MockAction();
		add_action( 'create_directory_failed', array( $action, 'action' ) );

		add_action(
			'before_create_directory',
			function ( $target ) {
				/*
				 * Force a failure by creating a file of the same name
				 * just before `mkdir()` runs.
				*/
				$this->touch( $target );
			}
		);

		wp_mkdir_p( self::$test_directory . 'create_directory_failed' );

		$this->assertSame( 1, $action->get_call_count() );
	}
}
