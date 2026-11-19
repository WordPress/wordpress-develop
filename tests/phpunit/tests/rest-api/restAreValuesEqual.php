<?php

/**
 * Tests for rest_are_values_equal().
 *
 * @group restapi
 *
 * @covers ::rest_are_values_equal
 */
class Tests_REST_API_RestAreValuesEqual extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_equal_values
	 *
	 * @param mixed $value1 First value.
	 * @param mixed $value2 Second value.
	 */
	public function test_returns_true_for_equal_values( $value1, $value2 ) {
		$this->assertTrue( rest_are_values_equal( $value1, $value2 ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_equal_values() {
		return array(
			'same strings'                     => array( 'hello', 'hello' ),
			'same integers'                    => array( 7, 7 ),
			'integer and float'                => array( 1, 1.0 ),
			'float and integer'                => array( 1.0, 1 ),
			'same booleans'                    => array( true, true ),
			'both null'                        => array( null, null ),
			'same lists'                       => array( array( 1, 2 ), array( 1, 2 ) ),
			'objects with null property'       => array(
				array(
					'a' => null,
					'b' => 1,
				),
				array(
					'a' => null,
					'b' => 1,
				),
			),
			'same objects different key order' => array(
				array(
					'a' => 1,
					'b' => 2,
				),
				array(
					'b' => 2,
					'a' => 1,
				),
			),
			'nested coercion and key order'    => array(
				array(
					'items' => array(
						1,
						array(
							'x' => 2,
							'y' => 3.0,
						),
					),
				),
				array(
					'items' => array(
						1.0,
						array(
							'y' => 3,
							'x' => 2.0,
						),
					),
				),
			),
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_unequal_values
	 *
	 * @param mixed $value1 First value.
	 * @param mixed $value2 Second value.
	 */
	public function test_returns_false_for_unequal_values( $value1, $value2 ) {
		$this->assertFalse( rest_are_values_equal( $value1, $value2 ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unequal_values() {
		return array(
			'different strings'    => array( 'hello', 'world' ),
			'string and integer'   => array( '1', 1 ),
			'integer and float'    => array( 1, 2.0 ),
			'float and integer'    => array( 2.0, 1 ),
			'different list order' => array( array( 1, 2 ), array( 2, 1 ) ),
			'different counts'     => array( array( 1 ), array( 1, 2 ) ),
			'missing object key'   => array(
				array( 'a' => 1 ),
				array( 'b' => 1 ),
			),
			'nested mismatch'      => array(
				array( 'items' => array( 1, 2 ) ),
				array( 'items' => array( 1, 3 ) ),
			),
		);
	}
}
