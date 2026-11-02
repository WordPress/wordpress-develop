<?php

/**
 * Tests for _deep_replace().
 *
 * @group formatting
 *
 * @covers ::_deep_replace
 */
class Tests_Formatting_DeepReplace extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_deep_replace
	 *
	 * @param string|string[] $search  Value to search for.
	 * @param string          $subject Subject to search.
	 * @param string          $expected Expected result.
	 */
	public function test_deep_replace( $search, $subject, $expected ) {
		$this->assertSame( $expected, _deep_replace( $search, $subject ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_deep_replace() {
		return array(
			'no match'                 => array( 'x', 'hello', 'hello' ),
			'single occurrence'        => array( 'bad', 'one bad word', 'one  word' ),
			'nested repetitions'       => array( '../', '....//....//file', 'file' ),
			'array of search strings'  => array( array( 'a', 'b' ), 'abacab', 'c' ),
			'empty subject'            => array( 'x', '', '' ),
			'casts non-string subject' => array( '1', 111, '' ),
		);
	}
}
