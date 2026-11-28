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
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_widgets_add_menu() {
		global $menu, $submenu;

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		remove_action( '_admin_menu', 'wp_widgets_add_menu' );

		require_once ABSPATH . 'wp-admin/menu.php';

		$submenu_before = $submenu['themes.php'];

		wp_widgets_add_menu();

		foreach ( $submenu_before as $item ) {
			$this->assertContains( $item, $submenu['themes.php'] );
		}

		$this->assertContains(
			array( __( 'Widgets' ), 'edit_theme_options', 'widgets.php' ),
			$submenu['themes.php']
		);
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
	 * @ticket 54916
	 * @ticket 57224
	 * @ticket 60179
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_widgets_add_menu_block_theme() {
		global $menu, $submenu;

		switch_theme( 'block-theme' );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		remove_action( '_admin_menu', 'wp_widgets_add_menu' );

		require_once ABSPATH . 'wp-admin/menu.php';

		$submenu_before = $submenu['themes.php'];

		wp_widgets_add_menu();

		foreach ( $submenu_before as $item ) {
			$this->assertContains( $item, $submenu['themes.php'] );
		}

		$this->assertContains(
			array( __( 'Widgets' ), 'edit_theme_options', 'widgets.php' ),
			$submenu['themes.php']
		);
	}
}
