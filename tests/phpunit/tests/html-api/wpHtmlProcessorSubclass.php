<?php
/**
 * Unit tests covering WP_HTML_Processor subclass functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.6.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorSubclass extends WP_UnitTestCase {
	/**
	 * Ensures that subclasses can be created from ::create_fragment method.
	 *
	 * @ticket TBD
	 */
	public function test_subclass_create_fragment_creates_subclass() {
		$processor = WP_HTML_Processor::create_fragment( '' );
		$this->assertInstanceOf( WP_HTML_Processor::class, $processor, '::create_fragment did not return class instance.' );

		$processor = WP_HTML_Processor_Subclass::create_fragment( '' );
		$this->assertInstanceOf( WP_HTML_Processor_Subclass::class, $processor, '::create_fragment did not return subclass instance.' );
	}
}

class WP_HTML_Processor_Subclass extends WP_HTML_Processor {}
