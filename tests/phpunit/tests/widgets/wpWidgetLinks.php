<?php
/**
 * Unit tests covering WP_Widget_Links functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-links.php
 *
 * @group widgets
 */
class Tests_Widgets_wpWidgetLinks extends WP_UnitTestCase {

	/**
	 * Tests that the checkbox settings reflect the submitted admin form.
	 *
	 * Browsers omit unchecked checkboxes from the submitted form, and send "on" for checked ones.
	 *
	 * @ticket 66165
	 * @covers WP_Widget_Links::update
	 * @dataProvider data_update_checkboxes
	 *
	 * @param array $new_instance The submitted form values.
	 * @param array $expected     The expected checkbox settings.
	 */
	public function test_update_checkboxes( $new_instance, $expected ) {
		$widget   = new WP_Widget_Links();
		$instance = $widget->update(
			$new_instance + array(
				'orderby'  => 'name',
				'category' => 0,
				'limit'    => -1,
			),
			array()
		);

		$this->assertSame( $expected, array_intersect_key( $instance, $expected ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_update_checkboxes() {
		return array(
			'none checked' => array(
				'new_instance' => array(),
				'expected'     => array(
					'images'      => 0,
					'name'        => 0,
					'description' => 0,
					'rating'      => 0,
				),
			),
			'all checked'  => array(
				'new_instance' => array(
					'images'      => 'on',
					'name'        => 'on',
					'description' => 'on',
					'rating'      => 'on',
				),
				'expected'     => array(
					'images'      => 1,
					'name'        => 1,
					'description' => 1,
					'rating'      => 1,
				),
			),
			'name only'    => array(
				'new_instance' => array( 'name' => 'on' ),
				'expected'     => array(
					'images'      => 0,
					'name'        => 1,
					'description' => 0,
					'rating'      => 0,
				),
			),
		);
	}

	/**
	 * Tests that passing a saved instance back through update() does not change it.
	 *
	 * The block widget editor saves the instance returned by update() by passing it to
	 * update() again, so unchecked settings (stored as 0) must stay unchecked.
	 *
	 * @ticket 66165
	 * @covers WP_Widget_Links::update
	 */
	public function test_update_is_idempotent() {
		$widget   = new WP_Widget_Links();
		$instance = $widget->update(
			array(
				'name'     => 'on',
				'orderby'  => 'name',
				'category' => 0,
				'limit'    => -1,
			),
			array()
		);

		$this->assertSame( $instance, $widget->update( $instance, $instance ) );
	}
}
