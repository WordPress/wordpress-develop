<?php
/**
 * Unit tests covering WP_CSS_Type_Selector functionality.
 *
 * @package WordPress
 *
 * @subpackage HTML-API
 *
 * @since 6.8.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_CSS_Type_Selector
 */
class Tests_HtmlApi_WpCssTypeSelector extends WP_UnitTestCase {
	/**
	 * @ticket 62653
	 *
	 * @dataProvider data_type_selectors
	 */
	public function test_parse_type( string $input, ?string $expected = null, ?string $rest = null ) {
		$offset = 0;
		$result = WP_CSS_Type_Selector::parse( $input, $offset );
		if ( null === $expected ) {
			$this->assertNull( $result );
		} else {
			$this->assertSame( $expected, $result->type );
			$this->assertSame( $rest, substr( $input, $offset ) );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_type_selectors(): array {
		return array(
			'any *'                   => array( '* .class', '*', ' .class' ),
			'a'                       => array( 'a', 'a', '' ),
			'div.class'               => array( 'div.class', 'div', '.class' ),
			'custom-type#id'          => array( 'custom-type#id', 'custom-type', '#id' ),

			// Invalid
			'Invalid: (empty string)' => array( '' ),
			'Invalid: #id'            => array( '#id' ),
			'Invalid: .class'         => array( '.class' ),
			'Invalid: [attr]'         => array( '[attr]' ),
		);
	}
}
