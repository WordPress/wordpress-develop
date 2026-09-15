<?php
/**
 * Tests for the wp_print_view_transitions_render_blocking_link() function.
 *
 * @package WordPress
 * @subpackage View Transitions
 */

/**
 * @group view-transitions
 * @covers ::wp_print_view_transitions_render_blocking_link
 */
class Tests_View_Transitions_wpPrintViewTransitionsRenderBlockingLink extends WP_UnitTestCase {

	/**
	 * Tests that the hook for printing the render-blocking link is set up.
	 *
	 * @ticket 65032
	 */
	public function test_hook(): void {
		$this->assertSame( 10, has_action( 'admin_head', 'wp_print_view_transitions_render_blocking_link' ) );
	}

	/**
	 * Tests that a render-blocking expect link is printed.
	 *
	 * @ticket 65032
	 */
	public function test_prints_render_blocking_link(): void {
		$output = get_echo( 'wp_print_view_transitions_render_blocking_link' );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'LINK' ), 'Expected a LINK tag to be printed.' );
		$this->assertSame( 'expect', $processor->get_attribute( 'rel' ), 'Expected the LINK to have a rel of "expect".' );
		$this->assertSame( '#wpfooter', $processor->get_attribute( 'href' ), 'Expected the LINK to point at the footer.' );
		$this->assertSame( 'render', $processor->get_attribute( 'blocking' ), 'Expected the LINK to block rendering.' );
		$this->assertSame( '(prefers-reduced-motion: no-preference)', $processor->get_attribute( 'media' ), 'Expected the LINK to be scoped to the view transitions media query.' );
		$this->assertFalse( $processor->next_tag(), 'Expected only a single tag to be printed.' );
	}
}
