<?php

/**
 * Tests for the wp_sidebar_description function.
 *
 * @group widgets
 *
 * @covers ::wp_sidebar_description
 */
class Tests_Widgets_WpSidebarDescription extends WP_UnitTestCase {

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
	 * Registers a sidebar with a description.
	 *
	 * @param string $id          ID of the sidebar.
	 * @param string $description Description of the sidebar.
	 */
	private function register_sidebar_with_description( $id, $description ) {
		register_sidebar(
			array(
				'id'          => $id,
				'description' => $description,
			)
		);
	}

	/**
	 * Tests that the description of the sidebar is returned.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_description_of_the_sidebar() {
		$this->register_sidebar_with_description( 'described-sidebar', 'Shown on the right.' );

		$this->assertSame( 'Shown on the right.', wp_sidebar_description( 'described-sidebar' ) );
	}

	/**
	 * Tests that the markup and the attributes that are not allowed are removed from the description.
	 *
	 * @ticket 66502
	 */
	public function test_should_remove_the_markup_and_attributes_that_are_not_allowed() {
		$this->register_sidebar_with_description( 'markup-sidebar', '<a href="https://example.org/" onclick="evil()">Link</a> <iframe src="https://example.org/"></iframe>' );

		$this->assertSame( '<a href="https://example.org/">Link</a> ', wp_sidebar_description( 'markup-sidebar' ) );
	}

	/**
	 * Tests that the markup that the `wp_kses_allowed_html` filter allows for the context of the description is kept.
	 *
	 * @ticket 66502
	 */
	public function test_should_keep_the_markup_that_the_filter_allows_for_the_description() {
		add_filter(
			'wp_kses_allowed_html',
			static function ( $tags, $context ) {
				if ( 'sidebar_description' === $context ) {
					return array( 'b' => array() );
				}

				return $tags;
			},
			10,
			2
		);

		$this->register_sidebar_with_description( 'markup-sidebar', '<b>Bold</b> <i>italic</i>' );

		$this->assertSame( '<b>Bold</b> italic', wp_sidebar_description( 'markup-sidebar' ) );
	}

	/**
	 * Tests that only the description of the requested sidebar is returned.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_description_of_the_requested_sidebar() {
		$this->register_sidebar_with_description( 'first-sidebar', 'First description' );
		$this->register_sidebar_with_description( 'second-sidebar', 'Second description' );

		$this->assertSame( 'First description', wp_sidebar_description( 'first-sidebar' ), 'The description of the first sidebar should be returned.' );
		$this->assertSame( 'Second description', wp_sidebar_description( 'second-sidebar' ), 'The description of the second sidebar should be returned.' );
	}

	/**
	 * Tests that the description of a sidebar registered without one is an empty string.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_an_empty_string_for_a_sidebar_without_a_description() {
		register_sidebar( array( 'id' => 'plain-sidebar' ) );

		$this->assertSame( '', wp_sidebar_description( 'plain-sidebar' ) );
	}

	/**
	 * Tests that nothing is returned when the sidebar is not registered.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_null_for_a_sidebar_that_is_not_registered() {
		$this->assertNull( wp_sidebar_description( 'unknown-sidebar' ) );
	}

	/**
	 * Tests that nothing is returned when the ID is not a scalar.
	 *
	 * @ticket 66502
	 *
	 * @dataProvider data_ids_that_are_not_scalar
	 *
	 * @param mixed $id ID that is not a scalar.
	 */
	public function test_should_return_null_when_the_id_is_not_a_scalar( $id ) {
		$this->register_sidebar_with_description( 'described-sidebar', 'Shown on the right.' );

		$this->assertNull( wp_sidebar_description( $id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_ids_that_are_not_scalar() {
		return array(
			'array'  => array( array( 'described-sidebar' ) ),
			'null'   => array( null ),
			'object' => array( new stdClass() ),
		);
	}
}
