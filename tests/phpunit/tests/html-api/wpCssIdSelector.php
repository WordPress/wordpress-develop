<?php
/**
 * Unit tests covering WP_CSS_ID_Selector functionality.
 *
 * @package WordPress
 *
 * @subpackage HTML-API
 *
 * @since 6.8.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_CSS_ID_Selector
 */
class Tests_HtmlApi_WpCssIdSelector extends WP_UnitTestCase {
	/**
	 * @ticket 62653
	 *
	 * @dataProvider data_id_selectors
	 */
	public function test_parse_id( string $input, ?string $expected = null, ?string $rest = null ) {
		$offset = 0;
		$result = WP_CSS_ID_Selector::parse( $input, $offset );
		if ( null === $expected ) {
			$this->assertNull( $result );
		} else {
			$this->assertSame( $expected, $result->id );
			$this->assertSame( $rest, substr( $input, $offset ) );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_id_selectors(): array {
		return array(
			'valid #_-foo123'             => array( '#_-foo123', '_-foo123', '' ),
			'valid #foo#bar'              => array( '#foo#bar', 'foo', '#bar' ),
			'escaped #\31 23'             => array( '#\\31 23', '123', '' ),
			'with descendant #\31 23 div' => array( '#\\31 23 div', '123', ' div' ),

			// Invalid
			'not ID foo'                  => array( 'foo' ),
			'not ID .bar'                 => array( '.bar' ),
			'not valid #1foo'             => array( '#1foo' ),
		);
	}
}
