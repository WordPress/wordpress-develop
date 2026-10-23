<?php

/**
 * Tests for wp_parse_widget_id().
 *
 * @group widgets
 *
 * @covers ::wp_parse_widget_id
 */
class Tests_Widgets_WpParseWidgetId extends WP_UnitTestCase {

	/**
	 * Tests that a widget ID ending in a number is split into its id_base and number.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_widget_ids_with_number
	 *
	 * @param string $widget_id Widget ID.
	 * @param array  $expected  Expected id_base and number.
	 */
	public function test_returns_id_base_and_number( $widget_id, $expected ) {
		$this->assertSame( $expected, wp_parse_widget_id( $widget_id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_widget_ids_with_number() {
		return array(
			'single word id_base'      => array(
				'text-2',
				array(
					'id_base' => 'text',
					'number'  => 2,
				),
			),
			'hyphenated id_base'       => array(
				'recent-posts-12',
				array(
					'id_base' => 'recent-posts',
					'number'  => 12,
				),
			),
			'id_base with underscore'  => array(
				'nav_menu-5',
				array(
					'id_base' => 'nav_menu',
					'number'  => 5,
				),
			),
			'id_base ending in number' => array(
				'text-2-3',
				array(
					'id_base' => 'text-2',
					'number'  => 3,
				),
			),
			'number zero'              => array(
				'block-0',
				array(
					'id_base' => 'block',
					'number'  => 0,
				),
			),
			'number with leading zero' => array(
				'text-02',
				array(
					'id_base' => 'text',
					'number'  => 2,
				),
			),
		);
	}

	/**
	 * Tests that a widget ID without a trailing number is returned as the id_base.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_widget_ids_without_number
	 *
	 * @param string $widget_id Widget ID.
	 */
	public function test_returns_id_base_only_without_number( $widget_id ) {
		$this->assertSame( array( 'id_base' => $widget_id ), wp_parse_widget_id( $widget_id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_widget_ids_without_number() {
		return array(
			'single widget'           => array( 'archives' ),
			'hyphen without number'   => array( 'text-' ),
			'number followed by text' => array( 'text-2a' ),
			'number without id_base'  => array( '-2' ),
			'number only'             => array( '2' ),
		);
	}
}
