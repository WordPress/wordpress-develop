<?php
/**
 * @group kses
 *
 * @covers ::wp_kses_no_null
 */
class Tests_KSES_wpKsesNoNull extends WP_UnitTestCase {

	/**
	 * Test that wp_kses_no_null() handles null values correctly.
	 *
	 * @ticket 62785
	 *
	 * @dataProvider data_wp_kses_no_null_with_null_values
	 *
	 * @param mixed  $content        The content to test.
	 * @param string $expected       Expected output.
	 * @param string $message        Message to display on failure.
	 */
	public function test_wp_kses_no_null_with_null_values( $content, $expected, $message ) {
		$this->assertSame( $expected, wp_kses_no_null( $content ), $message );
	}

	/**
	 * Data provider for test_wp_kses_no_null_with_null_values().
	 *
	 * @return array Test data.
	 */
	public function data_wp_kses_no_null_with_null_values() {
		return array(
			'null_value'             => array(
				'content'  => null,
				'expected' => '',
				'message'  => 'wp_kses_no_null() should convert null to empty string',
			),
			'string_with_null_bytes' => array(
				'content'  => "Test\x00String",
				'expected' => 'TestString',
				'message'  => 'wp_kses_no_null() should remove null bytes from string',
			),
			'empty_string'           => array(
				'content'  => '',
				'expected' => '',
				'message'  => 'wp_kses_no_null() should handle empty string',
			),
			'string_without_nulls'   => array(
				'content'  => 'Regular string',
				'expected' => 'Regular string',
				'message'  => 'wp_kses_no_null() should not modify strings without null bytes',
			),
		);
	}
}
