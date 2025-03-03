<?php
/**
 * Tests for the get_calendar() function.
 *
 * @since 6.8.0
 *
 * @group functions
 * @group calendar
 *
 * @covers ::get_calendar
 */
class Tests_Get_Calendar extends WP_UnitTestCase {

	/**
	 * Array of post IDs.
	 *
	 * @var int[]
	 */
	protected static $post_ids = array();

	/**
	 * Set up before class.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids = $factory->post->create_many(
			3,
			array(
				'post_date' => '2025-02-01 12:00:00',
			)
		);
	}

	/**
	 * Test that get_calendar() displays output when display is true.
	 *
	 * @ticket 34093
	 */
	public function test_get_calendar_display() {
		$expected = '<table id="wp-calendar"';
		$actual   = get_echo( 'get_calendar', array( array( 'display' => true ) ) );
		$this->assertStringContainsString( $expected, $actual );
	}

	/**
	 * Test that get_calendar() respects the get_calendar_args filter.
	 *
	 * @ticket 34093
	 */
	public function test_get_calendar_args_filter() {
		$page_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_date' => '2025-02-03 12:00:00',
			)
		);

		add_filter(
			'get_calendar_args',
			function ( $args ) {
				$args['post_type'] = 'page';
				return $args;
			}
		);

		$calendar_html = get_echo( 'get_calendar' );

		remove_all_filters( 'get_calendar_args' );

		$this->assertStringContainsString( '<table id="wp-calendar"', $calendar_html );
		$this->assertStringContainsString( 'Posts published on February 3, 2025', $calendar_html );
		$this->assertStringContainsString( 'February 2025', $calendar_html );
	}

	/**
	 * Test that get_calendar() maintains backwards compatibility with old parameter format.
	 *
	 * @ticket 34093
	 */
	public function test_get_calendar_backwards_compatibility() {
		$first_calendar_html = get_echo( 'get_calendar', array( false ) );

		wp_cache_delete( 'get_calendar', 'calendar' );

		$second_calendar_html = get_calendar( false, false );

		$this->assertStringContainsString( '<th scope="col" aria-label="Monday">Mon</th>', $first_calendar_html );
		$this->assertStringContainsString( 'February 2025', $first_calendar_html );
		$this->assertStringContainsString( '<table id="wp-calendar"', $second_calendar_html );
		$this->assertStringContainsString( '<th scope="col" aria-label="Monday">Mon</th>', $second_calendar_html );
	}
}
