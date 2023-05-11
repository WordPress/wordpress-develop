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

	public function set_up() {
		parent::set_up();
		$this->locale = new WP_Locale();
	}

	/**
	 * @ticket 57427
	 *
	 * @dataProvider data_property_initializes_to_array
	 *
	 * @param string $name Property name to test.
	 */
	public function test_property_initializes_to_array( $name ) {
		$this->assertIsArray( $this->locale->$name, "WP_Locale::{$name} property should be an array" );

		// Test a custom implementation when `init()` is not invoked in the constructor.
		$wp_locale = new Custom_WP_Locale();
		$this->assertIsArray( $wp_locale->$name, "Custom_WP_Locale::{$name} property should be an array" );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_property_initializes_to_array() {
		return array(
			'weekday'         => array( 'weekday' ),
			'weekday_initial' => array( 'weekday_initial' ),
			'weekday_abbrev'  => array( 'weekday_abbrev' ),
			'month'           => array( 'month' ),
			'month_genitive'  => array( 'month_genitive' ),
			'month_abbrev'    => array( 'month_abbrev' ),
			'meridiem'        => array( 'meridiem' ),
			'number_format'   => array( 'number_format' ),
		);
	}

	/**
	 * @covers WP_Locale::get_weekday
	 */
	public function test_get_weekday() {
		$this->assertSame( (string) __( 'Sunday' ), $this->locale->get_weekday( 0 ) );
		$this->assertSame( (string) __( 'Monday' ), $this->locale->get_weekday( 1 ) );
		$this->assertSame( (string) __( 'Tuesday' ), $this->locale->get_weekday( 2 ) );
		$this->assertSame( (string) __( 'Wednesday' ), $this->locale->get_weekday( 3 ) );
		$this->assertSame( (string) __( 'Thursday' ), $this->locale->get_weekday( 4 ) );
		$this->assertSame( (string) __( 'Friday' ), $this->locale->get_weekday( 5 ) );
		$this->assertSame( (string) __( 'Saturday' ), $this->locale->get_weekday( 6 ) );
	}

	/**
	 * @covers WP_Locale::get_weekday
	 */
	public function test_get_weekday_undefined_index() {
		if ( PHP_VERSION_ID >= 80000 ) {
			$this->expectWarning();
		} else {
			$this->expectNotice();
		}

		$this->locale->get_weekday( 7 );
	}

	/**
	 * @covers WP_Locale::get_weekday_initial
	 */
	public function test_get_weekday_initial() {
		$this->assertSame( (string) __( 'S' ), $this->locale->get_weekday_initial( __( 'Sunday' ) ) );
		$this->assertSame( (string) __( 'M' ), $this->locale->get_weekday_initial( __( 'Monday' ) ) );
		$this->assertSame( (string) __( 'T' ), $this->locale->get_weekday_initial( __( 'Tuesday' ) ) );
		$this->assertSame( (string) __( 'W' ), $this->locale->get_weekday_initial( __( 'Wednesday' ) ) );
		$this->assertSame( (string) __( 'T' ), $this->locale->get_weekday_initial( __( 'Thursday' ) ) );
		$this->assertSame( (string) __( 'F' ), $this->locale->get_weekday_initial( __( 'Friday' ) ) );
		$this->assertSame( (string) __( 'S' ), $this->locale->get_weekday_initial( __( 'Saturday' ) ) );
	}

	/**
	 * @covers WP_Locale::get_weekday_abbrev
	 */
	public function test_get_weekday_abbrev() {
		$this->assertSame( (string) __( 'Sun' ), $this->locale->get_weekday_abbrev( __( 'Sunday' ) ) );
		$this->assertSame( (string) __( 'Mon' ), $this->locale->get_weekday_abbrev( __( 'Monday' ) ) );
		$this->assertSame( (string) __( 'Tue' ), $this->locale->get_weekday_abbrev( __( 'Tuesday' ) ) );
		$this->assertSame( (string) __( 'Wed' ), $this->locale->get_weekday_abbrev( __( 'Wednesday' ) ) );
		$this->assertSame( (string) __( 'Thu' ), $this->locale->get_weekday_abbrev( __( 'Thursday' ) ) );
		$this->assertSame( (string) __( 'Fri' ), $this->locale->get_weekday_abbrev( __( 'Friday' ) ) );
		$this->assertSame( (string) __( 'Sat' ), $this->locale->get_weekday_abbrev( __( 'Saturday' ) ) );
	}

	/**
	 * @covers WP_Locale::get_month
	 */
	public function test_get_month() {
		$this->assertSame( (string) __( 'January' ), $this->locale->get_month( 1 ) );
		$this->assertSame( (string) __( 'February' ), $this->locale->get_month( 2 ) );
		$this->assertSame( (string) __( 'March' ), $this->locale->get_month( 3 ) );
		$this->assertSame( (string) __( 'April' ), $this->locale->get_month( 4 ) );
		$this->assertSame( (string) __( 'May' ), $this->locale->get_month( 5 ) );
		$this->assertSame( (string) __( 'June' ), $this->locale->get_month( 6 ) );
		$this->assertSame( (string) __( 'July' ), $this->locale->get_month( 7 ) );
		$this->assertSame( (string) __( 'August' ), $this->locale->get_month( 8 ) );
		$this->assertSame( (string) __( 'September' ), $this->locale->get_month( 9 ) );
		$this->assertSame( (string) __( 'October' ), $this->locale->get_month( 10 ) );
		$this->assertSame( (string) __( 'November' ), $this->locale->get_month( 11 ) );
		$this->assertSame( (string) __( 'December' ), $this->locale->get_month( 12 ) );
	}

	/**
	 * @covers WP_Locale::get_month
	 */
	public function test_get_month_leading_zero() {
		$this->assertSame( (string) __( 'January' ), $this->locale->get_month( '01' ) );
		$this->assertSame( (string) __( 'February' ), $this->locale->get_month( '02' ) );
		$this->assertSame( (string) __( 'March' ), $this->locale->get_month( '03' ) );
		$this->assertSame( (string) __( 'April' ), $this->locale->get_month( '04' ) );
		$this->assertSame( (string) __( 'May' ), $this->locale->get_month( '05' ) );
		$this->assertSame( (string) __( 'June' ), $this->locale->get_month( '06' ) );
		$this->assertSame( (string) __( 'July' ), $this->locale->get_month( '07' ) );
		$this->assertSame( (string) __( 'August' ), $this->locale->get_month( '08' ) );
		$this->assertSame( (string) __( 'September' ), $this->locale->get_month( '09' ) );
	}

	/**
	 * @covers WP_Locale::get_month_abbrev
	 */
	public function test_get_month_abbrev() {
		$this->assertSame( (string) __( 'Jan' ), $this->locale->get_month_abbrev( __( 'January' ) ) );
		$this->assertSame( (string) __( 'Feb' ), $this->locale->get_month_abbrev( __( 'February' ) ) );
		$this->assertSame( (string) __( 'Mar' ), $this->locale->get_month_abbrev( __( 'March' ) ) );
		$this->assertSame( (string) __( 'Apr' ), $this->locale->get_month_abbrev( __( 'April' ) ) );
		$this->assertSame( (string) __( 'May' ), $this->locale->get_month_abbrev( __( 'May' ) ) );
		$this->assertSame( (string) __( 'Jun' ), $this->locale->get_month_abbrev( __( 'June' ) ) );
		$this->assertSame( (string) __( 'Jul' ), $this->locale->get_month_abbrev( __( 'July' ) ) );
		$this->assertSame( (string) __( 'Aug' ), $this->locale->get_month_abbrev( __( 'August' ) ) );
		$this->assertSame( (string) __( 'Sep' ), $this->locale->get_month_abbrev( __( 'September' ) ) );
		$this->assertSame( (string) __( 'Oct' ), $this->locale->get_month_abbrev( __( 'October' ) ) );
		$this->assertSame( (string) __( 'Nov' ), $this->locale->get_month_abbrev( __( 'November' ) ) );
		$this->assertSame( (string) __( 'Dec' ), $this->locale->get_month_abbrev( __( 'December' ) ) );
	}

	/**
	 * @covers WP_Locale::get_meridiem
	 */
	public function test_get_meridiem() {
		$this->assertSame( (string) __( 'am' ), $this->locale->get_meridiem( 'am' ) );
		$this->assertSame( (string) __( 'AM' ), $this->locale->get_meridiem( 'AM' ) );
		$this->assertSame( (string) __( 'pm' ), $this->locale->get_meridiem( 'pm' ) );
		$this->assertSame( (string) __( 'PM' ), $this->locale->get_meridiem( 'PM' ) );
	}

	/**
	 * @covers WP_Locale::is_rtl
	 */
	public function test_is_rtl() {
		$this->assertFalse( $this->locale->is_rtl() );
		$this->locale->text_direction = 'foo';
		$this->assertFalse( $this->locale->is_rtl() );
		$this->locale->text_direction = 'rtl';
		$this->assertTrue( $this->locale->is_rtl() );
		$this->locale->text_direction = 'ltr';
		$this->assertFalse( $this->locale->is_rtl() );
	}

	/**
	 * Tests that `WP_Locale::get_word_count_type()` returns
	 * the appropriate value.
	 *
	 * @ticket 56698
	 *
	 * @covers WP_Locale::get_word_count_type
	 *
	 * @dataProvider data_get_word_count_type
	 *
	 * @param string $word_count_type The word count type.
	 * @param string $expected        The expected return value.
	 */
	public function test_get_word_count_type( $word_count_type, $expected ) {
		if ( is_string( $word_count_type ) ) {
			$this->locale->word_count_type = $word_count_type;

		}

		$this->assertSame( $expected, $this->locale->get_word_count_type() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_word_count_type() {
		return array(
			'default'                   => array(
				'word_count_type' => null,
				'expected'        => 'words',
			),
			'empty string'              => array(
				'word_count_type' => '',
				'expected'        => 'words',
			),
			'an invalid option - "foo"' => array(
				'word_count_type' => 'foo',
				'expected'        => 'words',
			),
			'a valid option - "words"'  => array(
				'word_count_type' => 'words',
				'expected'        => 'words',
			),
			'a valid option - "characters_excluding_spaces"' => array(
				'word_count_type' => 'characters_excluding_spaces',
				'expected'        => 'characters_excluding_spaces',
			),
			'a valid option - "characters_including_spaces"' => array(
				'word_count_type' => 'characters_including_spaces',
				'expected'        => 'characters_including_spaces',
			),
		);
	}
}

class Custom_WP_Locale extends WP_Locale {
	public function __construct() {
		// Do not initialize to test property initialization.
		// $this->init();
		$this->register_globals();
	}
}
