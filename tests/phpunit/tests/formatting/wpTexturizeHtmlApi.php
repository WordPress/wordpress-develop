<?php

require_once __DIR__ . '/wpTexturize.php';

/**
 * Runs every wptexturize() test with the HTML API implementation opted in.
 *
 * _wptexturize_html_api() is meant to produce the same output as the legacy
 * implementation, so the legacy tests are its specification. With the opt-in,
 * wptexturize() only sends input that needs it to the HTML API, so the tests
 * below also compare _wptexturize_html_api() with the legacy code directly on
 * every input from the data providers.
 *
 * @group formatting
 *
 * @covers ::_wptexturize_html_api
 * @covers ::_wptexturize_needs_html_api
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
	 * Turns off the opt-in, so wptexturize() gives the legacy output.
	 */
	private function use_legacy() {
		remove_filter( 'wptexturize_force_legacy_parser', '__return_false' );
		wptexturize( 'reset', true );
	}

	/**
	 * Input with a quote directly after an inline tag is routed to the HTML API
	 * implementation, which recognizes element names case-insensitively. The
	 * legacy implementation only protects the lowercase spelling, so it curls
	 * these quotes.
	 *
	 * @ticket 18549
	 */
	public function test_opt_in_routes_markup_to_html_api(): void {
		$this->assertSame( '<CODE><em>"raw"</em></CODE>', wptexturize( '<CODE><em>"raw"</em></CODE>' ) );

		$this->use_legacy();
		$this->assertSame( '<CODE><em>&#8220;raw&#8221;</em></CODE>', wptexturize( '<CODE><em>"raw"</em></CODE>' ), 'Legacy curls quotes in an uppercase CODE element.' );
	}

	/**
	 * Only input with a straight quote directly after an inline tag needs the
	 * HTML API implementation.
	 *
	 * @ticket 18549
	 *
	 * @dataProvider data_needs_html_api
	 *
	 * @param string $input    HTML to texturize.
	 * @param bool   $expected Whether it needs the HTML API.
	 */
	public function test_needs_html_api( string $input, bool $expected ): void {
		$this->assertSame( $expected, _wptexturize_needs_html_api( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_needs_html_api(): array {
		return array(
			'apostrophe after inline closer'  => array( "<em>word</em>'s", true ),
			'quote after inline closer'       => array( 'He said "<em>no</em>" twice', true ),
			'apostrophe after inline opener'  => array( "That<em>'s</em> right", true ),
			'inline opener with attributes'   => array( '<a href="/x" class="y">"Link"</a>', true ),
			'uppercase inline tag'            => array( "<EM>x</EM>'s", true ),
			'quote after block tag'           => array( '<p>"Hello"</p>', false ),
			'quote after a space'             => array( '<em>word</em> "x"', false ),
			'no markup'                       => array( '"Hello" -- it\'s', false ),
			'tag name that starts like one'   => array( '<blockquote>"x"</blockquote><bdx>"y"', false ),
			'quote before an inline tag only' => array( 'say "<em>no</em> more', false ),
		);
	}

	/**
	 * Markup is never texturized. The legacy implementation ends a tag at its
	 * first ">", so it curls the closing quote of this attribute value.
	 *
	 * @ticket 18549
	 */
	public function test_markup_is_not_texturized(): void {
		$this->assertSame( '<em title="a > b">&#8220;c&#8221;</em>', wptexturize( '<em title="a > b">"c"</em>' ) );

		$this->use_legacy();
		$this->assertSame( '<em title="a > b&#8221;>&#8221;c&#8221;</em>', wptexturize( '<em title="a > b">"c"</em>' ) );
	}

	/**
	 * The HTML API implementation gives the legacy output for every input in the
	 * data providers, whether or not wptexturize() would route it there.
	 *
	 * @ticket 18549
	 *
	 * @dataProvider data_all_inputs
	 *
	 * @param string $input Text to texturize.
	 */
	public function test_html_api_matches_legacy( string $input ): void {
		$this->use_legacy();
		$this->assertSame( wptexturize( $input ), _wptexturize_html_api( $input ) );
	}

	/**
	 * Data provider: the first argument of every row in the inherited providers.
	 *
	 * @return array[]
	 */
	public function data_all_inputs(): array {
		$inputs = array();
		$class  = new ReflectionClass( Tests_Formatting_wpTexturize::class );
		foreach ( $class->getMethods() as $method ) {
			if ( ! str_starts_with( $method->getName(), 'data_' ) || Tests_Formatting_wpTexturize::class !== $method->getDeclaringClass()->getName() ) {
				continue;
			}
			foreach ( $method->invoke( $this ) as $key => $row ) {
				if ( isset( $row[0] ) && is_string( $row[0] ) ) {
					$inputs[ $method->getName() . ' ' . $key ] = array( $row[0] );
				}
			}
		}
		return $inputs;
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
		$this->use_legacy();
		$this->assertSame( wptexturize( $input ), _wptexturize_html_api( $input ) );
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
		$this->assertSame( $input, _wptexturize_html_api( $input ) );
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
			'<p><em>&#8220;One&#8221;</em></p><p><em>&#8220;Two&#8221;</em></p><p><em>&#8220;Three&#8221;</em></p>',
			wptexturize( '<p><em>"One"</em></p><p><em>"Two"</em></p><p><em>"Three"</em></p>' )
		);
		$this->assertSame( 1, $tags->get_call_count(), "The 'no_texturize_tags' filter should run once." );
		$this->assertSame( 1, $shortcodes->get_call_count(), "The 'no_texturize_shortcodes' filter should run once." );
	}
}
