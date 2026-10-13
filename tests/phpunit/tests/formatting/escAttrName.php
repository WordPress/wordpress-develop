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
			array( 'data-ñame' ),
			array( 'data-😀' ),
			array( 'attrф' ),
			array( 'data-名字' ),
			array( 'data-@!$%&()+,;?^`{|}~' ),
			array( "data-\u{00A0}name" ),
			array( "data-\u{FDCF}\u{FDF0}\u{FFFD}\u{10000}\u{1FFFD}\u{20000}\u{10FFFD}" ),
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
			array( 'data-' . "\xc0\x80" . 'foo', '' ),
			array( "caf\xE9", '' ),
			array( "data-\xED\xA0\x80", '' ),
			array( "data-\xF4\x90\x80\x80", '' ),
			array( "data-\u{007F}\u{0080}\u{009F}name", 'data-name' ),
			array( "data-\u{FDD0}\u{FDEF}\u{FFFE}\u{FFFF}name", 'data-name' ),
			array( "data-\u{1FFFE}\u{10FFFF}name", 'data-name' ),
			array( "\u{FDD0}\u{007F}", '' ),
		);
	}

	public function test_filter_is_applied() {
		add_filter( 'esc_attr_name', array( $this, 'filter_attr_name' ), 10, 2 );

		$result = esc_attr_name( 'data-ñame foo' );

		remove_filter( 'esc_attr_name', array( $this, 'filter_attr_name' ) );

		$this->assertSame( 'filtered-data-ñamefoo', $result );
	}

	public function filter_attr_name( $safe_text, $text ) {
		$this->assertSame( 'data-ñamefoo', $safe_text );
		$this->assertSame( 'data-ñame foo', $text );

		return 'filtered-' . $safe_text;
	}
}
