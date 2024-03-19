<?php
/**
 * @group themes
 *
 * @covers ::add_editor_style
 */

class Tests_Theme_addEditorStyle extends WP_UnitTestCase {
	/**
	 * Reset the editor styles to an empty array.
	 */
	public function set_up() {
		parent::set_up();
		global $editor_styles;
		$editor_styles = array();
	}

	/**
	 * Tests the default editor styles in RTL mode.
	 *
	 * @ticket 52294
	 *
	 * @return void
	 */
	public function test_add_editor_style_default() {
		global $editor_styles;

		add_editor_style();
		$this->assertSame( array( 'editor-style.css' ), $editor_styles );
	}

	/**
	 * Runs default setting in rtl mode.
	 *
	 * @ticket 52294
	 *
	 * @return void
	 */
	public function test_add_editor_style_default_rtl_add() {
		global $editor_styles, $wp_locale;

		$direction = $wp_locale->text_direction;
		// set to rtl mode
		$wp_locale->text_direction = 'rtl';
		add_editor_style();
		$wp_locale->text_direction = $direction;

		$this->assertSame( array( 'editor-style.css', 'editor-style-rtl.css' ), $editor_styles );
	}

	/**
	 * Runs default setting in rtl mode and replace.
	 *
	 * @ticket 52294
	 *
	 * @return void
	 */
	public function test_add_editor_style_default_rtl_replace() {
		global $editor_styles, $wp_locale;

		$direction = $wp_locale->text_direction;
		// set to rtl mode
		$wp_locale->text_direction = 'rtl';
		add_editor_style( null, 'replace' );
		$wp_locale->text_direction = $direction;

		$this->assertSame( array( 'editor-style-rtl.css' ), $editor_styles );
	}

	/**
	 * Runs with custom path.
	 *
	 * @ticket 52294
	 *
	 * @return void
	 */
	public function test_add_editor_style_custom_path() {
		global $editor_styles;

		add_editor_style( './assets/css/style-editor.css' );
		$this->assertSame( array( './assets/css/style-editor.css' ), $editor_styles );
	}

	/**
	 * Runs with custom path in rtl mode.
	 *
	 * @ticket 52294
	 *
	 * @return void
	 */
	public function test_add_editor_style_default_custom_path_rtl_add() {
		global $editor_styles, $wp_locale;
		$direction = $wp_locale->text_direction;
		// set to rtl mode
		$wp_locale->text_direction = 'rtl';
		add_editor_style( './assets/css/style-editor.css' );
		$wp_locale->text_direction = $direction;

		$this->assertSame( array( './assets/css/style-editor.css', './assets/css/style-editor-rtl.css' ), $editor_styles );
	}

	/**
	 * Runs with custom path in rtl mode and replace.
	 *
	 * @ticket 52294
	 *
	 * @return void
	 */
	public function test_add_editor_style_default_custom_path_rtl_replace() {
		global $editor_styles, $wp_locale;
		$direction = $wp_locale->text_direction;
		// set to rtl mode
		$wp_locale->text_direction = 'rtl';
		add_editor_style( './assets/css/style-editor.css', 'replace' );
		$wp_locale->text_direction = $direction;

		$this->assertSame( array( './assets/css/style-editor-rtl.css' ), $editor_styles );
	}

	/**
	 * Checks that theme fetures are set by add_editor_style().
	 *
	 * @ticket 52294
	 *
	 * @return void
	 */
	public function test_add_editor_style_sets_theme_support() {
		global $_wp_theme_features;
		$old_value = $_wp_theme_features['editor-style'];
		unset( $_wp_theme_features['editor-style'] );

		add_editor_style();

		$this->assertContains( 'editor-style', array_keys( $_wp_theme_features ) );

		$_wp_theme_features['editor-style'] = $old_value;
	}
}
