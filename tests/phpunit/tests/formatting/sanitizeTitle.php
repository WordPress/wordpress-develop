<?php

use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Tests for the sanitize_title() function.
 *
 * @group formatting
 *
 * @covers ::sanitize_title
 */
class Tests_Formatting_SanitizeTitle extends WP_UnitTestCase {
	public function test_strips_html() {
		$input    = 'Captain <strong>Awesome</strong>';
		$expected = 'captain-awesome';
		$this->assertSame( $expected, sanitize_title( $input ) );
	}

	/**
	 * Tests for the sanitize_title() function leaving Devanagari characters intact.
	 *
	 * @ticket 31992
	 */
	public function test_leaves_devanagari() {
		$input = 'राजीव';
		$this->assertSame( $input, urldecode( sanitize_title( $input ) ) );
	}

	public function test_titles_sanitized_to_nothing_are_replaced_with_optional_fallback() {
		$input    = '<strong></strong>';
		$fallback = 'Captain Awesome';
		$this->assertSame( $fallback, sanitize_title( $input, $fallback ) );
	}
}
