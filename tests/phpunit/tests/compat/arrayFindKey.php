<?php

/**
 * @group compat
 *
 * @covers ::array_find_key
 */
class Test_Compat_arrayFindKey extends WP_UnitTestCase {

	/**
	 * Test that array_find_key() is always available (either from PHP or WP).
	 *
	 * @ticket 62558
	 */
	public function test_array_find_key_availability() {
		$this->assertTrue( function_exists( 'array_find_key' ) );
	}

	/**
	 * @dataProvider data_array_find_key
	 *
	 * @ticket 62558
	 *
	 * @param mixed $expected The expected value.
	 * @param array $arr The array.
	 * @param callable $callback The callback.
	 */
	public function test_array_find_key( $expected, array $arr, callable $callback ) {
		$this->assertSame( $expected, array_find_key( $arr, $callback ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_array_find_key(): array {
		return array(
			'empty array'          => array(
				'expected' => null,
				'arr'      => array(),
				'callback' => function ( $value ) {
					return 1 === $value;
				},
			),
			'no match'             => array(
				'expected' => null,
				'arr'      => array( 2, 3, 4 ),
				'callback' => function ( $value ) {
					return 1 === $value;
				},
			),
			'match'                => array(
				'expected' => 1,
				'arr'      => array( 2, 3, 4 ),
				'callback' => function ( $value ) {
					return 3 === $value;
				},
			),
			'key match'            => array(
				'expected' => 'b',
				'arr'      => array(
					'a' => 2,
					'b' => 3,
					'c' => 4,
				),
				'callback' => function ( $value ) {
					return 3 === $value;
				},
			),
			'two callback matches' => array(
				'expected' => 'b',
				'arr'      => array(
					'a' => 2,
					'b' => 3,
					'c' => 3,
				),
				'callback' => function ( $value ) {
					return 3 === $value;
				},
			),
		);
	}
}
