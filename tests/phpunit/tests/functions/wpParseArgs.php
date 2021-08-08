<?php

/**
 * @group functions.php
 * @covers ::wp_parse_args
 */
class Tests_Functions_WpParseArgs extends WP_UnitTestCase {

	/**
	 * Tests parsing of arguments when no value has been passed for the $defaults parameter.
	 *
	 * @dataProvider data_wp_parse_args_no_defaults
	 *
	 * @param mixed $args     Value to parse.
	 * @param array $expected Expected function output.
	 */
	public function test_wp_parse_args_no_defaults( $args, $expected ) {
		$this->assertSame( $expected, wp_parse_args( $args ) );
	}

	/**
	 * Data Provider.
	 *
	 * @return array
	 */
	public function data_wp_parse_args_no_defaults() {
		// Default expected return value.
		$expected = array(
			'_baba' => 5,
			'yZ'    => 'baba',
			'a'     => array( 5, 111, 'x' ),
		);

		$data = array(
			'object without properties'  => array(
				'args'     => new MockClass(),
				'expected' => array(),
			),
			'object with properties'     => array(
				'args'     => $this->get_object_for_parsing(),
				'expected' => $expected,
			),
			'empty array'                => array(
				'args'     => array(),
				'expected' => array(),
			),
			'array with keys and values' => array(
				'args'     => $expected,
				'expected' => $expected,
			),
		);

		$other_data = array(
			'null'         => null,
			'boolean true' => true,
			'query string' => 'x=5&_baba=dudu&',
		);

		foreach ( $other_data as $key => $input ) {
			wp_parse_str( $input, $output );
			$data[ $key ] = array(
				'args'     => $input,
				'expected' => $output,
			);
		}

		return $data;
	}

	public function test_wp_parse_args_defaults() {
		$x = $this->get_object_for_parsing();
		$d = array( 'pu' => 'bu' );
		$this->assertSame(
			array(
				'pu'    => 'bu',
				'_baba' => 5,
				'yZ'    => 'baba',
				'a'     => array( 5, 111, 'x' ),
			),
			wp_parse_args( $x, $d )
		);
		$e = array( '_baba' => 6 );
		$this->assertSame(
			array(
				'_baba' => 5,
				'yZ'    => 'baba',
				'a'     => array( 5, 111, 'x' ),
			),
			wp_parse_args( $x, $e )
		);
	}

	/**
	 * @ticket 30753
	 */
	public function test_wp_parse_args_boolean_strings() {
		$args = wp_parse_args( 'foo=false&bar=true' );
		$this->assertIsArray( $args, 'Return value is not an array' );
		$this->assertArrayHasKey( 'foo', $args, 'Returned array does not have key "foo"' );
		$this->assertIsString( $args['foo'], 'Value for array index "foo" is not a string' );
		$this->assertArrayHasKey( 'bar', $args, 'Returned array does not have key "bar"' );
		$this->assertIsString( $args['bar'], 'Value for array index "bar" is not a string' );
	}

	/**
	 * Helper method. Creates an object with properties for use in these tests.
	 *
	 * @return MockClass
	 */
	private function get_object_for_parsing() {
		$x        = new MockClass;
		$x->_baba = 5;
		$x->yZ    = 'baba'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$x->a     = array( 5, 111, 'x' );

		return $x;
	}
}
