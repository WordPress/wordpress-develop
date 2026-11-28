<?php
/**
 * Test case for wp_print_admin_block_editor_font_faces().
 *
 * @package    WordPress
 * @subpackage Fonts
 *
 * @since 7.3.0
 *
 * @group fonts
 * @group fontface
 *
 * @covers wp_print_admin_block_editor_font_faces
 */
class Tests_Fonts_WpPrintAdminBlockEditorFontFaces extends WP_Font_Face_UnitTestCase {
	const FONTS_THEME = 'fonts-block-theme';

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/screen.php';
		switch_theme( static::FONTS_THEME );
	}

	public function tear_down() {
		$GLOBALS['current_screen'] = null;

		parent::tear_down();
	}

	/**
	 * @ticket 66854
	 */
	public function test_should_be_hooked_to_admin_print_styles() {
		$this->assertSame( 50, has_action( 'admin_print_styles', 'wp_print_admin_block_editor_font_faces' ) );
		$this->assertFalse( has_action( 'admin_print_styles', 'wp_print_font_faces' ) );
		$this->assertFalse( has_action( 'admin_print_styles', 'wp_print_font_faces_from_style_variations' ) );
	}

	/**
	 * @ticket 66854
	 */
	public function test_should_print_theme_and_style_variation_fonts_on_block_editor_screen() {
		set_current_screen( 'post' );
		get_current_screen()->is_block_editor( true );

		$expected = get_echo( 'wp_print_font_faces' ) . get_echo( 'wp_print_font_faces_from_style_variations' );

		$this->assertStringContainsString( '@font-face', $expected );
		$this->assertSame( $expected, get_echo( 'wp_print_admin_block_editor_font_faces' ) );
	}

	/**
	 * @ticket 66854
	 *
	 * @dataProvider data_screens_without_block_editor
	 *
	 * @param string $screen Screen ID.
	 */
	public function test_should_not_print_on_other_admin_screens( $screen ) {
		set_current_screen( $screen );

		$this->assertSame( '', get_echo( 'wp_print_admin_block_editor_font_faces' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_screens_without_block_editor() {
		return array(
			'dashboard'  => array( 'dashboard' ),
			'posts list' => array( 'edit-post' ),
			'plugins'    => array( 'plugins' ),
		);
	}

	/**
	 * @ticket 66854
	 */
	public function test_should_not_print_without_current_screen() {
		$GLOBALS['current_screen'] = null;

		$this->assertSame( '', get_echo( 'wp_print_admin_block_editor_font_faces' ) );
	}
}
