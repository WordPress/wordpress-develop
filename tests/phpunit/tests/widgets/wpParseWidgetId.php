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
}
