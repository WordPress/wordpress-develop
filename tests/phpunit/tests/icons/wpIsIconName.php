<?php
/**
 * Unit tests covering the wp_is_icon_name() function.
 *
 * @package WordPress
 * @subpackage Icons
 * @since 7.2.0
 *
 * @group icons
 *
 * @covers ::wp_is_icon_name
 */
class Tests_Icons_WpIsIconName extends WP_UnitTestCase {

	/**
	 * @ticket 65089
	 *
	 * @dataProvider data_icon_names
	 *
	 * @param mixed $value The value to check.
	 */
	public function test_returns_true_for_namespaced_icon_names( $value ): void {
		$this->assertTrue( wp_is_icon_name( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: non-falsy-string }>
	 */
	public static function data_icon_names(): array {
		return array(
			'core icon'                   => array( 'core/plus' ),
			'hyphens'                     => array( 'core-admin/chart-bar' ),
			'underscores and digits'      => array( 'my_plugin2/icon_3' ),
			'single characters'           => array( 'a/b' ),
			'name that is not registered' => array( 'my-plugin/not-registered' ),
		);
	}

	/**
	 * @ticket 65089
	 *
	 * @dataProvider data_values_that_are_not_icon_names
	 *
	 * @param mixed $value The value to check.
	 */
	public function test_returns_false_for_other_values( $value ): void {
		$this->assertFalse( wp_is_icon_name( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: mixed }>
	 */
	public static function data_values_that_are_not_icon_names(): array {
		return array(
			'Dashicons class'          => array( 'dashicons-admin-post' ),
			'none'                     => array( 'none' ),
			'URL'                      => array( 'https://example.org/images/icon.png' ),
			'relative path with a dot' => array( 'images/icon.png' ),
			'absolute path'            => array( '/images/icon' ),
			'base64 SVG'               => array( 'data:image/svg+xml;base64,PHN2Zy8+' ),
			'uppercase'                => array( 'Core/Plus' ),
			'trailing newline'         => array( "core/plus\n" ),
			'two slashes'              => array( 'a/b/c' ),
			'no collection'            => array( '/plus' ),
			'no name'                  => array( 'core/' ),
			'leading hyphen'           => array( '-core/plus' ),
			'trailing hyphen'          => array( 'core/plus-' ),
			'empty string'             => array( '' ),
			'false'                    => array( false ),
			'null'                     => array( null ),
			'integer'                  => array( 42 ),
			'array'                    => array( array( 'core/plus' ) ),
		);
	}
}
