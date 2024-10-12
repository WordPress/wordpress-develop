<?php

/**
 * Tests for the wp_check_invalid_utf8 function.
 *
 * @group formatting
 *
 * @covers ::wp_check_invalid_utf8
 */
class Tests_formatting_wp_check_invalid_utf8 extends WP_UnitTestCase {

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
            'invalid_string2' => array( "\xfc\xa1\xa1\xa1\xa1\xa1 utf8", true, " utf8" ),
            'incomplete multibyte' =>array( "\xF0\xA4\xAD\xA2"."\xF0\xA4\xAD\xA2"."\xF0\xA4\xAD", true, '𤭢𤭢')
	         );
    }

	/**
	 * @ticket 29717
	 *
	 * @param string $string
	 * @param bool $force
	 * @param string $expected_message
	 *
	 * @dataProvider data_wp_check_invalid_utf8_with_bytewise
	 */
	public function test_wp_check_invalid_utf8_with_bytewise( $string, $force, $bytewise_fallback, $bytewise_always, $expected_message ) {

		$this->assertSame( $expected_message,  wp_check_invalid_utf8( $string, $force, $bytewise_fallback, $bytewise_always  ));
	}

	public function data_wp_check_invalid_utf8_with_bytewise() {
		return array(
			'incomplete_multibyte' => array( "\xF0\xA4\xAD\xA2"."\xF0\xA4\xAD\xA2"."\xF0\xA4\xAD", true, true,  false, "𤭢𤭢" ),
			'incomplete_multibyte_true' => array( "\xF0\xA4\xAD\xA2"."\xF0\xA4\xAD\xA2"."\xF0\xA4\xAD", false, true,  true, "" ),
		);
	}
}
