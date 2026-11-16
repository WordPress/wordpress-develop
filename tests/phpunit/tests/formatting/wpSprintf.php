<?php

/**
 * Tests for wp_sprintf() and wp_sprintf_l().
 *
 * @group formatting
 */
class Tests_Formatting_wpSprintf extends WP_UnitTestCase {

	/**
	 * Tests that patterns are formatted like sprintf().
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_sprintf
	 *
	 * @dataProvider data_wp_sprintf
	 *
	 * @param string $pattern  Pattern.
	 * @param array  $args     Arguments.
	 * @param string $expected Expected result.
	 */
	public function test_wp_sprintf( $pattern, $args, $expected ) {
		$this->assertSame( $expected, wp_sprintf( $pattern, ...$args ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wp_sprintf() {
		return array(
			'no specifiers'                     => array( 'Hello world', array(), 'Hello world' ),
			'empty pattern'                     => array( '', array( 'unused' ), '' ),
			'string specifier'                  => array( 'Hello %s!', array( 'world' ), 'Hello world!' ),
			'specifiers in order'               => array( '%s, %s and %s', array( 'one', 'two', 'three' ), 'one, two and three' ),
			'integer specifier'                 => array( '%d items', array( '12abc' ), '12 items' ),
			'padded specifier'                  => array( '[%05d]', array( 42 ), '[00042]' ),
			'numbered arguments'                => array( '%2$s before %1$s', array( 'first', 'second' ), 'second before first' ),
			'numbered argument used twice'      => array( '%1$s and %1$s', array( 'again' ), 'again and again' ),
			'numbered arguments do not advance' => array( '%2$s %s', array( 'first', 'second' ), 'second first' ),
			'missing argument'                  => array( '%s and %s', array( 'one' ), 'one and ' ),
			'missing numbered argument'         => array( '%3$s!', array( 'one' ), '!' ),
			'literal percent'                   => array( '100%% sure', array(), '100% sure' ),
			'literal percent before specifier'  => array( '%%%s', array( 'value' ), '%value' ),
			'trailing percent'                  => array( 'Rate: 5%', array(), 'Rate: 5%' ),
			'single character pattern'          => array( '%', array(), '%' ),
			'zero string argument'              => array( 'Count: %s', array( '0' ), 'Count: 0' ),
			'falsy argument'                    => array( '[%s]', array( false ), '[]' ),
		);
	}

	/**
	 * Tests that the wp_sprintf filter receives each fragment and its argument, and can replace the fragment.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_sprintf
	 */
	public function test_wp_sprintf_filter_receives_fragments_and_can_replace_them() {
		$filter = new MockAction();
		add_filter( 'wp_sprintf', array( $filter, 'filter' ), 5, 2 );
		add_filter(
			'wp_sprintf',
			static function ( $fragment, $arg ) {
				return '%x' === substr( $fragment, 0, 2 ) ? '[' . strtoupper( $arg ) . ']' . substr( $fragment, 2 ) : $fragment;
			},
			20,
			2
		);

		$this->assertSame( 'a [B] c', wp_sprintf( 'a %x c', 'b' ), 'A changed fragment should be used as is.' );
		$this->assertSame( array( array( '%x c', 'b' ) ), $filter->get_args(), 'The filter should receive the fragment and its argument.' );
	}

	/**
	 * Tests the list formatting of the %l specifier.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_sprintf
	 * @covers ::wp_sprintf_l
	 *
	 * @dataProvider data_wp_sprintf_l
	 *
	 * @param string $pattern  Pattern.
	 * @param mixed  $items    Items passed for the %l specifier.
	 * @param string $expected Expected result.
	 */
	public function test_wp_sprintf_formats_lists( $pattern, $items, $expected ) {
		$this->assertSame( $expected, wp_sprintf( $pattern, $items ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wp_sprintf_l() {
		return array(
			'one item'                 => array( 'Tags: %l.', array( 'one' ), 'Tags: one.' ),
			'two items'                => array( 'Tags: %l.', array( 'one', 'two' ), 'Tags: one and two.' ),
			'three items'              => array( 'Tags: %l.', array( 'one', 'two', 'three' ), 'Tags: one, two, and three.' ),
			'string instead of a list' => array( 'Tags: %l.', 'one', 'Tags: one.' ),
			'zero string item'         => array( 'Tags: %l.', array( '0', '1' ), 'Tags: 0 and 1.' ),
		);
	}

	/**
	 * Tests that wp_sprintf_l() only handles patterns that start with %l.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_sprintf_l
	 *
	 * @dataProvider data_wp_sprintf_l_patterns
	 *
	 * @param string $pattern  Pattern.
	 * @param array  $items    Items.
	 * @param string $expected Expected result.
	 */
	public function test_wp_sprintf_l( $pattern, $items, $expected ) {
		$this->assertSame( $expected, wp_sprintf_l( $pattern, $items ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wp_sprintf_l_patterns() {
		return array(
			'list specifier with a suffix' => array( '%l!', array( 'a', 'b', 'c' ), 'a, b, and c!' ),
			'other specifier'              => array( '%s', array( 'a', 'b' ), '%s' ),
			'list specifier not at start'  => array( 'x%l', array( 'a', 'b' ), 'x%l' ),
		);
	}

	/**
	 * Tests that the list delimiters are filterable.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_sprintf_l
	 */
	public function test_wp_sprintf_l_delimiters_are_filterable() {
		$filter = new MockAction();
		add_filter( 'wp_sprintf_l', array( $filter, 'filter' ) );
		add_filter(
			'wp_sprintf_l',
			static function () {
				return array(
					'between'          => ' / ',
					'between_last_two' => ' // ',
					'between_only_two' => ' + ',
				);
			},
			20
		);

		$this->assertSame( 'a + b', wp_sprintf_l( '%l', array( 'a', 'b' ) ), 'The two-item delimiter should be filtered.' );
		$this->assertSame( 'a / b / c // d', wp_sprintf_l( '%l', array( 'a', 'b', 'c', 'd' ) ), 'The list delimiters should be filtered.' );
		$this->assertSame(
			array(
				'between'          => ', ',
				'between_last_two' => ', and ',
				'between_only_two' => ' and ',
			),
			$filter->get_args()[0][0],
			'The filter should receive the default delimiters.'
		);
	}
}
