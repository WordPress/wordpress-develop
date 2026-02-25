<?php
/**
 * Tests for the sanitize_user() function.
 *
 * @group formatting
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
		if ( ! function_exists( 'mb_str_split' ) ) {
			$this->markTestSkipped( 'PHP 7.2/3 lacks mb_str_split' );
		}
		$expected = 'ATT Test;';

		// Multisite forces user logins to lowercase.
		if ( is_multisite() ) {
			$expected = strtolower( $expected );
		}

		$this->assertSame( $expected, sanitize_user( 'AT&amp;T Test;' ) );
	}

	/**
	 * Some languages use the Latin alphabet with various accents.
	 * The city Münster is in Germany, Orléans is in France. This
	 * test checks that an author (a user name) can use an accent.
	 *
	 * @ticket 31992
	 */
	public function test_strips_percent_encoded_octets() {
		if ( ! function_exists( 'mb_str_split' ) ) {
			$this->markTestSkipped( 'PHP 7.2/3 lacks mb_str_split' );
		}
		$expected = is_multisite() ? 'françois' : 'François';
		$this->assertSame( $expected, sanitize_user( 'Fran%c3%a7ois' ) );
	}

	public function test_optional_strict_mode_reduces_to_safe_ascii_subset() {
		$this->assertSame( 'abc', sanitize_user( '()~ab~ˆcˆ!', true ) );
	}

	/**
	 * Arabic script is used in various languages, including
	 * Arabic and Persian. This test checks that an author
	 * (a user name) can use such letters.
	 *
	 * @ticket 31992
	 */
	public function test_accepts_all_arabic() {
		if ( ! function_exists( 'mb_str_split' ) ) {
			$this->markTestSkipped( 'PHP 7.2/3 lacks mb_str_split' );
		}
		$expected = 'آرنت';
		$encoded  = '%D8%A2%D8%B1%D9%86%D8%AA';

		$this->assertSame( $expected, sanitize_user( $expected ) );
		$this->assertSame( $expected, sanitize_user( $encoded ) );
	}

	/**
	 * Some languages use the Latin alphabet with various
	 * extra letters. The city Bodø is in Norway, Gießen in
	 * Germany. This test checks that an author (a user name) can
	 * use such an extended Latin letter. (The letter used, ɔ, is
	 * like the o in top, and used in various countries in West
	 * Africa.)
	 *
	 * @ticket 31992
	 */
	public function test_accepts_west_african_latin() {
		if ( ! function_exists( 'mb_str_split' ) ) {
			$this->markTestSkipped( 'PHP 7.2/3 lacks mb_str_split' );
		}
		$expected = 'tɔnatɔn';
		$encoded  = 't%C9%94nat%C9%94n';

		$this->assertSame( $expected, sanitize_user( $expected ) );
		$this->assertSame( $expected, sanitize_user( $encoded ) );
	}

	/**
	 * If the database doesn't use UTF8, WP will add a filter to
	 * sanitize_users to prevent creation/use of non-ASCII user
	 * names. This test tests that that filter works.
	 *
	 * @ticket 31992
	 */
	public function test_reduction_to_ascii_for_non_utf8_databases() {
		add_filter( 'sanitize_user', 'wp_ascii_without_controls' );
		$this->assertSame( 'tnatn', sanitize_user( 'tɔnatɔn' ) );
	}
}
