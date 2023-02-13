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
	public function test_make_tag() {
		$script = WP_HTML::make_tag(
			'script',
			array( 'defer' => true, 'nonce' => 'HXhIuUk5lfXb' ),
			'console.log("loaded");'
		);

		$this->assertSame( '<script defer nonce="HXhIuUk5lfXb">console.log("loaded");</script>', $script );
	}
}
