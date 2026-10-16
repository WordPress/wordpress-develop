<?php
/**
 * Tests for the WP_Plugin_Dependencies::get_dependent_filepath() method.
 *
 * @package WordPress
 */

namespace WordPress\Tests\Admin\PluginDependencies;

use WP_Plugin_Dependencies;
use WP_PluginDependencies_UnitTestCase;

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers \WP_Plugin_Dependencies::get_dependent_filepath
 * @covers \WP_Plugin_Dependencies::get_plugin_dirnames
 */
class GetDependentFilepathTest extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that the expected dependent filepath is retrieved.
	 *
	 * @ticket 22316
	 *
	 * @dataProvider data_get_dependent_filepath
	 *
	 * @param string       $dependent_slug The dependent slug.
	 * @param array        $plugins        An array of plugin data.
	 * @param string|false $expected       The expected result.
	 */
	public function test_should_return_filepaths_for_installed_dependents( $dependent_slug, $plugins, $expected ) {
		$this->set_property_value( 'plugins', $plugins );
		self::$instance::initialize();

		$this->assertSame(
			$expected,
			self::$instance::get_dependent_filepath( $dependent_slug ),
			'The incorrect filepath was returned.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_dependent_filepath() {
		return array(
			'a plugin that exists'            => array(
				'dependent_slug' => 'dependent',
				'plugins'        => array( 'dependent/dependent.php' => array( 'RequiresPlugins' => 'woocommerce' ) ),
				'expected'       => 'dependent/dependent.php',
			),
			'no plugins'                      => array(
				'dependent_slug' => 'dependent',
				'plugins'        => array(),
				'expected'       => false,
			),
			'a plugin that starts with slug/' => array(
				'dependent_slug' => 'dependent',
				'plugins'        => array( 'dependent-pro/dependent.php' => array( 'RequiresPlugins' => 'woocommerce' ) ),
				'expected'       => false,
			),
			'a plugin that ends with slug/'   => array(
				'dependent_slug' => 'dependent',
				'plugins'        => array( 'not-dependent/not-dependent.php' => array( 'RequiresPlugins' => 'woocommerce' ) ),
				'expected'       => false,
			),
			'a plugin that does not exist'    => array(
				'dependent_slug' => 'dependent2',
				'plugins'        => array( 'dependent/dependent.php' => array( 'RequiresPlugins' => 'woocommerce' ) ),
				'expected'       => false,
			),
		);
	}
}
