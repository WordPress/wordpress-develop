<?php
/**
 * Tests the `Plugin_Upgrader` class.
 *
 * @group admin
 * @group upgrade
 */
class Tests_Admin_PluginUpgrader extends WP_UnitTestCase {

	/**
	 * Test plugin directories and files.
	 *
	 * @var array[]
	 */
	private static $test_plugins = array(
		'plugin-upgrader-nested-test' => array(
			'main.php'              => 'Z Main Plugin',
			'embedded/embedded.php' => 'A Embedded Plugin',
		),
		'plugin-upgrader-slug-test'   => array(
			'another.php'                   => 'A Another Plugin',
			'plugin-upgrader-slug-test.php' => 'Z Slug Plugin',
		),
	);

	/**
	 * Creates the test plugins before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

		foreach ( self::$test_plugins as $directory => $files ) {
			foreach ( $files as $file => $name ) {
				$path = WP_PLUGIN_DIR . "/$directory/$file";

				wp_mkdir_p( dirname( $path ) );
				file_put_contents( $path, "<?php\n/**\n * Plugin Name: $name\n */" );
			}
		}
	}

	/**
	 * Removes the test plugins after all tests run.
	 */
	public static function tear_down_after_class() {
		foreach ( self::$test_plugins as $directory => $files ) {
			foreach ( $files as $file => $name ) {
				unlink( WP_PLUGIN_DIR . "/$directory/$file" );
			}

			$subdirectories = glob( WP_PLUGIN_DIR . "/$directory/*", GLOB_ONLYDIR );
			foreach ( $subdirectories as $subdirectory ) {
				rmdir( $subdirectory );
			}

			rmdir( WP_PLUGIN_DIR . "/$directory" );
		}

		wp_clean_plugins_cache( false );

		parent::tear_down_after_class();
	}

	/**
	 * Tests that the main plugin file is selected for the activation link.
	 *
	 * @ticket 22287
	 *
	 * @covers Plugin_Upgrader::plugin_info
	 *
	 * @dataProvider data_plugin_info
	 *
	 * @param string $destination_name Plugin destination directory name.
	 * @param string $expected         Expected main plugin file.
	 */
	public function test_plugin_info_should_select_main_plugin( $destination_name, $expected ) {
		wp_clean_plugins_cache( false );

		$upgrader         = new Plugin_Upgrader();
		$upgrader->result = array( 'destination_name' => $destination_name );

		$this->assertSame( $expected, $upgrader->plugin_info() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_plugin_info() {
		return array(
			'nested plugin'      => array(
				'destination_name' => 'plugin-upgrader-nested-test',
				'expected'         => 'plugin-upgrader-nested-test/main.php',
			),
			'matching slug file' => array(
				'destination_name' => 'plugin-upgrader-slug-test',
				'expected'         => 'plugin-upgrader-slug-test/plugin-upgrader-slug-test.php',
			),
		);
	}
}
