<?php
/**
 * Unit tests covering WP_HTML functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 */

/**
 * @group html-api
 *
 * @coversDefaultClass WP_HTML
 */
class Tests_HtmlApi_wpHtml extends WP_UnitTestCase {
	public function test_tag() {
		$script = WP_HTML::tag(
			'script',
			array(
				'defer' => true,
				'nonce' => 'HXhIuUk5lfXb'
			),
			'console.log("loaded");'
		);

		$this->assertSame( '<script defer nonce="HXhIuUk5lfXb">console.log("loaded");</script>', $script );
	}

	public function test_tag_with_escapable_text() {
		$div = WP_HTML::tag( 'div', null, '4 < <em>9</em>. <3' );
		$this->assertSame( '<div>4 &lt; &lt;em>9&lt;/em>. &lt;3</div>', $div );
	}

	public function test_tag_with_inner_html_does_not_escape() {
		$div = WP_HTML::tag_with_inner_html( 'div', null, '4 < <em>9</em>. <3' );
		$this->assertSame( '<div>4 < <em>9</em>. <3</div>', $div );
	}
}
