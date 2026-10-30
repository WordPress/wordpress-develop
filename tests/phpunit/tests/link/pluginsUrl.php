<?php

/**
 * Tests for plugins_url() and plugin_dir_url().
 *
 * @group link
 * @group plugins
 *
 * @covers ::plugins_url
 * @covers ::plugin_dir_url
 */
class Tests_Link_PluginsUrl extends WP_UnitTestCase {

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
	public function test_plugins_url_for_symlinked_mu_plugin() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wpmu_plugin_path . '/symlinked-plugin' => '/var/shared/plugins/symlinked-plugin',
		);

		$this->assertSame(
			set_url_scheme( WPMU_PLUGIN_URL ) . '/symlinked-plugin/css/style.css',
			plugins_url( 'css/style.css', '/var/shared/plugins/symlinked-plugin/symlinked-plugin.php' )
		);
	}

	/**
	 * @ticket 34358
	 */
	public function test_plugin_dir_url_for_symlinked_mu_plugin() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wpmu_plugin_path . '/symlinked-plugin' => '/var/shared/plugins/symlinked-plugin',
		);

		$this->assertSame(
			set_url_scheme( WPMU_PLUGIN_URL ) . '/symlinked-plugin/',
			plugin_dir_url( '/var/shared/plugins/symlinked-plugin/symlinked-plugin.php' )
		);
	}

	/**
	 * @ticket 34358
	 */
	public function test_plugins_url_for_file_in_subdirectory_of_symlinked_mu_plugin() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wpmu_plugin_path . '/symlinked-plugin' => '/var/shared/plugins/symlinked-plugin',
		);

		$this->assertSame(
			set_url_scheme( WPMU_PLUGIN_URL ) . '/symlinked-plugin/includes/js/script.js',
			plugins_url( 'js/script.js', '/var/shared/plugins/symlinked-plugin/includes/class-loader.php' )
		);
	}

	/**
	 * @ticket 34358
	 */
	public function test_plugins_url_for_symlinked_mu_plugin_with_windows_path() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wpmu_plugin_path . '/symlinked-plugin' => 'C:/www/shared/symlinked-plugin',
		);

		$this->assertSame(
			set_url_scheme( WPMU_PLUGIN_URL ) . '/symlinked-plugin/style.css',
			plugins_url( 'style.css', 'c:\www\shared\symlinked-plugin\symlinked-plugin.php' )
		);
	}

	/**
	 * Symlinked regular plugins were already supported; make sure that still holds.
	 *
	 * @ticket 34358
	 */
	public function test_plugins_url_for_symlinked_plugin() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wp_plugin_path . '/symlinked-plugin' => '/var/shared/plugins/symlinked-plugin',
		);

		$this->assertSame(
			set_url_scheme( WP_PLUGIN_URL ) . '/symlinked-plugin/style.css',
			plugins_url( 'style.css', '/var/shared/plugins/symlinked-plugin/symlinked-plugin.php' )
		);
	}

	/**
	 * @ticket 34358
	 */
	public function test_plugins_url_for_mu_plugin_that_is_not_symlinked() {
		$this->assertSame(
			set_url_scheme( WPMU_PLUGIN_URL ) . '/my-plugin/style.css',
			plugins_url( 'style.css', WPMU_PLUGIN_DIR . '/my-plugin/my-plugin.php' )
		);
	}

	/**
	 * @ticket 34358
	 */
	public function test_plugins_url_for_unregistered_external_file_falls_back_to_plugins_url() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array();

		$this->assertStringStartsWith(
			set_url_scheme( WP_PLUGIN_URL ),
			plugins_url( 'style.css', '/var/shared/plugins/unregistered-plugin/plugin.php' )
		);
	}

	/**
	 * The `plugins_url` filter should still receive the plugin path that was passed in.
	 *
	 * @ticket 34358
	 */
	public function test_plugins_url_filter_receives_original_plugin_path() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			$this->wpmu_plugin_path . '/symlinked-plugin' => '/var/shared/plugins/symlinked-plugin',
		);

		$filter = new MockAction();
		add_filter( 'plugins_url', array( $filter, 'filter' ), 10, 3 );

		plugins_url( 'style.css', '/var/shared/plugins/symlinked-plugin/symlinked-plugin.php' );

		$args = $filter->get_args();
		$this->assertSame( '/var/shared/plugins/symlinked-plugin/symlinked-plugin.php', $args[0][2] );
	}
}
