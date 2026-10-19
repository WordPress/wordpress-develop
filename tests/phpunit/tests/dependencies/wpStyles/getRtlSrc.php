<?php
/**
 * Tests for {@see WP_Styles::get_rtl_src()}.
 *
 * @package WordPress
 * @subpackage Script Loader
 *
 * @group dependencies
 * @group scripts
 *
 * @covers WP_Styles::get_rtl_src
 */
class Tests_Dependencies_WpStyles_GetRtlSrc extends WP_UnitTestCase {

	/**
	 * Style registry under test.
	 */
	private WP_Styles $styles;

	public function set_up(): void {
		parent::set_up();

		remove_action( 'wp_default_styles', 'wp_default_styles' );

		$this->styles                  = new WP_Styles();
		$this->styles->base_url        = 'http://example.org';
		$this->styles->default_version = '7.2';
		$this->styles->text_direction  = 'rtl';
	}

	/**
	 * Tests the URL of the right-to-left stylesheet for each form of `rtl` data.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_builds_rtl_url
	 *
	 * @param string               $src      Source the style is registered with.
	 * @param string|false|null    $ver      Version the style is registered with.
	 * @param array<string, mixed> $data     Data added to the style.
	 * @param string               $expected Expected URL.
	 */
	public function test_builds_rtl_url( string $src, $ver, array $data, string $expected ): void {
		$this->styles->add( 'test', $src, array(), $ver );
		foreach ( $data as $key => $value ) {
			$this->styles->add_data( 'test', $key, $value );
		}

		$this->assertSame( $expected, $this->styles->get_rtl_src( 'test' ) );
	}

	/**
	 * Data provider for {@see self::test_builds_rtl_url()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string, 1: string|false|null, 2: array<string, mixed>, 3: string }>
	 */
	public function data_builds_rtl_url(): array {
		return array(
			'rtl true'                    => array( '/wp-admin/css/test.css', '1.0', array( 'rtl' => true ), 'http://example.org/wp-admin/css/test-rtl.css?ver=1.0' ),
			'rtl replace'                 => array( '/wp-admin/css/test.css', '1.0', array( 'rtl' => 'replace' ), 'http://example.org/wp-admin/css/test-rtl.css?ver=1.0' ),
			'rtl true, default version'   => array( '/wp-admin/css/test.css', false, array( 'rtl' => true ), 'http://example.org/wp-admin/css/test-rtl.css?ver=7.2' ),
			'rtl true, no version'        => array( '/wp-admin/css/test.css', null, array( 'rtl' => true ), 'http://example.org/wp-admin/css/test-rtl.css' ),
			'rtl URL, no version'         => array( '/wp-admin/css/test.css', null, array( 'rtl' => 'https://cdn.example.com/test-rtl.css' ), 'https://cdn.example.com/test-rtl.css' ),
			'rtl true, with suffix'       => array(
				'/wp-admin/css/test.min.css',
				'1.0',
				array(
					'rtl'    => true,
					'suffix' => '.min',
				),
				'http://example.org/wp-admin/css/test-rtl.min.css?ver=1.0',
			),
			'rtl URL'                     => array( '/wp-admin/css/test.css', '1.0', array( 'rtl' => 'https://cdn.example.com/test-rtl.css' ), 'https://cdn.example.com/test-rtl.css?ver=1.0' ),
			'rtl URL with a query string' => array( '/wp-admin/css/test.css', '1.0', array( 'rtl' => 'https://cdn.example.com/test-rtl.css?a=1' ), 'https://cdn.example.com/test-rtl.css?a=1&ver=1.0' ),
		);
	}

	/**
	 * Tests that null is returned whenever there is no right-to-left stylesheet to load.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_returns_null
	 *
	 * @param 'ltr'|'rtl'  $text_direction Text direction of the registry.
	 * @param string|false $src            Source the style is registered with, or false to leave it unregistered.
	 * @param mixed        $rtl            The style's `rtl` data, or null to add none.
	 */
	public function test_returns_null( string $text_direction, $src, $rtl ): void {
		$this->styles->text_direction = $text_direction;

		if ( false !== $src ) {
			$this->styles->add( 'dependency', '/wp-admin/css/dependency.css' );
			$this->styles->add( 'test', $src, array( 'dependency' ) );

			if ( null !== $rtl ) {
				$this->styles->add_data( 'test', 'rtl', $rtl );
			}
		}

		$this->assertNull( $this->styles->get_rtl_src( 'test' ) );
	}

	/**
	 * Data provider for {@see self::test_returns_null()}.
	 *
	 * @return array<non-falsy-string, array{ 0: 'ltr'|'rtl', 1: string|false, 2: mixed }>
	 */
	public function data_returns_null(): array {
		return array(
			'left-to-right text direction' => array( 'ltr', '/wp-admin/css/test.css', true ),
			'unregistered handle'          => array( 'rtl', false, null ),
			'no rtl data'                  => array( 'rtl', '/wp-admin/css/test.css', null ),
			'rtl false'                    => array( 'rtl', '/wp-admin/css/test.css', false ),
			'rtl not a string'             => array( 'rtl', '/wp-admin/css/test.css', 1 ),
			'alias without a source'       => array( 'rtl', '', true ),
		);
	}

	/**
	 * Tests that the right-to-left URL gets the same version and added arguments as the
	 * left-to-right one, rather than the arguments being encoded into the version.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_matches_ltr_query
	 *
	 * @param string|false|null $ver Version the style is registered with.
	 */
	public function test_matches_ltr_query( $ver ): void {
		$this->styles->add( 'test', '/wp-admin/css/test.css', array(), $ver );
		$this->styles->add_data( 'test', 'rtl', 'replace' );
		$this->styles->enqueue( 'test?color=blue' );

		$ltr_src = $this->styles->get_src( 'test' );
		$this->assertStringContainsString( 'color=blue', $ltr_src );

		$this->assertSame(
			str_replace( 'test.css', 'test-rtl.css', $ltr_src ),
			$this->styles->get_rtl_src( 'test' )
		);
	}

	/**
	 * Data provider for {@see self::test_matches_ltr_query()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string|false|null }>
	 */
	public function data_matches_ltr_query(): array {
		return array(
			'version'         => array( '1.0' ),
			'default version' => array( false ),
			'no version'      => array( null ),
		);
	}

	/**
	 * Tests that the URL is passed through the {@see 'style_loader_src'} filter with the right-to-left handle.
	 *
	 * @ticket 57548
	 */
	public function test_applies_style_loader_src_filter(): void {
		$this->styles->add( 'test', '/wp-admin/css/test.css', array(), '1.0' );
		$this->styles->add_data( 'test', 'rtl', true );

		$filter = new MockAction();
		add_filter( 'style_loader_src', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'style_loader_src',
			static function ( string $src, string $handle ): string {
				return 'test-rtl' === $handle ? str_replace( 'example.org', 'cdn.example.com', $src ) : $src;
			},
			20,
			2
		);

		$this->assertSame( 'http://cdn.example.com/wp-admin/css/test-rtl.css?ver=1.0', $this->styles->get_rtl_src( 'test' ) );
		$this->assertSame(
			array( 'http://example.org/wp-admin/css/test.css?ver=1.0', 'test-rtl' ),
			$filter->get_args()[0]
		);
	}

	/**
	 * Tests that the URL matches the one {@see WP_Styles::do_item()} prints, once decoded from the
	 * attribute, whether the right-to-left stylesheet replaces the left-to-right one or loads
	 * alongside it.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_matches_printed_href
	 *
	 * @param true|'replace' $rtl      The style's `rtl` data.
	 * @param positive-int   $expected Number of stylesheets expected to be printed.
	 */
	public function test_matches_printed_href( $rtl, int $expected ): void {
		// A query string of its own, so the printed URL has an `&` that is escaped in the attribute.
		$this->styles->add( 'test', '/wp-admin/css/test.css?color=blue', array(), '1.0' );
		$this->styles->add_data( 'test', 'rtl', $rtl );

		$output = get_echo( array( $this->styles, 'do_item' ), array( 'test' ) );

		$this->assertSame( $expected, substr_count( $output, "rel='stylesheet'" ) );

		$processor = new WP_HTML_Tag_Processor( $output );
		$href      = null;
		while ( $processor->next_tag( 'LINK' ) ) {
			if ( 'test-rtl-css' === $processor->get_attribute( 'id' ) ) {
				$href = $processor->get_attribute( 'href' );
			}
		}

		$this->assertSame( 'http://example.org/wp-admin/css/test-rtl.css?color=blue&ver=1.0', $href );
		$this->assertSame( $href, $this->styles->get_rtl_src( 'test' ) );
	}

	/**
	 * Tests that printing a right-to-left stylesheet sanitizes its URL only once, so the
	 * {@see 'clean_url'} filter runs once for it, as it did before get_rtl_src() was introduced.
	 *
	 * @ticket 57548
	 *
	 * @covers WP_Styles::do_item
	 */
	public function test_printing_runs_clean_url_filter_once(): void {
		$this->styles->add( 'test', '/wp-admin/css/test.css', array(), '1.0' );
		$this->styles->add_data( 'test', 'rtl', 'replace' );

		$filter = new MockAction();
		add_filter( 'clean_url', array( $filter, 'filter' ), 10, 3 );

		get_echo( array( $this->styles, 'do_item' ), array( 'test' ) );

		// Once for the left-to-right URL, which is built even when replaced, and once for the right-to-left one.
		$this->assertSame(
			array(
				array( 'http://example.org/wp-admin/css/test.css?ver=1.0', 'display' ),
				array( 'http://example.org/wp-admin/css/test-rtl.css?ver=1.0', 'display' ),
			),
			array_map(
				static function ( array $args ): array {
					return array( $args[0], $args[2] );
				},
				$filter->get_args()
			)
		);
	}

	/**
	 * Data provider for {@see self::test_matches_printed_href()}.
	 *
	 * @return array<non-falsy-string, array{ 0: true|'replace', 1: positive-int }>
	 */
	public function data_matches_printed_href(): array {
		return array(
			'alongside' => array( true, 2 ),
			'replacing' => array( 'replace', 1 ),
		);
	}
}
