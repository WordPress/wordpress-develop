<?php

/**
 * @group compat
 *
 * @covers ::array_all
 */
class Test_Compat_arrayAll extends WP_UnitTestCase {

	/**
	 * Test that array_all() is always available (either from PHP or WP).
	 *
	 * @ticket 62558
	 */
	public function test_array_all_availability() {
		$this->assertTrue( function_exists( 'array_all' ) );
	}

	/**
	 * @dataProvider data_array_all
	 *
	 * @ticket 62558
	 *
	 * @param bool $expected The expected value.
	 * @param array $arr The array.
	 * @param callable $callback The callback.
	 */
	public function test_array_all( bool $expected, array $arr, callable $callback ) {
		$this->assertSame( $expected, array_all( $arr, $callback ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_array_all(): array {
		return array(
			'empty array'   => array(
				'expected' => true,
				'arr'      => array(),
				'callback' => function ( $value ) {
					return 1 === $value;
				},
			),
			'no match'      => array(
				'expected' => false,
				'arr'      => array( 2, 3, 4 ),
				'callback' => function ( $value ) {
					return 1 === $value;
				},
			),
			'not all match' => array(
				'expected' => false,
				'arr'      => array( 2, 3, 4 ),
				'callback' => function ( $value ) {
					return 0 === $value % 2;
				},
			),
			'match'         => array(
				'expected' => true,
				'arr'      => array( 2, 4, 6 ),
				'callback' => function ( $value ) {
					return 0 === $value % 2;
				},
			),
			'key match'     => array(
				'expected' => true,
				'arr'      => array(
					'a' => 2,
					'b' => 4,
					'c' => 6,
				),
				'callback' => function ( $value, $key ) {
					return strlen( $key ) === 1;
				},
			),
		);
	}
}
