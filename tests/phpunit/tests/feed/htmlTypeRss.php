<?php
/**
 * Tests for html_type_rss().
 *
 * @group feed
 *
 * @covers ::html_type_rss
 */
class Tests_Feed_HtmlTypeRss extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_echoes_xhtml_when_html_type_contains_xhtml() {
		add_filter(
			'pre_option_html_type',
			static function () {
				return 'application/xhtml+xml';
			}
		);

		$this->expectOutputString( 'xhtml' );

		html_type_rss();
	}

	/**
	 * @ticket 65819
	 */
	public function test_echoes_html_for_non_xhtml_html_type() {
		add_filter(
			'pre_option_html_type',
			static function () {
				return 'text/html';
			}
		);

		$this->expectOutputString( 'html' );

		html_type_rss();
	}
}
