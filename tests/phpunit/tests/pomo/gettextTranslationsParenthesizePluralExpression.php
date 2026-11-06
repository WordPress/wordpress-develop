<?php

/**
 * Tests for the Gettext_Translations::parenthesize_plural_exression() method.
 *
 * @group pomo
 *
 * @covers Gettext_Translations::parenthesize_plural_exression
 */
class Tests_POMO_GettextTranslationsParenthesizePluralExpression extends WP_UnitTestCase {

	/**
	 * Tests that the parts of a conditional expression are wrapped in parentheses.
	 *
	 * @ticket 66511
	 *
	 * @dataProvider data_expressions
	 *
	 * @param string $expression Expression.
	 * @param string $expected   Expected expression.
	 */
	public function test_should_parenthesize_the_conditional_expression( $expression, $expected ) {
		$translations = new Gettext_Translations();

		$this->assertSame( $expected, $translations->parenthesize_plural_exression( $expression ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_expressions() {
		return array(
			'without a condition' => array( 'n != 1', 'n != 1' ),
			'a number'            => array( '0', '0' ),
			'one condition'       => array( 'n==1 ? 0 : 1', 'n==1  ? ( 0 ) : ( 1)' ),
			'two conditions'      => array( 'n==1 ? 0 : n==2 ? 1 : 2', 'n==1  ? ( 0 ) : ( n==2  ? ( 1 ) : ( 2))' ),
			'three conditions'    => array( 'n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : 3', 'n==0  ? ( 0 ) : ( n==1  ? ( 1 ) : ( n==2  ? ( 2 ) : ( 3)))' ),
			'empty string'        => array( '', '' ),
		);
	}

	/**
	 * Tests that a semicolon closes the open parentheses, and that the final one is removed.
	 *
	 * @ticket 66511
	 */
	public function test_should_close_the_parentheses_at_a_semicolon() {
		$translations = new Gettext_Translations();

		$this->assertSame(
			'n==1  ? ( 0 ) : ( 1); n==2  ? ( 3 ) : ( 4)',
			$translations->parenthesize_plural_exression( 'n==1 ? 0 : 1; n==2 ? 3 : 4' )
		);
	}
}
