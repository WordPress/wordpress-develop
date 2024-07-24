<?php
/**
 * @group formatting
 *
 * @covers ::normalize_whitespace
 */
class Tests_Formatting_NormalizeWhitespace extends WP_UnitTestCase {

	/**
	 * Tests the the normalize_whitespace() function.
	 *
	 * @dataProvider data_normalize_whitespace
	 */
	public function test_normalize_whitespace( $input, $expected ) {
		$this->assertSame( $expected, normalize_whitespace( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $input    Input content.
	 *         @type string $expected Expected output.
	 *     }
	 * }
	 */
	public function data_normalize_whitespace() {
		return array(
			array(
				'		',
				'',
			),
			array(
				"\rTEST\r",
				'TEST',
			),
			array(
				"\r\nMY TEST CONTENT\r\n",
				'MY TEST CONTENT',
			),
			array(
				"MY\r\nTEST\r\nCONTENT ",
				"MY\nTEST\nCONTENT",
			),
			array(
				"\tMY\rTEST\rCONTENT ",
				"MY\nTEST\nCONTENT",
			),
			array(
				"\tMY\t\t\tTEST\r\t\t\rCONTENT ",
				"MY TEST\n \nCONTENT",
			),
			array(
				"\tMY TEST \t\t\t CONTENT ",
				'MY TEST CONTENT',
			),
		);
	}
}
