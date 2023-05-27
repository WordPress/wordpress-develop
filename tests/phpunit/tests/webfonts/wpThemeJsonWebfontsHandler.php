<?php
/**
 * Enqueue only webfonts listed in theme.json
 *
 * @package WordPress
 */

/**
 * Integration tests for the theme.json webfonts handler.
 *
 * @group webfonts
 * @group themes
 * @covers _wp_theme_json_webfonts_handler
 */
class Tests_Webfonts_wpThemeJsonWebfontsHandler extends WP_UnitTestCase {

	/**
	 * WP_Styles instance reference
	 *
	 * @var WP_Styles
	 */
	private $orig_wp_styles;

	/**
	 * Theme root path.
	 *
	 * @var string
	 */
	private $theme_root;

	/**
	 * The old theme root path.
	 *
	 * @var string
	 */
	private $orig_theme_dir;

	public function set_up() {
		parent::set_up();

		global $wp_styles;
		$this->orig_wp_styles = $wp_styles;
		$wp_styles            = null;

		$this->theme_root     = realpath( DIR_TESTDATA . '/themedir1' );
		$this->orig_theme_dir = $GLOBALS['wp_theme_directories'];

		// /themes is necessary as theme.php functions assume /themes is the root if there is only one root.
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $this->theme_root );

		$theme_root_callback = function () {
			return $this->theme_root;
		};

		add_filter( 'theme_root', $theme_root_callback );
		add_filter( 'stylesheet_root', $theme_root_callback );
		add_filter( 'template_root', $theme_root_callback );

		// Clear caches.
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tear_down() {
		global $wp_styles;
		$wp_styles = $this->orig_wp_styles;

		// Restore the original theme directory setup.
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );

		parent::tear_down();
	}

	/**
	 * @ticket 55567
	 * @ticket 46370
	 * @ticket 57430
	 */
	public function test_font_face_generated_from_themejson() {
		$this->setup_theme_and_test( 'webfonts-theme' );

		$expected = <<<EOF
<style id='wp-webfonts-inline-css' type='text/css'>
@font-face{font-family:"Source Serif Pro";font-style:normal;font-weight:200 900;font-display:fallback;src:url('THEME_ROOT_URL/assets/fonts/SourceSerif4Variable-Roman.ttf.woff2') format('woff2');font-stretch:normal;}@font-face{font-family:"Source Serif Pro";font-style:italic;font-weight:200 900;font-display:fallback;src:url('THEME_ROOT_URL/assets/fonts/SourceSerif4Variable-Italic.ttf.woff2') format('woff2');font-stretch:normal;}
</style>
EOF;
		$expected = str_replace( 'THEME_ROOT_URL', get_stylesheet_directory_uri(), $expected );
		$expected = str_replace( "\r\n", "\n", $expected );

		$this->assertStringContainsString(
			$expected,
			get_echo( 'wp_print_styles' )
		);
	}

	/**
	 * @dataProvider data_font_face_not_generated
	 *
	 * @ticket 55567
	 * @ticket 46370
	 */
	public function test_font_face_not_generated( $theme_name ) {
		$this->setup_theme_and_test( $theme_name );

		$actual = get_echo( 'wp_print_styles' );
		$this->assertStringNotContainsString( "<style id='wp-webfonts-inline-css", $actual );
		$this->assertStringNotContainsString( '@font-face', $actual );
	}

	/**
	 * Data provider for unhappy paths.
	 *
	 * @return string[][]
	 */
	public function data_font_face_not_generated() {
		return array(
			'classic theme with no theme.json' => array( 'default' ),
			'no "fontFace" in theme.json'      => array( 'block-theme' ),
			'empty "fontFace" in theme.json'   => array( 'empty-fontface-theme' ),
		);
	}

	/**
	 * Sets up the theme and test.
	 *
	 * @param string $theme_name Name of the theme to switch to for the test.
	 */
	private function setup_theme_and_test( $theme_name ) {
		switch_theme( $theme_name );
		do_action( 'after_setup_theme' );
		wp_clean_theme_json_cache();
		do_action( 'plugins_loaded' );
		do_action( 'wp_loaded' );
		do_action( 'wp_enqueue_scripts' );
	}
}
