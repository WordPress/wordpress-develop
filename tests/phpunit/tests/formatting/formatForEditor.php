<?php

/**
 * Tests for the `format_for_editor()` function.
 *
 * @group formatting
 *
 * @covers ::format_for_editor
 */
class Tests_Formatting_FormatForEditor extends WP_UnitTestCase {

	/**
	 * Tests that format_for_editor() encodes HTML entities but preserves quotes.
	 *
	 * @ticket 65819
	 */
	public function test_format_for_editor_encodes_entities_without_quotes() {
		$input    = '<p>"Hello" & \'World\'</p>';
		$expected = '&lt;p&gt;"Hello" &amp; \'World\'&lt;/p&gt;';

		$this->assertSame( $expected, format_for_editor( $input ) );
	}

	/**
	 * Tests that format_for_editor() returns an empty string when input is empty.
	 *
	 * @ticket 65819
	 */
	public function test_format_for_editor_empty_input() {
		$this->assertSame( '', format_for_editor( '' ) );
	}

	/**
	 * Tests that the format_for_editor filter receives the text and default editor.
	 *
	 * @ticket 65819
	 */
	public function test_format_for_editor_filter() {
		$filter = new MockAction();
		add_filter( 'format_for_editor', array( $filter, 'filter' ), 10, 2 );

		format_for_editor( 'Hello World', 'tinymce' );

		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'Hello World', 'tinymce' ), $filter->get_args()[0] );
	}
}
