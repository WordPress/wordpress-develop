<?php
/**
 * @group formatting
 *
 * @covers ::normalize_whitespace
 */
class Tests_Formatting_NormalizeWhitespace extends WP_UnitTestCase {

	/**
	 * Validate the normalize_whitespace function
	 *
	 * @dataProvider data_normalize_whitespace
	 */
	public function test_normalize_whitespace( $in_str, $exp_str ) {
		$this->assertSame( $exp_str, normalize_whitespace( $in_str ) );
	}

	/**
	 * WhitespaceTest Content DataProvider
	 *
	 * array( input_txt, converted_output_txt)
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
