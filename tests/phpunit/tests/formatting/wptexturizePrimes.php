<?php

/**
 * Tests for the `wptexturize_primes()` function.
 *
 * @group formatting
 *
 * @covers ::wptexturize_primes
 */
class Tests_Formatting_WptexturizePrimes extends WP_UnitTestCase {

	/**
	 * Tests prime replacement for single quotes after numbers.
	 *
	 * @ticket 65819
	 */
	public function test_wptexturize_primes_single_quote() {
		$prime       = '&#8242;';
		$open_quote  = '&#8216;';
		$close_quote = '&#8217;';

		$input    = "He is 6' tall.";
		$expected = "He is 6{$prime} tall.";

		$this->assertSame( $expected, wptexturize_primes( $input, "'", $prime, $open_quote, $close_quote ) );
	}

	/**
	 * Tests double prime replacement for double quotes after numbers.
	 *
	 * @ticket 65819
	 */
	public function test_wptexturize_primes_double_quote() {
		$prime       = '&#8243;';
		$open_quote  = '&#8220;';
		$close_quote = '&#8221;';

		$input    = 'A 12" record.';
		$expected = "A 12{$prime} record.";

		$this->assertSame( $expected, wptexturize_primes( $input, '"', $prime, $open_quote, $close_quote ) );
	}

	/**
	 * Tests that text without the needle character is returned unchanged.
	 *
	 * @ticket 65819
	 */
	public function test_wptexturize_primes_without_matching_needle() {
		$text = 'Text without any quotes at all.';

		$this->assertSame( $text, wptexturize_primes( $text, "'", '&#8242;', '&#8216;', '&#8217;' ) );
	}

	/**
	 * Tests closing quote disambiguation when numbers with primes are inside quotations.
	 *
	 * @ticket 65819
	 */
	public function test_wptexturize_primes_inside_quotations() {
		$prime       = '&#8242;';
		$open_quote  = '&#8216;';
		$close_quote = '&#8217;';

		$input    = "{$open_quote}I am 6' tall.'";
		$expected = "{$open_quote}I am 6{$prime} tall.{$close_quote}";

		$this->assertSame( $expected, wptexturize_primes( $input, "'", $prime, $open_quote, $close_quote ) );
	}

	/**
	 * Tests that quotes not preceded by numbers become closing quotes.
	 *
	 * @ticket 65819
	 */
	public function test_wptexturize_primes_normal_quotes_become_closing_quotes() {
		$prime       = '&#8243;';
		$open_quote  = '&#8220;';
		$close_quote = '&#8221;';

		$input    = 'A "quoted word".';
		$expected = "A {$close_quote}quoted word{$close_quote}.";

		$this->assertSame( $expected, wptexturize_primes( $input, '"', $prime, $open_quote, $close_quote ) );
	}
}
