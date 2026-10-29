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
	 * Children with directives, for a host that declines to keep and process.
	 */
	const CHILDREN = '<span data-wp-text="state.text">x</span><b data-wp-class--on="state.on">y</b>';

	/**
	 * The children of a declining host once processed.
	 */
	const PROCESSED_CHILDREN = '<span data-wp-text="state.text">processed</span><b class="on" data-wp-class--on="state.on">y</b>';

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
		$wp_interactivity->state(
			'test',
			array(
				'text' => 'processed',
				'on'   => true,
			)
		);

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
	 * Gets the paths listed under `derivedStateClosures` of the client data
	 * printed so far, by namespace.
	 *
	 * @return array<string, string[]> Paths by namespace.
	 */
	private function announced_paths(): array {
		global $wp_interactivity;

		$data = $wp_interactivity->filter_script_module_interactivity_data( array() );
		return $data['derivedStateClosures'] ?? array();
	}

	/**
	 * Tests that a token renders as the exact content of the host, however the
	 * reference reaches it, and reaches the output only there: `data-wp-bind`
	 * does not set it and the client data holds none of its HTML. A token in
	 * state prints as the marker `{"wpServerHtml":true}`.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_ways_to_reach_a_token
	 *
	 * @param callable $make_state Callable returning the state to set.
	 * @param string   $printed    How `state.html` is expected to print in the client data.
	 */
	public function test_token_reaches_the_output_only_as_host_content( callable $make_state, string $printed ) {
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::data_wp_bind_processor' );
		$this->set_state( $make_state() );

		$output = wp_interactivity_process_directives(
			$this->page(
				'<div data-wp-html="state.html">Fallback</div>' .
				'<div data-wp-bind--data-html="state.html" data-wp-bind--title="state.html">z</div>'
			)
		);

		$this->assertSame(
			$this->processed_page(
				'<div data-wp-html="state.html">' . self::MARKER_HTML . '</div>' .
				'<div data-wp-bind--data-html="state.html" data-wp-bind--title="state.html">z</div>'
			),
			$output,
			'Expected the token to be the host content and to set no bound attribute.'
		);
		$this->assertSame(
			array(
				'WP_Interactivity_API::data_wp_bind_processor',
				'WP_Interactivity_API::data_wp_bind_processor',
			),
			array_column( $this->notices, 'source' ),
			'Expected only the non-scalar notice of each bound attribute.'
		);

		$this->assertStringNotContainsString( 'marker-7f3a', $this->printed_client_data(), 'Expected the client data to hold no trusted HTML.' );
		$this->assertStringContainsString( '"html":' . $printed, $this->printed_client_data(), 'Expected state.html to print as given.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: callable, 1: string }> Data provider.
	 */
	public static function data_ways_to_reach_a_token(): array {
		return array(
			'token in state'            => array(
				static function () {
					return array( 'html' => wp_interactivity_as_dangerous_html( self::MARKER_HTML ) );
				},
				'{"wpServerHtml":true}',
			),
			'closure returning a token' => array(
				static function () {
					return array(
						'html' => static function () {
							return wp_interactivity_as_dangerous_html( self::MARKER_HTML );
						},
					);
				},
				'{}',
			),
			'clone of a token'          => array(
				static function () {
					return array( 'html' => clone wp_interactivity_as_dangerous_html( self::MARKER_HTML ) );
				},
				'{"wpServerHtml":true}',
			),
			'token created with new'    => array(
				static function () {
					return array( 'html' => new WP_Interactivity_Dangerous_HTML( self::MARKER_HTML ) );
				},
				'{"wpServerHtml":true}',
			),
		);
	}

	/**
	 * Tests that every HTML string, malformed ones and ones holding directives
	 * included, becomes the host content byte for byte, that the directives in
	 * it are not processed, and that the markup after the host is processed in
	 * the host's namespace.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 * @covers wp_interactivity_as_dangerous_html
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
			'attributes and entities'         => array( '<DIV Class=\'x\'  data-foo="a&amp;b">&lt;tag&gt; &nbsp;é</div>' ),
			'empty string'                    => array( '' ),
			'lone less-than sign'             => array( 'a < b' ),
			'script with a closer inside'     => array( '<script>if (a < b) { x = "</div>"; }</script>' ),
			'comment holding a tag'           => array( '<!-- <div> -->' ),
			'unclosed tag'                    => array( '<p>abc' ),
			'stray closer'                    => array( '</div>' ),
			'stray closers of the host tag'   => array( '</div></div><div>' ),
			'unclosed comment'                => array( 'a<!-- b' ),
			'directives, template and region' => array(
				'<span data-wp-text="state.text">raw</span>' .
				'<div data-wp-interactive="other" data-wp-context=\'{"a":1}\'>' .
				'<template data-wp-each="state.list"><i data-wp-text="context.item">t</i></template></div>',
			),
			'directives in unbalanced markup' => array( '<template><p data-wp-text="state.text"></template></div><b>marker-7f3a' ),
			'whitespace'                      => array( " \t\r\n<br>\n " ),
			'null byte'                       => array( "a\0b" ),
			'multibyte'                       => array( 'Ünï© 日本 😀' ),
		);
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
	 * Tests that a value that is not a token keeps the host's content, with the
	 * directives in it processed, and raises exactly one notice, which names the
	 * function that creates tokens.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 * @covers WP_Interactivity_Dangerous_HTML::get_html
	 *
	 * @dataProvider data_values_that_are_not_tokens
	 *
	 * @param mixed $value The value of `state.html`.
	 */
	public function test_value_that_is_not_a_token_declines_with_one_notice( $value ) {
		$this->setExpectedIncorrectUsage( self::NOTICE_SOURCE );
		$this->set_state( array( 'html' => $value ) );

		$output = wp_interactivity_process_directives( $this->page( '<div data-wp-html="state.html">' . self::CHILDREN . '</div>' ) );

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="state.html">' . self::PROCESSED_CHILDREN . '</div>' ),
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
			'empty string'               => array( '' ),
			'integer'                    => array( 1 ),
			'boolean'                    => array( true ),
			'false'                      => array( false ),
			'array'                      => array( array( '<p>Hi</p>' ) ),
			'array with an html key'     => array( array( 'html' => '<p>Hi</p>' ) ),
			'stdClass'                   => array( new stdClass() ),
			'json_decode object'         => array( json_decode( '{"html":"<p>Hi</p>"}' ) ),
			'other object'               => array( new ArrayObject( array( '<p>Hi</p>' ) ) ),
			'json_decode of a token'     => array( json_decode( wp_json_encode( new WP_Interactivity_Dangerous_HTML( '<p>Hi</p>' ) ) ) ),
			'marker decoded as an array' => array( json_decode( '{"wpServerHtml":true}', true ) ),
			'closure returning a string' => array(
				static function () {
					return '<p>Hi</p>';
				},
			),
		);
	}

	/**
	 * Tests that a `null` value and an unresolved reference keep the host's
	 * content, with the directives in it processed, without a notice.
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

		$output = wp_interactivity_process_directives( $this->page( '<div data-wp-html="' . $reference . '">' . self::CHILDREN . '</div>' ) );

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="' . $reference . '">' . self::PROCESSED_CHILDREN . '</div>' ),
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
	 * Tests that a host that cannot hold content, or that carries a directive
	 * `data-wp-html` cannot be combined with, declines with one notice naming
	 * the reason, without resolving its reference, and that its other
	 * directives still run.
	 *
	 * A reference that was resolved through a derived-state closure would be
	 * listed in the client data, so an empty list shows it was not.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_structurally_declining_hosts
	 *
	 * @param string $host     The host markup.
	 * @param string $expected The expected host markup.
	 * @param string $reason   Text the notice contains to name the reason.
	 */
	public function test_structural_decline_names_its_reason_without_resolving_the_reference( string $host, string $expected, string $reason ) {
		$this->setExpectedIncorrectUsage( self::NOTICE_SOURCE );
		$token = $this->token();
		$this->set_state(
			array(
				'list' => array( 'a', 'b' ),
				'html' => static function () use ( $token ) {
					return $token;
				},
			)
		);

		$output = wp_interactivity_process_directives( $this->page( $host ) );

		$this->assertSame( $this->processed_page( $expected ), $output );
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
		$this->assertStringContainsString( $reason, $this->html_notice_messages()[0], 'Expected the notice to name the reason.' );
		$this->assertSame( array(), $this->announced_paths(), 'Expected the reference not to be resolved.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string, 1: string, 2: string }> Data provider.
	 */
	public static function data_structurally_declining_hosts(): array {
		return array(
			'void element'               => array(
				'<img data-wp-html="state.html">',
				'<img data-wp-html="state.html">',
				'<img>',
			),
			'element with raw content'   => array(
				'<textarea data-wp-html="state.html">Fallback</textarea>',
				'<textarea data-wp-html="state.html">Fallback</textarea>',
				'<textarea>',
			),
			'combined with data-wp-text' => array(
				'<div data-wp-html="state.html" data-wp-text="state.text">Fallback</div>',
				'<div data-wp-html="state.html" data-wp-text="state.text">processed</div>',
				'"data-wp-text"',
			),
			'template with data-wp-each' => array(
				'<template data-wp-each="state.list" data-wp-html="state.html"><p data-wp-text="context.item">x</p></template>',
				'<template data-wp-each="state.list" data-wp-html="state.html"><p data-wp-text="context.item">x</p></template>' .
				'<p data-wp-each-child="test::state.list" data-wp-text="context.item">a</p>' .
				'<p data-wp-each-child="test::state.list" data-wp-text="context.item">b</p>',
				'"data-wp-each"',
			),
		);
	}

	/**
	 * Tests that an authored non-string value declines with one not-a-token
	 * notice and processes the markup after the host.
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
		$this->setExpectedIncorrectUsage( self::NOTICE_SOURCE );

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
			'JSON object'        => array( '{"a":"b"}' ),
			'namespaced integer' => array( 'test::42' ),
		);
	}

	/**
	 * Tests that only the default entry renders, and that entries with a suffix
	 * or a unique ID, and empty entries, are ignored without a notice.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_only_the_default_entry_renders() {
		$this->set_state( array( 'html' => $this->token() ) );
		$ignored = '<div data-wp-html--x="state.html" data-wp-html---id="state.html">Fallback</div>' .
			'<div data-wp-html="">Fallback</div><div data-wp-html>Fallback</div>';

		$output = wp_interactivity_process_directives(
			$this->page( $ignored . '<div data-wp-html--x="state.missing" data-wp-html="state.html" data-wp-html---id="state.missing">Fallback</div>' )
		);

		$this->assertSame(
			$this->processed_page( $ignored . '<div data-wp-html--x="state.missing" data-wp-html="state.html" data-wp-html---id="state.missing">' . self::MARKER_HTML . '</div>' ),
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
	 * Tests that markup in the input that looks like a placeholder is not
	 * mistaken for one, however many marker variants it holds.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_substitution_cannot_be_forged() {
		$lookalikes = '<!--wp-interactivity-dangerous-html:0--><!--wp-interactivity-dangerous-html_:0-->';
		$this->set_state( array( 'html' => $this->token() ) );

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test"><i>' . $lookalikes . '</i><p data-wp-html="state.html">One</p></div>'
		);

		$this->assertSame(
			'<div data-wp-interactive="test"><i>' . $lookalikes . '</i><p data-wp-html="state.html">' . self::MARKER_HTML . '</p></div>',
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
	 * Tests that a token restored by unserialization does not render but prints
	 * as the marker like any other token: the host keeps its fallback, exactly
	 * one notice is raised for the unserialization and one for the decline, and
	 * the markup after the host is processed. The token that was serialized
	 * still renders.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 * @covers ::filter_script_module_interactivity_data
	 * @covers WP_Interactivity_Dangerous_HTML::__wakeup
	 */
	public function test_unserialized_token_declines_as_not_a_token() {
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_Dangerous_HTML::__wakeup' );
		$this->setExpectedIncorrectUsage( self::NOTICE_SOURCE );

		$original = $this->token();
		$value    = unserialize( serialize( $original ) );

		$this->assertSame(
			array( 'WP_Interactivity_Dangerous_HTML::__wakeup' ),
			array_column( $this->notices, 'source' ),
			'Expected exactly one notice at unserialization.'
		);

		$this->set_state(
			array(
				'html'     => $value,
				'original' => $original,
			)
		);

		$output = wp_interactivity_process_directives(
			$this->page( '<div data-wp-html="state.html">Fallback</div><div data-wp-html="state.original">Fallback</div>' )
		);

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="state.html">Fallback</div><div data-wp-html="state.original">' . self::MARKER_HTML . '</div>' ),
			$output,
			'Expected the unserialized token to decline and the serialized one to render.'
		);
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
		$this->assertCount( 2, $this->notices, 'Expected no notice besides those of the unserialization and the decline.' );
		// Keep assertEquals() because the objects are intentionally compared by value.
		$this->assertEquals(
			(object) array( 'wpServerHtml' => true ),
			json_decode( $this->printed_client_data() )->state->test->html,
			'Expected the token to print as the marker.'
		);
	}

	/**
	 * Tests that a token in `wp_interactivity_data_wp_context()` prints as the
	 * marker, so its HTML reaches neither the output nor the client data, and
	 * that referencing it from other directives neither renders it nor aborts
	 * processing.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_token_in_context_helper_reaches_no_output_or_client_data() {
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::data_wp_bind_processor' );
		$this->setExpectedIncorrectUsage( self::NOTICE_SOURCE );

		$wrapper = '<div data-wp-interactive="test" ' . wp_interactivity_data_wp_context( array( 'h' => $this->token() ) ) . '>';
		$output  = wp_interactivity_process_directives(
			$wrapper .
			'<div data-wp-html="context.h">Fallback</div>' .
			'<div data-wp-bind--title="context.h">z</div>' .
			'<span data-wp-text="state.text">x</span></div>'
		);

		$this->assertSame(
			$wrapper .
			'<div data-wp-html="context.h">Fallback</div>' .
			'<div data-wp-bind--title="context.h">z</div>' .
			'<span data-wp-text="state.text">processed</span></div>',
			$output,
			'Expected the context to hold no HTML to render, and the markup after it to be processed.'
		);
		$this->assertStringContainsString( '{"h":{"wpServerHtml":true}}', $output, 'Expected the token to print as the marker in the context attribute.' );
		$this->assertStringNotContainsString( 'marker-7f3a', $output );
		$this->assertStringNotContainsString( 'marker-7f3a', $this->printed_client_data() );
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected the value read from context to decline as not a token.' );
	}

	/**
	 * Tests that every host renders the token its reference reaches through
	 * state, at any depth and in any namespace.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_tokens_render_at_any_depth_and_namespace() {
		$this->set_state(
			array(
				'html' => $this->token(),
				'deep' => array( 'er' => array( 'html' => $this->token() ) ),
			)
		);
		wp_interactivity_state(
			'other',
			array(
				'html' => $this->token(),
				'deep' => array( 'er' => array( 'html' => $this->token() ) ),
			)
		);

		$hosts    = '';
		$rendered = '';
		foreach ( array( 'state.deep.er.html', 'state.deep.er.html', 'state.html', 'other::state.html', 'other::state.deep.er.html', 'other::state.deep.er.html' ) as $reference ) {
			$hosts    .= '<div data-wp-html="' . $reference . '">Fallback</div>';
			$rendered .= '<div data-wp-html="' . $reference . '">' . self::MARKER_HTML . '</div>';
		}
		$output = wp_interactivity_process_directives( $this->page( $hosts ) );

		$this->assertSame( $this->processed_page( $rendered ), $output, 'Expected every host to render its token.' );
	}

	/**
	 * Tests that a token reached through the result of a derived-state closure
	 * renders and only the closure's path is listed.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 * @covers ::filter_script_module_interactivity_data
	 */
	public function test_token_in_a_closure_result_lists_only_the_closure() {
		$token = $this->token();
		$this->set_state(
			array(
				'foo' => static function () use ( $token ) {
					return array( 'bar' => $token );
				},
			)
		);

		$output = wp_interactivity_process_directives( $this->page( '<div data-wp-html="state.foo.bar">Fallback</div>' ) );

		$this->assertSame(
			$this->processed_page( '<div data-wp-html="state.foo.bar">' . self::MARKER_HTML . '</div>' ),
			$output
		);
		$this->assertSame( array( 'test' => array( 'state.foo' ) ), $this->announced_paths() );
	}

	/**
	 * Tests that tokens reached through `context`, such as the items of
	 * `data-wp-each`, render without a notice, add no listed path, and print
	 * as the marker in the state that holds them.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 * @covers ::filter_script_module_interactivity_data
	 */
	public function test_tokens_reached_through_context_render_and_print_as_the_marker() {
		$this->set_state(
			array(
				'items' => array(
					array( 'html' => $this->token() ),
					array( 'html' => $this->token() ),
				),
			)
		);

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test"><ul><template data-wp-each="state.items"><li data-wp-html="context.item.html">Fallback</li></template></ul></div>'
		);

		$this->assertSame( 2, substr_count( $output, '<li data-wp-each-child="test::state.items" data-wp-html="context.item.html">' . self::MARKER_HTML . '</li>' ) );
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
		$this->assertSame( array(), $this->announced_paths(), 'Expected no listed path.' );
		$this->assertStringContainsString(
			'"items":[{"html":{"wpServerHtml":true}},{"html":{"wpServerHtml":true}}]',
			$this->printed_client_data(),
			'Expected each item token to print as the marker.'
		);
	}

	/**
	 * Tests that a token prints as the marker on both encoding paths of
	 * `wp_json_encode()`, including the one taken when the data holds a string
	 * that is not valid UTF-8, and that iterating a token yields only the
	 * marker.
	 *
	 * @ticket 66246
	 *
	 * @covers WP_Interactivity_Dangerous_HTML::jsonSerialize
	 * @covers WP_Interactivity_Dangerous_HTML::getIterator
	 */
	public function test_token_prints_as_the_marker_on_both_encoding_paths() {
		$token = $this->token();

		$this->assertSame( '{"wpServerHtml":true}', wp_json_encode( $token ) );

		$data = array(
			'bad'   => "caf\xE9",
			'token' => $token,
		);
		$this->assertFalse( json_encode( $data ), 'Expected the data to take the fallback path.' );
		$fallback = wp_json_encode( $data );
		$this->assertIsString( $fallback );
		$this->assertStringContainsString( '"token":{"wpServerHtml":true}', $fallback, 'Expected the fallback path to print the marker.' );
		$this->assertStringNotContainsString( 'marker-7f3a', $fallback );

		$this->assertSame( array( 'wpServerHtml' => true ), iterator_to_array( $token ) );
	}

	/**
	 * Tests that, in block rendering, each root interactive block renders the
	 * token its render callback puts in state, as its directives are processed
	 * as soon as it has rendered.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 * @covers WP_Block::render
	 */
	public function test_root_interactive_blocks_render_the_tokens_they_set() {
		register_block_type(
			'test/dangerous-html',
			array(
				'render_callback' => static function ( $attributes ) {
					wp_interactivity_state( 'test', array( 'html' => wp_interactivity_as_dangerous_html( '<b>' . $attributes['label'] . '</b>' ) ) );
					return '<div data-wp-interactive="test" data-wp-html="state.html">Fallback</div>';
				},
				'supports'        => array(
					'interactivity' => true,
				),
			)
		);

		$output = do_blocks( '<!-- wp:test/dangerous-html {"label":"one"} /--><!-- wp:test/dangerous-html {"label":"two"} /-->' );
		unregister_block_type( 'test/dangerous-html' );

		$this->assertSame(
			'<div data-wp-interactive="test" data-wp-html="state.html"><b>one</b></div>' .
			'<div data-wp-interactive="test" data-wp-html="state.html"><b>two</b></div>',
			$output
		);
	}
}
