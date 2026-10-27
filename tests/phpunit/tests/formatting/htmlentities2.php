<?php

/**
 * Tests for htmlentities2().
 *
 * @group formatting
 *
 * @covers ::htmlentities2
 */
class Tests_Formatting_Htmlentities2 extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_htmlentities2
	 *
	 * @param string $text     Input text.
	 * @param string $expected Expected converted text.
	 */
	public function test_htmlentities2( $text, $expected ) {
		$this->assertSame( $expected, htmlentities2( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_htmlentities2() {
		return array(
			'plain text'               => array( 'hello world', 'hello world' ),
			'encodes angle brackets'   => array( '<b>bold</b>', '&lt;b&gt;bold&lt;/b&gt;' ),
			'encodes double quotes'    => array( '"quoted"', '&quot;quoted&quot;' ),
			'encodes bare ampersand'   => array( 'A & B', 'A &amp; B' ),
			'preserves amp entity'     => array( 'A &amp; B', 'A &amp; B' ),
			'preserves numeric entity' => array( '&#38;', '&#38;' ),
		);
	}
}
