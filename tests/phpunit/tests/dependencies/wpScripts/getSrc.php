<?php
/**
 * Tests for {@see WP_Scripts::get_src()}.
 *
 * @package WordPress
 * @subpackage Script Loader
 *
 * @group dependencies
 * @group scripts
 *
 * @covers WP_Scripts::get_src
 */
class Tests_Dependencies_WpScripts_GetSrc extends WP_UnitTestCase {

	/**
	 * Script registry under test.
	 */
	private WP_Scripts $scripts;

	public function set_up(): void {
		parent::set_up();

		remove_action( 'wp_default_scripts', 'wp_default_scripts' );
		remove_action( 'wp_default_scripts', 'wp_default_packages' );

		$this->scripts                  = new WP_Scripts();
		$this->scripts->base_url        = 'http://example.org';
		$this->scripts->content_url     = '/wp-content';
		$this->scripts->default_version = '7.2';
	}

	/**
	 * Tests that the URL is built from the source, the base URL and the version.
	 *
	 * @ticket 57548
	 *
	 * @dataProvider data_builds_url
	 *
	 * @param string            $src      Source the script is registered with.
	 * @param string|false|null $ver      Version the script is registered with.
	 * @param string            $expected Expected URL.
	 */
	public function test_builds_url( string $src, $ver, string $expected ): void {
		$this->scripts->add( 'test', $src, array(), $ver );

		$this->assertSame( $expected, $this->scripts->get_src( 'test' ) );
	}

	/**
	 * Data provider for {@see self::test_builds_url()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string, 1: string|false|null, 2: string }>
	 */
	public function data_builds_url(): array {
		return array(
			'relative source, default version'      => array( '/wp-includes/js/test.js', false, 'http://example.org/wp-includes/js/test.js?ver=7.2' ),
			'relative source, explicit version'     => array( '/wp-includes/js/test.js', '1.0', 'http://example.org/wp-includes/js/test.js?ver=1.0' ),
			'relative source, no version'           => array( '/wp-includes/js/test.js', null, 'http://example.org/wp-includes/js/test.js' ),
			'absolute source'                       => array( 'https://cdn.example.com/test.js', '1.0', 'https://cdn.example.com/test.js?ver=1.0' ),
			'protocol-relative source'              => array( '//cdn.example.com/test.js', '1.0', '//cdn.example.com/test.js?ver=1.0' ),
			'source under the content URL'          => array( '/wp-content/plugins/test/test.js', '1.0', '/wp-content/plugins/test/test.js?ver=1.0' ),
			'source with a query string'            => array( 'https://cdn.example.com/test.js?a=1', '1.0', 'https://cdn.example.com/test.js?a=1&ver=1.0' ),
			'source with a fragment'                => array( 'https://cdn.example.com/test.js#frag', '1.0', 'https://cdn.example.com/test.js?ver=1.0#frag' ),
			'source with a fragment and no version' => array( 'https://cdn.example.com/test.js#frag', null, 'https://cdn.example.com/test.js#frag' ),
			'version needing to be encoded'         => array( 'https://cdn.example.com/test.js', '1.0 beta', 'https://cdn.example.com/test.js?ver=1.0%20beta' ),
		);
	}

	/**
	 * Tests that arguments added to the handle are appended after the version.
	 *
	 * @ticket 57548
	 */
	public function test_appends_handle_args(): void {
		$this->scripts->add( 'test', 'https://cdn.example.com/test.js#frag', array(), '1.0' );
		$this->scripts->all_deps( 'test?a=1&b=2' );

		$this->assertSame( 'https://cdn.example.com/test.js?ver=1.0&a=1&b=2#frag', $this->scripts->get_src( 'test' ) );
	}

	/**
	 * Tests that an empty string is returned for a handle that is not registered.
	 *
	 * @ticket 57548
	 */
	public function test_returns_empty_string_for_unregistered_handle(): void {
		$this->assertSame( '', $this->scripts->get_src( 'unregistered' ) );
	}

	/**
	 * Tests that an empty string is returned for a handle that only aliases other handles.
	 *
	 * @ticket 57548
	 */
	public function test_returns_empty_string_for_alias(): void {
		$this->scripts->add( 'dependency', '/wp-includes/js/dependency.js' );
		$this->scripts->add( 'alias', false, array( 'dependency' ) );

		$this->assertSame( '', $this->scripts->get_src( 'alias' ) );
	}

	/**
	 * Tests that the URL is passed through the {@see 'script_loader_src'} filter along with the handle.
	 *
	 * @ticket 57548
	 */
	public function test_applies_script_loader_src_filter(): void {
		$this->scripts->add( 'test', '/wp-includes/js/test.js', array(), '1.0' );

		$filter = new MockAction();
		add_filter( 'script_loader_src', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'script_loader_src',
			static function ( string $src, string $handle ): string {
				return 'test' === $handle ? str_replace( 'example.org', 'cdn.example.com', $src ) : $src;
			},
			20,
			2
		);

		$this->assertSame( 'http://cdn.example.com/wp-includes/js/test.js?ver=1.0', $this->scripts->get_src( 'test' ) );
		$this->assertSame(
			array( 'http://example.org/wp-includes/js/test.js?ver=1.0', 'test' ),
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
	 * @param mixed $filtered Value the {@see 'script_loader_src'} filter returns.
	 */
	public function test_returns_empty_string_when_filtered_away( $filtered ): void {
		$this->scripts->add( 'test', '/wp-includes/js/test.js' );

		add_filter(
			'script_loader_src',
			static function () use ( $filtered ) {
				return $filtered;
			}
		);

		$this->assertSame( '', $this->scripts->get_src( 'test' ) );
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
	 * Tests that the URL matches the one {@see WP_Scripts::do_item()} prints.
	 *
	 * @ticket 57548
	 */
	public function test_matches_printed_src(): void {
		$this->scripts->add( 'test', 'https://cdn.example.com/test.js#frag', array(), '1.0' );
		$this->scripts->all_deps( 'test?a=1&b=2' );

		$processor = new WP_HTML_Tag_Processor( get_echo( array( $this->scripts, 'do_item' ), array( 'test' ) ) );
		$this->assertTrue( $processor->next_tag( 'SCRIPT' ) );

		$this->assertSame( $this->scripts->get_src( 'test' ), $processor->get_attribute( 'src' ) );
	}
}
