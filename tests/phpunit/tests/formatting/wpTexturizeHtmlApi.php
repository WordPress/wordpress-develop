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
	 * Markup is never texturized. The legacy implementation ends a tag at its
	 * first ">", so it curls the closing quote of this attribute value.
	 *
	 * @ticket 18549
	 */
	public function test_markup_is_not_texturized(): void {
		$this->assertSame( '<p title="a > b">&#8220;c&#8221;</p>', wptexturize( '<p title="a > b">"c"</p>' ) );
	}

	/**
	 * Markup keeps the legacy handling that changes its bytes: a bare & in a tag
	 * becomes &#038;, and the contents of an unprotected raw text element are
	 * texturized, even where the HTML Processor adds virtual tokens.
	 *
	 * @ticket 18549
	 *
	 * @dataProvider data_markup_matches_legacy
	 *
	 * @param string $input HTML to texturize.
	 */
	public function test_markup_matches_legacy( string $input ): void {
		$html_api = wptexturize( $input );

		remove_filter( 'wptexturize_force_legacy_parser', '__return_false' );
		$this->assertSame( wptexturize( $input, true ), $html_api );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_markup_matches_legacy(): array {
		return array(
			'title contents'                     => array( '<title>"x" -- y</title><p>"z"</p>' ),
			'textarea contents'                  => array( '<textarea>"x" & y</textarea>' ),
			'& in a raw text element tag'        => array( '<textarea data-a="1&2">"x"</textarea>' ),
			'protected script contents'          => array( '& <script>"a" && b</script>' ),
			'& in an attribute'                  => array( '<a href="?a=1&b=2">"x" & y</a>' ),
			'& in a tag after an implied closer' => array( '<p>"a"<div class="x&y">"b"</div>' ),
			'textarea after an implied closer'   => array( '<select><textarea>"x"</textarea>' ),
			'& in a stray closer'                => array( '<p>"a"</span &>"b"</p>' ),
			'& in a doctype'                     => array( '<!DOCTYPE html &><p>"a" -- b</p>' ),
			'& in a comment'                     => array( '<!-- a & b --><p>"c"</p>' ),
		);
	}

	/**
	 * Input with nothing to change is returned as it was.
	 *
	 * @ticket 18549
	 */
	public function test_unchanged_markup_is_returned_as_is(): void {
		$input = '<p class="a">Plain &amp; simple &#8217; text.</p><!-- "comment" --><pre>"x"</pre>';
		$this->assertSame( $input, wptexturize( $input ) );
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
