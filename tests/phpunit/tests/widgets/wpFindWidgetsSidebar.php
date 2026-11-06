<?php

/**
 * Tests for the wp_find_widgets_sidebar function.
 *
 * @group widgets
 *
 * @covers ::wp_find_widgets_sidebar
 */
class Tests_Widgets_WpFindWidgetsSidebar extends WP_UnitTestCase {

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
	 * Tests that the ID of the sidebar of a widget is returned.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_sidebar_of_the_widget() {
		wp_set_sidebars_widgets(
			array(
				'first-sidebar'  => array( 'search-2', 'text-3' ),
				'second-sidebar' => array( 'meta-4' ),
			)
		);

		$this->assertSame( 'first-sidebar', wp_find_widgets_sidebar( 'text-3' ), 'The widget of the first sidebar should be found.' );
		$this->assertSame( 'second-sidebar', wp_find_widgets_sidebar( 'meta-4' ), 'The widget of the second sidebar should be found.' );
	}

	/**
	 * Tests that the widgets of the inactive sidebar are found too.
	 *
	 * @ticket 66502
	 */
	public function test_should_find_the_widgets_of_the_inactive_widgets_sidebar() {
		wp_set_sidebars_widgets( array( 'wp_inactive_widgets' => array( 'search-2' ) ) );

		$this->assertSame( 'wp_inactive_widgets', wp_find_widgets_sidebar( 'search-2' ) );
	}

	/**
	 * Tests that null is returned when the widget is not assigned to a sidebar.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_null_when_the_widget_is_not_assigned() {
		wp_set_sidebars_widgets( array( 'first-sidebar' => array( 'search-2' ) ) );

		$this->assertNull( wp_find_widgets_sidebar( 'text-3' ) );
	}

	/**
	 * Tests that null is returned when there are no sidebars.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_null_when_there_are_no_sidebars() {
		wp_set_sidebars_widgets( array() );

		$this->assertNull( wp_find_widgets_sidebar( 'search-2' ) );
	}

	/**
	 * Tests that the ID of the widget is matched exactly.
	 *
	 * @ticket 66502
	 */
	public function test_should_match_the_id_of_the_widget_exactly() {
		wp_set_sidebars_widgets( array( 'first-sidebar' => array( 'search-2', '10' ) ) );

		$this->assertNull( wp_find_widgets_sidebar( 'search-20' ), 'A longer ID should not match.' );
		$this->assertNull( wp_find_widgets_sidebar( 'Search-2' ), 'The ID should be case sensitive.' );
		$this->assertNull( wp_find_widgets_sidebar( 10 ), 'An integer should not match an ID that is a string.' );
		$this->assertSame( 'first-sidebar', wp_find_widgets_sidebar( '10' ), 'The same string should match.' );
	}

	/**
	 * Tests that the first sidebar is returned when a widget is assigned to more than one.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_first_sidebar_when_the_widget_is_assigned_twice() {
		wp_set_sidebars_widgets(
			array(
				'first-sidebar'  => array( 'search-2' ),
				'second-sidebar' => array( 'search-2' ),
			)
		);

		$this->assertSame( 'first-sidebar', wp_find_widgets_sidebar( 'search-2' ) );
	}

	/**
	 * Tests that the ID of a sidebar that is a number is returned as a string.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_id_of_the_sidebar_as_a_string() {
		wp_set_sidebars_widgets( array( 7 => array( 'search-2' ) ) );

		$this->assertSame( '7', wp_find_widgets_sidebar( 'search-2' ) );
	}

	/**
	 * Tests that the sidebars are read through the `sidebars_widgets` filter.
	 *
	 * @ticket 66502
	 */
	public function test_should_use_the_widgets_that_the_filter_returns() {
		wp_set_sidebars_widgets( array( 'first-sidebar' => array( 'search-2' ) ) );

		add_filter(
			'sidebars_widgets',
			static function () {
				return array( 'filtered-sidebar' => array( 'text-3' ) );
			}
		);

		$this->assertSame( 'filtered-sidebar', wp_find_widgets_sidebar( 'text-3' ), 'The widget of the filter should be found.' );
		$this->assertNull( wp_find_widgets_sidebar( 'search-2' ), 'The widget that the filter removed should not be found.' );
	}
}
