<?php

/**
 * Tests for wp_get_plugin_path_from_realpath().
 *
 * @group functions
 * @group plugins
 *
 * @covers ::wp_get_plugin_path_from_realpath
 */
class Tests_Functions_WpGetPluginPathFromRealpath extends WP_UnitTestCase {

	/**
	 * Backup of the `$wp_plugin_paths` global.
	 *
	 * @var array
	 */
	protected $wp_plugin_paths_backup;

	/**
	 * Normalized path to the plugins directory.
	 *
	 * @var string
	 */
	protected $wp_plugin_path;

	/**
	 * Normalized path to the must-use plugins directory.
	 *
	 * @var string
	 */
	protected $wpmu_plugin_path;

	public function set_up() {
		parent::set_up();

		$this->wp_plugin_paths_backup = $GLOBALS['wp_plugin_paths'];
		$this->wp_plugin_path         = wp_normalize_path( WP_PLUGIN_DIR );
		$this->wpmu_plugin_path       = wp_normalize_path( WPMU_PLUGIN_DIR );
	}

	public function tear_down() {
		$GLOBALS['wp_plugin_paths'] = $this->wp_plugin_paths_backup;

		parent::tear_down();
	}

	/**
	 * @ticket 34358
	 */
	public function test_should_map_real_path_to_registered_mu_plugin_path() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wpmu_plugin_path . '/symlinked-plugin' => '/var/shared/plugins/symlinked-plugin',
		);

		$this->assertSame(
			$this->wpmu_plugin_path . '/symlinked-plugin/symlinked-plugin.php',
			wp_get_plugin_path_from_realpath( '/var/shared/plugins/symlinked-plugin/symlinked-plugin.php' )
		);
	}

	/**
	 * @ticket 34358
	 */
	public function test_should_map_real_path_to_registered_plugin_path() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wp_plugin_path . '/symlinked-plugin' => '/var/shared/plugins/symlinked-plugin',
		);

		$this->assertSame(
			$this->wp_plugin_path . '/symlinked-plugin/includes/file.php',
			wp_get_plugin_path_from_realpath( '/var/shared/plugins/symlinked-plugin/includes/file.php' )
		);
	}

	/**
	 * @ticket 34358
	 */
	public function test_should_normalize_windows_paths() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wp_plugin_path . '/symlinked-plugin' => 'C:/www/shared/symlinked-plugin',
		);

		$this->assertSame(
			$this->wp_plugin_path . '/symlinked-plugin/plugin.php',
			wp_get_plugin_path_from_realpath( 'c:\www\shared\symlinked-plugin\plugin.php' )
		);
	}

	/**
	 * @ticket 34358
	 */
	public function test_should_prefer_the_most_specific_registered_real_path() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wp_plugin_path . '/plugin' => '/Users/me/Repositories/plugin',
			$this->wp_plugin_path . '/trunk'  => '/Users/me/Repositories/plugin/trunk',
		);

		$this->assertSame(
			$this->wp_plugin_path . '/trunk/plugin.php',
			wp_get_plugin_path_from_realpath( '/Users/me/Repositories/plugin/trunk/plugin.php' )
		);
	}

	/**
	 * @ticket 34358
	 */
	public function test_should_return_normalized_path_unchanged_when_not_registered() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wp_plugin_path . '/symlinked-plugin' => '/var/shared/plugins/symlinked-plugin',
		);

		$this->assertSame(
			'C:/www/other-plugin/plugin.php',
			wp_get_plugin_path_from_realpath( 'c:\www\other-plugin\plugin.php' )
		);
	}

	/**
	 * @ticket 34358
	 */
	public function test_should_return_empty_string_for_empty_path() {
		$this->assertSame( '', wp_get_plugin_path_from_realpath( '' ) );
	}
}
