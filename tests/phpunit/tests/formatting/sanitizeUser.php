<?php

/**
 * @group formatting
 *
 * @covers ::sanitize_user
 */
class Tests_Formatting_SanitizeUser extends WP_UnitTestCase {
	public function test_strips_html() {
		$input    = 'Captain <strong>Awesome</strong>';
		$expected = is_multisite() ? 'captain awesome' : 'Captain Awesome';
		$this->assertSame( $expected, sanitize_user( $input ) );
	}

	public function test_strips_encoded_ampersand() {
		$expected = 'ATT';

		// Multisite forces user logins to lowercase.
		if ( is_multisite() ) {
			$expected = strtolower( $expected );
		}

		$this->assertSame( $expected, sanitize_user( 'AT&amp;T' ) );
	}

	public function test_strips_encoded_ampersand_when_followed_by_semicolon() {
		$expected = 'ATT Test;';

		// Multisite forces user logins to lowercase.
		if ( is_multisite() ) {
			$expected = strtolower( $expected );
		}

		$this->assertSame( $expected, sanitize_user( 'AT&amp;T Test;' ) );
	}

	public function test_strips_percent_encoded_octets() {
		$expected = is_multisite() ? 'françois' : 'François';
		$this->assertSame( $expected, sanitize_user( 'Fran%c3%a7ois' ) );
	}
	public function test_optional_strict_mode_reduces_to_safe_ascii_subset() {
		$this->assertSame( 'abc', sanitize_user( '()~ab~ˆcˆ!', true ) );
	}

	public function test_accepts_all_arabic() {
		$expected = 'آرنت';
		$encoded = '%D8%A2%D8%B1%D9%86%D8%AA';

		$this->assertSame( $expected, sanitize_user( $expected ) );
		$this->assertSame( $expected, sanitize_user( $encoded ) );
	}

	public function test_accepts_west_african_latin() {
		$expected = 'tɔnatɔn';
		$encoded = 't%C9%94nat%C9%94n';

		$this->assertSame( $expected, sanitize_user( $expected ) );
		$this->assertSame( $expected, sanitize_user( $encoded ) );
	}

	public function test_blocks_latin_cyrillic_mixed_name() {
		$this->assertSame( "arn", sanitize_user( 'arn%D1%82' ) );
	}
}
