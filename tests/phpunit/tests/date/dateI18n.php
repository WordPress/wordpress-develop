<?php

/**
 * @group date
 * @group datetime
 * @covers ::date_i18n
 */
class Tests_Date_DateI18n extends WP_UnitTestCase {

	/**
	 * Cleans up.
	 */
	public function tear_down() {
		// Reset changed options to their default value.
		update_option( 'gmt_offset', 0 );
		update_option( 'timezone_string', '' );

		parent::tear_down();
	}

	/**
	 * @ticket 28636
	 */
	public function test_should_return_current_time_on_invalid_timestamp() {
		$timezone = 'Europe/Helsinki';
		update_option( 'timezone_string', $timezone );

		$datetime     = new DateTime( 'now', new DateTimeZone( $timezone ) );
		$wp_timestamp = $datetime->getTimestamp() + $datetime->getOffset();

		$this->assertEqualsWithDelta( $wp_timestamp, date_i18n( 'U', 'invalid' ), 5, 'The dates should be equal' );
	}

	/**
	 * @ticket 28636
	 */
	public function test_should_handle_zero_timestamp() {
		$timezone = 'Europe/Helsinki';
		update_option( 'timezone_string', $timezone );

		$datetime = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			'1970-01-01 00:00:00',
			new DateTimeZone( $timezone )
		);
		$rfc3339  = $datetime->format( DATE_RFC3339 );

		$this->assertSame( 0, date_i18n( 'U', 0 ) );
		$this->assertSame( $rfc3339, date_i18n( DATE_RFC3339, 0 ) );
	}

	public function test_should_format_date() {
		$this->assertEqualsWithDelta( strtotime( gmdate( 'Y-m-d H:i:s' ) ), strtotime( date_i18n( 'Y-m-d H:i:s' ) ), 2, 'The dates should be equal' );
	}

	public function test_should_use_custom_timestamp() {
		$this->assertSame( '2012-12-01 00:00:00', date_i18n( 'Y-m-d H:i:s', strtotime( '2012-12-01 00:00:00' ) ) );
	}

	public function test_date_should_be_in_gmt() {
		$this->assertEqualsWithDelta( strtotime( gmdate( DATE_RFC3339 ) ), strtotime( date_i18n( DATE_RFC3339, false, true ) ), 2, 'The dates should be equal' );
	}

	public function test_custom_timezone_setting() {
		update_option( 'timezone_string', 'America/Regina' );

		$this->assertEqualsWithDelta( strtotime( gmdate( 'Y-m-d H:i:s', time() + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ), strtotime( date_i18n( 'Y-m-d H:i:s' ) ), 2, 'The dates should be equal' );
	}

	public function test_date_should_be_in_gmt_with_custom_timezone_setting() {
		update_option( 'timezone_string', 'America/Regina' );

		$this->assertEqualsWithDelta( strtotime( gmdate( DATE_RFC3339 ) ), strtotime( date_i18n( DATE_RFC3339, false, true ) ), 2, 'The dates should be equal' );
	}

	public function test_date_should_be_in_gmt_with_custom_timezone_setting_and_timestamp() {
		update_option( 'timezone_string', 'America/Regina' );

		$this->assertSame( '2012-12-01 00:00:00', date_i18n( 'Y-m-d H:i:s', strtotime( '2012-12-01 00:00:00' ) ) );
	}

	public function test_adjusts_format_based_on_locale() {
		$original_locale = $GLOBALS['wp_locale'];
		/* @var WP_Locale $locale */
		$locale = clone $GLOBALS['wp_locale'];

		$locale->weekday[6]                            = 'Saturday_Translated';
		$locale->weekday_abbrev['Saturday_Translated'] = 'Sat_Translated';
		$locale->month[12]                             = 'December_Translated';
		$locale->month_abbrev['December_Translated']   = 'Dec_Translated';
		$locale->meridiem['am']                        = 'am_Translated';
		$locale->meridiem['AM']                        = 'AM_Translated';

		$GLOBALS['wp_locale'] = $locale;

		$expected = 'Saturday_Translated (Sat_Translated) 01 December_Translated (Dec_Translated) 00:00:00 am_Translated AM_Translated';
		$actual   = date_i18n( 'l (D) d F (M) H:i:s a A', strtotime( '2012-12-01 00:00:00' ) );

		// Restore original locale.
		$GLOBALS['wp_locale'] = $original_locale;

		$this->assertSame( $expected, $actual );
	}

	public function test_adjusts_format_based_on_timezone_string() {
		update_option( 'timezone_string', 'America/Regina' );

		$this->assertSame( '2012-12-01 00:00:00 CST -06:00 America/Regina', date_i18n( 'Y-m-d H:i:s T P e', strtotime( '2012-12-01 00:00:00' ) ) );
	}

	/**
	 * Ensures that deprecated timezone strings are handled correctly.
	 *
	 * @ticket 56468
	 */
	public function test_adjusts_format_based_on_deprecated_timezone_string() {
		update_option( 'timezone_string', 'America/Buenos_Aires' ); // This timezone was deprecated pre-PHP 5.6.

		$expected = '2022-08-01 00:00:00 -03 -03:00 America/Buenos_Aires';
		if ( PHP_VERSION_ID < 70000 ) {
			// PHP 5.6.
			$expected = '2022-08-01 00:00:00 ART -03:00 America/Buenos_Aires';
		}

		$this->assertSame( $expected, date_i18n( 'Y-m-d H:i:s T P e', strtotime( '2022-08-01 00:00:00' ) ) );
	}

	/**
	 * @ticket 34835
	 */
	public function test_gmt_offset_should_output_correct_timezone() {
		$timezone_formats = 'P I O T Z e';
		$timezone_string  = 'America/Regina';
		$datetimezone     = new DateTimeZone( $timezone_string );
		update_option( 'timezone_string', '' );
		$offset = $datetimezone->getOffset( new DateTime() ) / 3600;
		update_option( 'gmt_offset', $offset );

		$datetime = new DateTime( 'now', $datetimezone );
		$datetime = new DateTime( $datetime->format( 'P' ) );

		$this->assertSame( $datetime->format( $timezone_formats ), date_i18n( $timezone_formats ) );
	}

	/**
	 * @ticket 20973
	 *
	 * @dataProvider data_formats
	 */
	public function test_date_i18n_handles_shorthand_formats( $short, $full ) {
		update_option( 'timezone_string', 'America/Regina' );

		$this->assertEqualsWithDelta( strtotime( date_i18n( $full ) ), strtotime( date_i18n( $short ) ), 2, 'The dates should be equal' );
		$this->assertSame( $short, date_i18n( '\\' . $short ) );
	}

	public function data_formats() {
		return array(
			array(
				'c',
				'Y-m-d\TH:i:sP',
			),
			array(
				'r',
				'D, d M Y H:i:s O',
			),
		);
	}

	/**
	 * @ticket 25768
	 */
	public function test_should_return_wp_timestamp() {
		update_option( 'timezone_string', 'Europe/Helsinki' );

		$datetime     = new DateTimeImmutable( 'now', wp_timezone() );
		$timestamp    = $datetime->getTimestamp();
		$wp_timestamp = $timestamp + $datetime->getOffset();

		$this->assertEqualsWithDelta( $wp_timestamp, date_i18n( 'U' ), 2, 'The dates should be equal' );
		$this->assertEqualsWithDelta( $timestamp, date_i18n( 'U', false, true ), 2, 'The dates should be equal' );
		$this->assertSame( $wp_timestamp, date_i18n( 'U', $wp_timestamp ) );
	}

	/**
	 * @ticket 43530
	 */
	public function test_swatch_internet_time_with_wp_timestamp() {
		update_option( 'timezone_string', 'America/Regina' );

		$this->assertSame( gmdate( 'B' ), date_i18n( 'B' ) );
	}

	/**
	 * @ticket 25768
	 */
	public function test_should_handle_escaped_formats() {
		$format = 'D | \D | \\D | \\\D | \\\\D | \\\\\D | \\\\\\D';

		$this->assertSame( gmdate( $format ), date_i18n( $format ) );
	}

	/**
	 * @ticket 25768
	 *
	 * @dataProvider dst_times
	 *
	 * @param string $time     Time to test in Y-m-d H:i:s format.
	 * @param string $timezone PHP timezone string to use.
	 */
	public function test_should_handle_dst( $time, $timezone ) {
		update_option( 'timezone_string', $timezone );

		$timezone     = new DateTimeZone( $timezone );
		$datetime     = new DateTime( $time, $timezone );
		$wp_timestamp = strtotime( $time );
		$format       = 'I ' . DATE_RFC3339;

		$this->assertSame( $datetime->format( $format ), date_i18n( $format, $wp_timestamp ) );
	}

	public function dst_times() {
		return array(
			'Before DST start' => array( '2019-03-31 02:59:00', 'Europe/Helsinki' ),
			'After DST start'  => array( '2019-03-31 04:01:00', 'Europe/Helsinki' ),
			'Before DST end'   => array( '2019-10-27 02:59:00', 'Europe/Helsinki' ),
			'After DST end'    => array( '2019-10-27 04:01:00', 'Europe/Helsinki' ),
		);
	}
}
