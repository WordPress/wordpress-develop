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
	 * Children with directives, for an element whose content is processed or
	 * replaced.
	 */
	const CHILDREN = '<span data-wp-text="state.text">x</span><b data-wp-class--on="state.on">y</b>';

	/**
	 * The children once processed.
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
	 * Tests that a value that is not a token empties the host without processing
	 * its content, and raises exactly one notice, which names the function that
	 * creates tokens.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 * @covers WP_Interactivity_Dangerous_HTML::get_html
	 *
	 * @dataProvider data_values_that_are_not_tokens
	 *
	 * @param string $attributes The attributes of the host.
	 * @param array  $state      The state of the `test` namespace.
	 */
	public function test_value_that_is_not_a_token_empties_the_host_with_one_notice( string $attributes, array $state ) {
		$this->setExpectedIncorrectUsage( self::NOTICE_SOURCE );
		$this->set_state( $state );

		$output = wp_interactivity_process_directives( $this->page( '<div ' . $attributes . '>' . self::CHILDREN . '</div>' ) );

		$this->assertSame( $this->processed_page( '<div ' . $attributes . '></div>' ), $output );
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
		$this->assertCount( 1, $this->notices, 'Expected no other notice.' );
		$this->assertStringContainsString( 'wp_interactivity_as_dangerous_html()', $this->html_notice_messages()[0] );
		$this->assertSame( array(), $this->announced_paths(), 'Expected no closure to run.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string, 1: array }> Data provider.
	 */
	public static function data_values_that_are_not_tokens(): array {
		$token = wp_interactivity_as_dangerous_html( '<p>Hi</p>' );

		return array(
			'string of HTML'                => array( 'data-wp-html="state.html"', array( 'html' => '<p>Hi</p>' ) ),
			'integer'                       => array( 'data-wp-html="state.html"', array( 'html' => 1 ) ),
			'false'                         => array( 'data-wp-html="state.html"', array( 'html' => false ) ),
			'list'                          => array( 'data-wp-html="state.html"', array( 'html' => array( '<p>Hi</p>' ) ) ),
			'marker imitated by an array'   => array( 'data-wp-html="state.html"', array( 'html' => array( 'wpServerHtml' => true ) ) ),
			'marker imitated by an object'  => array(
				'data-wp-html="state.html"',
				array(
					'html' => new class() implements JsonSerializable {
						public function jsonSerialize(): array {
							return array( 'wpServerHtml' => true );
						}
					},
				),
			),
			'negated token'                 => array( 'data-wp-html="!state.html"', array( 'html' => $token ) ),
			'negated reserved path segment' => array( 'data-wp-html="test::!state.obj.constructor"', array( 'obj' => array( 'constructor' => $token ) ) ),
			'doubly negated closure'        => array(
				'data-wp-html="!!state.html"',
				array(
					'html' => static function () use ( $token ) {
						return $token;
					},
				),
			),
			'string in context'             => array( 'data-wp-context=\'{"h":"<p>Hi</p>"}\' data-wp-html="context.h"', array() ),
		);
	}

	/**
	 * Tests that a reference that resolves to `null` or to nothing empties the
	 * host without processing its content, and without a notice.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_references_that_resolve_to_nothing
	 *
	 * @param string $attributes The attributes of the host.
	 */
	public function test_reference_that_resolves_to_nothing_empties_the_host_without_notice( string $attributes ) {
		$token = $this->token();
		$this->set_state(
			array(
				'html'        => $token,
				'nul'         => null,
				'constructor' => $token,
			)
		);

		$output = wp_interactivity_process_directives( $this->page( '<div ' . $attributes . '>' . self::CHILDREN . '</div>' ) );

		$this->assertSame( $this->processed_page( '<div ' . $attributes . '></div>' ), $output );
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }> Data provider.
	 */
	public static function data_references_that_resolve_to_nothing(): array {
		return array(
			'null in state'         => array( 'data-wp-html="state.nul"' ),
			'missing path'          => array( 'data-wp-html="state.missing"' ),
			'reserved path segment' => array( 'data-wp-html="state.constructor"' ),
			'quoted reference'      => array( 'data-wp-html=\'"state.html"\'' ),
			'empty value'           => array( 'data-wp-html=""' ),
			'no value'              => array( 'data-wp-html' ),
		);
	}

	/**
	 * Tests that a reference whose evaluation fails empties the host with only
	 * the notice of the evaluation: a derived-state closure that throws, a host
	 * processed apart from the `data-wp-interactive` element that gives it its
	 * namespace, and a host whose `data-wp-interactive` names a reserved word.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_references_whose_evaluation_fails
	 *
	 * @param string $input    The HTML to process.
	 * @param string $expected The expected output.
	 */
	public function test_failed_evaluation_empties_the_host_without_a_data_wp_html_notice( string $input, string $expected ) {
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::evaluate' );
		$this->set_state(
			array(
				'html'   => $this->token(),
				'throws' => static function () {
					throw new Exception( 'Failed.' );
				},
			)
		);

		$this->assertSame( $expected, wp_interactivity_process_directives( $input ) );
		$this->assertSame( array( 'WP_Interactivity_API::evaluate' ), array_column( $this->notices, 'source' ), 'Expected only the notice of the evaluation.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string, 1: string }> Data provider.
	 */
	public static function data_references_whose_evaluation_fails(): array {
		return array(
			'throwing closure'   => array(
				'<div data-wp-interactive="test"><div data-wp-html="state.throws">' . self::CHILDREN . '</div></div>',
				'<div data-wp-interactive="test"><div data-wp-html="state.throws"></div></div>',
			),
			'no namespace'       => array(
				'<div data-wp-html="state.html">' . self::CHILDREN . '</div>',
				'<div data-wp-html="state.html"></div>',
			),
			'reserved namespace' => array(
				'<div data-wp-interactive="constructor"><div data-wp-html="state.html">' . self::CHILDREN . '</div></div>',
				'<div data-wp-interactive="constructor"><div data-wp-html="state.html"></div></div>',
			),
		);
	}

	/**
	 * Tests that a host under a `data-wp-interactive` directive that names no
	 * namespace is emptied, with only the notice of the missing namespace, even
	 * though an enclosing one names a namespace. Other directives there still
	 * resolve in the enclosing namespace.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_islands_that_name_no_namespace
	 *
	 * @param string $island The attributes of the inner `data-wp-interactive` element.
	 */
	public function test_host_under_an_island_that_names_no_namespace_is_emptied( string $island ) {
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_API::evaluate' );
		$this->set_state( array( 'html' => $this->token() ) );

		$output = wp_interactivity_process_directives(
			$this->page( '<div ' . $island . '><p data-wp-html="state.html">' . self::CHILDREN . '</p><span data-wp-text="state.text">x</span></div>' )
		);

		$this->assertSame(
			$this->processed_page( '<div ' . $island . '><p data-wp-html="state.html"></p><span data-wp-text="state.text">processed</span></div>' ),
			$output
		);
		$this->assertSame( array( 'WP_Interactivity_API::evaluate' ), array_column( $this->notices, 'source' ), 'Expected only the notice of the missing namespace.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }> Data provider.
	 */
	public static function data_islands_that_name_no_namespace(): array {
		return array(
			'no value'                   => array( 'data-wp-interactive' ),
			'object without a namespace' => array( 'data-wp-interactive=\'{"a":1}\'' ),
		);
	}

	/**
	 * Tests that an element whose only `data-wp-interactive` directive has a
	 * unique ID is not an island, so a host inside it resolves its reference
	 * in the enclosing namespace.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_host_under_a_unique_id_interactive_directive_keeps_the_enclosing_namespace() {
		$this->set_state( array( 'html' => $this->token() ) );

		$output = wp_interactivity_process_directives(
			$this->page( '<div data-wp-interactive---x><p data-wp-html="state.html">Fallback</p></div>' )
		);

		$this->assertSame(
			$this->processed_page( '<div data-wp-interactive---x><p data-wp-html="state.html">' . self::MARKER_HTML . '</p></div>' ),
			$output
		);
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Tests that a directive the client cannot combine with `data-wp-html`
	 * makes the element render as if it had no `data-wp-html` directive, with
	 * one notice naming that directive, and without resolving the reference.
	 *
	 * A reference that was resolved through a derived-state closure would be
	 * listed in the client data, so an empty list shows it was not.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_directives_that_conflict
	 *
	 * @param string $host     The host markup.
	 * @param string $expected The expected host markup.
	 * @param string $name     The name of the conflicting directive.
	 */
	public function test_conflicting_directive_ignores_data_wp_html_with_one_notice( string $host, string $expected, string $name ) {
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
		$this->assertStringContainsString( '"' . $name . '"', $this->html_notice_messages()[0], 'Expected the notice to name the directive.' );
		$this->assertSame( array(), $this->announced_paths(), 'Expected the reference not to be resolved.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string, 1: string, 2: string }> Data provider.
	 */
	public static function data_directives_that_conflict(): array {
		return array(
			'data-wp-text'                             => array(
				'<div data-wp-html="state.html" data-wp-text="state.text">Fallback</div>',
				'<div data-wp-html="state.html" data-wp-text="state.text">processed</div>',
				'data-wp-text',
			),
			'data-wp-text with a suffix'               => array(
				'<div data-wp-html="state.html" data-wp-text--x="state.text">' . self::CHILDREN . '</div>',
				'<div data-wp-html="state.html" data-wp-text--x="state.text">' . self::PROCESSED_CHILDREN . '</div>',
				'data-wp-text',
			),
			'data-wp-text with a unique ID'            => array(
				'<div data-wp-html="state.html" data-wp-text---x="state.text">' . self::CHILDREN . '</div>',
				'<div data-wp-html="state.html" data-wp-text---x="state.text">' . self::PROCESSED_CHILDREN . '</div>',
				'data-wp-text',
			),
			'data-wp-router-region'                    => array(
				'<div data-wp-html="state.html" data-wp-router-region="region">' . self::CHILDREN . '</div>',
				'<div data-wp-html="state.html" data-wp-router-region="region">' . self::PROCESSED_CHILDREN . '</div>',
				'data-wp-router-region',
			),
			'data-wp-each on a div'                    => array(
				'<div data-wp-each="state.list" data-wp-html="state.html">' . self::CHILDREN . '</div>',
				'<div data-wp-each="state.list" data-wp-html="state.html">' . self::PROCESSED_CHILDREN . '</div>',
				'data-wp-each',
			),
			'data-wp-each with a suffix on a template' => array(
				'<template data-wp-each--letter="state.list" data-wp-html="state.html"><p data-wp-text="context.letter">x</p></template>',
				'<template data-wp-each--letter="state.list" data-wp-html="state.html"><p data-wp-text="context.letter">x</p></template>' .
				'<p data-wp-each-child="test::state.list" data-wp-text="context.letter">a</p>' .
				'<p data-wp-each-child="test::state.list" data-wp-text="context.letter">b</p>',
				'data-wp-each',
			),
		);
	}

	/**
	 * Tests that a `data-wp-html` directive the client drops, or whose only
	 * entries have a suffix, leaves the element to render as if it had none,
	 * and that a directive the client drops does not conflict with
	 * `data-wp-html`. No notice is raised.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_directives_the_client_drops
	 *
	 * @param string $host     The host markup.
	 * @param string $expected The expected host markup.
	 */
	public function test_directive_the_client_drops_is_ignored( string $host, string $expected ) {
		$this->set_state( array( 'html' => $this->token() ) );
		wp_interactivity_state( 'constructor', array( 'html' => $this->token() ) );

		$output = wp_interactivity_process_directives( $this->page( $host ) );

		$this->assertSame( $this->processed_page( $expected ), $output );
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string, 1: string }> Data provider.
	 */
	public static function data_directives_the_client_drops(): array {
		return array(
			'reserved namespace in the value'              => array(
				'<div data-wp-html="constructor::state.html">' . self::CHILDREN . '</div>',
				'<div data-wp-html="constructor::state.html">' . self::PROCESSED_CHILDREN . '</div>',
			),
			'reserved unique ID'                           => array(
				'<div data-wp-html---Prototype-="state.html">' . self::CHILDREN . '</div>',
				'<div data-wp-html---Prototype-="state.html">' . self::PROCESSED_CHILDREN . '</div>',
			),
			'suffix only'                                  => array(
				'<div data-wp-html--x="state.html">' . self::CHILDREN . '</div>',
				'<div data-wp-html--x="state.html">' . self::PROCESSED_CHILDREN . '</div>',
			),
			'conflicting directive with a reserved suffix' => array(
				'<div data-wp-html="state.html" data-wp-text--constructor="state.text">Fallback</div>',
				'<div data-wp-html="state.html" data-wp-text--constructor="state.text">' . self::MARKER_HTML . '</div>',
			),
		);
	}

	/**
	 * Tests that no directive inside the content of an element that carries a
	 * `data-wp-html` entry without a suffix or unique ID is processed, and that
	 * no derived-state closure referenced only there runs. Hosts get the content
	 * their reference decides.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 * @covers ::filter_script_module_interactivity_data
	 */
	public function test_content_of_a_data_wp_html_element_is_not_processed() {
		$this->set_state(
			array(
				'html'  => $this->token(),
				'nul'   => null,
				'label' => static function () {
					return 'derived';
				},
			)
		);
		$content = '<span data-wp-text="state.label">As written</span>';

		$output = wp_interactivity_process_directives(
			$this->page(
				'<div data-wp-html="state.nul">' . $content . '</div>' .
				'<div data-wp-html="state.html">' . $content . '</div>'
			)
		);

		$this->assertSame(
			$this->processed_page(
				'<div data-wp-html="state.nul"></div>' .
				'<div data-wp-html="state.html">' . self::MARKER_HTML . '</div>'
			),
			$output
		);
		$this->assertSame( array(), $this->announced_paths(), 'Expected no closure to be listed.' );
	}

	/**
	 * Tests that a unique-ID entry is ignored without evaluating its reference,
	 * while its children are processed and their derived state is tracked.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 * @covers ::filter_script_module_interactivity_data
	 */
	public function test_unique_id_entry_does_not_prevent_processing_children() {
		$html_calls  = 0;
		$label_calls = 0;
		$token       = $this->token();
		$this->set_state(
			array(
				'html'  => static function () use ( &$html_calls, $token ) {
					++$html_calls;
					return $token;
				},
				'label' => static function () use ( &$label_calls ) {
					++$label_calls;
					return 'derived';
				},
			)
		);

		$output = wp_interactivity_process_directives(
			$this->page( '<div data-wp-html---x="state.html"><span data-wp-text="state.label">Fallback</span></div>' )
		);

		$this->assertSame(
			$this->processed_page( '<div data-wp-html---x="state.html"><span data-wp-text="state.label">derived</span></div>' ),
			$output
		);
		$this->assertSame( 0, $html_calls, 'Expected the unique-ID reference not to be evaluated.' );
		$this->assertSame( 1, $label_calls, 'Expected the child reference to be evaluated once.' );
		$this->assertSame( array( 'test' => array( 'state.label' ) ), $this->announced_paths() );
		$this->assertSame( array(), $this->notices, 'Expected no notice.' );
	}

	/**
	 * Tests that data-wp-html is ignored on void and text-only elements without
	 * evaluating its reference. Content is preserved, one notice names the
	 * unsupported element, and other directives on and after it still run.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_hosts_that_cannot_hold_html
	 *
	 * @param string $host     The host markup.
	 * @param string $expected The expected host markup.
	 * @param string $notice   Text the only notice contains.
	 */
	public function test_host_that_cannot_hold_html( string $host, string $expected, string $notice ) {
		$this->setExpectedIncorrectUsage( self::NOTICE_SOURCE );
		$token = $this->token();
		$calls = 0;
		$this->set_state(
			array(
				'nul'    => null,
				'str'    => '<b>S</b>',
				'token'  => $token,
				'closer' => wp_interactivity_as_dangerous_html( '</textarea><b>B</b>' ),
				'html'   => static function () use ( $token, &$calls ) {
					++$calls;
					return $token;
				},
			)
		);
		$output = wp_interactivity_process_directives( $this->page( $host ) );

		$this->assertSame( $this->processed_page( $expected ), $output );
		$this->assertCount( 1, $this->notices, 'Expected exactly one notice.' );
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected a data-wp-html notice.' );
		$this->assertStringContainsString( $notice, $this->html_notice_messages()[0] );
		$this->assertSame( 0, $calls, 'Expected the reference not to be evaluated.' );
		$this->assertSame( array(), $this->announced_paths(), 'Expected no closure to be listed.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string, 1: string, 2: string }> Data provider.
	 */
	public static function data_hosts_that_cannot_hold_html(): array {
		$cases      = array();
		$attributes = array(
			'null'          => 'data-wp-html="state.nul"',
			'string'        => 'data-wp-html="state.str"',
			'token'         => 'data-wp-html="state.token"',
			'closing token' => 'data-wp-html="state.closer"',
			'closure'       => 'data-wp-html="state.html"',
			'missing path'  => 'data-wp-html="state.missing"',
			'empty value'   => 'data-wp-html=""',
			'no value'      => 'data-wp-html',
		);

		foreach ( array( 'textarea', 'title', 'script', 'style', 'iframe', 'xmp', 'noembed', 'noframes', 'img', 'input' ) as $tag ) {
			$content = in_array( $tag, array( 'img', 'input' ), true ) ? '' : 'Fallback &amp; <b>as written</b></' . $tag . '>';
			foreach ( $attributes as $name => $attribute ) {
				$markup                           = $attribute . ' data-wp-bind--title="state.text">' . $content;
				$cases[ $tag . ' with ' . $name ] = array(
					'<' . $tag . ' ' . $markup,
					'<' . $tag . ' title="processed" ' . $markup,
					'not supported on <' . $tag . '> elements',
				);
			}
		}

		return $cases;
	}

	/**
	 * Tests that only the default entry renders, and that entries with a suffix
	 * or a unique ID are ignored without a notice.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_only_the_default_entry_renders() {
		$this->set_state( array( 'html' => $this->token() ) );
		$ignored = '<div data-wp-html--x="state.html" data-wp-html---id="state.html">Fallback</div>';

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
	 * Tests that a call holding more hosts than the HTML API allows seeking
	 * renders every host and processes the directives after them.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_hosts_beyond_the_seek_limit_render() {
		$count = WP_HTML_Tag_Processor::MAX_SEEK_OPS + 1;
		$this->set_state( array( 'html' => $this->token() ) );

		$output = wp_interactivity_process_directives(
			$this->page( str_repeat( '<div data-wp-html="state.html">Fallback</div>', $count ) . '<p data-wp-bind--title="state.text">z</p>' )
		);

		$this->assertSame(
			$this->processed_page(
				str_repeat( '<div data-wp-html="state.html">' . self::MARKER_HTML . '</div>', $count ) .
				'<p title="processed" data-wp-bind--title="state.text">z</p>'
			),
			$output
		);
	}

	/**
	 * Tests that a call made while another call is processing returns its own
	 * processed markup, and that the other call then completes as without it:
	 * every host holds its token's HTML, and the directives after the nested
	 * call resolve in the namespace and context in effect there.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_ways_to_make_a_nested_call
	 *
	 * @param callable $make_value Callable that receives a callable making the nested call, and
	 *                             returns the value of `state.inner` whose `markup` is its result.
	 */
	public function test_nested_call_keeps_the_outer_call_processing( callable $make_value ) {
		$this->set_state(
			array(
				'html'  => $this->token(),
				'inner' => $make_value(
					static function () {
						return wp_interactivity_process_directives( '<i data-wp-interactive="test" data-wp-html="state.html">Inner</i>' );
					}
				),
			)
		);
		$host = '<p data-wp-html="state.html">Fallback</p>';

		$output = wp_interactivity_process_directives(
			'<div data-wp-interactive="test" data-wp-context=\'{"id":"outer"}\'>' . $host .
			'<span data-wp-text="state.inner.markup">x</span>' . $host .
			'<b data-wp-bind--title="context.id">z</b></div>'
		);

		$rendered = '<p data-wp-html="state.html">' . self::MARKER_HTML . '</p>';
		$this->assertSame(
			'<div data-wp-interactive="test" data-wp-context=\'{"id":"outer"}\'>' . $rendered .
			'<span data-wp-text="state.inner.markup">' . esc_html( '<i data-wp-interactive="test" data-wp-html="state.html">' . self::MARKER_HTML . '</i>' ) . '</span>' . $rendered .
			'<b title="outer" data-wp-bind--title="context.id">z</b></div>',
			$output
		);
		$this->assertStringNotContainsString( 'wp-interactivity-dangerous-html', $output, 'Expected no placeholder to be left.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: callable }> Data provider.
	 */
	public static function data_ways_to_make_a_nested_call(): array {
		return array(
			'derived-state closure'            => array(
				static function ( callable $render ) {
					return static function () use ( $render ) {
						return array( 'markup' => $render() );
					};
				},
			),
			'state value read during the call' => array(
				function ( callable $render ) {
					return new class( $render ) implements ArrayAccess {
						/**
						 * Makes the nested call.
						 *
						 * @var callable
						 */
						private $render;

						public function __construct( callable $render ) {
							$this->render = $render;
						}

						public function offsetExists( $offset ): bool {
							return 'markup' === $offset;
						}

						#[ReturnTypeWillChange]
						public function offsetGet( $offset ) {
							return ( $this->render )();
						}

						public function offsetSet( $offset, $value ): void {}

						public function offsetUnset( $offset ): void {}
					};
				},
			),
		);
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
	 * as the marker like any other token: the host is emptied, exactly one
	 * notice is raised for the unserialization and one for the value that is
	 * not a token, and the markup after the host is processed. The token that
	 * was serialized still renders.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 * @covers ::filter_script_module_interactivity_data
	 * @covers WP_Interactivity_Dangerous_HTML::__wakeup
	 */
	public function test_unserialized_token_is_not_a_token() {
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
			$this->processed_page( '<div data-wp-html="state.html"></div><div data-wp-html="state.original">' . self::MARKER_HTML . '</div>' ),
			$output,
			'Expected the unserialized token to empty its host and the serialized one to render.'
		);
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected exactly one data-wp-html notice.' );
		$this->assertCount( 2, $this->notices, 'Expected no notice besides those of the unserialization and of the value.' );
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
			'<div data-wp-html="context.h"></div>' .
			'<div data-wp-bind--title="context.h">z</div>' .
			'<span data-wp-text="state.text">processed</span></div>',
			$output,
			'Expected the context to hold no HTML to render, and the markup after it to be processed.'
		);
		$this->assertStringContainsString( '{"h":{"wpServerHtml":true}}', $output, 'Expected the token to print as the marker in the context attribute.' );
		$this->assertStringNotContainsString( 'marker-7f3a', $output );
		$this->assertStringNotContainsString( 'marker-7f3a', $this->printed_client_data() );
		$this->assertCount( 1, $this->html_notice_messages(), 'Expected the value read from context not to be a token.' );
	}

	/**
	 * Tests that every host renders the token its reference reaches through
	 * state, at any depth, through an object property, and in any namespace.
	 *
	 * @ticket 66246
	 *
	 * @covers ::process_directives
	 */
	public function test_tokens_render_at_any_depth_and_namespace() {
		$this->set_state(
			array(
				'html'   => $this->token(),
				'deep'   => array( 'er' => array( 'html' => $this->token() ) ),
				'object' => (object) array( 'html' => $this->token() ),
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
		foreach ( array( 'state.deep.er.html', 'state.deep.er.html', 'state.html', 'state.object.html', 'other::state.html', 'other::state.deep.er.html', 'other::state.deep.er.html' ) as $reference ) {
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
