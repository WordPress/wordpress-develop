<?php

/**
* @group formatting
*
* @covers \wp_normalize_escaped_html_text()
*/
class Tests_Formatting_NormalizeEscapedHtmlText extends WP_UnitTestCase {
	/**
	 * Ensures that HTML test is properly normalized.
	 *
	 * @dataProvider data_example_datasets
	 *
	 * @param string $context
	 * @param string $text
	 * @param string $expected
	 */
	public function test_example_datasets( $context, $text, $expected ) {
		$this->assertEquals(
			$expected,
			wp_normalize_escaped_html_text( $context, $text )
		);
	}

	public static function data_example_datasets() {
		return array(
			array( 'attribute', 'test', 'test' ),
			array( 'attribute', 'test & done', 'test &amp; done' ),
			array( 'attribute', '&#XFe; is not iron', '&#xFE; is not iron' ),
			array( 'attribute', 'spec > guess', 'spec &gt; guess' ),
			array( 'attribute', 'art & copy', 'art &amp; copy' ),
			array( 'attribute', '&#x1F170', '&#x1F170;' ),
			array( 'attribute', '&#x1F170 ', '&#x1F170; ' ),

			array( 'data', 'test', 'test' ),
			array( 'data', 'test & done', 'test &amp; done' ),
			array( 'data', '&#XFe; is not iron', '&#xFE; is not iron' ),
			array( 'data', 'spec > guess', 'spec &gt; guess' ),
			array( 'data', 'art & copy', 'art &amp; copy' ),
			array( 'data', '&#x1F170', '&#x1F170;' ),
			array( 'data', '&#x1F170 ', '&#x1F170; ' ),

			// The “ambiguous ampersand” has different rules in the attribute value and data states.
			array( 'attribute', '&notmyproblem', '&amp;notmyproblem' ),
			array( 'data', '&notmyproblem', '&not;myproblem' ),

			// Certain characters should remain plaintext.
			array( 'attribute', 'eat &#x000033; apples', 'eat 3 apples' ),
			array( 'data', 'eat &#x000033; apples', 'eat 3 apples' ),
			array( 'data', '<&#x00073;cr&#0105pt&gt;', '&lt;script&gt;' ),
			array( 'attribute', '&#x6a;avascript&#58alert&#40;&#x0000007b"test&quot;&#125;&#41;', 'javascript:alert&#40;&#x7B;&quot;test&quot;&#125;&#41;' ),

			// Syntax characters should be represented uniformly.
			array( 'attribute', '&#X3CIMG&#00062', '&lt;IMG&gt;' ),
			array( 'data', '&#X3CIMG&#00062', '&lt;IMG&gt;' ),
		);
	}
}
