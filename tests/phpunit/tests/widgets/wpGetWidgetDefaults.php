<?php

/**
 * Tests for the wp_get_widget_defaults function.
 *
 * @group widgets
 *
 * @covers ::wp_get_widget_defaults
 */
class Tests_Widgets_WpGetWidgetDefaults extends WP_UnitTestCase {

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

		global $wp_registered_sidebars, $wp_registered_widgets;

		$this->original_sidebars = $wp_registered_sidebars;
		$this->original_widgets  = $wp_registered_widgets;

		$wp_registered_sidebars = array();
		$wp_registered_widgets  = array();
	}

	public function tear_down() {
		global $wp_registered_sidebars, $wp_registered_widgets;

		$wp_registered_sidebars = $this->original_sidebars;
		$wp_registered_widgets  = $this->original_widgets;

		parent::tear_down();
	}

	/**
	 * Tests that an empty list of widgets is returned for each registered sidebar.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_an_empty_list_for_each_registered_sidebar() {
		register_sidebar( array( 'id' => 'first-sidebar' ) );
		register_sidebar( array( 'id' => 'second-sidebar' ) );

		$this->assertSame(
			array(
				'first-sidebar'  => array(),
				'second-sidebar' => array(),
			),
			wp_get_widget_defaults()
		);
	}

	/**
	 * Tests that the sidebars are returned in the order they are registered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_sidebars_in_the_order_they_are_registered() {
		register_sidebar( array( 'id' => 'sidebar-b' ) );
		register_sidebar( array( 'id' => 'sidebar-a' ) );
		register_sidebar( array( 'id' => 'sidebar-c' ) );

		$this->assertSame( array( 'sidebar-b', 'sidebar-a', 'sidebar-c' ), array_keys( wp_get_widget_defaults() ) );
	}

	/**
	 * Tests that no defaults are returned when no sidebar is registered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_an_empty_array_when_no_sidebar_is_registered() {
		$this->assertSame( array(), wp_get_widget_defaults() );
	}

	/**
	 * Tests that the widgets assigned to the sidebars do not change the defaults.
	 *
	 * @ticket 66502
	 */
	public function test_should_not_include_the_widgets_that_are_assigned_to_the_sidebars() {
		register_sidebar( array( 'id' => 'main-sidebar' ) );

		wp_set_sidebars_widgets( array( 'main-sidebar' => array( 'search-2', 'text-3' ) ) );

		$this->assertSame( array( 'main-sidebar' => array() ), wp_get_widget_defaults() );
	}
}
