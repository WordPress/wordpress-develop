<?php

/**
 * @group formatting
 */
class Tests_Formatting_wpReplaceInHtmlTags extends WP_UnitTestCase {
	/**
	 * Check for expected behavior of new function wp_replace_in_html_tags().
	 *
	 * @dataProvider data_wp_replace_in_html_tags
	 */
	public function test_wp_replace_in_html_tags( $input, $output ) {
		return $this->assertSame( $output, wp_replace_in_html_tags( $input, array( "\n" => ' ' ) ) );
	}

	public function data_wp_replace_in_html_tags() {
		return array(
			array(
				"Hello \n World",
				"Hello \n World",
			),
			array(
				"<Hello \n World>",
				'<Hello   World>',
			),
			array(
				"<!-- Hello \n World -->",
				'<!-- Hello   World -->',
			),
			array(
				"<!-- Hello <\n> World -->",
				'<!-- Hello < > World -->',
			),
		);
	}
}

