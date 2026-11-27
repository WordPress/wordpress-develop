<?php

/**
 * Tests for plugin_basename()
 *
 * @group functions
 * @group plugins
 *
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

	/**
	 * Mimic a parent project directory that contains a plugin and WordPress installation. E.g.
	 *
	 *   Project directory: ../Projects/plugin-root
	 *   WordPress install: ../Projects/plugin-root/wordpress
	 *
	 * @ticket 42670
	 */
	public function test_should_return_correct_basename_for_plugin_when_wp_plugins_dir_is_subdir_of_symlinked_plugin() {
		global $wp_plugin_paths;

		// Set project root directory to any real absolute path. Using WP_PLUGIN_DIR for convenience.
		$plugin_project_root_directory = $this->wp_plugin_path;

		$wp_plugin_paths = array(
			// Symlinked plugin located at project root.
			$this->wp_plugin_path . '/plugin-root'        => $plugin_project_root_directory,
			// Plugin in nested WordPress installation.
			$this->wp_plugin_path . '/wordpress-importer' => $plugin_project_root_directory . '/wordpress/wp-content/plugins/wordpress-importer',
		);

		$actual = plugin_basename( $plugin_project_root_directory . '/wordpress/wp-content/plugins/wordpress-importer/wordpress-importer.php' );
		$this->assertSame( 'wordpress-importer/wordpress-importer.php', $actual, 'The basename returned is incorrect.' );
	}

	/**
	 * Always record the plugin path in `wp_register_plugin_realpath()`.
	 *
	 * As of 6.3, `$wp_plugin_paths` acts only as a map when path and realpath differ, meaning it does not contain all
	 * plugin paths.
	 *
	 * @ticket 42670
	 */
	public function test_wp_register_plugin_realpath_should_always_record_path() {
		global $wp_plugin_paths;
		$wp_plugin_paths = array();

		$unzip = new ZipArchive();
		$unzip->open( $this->wp_plugin_path . '/link-manager.zip' );
		$unzip->extractTo( $this->wp_plugin_path );
		$unzip->close();

		wp_register_plugin_realpath( $this->wp_plugin_path . '/link-manager/link-manager.php' );

		$this->assertArrayHasKey( $this->wp_plugin_path . '/link-manager', $wp_plugin_paths );
		$this->assertContains( $this->wp_plugin_path . '/link-manager', $wp_plugin_paths );

		$this->rmdir( $this->wp_plugin_path . '/link-manager' );
		rmdir( $this->wp_plugin_path . '/link-manager' );
	}
}
