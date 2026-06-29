<?php
/**
 * Test wp_is_font_library_enabled().
 *
 * @package WordPress
 * @subpackage Font Library
 *
 * @group fonts
 * @group font-library
 *
 * @covers ::wp_is_font_library_enabled
 */
class Tests_Fonts_WpIsFontLibraryEnabled extends WP_UnitTestCase {

	/**
	 * @ticket 65550
	 */
	public function test_is_enabled_by_default() {
		$this->assertTrue( wp_is_font_library_enabled() );
	}

	/**
	 * @ticket 65550
	 */
	public function test_can_be_disabled_via_filter() {
		add_filter( 'wp_is_font_library_enabled', '__return_false' );

		$this->assertFalse( wp_is_font_library_enabled() );
	}

	/**
	 * @ticket 65550
	 */
	public function test_filtered_value_is_cast_to_boolean() {
		add_filter( 'wp_is_font_library_enabled', '__return_zero' );

		$this->assertFalse( wp_is_font_library_enabled() );
	}
}
