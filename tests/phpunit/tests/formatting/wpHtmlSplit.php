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
	 * Ensures that a ">" inside a quoted attribute value does not end the tag.
	 *
	 * @ticket 63997
	 * @ticket 57381
	 * @ticket 63426
	 *
	 * @dataProvider data_gt_in_quoted_attribute_value
	 *
	 * @param string   $input  HTML to split.
	 * @param string[] $output Expected split.
	 */
	public function test_gt_in_quoted_attribute_value( string $input, array $output ): void {
		$this->assertSame( $output, wp_html_split( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_gt_in_quoted_attribute_value(): array {
		return array(
			'Double-quoted value'              => array(
				'<p data-rule="a > b">text</p>',
				array( '', '<p data-rule="a > b">', 'text', '</p>', '' ),
			),
			'Single-quoted value'              => array(
				"<p data-rule='a > b'>text</p>",
				array( '', "<p data-rule='a > b'>", 'text', '</p>', '' ),
			),
			'Several values, one containing >' => array(
				'<p data-a="a > b" data-b="c &gt; d">text</p>',
				array( '', '<p data-a="a > b" data-b="c &gt; d">', 'text', '</p>', '' ),
			),
			'Ticket 63997: template attribute' => array(
				'<div data-template="Label <% value %>" data-label="{A \'message\' string}">Content</div>',
				array( '', '<div data-template="Label <% value %>" data-label="{A \'message\' string}">', 'Content', '</div>', '' ),
			),
			'Ticket 63426: markup in srcdoc'   => array(
				'<iframe srcdoc="<body></body>"></iframe>',
				array( '', '<iframe srcdoc="<body></body>">', '', '</iframe>', '' ),
			),
			'Ticket 57381: Tailwind variant'   => array(
				'<div class="swiper [&>.swiper-pagination]:static">content</div>',
				array( '', '<div class="swiper [&>.swiper-pagination]:static">', 'content', '</div>', '' ),
			),
		);
	}

	/**
	 * Ensures that inputs the split already handled split the same way as before.
	 *
	 * @ticket 63997
	 *
	 * @dataProvider data_unchanged_splits
	 *
	 * @param string   $input  HTML to split.
	 * @param string[] $output Expected split.
	 */
	public function test_unchanged_splits( string $input, array $output ): void {
		$this->assertSame( $output, wp_html_split( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unchanged_splits(): array {
		return array(
			'Apostrophe in an unquoted value does not reach the next tag' => array(
				"<p>Hi</p>\n<a href=\"/x\" title=it's>It's here</a>",
				array( '', '<p>', 'Hi', '</p>', "\n", "<a href=\"/x\" title=it's>", "It's here", '</a>', '' ),
			),
			'Stray quote after an unquoted value'        => array(
				'<b class=x">hello</b>',
				array( '', '<b class=x">', 'hello', '</b>', '' ),
			),
			'Unclosed tag matches the rest of the input' => array(
				"<p>Truncated <a href=\"http://example.com/it's",
				array( '', '<p>', 'Truncated ', "<a href=\"http://example.com/it's", '' ),
			),
			'Quote in an attribute name'                 => array(
				'<div "=noquote id="quote">x',
				array( '', '<div "=noquote id="quote">', 'x' ),
			),
			'Quote after a < that does not start a tag'  => array(
				"<p>x <=' y is not a tag</p><p>and this isn't an attribute</p>",
				array( '', '<p>', 'x ', "<=' y is not a tag</p>", '', '<p>', "and this isn't an attribute", '</p>', '' ),
			),
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
