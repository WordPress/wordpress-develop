<?php
/**
 * Tests for the WP_Plugin_Dependencies::get_dependency_filepaths() method.
 *
 * @package WP_Plugin_Dependencies
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::get_dependency_filepaths
 */
class Tests_Admin_WPPluginDependencies_GetDependencyFilepaths extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that the expected dependency filepaths are retrieved for installed dependencies.
	 *
	 * @dataProvider data_get_dependency_filepaths
	 *
	 * @param string[] $slugs    An array of slugs.
	 * @param string[] $plugins  An array of plugin paths.
	 * @param array    $expected An array of expected filepath results.
	 */
	public function test_should_return_filepaths_for_installed_dependencies( $slugs, $plugins, $expected ) {
		$this->set_property_value( 'dependency_slugs', $slugs );
		$this->set_property_value( 'plugins', array_flip( $plugins ) );

		$this->assertSame( $expected, $this->call_method( 'get_dependency_filepaths' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_dependency_filepaths() {
		return array(
			'no slugs'                                     => array(
				'dependency_slugs' => array(),
				'plugins'          => array( 'plugin1/plugin1.php', 'plugin2/plugin2.php' ),
				'expected'         => array(),
			),
			'no plugins'                                   => array(
				'dependency_slugs' => array( 'plugin1', 'plugin2' ),
				'plugins'          => array(),
				'expected'         => array(),
			),
			'a plugin that starts with slug/'              => array(
				'dependency_slugs' => array( 'plugin1' ),
				'plugins'          => array( 'plugin1-pro/plugin1.php' ),
				'expected'         => array( 'plugin1' => false ),
			),
			'a plugin that ends with slug/'                => array(
				'dependency_slugs' => array( 'plugin1' ),
				'plugins'          => array( 'addon-for-plugin1/plugin1.php' ),
				'expected'         => array( 'plugin1' => false ),
			),
			'a plugin that does not exist'                 => array(
				'dependency_slugs' => array( 'plugin2' ),
				'plugins'          => array( 'plugin1/plugin1.php' ),
				'expected'         => array( 'plugin2' => false ),
			),
			'a plugin that exists'                         => array(
				'dependency_slugs' => array( 'plugin1' ),
				'plugins'          => array( 'plugin1/plugin1.php' ),
				'expected'         => array( 'plugin1' => 'plugin1/plugin1.php' ),
			),
			'two plugins that exist'                       => array(
				'dependency_slugs' => array( 'plugin1', 'plugin2' ),
				'plugins'          => array( 'plugin1/plugin1.php', 'plugin2/plugin2.php' ),
				'expected'         => array(
					'plugin1' => 'plugin1/plugin1.php',
					'plugin2' => 'plugin2/plugin2.php',
				),
			),
			'two plugins that exist (reversed slug order)' => array(
				'dependency_slugs' => array( 'plugin2', 'plugin1' ),
				'plugins'          => array( 'plugin1/plugin1.php', 'plugin2/plugin2.php' ),
				'expected'         => array(
					'plugin2' => 'plugin2/plugin2.php',
					'plugin1' => 'plugin1/plugin1.php',
				),
			),
			'two plugins, first exists, second does not exist' => array(
				'dependency_slugs' => array( 'plugin1', 'plugin2' ),
				'plugins'          => array( 'plugin1/plugin1.php', 'plugin3/plugin3.php' ),
				'expected'         => array(
					'plugin1' => 'plugin1/plugin1.php',
					'plugin2' => false,
				),
			),
			'two plugins, first does not exist, second does exist' => array(
				'dependency_slugs' => array( 'plugin1', 'plugin2' ),
				'plugins'          => array( 'plugin2/plugin2.php', 'plugin3/plugin3.php' ),
				'expected'         => array(
					'plugin1' => false,
					'plugin2' => 'plugin2/plugin2.php',
				),
			),
			'two plugins that do not exist'                => array(
				'dependency_slugs' => array( 'plugin1', 'plugin2' ),
				'plugins'          => array( 'plugin3/plugin3.php', 'plugin4/plugin4.php' ),
				'expected'         => array(
					'plugin1' => false,
					'plugin2' => false,
				),
			),
			'a plugin with a path beginning with a period' => array(
				'dependency_slugs' => array( 'plugin1' ),
				'plugins'          => array( './plugin1.php' ),
				'expected'         => array(
					'plugin1' => false,
				),
			),
		);
	}

	/**
	 * Tests that the plugin directory name cache is updated when
	 * it does not match the list of current plugins.
	 */
	public function test_get_dependency_filepaths_with_unmatched_dirnames_and_dirnames_cache() {
		$expected = array(
			'plugin1' => 'plugin1/plugin1.php',
			'plugin2' => 'plugin2/plugin2.php',
			'plugin3' => 'plugin3/plugin3.php',
		);

		// An additional plugin has been added during runtime.
		$this->set_property_value(
			'dependency_slugs',
			array( 'plugin1', 'plugin2', 'plugin3' )
		);

		$this->set_property_value(
			'plugins',
			array(
				// This is flipped as paths are stored in the keys.
				'plugin1/plugin1.php' => '',
				'plugin2/plugin2.php' => '',
				'plugin3/plugin3.php' => '',
			)
		);

		$this->set_property_value( 'plugin_dirnames', $expected );

		// The cache no longer matches the stored directory names and should be refreshed.
		$this->set_property_value(
			'plugin_dirnames_cache',
			array(
				'plugin1/plugin1.php',
				'plugin2/plugin2.php',
			)
		);

		$this->assertSame( $expected, $this->call_method( 'get_dependency_filepaths' ) );
	}
}
