<?php

/**
 * @group formatting
 * @group emoji
 *
 * @covers ::translate_smiley
 */
class Tests_Formatting_TranslateSmiley extends WP_UnitTestCase {
	/**
	 * @dataProvider data_translate_smiley_should_return_empty_string
	 *
	 * @ticket 54827
	 *
	 * @param string $smiley A string to be translated.
	 */
	public function test_translate_smiley_should_return_empty_string( $smiley ) {
		$this->assertSame( '', translate_smiley( $smiley ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_translate_smiley_should_return_empty_string() {
		return array(
			'empty string' => array( 'smiley' => '' ),
			'null'         => array( 'smiley' => null ),
		);
	}
}
