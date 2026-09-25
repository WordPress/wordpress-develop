<?php

/**
 * @group pomo
 */
class PluralFormsTest extends WP_UnitTestCase {
	/**
	 * Parenthesize plural expression.
	 *
	 * Legacy workaround for PHP's flipped precedence order for ternary.
	 *
	 * @param string $expression the expression without parentheses
	 * @return string the expression with parentheses added
	 */
	protected static function parenthesize_plural_expression( $expression ) {
		$expression .= ';';
		$res         = '';
		$depth       = 0;
		for ( $i = 0; $i < strlen( $expression ); ++$i ) {
			$char = $expression[ $i ];
			switch ( $char ) {
				case '?':
					$res .= ' ? (';
					++$depth;
					break;
				case ':':
					$res .= ') : (';
					break;
				case ';':
					$res  .= str_repeat( ')', $depth ) . ';';
					$depth = 0;
					break;
				default:
					$res .= $char;
			}
		}
		return rtrim( $res, ';' );
	}

	/**
	 * @ticket 41562
	 * @dataProvider data_regression
	 */
	public function test_regression( int $nplurals, string $expression ): void {
		require_once dirname( __DIR__, 2 ) . '/includes/plural-form-function.php';

		$parenthesized = self::parenthesize_plural_expression( $expression );
		$old_style     = tests_make_plural_form_function( $nplurals, $parenthesized );
		$plural_forms  = new Plural_Forms( $expression );

		$generated_old = array();
		$generated_new = array();

		foreach ( range( 0, 200 ) as $i ) {
			$generated_old[] = $old_style( $i );
			$generated_new[] = $plural_forms->get( $i );
		}

		$this->assertSame( $generated_old, $generated_new );
	}

	/**
	 * Distinct plural expressions from the GlotPress locales file, keyed by one locale that uses each.
	 *
	 * @see https://raw.githubusercontent.com/GlotPress/GlotPress-WP/develop/locales/locales.php
	 *
	 * @return array<string, array{ 0: int, 1: string }>
	 */
	public static function data_regression(): array {
		return array(
			'ar'    => array( 6, '(n == 0) ? 0 : ((n == 1) ? 1 : ((n == 2) ? 2 : ((n % 100 >= 3 && n % 100 <= 10) ? 3 : ((n % 100 >= 11 && n % 100 <= 99) ? 4 : 5))))' ),
			'ay'    => array( 1, '0' ),
			'bel'   => array( 3, '(n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2)' ),
			'bn-in' => array( 2, 'n > 1' ),
			'cor'   => array( 6, '(n == 0) ? 0 : ((n == 1) ? 1 : (((n % 100 == 2 || n % 100 == 22 || n % 100 == 42 || n % 100 == 62 || n % 100 == 82) || n % 1000 == 0 && (n % 100000 >= 1000 && n % 100000 <= 20000 || n % 100000 == 40000 || n % 100000 == 60000 || n % 100000 == 80000) || n != 0 && n % 1000000 == 100000) ? 2 : ((n % 100 == 3 || n % 100 == 23 || n % 100 == 43 || n % 100 == 63 || n % 100 == 83) ? 3 : ((n != 1 && (n % 100 == 1 || n % 100 == 21 || n % 100 == 41 || n % 100 == 61 || n % 100 == 81)) ? 4 : 5))))' ),
			'cs'    => array( 3, '(n == 1) ? 0 : ((n >= 2 && n <= 4) ? 1 : 2)' ),
			'csb'   => array( 3, 'n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2' ),
			'cy'    => array( 4, '(n==1) ? 0 : (n==2) ? 1 : (n != 8 && n != 11) ? 2 : 3' ),
			'dsb'   => array( 4, '(n % 100 == 1) ? 0 : ((n % 100 == 2) ? 1 : ((n % 100 == 3 || n % 100 == 4) ? 2 : 3))' ),
			'ga'    => array( 5, '(n == 1) ? 0 : ((n == 2) ? 1 : ((n >= 3 && n <= 6) ? 2 : ((n >= 7 && n <= 10) ? 3 : 4)))' ),
			'gd'    => array( 4, '(n == 1 || n == 11) ? 0 : ((n == 2 || n == 12) ? 1 : ((n >= 3 && n <= 10 || n >= 13 && n <= 19) ? 2 : 3))' ),
			'ike'   => array( 3, '(n == 1) ? 0 : ((n == 2) ? 1 : 2)' ),
			'is'    => array( 2, 'n % 10 != 1 || n % 100 == 11' ),
			'lt'    => array( 3, '(n % 10 == 1 && (n % 100 < 11 || n % 100 > 19)) ? 0 : ((n % 10 >= 2 && n % 10 <= 9 && (n % 100 < 11 || n % 100 > 19)) ? 1 : 2)' ),
			'lv'    => array( 3, '(n % 10 == 0 || n % 100 >= 11 && n % 100 <= 19) ? 0 : ((n % 10 == 1 && n % 100 != 11) ? 1 : 2)' ),
			'me'    => array( 3, '(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)' ),
			'mlt'   => array( 4, '(n == 1) ? 0 : ((n == 0 || n % 100 >= 2 && n % 100 <= 10) ? 1 : ((n % 100 >= 11 && n % 100 <= 19) ? 2 : 3))' ),
			'pl'    => array( 3, '(n == 1) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2)' ),
			'ro'    => array( 3, '(n == 1) ? 0 : ((n == 0 || n % 100 >= 2 && n % 100 <= 19) ? 1 : 2)' ),
			'szl'   => array( 3, '(n==1 ? 0 : n%10>=2 && n%10<=4 && n%100==20 ? 1 : 2)' ),
			'zgh'   => array( 2, 'n >= 2 && (n < 11 || n > 99)' ),
		);
	}

	/**
	 * @ticket 41562
	 * @dataProvider data_simple
	 */
	public function test_simple( $expression, $expected ) {
		$plural_forms = new Plural_Forms( $expression );
		$actual       = array();
		foreach ( array_keys( $expected ) as $num ) {
			$actual[ $num ] = $plural_forms->get( $num );
		}

		$this->assertSame( $expected, $actual );
	}

	public static function data_simple() {
		return array(
			array(
				// Simple equivalence.
				'n != 1',
				array(
					-1 => 1,
					0  => 1,
					1  => 0,
					2  => 1,
					5  => 1,
					10 => 1,
				),
			),
			array(
				// Ternary.
				'n ? 1 : 2',
				array(
					-1 => 1,
					0  => 2,
					1  => 1,
					2  => 1,
				),
			),
			array(
				// Comparison.
				'n > 1 ? 1 : 2',
				array(
					-2 => 2,
					-1 => 2,
					0  => 2,
					1  => 2,
					2  => 1,
					3  => 1,
				),
			),
			array(
				'n > 1 ? n > 2 ? 1 : 2 : 3',
				array(
					-2 => 3,
					-1 => 3,
					0  => 3,
					1  => 3,
					2  => 2,
					3  => 1,
					4  => 1,
				),
			),
		);
	}

	/**
	 * Ensures that an exception is thrown when an invalid plural form is encountered.
	 *
	 * @ticket 41562
	 * @dataProvider data_exceptions
	 */
	public function test_exceptions( $expression, $expected_message, $call_get ) {
		$this->expectException( 'Exception' );
		$this->expectExceptionMessage( $expected_message );

		$plural_forms = new Plural_Forms( $expression );
		if ( $call_get ) {
			$plural_forms->get( 1 );
		}
	}

	public function data_exceptions() {
		return array(
			array(
				'n # 2',              // Invalid expression to parse.
				'Unknown symbol "#"', // Expected exception message.
				false,                // Whether to call the get() method or not.
			),
			array(
				'n & 1',
				'Unknown operator "&"',
				false,
			),
			array(
				'((n)',
				'Mismatched parentheses',
				false,
			),
			array(
				'(n))',
				'Mismatched parentheses',
				false,
			),
			array(
				'n : 2',
				'Missing starting "?" ternary operator',
				false,
			),
			array(
				'n ? 1',
				'Unknown operator "?"',
				true,
			),
			array(
				'n n',
				'Too many values remaining on the stack',
				true,
			),
		);
	}

	/**
	 * @ticket 41562
	 */
	public function test_cache() {
		$mock = $this->getMockBuilder( 'Plural_Forms' )
			->setMethods( array( 'execute' ) )
			->setConstructorArgs( array( 'n != 1' ) )
			->getMock();

		$mock->expects( $this->once() )
			->method( 'execute' )
			->with( $this->identicalTo( 2 ) )
			->willReturn( 1 );

		$first  = $mock->get( 2 );
		$second = $mock->get( 2 );
		$this->assertSame( $first, $second );
	}
}
