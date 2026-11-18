<?php

require_once __DIR__ . '/wpTexturize.php';

/**
 * Runs every wptexturize() test with the HTML API implementation opted in.
 *
 * _wptexturize_html_api() is meant to produce the same output as the legacy
 * implementation, so the legacy tests are its specification. The tests below
 * cover what is specific to it.
 *
 * @group formatting
 *
 * @covers ::_wptexturize_html_api
 */
class Tests_Formatting_wpTexturizeHtmlApi extends Tests_Formatting_wpTexturize {

	public function set_up() {
		parent::set_up();
		add_filter( 'wptexturize_force_legacy_parser', '__return_false' );
		wptexturize( 'reset', true );
	}

	public function tear_down() {
		remove_filter( 'wptexturize_force_legacy_parser', '__return_false' );
		wptexturize( 'reset', true );
		parent::tear_down();
	}

	/**
	 * Markup is routed to the HTML API implementation, which recognizes element
	 * names case-insensitively. The legacy implementation only protects the
	 * lowercase spelling, so it curls these quotes.
	 *
	 * @ticket 18549
	 */
	public function test_opt_in_routes_markup_to_html_api(): void {
		$this->assertSame( '<CODE>"raw"</CODE>', wptexturize( '<CODE>"raw"</CODE>' ) );

		remove_filter( 'wptexturize_force_legacy_parser', '__return_false' );
		$this->assertSame( '<CODE>&#8220;raw&#8221;</CODE>', wptexturize( '<CODE>"raw"</CODE>', true ), 'Legacy curls quotes in an uppercase CODE element.' );
	}

	/**
	 * The texturize filters run once per call, not once per text node.
	 *
	 * @ticket 18549
	 */
	public function test_filters_run_once_per_call(): void {
		$tags       = new MockAction();
		$shortcodes = new MockAction();
		add_filter( 'no_texturize_tags', array( $tags, 'filter' ) );
		add_filter( 'no_texturize_shortcodes', array( $shortcodes, 'filter' ) );

		$this->assertSame(
			'<p>&#8220;One&#8221;</p><p>&#8220;Two&#8221;</p><p>&#8220;Three&#8221;</p>',
			wptexturize( '<p>"One"</p><p>"Two"</p><p>"Three"</p>' )
		);
		$this->assertSame( 1, $tags->get_call_count(), "The 'no_texturize_tags' filter should run once." );
		$this->assertSame( 1, $shortcodes->get_call_count(), "The 'no_texturize_shortcodes' filter should run once." );
	}
}
