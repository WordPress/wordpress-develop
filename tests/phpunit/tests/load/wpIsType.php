<?php

/**
 * Tests for is_type().
 *
 * @group load
 *
 * @covers ::is_login
 */
class Tests_Load_WpIsType extends WP_UnitTestCase {
	/**
	 * @ticket 51525
	 *
	 * @dataProvider data_wp_is_type
	 */
	public function test_wp_is_type( $type, $value, $expected ) {
		$this->assertSame( $expected, wp_is_type( $type, $value ) );
	}

	public function data_wp_is_type() {
		return array(
			'testIsBool'        => array(
				'boolean',
				true,
				true,
			),
			'testIsNotBool'     => array(
				'boolean',
				1,
				false,
			),
			'testIsInt'         => array(
				'integer',
				1,
				true,
			),
			'testIsNotInt'      => array(
				'integer',
				1.1,
				false,
			),
			'testIsFloat'       => array(
				'double',
				1.1,
				true,
			),
			'testIsNotFloat'    => array(
				'double',
				1,
				false,
			),
			'testIsString'      => array(
				'string',
				'1',
				true,
			),
			'testIsNotString'   => array(
				'string',
				1,
				false,
			),
			'testIsArray'       => array(
				'array',
				array(),
				true,
			),
			'testIsNotArray'    => array(
				'array',
				1,
				false,
			),
			'testIsObject'      => array(
				'object',
				(object) array(),
				true,
			),
			'testIsNotObject'   => array(
				'object',
				1,
				false,
			),
			'testIsResource'    => array(
				'resource',
				fopen( 'php://memory', 'r' ),
				true,
			),
			'testIsNotResource' => array(
				'resource',
				1,
				false,
			),
			'testIsNull'        => array(
				'NULL',
				null,
				true,
			),
			'testIsNotNull'     => array(
				'NULL',
				1,
				false,
			),
			'testIsUnknownType' => array(
				'unknown_type',
				1,
				false,
			),
		);
	}
}
