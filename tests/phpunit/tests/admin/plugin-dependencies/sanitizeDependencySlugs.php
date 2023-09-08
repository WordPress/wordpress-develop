<?php
/**
 * Tests for the WP_Plugin_Dependencies::sanitize_dependency_slugs() method.
 *
 * @package WP_Plugin_Dependencies
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::sanitize_dependency_slugs
 */
class Tests_Admin_WPPluginDependencies_SanitizeDependencySlugs extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that slugs are correctly sanitized from the 'RequiresPlugins' header.
	 *
	 * @dataProvider data_should_return_sanitized_slugs
	 *
	 * @param string $requires_plugins The unsanitized dependency slug(s).
	 * @param array  $expected         The sanitized dependency slug(s).
	 */
	public function test_should_return_sanitized_slugs( $requires_plugins, $expected ) {
		$this->assertSame( $expected, $this->call_method( 'sanitize_dependency_slugs', $requires_plugins ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_return_sanitized_slugs() {
		return array(
			'one dependency'                         => array(
				'requires_plugins' => 'hello-dolly',
				'expected'         => array( 'hello-dolly' ),
			),
			'two dependencies in alphabetical order' => array(
				'requires_plugins' => 'hello-dolly, woocommerce',
				'expected'         => array(
					'hello-dolly',
					'woocommerce',
				),
			),
			'two dependencies in reverse alphabetical order' => array(
				'requires_plugins' => 'woocommerce, hello-dolly',
				'expected'         => array(
					'hello-dolly',
					'woocommerce',
				),
			),
			'two dependencies with a space'          => array(
				'requires_plugins' => 'hello-dolly , woocommerce',
				'expected'         => array(
					'hello-dolly',
					'woocommerce',
				),
			),
			'a repeated dependency'                  => array(
				'requires_plugins' => 'hello-dolly, woocommerce, hello-dolly',
				'expected'         => array(
					'hello-dolly',
					'woocommerce',
				),
			),
			'a dependency with multiple dashes'      => array(
				'requires_plugins' => 'this-is-a-valid-slug',
				'expected'         => array( 'this-is-a-valid-slug' ),
			),
			'a dependency starting with numbers'     => array(
				'requires_plugins' => '123slug',
				'expected'         => array( '123slug' ),
			),
		);
	}

	/**
	 * Tests that an empty array is returned for invalid slugs.
	 *
	 * @dataProvider data_should_return_an_empty_array_for_invalid_slugs
	 *
	 * @param string $requires_plugins The unsanitized dependency slug(s).
	 */
	public function test_should_return_an_empty_array_for_invalid_slugs( $requires_plugins ) {
		$actual = $this->call_method( 'sanitize_dependency_slugs', $requires_plugins );

		$this->assertIsArray( $actual, 'An array was not returned.' );
		$this->assertEmpty( $actual, 'An empty array was not returned.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_return_an_empty_array_for_invalid_slugs() {
		return array(
			'no dependencies'                 => array(
				'requires_plugins' => '',
			),
			'a dependency with an underscore' => array(
				'requires_plugins' => 'hello_dolly',
			),
			'a dependency with a space'       => array(
				'requires_plugins' => 'hello dolly',
			),
			'a dependency in quotes'          => array(
				'requires_plugins' => '"hello-dolly"',
			),
			'two dependencies in quotes'      => array(
				'requires_plugins' => '"hello-dolly, woocommerce"',
			),
			'a dependency with trailing dash' => array(
				'requires_plugins' => 'ending-dash-',
			),
			'a dependency with leading dash'  => array(
				'requires_plugins' => '-slug',
			),
			'a dependency with double dashes' => array(
				'requires_plugins' => 'abc--123',
			),
			'cyrillic dependencies'           => array(
				'requires_plugins' => 'я-делюсь',
			),
			'arabic dependencies'             => array(
				'requires_plugins' => 'لينوكس-ويكى',
			),
			'chinese dependencies'            => array(
				'requires_plugins' => '唐诗宋词chinese-poem,社交登录,腾讯微博一键登录,豆瓣秀-for-wordpress',
			),
			'symbol dependencies'             => array(
				'requires_plugins' => '★-wpsymbols-★',
			),
		);
	}
}
