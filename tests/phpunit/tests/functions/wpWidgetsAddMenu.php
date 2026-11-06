<?php
/**
 * Tests for the wp_widgets_add_menu() function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.5.0
 */

/**
 * Tests for the wp_widgets_add_menu() function.
 *
 * @group functions
 * @group widgets
 *
 * @covers ::wp_widgets_add_menu
 */
class Tests_Functions_WpWidgetsAddMenu extends WP_UnitTestCase {

	/**
	 * Original submenu state.
	 *
	 * @var array|null
	 */
	protected $original_submenu;

	/**
	 * Sets up the test fixture.
	 */
	public function set_up() {
		parent::set_up();
		global $submenu;
		$this->original_submenu = $submenu;
		$submenu                = array();
	}

	/**
	 * Restores the test fixture.
	 */
	public function tear_down() {
		global $submenu;
		$submenu = $this->original_submenu;
		parent::tear_down();
	}

	/**
	 * Tests that wp_widgets_add_menu() adds a submenu item for classic themes.
	 *
	 * @ticket 57224
	 * @ticket 60179
	 */
	public function test_wp_widgets_add_menu() {
		global $submenu;

		$submenu['themes.php'] = array(
			11 => array( __( 'Menus' ), 'edit_theme_options', 'nav-menus.php' ),
			5  => array( __( 'Themes' ), 'switch_themes', 'themes.php' ),
		);

		wp_widgets_add_menu();

		$expected = array(
			'themes.php' => array(
				5  => array( __( 'Themes' ), 'switch_themes', 'themes.php' ),
				9  => array( __( 'Widgets' ), 'edit_theme_options', 'widgets.php' ),
				11 => array( __( 'Menus' ), 'edit_theme_options', 'nav-menus.php' ),
			),
		);
		$this->assertSame( $expected, $submenu );
	}

	/**
	 * Tests that wp_widgets_add_menu() does not add a submenu item when the theme does not support widgets.
	 *
	 * @ticket 57224
	 * @ticket 60179
	 */
	public function test_wp_widgets_add_menu_no_widget_support() {
		global $submenu;

		add_filter( 'current_theme_supports-widgets', '__return_false' );

		wp_widgets_add_menu();

		remove_filter( 'current_theme_supports-widgets', '__return_false' );

		$this->assertSame( array(), $submenu );
	}

	/**
	 * Tests that wp_widgets_add_menu() appends a submenu item for block themes.
	 *
	 * @ticket 57224
	 * @ticket 60179
	 */
	public function test_wp_widgets_add_menu_block_theme() {
		global $submenu;

		switch_theme( 'block-theme' );

		$submenu['themes.php'] = array(
			5 => array( __( 'Themes' ), 'switch_themes', 'themes.php' ),
		);

		wp_widgets_add_menu();

		$expected = array(
			'themes.php' => array(
				5 => array( __( 'Themes' ), 'switch_themes', 'themes.php' ),
				6 => array( __( 'Widgets' ), 'edit_theme_options', 'widgets.php' ),
			),
		);
		$this->assertSame( $expected, $submenu );
	}
}
