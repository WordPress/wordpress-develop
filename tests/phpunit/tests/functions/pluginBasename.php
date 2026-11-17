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
	 * Tests that overlapping paths resolve to the most specific matching directory.
	 *
	 * @ticket 42670
	 *
	 * @dataProvider data_return_correct_basename_for_overlapping_paths
	 *
	 * @param string $file         The plugin file path.
	 * @param array  $plugin_paths Registered plugin paths and their real paths.
	 * @param string $expected     The expected plugin basename.
	 */
	public function test_return_correct_basename_for_overlapping_paths( $file, $plugin_paths, $expected ) {
		$GLOBALS['wp_plugin_paths'] = $plugin_paths;

		$this->assertSame( $expected, plugin_basename( $file ) );
	}

	/**
	 * Data provider for test_return_correct_basename_for_overlapping_paths().
	 *
	 * @return array[]
	 */
	public static function data_return_correct_basename_for_overlapping_paths() {
		$plugin_dir    = wp_normalize_path( WP_PLUGIN_DIR );
		$mu_plugin_dir = wp_normalize_path( WPMU_PLUGIN_DIR );
		$project_dir   = dirname( $plugin_dir, 2 );

		return array(
			'ordinary plugin sharing a directory name prefix with a symlink target' => array(
				$plugin_dir . '/wordpress-importer/wordpress-importer.php',
				array( $plugin_dir . '/sample-plugin' => substr( $plugin_dir, 0, -1 ) ),
				'wordpress-importer/wordpress-importer.php',
			),
			'ordinary plugin inside a symlink target'     => array(
				$plugin_dir . '/wordpress-importer/wordpress-importer.php',
				array( $plugin_dir . '/sample-plugin' => $project_dir ),
				'wordpress-importer/wordpress-importer.php',
			),
			'plugin with both its own directory and its ancestor symlinked' => array(
				$project_dir . '/shared-content/plugins/wordpress-importer/wordpress-importer.php',
				array(
					$plugin_dir . '/sample-plugin'      => $project_dir,
					$plugin_dir . '/wordpress-importer' => $project_dir . '/shared-content/plugins/wordpress-importer',
				),
				'wordpress-importer/wordpress-importer.php',
			),
			'exact symlink target directory'              => array(
				'/external/sample-plugin',
				array( $plugin_dir . '/sample-plugin' => '/external/sample-plugin' ),
				'sample-plugin',
			),
			'file inside a symlink target'                => array(
				'/external/sample-plugin/includes/plugin.php',
				array( $plugin_dir . '/sample-plugin' => '/external/sample-plugin' ),
				'sample-plugin/includes/plugin.php',
			),
			'sibling directory of a symlink target'       => array(
				'/external/sample-plugin-wp/plugin.php',
				array( $plugin_dir . '/sample-plugin' => '/external/sample-plugin' ),
				'external/sample-plugin-wp/plugin.php',
			),
			'Windows sibling directory of a symlink target' => array(
				'c:\\external\\sample-plugin-wp\\plugin.php',
				array( $plugin_dir . '/sample-plugin' => 'C:/external/sample-plugin' ),
				'C:/external/sample-plugin-wp/plugin.php',
			),
			'symlink target inside the plugins directory' => array(
				$plugin_dir . '/target/plugin.php',
				array( $plugin_dir . '/alias' => $plugin_dir . '/target' ),
				'alias/plugin.php',
			),
			'symlink target equal to the plugins directory' => array(
				$plugin_dir . '/plugin.php',
				array( $plugin_dir . '/alias' => $plugin_dir ),
				'alias/plugin.php',
			),
			'symlink target equal to the mu-plugins directory' => array(
				$mu_plugin_dir . '/plugin.php',
				array( $mu_plugin_dir . '/alias' => $mu_plugin_dir ),
				'alias/plugin.php',
			),
			'ordinary mu-plugin inside a symlink target'  => array(
				$mu_plugin_dir . '/ordinary-plugin/plugin.php',
				array( $plugin_dir . '/sample-plugin' => dirname( $mu_plugin_dir ) ),
				'ordinary-plugin/plugin.php',
			),
			'ordinary plugin without symlink mappings'    => array(
				$plugin_dir . '/wordpress-importer/wordpress-importer.php',
				array(),
				'wordpress-importer/wordpress-importer.php',
			),
			'ordinary mu-plugin without symlink mappings' => array(
				$mu_plugin_dir . '/ordinary-plugin/plugin.php',
				array(),
				'ordinary-plugin/plugin.php',
			),
		);
	}

	/**
	 * Tests that a symlink target's directory name prefix does not corrupt another plugin's URL.
	 *
	 * @ticket 42670
	 *
	 * @covers ::plugins_url
	 */
	public function test_return_correct_url_for_plugin_sharing_path_prefix_with_symlink_target() {
		$GLOBALS['wp_plugin_paths'] = array(
			$this->wp_plugin_path . '/sample-plugin' => substr( $this->wp_plugin_path, 0, -1 ),
		);

		$this->assertSame(
			set_url_scheme( WP_PLUGIN_URL ) . '/wordpress-importer/assets/script.js',
			plugins_url( 'assets/script.js', $this->wp_plugin_path . '/wordpress-importer/wordpress-importer.php' )
		);
	}
}
