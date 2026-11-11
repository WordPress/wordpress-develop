<?php

/**
 * Tests for build_style_attribute_declarations().
 *
 * @package WordPress
 *
 * @group testsuite
 */
class Tests_Build_Style_Attribute_Declarations extends WP_UnitTestCase {
	/**
	 * @ticket 65738
	 *
	 * @covers ::build_style_attribute_declarations
	 *
	 * @dataProvider data_build_style_attribute_declarations
	 *
	 * @param string        $style    Decoded style attribute value.
	 * @param string[]|null $expected Expected declarations.
	 */
	public function test_build_style_attribute_declarations( $style, $expected ) {
		$this->assertSame( $expected, build_style_attribute_declarations( $style ) );
	}

	/**
	 * Data provider for test_build_style_attribute_declarations().
	 *
	 * @return array<string, array{string, string[]|null}>
	 */
	public function data_build_style_attribute_declarations() {
		return array(
			'empty'                          => array( '', array() ),
			'semicolons and whitespace'      => array( " ;\t; ", array() ),
			'one declaration'                => array( 'color: red', array( 'color:red;' ) ),
			'property name case'             => array( 'COLOR: red', array( 'color:red;' ) ),
			'custom property name case'      => array( '--Foo: a', array( '--Foo:a;' ) ),
			'priority'                       => array( 'color: red ! IMPORTANT', array( 'color:red !important;' ) ),
			'whitespace and comments'        => array( "margin : 1px /* a */ \t 2px ;", array( 'margin:1px 2px;' ) ),
			'whitespace between tokens'      => array( 'color: rgb(1, 2, 3)', array( 'color:rgb(1,2,3);' ) ),
			'comment between tokens'         => array( 'margin: 1px/**/2px', array( 'margin:1px 2px;' ) ),
			'escapes and quotes'             => array( "font-family: 'a\\62 c'", array( 'font-family:"abc";' ) ),
			'url'                            => array( 'background-image: url( https://example.com/a.png )', array( 'background-image:url(https://example.com/a.png);' ) ),
			'blocks left open'               => array( 'color: var(--x, rgb(1 2 3', array( 'color:var(--x,rgb(1 2 3));' ) ),
			'repeated property'              => array( 'color: red; color: blue', array( 'color:red;', 'color:blue;' ) ),
			'empty value'                    => array( 'color:;', array( 'color:;' ) ),
			'stray closing brace'            => array( 'color: red; }', null ),
			'at-rule'                        => array( '@media x; color: red', null ),
			'declaration without a colon'    => array( 'color red; width: 1px', null ),
			'priority without a declaration' => array( 'color: red; !important', null ),
		);
	}
}
