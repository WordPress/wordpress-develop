<?php

/**
 * @group formatting
 *
 * @covers ::esc_attr_name
 */
class Tests_Formatting_EscAttrName extends WP_UnitTestCase {

	/**
	 * @dataProvider data_valid_attribute_names
	 *
	 * @param string $attr Valid HTML attribute name.
	 */
	public function test_valid_attribute_names_are_unchanged( $attr ) {
		$this->assertSame( $attr, esc_attr_name( $attr ) );
	}

	/**
	 * @return array[]
	 */
	public function data_valid_attribute_names() {
		return array(
			array( 'class' ),
			array( 'data-my-value' ),
			array( 'aria-label' ),
			array( 'my_attr' ),
			array( 'attr123' ),
			array( 'name[key]' ),
			array( 'xml:lang' ),
			array( 'x-my.attr' ),
			array( 'MyAttr' ),
			array( 'data-foo_bar.baz[0]' ),
		);
	}

	/**
	 * @dataProvider data_forbidden_chars
	 *
	 * @param string $input    Attribute name containing forbidden characters.
	 * @param string $expected Expected output after escaping.
	 */
	public function test_forbidden_chars_are_removed( $input, $expected ) {
		$this->assertSame( $expected, esc_attr_name( $input ) );
	}

	/**
	 * @return array[]
	 */
	public function data_forbidden_chars() {
		return array(
			array( 'foo bar', 'foobar' ),
			array( '"data-foo"', 'data-foo' ),
			array( "'data-foo'", 'data-foo' ),
			array( 'foo>bar', 'foobar' ),
			array( 'foo<bar', 'foobar' ),
			array( 'foo/bar', 'foobar' ),
			array( 'foo=bar', 'foobar' ),
			array( 'foo ="bar\'', 'foobar' ),
			array( '"attr', 'attr' ),
			array( 'attr/', 'attr' ),
			array( "\tdata-foo", 'data-foo' ),
			array( "data\nfoo", 'datafoo' ),
			array( "\fdata-foo", 'data-foo' ),
			array( "data\rfoo", 'datafoo' ),
			array( "data\x00foo", 'datafoo' ),
			array( "\t data-foo \n", 'data-foo' ),
			array( '', '' ),
			array( ' "\'><=/', '' ),
			array( 'data-' . "\xc0\x80" . 'foo', 'data-foo' ),
			array( 'data-ñame', 'data-ame' ),
			array( 'data-😀', 'data-' ),
			array( 'attrф', 'attr' ),
		);
	}

	public function test_filter_is_applied() {
		add_filter( 'esc_attr_name', array( $this, 'filter_attr_name' ), 10, 2 );

		$result = esc_attr_name( 'data-foo' );

		remove_filter( 'esc_attr_name', array( $this, 'filter_attr_name' ) );

		$this->assertSame( 'filtered-data-foo', $result );
	}

	public function filter_attr_name( $safe_text, $text ) {
		return 'filtered-' . $safe_text;
	}
}
