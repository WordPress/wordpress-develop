<?php
/**
 * Tests for the get_calendar() function.
 *
 * @since 6.8.0
 *
 * @group general
 * @group template
 *
 * @covers ::get_calendar
 */
class Tests_General_GetCalendar extends WP_UnitTestCase {

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

		self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_date' => '2025-02-03 12:00:00',
			)
		);
	}

	/**
	 * Set up for each test.
	 */
	public function set_up() {
		parent::set_up();

		/*
		 * Navigate to February 2025.
		 *
		 * All posts within this test suite are published in February 2025,
		 * navigating to the month ensures that the correct month is displayed
		 * in the calendar to allow the assertions to pass.
		 */
		$this->go_to( '/?m=202502' );
	}

	/**
	 * Test that get_calendar() displays output when display is true.
	 *
	 * @ticket 34093
	 */
	public function test_get_calendar_display() {
		$calendar_html = get_echo( 'get_calendar', array( array( 'display' => true ) ) );
		$this->assertStringContainsString( '<th scope="col" aria-label="Monday">M</th>', $calendar_html, 'Calendar is expected to use initials for day names' );
		$this->assertStringContainsString( '<table id="wp-calendar"', $calendar_html, 'Calendar is expected to contain the element table#wp-calendar' );
		$this->assertStringContainsString( 'Posts published on February 1, 2025', $calendar_html, 'Calendar is expected to display posts published on February 1, 2025.' );
		$this->assertStringContainsString( '<caption>February 2025</caption', $calendar_html, 'Calendar is expected to be captioned February 2025.' );
	}

	/**
	 * Test that get_calendar() respects the get_calendar_args filter.
	 *
	 * @ticket 34093
	 */
	public function test_get_calendar_args_filter() {
		add_filter(
			'get_calendar_args',
			function ( $args ) {
				$args['post_type'] = 'page';
				return $args;
			}
		);

		$calendar_html = get_echo( 'get_calendar' );

		$this->assertStringContainsString( '<th scope="col" aria-label="Monday">M</th>', $calendar_html, 'Calendar is expected to use initials for day names' );
		$this->assertStringContainsString( '<table id="wp-calendar"', $calendar_html, 'Calendar is expected to contain the element table#wp-calendar' );
		$this->assertStringContainsString( 'Posts published on February 3, 2025', $calendar_html, 'Calendar is expected to display page published on February 3, 2025.' );
		$this->assertStringNotContainsString( 'Posts published on February 1, 2025', $calendar_html, 'Calendar is not expected to display posts published on February 1, 2025.' );
		$this->assertStringContainsString( '<caption>February 2025</caption', $calendar_html, 'Calendar is expected to be captioned February 2025.' );
	}

	/**
	 * Test that get_calendar() respects the args post type parameter.
	 *
	 * @ticket 34093
	 */
	public function test_get_calendar_post_type_args() {
		$calendar_html = get_echo( 'get_calendar', array( array( 'post_type' => 'page' ) ) );

		$this->assertStringContainsString( '<th scope="col" aria-label="Monday">M</th>', $calendar_html, 'Calendar is expected to use initials for day names' );
		$this->assertStringContainsString( '<table id="wp-calendar"', $calendar_html, 'Calendar is expected to contain the element table#wp-calendar' );
		$this->assertStringContainsString( 'Posts published on February 3, 2025', $calendar_html, 'Calendar is expected to display page published on February 3, 2025.' );
		$this->assertStringNotContainsString( 'Posts published on February 1, 2025', $calendar_html, 'Calendar is not expected to display posts published on February 1, 2025.' );
		$this->assertStringContainsString( '<caption>February 2025</caption', $calendar_html, 'Calendar is expected to be captioned February 2025.' );
	}

	/**
	 * Test that get_calendar() respects the args initial parameter.
	 *
	 * @ticket 34093
	 */
	public function test_get_calendar_initial_args() {
		$first_calendar_html  = get_echo( 'get_calendar', array( array( 'initial' => true ) ) );
		$second_calendar_html = get_echo( 'get_calendar', array( array( 'initial' => false ) ) );

		$this->assertStringContainsString( '<th scope="col" aria-label="Monday">M</th>', $first_calendar_html, 'First calendar is expected to use initials for day names' );
		$this->assertStringContainsString( '<th scope="col" aria-label="Monday">Mon</th>', $second_calendar_html, 'Second calendar is expected to use abbreviations for day names' );
	}

	/**
	 * Test that get_calendar() uses a different cache for different arguments.
	 *
	 * @ticket 34093
	 */
	public function test_get_calendar_caching_accounts_for_args() {
		$first_calendar_html  = get_echo( 'get_calendar' );
		$second_calendar_html = get_echo( 'get_calendar', array( array( 'post_type' => 'page' ) ) );

		$this->assertNotSame( $first_calendar_html, $second_calendar_html, 'Each calendar should be different' );
	}

	/**
	 * Test that get_calendar() uses the same cache for equivalent arguments.
	 *
	 * @ticket 34093
	 */
	public function test_get_calendar_caching_accounts_for_equivalent_args() {
		get_echo( 'get_calendar', array( array( 'post_type' => 'page' ) ) );

		$num_queries_start = get_num_queries();
		// Including an argument that is the same as the default value shouldn't miss the cache.
		get_echo(
			'get_calendar',
			array(
				array(
					'post_type' => 'page',
					'initial'   => true,
				),
			)
		);

		// Changing the order of arguments shouldn't miss the cache.
		get_echo(
			'get_calendar',
			array(
				array(
					'initial'   => true,
					'post_type' => 'page',
				),
			)
		);

		// Display param should be ignored for the cache.
		get_calendar(
			array(
				'post_type' => 'page',
				'initial'   => true,
				'display'   => false,
			)
		);
		$num_queries_end = get_num_queries();

		$this->assertSame( 0, $num_queries_end - $num_queries_start, 'Cache should be hit for subsequent equivalent calendar queries.' );
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

		$this->assertStringContainsString( '<th scope="col" aria-label="Monday">Mon</th>', $first_calendar_html, 'Calendar is expected to use abbreviations for day names' );
		$this->assertStringContainsString( '<caption>February 2025</caption>', $first_calendar_html, 'Calendar is expected to be captioned February 2025' );
		$this->assertStringContainsString( '<table id="wp-calendar"', $first_calendar_html, 'Calendar is expected to contain the element table#wp-calendar' );
		$this->assertSame( $first_calendar_html, $second_calendar_html, 'Both calendars should be identical' );
	}
}
