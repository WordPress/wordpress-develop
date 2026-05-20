<?php
/**
 * Tests for the Connectors wp-admin page render output.
 *
 * @group connectors
 */
class Tests_Connectors_WpOptionsConnectorsWpAdminRenderPage extends WP_UnitTestCase {

	/**
	 * Critical styles should use a background token for #wpwrap, not a foreground color.
	 *
	 * @ticket 65247
	 */
	public function test_render_page_critical_styles_use_background_token_for_wpwrap() {
		if ( ! function_exists( 'wp_options_connectors_wp_admin_render_page' ) ) {
			$this->markTestSkipped( 'Connectors build files are not available.' );
		}

		ob_start();
		wp_options_connectors_wp_admin_render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'background: var(--wpds-color-bg-surface-neutral-weak, #f0f0f0)',
			$output,
			'#wpwrap should use the same background token as .boot-layout before hydration.'
		);
		$this->assertStringNotContainsString(
			'background: var(--wpds-color-fg-content-neutral, #1e1e1e)',
			$output,
			'#wpwrap must not use a foreground color token as its background fallback.'
		);
	}
}
