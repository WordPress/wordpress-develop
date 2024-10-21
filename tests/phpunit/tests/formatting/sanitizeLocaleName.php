<?php

/**
 * @group formatting
 *
 * @covers ::sanitize_locale_name
 */
class Tests_Formatting_SanitizeLocaleName extends WP_UnitTestCase {
	/**
	 * @dataProvider data_sanitize_locale_name_returns_non_empty_string
	 */
	public function test_sanitize_locale_name_returns_non_empty_string( $expected, $input ) {
		$this->assertSame( $expected, sanitize_locale_name( $input ) );
	}

	public function data_sanitize_locale_name_returns_non_empty_string() {
		return array(
			// array( expected, input )
			array( 'en_US', 'en_US' ),
			array( 'en', 'en' ),
			array( 'fr_FR', 'fr_FR' ),
			array( 'fr_FR', 'fr_FR' ),
			array( 'fr_FR-e2791ba830489d23043be8650a22a22b', 'fr_FR-e2791ba830489d23043be8650a22a22b' ),
			array( '-fr_FRmo', '-fr_FR.mo' ),
			array( '12324', '$12324' ),
			array( '4124FRRa', '/4124$$$%%FRRa' ),
			array( 'FR', '<FR' ),
			array( 'FR_FR', 'FR_FR' ),
			array( '--__', '--__' ),
		);
	}

	/**
	 * @dataProvider data_sanitize_locale_name_returns_empty_string
	 */
	public function test_sanitize_locale_name_returns_empty_string( $input ) {
		$this->assertSame( '', sanitize_locale_name( $input ) );
	}

	public function data_sanitize_locale_name_returns_empty_string() {
		return array(
			// array( input )
			array( '$<>' ),
			array( '/$$$%%\\)' ),
			array( '....' ),
			array( '@///' ),
		);
	}
}
