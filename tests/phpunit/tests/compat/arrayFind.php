<?php

/**
 * @group compat
 *
 * @covers ::array_find
 */
class Tests_Compat_arrayFind extends WP_UnitTestCase {

	/**
	 * Test that array_find() is always available (either from PHP or WP).
	 *
	 * @ticket 62558
	 */
	public function test_array_find_availability() {
		$this->assertTrue( function_exists( 'array_find' ) );
	}

	/**
	 * @dataProvider data_array_find
	 *
	 * @ticket 62558
	 *
	 * @param mixed $expected The expected value.
	 * @param array $arr      The array.
	 * @param callable $callback The needle.
	 */
	public function test_array_find( $expected, array $arr, callable $callback ) {
		$this->assertSame( $expected, array_find( $arr, $callback ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_array_find(): array {
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
				'expected' => 3,
				'arr'      => array( 2, 3, 4 ),
				'callback' => function ( $value ) {
					return 3 === $value;
				},
			),
			'key match'            => array(
				'expected' => 3,
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
				'expected' => 2,
				'arr'      => array( 2, 3, 4 ),
				'callback' => function ( $value ) {
					return 0 === $value % 2;
				},
			),

		);
	}
}
