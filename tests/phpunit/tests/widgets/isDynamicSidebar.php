<?php

/**
 * Tests for the is_dynamic_sidebar function.
 *
 * @group widgets
 *
 * @covers ::is_dynamic_sidebar
 */
class Tests_Widgets_IsDynamicSidebar extends WP_UnitTestCase {

	/**
	 * Registered sidebars, to restore after each test.
	 *
	 * @var array
	 */
	private $original_sidebars;

	/**
	 * Registered widgets, to restore after each test.
	 *
	 * @var array
	 */
	private $original_widgets;

	public function set_up() {
		parent::set_up();

		global $wp_registered_sidebars, $wp_registered_widgets, $_wp_sidebars_widgets;

		$this->original_sidebars = $wp_registered_sidebars;
		$this->original_widgets  = $wp_registered_widgets;

		$wp_registered_sidebars = array();
		$wp_registered_widgets  = array();
		$_wp_sidebars_widgets   = null;
	}

	public function tear_down() {
		global $wp_registered_sidebars, $wp_registered_widgets, $_wp_sidebars_widgets;

		$wp_registered_sidebars = $this->original_sidebars;
		$wp_registered_widgets  = $this->original_widgets;
		$_wp_sidebars_widgets   = null;

		parent::tear_down();
	}

	/**
	 * Registers a sidebar and a widget, and assigns the widget to the sidebar.
	 *
	 * @param string   $sidebar_id Sidebar ID.
	 * @param string[] $widget_ids IDs of the widgets, which are registered and assigned.
	 */
	private function register_sidebar_with_widgets( $sidebar_id, $widget_ids ) {
		register_sidebar( array( 'id' => $sidebar_id ) );

		foreach ( $widget_ids as $widget_id ) {
			wp_register_sidebar_widget( $widget_id, 'Widget ' . $widget_id, '__return_empty_string' );
		}

		$sidebars_widgets                = get_option( 'sidebars_widgets', array() );
		$sidebars_widgets[ $sidebar_id ] = $widget_ids;

		wp_set_sidebars_widgets( $sidebars_widgets );
	}

	/**
	 * Tests that a registered sidebar with a registered widget is dynamic.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_true_when_a_sidebar_has_a_registered_widget() {
		$this->register_sidebar_with_widgets( 'main-sidebar', array( 'dynamic-widget' ) );

		$this->assertTrue( is_dynamic_sidebar() );
	}

	/**
	 * Tests that it is false when no sidebar is registered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_false_when_no_sidebar_is_registered() {
		wp_register_sidebar_widget( 'dynamic-widget', 'Widget', '__return_empty_string' );
		wp_set_sidebars_widgets( array( 'main-sidebar' => array( 'dynamic-widget' ) ) );

		$this->assertFalse( is_dynamic_sidebar() );
	}

	/**
	 * Tests that it is false when the registered sidebars have no widgets.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_false_when_the_registered_sidebars_have_no_widgets() {
		register_sidebar( array( 'id' => 'main-sidebar' ) );
		wp_set_sidebars_widgets( array( 'main-sidebar' => array() ) );

		$this->assertFalse( is_dynamic_sidebar() );
	}

	/**
	 * Tests that the widgets that are not registered are ignored.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_false_when_the_widgets_are_not_registered() {
		register_sidebar( array( 'id' => 'main-sidebar' ) );
		wp_set_sidebars_widgets( array( 'main-sidebar' => array( 'unregistered-widget' ) ) );

		$this->assertFalse( is_dynamic_sidebar() );
	}

	/**
	 * Tests that the widgets of a sidebar that is not registered are ignored.
	 *
	 * @ticket 66502
	 */
	public function test_should_ignore_the_widgets_of_a_sidebar_that_is_not_registered() {
		register_sidebar( array( 'id' => 'main-sidebar' ) );
		wp_register_sidebar_widget( 'dynamic-widget', 'Widget', '__return_empty_string' );
		wp_set_sidebars_widgets(
			array(
				'main-sidebar'        => array(),
				'unregistered-bar'    => array( 'dynamic-widget' ),
				'wp_inactive_widgets' => array( 'dynamic-widget' ),
			)
		);

		$this->assertFalse( is_dynamic_sidebar() );
	}

	/**
	 * Tests that one registered widget is enough, even after widgets that are not registered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_true_when_one_widget_is_registered_among_others() {
		register_sidebar( array( 'id' => 'first-sidebar' ) );
		register_sidebar( array( 'id' => 'second-sidebar' ) );
		wp_register_sidebar_widget( 'dynamic-widget', 'Widget', '__return_empty_string' );
		wp_set_sidebars_widgets(
			array(
				'first-sidebar'  => array( 'unregistered-widget' ),
				'second-sidebar' => array( 'unregistered-widget-2', 'dynamic-widget' ),
			)
		);

		$this->assertTrue( is_dynamic_sidebar() );
	}
}
