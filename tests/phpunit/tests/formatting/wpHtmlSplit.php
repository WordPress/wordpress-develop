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
			'Double-quoted value'     => array(
				'<p data-rule="a > b">text</p>',
				array( '', '<p data-rule="a > b">', 'text', '</p>', '' ),
			),
			'Single-quoted value'     => array(
				"<p data-rule='a > b'>text</p>",
				array( '', "<p data-rule='a > b'>", 'text', '</p>', '' ),
			),
			'Markup in srcdoc'        => array(
				'<iframe srcdoc="<p>inner</p>">outer</iframe>',
				array( '', '<iframe srcdoc="<p>inner</p>">', 'outer', '</iframe>', '' ),
			),
			'Tailwind variant class'  => array(
				'<div class="[&>p]:m-0">text</div>',
				array( '', '<div class="[&>p]:m-0">', 'text', '</div>', '' ),
			),
			'Apostrophe in unquoted'  => array(
				"<a title=it's>it's</a>",
				array( '', "<a title=it's>", "it's", '</a>', '' ),
			),
			'Unclosed tag still ends' => array(
				'text <a href="unfinished',
				array( 'text ', '<a href="unfinished', '' ),
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
