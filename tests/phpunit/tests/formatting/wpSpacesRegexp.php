<?php

/**
 * Tests for the `wp_spaces_regexp()` function.
 *
 * @group formatting
 *
 * @covers ::wp_spaces_regexp
 */
class Tests_Formatting_WpSpacesRegexp extends WP_UnitTestCase {

	/**
	 * Tests that wp_spaces_regexp() returns the expected regex pattern.
	 *
	 * @ticket 65819
	 */
	public function test_wp_spaces_regexp_pattern() {
		$this->assertSame( '[\r\n\t ]|\xC2\xA0|&nbsp;', wp_spaces_regexp() );
	}

	/**
	 * Tests that the pattern returned by wp_spaces_regexp() matches whitespace characters.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_whitespace_characters
	 *
	 * @param string $char Character or entity to test.
	 */
	public function test_wp_spaces_regexp_matches_whitespace( $char ) {
		$pattern = '/' . wp_spaces_regexp() . '/';

		$this->assertSame( 1, preg_match( $pattern, $char ) );
	}

	/**
	 * Data provider for test_wp_spaces_regexp_matches_whitespace().
	 *
	 * @return array[]
	 */
	public static function data_whitespace_characters() {
		return array(
			'space'             => array( ' ' ),
			'tab'               => array( "\t" ),
			'line feed'         => array( "\n" ),
			'carriage return'   => array( "\r" ),
			'UTF-8 nbsp'        => array( "\xC2\xA0" ),
			'named HTML &nbsp;' => array( '&nbsp;' ),
		);
	}

	/**
	 * Tests that the pattern does not match non-whitespace characters.
	 *
	 * @ticket 65819
	 */
	public function test_wp_spaces_regexp_does_not_match_non_whitespace() {
		$pattern = '/' . wp_spaces_regexp() . '/';

		$this->assertSame( 0, preg_match( $pattern, 'abc' ) );
	}
}
