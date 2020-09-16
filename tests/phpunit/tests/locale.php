<?php

/**
 * @group l10n
 * @group i18n
 */
class Tests_Locale extends WP_UnitTestCase {
	/**
	 * @var WP_Locale
	 */
	protected $locale;

	public function setUp() {
		parent::setUp();
		$this->locale = new WP_Locale();
	}

	public function test_get_weekday() {
		$this->assertSame( __( 'Sunday' ), $this->locale->get_weekday( 0 ) );
		$this->assertSame( __( 'Monday' ), $this->locale->get_weekday( 1 ) );
		$this->assertSame( __( 'Tuesday' ), $this->locale->get_weekday( 2 ) );
		$this->assertSame( __( 'Wednesday' ), $this->locale->get_weekday( 3 ) );
		$this->assertSame( __( 'Thursday' ), $this->locale->get_weekday( 4 ) );
		$this->assertSame( __( 'Friday' ), $this->locale->get_weekday( 5 ) );
		$this->assertSame( __( 'Saturday' ), $this->locale->get_weekday( 6 ) );
	}

	/**
	 * @expectedException PHPUnit_Framework_Error_Notice
	 */
	public function test_get_weekday_undefined_index() {
		$this->locale->get_weekday( 7 );
	}

	public function test_get_weekday_initial() {
		$this->assertSame( __( 'S' ), $this->locale->get_weekday_initial( __( 'Sunday' ) ) );
		$this->assertSame( __( 'M' ), $this->locale->get_weekday_initial( __( 'Monday' ) ) );
		$this->assertSame( __( 'T' ), $this->locale->get_weekday_initial( __( 'Tuesday' ) ) );
		$this->assertSame( __( 'W' ), $this->locale->get_weekday_initial( __( 'Wednesday' ) ) );
		$this->assertSame( __( 'T' ), $this->locale->get_weekday_initial( __( 'Thursday' ) ) );
		$this->assertSame( __( 'F' ), $this->locale->get_weekday_initial( __( 'Friday' ) ) );
		$this->assertSame( __( 'S' ), $this->locale->get_weekday_initial( __( 'Saturday' ) ) );
	}

	public function test_get_weekday_abbrev() {
		$this->assertSame( __( 'Sun' ), $this->locale->get_weekday_abbrev( __( 'Sunday' ) ) );
		$this->assertSame( __( 'Mon' ), $this->locale->get_weekday_abbrev( __( 'Monday' ) ) );
		$this->assertSame( __( 'Tue' ), $this->locale->get_weekday_abbrev( __( 'Tuesday' ) ) );
		$this->assertSame( __( 'Wed' ), $this->locale->get_weekday_abbrev( __( 'Wednesday' ) ) );
		$this->assertSame( __( 'Thu' ), $this->locale->get_weekday_abbrev( __( 'Thursday' ) ) );
		$this->assertSame( __( 'Fri' ), $this->locale->get_weekday_abbrev( __( 'Friday' ) ) );
		$this->assertSame( __( 'Sat' ), $this->locale->get_weekday_abbrev( __( 'Saturday' ) ) );
	}

	public function test_get_month() {
		$this->assertSame( __( 'January' ), $this->locale->get_month( 1 ) );
		$this->assertSame( __( 'February' ), $this->locale->get_month( 2 ) );
		$this->assertSame( __( 'March' ), $this->locale->get_month( 3 ) );
		$this->assertSame( __( 'April' ), $this->locale->get_month( 4 ) );
		$this->assertSame( __( 'May' ), $this->locale->get_month( 5 ) );
		$this->assertSame( __( 'June' ), $this->locale->get_month( 6 ) );
		$this->assertSame( __( 'July' ), $this->locale->get_month( 7 ) );
		$this->assertSame( __( 'August' ), $this->locale->get_month( 8 ) );
		$this->assertSame( __( 'September' ), $this->locale->get_month( 9 ) );
		$this->assertSame( __( 'October' ), $this->locale->get_month( 10 ) );
		$this->assertSame( __( 'November' ), $this->locale->get_month( 11 ) );
		$this->assertSame( __( 'December' ), $this->locale->get_month( 12 ) );
	}

	public function test_get_month_leading_zero() {
		$this->assertSame( __( 'January' ), $this->locale->get_month( '01' ) );
		$this->assertSame( __( 'February' ), $this->locale->get_month( '02' ) );
		$this->assertSame( __( 'March' ), $this->locale->get_month( '03' ) );
		$this->assertSame( __( 'April' ), $this->locale->get_month( '04' ) );
		$this->assertSame( __( 'May' ), $this->locale->get_month( '05' ) );
		$this->assertSame( __( 'June' ), $this->locale->get_month( '06' ) );
		$this->assertSame( __( 'July' ), $this->locale->get_month( '07' ) );
		$this->assertSame( __( 'August' ), $this->locale->get_month( '08' ) );
		$this->assertSame( __( 'September' ), $this->locale->get_month( '09' ) );
	}

	public function test_get_month_abbrev() {
		$this->assertSame( __( 'Jan' ), $this->locale->get_month_abbrev( __( 'January' ) ) );
		$this->assertSame( __( 'Feb' ), $this->locale->get_month_abbrev( __( 'February' ) ) );
		$this->assertSame( __( 'Mar' ), $this->locale->get_month_abbrev( __( 'March' ) ) );
		$this->assertSame( __( 'Apr' ), $this->locale->get_month_abbrev( __( 'April' ) ) );
		$this->assertSame( __( 'May' ), $this->locale->get_month_abbrev( __( 'May' ) ) );
		$this->assertSame( __( 'Jun' ), $this->locale->get_month_abbrev( __( 'June' ) ) );
		$this->assertSame( __( 'Jul' ), $this->locale->get_month_abbrev( __( 'July' ) ) );
		$this->assertSame( __( 'Aug' ), $this->locale->get_month_abbrev( __( 'August' ) ) );
		$this->assertSame( __( 'Sep' ), $this->locale->get_month_abbrev( __( 'September' ) ) );
		$this->assertSame( __( 'Oct' ), $this->locale->get_month_abbrev( __( 'October' ) ) );
		$this->assertSame( __( 'Nov' ), $this->locale->get_month_abbrev( __( 'November' ) ) );
		$this->assertSame( __( 'Dec' ), $this->locale->get_month_abbrev( __( 'December' ) ) );
	}

	public function test_get_meridiem() {
		$this->assertSame( __( 'am' ), $this->locale->get_meridiem( 'am' ) );
		$this->assertSame( __( 'AM' ), $this->locale->get_meridiem( 'AM' ) );
		$this->assertSame( __( 'pm' ), $this->locale->get_meridiem( 'pm' ) );
		$this->assertSame( __( 'PM' ), $this->locale->get_meridiem( 'PM' ) );
	}

	public function test_is_rtl() {
		$this->assertFalse( $this->locale->is_rtl() );
		$this->locale->text_direction = 'foo';
		$this->assertFalse( $this->locale->is_rtl() );
		$this->locale->text_direction = 'rtl';
		$this->assertTrue( $this->locale->is_rtl() );
		$this->locale->text_direction = 'ltr';
		$this->assertFalse( $this->locale->is_rtl() );
	}
}
