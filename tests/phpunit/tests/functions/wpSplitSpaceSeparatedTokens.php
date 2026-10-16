<?php

/**
 * Tests for the wp_split_space_separated_tokens() function.
 *
 * @group functions
 *
 * @covers ::wp_split_space_separated_tokens
 */
class Tests_Functions_wpSplitSpaceSeparatedTokens extends WP_UnitTestCase {

	/**
	 * @ticket 65466
	 *
	 * @dataProvider data_wp_split_space_separated_tokens
	 *
	 * @param string   $input    String to split.
	 * @param string[] $expected Expected tokens.
	 */
	public function test_wp_split_space_separated_tokens( string $input, array $expected ): void {
		$this->assertSame( $expected, wp_split_space_separated_tokens( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ input: string, expected: string[] }>
	 */
	public function data_wp_split_space_separated_tokens(): array {
		return array(
			'empty string'                          => array(
				'input'    => '',
				'expected' => array(),
			),
			'whitespace only'                       => array(
				'input'    => " \t\n\f\r ",
				'expected' => array(),
			),
			'single token'                          => array(
				'input'    => 'foo',
				'expected' => array( 'foo' ),
			),
			'single spaces'                         => array(
				'input'    => 'foo bar baz',
				'expected' => array( 'foo', 'bar', 'baz' ),
			),
			'leading, trailing and repeated space'  => array(
				'input'    => '  foo   bar  ',
				'expected' => array( 'foo', 'bar' ),
			),
			'all ASCII whitespace separators'       => array(
				'input'    => "a\tb\nc\fd\re f",
				'expected' => array( 'a', 'b', 'c', 'd', 'e', 'f' ),
			),
			'vertical tab is not a separator'       => array(
				'input'    => "a\vb",
				'expected' => array( "a\vb" ),
			),
			'non-breaking space is not a separator' => array(
				'input'    => "a\u{00A0}b c",
				'expected' => array( "a\u{00A0}b", 'c' ),
			),
			'duplicates are preserved'              => array(
				'input'    => 'foo bar foo',
				'expected' => array( 'foo', 'bar', 'foo' ),
			),
			'case is preserved'                     => array(
				'input'    => 'Foo foo',
				'expected' => array( 'Foo', 'foo' ),
			),
			'character references are not decoded'  => array(
				'input'    => 'a&amp;b c&#32;d',
				'expected' => array( 'a&amp;b', 'c&#32;d' ),
			),
			'zero is a valid token'                 => array(
				'input'    => '0 1',
				'expected' => array( '0', '1' ),
			),
		);
	}
}
