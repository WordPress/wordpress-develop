<?php

/**
 * @group formatting
 *
 * @covers ::sanitize_title_with_dashes
 */
class Tests_Formatting_SanitizeTitleWithDashes extends WP_UnitTestCase {
	public function test_strips_html() {
		$input    = 'Captain <strong>Awesome</strong>';
		$expected = 'captain-awesome';
		$this->assertSame( $expected, sanitize_title_with_dashes( $input ) );
	}

	public function test_strips_unencoded_percent_signs() {
		$this->assertSame( 'fran%c3%a7ois', sanitize_title_with_dashes( 'fran%c3%a7%ois' ) );
	}

	public function test_makes_title_lowercase() {
		$this->assertSame( 'abc', sanitize_title_with_dashes( 'ABC' ) );
	}

	public function test_replaces_any_amount_of_whitespace_with_one_hyphen() {
		$this->assertSame( 'a-t', sanitize_title_with_dashes( 'a          t' ) );
		$this->assertSame( 'a-t', sanitize_title_with_dashes( "a    \n\n\nt" ) );
	}

	public function test_replaces_any_number_of_hyphens_with_one_hyphen() {
		$this->assertSame( 'a-t-t', sanitize_title_with_dashes( 'a----t----t' ) );
	}

	public function test_trims_trailing_hyphens() {
		$this->assertSame( 'a-t-t', sanitize_title_with_dashes( 'a----t----t----' ) );
	}

	public function test_handles_non_entity_ampersands() {
		$this->assertSame( 'penn-teller-bull', sanitize_title_with_dashes( 'penn & teller bull' ) );
	}

	public function test_strips_nbsp_ndash_and_amp() {
		$this->assertSame( 'no-entities-here', sanitize_title_with_dashes( 'No &nbsp; Entities &ndash; Here &amp;' ) );
	}

	public function test_strips_encoded_ampersand() {
		$this->assertSame( 'one-two', sanitize_title_with_dashes( 'One &amp; Two', '', 'save' ) );
	}

	public function test_strips_url_encoded_ampersand() {
		$this->assertSame( 'one-two', sanitize_title_with_dashes( 'One &#123; Two;', '', 'save' ) );
	}

	public function test_strips_trademark_symbol() {
		$this->assertSame( 'one-two', sanitize_title_with_dashes( 'One Two™;', '', 'save' ) );
	}

	public function test_strips_unencoded_ampersand_followed_by_encoded_ampersand() {
		$this->assertSame( 'one-two', sanitize_title_with_dashes( 'One &&amp; Two;', '', 'save' ) );
	}

	public function test_strips_unencoded_ampersand_when_not_surrounded_by_spaces() {
		$this->assertSame( 'onetwo', sanitize_title_with_dashes( 'One&Two', '', 'save' ) );
	}

	public function test_replaces_nbsp() {
		$this->assertSame( 'dont-break-the-space', sanitize_title_with_dashes( "don't break the space", '', 'save' ) );
	}

	/**
	 * @ticket 31790
	 */
	public function test_replaces_nbsp_entities() {
		$this->assertSame( 'dont-break-the-space', sanitize_title_with_dashes( "don't&nbsp;break&#160;the&nbsp;space", '', 'save' ) );
	}

	public function test_replaces_ndash_mdash() {
		$this->assertSame( 'do-the-dash', sanitize_title_with_dashes( 'Do – the Dash', '', 'save' ) );
		$this->assertSame( 'do-the-dash', sanitize_title_with_dashes( 'Do the — Dash', '', 'save' ) );
	}

	/**
	 * @ticket 31790
	 */
	public function test_replaces_ndash_mdash_entities() {
		$this->assertSame( 'do-the-dash', sanitize_title_with_dashes( 'Do &ndash; the &#8211; Dash', '', 'save' ) );
		$this->assertSame( 'do-the-dash', sanitize_title_with_dashes( 'Do &mdash; the &#8212; Dash', '', 'save' ) );
	}

	public function test_replaces_iexcel_iquest() {
		$this->assertSame( 'just-a-slug', sanitize_title_with_dashes( 'Just ¡a Slug', '', 'save' ) );
		$this->assertSame( 'just-a-slug', sanitize_title_with_dashes( 'Just a Slug¿', '', 'save' ) );
	}

	public function test_replaces_angle_quotes() {
		$this->assertSame( 'just-a-slug', sanitize_title_with_dashes( '‹Just a Slug›', '', 'save' ) );
		$this->assertSame( 'just-a-slug', sanitize_title_with_dashes( '«Just a Slug»', '', 'save' ) );
	}

	public function test_replaces_curly_quotes() {
		$this->assertSame( 'hey-its-curly-joe', sanitize_title_with_dashes( 'Hey its “Curly Joe”', '', 'save' ) );
		$this->assertSame( 'hey-its-curly-joe', sanitize_title_with_dashes( 'Hey its ‘Curly Joe’', '', 'save' ) );
		$this->assertSame( 'hey-its-curly-joe', sanitize_title_with_dashes( 'Hey its „Curly Joe“', '', 'save' ) );
		$this->assertSame( 'hey-its-curly-joe', sanitize_title_with_dashes( 'Hey its ‚Curly Joe‛', '', 'save' ) );
		$this->assertSame( 'hey-its-curly-joe', sanitize_title_with_dashes( 'Hey its „Curly Joe‟', '', 'save' ) );
	}

	/**
	 * @ticket 49791
	 */
	public function test_replaces_bullet() {
		$this->assertSame( 'fancy-title-amazing', sanitize_title_with_dashes( 'Fancy Title • Amazing', '', 'save' ) );
	}

	public function test_replaces_copy_reg_deg_trade() {
		$this->assertSame( 'just-a-slug', sanitize_title_with_dashes( 'Just © a Slug', '', 'save' ) );
		$this->assertSame( 'just-a-slug', sanitize_title_with_dashes( '® Just a Slug', '', 'save' ) );
		$this->assertSame( 'just-a-slug', sanitize_title_with_dashes( 'Just a ° Slug', '', 'save' ) );
		$this->assertSame( 'just-a-slug', sanitize_title_with_dashes( 'Just ™ a Slug', '', 'save' ) );
	}

	/**
	 * @ticket 10792
	 */
	public function test_replaces_forward_slash() {
		$this->assertSame( 'songs-by-lennon-mccartney', sanitize_title_with_dashes( 'songs by Lennon/McCartney', '', 'save' ) );
		$this->assertSame( 'songs-by-lennon-mccartney', sanitize_title_with_dashes( 'songs by Lennon//McCartney', '', 'save' ) );
		$this->assertSame( 'songs-by-lennon-mccartney', sanitize_title_with_dashes( 'songs by Lennon///McCartney', '', 'save' ) );
		$this->assertSame( 'songs-by-lennon-mccartney', sanitize_title_with_dashes( 'songs by Lennon/-McCartney', '', 'save' ) );
		$this->assertSame( 'songs-by-lennon-mccartney', sanitize_title_with_dashes( '//songs by Lennon/McCartney', '', 'save' ) );
	}

	/**
	 * @ticket 19820
	 */
	public function test_replaces_multiply_sign() {
		$this->assertSame( '6x7-is-42', sanitize_title_with_dashes( '6×7 is 42', '', 'save' ) );
	}

	/**
	 * @ticket 20772
	 */
	public function test_replaces_standalone_diacritic() {
		$this->assertSame( 'aaaa', sanitize_title_with_dashes( 'āáǎà', '', 'save' ) );
	}

	/**
	 * @ticket 22395
	 */
	public function test_replaces_acute_accents() {
		$this->assertSame( 'aaaa', sanitize_title_with_dashes( 'ááa´aˊ', '', 'save' ) );
	}

	/**
	 * @ticket 47912
	 * @dataProvider data_removes_non_visible_characters_without_width
	 *
	 * @param string $title     The title to be sanitized.
	 * @param string $expected  Expected sanitized title.
	 */
	public function test_removes_non_visible_characters_without_width( $title, $expected = '' ) {
		$this->assertSame( $expected, sanitize_title_with_dashes( $title, '', 'save' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_removes_non_visible_characters_without_width() {
		return array(
			// Only the non-visible characters.
			'only %e2%80%8b'     => array( '%e2%80%8b' ),
			'only %e2%80%8c'     => array( '%e2%80%8c' ),
			'only %e2%80%8d'     => array( '%e2%80%8d' ),
			'only %e2%80%8e'     => array( '%e2%80%8e' ),
			'only %e2%80%8f'     => array( '%e2%80%8f' ),
			'only %e2%80%aa'     => array( '%e2%80%aa' ),
			'only %e2%80%ab'     => array( '%e2%80%ab' ),
			'only %e2%80%ac'     => array( '%e2%80%ac' ),
			'only %e2%80%ad'     => array( '%e2%80%ad' ),
			'only %e2%80%ae'     => array( '%e2%80%ae' ),
			'only %ef%bb%bf'     => array( '%ef%bb%bf' ),

			// Non-visible characters within the title.
			'in middle of title' => array(
				'title'    => 'Nonvisible %ef%bb%bfin middle of title',
				'expected' => 'nonvisible-in-middle-of-title',
			),
			'at start of title'  => array(
				'title'    => '%e2%80%8bNonvisible at start of title',
				'expected' => 'nonvisible-at-start-of-title',
			),
			'at end of title'    => array(
				'title'    => 'Nonvisible at end of title %e2%80%8b',
				'expected' => 'nonvisible-at-end-of-title',
			),
			'randomly in title'  => array(
				'title'    => 'Nonvisible%ef%bb%bf %e2%80%aerandomly %e2%80%8ein the %e2%80%8e title%e2%80%8e',
				'expected' => 'nonvisible-randomly-in-the-title',
			),
		);
	}

	/**
	 * @ticket 47912
	 * @dataProvider data_non_visible_characters_without_width_when_not_save
	 *
	 * @param string $title     The title to be sanitized.
	 * @param string $expected  Expected sanitized title.
	 */
	public function test_non_visible_characters_without_width_when_not_save( $title, $expected = '' ) {
		$this->assertSame( $expected, sanitize_title_with_dashes( $title ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_non_visible_characters_without_width_when_not_save() {
		return array(
			// Just the non-visible characters.
			'only %e2%80%8b'     => array( '%e2%80%8b', '%e2%80%8b' ),
			'only %e2%80%8c'     => array( '%e2%80%8c', '%e2%80%8c' ),
			'only %e2%80%8d'     => array( '%e2%80%8d', '%e2%80%8d' ),
			'only %e2%80%8e'     => array( '%e2%80%8e', '%e2%80%8e' ),
			'only %e2%80%8f'     => array( '%e2%80%8f', '%e2%80%8f' ),
			'only %e2%80%aa'     => array( '%e2%80%aa', '%e2%80%aa' ),
			'only %e2%80%ab'     => array( '%e2%80%ab', '%e2%80%ab' ),
			'only %e2%80%ac'     => array( '%e2%80%ac', '%e2%80%ac' ),
			'only %e2%80%ad'     => array( '%e2%80%ad', '%e2%80%ad' ),
			'only %e2%80%ae'     => array( '%e2%80%ae', '%e2%80%ae' ),
			'only %ef%bb%bf'     => array( '%ef%bb%bf', '%ef%bb%bf' ),

			// Non-visible characters within the title.
			'in middle of title' => array(
				'title'    => 'Nonvisible %ef%bb%bfin middle of title',
				'expected' => 'nonvisible-%ef%bb%bfin-middle-of-title',
			),
			'at start of title'  => array(
				'title'    => '%e2%80%8bNonvisible at start of title',
				'expected' => '%e2%80%8bnonvisible-at-start-of-title',
			),
			'at end of title'    => array(
				'title'    => 'Nonvisible at end of title %e2%80%8b',
				'expected' => 'nonvisible-at-end-of-title-%e2%80%8b',
			),
			'randomly in title'  => array(
				'title'    => 'Nonvisible%ef%bb%bf %e2%80%aerandomly %e2%80%8ein the %e2%80%8e title%e2%80%8e',
				'expected' => 'nonvisible%ef%bb%bf-%e2%80%aerandomly-%e2%80%8ein-the-%e2%80%8e-title%e2%80%8e',
			),
		);
	}

	/**
	 * @ticket 47912
	 * @dataProvider data_converts_non_visible_characters_with_width_to_hyphen
	 *
	 * @param string $title     The title to be sanitized.
	 * @param string $expected  Expected sanitized title.
	 */
	public function test_converts_non_visible_characters_with_width_to_hyphen( $title, $expected = '' ) {
		$this->assertSame( $expected, sanitize_title_with_dashes( $title, '', 'save' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_converts_non_visible_characters_with_width_to_hyphen() {
		return array(
			// Only the non-visible characters.
			'only %e2%80%80'     => array( '%e2%80%80' ),
			'only %e2%80%81'     => array( '%e2%80%81' ),
			'only %e2%80%82'     => array( '%e2%80%82' ),
			'only %e2%80%83'     => array( '%e2%80%83' ),
			'only %e2%80%84'     => array( '%e2%80%84' ),
			'only %e2%80%85'     => array( '%e2%80%85' ),
			'only %e2%80%86'     => array( '%e2%80%86' ),
			'only %e2%80%87'     => array( '%e2%80%87' ),
			'only %e2%80%88'     => array( '%e2%80%88' ),
			'only %e2%80%89'     => array( '%e2%80%89' ),
			'only %e2%80%8a'     => array( '%e2%80%8a' ),
			'only %e2%80%a8'     => array( '%e2%80%a8' ),
			'only %e2%80%a9'     => array( '%e2%80%a9' ),
			'only %e2%80%af'     => array( '%e2%80%af' ),

			// Non-visible characters within the title.
			'in middle of title' => array(
				'title'    => 'Nonvisible %e2%80%82 in middle of title',
				'expected' => 'nonvisible-in-middle-of-title',
			),
			'at start of title'  => array(
				'title'    => '%e2%80%83Nonvisible at start of title',
				'expected' => 'nonvisible-at-start-of-title',
			),
			'at end of title'    => array(
				'title'    => 'Nonvisible at end of title %e2%80%81',
				'expected' => 'nonvisible-at-end-of-title',
			),
			'two end of title'   => array(
				'title'    => 'Nonvisible at end of title %e2%80%81 %e2%80%af',
				'expected' => 'nonvisible-at-end-of-title',
			),
			'randomly in title'  => array(
				'title'    => 'Nonvisible%e2%80%80 %e2%80%a9randomly %e2%80%87in the %e2%80%a8 title%e2%80%af',
				'expected' => 'nonvisible-randomly-in-the-title',
			),
		);
	}

	/**
	 * @ticket 47912
	 * @dataProvider data_non_visible_characters_with_width_to_hyphen_when_not_save
	 *
	 * @param string $title     The title to be sanitized.
	 * @param string $expected  Expected sanitized title.
	 */
	public function test_non_visible_characters_with_width_to_hyphen_when_not_save( $title, $expected = '' ) {
		$this->assertSame( $expected, sanitize_title_with_dashes( $title ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_non_visible_characters_with_width_to_hyphen_when_not_save() {
		return array(
			// Just the non-visible characters.
			'only %e2%80%8b'     => array( '%e2%80%8b', '%e2%80%8b' ),
			'only %e2%80%8c'     => array( '%e2%80%8c', '%e2%80%8c' ),
			'only %e2%80%8d'     => array( '%e2%80%8d', '%e2%80%8d' ),
			'only %e2%80%8e'     => array( '%e2%80%8e', '%e2%80%8e' ),
			'only %e2%80%8f'     => array( '%e2%80%8f', '%e2%80%8f' ),
			'only %e2%80%aa'     => array( '%e2%80%aa', '%e2%80%aa' ),
			'only %e2%80%ab'     => array( '%e2%80%ab', '%e2%80%ab' ),
			'only %e2%80%ac'     => array( '%e2%80%ac', '%e2%80%ac' ),
			'only %e2%80%ad'     => array( '%e2%80%ad', '%e2%80%ad' ),
			'only %e2%80%ae'     => array( '%e2%80%ae', '%e2%80%ae' ),
			'only %ef%bb%bf'     => array( '%ef%bb%bf', '%ef%bb%bf' ),

			// Non-visible characters within the title.
			'in middle of title' => array(
				'title'    => 'Nonvisible %e2%80%82 in middle of title',
				'expected' => 'nonvisible-%e2%80%82-in-middle-of-title',
			),
			'at start of title'  => array(
				'title'    => '%e2%80%83Nonvisible at start of title',
				'expected' => '%e2%80%83nonvisible-at-start-of-title',
			),
			'at end of title'    => array(
				'title'    => 'Nonvisible at end of title %e2%80%81',
				'expected' => 'nonvisible-at-end-of-title-%e2%80%81',
			),
			'randomly in title'  => array(
				'title'    => 'Nonvisible%e2%80%80 %e2%80%aerandomly %e2%80%87in the %e2%80%a8 title%e2%80%af',
				'expected' => 'nonvisible%e2%80%80-%e2%80%aerandomly-%e2%80%87in-the-%e2%80%a8-title%e2%80%af',
			),
		);
	}
}
