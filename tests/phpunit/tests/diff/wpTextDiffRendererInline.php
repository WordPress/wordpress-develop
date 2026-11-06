<?php

/**
 * Tests for WP_Text_Diff_Renderer_inline::_splitOnWords().
 *
 * @group diff
 *
 * @covers WP_Text_Diff_Renderer_inline::_splitOnWords
 */
class Tests_Diff_WpTextDiffRendererInline extends WP_UnitTestCase {

	/**
	 * @var WP_Text_Diff_Renderer_inline
	 */
	private $renderer;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once ABSPATH . WPINC . '/wp-diff.php';
	}

	public function set_up() {
		parent::set_up();
		$this->renderer = new WP_Text_Diff_Renderer_inline();
	}

	/**
	 * Tests that a string is split on the characters that are not part of a word.
	 *
	 * @ticket 66513
	 *
	 * @dataProvider data_strings_to_split
	 *
	 * @param string   $text     Text to split.
	 * @param string[] $expected Expected words and separators.
	 */
	public function test_should_split_a_string_into_words_and_separators( $text, $expected ) {
		$this->assertSame( $expected, $this->renderer->_splitOnWords( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_strings_to_split() {
		return array(
			'empty string'                => array( '', array( '' ) ),
			'a single word'               => array( 'word', array( 'word' ) ),
			'two words'                   => array( 'hello world', array( 'hello', ' ', 'world' ) ),
			'a word with an underscore'   => array( 'snake_case word', array( 'snake_case', ' ', 'word' ) ),
			'a word with digits'          => array( 'abc123 def', array( 'abc123', ' ', 'def' ) ),
			'punctuation'                 => array( 'a,b', array( 'a', ',', 'b' ) ),
			'a hyphen'                    => array( 'well-known', array( 'well', '-', 'known' ) ),
			'consecutive separators'      => array( 'a, b', array( 'a', ',', '', ' ', 'b' ) ),
			'a leading separator'         => array( ' a', array( '', ' ', 'a' ) ),
			'a trailing separator'        => array( 'a ', array( 'a', ' ', '' ) ),
			'html tags'                   => array( '<b>x</b>', array( '', '<', 'b', '>', 'x', '<', '', '/', 'b', '>', '' ) ),
			'multibyte letters in a word' => array( 'caf' . "\u{E9}" . ' ok', array( 'caf' . "\u{E9}", ' ', 'ok' ) ),
			'a multibyte separator'       => array( "a\u{2014}b", array( 'a', "\u{2014}", 'b' ) ),
		);
	}

	/**
	 * Tests that null bytes are removed before the string is split.
	 *
	 * @ticket 66513
	 */
	public function test_should_remove_null_bytes() {
		$this->assertSame(
			array( 'ab', ' ', 'cd' ),
			$this->renderer->_splitOnWords( "a\0b c\0d" )
		);
	}

	/**
	 * Tests that a newline is replaced with the default escape string.
	 *
	 * @ticket 66513
	 */
	public function test_should_keep_newlines_by_default() {
		$this->assertSame(
			array( 'a', "\n", 'b' ),
			$this->renderer->_splitOnWords( "a\nb" )
		);
	}

	/**
	 * Tests that a newline is replaced with the escape string that is passed.
	 *
	 * @ticket 66513
	 *
	 * @dataProvider data_newline_escapes
	 *
	 * @param string   $text           Text to split.
	 * @param string   $newline_escape Escape string for the newlines.
	 * @param string[] $expected       Expected words and separators.
	 */
	public function test_should_replace_newlines_with_the_escape_string( $text, $newline_escape, $expected ) {
		$this->assertSame( $expected, $this->renderer->_splitOnWords( $text, $newline_escape ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_newline_escapes() {
		return array(
			'a visible marker'       => array( "a\nb", '~', array( 'a', '~', 'b' ) ),
			'an empty escape'        => array( "a\nb", '', array( 'a', '', 'b' ) ),
			'a multi character mark' => array( "a\n\nb", '<nl>', array( 'a', '<nl>', '', '<nl>', 'b' ) ),
			'no newline'             => array( 'a b', '~', array( 'a', ' ', 'b' ) ),
		);
	}

	/**
	 * Tests that the result is not changed by the escape string when it contains no newline.
	 *
	 * @ticket 66513
	 */
	public function test_should_not_change_other_separators_when_escaping_newlines() {
		$this->assertSame(
			array( 'a', ' ', 'b', '~', 'c', "\t", 'd' ),
			$this->renderer->_splitOnWords( "a b\nc\td", '~' )
		);
	}
}
