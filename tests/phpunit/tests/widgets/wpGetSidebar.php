<?php

/**
 * Tests for the wp_get_sidebar function.
 *
 * @group widgets
 *
 * @covers ::wp_get_sidebar
 */
class Tests_Widgets_WpGetSidebar extends WP_UnitTestCase {

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
	 * Tests that the data of a registered sidebar is returned.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_registered_sidebar() {
		global $wp_registered_sidebars;

		register_sidebar(
			array(
				'id'   => 'main-sidebar',
				'name' => 'Main sidebar',
			)
		);

		$sidebar = wp_get_sidebar( 'main-sidebar' );

		$this->assertSame( $wp_registered_sidebars['main-sidebar'], $sidebar, 'The registered data should be returned.' );
		$this->assertSame( 'main-sidebar', $sidebar['id'], 'The ID should be the requested one.' );
		$this->assertSame( 'Main sidebar', $sidebar['name'], 'The name should be the registered one.' );
	}

	/**
	 * Tests that the requested sidebar is returned when there are several.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_requested_sidebar_among_several() {
		register_sidebar(
			array(
				'id'   => 'first-sidebar',
				'name' => 'First',
			)
		);
		register_sidebar(
			array(
				'id'   => 'second-sidebar',
				'name' => 'Second',
			)
		);

		$this->assertSame( 'First', wp_get_sidebar( 'first-sidebar' )['name'], 'The first sidebar should be returned.' );
		$this->assertSame( 'Second', wp_get_sidebar( 'second-sidebar' )['name'], 'The second sidebar should be returned.' );
	}

	/**
	 * Tests that the inactive widgets sidebar is returned even when it is not registered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_inactive_widgets_sidebar_when_it_is_not_registered() {
		$sidebar = wp_get_sidebar( 'wp_inactive_widgets' );

		$this->assertSame(
			array(
				'id'   => 'wp_inactive_widgets',
				'name' => 'Inactive widgets',
			),
			$sidebar
		);
	}

	/**
	 * Tests that the inactive widgets sidebar that is registered is returned as it is registered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_inactive_widgets_sidebar_that_is_registered() {
		register_sidebar(
			array(
				'id'   => 'wp_inactive_widgets',
				'name' => 'Custom inactive name',
			)
		);

		$this->assertSame( 'Custom inactive name', wp_get_sidebar( 'wp_inactive_widgets' )['name'] );
	}

	/**
	 * Tests that null is returned when the sidebar is not registered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_null_for_a_sidebar_that_is_not_registered() {
		register_sidebar( array( 'id' => 'main-sidebar' ) );

		$this->assertNull( wp_get_sidebar( 'unknown-sidebar' ) );
	}

	/**
	 * Tests that null is returned when no sidebar is registered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_null_when_no_sidebar_is_registered() {
		$this->assertNull( wp_get_sidebar( 'main-sidebar' ) );
	}

	/**
	 * Tests that the ID is compared strictly, so a sidebar is not found by an ID of another type.
	 *
	 * @ticket 66502
	 */
	public function test_should_compare_the_id_strictly() {
		register_sidebar( array( 'id' => 'main-sidebar' ) );
		register_sidebar( array( 'id' => '5' ) );

		$this->assertNull( wp_get_sidebar( 'MAIN-SIDEBAR' ), 'The ID should be case sensitive.' );
		$this->assertNull( wp_get_sidebar( 5 ), 'An integer should not match the sidebar with the same ID as a string.' );
		$this->assertSame( '5', wp_get_sidebar( '5' )['id'], 'The ID as a string should match.' );
	}
}
