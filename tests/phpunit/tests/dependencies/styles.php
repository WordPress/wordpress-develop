<?php
/**
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies_Styles extends WP_UnitTestCase {
	private $old_wp_styles;
	private $old_wp_scripts;

	function setUp() {
		parent::setUp();

		if ( empty( $GLOBALS['wp_styles'] ) ) {
			$GLOBALS['wp_styles'] = null;
		}

		$this->old_wp_styles = $GLOBALS['wp_styles'];

		if ( empty( $GLOBALS['wp_scripts'] ) ) {
			$GLOBALS['wp_scripts'] = null;
		}

		$this->old_wp_styles = $GLOBALS['wp_scripts'];

		remove_action( 'wp_default_styles', 'wp_default_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		$GLOBALS['wp_styles']                  = new WP_Styles();
		$GLOBALS['wp_styles']->default_version = get_bloginfo( 'version' );

		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );
	}

	function tearDown() {
		$GLOBALS['wp_styles']  = $this->old_wp_styles;
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;

		add_action( 'wp_default_styles', 'wp_default_styles' );
		add_action( 'wp_print_styles', 'print_emoji_styles' );

		if ( current_theme_supports( 'wp-block-styles' ) ) {
			remove_theme_support( 'wp-block-styles' );
		}

		parent::tearDown();
	}

	/**
	 * Test versioning
	 *
	 * @ticket 11315
	 */
	function test_wp_enqueue_style() {
		wp_enqueue_style( 'no-deps-no-version', 'example.com' );
		wp_enqueue_style( 'no-deps-version', 'example.com', array(), 1.2 );
		wp_enqueue_style( 'no-deps-null-version', 'example.com', array(), null );
		wp_enqueue_style( 'no-deps-null-version-print-media', 'example.com', array(), null, 'print' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<link rel='stylesheet' id='no-deps-no-version-css'  href='http://example.com?ver=$ver' type='text/css' media='all' />\n";
		$expected .= "<link rel='stylesheet' id='no-deps-version-css'  href='http://example.com?ver=1.2' type='text/css' media='all' />\n";
		$expected .= "<link rel='stylesheet' id='no-deps-null-version-css'  href='http://example.com' type='text/css' media='all' />\n";
		$expected .= "<link rel='stylesheet' id='no-deps-null-version-print-media-css'  href='http://example.com' type='text/css' media='print' />\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_styles' ) );

		// No styles left to print.
		$this->assertEquals( '', get_echo( 'wp_print_styles' ) );
	}

	/**
	 * @ticket 42804
	 */
	function test_wp_enqueue_style_with_html5_support_does_not_contain_type_attribute() {
		add_theme_support( 'html5', array( 'style' ) );

		$GLOBALS['wp_styles']                  = new WP_Styles();
		$GLOBALS['wp_styles']->default_version = get_bloginfo( 'version' );

		wp_enqueue_style( 'no-deps-no-version', 'example.com' );

		$ver      = get_bloginfo( 'version' );
		$expected = "<link rel='stylesheet' id='no-deps-no-version-css'  href='http://example.com?ver=$ver' media='all' />\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * Test the different protocol references in wp_enqueue_style
	 *
	 * @global WP_Styles $wp_styles
	 * @ticket 16560
	 */
	public function test_protocols() {
		// Init.
		global $wp_styles;
		$base_url_backup     = $wp_styles->base_url;
		$wp_styles->base_url = 'http://example.com/wordpress';
		$expected            = '';
		$ver                 = get_bloginfo( 'version' );

		// Try with an HTTP reference.
		wp_enqueue_style( 'reset-css-http', 'http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css' );
		$expected .= "<link rel='stylesheet' id='reset-css-http-css'  href='http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' type='text/css' media='all' />\n";

		// Try with an HTTPS reference.
		wp_enqueue_style( 'reset-css-https', 'http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css' );
		$expected .= "<link rel='stylesheet' id='reset-css-https-css'  href='http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' type='text/css' media='all' />\n";

		// Try with an automatic protocol reference (//).
		wp_enqueue_style( 'reset-css-doubleslash', '//yui.yahooapis.com/2.8.1/build/reset/reset-min.css' );
		$expected .= "<link rel='stylesheet' id='reset-css-doubleslash-css'  href='//yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' type='text/css' media='all' />\n";

		// Try with a local resource and an automatic protocol reference (//).
		$url = '//my_plugin/style.css';
		wp_enqueue_style( 'plugin-style', $url );
		$expected .= "<link rel='stylesheet' id='plugin-style-css'  href='$url?ver=$ver' type='text/css' media='all' />\n";

		// Try with a bad protocol.
		wp_enqueue_style( 'reset-css-ftp', 'ftp://yui.yahooapis.com/2.8.1/build/reset/reset-min.css' );
		$expected .= "<link rel='stylesheet' id='reset-css-ftp-css'  href='{$wp_styles->base_url}ftp://yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' type='text/css' media='all' />\n";

		// Go!
		$this->assertEquals( $expected, get_echo( 'wp_print_styles' ) );

		// No styles left to print.
		$this->assertEquals( '', get_echo( 'wp_print_styles' ) );

		// Cleanup.
		$wp_styles->base_url = $base_url_backup;
	}

	/**
	 * Test if inline styles work
	 *
	 * @ticket 24813
	 */
	public function test_inline_styles() {

		$style  = ".thing {\n";
		$style .= "\tbackground: red;\n";
		$style .= '}';

		$expected  = "<link rel='stylesheet' id='handle-css'  href='http://example.com?ver=1' type='text/css' media='all' />\n";
		$expected .= "<style id='handle-inline-css' type='text/css'>\n";
		$expected .= "$style\n";
		$expected .= "</style>\n";

		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );
		wp_add_inline_style( 'handle', $style );

		// No styles left to print.
		$this->assertEquals( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * Test if inline styles work with concatination
	 *
	 * @global WP_Styles $wp_styles
	 * @ticket 24813
	 */
	public function test_inline_styles_concat() {

		global $wp_styles;

		$wp_styles->do_concat    = true;
		$wp_styles->default_dirs = array( '/wp-admin/', '/wp-includes/css/' ); // Default dirs as in wp-includes/script-loader.php.

		$style  = ".thing {\n";
		$style .= "\tbackground: red;\n";
		$style .= '}';

		$expected  = "<link rel='stylesheet' id='handle-css'  href='http://example.com?ver=1' type='text/css' media='all' />\n";
		$expected .= "<style id='handle-inline-css' type='text/css'>\n";
		$expected .= "$style\n";
		$expected .= "</style>\n";

		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );
		wp_add_inline_style( 'handle', $style );

		wp_print_styles();
		$this->assertEquals( $expected, $wp_styles->print_html );

	}

	/**
	 * Test if multiple inline styles work
	 *
	 * @ticket 24813
	 */
	public function test_multiple_inline_styles() {

		$style1  = ".thing1 {\n";
		$style1 .= "\tbackground: red;\n";
		$style1 .= '}';

		$style2  = ".thing2 {\n";
		$style2 .= "\tbackground: blue;\n";
		$style2 .= '}';

		$expected  = "<link rel='stylesheet' id='handle-css'  href='http://example.com?ver=1' type='text/css' media='all' />\n";
		$expected .= "<style id='handle-inline-css' type='text/css'>\n";
		$expected .= "$style1\n";
		$expected .= "$style2\n";
		$expected .= "</style>\n";

		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );
		wp_add_inline_style( 'handle', $style1 );
		wp_add_inline_style( 'handle', $style2 );

		// No styles left to print.
		$this->assertEquals( $expected, get_echo( 'wp_print_styles' ) );

	}

	/**
	 * Test if a plugin doing it the wrong way still works
	 *
	 * @expectedIncorrectUsage wp_add_inline_style
	 * @ticket 24813
	 */
	public function test_plugin_doing_inline_styles_wrong() {

		$style  = "<style id='handle-inline-css' type='text/css'>\n";
		$style .= ".thing {\n";
		$style .= "\tbackground: red;\n";
		$style .= "}\n";
		$style .= '</style>';

		$expected  = "<link rel='stylesheet' id='handle-css'  href='http://example.com?ver=1' type='text/css' media='all' />\n";
		$expected .= "$style\n";

		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );

		wp_add_inline_style( 'handle', $style );

		$this->assertEquals( $expected, get_echo( 'wp_print_styles' ) );

	}

	/**
	 * Test to make sure <style> tags aren't output if there are no inline styles.
	 *
	 * @ticket 24813
	 */
	public function test_unnecessary_style_tags() {

		$expected = "<link rel='stylesheet' id='handle-css'  href='http://example.com?ver=1' type='text/css' media='all' />\n";

		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );

		$this->assertEquals( $expected, get_echo( 'wp_print_styles' ) );

	}

	/**
	 * Test to make sure that inline styles attached to conditional
	 * stylesheets are also conditional.
	 */
	public function test_conditional_inline_styles_are_also_conditional() {
		$expected = <<<CSS
<!--[if IE]>
<link rel='stylesheet' id='handle-css'  href='http://example.com?ver=1' type='text/css' media='all' />
<style id='handle-inline-css' type='text/css'>
a { color: blue; }
</style>
<![endif]-->

CSS;
		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );
		wp_style_add_data( 'handle', 'conditional', 'IE' );
		wp_add_inline_style( 'handle', 'a { color: blue; }' );

		$this->assertEqualsIgnoreEOL( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * Testing 'wp_register_style' return boolean success/failure value.
	 *
	 * @ticket 31126
	 */
	function test_wp_register_style() {
		$this->assertTrue( wp_register_style( 'duplicate-handler', 'http://example.com' ) );
		$this->assertFalse( wp_register_style( 'duplicate-handler', 'http://example.com' ) );
	}

	/**
	 * @ticket 35229
	 */
	function test_wp_add_inline_style_for_handle_without_source() {
		$style = 'a { color: blue; }';

		$expected  = "<link rel='stylesheet' id='handle-one-css'  href='http://example.com?ver=1' type='text/css' media='all' />\n";
		$expected .= "<link rel='stylesheet' id='handle-two-css'  href='http://example.com?ver=1' type='text/css' media='all' />\n";
		$expected .= "<style id='handle-three-inline-css' type='text/css'>\n";
		$expected .= "$style\n";
		$expected .= "</style>\n";

		wp_register_style( 'handle-one', 'http://example.com', array(), 1 );
		wp_register_style( 'handle-two', 'http://example.com', array(), 1 );
		wp_register_style( 'handle-three', false, array( 'handle-one', 'handle-two' ) );

		wp_enqueue_style( 'handle-three' );
		wp_add_inline_style( 'handle-three', $style );

		$this->assertEquals( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * @ticket 35921
	 * @dataProvider data_styles_with_media
	 */
	function test_wp_enqueue_style_with_media( $expected, $media ) {
		wp_enqueue_style( 'handle', 'http://example.com', array(), 1, $media );
		$this->assertContains( $expected, get_echo( 'wp_print_styles' ) );
	}

	function data_styles_with_media() {
		return array(
			array(
				"media='all'",
				'all',
			),
			array(
				"media='(orientation: portrait)'",
				'(orientation: portrait)',
			),
			array(
				"media='(max-width: 640px)'",
				'(max-width: 640px)',
			),
			array(
				"media='print and (min-width: 25cm)'",
				'print and (min-width: 25cm)',
			),
			array(
				"media='screen and (color), projection and (color)'",
				'screen and (color), projection and (color)',
			),
			array(
				"media='not screen and (color)'",
				'not screen and (color)',
			),
		);
	}

	/**
	 * Tests that visual block styles are enqueued in the editor even when there is not theme support for 'wp-block-styles'.
	 *
	 * Visual block styles should always be enqueued when editing to avoid the appearance of a broken editor.
	 */
	function test_block_styles_for_editing_without_theme_support() {
		// Confirm we are without theme support by default.
		$this->assertFalse( current_theme_supports( 'wp-block-styles' ) );

		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ) );
		wp_enqueue_style( 'wp-edit-blocks' );
		$this->assertTrue( wp_style_is( 'wp-block-library-theme' ) );
	}

	/**
	 * Tests that visual block styles are enqueued when there is theme support for 'wp-block-styles'.
	 *
	 * Visual block styles should always be enqueued when editing to avoid the appearance of a broken editor.
	 */
	function test_block_styles_for_editing_with_theme_support() {
		add_theme_support( 'wp-block-styles' );

		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ) );
		wp_common_block_scripts_and_styles();
		$this->assertTrue( wp_style_is( 'wp-block-library-theme' ) );
	}

	/**
	 * Tests that visual block styles are not enqueued for viewing when there is no theme support for 'wp-block-styles'.
	 *
	 * Visual block styles should not be enqueued unless a theme opts in.
	 * This way we avoid style conflicts with existing themes.
	 */
	function test_no_block_styles_for_viewing_without_theme_support() {
		// Confirm we are without theme support by default.
		$this->assertFalse( current_theme_supports( 'wp-block-styles' ) );

		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ) );
		wp_enqueue_style( 'wp-block-library' );
		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ) );
	}

	/**
	 * Tests that visual block styles are enqueued for viewing when there is theme support for 'wp-block-styles'.
	 *
	 * Visual block styles should be enqueued when a theme opts in.
	 */
	function test_block_styles_for_viewing_with_theme_support() {
		add_theme_support( 'wp-block-styles' );

		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ) );
		wp_common_block_scripts_and_styles();
		$this->assertTrue( wp_style_is( 'wp-block-library-theme' ) );
	}
}
