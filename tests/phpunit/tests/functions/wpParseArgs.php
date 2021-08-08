<?php

/**
 * @group functions.php
 * @covers ::wp_parse_args
 */
class Tests_Functions_WpParseArgs extends WP_UnitTestCase {

	public function test_wp_parse_args_object() {
		$x = $this->get_object_for_parsing();
		$this->assertSame(
			array(
				'_baba' => 5,
				'yZ'    => 'baba',
				'a'     => array( 5, 111, 'x' ),
			),
			wp_parse_args( $x )
		);
		$y = new MockClass;
		$this->assertSame( array(), wp_parse_args( $y ) );
	}

	public function test_wp_parse_args_array() {
		// Arrays.
		$a = array();
		$this->assertSame( array(), wp_parse_args( $a ) );
		$b = array(
			'_baba' => 5,
			'yZ'    => 'baba',
			'a'     => array( 5, 111, 'x' ),
		);
		$this->assertSame(
			array(
				'_baba' => 5,
				'yZ'    => 'baba',
				'a'     => array( 5, 111, 'x' ),
			),
			wp_parse_args( $b )
		);
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

	public function test_wp_parse_args_other() {
		$b = true;
		wp_parse_str( $b, $s );
		$this->assertSame( $s, wp_parse_args( $b ) );
		$q = 'x=5&_baba=dudu&';
		wp_parse_str( $q, $ss );
		$this->assertSame( $ss, wp_parse_args( $q ) );
	}

	/**
	 * @ticket 30753
	 */
	public function test_wp_parse_args_boolean_strings() {
		$args = wp_parse_args( 'foo=false&bar=true' );
		$this->assertIsString( $args['foo'] );
		$this->assertIsString( $args['bar'] );
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
