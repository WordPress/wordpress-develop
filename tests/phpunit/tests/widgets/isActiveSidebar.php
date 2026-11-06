<?php

/**
 * Tests for the is_active_sidebar function.
 *
 * @group widgets
 *
 * @covers ::is_active_sidebar
 */
class Tests_Widgets_IsActiveSidebar extends WP_UnitTestCase {

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
	 * Tests that a sidebar with widgets is active.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_true_for_a_sidebar_with_widgets() {
		wp_set_sidebars_widgets( array( 'main-sidebar' => array( 'search-2' ) ) );

		$this->assertTrue( is_active_sidebar( 'main-sidebar' ) );
	}

	/**
	 * Tests that a sidebar without widgets is not active.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_false_for_a_sidebar_without_widgets() {
		wp_set_sidebars_widgets(
			array(
				'main-sidebar'  => array(),
				'other-sidebar' => array( 'search-2' ),
			)
		);

		$this->assertFalse( is_active_sidebar( 'main-sidebar' ) );
	}

	/**
	 * Tests that a sidebar that does not exist is not active.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_false_for_an_unknown_sidebar() {
		wp_set_sidebars_widgets( array( 'main-sidebar' => array( 'search-2' ) ) );

		$this->assertFalse( is_active_sidebar( 'unknown-sidebar' ) );
	}

	/**
	 * Tests that the state of the sidebar follows the widgets that are assigned to it.
	 *
	 * @ticket 66502
	 */
	public function test_should_follow_the_widgets_assigned_to_the_sidebar() {
		wp_set_sidebars_widgets( array( 'main-sidebar' => array() ) );

		$this->assertFalse( is_active_sidebar( 'main-sidebar' ), 'The sidebar should not be active without widgets.' );

		wp_set_sidebars_widgets( array( 'main-sidebar' => array( 'search-2' ) ) );

		$this->assertTrue( is_active_sidebar( 'main-sidebar' ), 'The sidebar should be active once a widget is assigned.' );
	}

	/**
	 * Tests that an integer is the number of a sidebar.
	 *
	 * @ticket 66502
	 */
	public function test_should_use_an_integer_as_the_number_of_the_sidebar() {
		wp_set_sidebars_widgets(
			array(
				'sidebar-2' => array( 'search-2' ),
				'2'         => array(),
			)
		);

		$this->assertTrue( is_active_sidebar( 2 ), 'The integer should be the number of the sidebar.' );
		$this->assertFalse( is_active_sidebar( 3 ), 'Another number should not be active.' );
		$this->assertFalse( is_active_sidebar( '2' ), 'A string is the ID of the sidebar, and not its number.' );
	}

	/**
	 * Tests that the ID of the sidebar is sanitized like a title.
	 *
	 * @ticket 66502
	 */
	public function test_should_sanitize_the_id_of_the_sidebar() {
		wp_set_sidebars_widgets( array( 'main-sidebar' => array( 'search-2' ) ) );

		$this->assertTrue( is_active_sidebar( 'Main Sidebar' ), 'The ID should be sanitized.' );
		$this->assertTrue( is_active_sidebar( 'MAIN-SIDEBAR' ), 'The case should be ignored.' );
	}

	/**
	 * Tests that the `is_active_sidebar` filter receives the result and the index of the sidebar.
	 *
	 * @ticket 66502
	 */
	public function test_should_apply_the_filter_with_the_result_and_the_index() {
		wp_set_sidebars_widgets(
			array(
				'main-sidebar'  => array( 'search-2' ),
				'empty-sidebar' => array(),
			)
		);

		$received = array();
		add_filter(
			'is_active_sidebar',
			static function ( $is_active, $index ) use ( &$received ) {
				$received[] = array( $is_active, $index );

				return $is_active;
			},
			10,
			2
		);

		is_active_sidebar( 'main-sidebar' );
		is_active_sidebar( 'Empty Sidebar' );
		is_active_sidebar( 4 );

		$this->assertSame(
			array(
				array( true, 'main-sidebar' ),
				array( false, 'empty-sidebar' ),
				array( false, 'sidebar-4' ),
			),
			$received
		);
	}

	/**
	 * Tests that the result of the `is_active_sidebar` filter is returned.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_result_of_the_filter() {
		wp_set_sidebars_widgets( array( 'main-sidebar' => array( 'search-2' ) ) );

		add_filter( 'is_active_sidebar', '__return_false' );

		$this->assertFalse( is_active_sidebar( 'main-sidebar' ), 'The filter should be able to make the sidebar inactive.' );

		remove_filter( 'is_active_sidebar', '__return_false' );
		add_filter( 'is_active_sidebar', '__return_true' );

		$this->assertTrue( is_active_sidebar( 'unknown-sidebar' ), 'The filter should be able to make the sidebar active.' );
	}
}
