<?php
/**
 * Tests for {@see WP_Styles::get_src()}.
 *
 * @package WordPress
 * @subpackage Script Loader
 *
 * @group dependencies
 * @group scripts
 *
 * @covers WP_Styles::get_src
 */
class Tests_Dependencies_WpStyles_GetSrc extends WP_UnitTestCase {

	/**
	 * Style registry under test.
	 */
	private WP_Styles $styles;

	public function set_up(): void {
		parent::set_up();

		remove_action( 'wp_default_styles', 'wp_default_styles' );

		$this->styles                  = new WP_Styles();
		$this->styles->base_url        = 'http://example.org';
		$this->styles->content_url     = '/wp-content';
		$this->styles->default_version = '7.2';
	}

	/**
	 * Tests that the URL is built from the source, the base URL and the version.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_builds_url
	 *
	 * @param string            $src      Source the style is registered with.
	 * @param string|false|null $ver      Version the style is registered with.
	 * @param string            $expected Expected URL.
	 */
	public function test_builds_url( string $src, $ver, string $expected ): void {
		$this->styles->add( 'test', $src, array(), $ver );

		$this->assertSame( $expected, $this->styles->get_src( 'test' ) );
	}

	/**
	 * Data provider for {@see self::test_builds_url()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string, 1: string|false|null, 2: string }>
	 */
	public function data_builds_url(): array {
		return array(
			'relative source, default version'      => array( '/wp-admin/css/test.css', false, 'http://example.org/wp-admin/css/test.css?ver=7.2' ),
			'relative source, explicit version'     => array( '/wp-admin/css/test.css', '1.0', 'http://example.org/wp-admin/css/test.css?ver=1.0' ),
			'relative source, no version'           => array( '/wp-admin/css/test.css', null, 'http://example.org/wp-admin/css/test.css' ),
			'absolute source'                       => array( 'https://cdn.example.com/test.css', '1.0', 'https://cdn.example.com/test.css?ver=1.0' ),
			'protocol-relative source'              => array( '//cdn.example.com/test.css', '1.0', '//cdn.example.com/test.css?ver=1.0' ),
			'source under the content URL'          => array( '/wp-content/plugins/test/test.css', '1.0', '/wp-content/plugins/test/test.css?ver=1.0' ),
			'source with a query string'            => array( 'https://cdn.example.com/test.css?a=1', '1.0', 'https://cdn.example.com/test.css?a=1&ver=1.0' ),
			'source with a fragment'                => array( 'https://cdn.example.com/test.css#frag', '1.0', 'https://cdn.example.com/test.css?ver=1.0#frag' ),
			'source with a fragment and no version' => array( 'https://cdn.example.com/test.css#frag', null, 'https://cdn.example.com/test.css#frag' ),
			'version needing to be encoded'         => array( 'https://cdn.example.com/test.css', '1.0 beta', 'https://cdn.example.com/test.css?ver=1.0%20beta' ),
		);
	}

	/**
	 * Tests that arguments added to the handle are appended after the version.
	 *
	 * @ticket 57548
	 */
	public function test_appends_handle_args(): void {
		$this->styles->add( 'test', 'https://cdn.example.com/test.css#frag', array(), '1.0' );
		$this->styles->all_deps( 'test?a=1&b=2' );

		$this->assertSame( 'https://cdn.example.com/test.css?ver=1.0&a=1&b=2#frag', $this->styles->get_src( 'test' ) );
	}

	/**
	 * Tests that an empty string is returned for a handle that is not registered.
	 *
	 * @ticket 57548
	 */
	public function test_returns_empty_string_for_unregistered_handle(): void {
		$this->assertSame( '', $this->styles->get_src( 'unregistered' ) );
	}

	/**
	 * Tests that an empty string is returned for a handle that only aliases other handles.
	 *
	 * @ticket 57548
	 */
	public function test_returns_empty_string_for_alias(): void {
		$this->styles->add( 'dependency', '/wp-admin/css/dependency.css' );
		$this->styles->add( 'alias', false, array( 'dependency' ) );

		$this->assertSame( '', $this->styles->get_src( 'alias' ) );
	}

	/**
	 * Tests that a handle registered with `true` as its source, as `colors` is, gets its URL from the
	 * {@see 'style_loader_src'} filter.
	 *
	 * @ticket 57548
	 */
	public function test_uses_filter_for_source_of_true(): void {
		$this->styles->add( 'test', true ); // @phpstan-ignore argument.type (Core registers `colors` this way, though the add() docblock does not allow `true`.)

		add_filter(
			'style_loader_src',
			static function ( $src, string $handle ) {
				return 'test' === $handle ? 'https://cdn.example.com/scheme.css' : $src;
			},
			10,
			2
		);

		$this->assertSame( 'https://cdn.example.com/scheme.css', $this->styles->get_src( 'test' ) );
	}

	/**
	 * Tests that the URL is passed through the {@see 'style_loader_src'} filter along with the handle.
	 *
	 * @ticket 57548
	 */
	public function test_applies_style_loader_src_filter(): void {
		$this->styles->add( 'test', '/wp-admin/css/test.css', array(), '1.0' );

		$filter = new MockAction();
		add_filter( 'style_loader_src', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'style_loader_src',
			static function ( string $src, string $handle ): string {
				return 'test' === $handle ? str_replace( 'example.org', 'cdn.example.com', $src ) : $src;
			},
			20,
			2
		);

		$this->assertSame( 'http://cdn.example.com/wp-admin/css/test.css?ver=1.0', $this->styles->get_src( 'test' ) );
		$this->assertSame(
			array( 'http://example.org/wp-admin/css/test.css?ver=1.0', 'test' ),
			$filter->get_args()[0]
		);
	}

	/**
	 * Tests that an empty string is returned when the filter removes the URL.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_filtered_away
	 *
	 * @param mixed $filtered Value the {@see 'style_loader_src'} filter returns.
	 */
	public function test_returns_empty_string_when_filtered_away( $filtered ): void {
		$this->styles->add( 'test', '/wp-admin/css/test.css' );

		add_filter(
			'style_loader_src',
			static function () use ( $filtered ) {
				return $filtered;
			}
		);

		$this->assertSame( '', $this->styles->get_src( 'test' ) );
	}

	/**
	 * Data provider for {@see self::test_returns_empty_string_when_filtered_away()}.
	 *
	 * @return array<non-falsy-string, array{ 0: mixed }>
	 */
	public function data_filtered_away(): array {
		return array(
			'empty string' => array( '' ),
			'false'        => array( false ),
			'null'         => array( null ),
		);
	}

	/**
	 * Tests that the URL matches the one {@see WP_Styles::do_item()} prints, once decoded from the
	 * attribute, and that {@see WP_Styles::_css_href()} still returns it escaped for the attribute.
	 *
	 * @ticket 57548
	 */
	public function test_matches_printed_href(): void {
		$this->styles->add( 'test', 'https://cdn.example.com/test.css#frag', array(), '1.0' );
		$this->styles->all_deps( 'test?a=1&b=2' );

		$output = get_echo( array( $this->styles, 'do_item' ), array( 'test' ) );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'LINK' ) );
		$this->assertSame( $this->styles->get_src( 'test' ), $processor->get_attribute( 'href' ) );

		$this->assertStringContainsString( "href='https://cdn.example.com/test.css?ver=1.0&#038;a=1&#038;b=2#frag'", $output );
		$this->assertSame(
			'https://cdn.example.com/test.css?ver=1.0&#038;a=1&#038;b=2#frag',
			$this->styles->_css_href( 'https://cdn.example.com/test.css#frag', '1.0', 'test' )
		);
	}
}
