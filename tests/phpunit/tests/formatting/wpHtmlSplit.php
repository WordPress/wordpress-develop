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
	 */
	public function test_basic_features( $input, $output ) {
		return $this->assertSame( $output, wp_html_split( $input ) );
	}

	public static function data_basic_features() {
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
	 * @ticket 57381
	 * @ticket 63426
	 *
	 * @dataProvider data_gt_in_quoted_attribute_values
	 *
	 * @param non-falsy-string $input  Input HTML.
	 * @param list<string>     $output Expected split.
	 */
	public function test_gt_in_quoted_attribute_values( string $input, array $output ): void {
		$this->assertSame( $output, wp_html_split( $input ) );
	}

	/**
	 * Data provider for {@see self::test_gt_in_quoted_attribute_values()}.
	 *
	 * @return list<array{ 0: non-falsy-string, 1: list<string> }>
	 */
	public static function data_gt_in_quoted_attribute_values(): array {
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
			array(
				'<div data-template="Label <% value %>" data-label="{A \'message\' string}">Content</div>',
				array( '', '<div data-template="Label <% value %>" data-label="{A \'message\' string}">', 'Content', '</div>', '' ),
			),
			array(
				'<iframe srcdoc="<body></body>"></iframe>',
				array( '', '<iframe srcdoc="<body></body>">', '', '</iframe>', '' ),
			),
			array(
				'<div class="swiper [&>.swiper-pagination]:static">content</div>',
				array( '', '<div class="swiper [&>.swiper-pagination]:static">', 'content', '</div>', '' ),
			),
		);
	}

	/**
	 * Ensure that a quote in an unquoted attribute value does not pair with a
	 * quote after the tag.
	 *
	 * @ticket 63997
	 */
	public function test_quote_in_unquoted_attribute_value_does_not_pair_across_tag(): void {
		$input = "<p>Hi</p>\n<a href=\"/x\" title=it's>It's here</a>";

		$this->assertSame(
			array( '', '<p>', 'Hi', '</p>', "\n", '<a href="/x" title=it\'s>', 'It\'s here', '</a>', '' ),
			wp_html_split( $input )
		);
	}

	/**
	 * Ensure that an unclosed tag matches the remaining input.
	 *
	 * @ticket 63997
	 */
	public function test_unclosed_tag_matches_remaining_input(): void {
		$input = "<p>Truncated <a href=\"http://example.com/it's";

		$this->assertSame(
			array( '', '<p>', 'Truncated ', '<a href="http://example.com/it\'s', '' ),
			wp_html_split( $input )
		);
	}

	/**
	 * Ensure that a quote in an attribute name does not extend the tag past the
	 * next >.
	 *
	 * @ticket 63997
	 */
	public function test_quote_in_attribute_name_does_not_extend_tag(): void {
		$this->assertSame(
			array( '', '<div "=noquote >', ' id="quote">' ),
			wp_html_split( '<div "=noquote > id="quote">' )
		);
	}

	/**
	 * Ensure that a quote-named attribute splits the same as before.
	 *
	 * @ticket 63997
	 */
	public function test_quote_named_attribute_is_one_element(): void {
		$this->assertSame(
			array( '', '<div "=noquote id="quote">', 'x' ),
			wp_html_split( '<div "=noquote id="quote">x' )
		);
	}

	/**
	 * Ensure that a CDATA section containing > is a single element.
	 *
	 * @ticket 63997
	 */
	public function test_cdata_with_gt_is_one_element(): void {
		$input = '<script>/*<![CDATA[*/ if (a > b && c) {} /*]]>*/</script>';

		$this->assertSame(
			array( '', '<script>', '/*', '<![CDATA[*/ if (a > b && c) {} /*]]>', '*/', '</script>', '' ),
			wp_html_split( $input )
		);
	}

	/**
	 * Automated performance testing of the main regex.
	 *
	 * @dataProvider data_whole_posts
	 *
	 * @covers ::get_html_split_regex
	 */
	public function test_pcre_performance( $input ) {
		$regex  = get_html_split_regex();
		$result = benchmark_pcre_backtracking( $regex, $input, 'split' );
		return $this->assertLessThan( 200, $result );
	}

	public static function data_whole_posts() {
		require_once DIR_TESTDATA . '/formatting/whole-posts.php';
		return data_whole_posts();
	}
}
