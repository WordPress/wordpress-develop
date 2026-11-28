<?php

/**
 * Tests for wp_register_plugin_realpath()
 *
 * @group functions
 * @group plugins
 *
 * @covers ::wp_register_plugin_realpath
 */
class Tests_Functions_WpRegisterPluginRealpath extends WP_UnitTestCase {

	/**
	 * @var array
	 */
	protected $wp_plugin_paths_backup;

	public function set_up() {
		parent::set_up();

		$this->wp_plugin_paths_backup = $GLOBALS['wp_plugin_paths'];
	}

	public function tear_down() {
		$GLOBALS['wp_plugin_paths'] = $this->wp_plugin_paths_backup;

		parent::tear_down();
	}

	/**
	 * @ticket 43489
	 */
	public function test_should_return_false_for_nonexistent_file() {
		$this->assertFalse( wp_register_plugin_realpath( WP_PLUGIN_DIR . '/missing-plugin-43489/missing-plugin-43489.php' ) );
	}

	/**
	 * @ticket 43489
	 */
	public function test_should_not_register_path_for_nonexistent_file() {
		global $wp_plugin_paths;

		wp_register_plugin_realpath( WP_PLUGIN_DIR . '/missing-plugin-43489/missing-plugin-43489.php' );

		$this->assertArrayNotHasKey( wp_normalize_path( WP_PLUGIN_DIR ) . '/missing-plugin-43489', $wp_plugin_paths );
	}

	/**
	 * @ticket 43489
	 */
	public function test_should_not_affect_plugin_basename_for_nonexistent_file() {
		wp_register_plugin_realpath( WP_PLUGIN_DIR . '/missing-plugin-43489/missing-plugin-43489.php' );

		$this->assertSame( 'hello.php', plugin_basename( WP_PLUGIN_DIR . '/hello.php' ) );
	}

	/**
	 * @ticket 43489
	 */
	public function test_should_return_true_for_existing_file_in_plugin_subdirectory() {
		$this->assertTrue( wp_register_plugin_realpath( DIR_TESTDATA . '/plugins/hello.php' ) );
	}
}
