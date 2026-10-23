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

	private ?WP_Styles $original_wp_styles = null;

	/**
	 * Saves the global styles instance and resets it so each test starts with nothing enqueued.
	 *
	 * @global WP_Styles|null $wp_styles
	 */
	public function set_up() {
		global $wp_styles;

		parent::set_up();
		$this->original_wp_styles = $wp_styles;
		$wp_styles                = null;
	}

	public function tear_down() {
		global $wp_styles;

		$wp_styles = $this->original_wp_styles;
		parent::tear_down();
	}

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
		wp_enqueue_view_transitions_admin_css();
		get_echo( 'wp_print_styles', array( 'wp-view-transitions-admin' ) );
		$this->assertTrue( wp_style_is( 'wp-view-transitions-admin', 'done' ), 'Expected the style to have been printed.' );

		$output = get_echo( 'wp_print_view_transitions_render_blocking_link' );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'LINK' ), 'Expected a LINK tag to be printed.' );
		$this->assertSame( 'expect', $processor->get_attribute( 'rel' ), 'Expected the LINK to have a rel of "expect".' );
		$this->assertSame( '#wpcontent', $processor->get_attribute( 'href' ), 'Expected the LINK to point at the element after the admin menu.' );
		$this->assertSame( 'render', $processor->get_attribute( 'blocking' ), 'Expected the LINK to block rendering.' );
		$this->assertSame( '(prefers-reduced-motion: no-preference)', $processor->get_attribute( 'media' ), 'Expected the LINK to be scoped to the view transitions media query.' );
		$this->assertFalse( $processor->next_tag(), 'Expected only a single tag to be printed.' );
	}

	/**
	 * Tests that nothing is printed when the admin View Transitions style is not enqueued.
	 *
	 * @ticket 65032
	 */
	public function test_prints_nothing_when_style_not_enqueued(): void {
		$this->assertFalse( wp_style_is( 'wp-view-transitions-admin' ), 'Expected the style to not be enqueued.' );
		$this->assertSame( '', get_echo( 'wp_print_view_transitions_render_blocking_link' ) );
	}

	/**
	 * Tests that nothing is printed when the admin View Transitions style is enqueued but not yet printed.
	 *
	 * This happens when the style is enqueued too late for the head, so it is printed in the footer instead.
	 *
	 * @ticket 65032
	 */
	public function test_prints_nothing_when_style_not_printed(): void {
		wp_enqueue_view_transitions_admin_css();
		$this->assertTrue( wp_style_is( 'wp-view-transitions-admin' ), 'Expected the style to be enqueued.' );
		$this->assertFalse( wp_style_is( 'wp-view-transitions-admin', 'done' ), 'Expected the style to not be printed yet.' );

		$this->assertSame( '', get_echo( 'wp_print_view_transitions_render_blocking_link' ) );
	}

	/**
	 * Tests that iframe screens, which have no admin menu, get neither the style nor the expect link.
	 *
	 * iframe_header() fires the same enqueue and head actions as wp-admin/admin-header.php, so
	 * hooking the enqueue to one of those actions would print a link whose target never appears.
	 *
	 * @ticket 65032
	 *
	 * @covers ::iframe_header
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_iframe_header_prints_no_style_or_link(): void {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		set_current_screen( 'media-upload' );

		$output = get_echo( 'iframe_header' );

		$this->assertTrue( did_action( 'admin_head' ) > 0, 'Expected iframe_header() to fire the admin_head action.' );
		$this->assertFalse( wp_style_is( 'wp-view-transitions-admin' ), 'Expected the style to not be enqueued.' );
		$this->assertStringNotContainsString( '@view-transition', $output, 'Expected no view transitions CSS to be printed.' );

		$processor = new WP_HTML_Tag_Processor( $output );
		while ( $processor->next_tag( 'LINK' ) ) {
			$this->assertNotSame( 'expect', $processor->get_attribute( 'rel' ), 'Expected no expect link to be printed.' );
		}
	}
}
