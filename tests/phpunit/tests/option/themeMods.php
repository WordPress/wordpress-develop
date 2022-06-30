<?php

/**
 * @group option
 */
class Tests_Option_ThemeMods extends WP_UnitTestCase {

	public function test_theme_mod_default() {
		$this->assertFalse( get_theme_mod( 'non_existent' ) );
	}

	public function test_theme_mod_defined_default() {
		$this->assertSame( 'default', get_theme_mod( 'non_existent', 'default' ) );
	}

	public function test_theme_mod_set() {
		$expected = 'value';
		set_theme_mod( 'test_name', $expected );
		$this->assertSame( $expected, get_theme_mod( 'test_name' ) );
	}

	/**
	 * @ticket 51423
	 */
	public function test_theme_mod_set_with_invalid_theme_mods_option() {
		$theme_slug = get_option( 'stylesheet' );
		update_option( 'theme_mods_' . $theme_slug, '' );
		self::test_theme_mod_set();
	}

	public function test_theme_mod_update() {
		set_theme_mod( 'test_update', 'first_value' );
		$expected = 'updated_value';
		set_theme_mod( 'test_update', $expected );
		$this->assertSame( $expected, get_theme_mod( 'test_update' ) );
	}

	public function test_theme_mod_remove() {
		set_theme_mod( 'test_remove', 'value' );
		remove_theme_mod( 'test_remove' );
		$this->assertFalse( get_theme_mod( 'test_remove' ) );
	}

	/**
	 * @ticket 34290
	 *
	 * @dataProvider data_theme_mod_default_value_with_percent_symbols
	 */
	public function test_theme_mod_default_value_with_percent_symbols( $default, $expected ) {
		$this->assertSame( $expected, get_theme_mod( 'test_name', $default ) );
	}

	public function data_theme_mod_default_value_with_percent_symbols() {
		return array(
			array(
				'100%',
				'100%',
			),
			array(
				'%s',
				get_template_directory_uri(),
			),
			array(
				'%s%s',
				get_template_directory_uri() . get_stylesheet_directory_uri(),
			),
			array(
				'%1$s%s',
				get_template_directory_uri() . get_template_directory_uri(),
			),
			array(
				'%2$s%s',
				get_stylesheet_directory_uri() . get_template_directory_uri(),
			),
			array(
				'%1$s%2$s',
				get_template_directory_uri() . get_stylesheet_directory_uri(),
			),
			array(
				'%40s%40s',
				get_template_directory_uri() . get_stylesheet_directory_uri(),
			),
			array(
				'%%1',
				'%%1',
			),
			array(
				'%1%',
				'%1%',
			),
			array(
				'1%%',
				'1%%',
			),
			array(
				'%%s',
				'%%s',
			),
			array(
				'%s%',
				get_template_directory_uri(),
			),
			array(
				's%%',
				's%%',
			),
		);
	}

}
