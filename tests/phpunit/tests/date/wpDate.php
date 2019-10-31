<?php

/**
 * @group date
 * @group datetime
 */
class Tests_Date_WP_Date extends WP_UnitTestCase {

	/** @var WP_Locale */
	private $wp_locale_original;

	public function setUp() {
		global $wp_locale;

		parent::setUp();

		$this->wp_locale_original = clone $wp_locale;
	}

	public function tearDown() {
		global $wp_locale;

		$wp_locale = $this->wp_locale_original;

		parent::tearDown();
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

		$this->assertEquals( '10月', wp_date( 'F', $datetime->getTimestamp(), $utc ) );
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

		$this->assertEquals( $string, wp_date( 'F', $datetime->getTimestamp(), $utc ) );
	}
}
