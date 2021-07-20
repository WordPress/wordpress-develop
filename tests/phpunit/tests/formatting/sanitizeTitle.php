<?php

/**
 * @covers ::sanitize_title
 * @group formatting
 */
class Tests_Formatting_SanitizeTitle extends WP_UnitTestCase {
	function test_strips_html() {
		$input    = 'Captain <strong>Awesome</strong>';
		$expected = 'captain-awesome';
		$this->assertSame( $expected, sanitize_title( $input ) );
	}

	function test_titles_sanitized_to_nothing_are_replaced_with_optional_fallback() {
		$input    = '<strong></strong>';
		$fallback = 'Captain Awesome';
		$this->assertSame( $fallback, sanitize_title( $input, $fallback ) );
	}

	/**
	 * @ticket 47594
	 * @dataProvider data_unicode_space_characters
	 */
	function test_unicode_space_characters( $char ) {
		$title    = "Handle{$char}space{$char}character";
		$expected = 'handle-space-character';
		$this->assertSame( $expected, sanitize_title( $title ) );
	}

	public function data_unicode_space_characters() {
		return array(
			array( '&nbsp;' ),
			array( '%e2%80%af' ),
			array( '%e2%80%87' ),
			array( '%e2%81%a0' ),
			array( '&#x2007;' ),
			array( '&#8199;' ),
			array( '&#x202F;' ),
			array( '&#8239;' ),
			array( '&#x2060;' ),
			array( '&#8288;' ),
		);
	}
}
