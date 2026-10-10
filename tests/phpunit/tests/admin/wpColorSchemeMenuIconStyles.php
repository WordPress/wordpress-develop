<?php
/**
 * Unit tests covering the wp_color_scheme_menu_icon_styles() function.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 7.2.0
 *
 * @group admin
 *
 * @covers ::wp_color_scheme_menu_icon_styles
 */
class Tests_Admin_WpColorSchemeMenuIconStyles extends WP_UnitTestCase {

	/**
	 * Registered color schemes before each test.
	 *
	 * @var array|null
	 */
	private $original_admin_css_colors;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}

	public function set_up() {
		parent::set_up();

		$this->original_admin_css_colors = $GLOBALS['_wp_admin_css_colors'] ?? null;
		$GLOBALS['wp_styles']            = null;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down() {
		$GLOBALS['_wp_admin_css_colors'] = $this->original_admin_css_colors;
		$GLOBALS['wp_styles']            = null;

		parent::tear_down();
	}

	/**
	 * Registers a color scheme and makes it the scheme of the current user.
	 *
	 * @param string $url   URL of the stylesheet of the scheme.
	 * @param array  $icons Icon colors of the scheme.
	 */
	private function use_color_scheme( $url, $icons ): void {
		wp_admin_css_color( 'test-scheme', 'Test scheme', $url, array( '#111111', '#222222' ), $icons );
		update_user_option( get_current_user_id(), 'admin_color', 'test-scheme', true );
	}

	/**
	 * Dashicons back-compat: a third-party scheme that only colors `::before` gets its icon colors on SVG menu icons.
	 *
	 * @ticket 65089
	 */
	public function test_adds_icon_colors_of_third_party_scheme(): void {
		$this->use_color_scheme(
			'https://example.org/scheme.css',
			array(
				'base'    => '#aaaaaa',
				'focus'   => '#bbbbbb',
				'current' => '#cccccc',
			)
		);

		wp_color_scheme_menu_icon_styles();

		$styles = implode( '', (array) wp_styles()->get_data( 'colors', 'after' ) );

		$this->assertStringContainsString( 'color:#aaaaaa', $styles );
		$this->assertStringContainsString( 'color:#bbbbbb', $styles );
		$this->assertStringContainsString( 'color:#cccccc', $styles );
	}

	/**
	 * @ticket 65089
	 *
	 * @dataProvider data_schemes_without_icon_styles
	 *
	 * @param string $url   URL of the stylesheet of the scheme.
	 * @param array  $icons Icon colors of the scheme.
	 */
	public function test_adds_nothing_for_other_schemes( $url, $icons ): void {
		$this->use_color_scheme( $url, $icons );

		wp_color_scheme_menu_icon_styles();

		$this->assertEmpty( wp_styles()->get_data( 'colors', 'after' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: string, 1: array<string, string> }>
	 */
	public static function data_schemes_without_icon_styles(): array {
		$icons = array(
			'base'    => '#aaaaaa',
			'focus'   => '#bbbbbb',
			'current' => '#cccccc',
		);

		return array(
			'core scheme'           => array( admin_url( 'css/colors/light/colors.css' ), $icons ),
			'no stylesheet'         => array( '', $icons ),
			'no icon colors'        => array( 'https://example.org/scheme.css', array() ),
			'missing color'         => array( 'https://example.org/scheme.css', array_diff_key( $icons, array( 'current' => '' ) ) ),
			'color that is not hex' => array( 'https://example.org/scheme.css', array_merge( $icons, array( 'focus' => 'red' ) ) ),
		);
	}
}
