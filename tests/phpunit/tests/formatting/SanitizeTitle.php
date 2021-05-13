<?php

/**
 * @group formatting
 */
class Tests_Formatting_SanitizeTitle extends WP_UnitTestCase {

	/**
	 * @covers ::sanitize_title
	 */
	function test_strips_html() {
		$input    = 'Captain <strong>Awesome</strong>';
		$expected = 'captain-awesome';
		$this->assertSame( $expected, sanitize_title( $input ) );
	}

	/**
	 * @covers ::sanitize_title
	 */
	function test_titles_sanitized_to_nothing_are_replaced_with_optional_fallback() {
		$input    = '<strong></strong>';
		$fallback = 'Captain Awesome';
		$this->assertSame( $fallback, sanitize_title( $input, $fallback ) );
	}
}
