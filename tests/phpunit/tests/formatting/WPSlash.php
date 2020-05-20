<?php

/**
 * @group formatting
 */
class Tests_Formatting_WPSlash extends WP_UnitTestCase {

	/**
	 * @dataProvider data_wp_slash
	 *
	 * @ticket 42195
	 *
	 * @param string $value
	 * @param string $expected
	 */
	public function test_wp_slash_with( $value, $expected ) {
		$this->assertSame( $expected, wp_slash( $value ) );
	}

	/**
	 * Data provider for test_wp_slash().
	 *
	 * @return array {
	 *     @type array {
	 *         @type mixed  $value    The value passed to wp_slash().
	 *         @type string $expected The expected output of wp_slash().
	 *     }
	 * }
	 */
	public function data_wp_slash() {
		return [
			[ 123, 123 ],
			[ 123.4, 123.4 ],
			[ true, true ],
			[ false, false ],
			[
				[
					'hello',
					null,
					'"string"',
					125.41
				],
				[
					'hello',
					null,
					'\"string\"',
					125.41
				],
			],
			[ "first level 'string'", "first level \'string\'" ]
		];
	}
}
