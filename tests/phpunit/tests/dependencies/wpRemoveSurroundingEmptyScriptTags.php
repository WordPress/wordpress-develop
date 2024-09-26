<?php

/**
 * Test wp_remove_surrounding_empty_script_tags().
 *
 * @group dependencies
 * @group scripts
 * @ticket 58664
 * @covers ::wp_remove_surrounding_empty_script_tags
 */
class Tests_Functions_wpRemoveSurroundingEmptyScriptTags extends WP_UnitTestCase {

	/**
	 * Data provider for test.
	 *
	 * @return array
	 */
	public function get_data_to_test_wp_remove_surrounding_empty_script_tags() {
		$error_js = 'console.error("Function wp_remove_surrounding_empty_script_tags() used incorrectly in PHP. Expected string to start with script tag (without attributes) and end with script tag, with optional whitespace.")';
		return array(
			'basic_case'            => array(
				'<script>alert("hello")</script>',
				'alert("hello")',
				false,
			),
			'BASIC_CASE'            => array(
				'<SCRIPT>alert("hello")</SCRIPT>',
				'alert("hello")',
				false,
			),
			'whitespace_basic_case' => array(
				'  <script>alert("hello")</script>  ',
				'alert("hello")',
				false,
			),
			'missing_tags'          => array(
				'alert("hello")',
				$error_js,
				true,
			),
			'missing_start_tag'     => array(
				'alert("hello")</script>',
				$error_js,
				true,
			),
			'missing_end_tag'       => array(
				'<script>alert("hello")',
				$error_js,
				true,
			),
			'erroneous attributes'  => array(
				'<script type="text/javascript">alert("hello")</script>',
				$error_js,
				true,
			),
		);
	}

	/**
	 * Test scenarios for wp_remove_surrounding_empty_script_tags().
	 *
	 * @dataProvider get_data_to_test_wp_remove_surrounding_empty_script_tags
	 *
	 * @param string $input                 Input.
	 * @param string $expected              Expected.
	 * @param bool   $expect_doing_it_wrong Whether input is _doing_it_wrong().
	 */
	public function test_wp_remove_surrounding_empty_script_tags( $input, $expected, $expect_doing_it_wrong ) {
		if ( $expect_doing_it_wrong ) {
			$this->setExpectedIncorrectUsage( 'wp_remove_surrounding_empty_script_tags' );
		}

		$this->assertSame(
			$expected,
			wp_remove_surrounding_empty_script_tags( $input )
		);
	}
}
