<?php

/**
 * Tests for wp_kses_array_lc().
 *
 * @group kses
 *
 * @covers ::wp_kses_array_lc
 */
class Tests_Kses_wpKsesArrayLc extends WP_UnitTestCase {

	/**
	 * Tests that the keys of both levels are lowercased.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_arrays
	 *
	 * @param array $input    Array to normalize.
	 * @param array $expected Expected array.
	 */
	public function test_should_lowercase_keys_of_both_levels( $input, $expected ) {
		$this->assertSame( $expected, wp_kses_array_lc( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_arrays() {
		return array(
			'empty array'                  => array( array(), array() ),
			'already lowercase'            => array(
				array( 'a' => array( 'href' => true ) ),
				array( 'a' => array( 'href' => true ) ),
			),
			'uppercase tag and attributes' => array(
				array(
					'DIV' => array(
						'CLASS' => true,
						'ID'    => true,
					),
				),
				array(
					'div' => array(
						'class' => true,
						'id'    => true,
					),
				),
			),
			'mixed case'                   => array(
				array( 'Img' => array( 'SrC' => true ) ),
				array( 'img' => array( 'src' => true ) ),
			),
			'several tags'                 => array(
				array(
					'A'  => array( 'HREF' => true ),
					'P'  => array( 'ALIGN' => true ),
					'br' => array(),
				),
				array(
					'a'  => array( 'href' => true ),
					'p'  => array( 'align' => true ),
					'br' => array(),
				),
			),
			'tag without attributes'       => array(
				array( 'BR' => array() ),
				array( 'br' => array() ),
			),
			'order is kept'                => array(
				array(
					'B' => array(),
					'A' => array(),
					'C' => array(),
				),
				array(
					'b' => array(),
					'a' => array(),
					'c' => array(),
				),
			),
		);
	}

	/**
	 * Tests that the values of the second level are not changed.
	 *
	 * @ticket 66492
	 */
	public function test_should_not_change_values() {
		$input = array(
			'DIV' => array(
				'CLASS'  => array(
					'Required' => true,
					'Values'   => array( 'Foo', 'BAR' ),
				),
				'TITLE'  => 'Some Value',
				'HIDDEN' => false,
				'ORDER'  => 5,
			),
		);

		$this->assertSame(
			array(
				'div' => array(
					'class'  => array(
						'Required' => true,
						'Values'   => array( 'Foo', 'BAR' ),
					),
					'title'  => 'Some Value',
					'hidden' => false,
					'order'  => 5,
				),
			),
			wp_kses_array_lc( $input )
		);
	}

	/**
	 * Tests that the allowed HTML of a context can be normalized.
	 *
	 * @ticket 66492
	 */
	public function test_should_normalize_allowed_html() {
		$result = wp_kses_array_lc(
			array(
				'A'   => array(
					'HREF'  => true,
					'Title' => true,
				),
				'IMG' => array( 'SRC' => true ),
			)
		);

		$this->assertSame( array( 'a', 'img' ), array_keys( $result ) );
		$this->assertSame( array( 'href', 'title' ), array_keys( $result['a'] ) );
		$this->assertSame( array( 'src' ), array_keys( $result['img'] ) );
	}
}
