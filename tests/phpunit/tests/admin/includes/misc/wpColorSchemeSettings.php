<?php
/**
 * Test wp_color_scheme_settings().
 *
 * @group admin
 * @group misc
 */
class Tests_wp_color_scheme_settings extends WP_UnitTestCase {

	/**
	 * Original $_wp_admin_css_colors global.
	 *
	 * @var array
	 */
	private $orig_wp_admin_css_colors;

	public function set_up() {
		parent::set_up();
		global $_wp_admin_css_colors;
		$this->orig_wp_admin_css_colors = $_wp_admin_css_colors;
	}

	public function tear_down() {
		global $_wp_admin_css_colors;
		$_wp_admin_css_colors = $this->orig_wp_admin_css_colors;
		parent::tear_down();
	}

	/**
	 * Test wp_color_scheme_settings() with a valid color scheme.
	 */
	public function test_wp_color_scheme_settings_valid_scheme() {
		global $_wp_admin_css_colors;

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'admin_color', 'blue' );

		$_wp_admin_css_colors = array(
			'blue' => (object) array(
				'icon_colors' => array(
					'base'    => '#e5f8ff',
					'focus'   => '#fff',
					'current' => '#fff',
				),
			),
		);

		ob_start();
		wp_color_scheme_settings();
		$output = ob_get_clean();

		$expected = array(
			'icons' => array(
				'base'    => '#e5f8ff',
				'focus'   => '#fff',
				'current' => '#fff',
			),
		);

		$this->assertStringContainsString( 'var _wpColorScheme = ' . wp_json_encode( $expected ), $output );
	}

	/**
	 * Test wp_color_scheme_settings() with an invalid color scheme (fallback to modern).
	 */
	public function test_wp_color_scheme_settings_invalid_scheme_fallback_to_modern() {
		global $_wp_admin_css_colors;

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'admin_color', 'non-existent' );

		$_wp_admin_css_colors = array(
			'modern' => (object) array(
				'icon_colors' => array(
					'base'    => '#f0f0f1',
					'focus'   => '#fff',
					'current' => '#fff',
				),
			),
		);

		ob_start();
		wp_color_scheme_settings();
		$output = ob_get_clean();

		$expected = array(
			'icons' => array(
				'base'    => '#f0f0f1',
				'focus'   => '#fff',
				'current' => '#fff',
			),
		);

		$this->assertStringContainsString( 'var _wpColorScheme = ' . wp_json_encode( $expected ), $output );
	}

	/**
	 * Test wp_color_scheme_settings() with no icon colors defined (fallback to defaults).
	 */
	public function test_wp_color_scheme_settings_no_icon_colors_fallback() {
		global $_wp_admin_css_colors;

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'admin_color', 'empty-scheme' );

		$_wp_admin_css_colors = array(
			'empty-scheme' => (object) array(),
			'modern'       => (object) array(),
		);

		ob_start();
		wp_color_scheme_settings();
		$output = ob_get_clean();

		$expected = array(
			'icons' => array(
				'base'    => '#a7aaad',
				'focus'   => '#72aee6',
				'current' => '#fff',
			),
		);

		$this->assertStringContainsString( 'var _wpColorScheme = ' . wp_json_encode( $expected ), $output );
	}
}
