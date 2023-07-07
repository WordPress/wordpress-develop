<?php

/**
 * @group date
 * @group datetime
 * @covers ::wp_date
 */
class Tests_Date_wpDate extends WP_UnitTestCase {

	/** @var WP_Locale */
	private $wp_locale_original;

	public function set_up() {
		global $wp_locale;

		parent::set_up();

		$this->wp_locale_original = clone $wp_locale;
	}

	public function tear_down() {
		global $wp_locale;

		$wp_locale = $this->wp_locale_original;

		parent::tear_down();
	}

	/**
	 * @ticket 28636
	 */
	public function test_should_return_false_on_invalid_timestamp() {
		$this->assertFalse( wp_date( DATE_RFC3339, 'invalid' ) );
	}

	/**
	 * @ticket 48319
	 */
	public function test_should_not_escape_localized_numbers() {
		global $wp_locale;

		$wp_locale->month = array( 10 => '10月' );

		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( '10月', wp_date( 'F', $datetime->getTimestamp(), $utc ) );
	}

	/**
	 * @ticket 48319
	 */
	public function test_should_keep_localized_slashes() {
		global $wp_locale;

		$string           = 'A \ B';
		$wp_locale->month = array( 10 => $string );

		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( $string, wp_date( 'F', $datetime->getTimestamp(), $utc ) );
	}

	/**
	 * Tests that the date is formatted with no timestamp provided.
	 *
	 * @ticket 53485
	 */
	public function test_should_format_date_with_no_timestamp() {
		$utc = new DateTimeZone( 'UTC' );
		$this->assertSame( (string) time(), wp_date( 'U', null, $utc ) );
	}

	/**
	 * Tests that the date is formatted with no timezone provided.
	 *
	 * @ticket 53485
	 */
	public function test_should_format_date_with_no_timezone() {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );
		$this->assertSame( 'October', wp_date( 'F', $datetime->getTimestamp() ) );
	}

	/**
	 * Tests that the format is set correctly.
	 *
	 * @ticket 53485
	 *
	 * @dataProvider data_should_format_date
	 *
	 * @param string $expected The expected result.
	 * @param string $format   The date format.
	 */
	public function test_should_format_date( $expected, $format ) {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( $expected, wp_date( $format, $datetime->getTimestamp(), $utc ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_format_date() {
		return array(
			'Swatch Internet Time'                        => array(
				'expected' => '041',
				'format'   => 'B',
			),
			'Ante meridiem and Post meridiem (uppercase)' => array(
				'expected' => 'AM',
				'format'   => 'A',
			),
			'Ante meridiem and Post meridiem (uppercase) and escaped "A"' => array(
				'expected' => 'A AM',
				'format'   => '\\A A',
			),
			'Ante meridiem and Post meridiem (lowercase)' => array(
				'expected' => 'am',
				'format'   => 'a',
			),
			'Month'                                       => array(
				'expected' => 'October',
				'format'   => 'F',
			),
			'Month (abbreviated'                          => array(
				'expected' => 'Oct',
				'format'   => 'M',
			),
			'Weekday'                                     => array(
				'expected' => 'Thursday',
				'format'   => 'l',
			),
			'Weekday (abbreviated)'                       => array(
				'expected' => 'Thu',
				'format'   => 'D',
			),
		);
	}

	/**
	 * Tests that the date is formatted when
	 * `$wp_locale->month` and `$wp_locale->weekday` are empty.
	 *
	 * @ticket 53485
	 */
	public function test_should_format_date_with_empty_wp_locale_month_and_weekday() {
		global $wp_locale;

		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$wp_locale->month   = array();
		$wp_locale->weekday = array();
		$actual             = wp_date( 'F', $datetime->getTimestamp(), $utc );

		$this->assertSame( 'October', $actual );
	}

	/**
	 * Tests the wp_date filter.
	 *
	 * @ticket 53485
	 */
	public function test_should_apply_filters_for_wp_date() {
		$ma = new MockAction();
		add_filter( 'wp_date', array( &$ma, 'filter' ) );
		wp_date( '' );

		$this->assertSame( 1, $ma->get_call_count() );
	}
}
