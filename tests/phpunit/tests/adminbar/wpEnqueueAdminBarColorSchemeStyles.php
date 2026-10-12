<?php

/**
 * Tests for wp_enqueue_admin_bar_color_scheme_styles().
 *
 * @group admin-bar
 * @group toolbar
 *
 * @covers ::wp_enqueue_admin_bar_color_scheme_styles
 */
class Tests_AdminBar_wpEnqueueAdminBarColorSchemeStyles extends WP_UnitTestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	private static int $user_id;

	/**
	 * Original value of the `$wp_styles` global.
	 *
	 * @var WP_Styles|null
	 */
	private ?WP_Styles $original_wp_styles;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create();
	}

	public function set_up() {
		parent::set_up();

		$this->original_wp_styles = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles']     = null;

		wp_set_current_user( self::$user_id );
	}

	public function tear_down() {
		$GLOBALS['wp_styles'] = $this->original_wp_styles;

		parent::tear_down();
	}

	/**
	 * @ticket 64762
	 */
	public function test_enqueues_stylesheet_for_core_color_scheme() {
		update_user_option( self::$user_id, 'admin_color', 'blue', true );

		wp_enqueue_admin_bar_color_scheme_styles();

		$this->assertTrue( wp_style_is( 'admin-bar-color-scheme' ), 'The admin bar color scheme stylesheet should be enqueued.' );

		$style  = wp_styles()->registered['admin-bar-color-scheme'];
		$suffix = SCRIPT_DEBUG ? '' : '.min';
		$this->assertSame( admin_url( "css/colors/blue/admin-bar{$suffix}.css" ), $style->src, 'The stylesheet URL should match the color scheme.' );
		$this->assertContains( 'admin-bar', $style->deps, 'The stylesheet should depend on the admin bar stylesheet.' );
	}

	/**
	 * @ticket 64762
	 *
	 * @dataProvider data_color_schemes_without_admin_bar_stylesheet
	 *
	 * @param string $color_scheme Color scheme.
	 */
	public function test_does_not_enqueue_stylesheet_for_color_scheme_without_admin_bar_stylesheet( $color_scheme ) {
		update_user_option( self::$user_id, 'admin_color', $color_scheme, true );

		wp_enqueue_admin_bar_color_scheme_styles();

		$this->assertFalse( wp_style_is( 'admin-bar-color-scheme', 'registered' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: non-falsy-string }>
	 */
	public static function data_color_schemes_without_admin_bar_stylesheet(): array {
		return array(
			'fresh'         => array( 'fresh' ),
			'plugin scheme' => array( 'my-plugin-scheme' ),
		);
	}

	/**
	 * @ticket 64762
	 */
	public function test_does_not_enqueue_stylesheet_in_admin() {
		update_user_option( self::$user_id, 'admin_color', 'blue', true );
		set_current_screen( 'dashboard' );

		wp_enqueue_admin_bar_color_scheme_styles();

		$this->assertFalse( wp_style_is( 'admin-bar-color-scheme', 'registered' ) );
	}
}
