<?php

/**
 * @group formatting
 * @group functions.php
 */
class Tests_Functions_AddMagicQuotes extends WP_UnitTestCase {
	/**
	 * @ticket 48605
	 */
	function test_add_magic_quotes() {
		$data = [
			'sample string',
			52,
			true,
			false,
			null,
			"This is a 'string'",
			[
				1,
				false,
				true,
				'This is "another" string',
			],
		];
		$magic_quoted = add_magic_quotes( $data );
		$expected     = [
			'sample string',
			52,
			true,
			false,
			null,
			"This is a \'string\'",
			[
				1,
				false,
				true,
				'This is \"another\" string',
			]
		];
		$this->assertEquals( $expected, $magic_quoted );
	}
}
