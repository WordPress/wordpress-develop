<?php

/**
 * @group compat
 *
 * @covers ::str_starts_with
 */
class Tests_Compat_str_starts_with extends WP_UnitTestCase {

	/**
	 * Test that is_iterable() is always available (either from PHP or WP).
	 *
	 * @ticket 43619
	 */
	public function test_is_iterable_availability() {
		$this->assertTrue( function_exists( 'str_starts_with' ) );
	}
	/**
	 * @dataProvider str_starts_with_provider
	 */
	public function test_str_starts_with( $expected, $string, $needle ) {
		if ( ! function_exists( 'str_starts_with' ) ) {
			$this->markTestSkipped( 'str_starts_with() is not available.' );
		} else {
			$this->assertSame(
				$expected,
				str_starts_with( $string, $needle )
			);
		}

	}

	public function str_starts_with_provider() {
		return array(
			'lowercase'              => array(
				'expected' => true,
				'string'   => 'this is a test',
				'needle'   => 'this',
			),
			'uppercase'              => array(
				'expected' => true,
				'string'   => 'THIS is a TEST',
				'needle'   => 'THIS',
			),
			'first_leter_upprercase' => array(
				'expected' => true,
				'string'   => 'This is a Test',
				'needle'   => 'This',
			),
			'cammelCase'             => array(
				'expected' => true,
				'string'   => 'cammelCase is the start',
				'needle'   => 'cammelCase',
			),
			'not_cammelCase'         => array(
				'expected' => false,
				'string'   => ' cammelcase is the start',
				'needle'   => 'cammelCase',
			),
			'missing'                => array(
				'expected' => false,
				'string'   => 'This is a test',
				'needle'   => 'cammelCase',
			),
			'not end'                => array(
				'expected' => false,
				'string'   => 'This is a test extra',
				'needle'   => 'test',
			),
			'extra_space'                => array(
				'expected' => false,
				'string'   => ' This is a test',
				'needle'   => 'This',
			),
		);
	}
}
