<?php
/**
 * Test get_theme_root_uri().
 *
 * @group theme
 *
 * @covers ::get_theme_root_uri
 */
class Tests_Theme_GetThemeRootUri extends WP_UnitTestCase {

	/**
	 * Backup of $wp_theme_directories.
	 *
	 * @var array|null
	 */
	protected $old_wp_theme_directories;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		global $wp_theme_directories;
		$this->old_wp_theme_directories = $wp_theme_directories;
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		global $wp_theme_directories;
		$wp_theme_directories = $this->old_wp_theme_directories;
		remove_all_filters( 'theme_root_uri' );
		parent::tear_down();
	}

	/**
	 * Tests that get_theme_root_uri() defaults to content_url( 'themes' ) when called without arguments.
	 *
	 * @ticket 17597
	 */
	public function test_get_theme_root_uri_default() {
		$expected = content_url( 'themes' );
		$actual   = get_theme_root_uri();

		$this->assertSame( $expected, $actual, 'Default theme root URI should match content_url( "themes" ).' );
	}

	/**
	 * Tests that get_theme_root_uri() handles relative theme roots.
	 *
	 * @ticket 17597
	 */
	public function test_get_theme_root_uri_with_relative_root() {
		$expected = content_url( 'custom-themes' );
		$actual   = get_theme_root_uri( 'default', 'custom-themes' );

		$this->assertSame( $expected, $actual, 'Relative theme root should resolve via content_url().' );
	}

	/**
	 * Tests that get_theme_root_uri() resolves absolute theme root within WP_CONTENT_DIR.
	 *
	 * @ticket 17597
	 */
	public function test_get_theme_root_uri_with_wp_content_dir_path() {
		global $wp_theme_directories;

		$custom_dir             = WP_CONTENT_DIR . '/my-themes';
		$wp_theme_directories   = (array) $wp_theme_directories;
		$wp_theme_directories[] = $custom_dir;

		$expected = content_url( '/my-themes' );
		$actual   = get_theme_root_uri( 'my-theme', $custom_dir );

		$this->assertSame( $expected, $actual, 'Theme root within WP_CONTENT_DIR should resolve relative to content_url().' );
	}

	/**
	 * Tests that get_theme_root_uri() resolves absolute theme root within ABSPATH.
	 *
	 * @ticket 17597
	 */
	public function test_get_theme_root_uri_with_abspath() {
		global $wp_theme_directories;

		$custom_dir             = ABSPATH . 'custom-themes';
		$wp_theme_directories   = (array) $wp_theme_directories;
		$wp_theme_directories[] = $custom_dir;

		$expected = site_url( 'custom-themes' );
		$actual   = get_theme_root_uri( 'my-theme', $custom_dir );

		$this->assertSame( $expected, $actual, 'Theme root within ABSPATH should resolve relative to site_url().' );
	}

	/**
	 * Tests that theme_root_uri filter modifies the returned URI.
	 *
	 * @ticket 17597
	 */
	public function test_theme_root_uri_filter() {
		add_filter(
			'theme_root_uri',
			static function ( $theme_root_uri, $siteurl, $stylesheet_or_template ) {
				return 'https://cdn.example.com/themes';
			},
			10,
			3
		);

		$this->assertSame( 'https://cdn.example.com/themes', get_theme_root_uri(), 'Filter should modify returned theme root URI.' );
	}
}
