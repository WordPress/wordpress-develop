<?php

/**
 * Tests for plugin_basename()
 *
 * @group functions.php
 * @group plugins
 * @covers ::plugin_basename
 */
class Tests_Functions_PluginBasename extends WP_UnitTestCase {

	/**
	 * @var array
	 */
	protected $wp_plugin_paths_backup;

	/**
	 * Normalized path to plugin directory.
	 *
	 * @var string
	 */
	protected $wp_plugin_path;

	public function set_up() {
		parent::set_up();

		$this->wp_plugin_paths_backup = $GLOBALS['wp_plugin_paths'];
		$this->wp_plugin_path         = wp_normalize_path( WP_PLUGIN_DIR );
	}

	public function tear_down() {
		$GLOBALS['wp_plugin_paths'] = $this->wp_plugin_paths_backup;

		parent::tear_down();
	}

	/**
	 * @ticket 29154
	 */
	public function test_return_correct_basename_for_symlinked_plugins() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wp_plugin_path . '/a-symlinked-plugin' => 'C:/www/path/plugins/a-plugin',
		);

		$basename = plugin_basename( 'c:\www\path\plugins\a-plugin\plugin.php' );
		$this->assertSame( 'a-symlinked-plugin/plugin.php', $basename );
	}

	/**
	 * @ticket 28441
	 */
	public function test_return_correct_basename_for_symlinked_plugins_with_path_conflicts() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wp_plugin_path . '/plugin' => '/Users/me/Dropbox/Development/Repositories/plugin',
			$this->wp_plugin_path . '/trunk'  => '/Users/me/Dropbox/Development/Repositories/plugin/trunk',
		);

		$basename = plugin_basename( '/Users/me/Dropbox/Development/Repositories/plugin/trunk/plugin.php' );
		$this->assertSame( 'trunk/plugin.php', $basename );
	}
}
