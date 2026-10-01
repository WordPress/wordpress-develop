<?php

/**
 * Tests for the bundled MediaElement.js library.
 *
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies_Mediaelement extends WP_UnitTestCase {

	/**
	 * The version of the bundled MediaElement.js library, as declared in the library itself.
	 *
	 * @var string
	 */
	private static $bundled_version;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		$bundle = file_get_contents( ABSPATH . WPINC . '/js/mediaelement/mediaelement-and-player.js' );

		if ( preg_match( "/mejs\.version = '([^']+)'/", $bundle, $matches ) ) {
			self::$bundled_version = $matches[1];
		}
	}

	/**
	 * Ensures the version of the bundled library is detectable.
	 *
	 * If this fails, the version extraction regex in this test class needs
	 * updating to match the current MediaElement.js source layout.
	 *
	 * @ticket 56320
	 */
	public function test_bundled_library_version_is_detectable() {
		$this->assertNotEmpty( self::$bundled_version );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+/', self::$bundled_version );
	}

	/**
	 * Ensures registered MediaElement script versions match the bundled library version.
	 *
	 * Guards against updating the bundled files without bumping the registered
	 * versions (or vice versa), which would break browser cache busting.
	 *
	 * @ticket 56320
	 *
	 * @dataProvider data_mediaelement_script_handles
	 *
	 * @param string $handle Script handle to check.
	 */
	public function test_script_version_matches_bundled_library( $handle ) {
		$scripts = new WP_Scripts();
		wp_default_scripts( $scripts );

		$script = $scripts->query( $handle, 'registered' );

		$this->assertInstanceOf( '_WP_Dependency', $script );
		$this->assertSame( self::$bundled_version, $script->ver );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_mediaelement_script_handles() {
		return array(
			'mediaelement'       => array( 'mediaelement' ),
			'mediaelement-core'  => array( 'mediaelement-core' ),
			'mediaelement-vimeo' => array( 'mediaelement-vimeo' ),
		);
	}

	/**
	 * Ensures the registered MediaElement stylesheet version matches the bundled library version.
	 *
	 * @ticket 56320
	 */
	public function test_style_version_matches_bundled_library() {
		$styles = new WP_Styles();
		wp_default_styles( $styles );

		$style = $styles->query( 'mediaelement', 'registered' );

		$this->assertInstanceOf( '_WP_Dependency', $style );
		$this->assertSame( self::$bundled_version, $style->ver );
	}

	/**
	 * Ensures the MediaElement settings include a resolvable icon sprite path.
	 *
	 * Since MediaElement.js 5.0, player control icons are referenced from an
	 * SVG sprite via the `iconSprite` setting. Without it, players fall back
	 * to a page-relative path and icons fail to load.
	 *
	 * @ticket 56320
	 */
	public function test_mejs_settings_include_icon_sprite() {
		$scripts = new WP_Scripts();
		wp_default_scripts( $scripts );

		$data = $scripts->get_data( 'mediaelement', 'data' );

		$this->assertIsString( $data );
		$this->assertStringContainsString( '"iconSprite"', $data );
		$this->assertStringContainsString( 'mejs-controls.svg', $data );

		$this->assertFileExists( ABSPATH . WPINC . '/js/mediaelement/mejs-controls.svg' );
	}
}
