<?php

/**
 * Tests for rawurlencode_deep() and urldecode_deep().
 *
 * @group formatting
 *
 * @covers ::rawurlencode_deep
 * @covers ::urldecode_deep
 */
class Tests_Formatting_UrlencodeDeep extends WP_UnitTestCase {

	/**
	 * Tests that rawurlencode_deep() encodes a single string.
	 *
	 * @ticket 65819
	 */
	public function test_rawurlencode_deep_string() {
		$this->assertSame( 'foo%20bar%26baz%3D1', rawurlencode_deep( 'foo bar&baz=1' ) );
	}

	/**
	 * Tests that rawurlencode_deep() recursively encodes an array of strings.
	 *
	 * @ticket 65819
	 */
	public function test_rawurlencode_deep_array() {
		$input    = array(
			'foo bar',
			'nested' => array(
				'key space' => 'val space',
			),
		);
		$expected = array(
			'foo%20bar',
			'nested' => array(
				'key space' => 'val%20space',
			),
		);

		$this->assertSame( $expected, rawurlencode_deep( $input ) );
	}

	/**
	 * Tests that rawurlencode_deep() encodes object properties.
	 *
	 * @ticket 65819
	 */
	public function test_rawurlencode_deep_object() {
		$input    = (object) array( 'prop' => 'hello world' );
		$expected = (object) array( 'prop' => 'hello%20world' );

		// Use assertEquals to compare object properties by value.
		$this->assertEquals( $expected, rawurlencode_deep( $input ) );
	}

	/**
	 * Tests that urldecode_deep() decodes a single string.
	 *
	 * @ticket 65819
	 */
	public function test_urldecode_deep_string() {
		$this->assertSame( 'foo bar&baz=1', urldecode_deep( 'foo+bar%26baz%3D1' ) );
		$this->assertSame( 'foo bar', urldecode_deep( 'foo%20bar' ) );
	}

	/**
	 * Tests that urldecode_deep() recursively decodes an array of strings.
	 *
	 * @ticket 65819
	 */
	public function test_urldecode_deep_array() {
		$input    = array(
			'foo+bar',
			'nested' => array(
				'key space' => 'val%20space',
			),
		);
		$expected = array(
			'foo bar',
			'nested' => array(
				'key space' => 'val space',
			),
		);

		$this->assertSame( $expected, urldecode_deep( $input ) );
	}

	/**
	 * Tests that urldecode_deep() decodes object properties.
	 *
	 * @ticket 65819
	 */
	public function test_urldecode_deep_object() {
		$input    = (object) array( 'prop' => 'hello+world' );
		$expected = (object) array( 'prop' => 'hello world' );

		// Use assertEquals to compare object properties by value.
		$this->assertEquals( $expected, urldecode_deep( $input ) );
	}
}
