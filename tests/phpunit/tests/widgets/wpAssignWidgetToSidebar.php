<?php

/**
 * Tests for the wp_assign_widget_to_sidebar function.
 *
 * @group widgets
 *
 * @covers ::wp_assign_widget_to_sidebar
 */
class Tests_Widgets_WpAssignWidgetToSidebar extends WP_UnitTestCase {

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
	 * Gets the widgets of the sidebars, as they are saved.
	 *
	 * @return array Widgets of the sidebars, without the version of the array.
	 */
	private function get_saved_sidebars() {
		$sidebars = get_option( 'sidebars_widgets' );
		unset( $sidebars['array_version'] );

		return $sidebars;
	}

	/**
	 * Tests that a widget that is not assigned is added to the sidebar.
	 *
	 * @ticket 66502
	 */
	public function test_should_add_a_widget_to_the_sidebar() {
		wp_set_sidebars_widgets( array( 'main-sidebar' => array( 'search-2' ) ) );

		wp_assign_widget_to_sidebar( 'text-3', 'main-sidebar' );

		$this->assertSame( array( 'main-sidebar' => array( 'search-2', 'text-3' ) ), $this->get_saved_sidebars() );
	}

	/**
	 * Tests that a widget is added to a sidebar that does not have widgets yet.
	 *
	 * @ticket 66502
	 */
	public function test_should_add_a_widget_to_a_new_sidebar() {
		wp_set_sidebars_widgets( array() );

		wp_assign_widget_to_sidebar( 'search-2', 'new-sidebar' );

		$this->assertSame( array( 'new-sidebar' => array( 'search-2' ) ), $this->get_saved_sidebars() );
	}

	/**
	 * Tests that a widget is moved from the sidebar it was assigned to.
	 *
	 * @ticket 66502
	 */
	public function test_should_move_the_widget_from_the_sidebar_it_was_assigned_to() {
		wp_set_sidebars_widgets(
			array(
				'first-sidebar'  => array( 'search-2', 'text-3' ),
				'second-sidebar' => array( 'meta-4' ),
			)
		);

		wp_assign_widget_to_sidebar( 'search-2', 'second-sidebar' );

		$this->assertSame(
			array(
				'first-sidebar'  => array( 1 => 'text-3' ),
				'second-sidebar' => array( 'meta-4', 'search-2' ),
			),
			$this->get_saved_sidebars()
		);
		$this->assertSame( 'second-sidebar', wp_find_widgets_sidebar( 'search-2' ), 'The widget should be found in the new sidebar.' );
	}

	/**
	 * Tests that a widget is removed from its sidebar when the new sidebar is empty.
	 *
	 * @ticket 66502
	 *
	 * @dataProvider data_empty_sidebar_ids
	 *
	 * @param mixed $sidebar_id Empty ID of the sidebar.
	 */
	public function test_should_remove_the_widget_when_the_sidebar_is_empty( $sidebar_id ) {
		wp_set_sidebars_widgets(
			array(
				'first-sidebar'  => array( 'search-2', 'text-3' ),
				'second-sidebar' => array( 'meta-4' ),
			)
		);

		wp_assign_widget_to_sidebar( 'search-2', $sidebar_id );

		$this->assertSame(
			array(
				'first-sidebar'  => array( 1 => 'text-3' ),
				'second-sidebar' => array( 'meta-4' ),
			),
			$this->get_saved_sidebars()
		);
		$this->assertNull( wp_find_widgets_sidebar( 'search-2' ), 'The widget should not be assigned to a sidebar.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_sidebar_ids() {
		return array(
			'empty string' => array( '' ),
			'null'         => array( null ),
			'false'        => array( false ),
		);
	}

	/**
	 * Tests that the other widgets and sidebars are not changed.
	 *
	 * @ticket 66502
	 */
	public function test_should_not_change_the_other_widgets_and_sidebars() {
		wp_set_sidebars_widgets(
			array(
				'first-sidebar'       => array( 'search-2' ),
				'second-sidebar'      => array( 'meta-4', 'text-5' ),
				'wp_inactive_widgets' => array( 'archives-6' ),
			)
		);

		wp_assign_widget_to_sidebar( 'search-2', 'wp_inactive_widgets' );

		$this->assertSame(
			array(
				'first-sidebar'       => array(),
				'second-sidebar'      => array( 'meta-4', 'text-5' ),
				'wp_inactive_widgets' => array( 'archives-6', 'search-2' ),
			),
			$this->get_saved_sidebars()
		);
	}

	/**
	 * Tests that the widget is removed from every other sidebar it is assigned to.
	 *
	 * @ticket 66502
	 */
	public function test_should_remove_the_widget_from_all_the_other_sidebars() {
		wp_set_sidebars_widgets(
			array(
				'first-sidebar'  => array( 'search-2' ),
				'second-sidebar' => array( 'search-2', 'meta-4' ),
				'third-sidebar'  => array(),
			)
		);

		wp_assign_widget_to_sidebar( 'search-2', 'third-sidebar' );

		$this->assertSame(
			array(
				'first-sidebar'  => array(),
				'second-sidebar' => array( 1 => 'meta-4' ),
				'third-sidebar'  => array( 'search-2' ),
			),
			$this->get_saved_sidebars()
		);
	}

	/**
	 * Tests that the change is used by the functions that read the widgets of the sidebars.
	 *
	 * @ticket 66502
	 */
	public function test_should_update_the_sidebars_that_are_read_afterwards() {
		wp_set_sidebars_widgets( array( 'main-sidebar' => array() ) );

		$this->assertFalse( is_active_sidebar( 'main-sidebar' ), 'The sidebar should not be active before the widget is assigned.' );

		wp_assign_widget_to_sidebar( 'search-2', 'main-sidebar' );

		$this->assertTrue( is_active_sidebar( 'main-sidebar' ), 'The sidebar should be active after the widget is assigned.' );
		$this->assertSame( array( 'main-sidebar' => array( 'search-2' ) ), wp_get_sidebars_widgets(), 'The widgets of the sidebars should include the widget.' );
	}

	/**
	 * Tests that the version of the array of sidebars is saved with the change.
	 *
	 * @ticket 66502
	 */
	public function test_should_save_the_version_of_the_array() {
		wp_set_sidebars_widgets( array() );

		wp_assign_widget_to_sidebar( 'search-2', 'main-sidebar' );

		$this->assertSame( 3, get_option( 'sidebars_widgets' )['array_version'] );
	}
}
