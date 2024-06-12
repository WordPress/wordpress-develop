<?php

/**
 * Tests for the wp_check_invalid_utf8 function.
 *
 * @group formatting
 *
 * @covers ::wp_check_invalid_utf8
 */
class Tests_formatting_wp_check_invalid_utf8 extends WP_UnitTestCase{

    /**
     * @ticket 29717
     *
     * @param string $string
     * @param bool $force
     * @param string $expected
     *
     * @dataProvider data_wp_check_invalid_utf8
     */
    public function test_wp_check_invalid_utf8( $string, $force, $expected ) {

        $this->assertSame( $expected, wp_check_invalid_utf8( $string, $force ) );
    }

    public function data_wp_check_invalid_utf8() {
         // Add the string to check in utf8 here
         // Syntax would be: ['string to check', 'expected result']
         // For example: return [['test string', 'expected result'], [...another test case...]];
         return array(
            'plain_string' => array( 'string to check in utf8', false, 'string to check in utf8' ),
            'invalid_string' => array( "\xfc\xa1\xa1\xa1\xa1\xa1", false, "" ),
            'invalid_string2' => array( "\xfc\xa1\xa1\xa1\xa1\xa1", true, "\xfc\xa1\xa1\xa1\xa1\xa1" ),
         );
    }

	/**
	 * @ticket 29717
	 *
	 * @param string $string
	 * @param bool $force
	 * @param string $expected_message
	 *
	 * @dataProvider data_wp_check_invalid_utf8_errors
	 */
	public function test_wp_check_invalid_utf8_error( $string, $force, $expected_message ) {
		$this->expectError();
		$this->expectErrorMessage( $expected_message );
		wp_check_invalid_utf8( $string, $force );
	}

	public function data_wp_check_invalid_utf8_errors() {
		return array(
			'incomplete multibyte' => array( "\xF0\xA4\xAD\xA2"."\xF0\xA4\xAD\xA2"."\xF0\xA4\xAD", true, "iconv(): Detected an incomplete multibyte character in input string" ),
		);
	}
}
