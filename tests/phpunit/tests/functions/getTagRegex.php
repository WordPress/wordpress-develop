<?php
/**
 * Tests for the get_tag_regex function.
 *
 * @group functions.php
 *
 * @covers ::get_tag_regex
 */#
class Tests_functions_getTagRegex extends WP_UnitTestCase {

	/**
	 * @ticket 59791
	 *
	 * @dataProvider data_get_tag_regex
	 */
	public function test_get_tag_regex_empty( $tag, $expected ) {
		$this->assertEquals( $expected, get_tag_regex( $tag ) );
	}

	/**
	 * @ticket 59791
	 */
	public function data_get_tag_regex() {

		return array(
			array( '', '' ),
			array( 'a', '<a[^<]*(?:>[\s\S]*<\/a>|\s*\/>)' ),
			array( 'video', '<video[^<]*(?:>[\s\S]*<\/video>|\s*\/>)' ),
			array( '<a>', '<a[^<]*(?:>[\s\S]*<\/a>|\s*\/>)' ),
			array( ' a video', '<avideo[^<]*(?:>[\s\S]*<\/avideo>|\s*\/>)' ),
		);
	}
}
