<?php

/**
 * Tests for the `format_to_edit()` function.
 *
 * @group formatting
 *
 * @covers ::format_to_edit
 */
class Tests_Formatting_FormatToEdit extends WP_UnitTestCase {

	/**
	 * Tests that format_to_edit() escapes text for textarea by default ($rich_text = false).
	 *
	 * @ticket 65819
	 */
	public function test_format_to_edit_escapes_when_rich_text_is_false() {
		$content  = '<b>"hello" & \'world\'</b>';
		$expected = '&lt;b&gt;&quot;hello&quot; &amp; &#039;world&#039;&lt;/b&gt;';

		$this->assertSame( $expected, format_to_edit( $content ) );
		$this->assertSame( $expected, format_to_edit( $content, false ) );
	}

	/**
	 * Tests that format_to_edit() does not escape text when $rich_text is true.
	 *
	 * @ticket 65819
	 */
	public function test_format_to_edit_does_not_escape_when_rich_text_is_true() {
		$content = '<b>"hello" & \'world\'</b>';

		$this->assertSame( $content, format_to_edit( $content, true ) );
	}

	/**
	 * Tests that format_to_edit filter modifies content before escaping.
	 *
	 * @ticket 65819
	 */
	public function test_format_to_edit_filter() {
		add_filter(
			'format_to_edit',
			static function ( $text ) {
				return $text . ' & <span>extra</span>';
			}
		);

		$expected = 'original &amp; &lt;span&gt;extra&lt;/span&gt;';

		$this->assertSame( $expected, format_to_edit( 'original' ) );
	}
}
