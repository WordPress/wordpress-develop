<?php

/**
 * @group formatting
 */
class Tests_Formatting_wpHtmlSplit extends WP_UnitTestCase {

	/**
	 * Basic functionality goes here.
	 *
	 * @dataProvider data_basic_features
	 */
	public function test_basic_features( $input, $output ) {
		return $this->assertSame( $output, wp_html_split( $input ) );
	}

	public function data_basic_features() {
		return array(
			array(
				'abcd efgh',
				array( 'abcd efgh' ),
			),
			array(
				'abcd <html> efgh',
				array( 'abcd ', '<html>', ' efgh' ),
			),
			array(
				'abcd <!-- <html> --> efgh',
				array( 'abcd ', '<!-- <html> -->', ' efgh' ),
			),
			array(
				'abcd <![CDATA[ <html> ]]> efgh',
				array( 'abcd ', '<![CDATA[ <html> ]]>', ' efgh' ),
			),
		);
	}

	/**
	 * Automated performance testing of the main regex.
	 *
	 * @dataProvider data_whole_posts
	 */
	public function test_pcre_performance( $input ) {
		$regex  = get_html_split_regex();
		$result = benchmark_pcre_backtracking( $regex, $input, 'split' );
		return $this->assertLessThan( 200, $result );
	}

	public function data_whole_posts() {
		require_once DIR_TESTDATA . '/formatting/whole-posts.php';
		return data_whole_posts();
	}
}
