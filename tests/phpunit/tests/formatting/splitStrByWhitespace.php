<?php

/**
 * Tests for the `_split_str_by_whitespace()` function.
 *
 * @group formatting
 *
 * @covers ::_split_str_by_whitespace
 */
class Tests_Formatting_SplitStrByWhitespace extends WP_UnitTestCase {

	/**
	 * Tests that text shorter than goal is returned in a single chunk.
	 *
	 * @ticket 66621
	 */
	public function test_split_str_by_whitespace_short_string() {
		$text   = 'Short string';
		$chunks = _split_str_by_whitespace( $text, 20 );

		$this->assertSame( array( 'Short string' ), $chunks );
	}

	/**
	 * Tests splitting text at whitespace boundary near goal.
	 *
	 * @ticket 66621
	 */
	public function test_split_str_by_whitespace_splits_at_boundary() {
		$text   = 'The quick brown fox jumps over the lazy dog';
		$chunks = _split_str_by_whitespace( $text, 10 );

		$this->assertSame( $text, implode( '', $chunks ) );
		$this->assertSame( 'The quick ', $chunks[0] );
		$this->assertSame( 'brown fox ', $chunks[1] );
	}

	/**
	 * Tests handling of various whitespace characters (tabs, newlines, carriage returns).
	 *
	 * @ticket 66621
	 */
	public function test_split_str_by_whitespace_various_whitespace() {
		$text   = "Line one\nLine two\r\nLine three\tTabbed line";
		$chunks = _split_str_by_whitespace( $text, 12 );

		$this->assertSame( $text, implode( '', $chunks ) );
		$this->assertCount( 4, $chunks );
	}

	/**
	 * Tests handling when a word without whitespace is longer than the goal.
	 *
	 * @ticket 66621
	 */
	public function test_split_str_by_whitespace_word_longer_than_goal() {
		$text   = 'Supercalifragilisticexpialidocious and more words';
		$chunks = _split_str_by_whitespace( $text, 10 );

		$this->assertSame( $text, implode( '', $chunks ) );
		$this->assertSame( 'Supercalifragilisticexpialidocious ', $chunks[0] );
		$this->assertSame( 'and more ', $chunks[1] );
		$this->assertSame( 'words', $chunks[2] );
	}
}
