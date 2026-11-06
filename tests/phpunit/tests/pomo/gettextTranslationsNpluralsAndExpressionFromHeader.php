<?php

/**
 * Tests for the Gettext_Translations::nplurals_and_expression_from_header() method.
 *
 * @group pomo
 *
 * @covers Gettext_Translations::nplurals_and_expression_from_header
 */
class Tests_POMO_GettextTranslationsNpluralsAndExpressionFromHeader extends WP_UnitTestCase {

	/**
	 * Tests that the number of plural forms and the expression are read from the header.
	 *
	 * @ticket 66511
	 *
	 * @dataProvider data_headers
	 *
	 * @param string $header   Plural-Forms header.
	 * @param array  $expected Expected number of forms and expression.
	 */
	public function test_should_read_the_header( $header, $expected ) {
		$translations = new Gettext_Translations();

		$this->assertSame( $expected, $translations->nplurals_and_expression_from_header( $header ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_headers() {
		return array(
			'english'                     => array( 'nplurals=2; plural=n != 1;', array( 2, 'n != 1;' ) ),
			'without the final semicolon' => array( 'nplurals=2; plural=n != 1', array( 2, 'n != 1' ) ),
			'one form'                    => array( 'nplurals=1; plural=0;', array( 1, '0;' ) ),
			'three forms'                 => array( 'nplurals=3; plural=n%10==1 && n%100!=11 ? 0 : 1;', array( 3, 'n%10==1 && n%100!=11 ? 0 : 1;' ) ),
			'several digits'              => array( 'nplurals=10; plural=n;', array( 10, 'n;' ) ),
			'spaces around the signs'     => array( '  nplurals = 2 ;  plural =  n > 1  ', array( 2, 'n > 1' ) ),
			'tabs after the semicolon'    => array( "nplurals=2;\tplural=n>1", array( 2, 'n>1' ) ),
		);
	}

	/**
	 * Tests that the default is returned when the header is not valid.
	 *
	 * @ticket 66511
	 *
	 * @dataProvider data_invalid_headers
	 *
	 * @param string $header Header that is not valid.
	 */
	public function test_should_return_the_default_for_a_header_that_is_not_valid( $header ) {
		$translations = new Gettext_Translations();

		$this->assertSame( array( 2, 'n != 1' ), $translations->nplurals_and_expression_from_header( $header ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_headers() {
		return array(
			'empty string'                    => array( '' ),
			'no plural expression'            => array( 'nplurals=2;' ),
			'no number of forms'              => array( 'plural=n != 1;' ),
			'number of forms is not a number' => array( 'nplurals=two; plural=n != 1;' ),
			'no space after the semicolon'    => array( 'nplurals=2;plural=n != 1;' ),
			'other header'                    => array( 'Content-Type: text/plain' ),
			'wrong order'                     => array( 'plural=n != 1; nplurals=2;' ),
		);
	}
}
