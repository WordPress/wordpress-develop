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
	 * Test if no timestamp provides
	 *
	 * @ticket 53485
	 */
	public function test_no_timestamp() {

		$this->assertSame( (string) strtotime( 'now' ), wp_date( 'U' ) );
	}

	/**
	 * Test if format is set to D weekday_abbrev
	 *
	 * @ticket 53485
	 */
	public function test_format_D() {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( 'Thu', wp_date( 'D', $datetime->getTimestamp(), $utc ) );
	}

	/**
	 * Test if format is set to F Month
	 *
	 * @ticket 53485
	 */
	public function test_format_F() {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( 'October', wp_date( 'F', $datetime->getTimestamp(), $utc ) );
	}

	/**
	 * Test if format is set to L weekday
	 *
	 * @ticket 53485
	 */
	public function test_format_l() {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( 'Thursday', wp_date( 'l', $datetime->getTimestamp(), $utc ) );
	}

	/**
	 * Test if format is set to M month_abbrev
	 *
	 * @ticket 53485
	 */
	public function test_format_M() {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( 'Oct', wp_date( 'M', $datetime->getTimestamp(), $utc ) );
	}

	/**
	 * Test if format is set to a am/pm lowercase
	 *
	 * @ticket 53485
	 */
	public function test_format_a() {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( 'am', wp_date( 'a', $datetime->getTimestamp(), $utc ) );
	}

	/**
	 * Test if format is set to A  am/pm uppercase
	 *
	 * @ticket 53485
	 */
	public function test_format_upper_A() {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( 'AM', wp_date( 'A', $datetime->getTimestamp(), $utc ) );
	}

	/**
	 * Test if format is set to A with an escaped A
	 *
	 * @ticket 53485
	 */
	public function test_format_slash() {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( 'A AM', wp_date( '\\A A', $datetime->getTimestamp(), $utc ) );
	}


	/**
	 * Test if format is set directly
	 *
	 * @ticket 53485
	 */
	public function test_format() {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new DateTimeImmutable( '2019-10-17', $utc );

		$this->assertSame( '041', wp_date( 'B', $datetime->getTimestamp(), $utc ) );
	}

	/**
	 * Test wp_date Filter runs
	 *
	 * @ticket 53485
	 */
	public function test_wp_date_filter() {
		add_filter( 'wp_date', array( $this, '_test_wp_date_filter' ), 99 );

		$this->assertSame( 'filtered', wp_date( '' ) );

		remove_filter( 'wp_date', array( $this, '_test_wp_date_filter' ), 99 );
	}

	public function _test_wp_date_filter() {

		return 'filtered';
	}
}
