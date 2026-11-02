<?php

/**
 * Tests for urlencode_deep(), rawurlencode_deep(), and urldecode_deep().
 *
 * @group formatting
 * @ticket 22300
 *
 * @covers ::urlencode_deep
 * @covers ::rawurlencode_deep
 * @covers ::urldecode_deep
 */
class Tests_Formatting_UrlencodeDeep extends WP_UnitTestCase {

	/**
	 * Tests the urlencode_deep() function pair by pair.
	 *
	 * @dataProvider data_urlencode_deep
	 *
	 * @param string $input
	 * @param string $expected
	 */
	public function test_urlencode_deep_should_encode_individual_value( $input, $expected ) {
		$this->assertSame( $expected, urlencode_deep( $input ) );
	}

	/**
	 * Data provider.
	 */
	public static function data_urlencode_deep() {
		return array(
			array( 'qwerty123456', 'qwerty123456' ),
			array( '|!"£$%&/()=?', '%7C%21%22%C2%A3%24%25%26%2F%28%29%3D%3F' ),
			array( '^é*ç°§;:_-.,', '%5E%C3%A9%2A%C3%A7%C2%B0%C2%A7%3B%3A_-.%2C' ),
			array( 'abc123 @#[]€', 'abc123+%40%23%5B%5D%E2%82%AC' ),
			array( 'abc123 @#[]€', urlencode( 'abc123 @#[]€' ) ),
		);
	}

	/**
	 * Tests the whole array as input.
	 */
	public function test_urlencode_deep_should_encode_all_values_in_array() {
		$data = $this->data_urlencode_deep();

		$actual   = wp_list_pluck( $data, 0 );
		$expected = wp_list_pluck( $data, 1 );

		$this->assertSame( $expected, urlencode_deep( $actual ) );
	}

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
