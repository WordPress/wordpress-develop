<?php
/**
 * Unit tests covering WP_HTML_Processor functionality.
 *
 * @package gutenberg
 */

/**
 * Unit tests for the mobile block editor settings.
 *
 * @covers WP_HTML_Processor
 */

 class Tests_HtmlApi_wpHtmlProcessor extends WP_UnitTestCase {

	public function test_starts() {
		$p = new WP_HTML_Processor( '<p>Lorem<b>Ipsum</p>Dolor</b>Sit' );
		// The controller's schema is hardcoded, so tests would not be meaningful.
		$p->next_tag_in_body_insertion_mode();
	}

}
