<?php
/**
 * Unit tests covering the WP_Interactivity_Dangerous_HTML token and the
 * wp_interactivity_as_dangerous_html() function.
 *
 * @package WordPress
 * @subpackage Interactivity API
 *
 * @since 7.2.0
 *
 * @group interactivity-api
 *
 * @covers WP_Interactivity_Dangerous_HTML
 * @covers ::wp_interactivity_as_dangerous_html
 */
class Tests_Interactivity_API_WpInteractivityDangerousHtml extends WP_UnitTestCase {
	/**
	 * Tests that the function returns a token reporting the given HTML byte for byte.
	 *
	 * @ticket 66246
	 *
	 * @dataProvider data_html_strings
	 *
	 * @param string $html HTML to wrap in a token.
	 */
	public function test_factory_returns_token_with_exact_html( string $html ) {
		$token = wp_interactivity_as_dangerous_html( $html );

		$this->assertInstanceOf( WP_Interactivity_Dangerous_HTML::class, $token );
		$this->assertSame( $html, WP_Interactivity_Dangerous_HTML::get_html( $token ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_html_strings(): array {
		return array(
			'empty string'      => array( '' ),
			'plain text'        => array( 'Hello' ),
			'markup'            => array( '<b class="a">Hi</b> <i>there</i>' ),
			'malformed markup'  => array( '</div><p><b>unclosed <!-- comment' ),
			'script and entity' => array( '<script>alert(1)</script>&amp;&lt;' ),
			'whitespace'        => array( " \t\r\n<br>\n " ),
			'null byte'         => array( "a\0b" ),
			'multibyte'         => array( 'Ünï© 日本 😀' ),
		);
	}

	/**
	 * Tests that tokens created with `new` or `clone` report the same HTML as
	 * one returned by the function.
	 *
	 * @ticket 66246
	 */
	public function test_new_and_clone_tokens_report_the_same_html() {
		$html     = '<em>x</em>';
		$factory  = wp_interactivity_as_dangerous_html( $html );
		$new      = new WP_Interactivity_Dangerous_HTML( $html );
		$cloned   = clone $factory;
		$cloned_2 = clone $new;

		$this->assertSame( $html, WP_Interactivity_Dangerous_HTML::get_html( $factory ) );
		$this->assertSame( $html, WP_Interactivity_Dangerous_HTML::get_html( $new ) );
		$this->assertSame( $html, WP_Interactivity_Dangerous_HTML::get_html( $cloned ) );
		$this->assertSame( $html, WP_Interactivity_Dangerous_HTML::get_html( $cloned_2 ) );
	}

	/**
	 * Tests that the token class reports no HTML for values that are not tokens.
	 *
	 * @ticket 66246
	 *
	 * @dataProvider data_non_tokens
	 *
	 * @param mixed $value Value that is not a token.
	 */
	public function test_get_html_returns_null_for_values_that_are_not_tokens( $value ) {
		$this->assertNull( WP_Interactivity_Dangerous_HTML::get_html( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_non_tokens(): array {
		$token_like       = new stdClass();
		$token_like->html = '<b>x</b>';

		return array(
			'null'                 => array( null ),
			'string'               => array( '<b>x</b>' ),
			'empty string'         => array( '' ),
			'integer'              => array( 1 ),
			'boolean'              => array( true ),
			'array'                => array( array( '<b>x</b>' ) ),
			'array with html key'  => array( array( 'html' => '<b>x</b>' ) ),
			'stdClass'             => array( $token_like ),
			'other class'          => array( new ArrayObject( array( 'html' => '<b>x</b>' ) ) ),
			'json_decode object'   => array( json_decode( '{"html":"<b>x</b>"}' ) ),
			'json_decode array'    => array( json_decode( '{"html":"<b>x</b>"}', true ) ),
			'json_decode string'   => array( json_decode( '"<b>x</b>"' ) ),
			'json_decode of token' => array( json_decode( wp_json_encode( new WP_Interactivity_Dangerous_HTML( '<b>x</b>' ) ) ) ),
		);
	}

	/**
	 * Tests that encoding a token as JSON leaves its HTML out.
	 *
	 * @ticket 66246
	 */
	public function test_json_encoding_does_not_include_html() {
		$token = wp_interactivity_as_dangerous_html( '<b>secret-html</b>' );

		$this->assertSame( '{}', wp_json_encode( $token ) );
		$this->assertSame( '{"a":{"b":{}}}', wp_json_encode( array( 'a' => array( 'b' => $token ) ) ) );
		$this->assertStringNotContainsString( 'secret-html', var_export( json_encode( $token, JSON_PARTIAL_OUTPUT_ON_ERROR ), true ) );
	}

	/**
	 * Every `_doing_it_wrong()` call of the test, in order.
	 *
	 * @var string[]
	 */
	private $notice_sources = array();

	/**
	 * Records the source of a `_doing_it_wrong()` call, counting every call
	 * rather than only the first of each source as the test case does.
	 *
	 * @param string $source The function or method the notice names.
	 */
	public function collect_notice( $source ) {
		$this->notice_sources[] = $source;
	}

	/**
	 * Tests that unserializing a serialized token throws nothing and raises
	 * exactly one notice.
	 *
	 * @ticket 66246
	 */
	public function test_unserialize_of_serialized_token_raises_one_notice() {
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_Dangerous_HTML::__wakeup' );
		$this->notice_sources = array();
		add_action( 'doing_it_wrong_run', array( $this, 'collect_notice' ) );

		$token = unserialize( serialize( wp_interactivity_as_dangerous_html( '<b>x</b>' ) ) );

		$this->assertSame(
			array( 'WP_Interactivity_Dangerous_HTML::__wakeup' ),
			$this->notice_sources,
			'Expected exactly one notice.'
		);
		$this->assertInstanceOf( WP_Interactivity_Dangerous_HTML::class, $token );
	}

	/**
	 * Tests that a token restored by unserialization reports no HTML, whether it
	 * was serialized alone or nested in a structure, so it never renders.
	 *
	 * @ticket 66246
	 */
	public function test_unserialized_token_reports_no_html() {
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_Dangerous_HTML::__wakeup' );

		$alone  = unserialize( serialize( wp_interactivity_as_dangerous_html( '<b>x</b>' ) ) );
		$nested = unserialize( serialize( array( 'a' => array( wp_interactivity_as_dangerous_html( '<b>x</b>' ) ) ) ) );

		$this->assertNull( WP_Interactivity_Dangerous_HTML::get_html( $alone ), 'Expected a token restored on its own to report no HTML.' );
		$this->assertNull( WP_Interactivity_Dangerous_HTML::get_html( $nested['a'][0] ), 'Expected a nested token to report no HTML.' );
	}

	/**
	 * Tests that a token still reports its HTML after another one is restored
	 * by unserialization.
	 *
	 * @ticket 66246
	 */
	public function test_unserialization_does_not_affect_other_tokens() {
		$this->setExpectedIncorrectUsage( 'WP_Interactivity_Dangerous_HTML::__wakeup' );

		$token = wp_interactivity_as_dangerous_html( '<b>x</b>' );
		unserialize( serialize( $token ) );

		$this->assertSame( '<b>x</b>', WP_Interactivity_Dangerous_HTML::get_html( $token ) );
	}
}
