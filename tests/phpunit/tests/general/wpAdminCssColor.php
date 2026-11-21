<?php
/**
 * Test wp_admin_css_color().
 *
 * @group general
 * @group template
 *
 * @covers ::wp_admin_css_color
 */
class Tests_General_wpAdminCssColor extends WP_UnitTestCase {

	/**
	 * Original $_wp_admin_css_colors global.
	 *
	 * @var array|null
	 */
	private $original_wp_admin_css_colors;

	public function set_up() {
		parent::set_up();

		global $_wp_admin_css_colors;
		$this->original_wp_admin_css_colors = isset( $_wp_admin_css_colors ) ? $_wp_admin_css_colors : null;
	}

	public function tear_down() {
		global $_wp_admin_css_colors;
		$_wp_admin_css_colors = $this->original_wp_admin_css_colors;

		parent::tear_down();
	}

	/**
	 * Tests registering an admin color scheme.
	 *
	 * @ticket 65819
	 */
	public function test_wp_admin_css_color() {
		global $_wp_admin_css_colors;

		$url    = 'https://example.org/wp-admin/css/colors/custom.css';
		$colors = array( '#111111', '#222222', '#333333' );
		$icons  = array(
			'base'    => '#444444',
			'focus'   => '#555555',
			'current' => '#666666',
		);

		wp_admin_css_color( 'custom', 'Custom', $url, $colors, $icons );

		$this->assertSame( 'Custom', $_wp_admin_css_colors['custom']->name );
		$this->assertSame( $url, $_wp_admin_css_colors['custom']->url );
		$this->assertSame( $colors, $_wp_admin_css_colors['custom']->colors );
		$this->assertSame( $icons, $_wp_admin_css_colors['custom']->icon_colors );
	}
}
