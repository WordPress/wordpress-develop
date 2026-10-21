<?php

/**
 * Tests for register_uninstall_hook().
 *
 * @group functions
 * @group plugins
 *
 * @covers ::register_uninstall_hook
 */
class Tests_Functions_RegisterUninstallHook extends WP_UnitTestCase {

	/**
	 * Number of times the uninstall_plugins option was saved.
	 *
	 * @var int
	 */
	private $option_updates = 0;

	/**
	 * Starts each test with no uninstall callbacks and counts option updates.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'uninstall_plugins', array() );

		add_filter( 'pre_update_option_uninstall_plugins', array( $this, 'count_option_update' ) );
	}

	/**
	 * Tests that a function name is stored under the plugin basename.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_callbacks
	 *
	 * @param callable $callback The uninstall callback.
	 */
	public function test_should_store_callback_under_plugin_basename( $callback ) {
		register_uninstall_hook( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', $callback );

		$this->assertSame( array( 'my-plugin/my-plugin.php' => $callback ), get_option( 'uninstall_plugins' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_callbacks() {
		return array(
			'function name'           => array( 'my_plugin_uninstall' ),
			'static method as string' => array( 'My_Plugin::uninstall' ),
			'static method as array'  => array( array( 'My_Plugin', 'uninstall' ) ),
		);
	}

	/**
	 * Tests that an object method is rejected.
	 *
	 * @ticket 65819
	 *
	 * @expectedIncorrectUsage register_uninstall_hook
	 */
	public function test_should_reject_object_method() {
		register_uninstall_hook( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', array( new stdClass(), 'uninstall' ) );

		$this->assertSame( array(), get_option( 'uninstall_plugins' ), 'No callback should be stored.' );
		$this->assertSame( 0, $this->option_updates, 'The option should not be updated.' );
	}

	/**
	 * Tests that callbacks of other plugins are kept.
	 *
	 * @ticket 65819
	 */
	public function test_should_keep_callbacks_of_other_plugins() {
		update_option( 'uninstall_plugins', array( 'other-plugin/other-plugin.php' => 'other_plugin_uninstall' ) );

		register_uninstall_hook( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', 'my_plugin_uninstall' );

		$this->assertSame(
			array(
				'other-plugin/other-plugin.php' => 'other_plugin_uninstall',
				'my-plugin/my-plugin.php'       => 'my_plugin_uninstall',
			),
			get_option( 'uninstall_plugins' )
		);
	}

	/**
	 * Tests that a different callback for the same plugin replaces the stored one.
	 *
	 * @ticket 65819
	 */
	public function test_should_replace_changed_callback() {
		update_option( 'uninstall_plugins', array( 'my-plugin/my-plugin.php' => 'old_uninstall' ) );
		$this->option_updates = 0;

		register_uninstall_hook( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', 'new_uninstall' );

		$this->assertSame( array( 'my-plugin/my-plugin.php' => 'new_uninstall' ), get_option( 'uninstall_plugins' ), 'The callback should be replaced.' );
		$this->assertSame( 1, $this->option_updates, 'The option should be updated once.' );
	}

	/**
	 * Tests that the option is not saved again when the callback is unchanged.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_update_option_when_callback_is_unchanged() {
		update_option( 'uninstall_plugins', array( 'my-plugin/my-plugin.php' => 'my_plugin_uninstall' ) );
		$this->option_updates = 0;

		register_uninstall_hook( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', 'my_plugin_uninstall' );

		$this->assertSame( 0, $this->option_updates );
	}

	/**
	 * Tests that a stored callback of another type is replaced.
	 *
	 * @ticket 65819
	 */
	public function test_should_replace_callback_of_another_type() {
		update_option( 'uninstall_plugins', array( 'my-plugin/my-plugin.php' => array( 'My_Plugin', 'uninstall' ) ) );

		register_uninstall_hook( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', 'My_Plugin::uninstall' );

		$this->assertSame( array( 'my-plugin/my-plugin.php' => 'My_Plugin::uninstall' ), get_option( 'uninstall_plugins' ) );
	}

	/**
	 * Counts updates of the uninstall_plugins option.
	 *
	 * @param mixed $value The new option value.
	 * @return mixed The unchanged value.
	 */
	public function count_option_update( $value ) {
		++$this->option_updates;

		return $value;
	}
}
