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
	 * @ticket 65819
	 */
	public function test_preserves_hyphenated_id_base_and_returns_integer_number() {
		$this->assertSame(
			array(
				'id_base' => 'recent-posts',
				'number'  => 12,
			),
			wp_parse_widget_id( 'recent-posts-12' )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_preserves_underscore_in_id_base() {
		$this->assertSame(
			array(
				'id_base' => 'nav_menu',
				'number'  => 5,
			),
			wp_parse_widget_id( 'nav_menu-5' )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_only_id_base_without_number() {
		$this->assertSame(
			array( 'id_base' => 'archives' ),
			wp_parse_widget_id( 'archives' )
		);
	}
}
