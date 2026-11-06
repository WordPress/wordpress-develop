<?php

/**
 * Tests for the wp_widget_description function.
 *
 * @group widgets
 *
 * @covers ::wp_widget_description
 */
class Tests_Widgets_WpWidgetDescription extends WP_UnitTestCase {

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
	 * Registers a widget with a description.
	 *
	 * @param string $id          ID of the widget.
	 * @param mixed  $description Description of the widget.
	 */
	private function register_widget_with_description( $id, $description ) {
		wp_register_sidebar_widget(
			$id,
			'Widget ' . $id,
			'__return_empty_string',
			array( 'description' => $description )
		);
	}

	/**
	 * Tests that the description of the widget is returned.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_description_of_the_widget() {
		$this->register_widget_with_description( 'described-widget', 'Shows the latest posts.' );

		$this->assertSame( 'Shows the latest posts.', wp_widget_description( 'described-widget' ) );
	}

	/**
	 * Tests that the description of the widget is escaped for HTML.
	 *
	 * @ticket 66502
	 */
	public function test_should_escape_the_description_of_the_widget() {
		$this->register_widget_with_description( 'markup-widget', '<b>Bold</b> & "quoted"' );

		$this->assertSame( '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;quoted&quot;', wp_widget_description( 'markup-widget' ) );
	}

	/**
	 * Tests that only the description of the requested widget is returned.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_the_description_of_the_requested_widget() {
		$this->register_widget_with_description( 'first-widget', 'First description' );
		$this->register_widget_with_description( 'second-widget', 'Second description' );

		$this->assertSame( 'First description', wp_widget_description( 'first-widget' ), 'The description of the first widget should be returned.' );
		$this->assertSame( 'Second description', wp_widget_description( 'second-widget' ), 'The description of the second widget should be returned.' );
	}

	/**
	 * Tests that an empty description is returned as an empty string.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_an_empty_string_for_an_empty_description() {
		$this->register_widget_with_description( 'empty-widget', '' );

		$this->assertSame( '', wp_widget_description( 'empty-widget' ) );
	}

	/**
	 * Tests that nothing is returned when the widget is not registered, or has no description.
	 *
	 * @ticket 66502
	 */
	public function test_should_return_null_when_there_is_no_description() {
		wp_register_sidebar_widget( 'plain-widget', 'Plain widget', '__return_empty_string' );

		$this->assertNull( wp_widget_description( 'plain-widget' ), 'A widget without a description should return null.' );
		$this->assertNull( wp_widget_description( 'unknown-widget' ), 'A widget that is not registered should return null.' );
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
		$this->register_widget_with_description( 'described-widget', 'Shows the latest posts.' );

		$this->assertNull( wp_widget_description( $id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_ids_that_are_not_scalar() {
		return array(
			'array'  => array( array( 'described-widget' ) ),
			'null'   => array( null ),
			'object' => array( new stdClass() ),
		);
	}
}
