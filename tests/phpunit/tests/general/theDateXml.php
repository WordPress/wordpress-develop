<?php

/**
 * Tests for the `the_date_xml()` function.
 *
 * @group general
 * @group template
 * @group post
 *
 * @covers ::the_date_xml
 */
class Tests_General_TheDateXml extends WP_UnitTestCase {

	/**
	 * Tests that the_date_xml() outputs the formatted date for the global post.
	 *
	 * @ticket 65819
	 */
	public function test_the_date_xml_should_output_formatted_date() {
		global $post;

		$post = self::factory()->post->create_and_get(
			array(
				'post_date' => '2026-05-15 12:30:00',
			)
		);

		$this->expectOutputString( '2026-05-15' );
		the_date_xml();
	}
}
