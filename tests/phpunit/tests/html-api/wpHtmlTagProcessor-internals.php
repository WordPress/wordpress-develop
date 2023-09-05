<?php
/**
 * Unit tests covering WP_HTML_Tag_Processor internal helpers.
 *
 * @package WordPress
 * @subpackage HTML-API
 */

/**
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Tag_Processor
 */
class Tests_HtmlApi_WpHtmlTagProcessor_Internals extends WP_UnitTestCase {
	/**
	 * @ticket {TICKET NUMBER}
	 *
	 * @dataProvider data_html_with_entire_tag_raw_markup
	 *
	 * @param string $html_with_target_element HTML with a tag containing "target" attribute.
	 * @param string $expected_raw_markup      Expect full raw markup for targeted tag.
	 */
	public function test_returns_raw_html_markup_for_entire_tag( $html_with_target_element, $expected_raw_markup ) {
		$processor = new WP_HTML_Tag_Processor( $html_with_target_element );
		while ( $processor->next_tag() && null == $processor->get_attribute( 'target' ) ) {
			continue;
		}

		$this->assertSame(
			$expected_raw_markup,
			$processor->_wp_internal_extract_raw_token_markup(
				<<<INTERNAL_ONLY
			I understand that this is only for internal WordPress usage and something
			will likely break if used elsewhere. This function comes with no warranty.
INTERNAL_ONLY
				,
				'entire-tag'
			),
			'Failed to exactly extract raw HTML for matched tag.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_html_with_entire_tag_raw_markup() {
		return array(
			'Tag at start of string'          => array( '<img target src="atat.png">', '<img target src="atat.png">' ),
			'Tag in middle of string'         => array( '<a href="#"><img target src="atat.png"></a>', '<img target src="atat.png">' ),
			'Tag at end of string'            => array( '<a href="#"><img src="atat.png"><div id="5" target class="dingy">', '<div id="5" target class="dingy">' ),
			'Tab after tag name'              => array( "<img\ttarget>", "<img\ttarget>" ),
			'Space after attributes'          => array( '<img target    >', '<img target    >' ),
			'Unquoted attribute'              => array( '<span id = 5 class=sunshine target>', '<span id = 5 class=sunshine target>' ),
			'Value with character references' => array( '<a href="#" title="This is &gt; That" target>', '<a href="#" title="This is &gt; That" target>' ),
		);
	}

	/**
	 * @ticket {TICKET NUMBER}
	 *
	 * @dataProvider data_html_with_only_attributes_raw_markup
	 *
	 * @param string $html_with_target_element HTML with a tag containing "target" attribute.
	 * @param string $expected_raw_markup      Expect raw markup for targeted tag containing only the attributes.
	 */
	public function test_returns_raw_html_markup_for_only_attributes( $html_with_target_element, $expected_raw_markup ) {
		$processor = new WP_HTML_Tag_Processor( $html_with_target_element );
		while ( $processor->next_tag() && null == $processor->get_attribute( 'target' ) ) {
			continue;
		}

		$this->assertSame(
			$expected_raw_markup,
			$processor->_wp_internal_extract_raw_token_markup(
				<<<INTERNAL_ONLY
			I understand that this is only for internal WordPress usage and something
			will likely break if used elsewhere. This function comes with no warranty.
INTERNAL_ONLY
				,
				'only-attributes'
			),
			'Failed to exactly extract raw HTML for matched tag.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_html_with_only_attributes_raw_markup() {
		return array(
			'Tag at start of string'          => array( '<img target src="atat.png">', 'target src="atat.png"' ),
			'Tag in middle of string'         => array( '<a href="#"><img target src="atat.png"></a>', 'target src="atat.png"' ),
			'Tag at end of string'            => array( '<a href="#"><img src="atat.png"><div id="5" target class="dingy">', 'id="5" target class="dingy"' ),
			'Tab after tag name'              => array( "<img\ttarget>", "target" ),
			'Space after attributes'          => array( '<img target    >', 'target    ' ),
			'Unquoted attribute'              => array( '<span id = 5 class=sunshine target>', 'id = 5 class=sunshine target' ),
			'Value with character references' => array( '<a href="#" title="This is &gt; That" target>', 'href="#" title="This is &gt; That" target' ),
		);
	}
}
