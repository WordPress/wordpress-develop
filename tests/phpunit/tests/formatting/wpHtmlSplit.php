<?php

/**
 * @group formatting
 *
 * @covers ::wp_html_split
 */
class Tests_Formatting_wpHtmlSplit extends WP_UnitTestCase {

	/**
	 * Basic functionality goes here.
	 *
	 * @dataProvider data_basic_features
	 *
	 * @param non-falsy-string       $input
	 * @param list<non-falsy-string> $output
	 */
	public function test_basic_features( string $input, array $output ): void {
		$this->assertSame( $output, wp_html_split( $input ) );
	}

	/**
	 * Data provider for {@see self::test_basic_features()}.
	 *
	 * @return list<array{ 0: non-falsy-string, 1: list<non-falsy-string> }>
	 */
	public function data_basic_features(): array {
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
	 * Ensure that > inside quoted attribute values does not cause premature
	 * tag splitting in wp_html_split().
	 *
	 * @ticket 63997
	 * @dataProvider data_gt_in_quoted_attribute_values
	 *
	 * @param non-falsy-string $input
	 * @param list<string>     $output
	 */
	public function test_gt_in_quoted_attribute_values( string $input, array $output ): void {
		$this->assertSame( $output, wp_html_split( $input ) );
	}

	/**
	 * Data provider for {@see self::test_gt_in_quoted_attribute_values()}.
	 *
	 * @return list<array{ 0: non-falsy-string, 1: list<string> }>
	 */
	public function data_gt_in_quoted_attribute_values(): array {
		return array(
			array(
				'<div data-test="a > b">content</div>',
				array( '', '<div data-test="a > b">', 'content', '</div>', '' ),
			),
			array(
				'<div data-test=\'a > b\'>content</div>',
				array( '', '<div data-test=\'a > b\'>', 'content', '</div>', '' ),
			),
			array(
				'<div data-test="a > b" data-other="c &gt; d">content</div>',
				array( '', '<div data-test="a > b" data-other="c &gt; d">', 'content', '</div>', '' ),
			),
		);
	}

	/**
	 * Automated performance testing of the main regex.
	 *
	 * @dataProvider data_whole_posts
	 *
	 * @covers ::get_html_split_regex
	 *
	 * @param non-falsy-string $input
	 */
	public function test_pcre_performance( string $input ): void {
		$regex  = get_html_split_regex();
		$result = benchmark_pcre_backtracking( $regex, $input, 'split' );
		$this->assertLessThan( 200, $result );
	}

	/**
	 * Data provider for {@see self::test_pcre_performance()}.
	 *
	 * @return non-empty-list<array{ non-falsy-string }>
	 */
	public function data_whole_posts(): array {
		require_once DIR_TESTDATA . '/formatting/whole-posts.php';
		return data_whole_posts();
	}
}
