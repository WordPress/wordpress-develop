<?php

/**
 * Tests for wp_kses_no_null().
 *
 * @group kses
 *
 * @covers ::wp_kses_no_null
 */
class Tests_Kses_wpKsesNoNull extends WP_UnitTestCase {

	/**
	 * Tests that control characters are removed, and that whitespace characters are kept.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_control_characters
	 *
	 * @param string $content  Content to filter.
	 * @param string $expected Expected content.
	 */
	public function test_should_remove_control_characters( $content, $expected ) {
		$this->assertSame( $expected, wp_kses_no_null( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_control_characters() {
		return array(
			'empty string'                  => array( '', '' ),
			'plain text'                    => array( 'hello world', 'hello world' ),
			'NULL character'                => array( "a\x00b", 'ab' ),
			'start of heading'              => array( "a\x01b", 'ab' ),
			'backspace'                     => array( "a\x08b", 'ab' ),
			'vertical tab'                  => array( "a\x0Bb", 'ab' ),
			'form feed'                     => array( "a\x0Cb", 'ab' ),
			'shift out'                     => array( "a\x0Eb", 'ab' ),
			'unit separator'                => array( "a\x1Fb", 'ab' ),
			'several control characters'    => array( "\x00a\x01b\x02c\x1F", 'abc' ),
			'tab is kept'                   => array( "a\tb", "a\tb" ),
			'line feed is kept'             => array( "a\nb", "a\nb" ),
			'carriage return is kept'       => array( "a\rb", "a\rb" ),
			'space is kept'                 => array( 'a b', 'a b' ),
			'DEL is kept'                   => array( "a\x7Fb", "a\x7Fb" ),
			'multibyte characters are kept' => array( "caf\u{00E9} \u{1F600}", "caf\u{00E9} \u{1F600}" ),
		);
	}

	/**
	 * Tests that escaped zeros are removed by default.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_escaped_zeros
	 *
	 * @param string $content  Content to filter.
	 * @param string $expected Expected content when escaped zeros are removed.
	 */
	public function test_should_remove_escaped_zeros_by_default( $content, $expected ) {
		$this->assertSame( $expected, wp_kses_no_null( $content ) );
	}

	/**
	 * Tests that escaped zeros are removed when the `slash_zero` option is `remove`.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_escaped_zeros
	 *
	 * @param string $content  Content to filter.
	 * @param string $expected Expected content when escaped zeros are removed.
	 */
	public function test_should_remove_escaped_zeros_with_slash_zero_remove( $content, $expected ) {
		$this->assertSame( $expected, wp_kses_no_null( $content, array( 'slash_zero' => 'remove' ) ) );
	}

	/**
	 * Data provider for the default and `remove` behavior of `slash_zero`.
	 *
	 * @return array[]
	 */
	public static function data_escaped_zeros() {
		return array(
			'backslash and zero'           => array( 'a\0b', 'ab' ),
			'backslash and several zeros'  => array( 'a\000b', 'ab' ),
			'several backslashes and zero' => array( 'a\\\\0b', 'ab' ),
			'zero followed by digits'      => array( 'a\012b', 'a12b' ),
			'at the start'                 => array( '\0abc', 'abc' ),
			'at the end'                   => array( 'abc\0', 'abc' ),
			'several occurrences'          => array( 'a\0b\00c', 'abc' ),
			'backslash without zero'       => array( 'a\b', 'a\b' ),
			'zero without backslash'       => array( 'a0b100', 'a0b100' ),
			'backslash and other digit'    => array( 'a\1b', 'a\1b' ),
		);
	}

	/**
	 * Tests that escaped zeros are kept when the `slash_zero` option is `keep`.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_escaped_zeros_kept
	 *
	 * @param string $content  Content to filter.
	 * @param string $expected Expected content when escaped zeros are kept.
	 */
	public function test_should_keep_escaped_zeros_with_slash_zero_keep( $content, $expected ) {
		$this->assertSame( $expected, wp_kses_no_null( $content, array( 'slash_zero' => 'keep' ) ) );
	}

	/**
	 * Data provider for the `keep` behavior of `slash_zero`.
	 *
	 * @return array[]
	 */
	public static function data_escaped_zeros_kept() {
		return array(
			'backslash and zero'                     => array( 'a\0b', 'a\0b' ),
			'backslash and several zeros'            => array( 'a\000b', 'a\000b' ),
			'control characters are still removed'   => array( "a\x00\\0b", 'a\0b' ),
			'whitespace characters are still kept'   => array( "a\t\\0\nb", "a\t\\0\nb" ),
			'backslash without zero is kept as well' => array( 'a\b', 'a\b' ),
		);
	}

	/**
	 * Tests that escaped zeros are only removed when the `slash_zero` option is exactly `remove`,
	 * and are kept for any other value.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_slash_zero_options_that_do_not_remove
	 *
	 * @param array $options Options passed to the function.
	 */
	public function test_should_only_remove_escaped_zeros_when_slash_zero_is_remove( $options ) {
		$this->assertSame( 'a\0b', wp_kses_no_null( 'a\0b', $options ) );
	}

	/**
	 * Data provider for the `slash_zero` values that are not exactly `remove`.
	 *
	 * @return array[]
	 */
	public static function data_slash_zero_options_that_do_not_remove() {
		return array(
			'keep'         => array( array( 'slash_zero' => 'keep' ) ),
			'other string' => array( array( 'slash_zero' => 'other' ) ),
			'empty string' => array( array( 'slash_zero' => '' ) ),
			'uppercase'    => array( array( 'slash_zero' => 'REMOVE' ) ),
		);
	}

	/**
	 * Tests that escaped zeros are removed when the options do not contain `slash_zero`.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_options_without_slash_zero
	 *
	 * @param mixed $options Options passed to the function.
	 */
	public function test_should_remove_escaped_zeros_when_options_do_not_contain_slash_zero( $options ) {
		$this->assertSame( 'ab', wp_kses_no_null( 'a\0b', $options ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_options_without_slash_zero() {
		return array(
			'null'                 => array( null ),
			'empty array'          => array( array() ),
			'array with other key' => array( array( 'other' => 'keep' ) ),
		);
	}
}
