<?php

/**
 * Tests the wp_json_encode() function.
 *
 * @group functions
 *
 * @covers ::wp_json_encode
 */
class Tests_Functions_WpJsonEncode extends WP_UnitTestCase {

	/**
	 * Tests basic JSON encoding.
	 *
	 * @ticket 65652
	 *
	 * @dataProvider data_wp_json_encode
	 *
	 * @param mixed  $data     Data to encode.
	 * @param string $expected Expected JSON string.
	 */
	public function test_wp_json_encode( $data, $expected ) {
		$this->assertSame( $expected, wp_json_encode( $data ) );
	}

	/**
	 * Data provider for test_wp_json_encode().
	 *
	 * @return array<string, array{
	 *     data:     mixed,
	 *     expected: string,
	 * }>
	 */
	public function data_wp_json_encode(): array {
		return array(
			'simple array'  => array(
				array(
					'a' => 1,
					'b' => 2
				),
				'{"a":1,"b":2}',
			),
			'nested object' => array(
				(object) array( 'a' => array( 1, 2 ) ),
				'{"a":[1,2]}',
			),
			'string'        => array(
				'hello world',
				'"hello world"',
			),
			'integer'       => array(
				123,
				'123',
			),
		);
	}

	/**
	 * Tests encoding with options.
	 *
	 * @ticket 65652
	 */
	public function test_wp_json_encode_options() {
		$data = array( 'a' => 1 );
		$this->assertSame( "{\n    \"a\": 1\n}", wp_json_encode( $data, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Tests encoding with depth.
	 *
	 * @ticket 65652
	 */
	public function test_wp_json_encode_depth() {
		$data = array( 'a' => array( 'b' => 1 ) );
		// Depth 1 should fail to encode the nested array.
		$this->assertFalse( wp_json_encode( $data, 0, 1 ) );
		// Depth 2 should succeed.
		$this->assertSame( '{"a":{"b":1}}', wp_json_encode( $data, 0, 2 ) );
	}

	/**
	 * Tests handling of invalid UTF-8.
	 *
	 * wp_json_encode() attempts to fix invalid UTF-8 by using _wp_json_prepare_data().
	 *
	 * @ticket 65652
	 */
	public function test_wp_json_encode_invalid_utf8() {
		// Invalid UTF-8 sequence.
		$invalid_utf8 = "Invalid\x80UTF8";
		$encoded      = wp_json_encode( $invalid_utf8 );

		// It should not be false, it should have attempted to fix it.
		$this->assertNotFalse( $encoded );
		// In newer PHP versions, json_encode might handle it or it's sanitized to 'Invalid' or similar.
		// WordPress's _wp_json_sanity_check will attempt to clean it.
		$this->assertStringContainsString( 'Invalid', $encoded );
	}
}
