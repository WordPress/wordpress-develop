<?php

/**
 * Class responsible for testing the functionality of the get_calendar() template function
 * in various scenarios, including when a cached calendar is available, when no posts exist,
 * current month rendering, and more.
 *
 * @covers get_calendar()
 */

class Tests_General_Template_GetCalendar extends WP_UnitTestCase {
	/**
	 * Tests if get_calendar() returns the cached calendar when available.
	 *
	 * This test function verifies that the get_calendar() function correctly
	 * retrieves and returns a cached calendar when one is available in the cache.
	 * It also tests the non-display mode of get_calendar().
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_returns_cached_calendar() {
		global $m, $monthnum, $year;

		$m        = '202306';
		$monthnum = '06';
		$year     = '2023';

		$key             = md5( $m . $monthnum . $year );
		$cached_calendar = '<div class="cached-calendar">Cached Calendar Content</div>';

		wp_cache_set( 'get_calendar', array( $key => $cached_calendar ), 'calendar' );

		$this->expectOutputString( $cached_calendar );
		get_calendar();

		// Test non-display mode
		$result = get_calendar( true, false );
		$this->assertEquals( $cached_calendar, $result );
	}

	/**
	 * Tests if get_calendar() returns an empty string when there are no posts.
	 *
	 * This test function verifies that the get_calendar() function correctly
	 * returns an empty string and sets an empty cache when there are no posts
	 * in the database.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_returns_empty_string_if_no_posts() {
		global $posts, $m, $monthnum, $year;

		// Set up the test environment
		$posts = array();

		// Call the function
		$result = get_calendar( true, false );

		// Assert that the function returns an empty string
		$this->assertEmpty( $result );

		// Verify that the cache was set with an empty string
		$cache = wp_cache_get( 'get_calendar', 'calendar' );
		$key   = md5( $m . $monthnum . $year );
		$this->assertArrayHasKey( $key, $cache );
		$this->assertEmpty( $cache[ $key ] );
	}

	/**
	 * Tests if get_calendar() outputs the correct calendar for the current month.
	 *
	 * This test verifies that the get_calendar() function correctly generates the calendar
	 * for the current month, including the current year, days, and other expected details such as
	 * marking today's date, displaying correct day names, and including month navigation links.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_current_month() {
		global $wp_locale, $m, $monthnum, $year;

		$m        = gmdate( 'Ym' );
		$monthnum = gmdate( 'm' );
		$year     = gmdate( 'Y' );

		// Set up the current date
		$current_year  = gmdate( 'Y' );
		$current_month = gmdate( 'm' );
		$current_day   = gmdate( 'j' );
		$post_date     = gmdate( 'Y-m-d', strtotime( $current_year . '-' . $current_month . '-' . $current_day - 1 ) );

		$post_id = self::factory()->post->create(
			array(
				'post_date'   => $post_date,
				'post_status' => 'publish',
			)
		);

		// Call get_calendar() with default parameters
		$calendar_output = get_calendar( true, false );

		// Assert that the calendar output contains the current month and year
		$this->assertStringContainsString( $wp_locale->get_month( $current_month ), $calendar_output );
		$this->assertStringContainsString( $current_year, $calendar_output );

		// Assert that the calendar contains the correct number of days for the current month
		$days_in_month = gmdate( 't' );
		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$this->assertStringContainsString( ">$day<", $calendar_output );
		}

		// Assert that today's date is marked with the "today" ID
		$this->assertStringContainsString( '<td id="today">' . $current_day, $calendar_output );

		// Assert that the calendar contains the correct day names
		$week_begins = (int) get_option( 'start_of_week' );
		for ( $i = 0; $i < 7; $i++ ) {
			$day_name = $wp_locale->get_weekday_initial( $wp_locale->get_weekday( ( $i + $week_begins ) % 7 ) );
			$this->assertStringContainsString( $day_name, $calendar_output );
		}

		// Assert that the navigation links are present
		$this->assertStringContainsString( 'class="wp-calendar-nav-prev"', $calendar_output );
		$this->assertStringContainsString( 'class="wp-calendar-nav-next"', $calendar_output );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Tests if get_calendar() correctly generates a calendar for a specified month and year.
	 *
	 * This test function verifies that the get_calendar() function:
	 * 1. Displays the correct caption indicating the specified month and year.
	 * 2. Includes the correct weekday abbreviations in the calendar header.
	 * 3. Outputs the correct date range for the specified month, ensuring only valid days are included.
	 * 4. Includes navigation elements in the calendar.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_for_specified_month_and_year() {
		global $m, $monthnum, $year;

		$m        = '202306';
		$monthnum = '06';
		$year     = '2023';

		$post_date = gmdate( 'Y-m-d', strtotime( $year . '-' . $monthnum . '-06' ) );

		$post_id = self::factory()->post->create(
			array(
				'post_date'   => $post_date,
				'post_status' => 'publish',
			)
		);

		$expected_caption  = 'June 2023';
		$expected_weekdays = array(
			'S' => 'Sunday',
			'M' => 'Monday',
			'T' => 'Tuesday',
			'W' => 'Wednesday',
			'T' => 'Thursday',
			'F' => 'Friday',
			'S' => 'Saturday',
		);

		$calendar = get_calendar( true, false );

		$this->assertStringContainsString( $expected_caption, $calendar );

		foreach ( $expected_weekdays as $letter => $weekday ) {
			$this->assertStringContainsString( '<th scope="col" title="' . $weekday . '">' . $letter . '</th>', $calendar );
		}

		$this->assertStringContainsString( '<td>1</td>', $calendar );
		$this->assertStringContainsString( '<td>30</td>', $calendar );
		$this->assertStringNotContainsString( '<td>31</td>', $calendar );

		$this->assertStringContainsString( 'class="wp-calendar-nav"', $calendar );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Tests if the function get_calendar correctly handles input based on a specified week of the year.
	 *
	 * This method sets up a mock environment with database and localization functionality to emulate
	 * the retrieval and rendering of a calendar for a given week number. It verifies that the calendar
	 * correctly displays the appropriate month, year, and links for posts on specific days within that week.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_handles_week_based_input() {
		global $wpdb, $wp_locale, $m;
		$_GET['w'] = '23'; // Set week number
		$m         = '2023'; // Set year

		// Calculate the date of the first day of the week
		$first_day_of_week = gmdate( 'Y-m-d', strtotime( $m . 'W' . str_pad( $_GET['w'], 2, '0', STR_PAD_LEFT ) ) );

		// Get the month from this date
		$monthnum = gmdate( 'm', strtotime( $first_day_of_week ) );

		$post_date = gmdate( 'Y-m-d', strtotime( $m . '-' . $monthnum . '-06' ) );
		$post_id   = self::factory()->post->create(
			array(
				'post_date'   => $post_date,
				'post_status' => 'publish',
			)
		);

		// Mock database queries
		$wpdb = $this->createMock( wpdb::class );
		$wpdb->method( 'get_var' )
		->willReturn( '06' ); // Assume the week falls in June

		$wpdb->method( 'get_results' )
		->willReturn( array( array( 15 ) ) ); // Assume there's a post on the 15th

		// Mock wp_locale
		$wp_locale = $this->createMock( WP_Locale::class );
		$wp_locale->method( 'get_month' )
				->willReturn( 'June' );
		$wp_locale->method( 'get_weekday' )
				->willReturn( 'Monday' );
		$wp_locale->method( 'get_weekday_initial' )
				->willReturn( 'M' );

		// Call get_calendar
		$calendar = get_calendar( true, false );

		// Assert that the calendar contains the correct month and year
		$this->assertStringContainsString( 'June 2023', $calendar );

		// Assert that the calendar contains a link for the day with a post
		$this->assertStringContainsString( 'href="' . get_day_link( 2023, 6, 15 ) . '"', $calendar );

		// Clean up
		unset( $_GET['w'] );
		wp_delete_post( $post_id, true );
	}

	/**
	 * Tests whether the function get_calendar correctly generates weekday headers in the calendar table.
	 *
	 * This method creates a mock environment to render a calendar for a specific month and verifies
	 * that the generated output includes the appropriate HTML structure and weekday headers. It ensures
	 * that each day of the week is correctly displayed with its corresponding abbreviation and title.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_weekday_headers() {
		global $wp_locale;

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);

		$calendar = get_calendar( false, false );

		$this->assertStringContainsString( '<table id="wp-calendar" class="wp-calendar-table">', $calendar );
		$this->assertStringContainsString( '<thead>', $calendar );
		$this->assertStringContainsString( '<tr>', $calendar );

		$weekdays = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		foreach ( $weekdays as $weekday ) {
			$abbrev = $wp_locale->get_weekday_abbrev( $weekday );
			$this->assertStringContainsString( "<th scope=\"col\" title=\"$weekday\">$abbrev</th>", $calendar );
		}

		$this->assertStringContainsString( '</tr>', $calendar );
		$this->assertStringContainsString( '</thead>', $calendar );
		$this->assertStringContainsString( '<tbody>', $calendar );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Tests if the function get_calendar highlights the current day of the month accurately.
	 *
	 * This method sets up a scenario where the current day has an associated published post.
	 * It verifies that the calendar correctly highlights the current day with the 'id="today"'
	 * attribute. Additionally, it ensures that no other day within the month is mistakenly
	 * highlighted as the current day.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_highlights_current_day() {
		$current_year  = current_time( 'Y' );
		$current_month = current_time( 'm' );
		$current_day   = current_time( 'j' );
		$post_date     = gmdate( 'Y-m-d', strtotime( $current_year . '-' . $current_month . '-' . $current_day ) );

		$post_id = self::factory()->post->create(
			array(
				'post_date'   => $post_date,
				'post_status' => 'publish',
			)
		);

		// Set the global variables
		global $m, $monthnum, $year;
		$m        = $current_year . $current_month;
		$monthnum = $current_month;
		$year     = $current_year;

		// Get the calendar output
		$calendar_output = get_calendar( true, false );

		// Check if the current day is highlighted with the 'id="today"' attribute
		$expected_today_html = sprintf( '/<td id="today"><a .*>%d<\/a><\/td>/', $current_day );
		$this->assertMatchesRegularExpression( $expected_today_html, $calendar_output, 'Calendar output should highlight the current day.' );

		// Check that no other day has the 'id="today"' attribute
		$other_days = range( 1, 31 );
		unset( $other_days[ $current_day - 1 ] ); // Remove the current day from the array
		foreach ( $other_days as $day ) {
			$unexpected_today_html = sprintf( '<td id="today">%d</td>', $day );
			$this->assertStringNotContainsString( $unexpected_today_html, $calendar_output, sprintf( 'Day %d should not be highlighted as today.', $day ) );
		}

		wp_delete_post( $post_id, true );
	}

	/**
	 * Tests if the function get_calendar includes links to days that have published posts.
	 *
	 * This method creates a post dated today and verifies that the calendar output contains
	 * a link to the day corresponding to the post's publication date. It ensures that the
	 * calendar accurately highlights days with published posts by generating proper links.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_includes_links_to_days_with_posts() {
		// Create a post for today
		$today   = current_time( 'Y-m-d' );
		$post_id = self::factory()->post->create(
			array(
				'post_date'   => $today . ' 12:00:00',
				'post_status' => 'publish',
			)
		);

		// Get the calendar output
		$calendar = get_calendar( true, false );

		// Check if the calendar contains a link to today's post
		$this->assertStringContainsString(
			'<a href="' . get_day_link( current_time( 'Y' ), current_time( 'm' ), current_time( 'j' ) ) . '"',
			$calendar,
			'Calendar should contain a link to the day with a published post'
		);

		// Clean up
		wp_delete_post( $post_id, true );
	}


	/**
	 * Tests if the function get_calendar correctly handles months with fewer than 31 days.
	 *
	 * This method sets up a test environment with a post created in February to verify
	 * that the calendar correctly displays the appropriate number of days for the month,
	 * ensuring the absence of invalid days (e.g., 29th, 30th, or 31st in non-leap years).
	 * It also checks that the post date is properly linked within the calendar output.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_handles_months_with_less_than_31_days() {
		global $m, $monthnum, $year;

		$m        = '202302';
		$monthnum = '02';
		$year     = '2023';

		// Set up a test post in February
		$post_date = '2023-02-15 12:00:00';
		$post_id   = self::factory()->post->create(
			array(
				'post_date'   => $post_date,
				'post_status' => 'publish',
			)
		);

		// Capture the output
		ob_start();
		get_calendar();
		$calendar_output = ob_get_clean();

		// Check if the calendar contains the correct number of days for February
		$this->assertStringContainsString( '<td>28</td>', $calendar_output );
		$this->assertStringNotContainsString( '<td>29</td>', $calendar_output );
		$this->assertStringNotContainsString( '<td>30</td>', $calendar_output );
		$this->assertStringNotContainsString( '<td>31</td>', $calendar_output );

		// Check if the post date is linked
		$this->assertStringContainsString( '<a href="' . get_day_link( 2023, 2, 15 ) . '"', $calendar_output );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Tests if the function get_calendar includes navigation links for adjacent months.
	 *
	 * This method sets up a test environment by creating posts in different months to ensure
	 * that the navigation links for the previous and next months are correctly displayed
	 * in the calendar output. It verifies the presence of the navigation links and
	 * checks that they contain the correct month names.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_includes_navigation_links() {
		global $m, $monthnum, $year;

		$m        = '202306';
		$monthnum = '06';
		$year     = '2023';

		// Create some test posts in different months
		$post_ids[] = self::factory()->post->create( array( 'post_date' => '2023-05-15 12:00:00' ) );
		$post_ids[] = self::factory()->post->create( array( 'post_date' => '2023-06-15 12:00:00' ) );
		$post_ids[] = self::factory()->post->create( array( 'post_date' => '2023-07-15 12:00:00' ) );

		// Get the calendar output
		$calendar = get_calendar( true, false );

		// Check for the presence of navigation links
		$this->assertStringContainsString( '<span class="wp-calendar-nav-prev"><a href="', $calendar );
		$this->assertStringContainsString( '<span class="wp-calendar-nav-next"><a href="', $calendar );

		// Check for the correct month names in the navigation links
		$this->assertStringContainsString( '>&laquo; May</a>', $calendar );
		$this->assertStringContainsString( '>Jul &raquo;</a>', $calendar );

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Tests if the function get_calendar handles out of range values.
	 *
	 * @ticket 41011
	 */
	public function test_get_calendar_out_of_range() {
		global $m, $monthnum, $year;

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);

		$m        = gmdate( 'Ym' );
		$monthnum = null;
		$year     = null;

		wp_cache_delete( 'get_calendar', 'calendar' );
		$today_calendar = get_calendar( true, false );

		// Assert that the calendar output is empty or contains an error message
		$this->assertNotEmpty( $today_calendar );

		$_GET['w'] = 60; // Invalid week number (60)

		wp_cache_delete( 'get_calendar', 'calendar' );
		$calendar = get_calendar( true, false );

		$this->assertSame( $today_calendar, $calendar );

		// Set up an invalid date
		$m        = '202313'; // Invalid month (13)
		$monthnum = '13';
		$year     = gmdate( 'Y' );

		wp_cache_delete( 'get_calendar', 'calendar' );
		$calendar = get_calendar( true, false );

		$this->assertSame( $today_calendar, $calendar );

		wp_delete_post( $post_id, true );
	}
}
