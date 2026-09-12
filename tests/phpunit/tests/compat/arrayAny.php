<?php

/**
 * @group compat
 *
 * @covers ::array_any
 */
class Test_Compat_arrayAny extends WP_UnitTestCase {

	/**
	 * Test that array_any() is always available (either from PHP or WP).
	 *
	 * @ticket 62558
	 */
	public function test_array_any_availability() {
		$this->assertTrue( function_exists( 'array_any' ) );
	}

	/**
	 * @dataProvider data_array_any
	 *
	 * @ticket 62558
	 *
	 * @param bool $expected The expected value.
	 * @param array $arr The array.
	 * @param callable $callback The callback.
	 */
	public function test_array_any( bool $expected, array $arr, callable $callback ) {
		$this->assertSame( $expected, array_any( $arr, $callback ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_array_any(): array {
		return array(
			'empty array' => array(
				'expected' => false,
				'arr'      => array(),
				'callback' => function ( $value ) {
					return 1 === $value;
				},
			),
			'no match'    => array(
				'expected' => false,
				'arr'      => array( 2, 3, 4 ),
				'callback' => function ( $value ) {
					return 1 === $value;
				},
			),
			'match'       => array(
				'expected' => true,
				'arr'      => array( 2, 3, 4 ),
				'callback' => function ( $value ) {
					return 3 === $value;
				},
			),
			'key match'   => array(
				'expected' => true,
				'arr'      => array(
					'a' => 2,
					'b' => 3,
					'c' => 4,
				),
				'callback' => function ( $value, $key ) {
					return 'c' === $key;
				},
			),
		);
	}
}
