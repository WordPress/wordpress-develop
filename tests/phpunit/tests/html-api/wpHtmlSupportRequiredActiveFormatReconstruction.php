<?php
/**
 * Unit tests for the HTML API ensuring proper handling of behaviors related to
 * active format reconstruction.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.5.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlSupportRequiredActiveFormatReconstruction extends WP_UnitTestCase {
	/**
	 * Ensures that active formats are properly reconstructed when visiting text nodes,
	 * verifying that the proper breadcrumbs are maintained when scanning through HTML.
	 *
	 * @ticket 60455
	 */
	public function test_reconstructs_active_formats_on_text_nodes() {
		$processor = WP_HTML_Processor::create_fragment( '<p><b>One<p><source>Two<source>' );

		// The SOURCE element doesn't trigger reconstruction, and this test asserts that.
		$this->assertTrue(
			$processor->next_tag( 'SOURCE' ),
			'Should have found the first custom element.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'P', 'SOURCE' ),
			$processor->get_breadcrumbs(),
			'Should have closed formatting element at first P element.'
		);

		/*
		 * There are two ways this test could fail. One is to appropriately find the
		 * second text node but fail to reconstruct the implicitly-closed B element.
		 * The other way is to fail to abort when encountering the second text node
		 * because the kind of active format reconstruction isn't supported.
		 *
		 * At the time of writing this test, the HTML Processor bails whenever it
		 * needs to reconstruct active formats, unless there are no active formats.
		 * To ensure that this test properly works once that support is expanded,
		 * it's written to verify both circumstances. Once support is added, this
		 * can be simplified to only contain the first clause of the conditional.
		 *
		 * The use of the SOURCE element is important here because most elements
		 * will also trigger reconstruction, which would conflate the test results
		 * with the text node triggering reconstruction. The SOURCE element won't
		 * do this, making it neutral. Therefore, the implicitly-closed B element
		 * will only be reconstructed by the text node.
		 */

		if ( $processor->next_tag( 'SOURCE' ) ) {
			$this->assertSame(
				array( 'HTML', 'BODY', 'P', 'B', 'SOURCE' ),
				$processor->get_breadcrumbs(),
				'Should have reconstructed the implicitly-closed B element.'
			);
		} else {
			$this->assertSame(
				WP_HTML_Processor::ERROR_UNSUPPORTED,
				$processor->get_last_error(),
				'Should have aborted for incomplete active format reconstruction when encountering the second text node.'
			);
		}
	}
}
