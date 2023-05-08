<?php

/**
 * @group l10n
 * @group i18n
 *
 * @covers ::wp_word_count
 */
class Tests_L10n_wpWordcount extends WP_UnitTestCase {
	protected static $settings;

	public static function wpSetUpBeforeClass() {
		self::$settings = array(
			'shortcodes' => array( 'shortcode' ),
		);
	}

	/**
	 * @ticket 57987
	 *
	 * @dataProvider data_get_string_variations
	 *
	 * @param string $text     Text to count elements in.
	 * @param int    $expected Expected word count.
	 */
	public function test_wp_word_count_should_return_the_number_of_words( $text, $expected ) {
		$this->assertSame( wp_word_count( $text, 'words', self::$settings ), $expected['words'] );
	}

	/**
	 * @ticket 57987
	 *
	 * @dataProvider data_get_string_variations
	 *
	 * @param string $text     Text to count elements in.
	 * @param int    $expected Expected character count.
	 */
	public function test_wp_word_count_should_return_the_number_of_characters_excluding_spaces( $text, $expected ) {
		$this->assertSame( wp_word_count( $text, 'characters_excluding_spaces', self::$settings ), $expected['characters_excluding_spaces'] );
	}

	/**
	 * @ticket 57987
	 *
	 * @dataProvider data_get_string_variations
	 *
	 * @param string $text     Text to count elements in.
	 * @param int    $expected Expected character count.
	 */
	public function test_wp_word_count_should_return_the_number_of_characters_including_spaces( $text, $expected ) {
		$this->assertSame( wp_word_count( $text, 'characters_including_spaces', self::$settings ), $expected['characters_including_spaces'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_string_variations() {
		return array(
			'text containing spaces'          => array(
				'text'     => 'one two three',
				'expected' => array(
					'words'                       => 3,
					'characters_excluding_spaces' => 11,
					'characters_including_spaces' => 13,
				),
			),
			'text containing HTML tags'       => array(
				'text'     => 'one <em class="test">two</em><br />three',
				'expected' => array(
					'words'                       => 3,
					'characters_excluding_spaces' => 11,
					'characters_including_spaces' => 12,
				),
			),
			'text containing line breaks'     => array(
				'text'     => "one\ntwo\nthree",
				'expected' => array(
					'words'                       => 3,
					'characters_excluding_spaces' => 11,
					'characters_including_spaces' => 11,
				),
			),
			'text containing encoded spaces'  => array(
				'text'     => 'one&nbsp;two&#160;three',
				'expected' => array(
					'words'                       => 3,
					'characters_excluding_spaces' => 11,
					'characters_including_spaces' => 13,
				),
			),
			'text containing punctuation'     => array(
				'text'     => "It's two three " . json_decode( '"\u2026"' ) . ' 4?',
				'expected' => array(
					'words'                       => 3,
					'characters_excluding_spaces' => 15,
					'characters_including_spaces' => 19,
				),
			),
			'text containing an em dash'      => array(
				'text'     => 'one' . json_decode( '"\u2014"' ) . 'two--three',
				'expected' => array(
					'words'                       => 3,
					'characters_excluding_spaces' => 14,
					'characters_including_spaces' => 14,
				),
			),
			'text containing shortcodes'      => array(
				'text'     => 'one [shortcode attribute="value"]two[/shortcode]three',
				'expected' => array(
					'words'                       => 3,
					'characters_excluding_spaces' => 11,
					'characters_including_spaces' => 12,
				),
			),
			'text containing astrals'         => array(
				'text'     => json_decode( '"\uD83D\uDCA9"' ),
				'expected' => array(
					'words'                       => 1,
					'characters_excluding_spaces' => 1,
					'characters_including_spaces' => 1,
				),
			),
			'text containing an HTML comment' => array(
				'text'     => 'one<!-- comment -->two three',
				'expected' => array(
					'words'                       => 2,
					'characters_excluding_spaces' => 11,
					'characters_including_spaces' => 12,
				),
			),
			'text containing an HTML entity'  => array(
				'text'     => '&gt; test',
				'expected' => array(
					'words'                       => 1,
					'characters_excluding_spaces' => 5,
					'characters_including_spaces' => 6,
				),
			),
			'empty text'                      => array(
				'text'     => '',
				'expected' => array(
					'words'                       => 0,
					'characters_excluding_spaces' => 0,
					'characters_including_spaces' => 0,
				),
			),
			'text containing only whitespace' => array(
				'text'     => "\t\r\n ",
				'expected' => array(
					'words'                       => 0,
					'characters_excluding_spaces' => 0,
					'characters_including_spaces' => 0,
				),
			),
		);
	}
}
