<?php

/**
 * Tests for the is_registered_sidebar function.
 *
 * @group widgets
 *
 * @covers ::is_registered_sidebar
 */
class Tests_Widgets_IsRegisteredSidebar extends WP_UnitTestCase {

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
	 * Tests that a registered sidebar is found.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_true_for_a_registered_sidebar() {
		register_sidebar( array( 'id' => 'registered-sidebar' ) );

		$this->assertTrue( is_registered_sidebar( 'registered-sidebar' ) );
	}

	/**
	 * Tests that a sidebar that is not registered is not found.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_false_for_a_sidebar_that_is_not_registered() {
		register_sidebar( array( 'id' => 'registered-sidebar' ) );

		$this->assertFalse( is_registered_sidebar( 'another-sidebar' ) );
	}

	/**
	 * Tests that no sidebar is found when none is registered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_false_when_no_sidebar_is_registered() {
		$this->assertFalse( is_registered_sidebar( 'registered-sidebar' ) );
	}

	/**
	 * Tests that a sidebar is not found any more after it is unregistered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_false_after_the_sidebar_is_unregistered() {
		register_sidebar( array( 'id' => 'temporary-sidebar' ) );

		$this->assertTrue( is_registered_sidebar( 'temporary-sidebar' ), 'The sidebar should be registered.' );

		unregister_sidebar( 'temporary-sidebar' );

		$this->assertFalse( is_registered_sidebar( 'temporary-sidebar' ), 'The sidebar should not be registered after it is unregistered.' );
	}

	/**
	 * Tests that the ID of a sidebar is matched exactly, and that a numeric ID is accepted.
	 *
	 * @ticket 66502
	 */
	public function test_should_match_the_id_exactly() {
		register_sidebar( array( 'id' => 'Sidebar-One' ) );
		register_sidebar( array( 'id' => 5 ) );

		$this->assertTrue( is_registered_sidebar( 'Sidebar-One' ), 'The exact ID should be found.' );
		$this->assertFalse( is_registered_sidebar( 'sidebar-one' ), 'The ID should be case sensitive.' );
		$this->assertTrue( is_registered_sidebar( 5 ), 'A numeric ID should be found.' );
		$this->assertTrue( is_registered_sidebar( '5' ), 'A numeric ID given as a string should be found.' );
	}
}
