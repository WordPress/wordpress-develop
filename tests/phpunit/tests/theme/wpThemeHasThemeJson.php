<?php

require_once __DIR__ . '/base.php';

/**
 * Tests wp_theme_has_theme_json().
 *
 * @group theme_json
 *
 * @covers ::wp_theme_has_theme_json
 */
class Tests_Theme_WpThemeHasThemeJson extends WP_Theme_UnitTestCase {

	/**
	 * @ticket 56975
	 *
	 * @dataProvider data_theme_has_theme_json_reports_correctly
	 *
	 * @param string $theme    The slug of the theme to switch to.
	 * @param bool   $expected The expected result.
	 */
	public function test_theme_has_theme_json_reports_correctly( $theme, $expected ) {
		switch_theme( $theme );
		$this->assertSame( $expected, wp_theme_has_theme_json() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_theme_has_theme_json_reports_correctly() {
		return array(
			'a theme with theme.json'       => array(
				'theme'    => 'block-theme',
				'expected' => true,
			),
			'a theme without theme.json'    => array(
				'theme'    => 'default',
				'expected' => false,
			),
			'a child theme with theme.json' => array(
				'theme'    => 'block-theme-child',
				'expected' => true,
			),
			'a child theme without theme.json and parent theme with theme.json' => array(
				'theme'    => 'block-theme-child-no-theme-json',
				'expected' => true,
			),
			'a child theme without theme.json and parent theme without theme.json' => array(
				'theme'    => 'default-child-no-theme-json',
				'expected' => false,
			),
		);
	}

	/**
	 * @ticket 52991
	 */
	public function test_switching_themes_recalculates_support() {
		// The "default" theme doesn't have theme.json support.
		switch_theme( 'default' );
		$default = wp_theme_has_theme_json();

		// Switch to a theme that does have support.
		switch_theme( 'block-theme' );
		$block_theme = wp_theme_has_theme_json();

		$this->assertFalse( $default, 'The "default" theme should not report theme.json support.' );
		$this->assertTrue( $block_theme, 'The block theme should report theme.json support.' );
	}
}
