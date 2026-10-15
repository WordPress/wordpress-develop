<?php
/**
 * Unit tests covering the data_wp_html_processor functionality of the
 * WP_Interactivity_API class.
 *
 * They exercise the public `wp_interactivity_process_directives()` function
 * against the global Interactivity API instance, which each test replaces with
 * a fresh one.
 *
 * @package WordPress
 * @subpackage Interactivity API
 *
 * @coversDefaultClass WP_Interactivity_API
 *
 * @since 7.2.0
 *
 * @group interactivity-api
 */
class Tests_WP_Interactivity_API_WP_Html extends WP_UnitTestCase {
	/**
	 * The function name `data-wp-html` notices are raised under.
	 */
	const NOTICE_SOURCE = 'WP_Interactivity_API::data_wp_html_processor';

	/**
	 * The HTML of the token every test shares.
	 */
	const MARKER_HTML = '<b>marker-7f3a</b>';

	/**
	 * Global WP_Interactivity_API instance from before the test.
	 *
	 * @var WP_Interactivity_API|null
	 */
	private $original_wp_interactivity;

	/**
	 * Every `_doing_it_wrong()` call of the test, in order.
	 *
	 * @var array<int, array{ source: string, message: string }>
	 */
	private $notices = array();

	/**
	 * Set up.
	 */
	public function set_up() {
		global $wp_interactivity;

		parent::set_up();

		$this->original_wp_interactivity = $wp_interactivity;
		$wp_interactivity                = new WP_Interactivity_API();
		$wp_interactivity->add_hooks();
		$wp_interactivity->state( 'test', array( 'text' => 'processed' ) );

		$this->notices = array();
		add_action( 'doing_it_wrong_run', array( $this, 'collect_notice' ), 10, 2 );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		global $wp_interactivity;

		$wp_interactivity = $this->original_wp_interactivity;

		parent::tear_down();
	}

	/**
	 * Records a `_doing_it_wrong()` call, counting every call rather than only
	 * the first of each source as the test case does.
	 *
	 * @param string $source  The function or method the notice names.
	 * @param string $message The notice message.
	 */
	public function collect_notice( $source, $message ) {
		$this->notices[] = array(
			'source'  => $source,
			'message' => $message,
		);
	}

	/**
	 * Gets the messages of the `data-wp-html` notices raised so far.
	 *
	 * @return string[] Messages.
	 */
	private function html_notice_messages(): array {
		$messages = array();
		foreach ( $this->notices as $notice ) {
			if ( self::NOTICE_SOURCE === $notice['source'] ) {
				$messages[] = $notice['message'];
			}
		}
		return $messages;
	}

	/**
	 * Declares that `data-wp-html` notices are expected, so the test case does
	 * not fail the test for them.
	 */
	private function expect_html_notices() {
		$this->setExpectedIncorrectUsage( self::NOTICE_SOURCE );
	}

	/**
	 * Sets state of the `test` namespace.
	 *
	 * @param array $state State to merge.
	 */
	private function set_state( array $state ) {
		wp_interactivity_state( 'test', $state );
	}

	/**
	 * Gets a token for the shared marker HTML.
	 *
	 * @return WP_Interactivity_Dangerous_HTML Token.
	 */
	private function token(): WP_Interactivity_Dangerous_HTML {
		return wp_interactivity_as_dangerous_html( self::MARKER_HTML );
	}

	/**
	 * Wraps a host element in the common page: the host followed by a
	 * `data-wp-text` span, inside the `test` namespace.
	 *
	 * @param string $host The host markup.
	 * @return string Page markup.
	 */
	private function page( string $host ): string {
		return '<div data-wp-interactive="test">' . $host . '<span data-wp-text="state.text">x</span></div>';
	}

	/**
	 * Gets the markup the common page is expected to process to.
	 *
	 * @param string $host The expected host markup.
	 * @return string Expected output.
	 */
	private function processed_page( string $host ): string {
		return '<div data-wp-interactive="test">' . $host . '<span data-wp-text="state.text">processed</span></div>';
	}

	/**
	 * Tests that a token renders as the exact content of the host, however the
	 * reference reaches it.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_ways_to_reach_a_token
	 *
	 * @param callable $make_state Callable returning the state to set.
	 */
	public function test_token_renders_as_exact_host_content( callable $make_state ) {
		$this->set_state( $make_state() );

		$output = wp_interactivity_process_directives( $this->page( '<div data-wp-html="state.html">Fallback</div>' ) );

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="state.html">' . self::MARKER_HTML . '</div>' ),
			$output
		);
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: callable }> Data provider.
	 */
	public static function data_ways_to_reach_a_token(): array {
		return array(
			'token in state'            => array(
				static function () {
					return array( 'html' => wp_interactivity_as_dangerous_html( self::MARKER_HTML ) );
				},
			),
			'closure returning a token' => array(
				static function () {
					return array(
						'html' => static function () {
							return wp_interactivity_as_dangerous_html( self::MARKER_HTML );
						},
					);
				},
			),
			'clone of a token'          => array(
				static function () {
					return array( 'html' => clone wp_interactivity_as_dangerous_html( self::MARKER_HTML ) );
				},
			),
			'token created with new'    => array(
				static function () {
					return array( 'html' => new WP_Interactivity_Dangerous_HTML( self::MARKER_HTML ) );
				},
			),
		);
	}

	/**
	 * Tests that every HTML string, malformed ones included, becomes the host
	 * content byte for byte and leaves the markup after the host processed.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_html_strings
	 *
	 * @param string $html The trusted HTML.
	 */
	public function test_html_strings_render_byte_for_byte( string $html ) {
		$this->set_state( array( 'html' => wp_interactivity_as_dangerous_html( $html ) ) );

		$output = wp_interactivity_process_directives( $this->page( '<div data-wp-html="state.html">Fallback</div>' ) );

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="state.html">' . $html . '</div>' ),
			$output
		);
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }> Data provider.
	 */
	public static function data_html_strings(): array {
		return array(
			'attributes and entities'       => array( '<DIV Class=\'x\'  data-foo="a&amp;b">&lt;tag&gt; &nbsp;é</div>' ),
			'empty string'                  => array( '' ),
			'lone less-than sign'           => array( 'a < b' ),
			'script with a closer inside'   => array( '<script>if (a < b) { x = "</div>"; }</script>' ),
			'comment holding a tag'         => array( '<!-- <div> -->' ),
			'unclosed tag'                  => array( '<p>abc' ),
			'stray closer'                  => array( '</div>' ),
			'stray closers of the host tag' => array( '</div></div><div>' ),
			'unclosed comment'              => array( 'a<!-- b' ),
		);
	}

	/**
	 * Tests that directives, templates and regions inside the HTML stay as
	 * given and that the markup after the host keeps its own namespace and
	 * context.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_directives_inside_html_stay_as_given() {
		$html = '<span data-wp-text="state.text">raw</span>' .
			'<div data-wp-interactive="other" data-wp-context=\'{"a":1}\'>' .
			'<template data-wp-each="state.list"><i data-wp-text="context.item">t</i></template></div>';
		$this->set_state(
			array(
				'html' => wp_interactivity_as_dangerous_html( $html ),
				'list' => array( 1, 2 ),
			)
		);

		$output = wp_interactivity_process_directives( $this->page( '<div data-wp-html="state.html">Fallback</div>' ) );

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="state.html">' . $html . '</div>' ),
			$output
		);
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Tests that the HTML is inserted exactly once per host, whatever it holds,
	 * and does not disturb markup that is balanced around it.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_each_host_holds_its_html_exactly_once() {
		$html = '<template><p data-wp-text="state.text"></template></div><b>marker-7f3a';
		$this->set_state( array( 'html' => wp_interactivity_as_dangerous_html( $html ) ) );

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test">' .
			'<div data-wp-html="state.html">One</div>' .
			'<p data-wp-class--on="state.text">between</p>' .
			'<section data-wp-html="state.html">Two</section>' .
			'<span data-wp-text="state.text">x</span></div>'
		);

		$this->assertSame(
			'<div data-wp-interactive="test">' .
			'<div data-wp-html="state.html">' . $html . '</div>' .
			'<p class="on" data-wp-class--on="state.text">between</p>' .
			'<section data-wp-html="state.html">' . $html . '</section>' .
			'<span data-wp-text="state.text">processed</span></div>',
			$output
		);
		$this->assertSame( 2, substr_count( $output, $html ), 'Expected the HTML exactly once per host.' );
	}

	/**
	 * Tests that a host resolves its reference in the context it declares, runs
	 * its other directives, and leaves the context of the markup after it alone.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_host_resolves_in_its_own_context() {
		$token = $this->token();
		$this->set_state(
			array(
				'on'   => true,
				'pick' => static function () use ( $token ) {
					return 'a' === wp_interactivity_get_context()['id'] ? $token : null;
				},
			)
		);

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test" data-wp-context=\'{"id":"outer"}\'>' .
			'<div data-wp-context=\'{"id":"a"}\' data-wp-class--active="state.on" data-wp-html="state.pick">Fallback</div>' .
			'<span data-wp-text="context.id">x</span></div>'
		);

		$this->assertSame(
			'<div data-wp-interactive="test" data-wp-context=\'{"id":"outer"}\'>' .
			'<div class="active" data-wp-context=\'{"id":"a"}\' data-wp-class--active="state.on" data-wp-html="state.pick">' . self::MARKER_HTML . '</div>' .
			'<span data-wp-text="context.id">outer</span></div>',
			$output
		);
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Tests that a value that is not a token leaves the output unchanged with
	 * exactly one not-a-token notice, and processes the fallback and the markup
	 * after it.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_values_that_are_not_tokens
	 *
	 * @param mixed $value The value of `state.html`.
	 */
	public function test_value_that_is_not_a_token_declines_with_one_notice( $value ) {
		$this->expect_html_notices();
		$this->set_state( array( 'html' => $value ) );

		$output = wp_interactivity_process_directives( $this->page( '<div data-wp-html="state.html">Fallback</div>' ) );

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="state.html">Fallback</div>' ),
			$output
		);
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
		$this->assertCount( 1, $this->notices, 'Expected no other notice.' );
		$this->assertStringContainsString( 'wp_interactivity_as_dangerous_html()', $this->html_notice_messages()[0] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: mixed }> Data provider.
	 */
	public static function data_values_that_are_not_tokens(): array {
		return array(
			'string of HTML'             => array( '<p>Hi</p>' ),
			'integer'                    => array( 1 ),
			'boolean'                    => array( true ),
			'false'                      => array( false ),
			'array'                      => array( array( '<p>Hi</p>' ) ),
			'stdClass'                   => array( new stdClass() ),
			'json_decode object'         => array( json_decode( '{"html":"<p>Hi</p>"}' ) ),
			'other object'               => array( new ArrayObject( array( '<p>Hi</p>' ) ) ),
			'json_decode of a token'     => array( json_decode( wp_json_encode( new WP_Interactivity_Dangerous_HTML( '<p>Hi</p>' ) ) ) ),
			'closure returning a string' => array(
				static function () {
					return '<p>Hi</p>';
				},
			),
		);
	}

	/**
	 * Tests that a value read from context declines, even when it holds HTML.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_value_from_context_declines_with_one_notice() {
		$this->expect_html_notices();

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test" data-wp-context=\'{"html":"<p>Hi</p>"}\'>' .
			'<div data-wp-html="context.html">Fallback</div><span data-wp-text="state.text">x</span></div>'
		);

		$this->assertSame(
			'<div data-wp-interactive="test" data-wp-context=\'{"html":"<p>Hi</p>"}\'>' .
			'<div data-wp-html="context.html">Fallback</div><span data-wp-text="state.text">processed</span></div>',
			$output
		);
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
	}

	/**
	 * Tests that a `null` value and an unresolved reference decline silently.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_null_references
	 *
	 * @param string $reference The reference.
	 */
	public function test_null_and_unresolved_references_decline_without_notice( string $reference ) {
		$this->set_state( array( 'html' => null ) );

		$output = wp_interactivity_process_directives( $this->page( '<div data-wp-html="' . $reference . '">Fallback</div>' ) );

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="' . $reference . '">Fallback</div>' ),
			$output
		);
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }> Data provider.
	 */
	public static function data_null_references(): array {
		return array(
			'null in state'        => array( 'state.html' ),
			'unresolved reference' => array( 'state.missing' ),
			'unknown namespace'    => array( 'other::state.html' ),
		);
	}

	/**
	 * Tests that a declining host keeps its children and has the directives in
	 * them processed, whatever the reason it declined for.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_declining_values_with_notice_count
	 *
	 * @param string $reference The reference.
	 * @param int    $notices   The expected number of data-wp-html notices.
	 */
	public function test_declining_host_processes_its_children( string $reference, int $notices ) {
		if ( $notices ) {
			$this->expect_html_notices();
		}
		$this->set_state(
			array(
				'on'        => true,
				'html'      => '<p>Hi</p>',
				'html_null' => null,
			)
		);

		$host = '<div data-wp-html="' . $reference . '"><span data-wp-text="state.text">x</span><b data-wp-class--on="state.on">y</b></div>';

		$output = wp_interactivity_process_directives( $this->page( $host ) );

		$this->assertSame(
			$this->processed_page(
				'<div data-wp-html="' . $reference . '"><span data-wp-text="state.text">processed</span><b class="on" data-wp-class--on="state.on">y</b></div>'
			),
			$output
		);
		$this->assertCount( $notices, $this->html_notice_messages() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string, 1: int }> Data provider.
	 */
	public static function data_declining_values_with_notice_count(): array {
		return array(
			'unresolved reference' => array( 'state.missing', 0 ),
			'string'               => array( 'state.html', 1 ),
			'null'                 => array( 'state.html_null', 0 ),
		);
	}

	/**
	 * Tests that hosts that cannot hold content decline with one notice whatever
	 * the value, and keep their own markup.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_hosts_that_cannot_hold_content
	 *
	 * @param string $host  The host markup.
	 * @param bool   $token Whether `state.html` is a token. When false it is null.
	 */
	public function test_host_that_cannot_hold_content_declines_with_one_notice( string $host, bool $token ) {
		$this->expect_html_notices();
		$this->set_state( array( 'html' => $token ? $this->token() : null ) );

		$output = wp_interactivity_process_directives( $this->page( $host ) );

		$this->assertSame( $this->processed_page( $host ), $output );
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
		$this->assertStringNotContainsString( 'marker-7f3a', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string, 1: bool }> Data provider.
	 */
	public static function data_hosts_that_cannot_hold_content(): array {
		$hosts = array(
			'img'      => '<img data-wp-html="state.html">',
			'input'    => '<input data-wp-html="state.html">',
			'textarea' => '<textarea data-wp-html="state.html">Fallback</textarea>',
			'title'    => '<title data-wp-html="state.html">Fallback</title>',
			'script'   => '<script data-wp-html="state.html">Fallback</script>',
			'style'    => '<style data-wp-html="state.html">Fallback</style>',
			'iframe'   => '<iframe data-wp-html="state.html">Fallback</iframe>',
			'xmp'      => '<xmp data-wp-html="state.html">Fallback</xmp>',
		);

		$data = array();
		foreach ( $hosts as $name => $host ) {
			$data[ "$name with a token" ] = array( $host, true );
			$data[ "$name with null" ]    = array( $host, false );
		}
		return $data;
	}

	/**
	 * Tests that a host that also carries `data-wp-text` declines with one
	 * notice, whatever the value, and `data-wp-text` still sets the content.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_token_or_null
	 *
	 * @param bool $token Whether `state.html` is a token. When false it is null.
	 */
	public function test_host_with_text_declines_and_text_still_works( bool $token ) {
		$this->expect_html_notices();
		$this->set_state( array( 'html' => $token ? $this->token() : null ) );

		$output = wp_interactivity_process_directives(
			$this->page( '<div data-wp-html="state.html" data-wp-text="state.text">Fallback</div>' )
		);

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="state.html" data-wp-text="state.text">processed</div>' ),
			$output
		);
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: bool }> Data provider.
	 */
	public static function data_token_or_null(): array {
		return array(
			'token' => array( true ),
			'null'  => array( false ),
		);
	}

	/**
	 * Tests that a `template` carrying `data-wp-each` and `data-wp-html` declines
	 * with one notice and still renders its items.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_token_or_null
	 *
	 * @param bool $token Whether `state.html` is a token. When false it is null.
	 */
	public function test_template_with_each_declines_and_each_still_works( bool $token ) {
		$this->expect_html_notices();
		$this->set_state(
			array(
				'html' => $token ? $this->token() : null,
				'list' => array( 'a', 'b' ),
			)
		);

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test"><ul>' .
			'<template data-wp-each="state.list" data-wp-html="state.html"><li data-wp-text="context.item">x</li></template>' .
			'</ul></div>'
		);

		$this->assertSame(
			'<div data-wp-interactive="test"><ul>' .
			'<template data-wp-each="state.list" data-wp-html="state.html"><li data-wp-text="context.item">x</li></template>' .
			'<li data-wp-each-child="test::state.list" data-wp-text="context.item">a</li>' .
			'<li data-wp-each-child="test::state.list" data-wp-text="context.item">b</li>' .
			'</ul></div>',
			$output
		);
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
	}

	/**
	 * Tests that the three decline reasons have pairwise different messages.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_decline_reasons_have_pairwise_different_messages() {
		$this->expect_html_notices();

		$messages = array();
		foreach (
			array(
				'not a token'           => array( '<p>Hi</p>', '<div data-wp-html="state.html">Fallback</div>' ),
				'cannot hold content'   => array( $this->token(), '<img data-wp-html="state.html">' ),
				'combination with text' => array( $this->token(), '<div data-wp-html="state.html" data-wp-text="state.text">Fallback</div>' ),
			) as $reason => list( $value, $host )
		) {
			$this->notices = array();
			$this->set_state( array( 'html' => $value ) );
			wp_interactivity_process_directives( $this->page( $host ) );
			$captured = $this->html_notice_messages();
			$this->assertCount( 1, $captured, "Expected exactly one notice for: $reason." );
			$messages[ $reason ] = $captured[0];
		}

		$this->assertCount( 3, array_unique( $messages ), 'Expected three different messages.' );
	}

	/**
	 * Tests that an element that both cannot hold content and is a combination
	 * raises only the cannot-hold-content notice.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_cannot_hold_content_takes_precedence_over_combination() {
		$this->expect_html_notices();
		$this->set_state( array( 'html' => $this->token() ) );

		wp_interactivity_process_directives( $this->page( '<textarea data-wp-html="state.html">Fallback</textarea>' ) );
		$cannot_hold = $this->html_notice_messages();

		$this->notices = array();
		wp_interactivity_process_directives( $this->page( '<textarea data-wp-html="state.html" data-wp-text="state.text">Fallback</textarea>' ) );
		$both = $this->html_notice_messages();

		$this->notices = array();
		wp_interactivity_process_directives( $this->page( '<div data-wp-html="state.html" data-wp-text="state.text">Fallback</div>' ) );
		$combination = $this->html_notice_messages();

		$this->assertCount( 1, $cannot_hold );
		$this->assertCount( 1, $both, 'Expected exactly one notice.' );
		$this->assertSame( $cannot_hold[0], $both[0], 'Expected the cannot-hold-content message.' );
		$this->assertNotSame( $combination[0], $both[0], 'Expected the combination message to differ.' );
	}

	/**
	 * Tests that a declining host does not evaluate its reference.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_structurally_declining_hosts
	 *
	 * @param string $host The host markup.
	 */
	public function test_structural_decline_does_not_evaluate_the_reference( string $host ) {
		$this->expect_html_notices();
		$calls = 0;
		$this->set_state(
			array(
				'list' => array( 'a' ),
				'html' => function () use ( &$calls ) {
					++$calls;
					return wp_interactivity_as_dangerous_html( '<i>x</i>' );
				},
			)
		);

		wp_interactivity_process_directives( $this->page( $host ) );

		$this->assertSame( 0, $calls, 'Expected the derived state closure not to be invoked.' );
		$this->assertCount( 1, $this->html_notice_messages() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }> Data provider.
	 */
	public static function data_structurally_declining_hosts(): array {
		return array(
			'void element'          => array( '<img data-wp-html="state.html">' ),
			'raw text element'      => array( '<script data-wp-html="state.html">x</script>' ),
			'combination with text' => array( '<div data-wp-html="state.html" data-wp-text="state.text">x</div>' ),
			'template with each'    => array( '<template data-wp-each="state.list" data-wp-html="state.html"><li>x</li></template>' ),
			'void with text'        => array( '<input data-wp-html="state.html" data-wp-text="state.text">' ),
		);
	}

	/**
	 * Tests that an authored non-string value declines with one not-a-token
	 * notice, without evaluating it, and processes the markup after the host.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_authored_literals
	 *
	 * @param string $literal The attribute value.
	 */
	public function test_authored_non_string_literal_declines_with_one_notice( string $literal ) {
		$this->expect_html_notices();

		$output = wp_interactivity_process_directives(
			$this->page( '<div data-wp-html=\'' . $literal . '\'>Fallback</div>' )
		);

		$this->assertSame(
			$this->processed_page( '<div data-wp-html=\'' . $literal . '\'>Fallback</div>' ),
			$output
		);
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
		$this->assertCount( 1, $this->notices, 'Expected no other notice.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }> Data provider.
	 */
	public static function data_authored_literals(): array {
		return array(
			'integer'        => array( '1' ),
			'float'          => array( '1.5' ),
			'true'           => array( 'true' ),
			'array'          => array( '[1,2]' ),
			'object'         => array( '{"a":"b"}' ),
			'namespaced int' => array( 'test::42' ),
		);
	}

	/**
	 * Tests that entries other than the default one are ignored without a notice.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_ignored_entries
	 *
	 * @param string $attribute The attribute written on the host.
	 */
	public function test_non_default_entries_are_ignored_without_notice( string $attribute ) {
		$this->set_state( array( 'html' => $this->token() ) );

		$host   = '<div ' . $attribute . '>Fallback</div>';
		$output = wp_interactivity_process_directives( $this->page( $host ) );

		$this->assertSame( $this->processed_page( $host ), $output );
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }> Data provider.
	 */
	public static function data_ignored_entries(): array {
		return array(
			'suffix'    => array( 'data-wp-html--x="state.html"' ),
			'unique id' => array( 'data-wp-html---id="state.html"' ),
			'empty'     => array( 'data-wp-html=""' ),
			'boolean'   => array( 'data-wp-html' ),
		);
	}

	/**
	 * Tests that the default entry renders when other entries accompany it.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_default_entry_renders_beside_ignored_entries() {
		$this->set_state( array( 'html' => $this->token() ) );

		$output = wp_interactivity_process_directives(
			$this->page( '<div data-wp-html--x="state.missing" data-wp-html="state.html" data-wp-html---id="state.missing">Fallback</div>' )
		);

		$this->assertSame(
			$this->processed_page( '<div data-wp-html--x="state.missing" data-wp-html="state.html" data-wp-html---id="state.missing">' . self::MARKER_HTML . '</div>' ),
			$output
		);
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Tests that inside `data-wp-each` each item renders the HTML its reference
	 * resolves to, with the item in context, and only the item's top-level
	 * element is marked.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_each_item_renders_html_resolved_with_the_item() {
		$this->set_state(
			array(
				'items'        => array( array( 'id' => 'a' ), array( 'id' => 'b' ) ),
				'descriptions' => array(
					'a' => '<p>A</p><p>A2</p>',
					'b' => '<i>B</i>',
				),
				'description'  => static function () {
					$id = wp_interactivity_get_context()['item']['id'];
					return wp_interactivity_as_dangerous_html( wp_interactivity_state()['descriptions'][ $id ] );
				},
			)
		);

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test"><ul><template data-wp-each="state.items"><li data-wp-html="state.description">x</li></template></ul></div>'
		);

		$this->assertSame(
			'<div data-wp-interactive="test"><ul><template data-wp-each="state.items"><li data-wp-html="state.description">x</li></template>' .
			'<li data-wp-each-child="test::state.items" data-wp-html="state.description"><p>A</p><p>A2</p></li>' .
			'<li data-wp-each-child="test::state.items" data-wp-html="state.description"><i>B</i></li></ul></div>',
			$output
		);
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Tests that, inside `data-wp-each`, trusted HTML holding directives or
	 * unbalanced tags is inserted as given without disturbing the items.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_each_item_html_is_never_scanned() {
		$html = '<p data-wp-text="state.text">raw</p><div data-wp-each-child="x"></div></li><template data-wp-each="state.list"><i>';
		$this->set_state(
			array(
				'list' => array( 'a', 'b' ),
				'html' => wp_interactivity_as_dangerous_html( $html ),
			)
		);

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test"><ul><template data-wp-each="state.list">' .
			'<li data-wp-html="state.html">x</li></template></ul><span data-wp-text="state.text">x</span></div>'
		);

		$this->assertSame(
			'<div data-wp-interactive="test"><ul><template data-wp-each="state.list"><li data-wp-html="state.html">x</li></template>' .
			'<li data-wp-each-child="test::state.list" data-wp-html="state.html">' . $html . '</li>' .
			'<li data-wp-each-child="test::state.list" data-wp-html="state.html">' . $html . '</li></ul>' .
			'<span data-wp-text="state.text">processed</span></div>',
			$output
		);
		$this->assertSame( 2, substr_count( $output, $html ), 'Expected the HTML exactly once per item.' );
	}

	/**
	 * Tests that a declining host inside `data-wp-each` raises one notice per
	 * item and keeps the item's own processing.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_each_item_declines_with_one_notice_per_item() {
		$this->expect_html_notices();
		$this->set_state(
			array(
				'list' => array( 'a', 'b' ),
				'html' => '<p>Hi</p>',
			)
		);

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test"><ul><template data-wp-each="state.list">' .
			'<li data-wp-html="state.html"><span data-wp-text="context.item">x</span></li></template></ul></div>'
		);

		$this->assertSame(
			'<div data-wp-interactive="test"><ul><template data-wp-each="state.list">' .
			'<li data-wp-html="state.html"><span data-wp-text="context.item">x</span></li></template>' .
			'<li data-wp-each-child="test::state.list" data-wp-html="state.html"><span data-wp-text="context.item">a</span></li>' .
			'<li data-wp-each-child="test::state.list" data-wp-html="state.html"><span data-wp-text="context.item">b</span></li></ul></div>',
			$output
		);
		$this->assertCount( 2, $this->html_notice_messages(), 'Expected one notice per item.' );
	}

	/**
	 * Tests that a host nested in a host is replaced as a whole and the HTML
	 * of the outer host appears once.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_host_inside_a_rendering_host_is_replaced_with_it() {
		$this->set_state( array( 'html' => $this->token() ) );

		$output = wp_interactivity_process_directives(
			$this->page( '<div data-wp-html="state.html"><div data-wp-html="state.html">Inner</div></div>' )
		);

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="state.html">' . self::MARKER_HTML . '</div>' ),
			$output
		);
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Tests that markup that was never trusted is not mistaken for a placeholder
	 * and that inserted HTML is not searched for placeholders.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_substitution_cannot_be_forged() {
		$lookalike = '<!--wp-interactivity-dangerous-html:0-->';
		$this->set_state( array( 'html' => $this->token() ) );

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test"><i>' . $lookalike . '</i><p data-wp-html="state.html">One</p></div>'
		);

		$this->assertSame(
			'<div data-wp-interactive="test"><i>' . $lookalike . '</i><p data-wp-html="state.html">' . self::MARKER_HTML . '</p></div>',
			$output
		);
	}

	/**
	 * Tests that HTML that looks like the placeholder of another host is
	 * inserted as given, not replaced by that host's HTML.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_inserted_html_is_not_searched_for_placeholders() {
		$lookalike = '<!--wp-interactivity-dangerous-html:1-->';
		$this->set_state(
			array(
				'first'  => wp_interactivity_as_dangerous_html( $lookalike ),
				'second' => wp_interactivity_as_dangerous_html( '<u>second</u>' ),
			)
		);

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test"><p data-wp-html="state.first">One</p><p data-wp-html="state.second">Two</p></div>'
		);

		$this->assertSame(
			'<div data-wp-interactive="test"><p data-wp-html="state.first">' . $lookalike . '</p><p data-wp-html="state.second"><u>second</u></p></div>',
			$output
		);
	}

	/**
	 * Tests that unbalanced markup returns the input unchanged, with no HTML
	 * inserted.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_unbalanced_markup_returns_input_unchanged() {
		$this->set_state( array( 'html' => $this->token() ) );

		$input = '<div data-wp-interactive="test"><div data-wp-html="state.html">Fallback</div><p></div>';

		$this->assertSame( $input, wp_interactivity_process_directives( $input ) );
	}

	/**
	 * Tests that one call's HTML is not carried to the next call.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_calls_do_not_share_trusted_html() {
		$this->set_state( array( 'html' => $this->token() ) );
		$host = '<div data-wp-html="state.html">Fallback</div>';

		$first  = wp_interactivity_process_directives( $this->page( $host ) );
		$second = wp_interactivity_process_directives( $this->page( $host ) );
		$other  = wp_interactivity_process_directives( '<div data-wp-interactive="test"><p data-wp-text="state.text">x</p></div>' );

		$this->assertSame( $first, $second );
		$this->assertSame( '<div data-wp-interactive="test"><p data-wp-text="state.text">processed</p></div>', $other );
	}

	/**
	 * Tests that the value `unserialize()` creates from a `C:` payload naming the
	 * token class declines as not a token.
	 *
	 * Also records how `unserialize()` treats such a payload: it warns, runs no
	 * code of the class and returns an object of the class.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_value_unserialized_from_custom_format_declines_as_not_a_token() {
		$this->expect_html_notices();

		$class    = get_class( $this->token() );
		$warnings = array();
		set_error_handler(
			static function ( $errno, $errstr ) use ( &$warnings ) {
				$warnings[] = array( $errno, $errstr );
				return true;
			}
		);
		try {
			$value = unserialize( 'C:' . strlen( $class ) . ':"' . $class . '":0:{}' );
		} finally {
			restore_error_handler();
		}

		$this->assertCount( 1, $warnings, 'Expected unserialize() to raise exactly one warning.' );
		$this->assertSame( E_WARNING, $warnings[0][0], 'Expected the warning to be an E_WARNING.' );
		$this->assertInstanceOf( $class, $value, 'Expected unserialize() to return an object of the token class.' );
		$this->assertNull( WP_Interactivity_Dangerous_HTML::get_html( $value ), 'Expected the object to report no HTML.' );

		$this->set_state( array( 'html' => $value ) );

		$output = wp_interactivity_process_directives( $this->page( '<div data-wp-html="state.html">Fallback</div>' ) );

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="state.html">Fallback</div>' ),
			$output
		);
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
	}

	/**
	 * Gets the sources of the `_doing_it_wrong()` calls raised so far.
	 *
	 * @return string[] Sources, in the order raised.
	 */
	private function notice_sources(): array {
		return array_column( $this->notices, 'source' );
	}

	/**
	 * Asserts that no attribute of any element in the markup holds the shared
	 * marker.
	 *
	 * @param string $output The processed markup.
	 */
	private function assert_marker_in_no_attribute( string $output ) {
		$p = new WP_HTML_Tag_Processor( $output );
		while ( $p->next_tag() ) {
			foreach ( $p->get_attribute_names_with_prefix( '' ) ?? array() as $name ) {
				$value = $p->get_attribute( $name );
				$this->assertStringNotContainsString(
					'marker-7f3a',
					is_string( $value ) ? $value : '',
					"Expected the \"{$name}\" attribute of <{$p->get_tag()}> to hold no trusted HTML."
				);
			}
		}
	}

	/**
	 * Gets the Interactivity data printed for the client, encoded as the script
	 * module prints it.
	 *
	 * @return string JSON.
	 */
	private function printed_client_data(): string {
		global $wp_interactivity;

		$encoded = wp_json_encode( $wp_interactivity->filter_script_module_interactivity_data( array() ) );
		$this->assertIsString( $encoded, 'Expected the client data to be encodable as JSON.' );
		return $encoded;
	}

	/**
	 * Tests that a token placed in state, or returned by a derived-state
	 * closure, reaches the output only as the host's content, even when
	 * `data-wp-bind` and `data-wp-style` reference it, and is absent from the
	 * client data.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_ways_to_reach_a_token
	 *
	 * @param callable $make_state Callable returning the state to set.
	 */
	public function test_token_reaches_the_output_only_as_host_content( callable $make_state ) {
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::data_wp_bind_processor' );
		$this->set_state( $make_state() );

		$output = wp_interactivity_process_directives(
			$this->page(
				'<div data-wp-html="state.html">Fallback</div>' .
				'<div data-wp-bind--data-html="state.html" data-wp-bind--title="state.html" data-wp-style--color="state.html">z</div>'
			)
		);

		$this->assertSame(
			$this->processed_page(
				'<div data-wp-html="state.html">' . self::MARKER_HTML . '</div>' .
				'<div data-wp-bind--data-html="state.html" data-wp-bind--title="state.html" data-wp-style--color="state.html">z</div>'
			),
			$output,
			'Expected the token to be the host content, to remove the bound attributes and to leave no style.'
		);
		$this->assertSame( 1, substr_count( $output, 'marker-7f3a' ), 'Expected the marker once, in the host content.' );
		$this->assert_marker_in_no_attribute( $output );
		$this->assertSame(
			array(
				'WP_Interactivity_API::data_wp_bind_processor',
				'WP_Interactivity_API::data_wp_bind_processor',
			),
			$this->notice_sources(),
			'Expected only the non-scalar notice of each bound attribute.'
		);

		$printed = $this->printed_client_data();
		$this->assertStringNotContainsString( 'marker-7f3a', $printed, 'Expected the client data to hold no trusted HTML.' );
		$this->assertStringContainsString( '"html":{}', $printed, 'Expected the token to print as an empty object.' );
	}

	/**
	 * Tests that a token in `wp_interactivity_data_wp_context()` reaches neither
	 * the output nor the client data, and that referencing it from other
	 * directives neither renders it nor aborts processing.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_token_in_context_helper_reaches_no_output_or_client_data() {
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::data_wp_bind_processor' );
		$this->expect_html_notices();

		$wrapper = '<div data-wp-interactive="test" ' . wp_interactivity_data_wp_context( array( 'h' => $this->token() ) ) . '>';
		$output  = wp_interactivity_process_directives(
			$wrapper .
			'<div data-wp-html="context.h">Fallback</div>' .
			'<div data-wp-bind--title="context.h" data-wp-style--color="context.h">z</div>' .
			'<span data-wp-text="state.text">x</span></div>'
		);

		$this->assertSame(
			$wrapper .
			'<div data-wp-html="context.h">Fallback</div>' .
			'<div data-wp-bind--title="context.h" data-wp-style--color="context.h">z</div>' .
			'<span data-wp-text="state.text">processed</span></div>',
			$output,
			'Expected the context to hold no HTML to render, and the markup after it to be processed.'
		);
		$this->assertStringContainsString( '{"h":{}}', $output, 'Expected the token to print as an empty object in the context attribute.' );
		$this->assertStringNotContainsString( 'marker-7f3a', $output );
		$this->assertStringNotContainsString( 'marker-7f3a', $this->printed_client_data() );
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected the value read from context to decline as not a token.' );
	}

	/**
	 * Tests that `data-wp-style` referencing a token removes the targeted
	 * property as it does for `null`, raising no notice.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_ways_to_reach_a_token
	 *
	 * @param callable $make_state Callable returning the state to set.
	 */
	public function test_style_with_a_token_removes_the_property_as_null_does( callable $make_state ) {
		$markup = '<div data-wp-style--color="state.html" style="color:red;background:blue">a</div>' .
			'<div data-wp-style--color="state.html">b</div>';

		$this->set_state( array( 'html' => null ) );
		$expected = wp_interactivity_process_directives( $this->page( $markup ) );

		$this->set_state( $make_state() );
		$output = wp_interactivity_process_directives( $this->page( $markup ) );

		$this->assertSame( $expected, $output, 'Expected a token to be treated as no value.' );
		$this->assertStringContainsString( 'style="background:blue;"', $output, 'Expected the declared property to be removed.' );
		$this->assertStringContainsString( '<span data-wp-text="state.text">processed</span>', $output, 'Expected processing to continue.' );
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Tests that `data-wp-style` also treats the object `unserialize()` returns
	 * for a `C:` payload naming the token class as no value.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_style_with_a_value_unserialized_from_custom_format_removes_the_property() {
		$class = get_class( $this->token() );
		$value = @unserialize( 'C:' . strlen( $class ) . ':"' . $class . '":0:{}' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$this->set_state( array( 'html' => $value ) );

		$output = wp_interactivity_process_directives(
			$this->page( '<div data-wp-style--color="state.html" style="color:red;background:blue">a</div>' )
		);

		$this->assertSame(
			$this->processed_page( '<div data-wp-style--color="state.html" style="background:blue;">a</div>' ),
			$output
		);
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}
}
