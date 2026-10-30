<?php
/**
 * Tests for sanitize_html_class().
 *
 * Documents current allowed characters (A-Z, a-z, 0-9, underscore, hyphen).
 * Broader CDATA support is tracked separately in #63156.
 *
 * @group formatting
 *
 * @covers ::sanitize_html_class
 */
class Tests_Formatting_SanitizeHtmlClass extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_sanitize_html_class
	 *
	 * @param string $classname Class name to sanitize.
	 * @param string $expected  Expected sanitized class.
	 * @param string $fallback  Optional fallback class.
	 */
	public function test_sanitize_html_class( $classname, $expected, $fallback = '' ) {
		$this->assertSame( $expected, sanitize_html_class( $classname, $fallback ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_sanitize_html_class() {
		return array(
			'empty string'                           => array(
				'',
				'',
			),
			'already valid class unchanged'          => array(
				'foo-bar_123',
				'foo-bar_123',
			),
			'preserves mixed case'                   => array(
				'FooBar',
				'FooBar',
			),
			'strips spaces'                          => array(
				'foo bar',
				'foobar',
			),
			'strips punctuation'                     => array(
				'foo.bar!baz',
				'foobarbaz',
			),
			'strips percent-encoded sequences'       => array(
				'foo%20bar%2Fbaz',
				'foobarbaz',
			),
			'strips incomplete percent encoding'     => array(
				'foo%2',
				'foo2',
			),
			'strips high-bit characters'             => array(
				"caf\xC3\xA9-latte",
				'caf-latte',
			),
			'empty classname uses fallback'          => array(
				'',
				'fallback-class',
				'fallback-class',
			),
			'fully stripped classname uses fallback' => array(
				'%20!!!',
				'ok-fallback',
				'ok-fallback',
			),
			'fallback is also sanitized'             => array(
				'',
				'okfallback',
				'ok fallback!',
			),
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_sanitize_html_class_filter_receives_original_args() {
		$filter = new MockAction();
		add_filter( 'sanitize_html_class', array( $filter, 'filter_append' ), 10, 3 );

		$result = sanitize_html_class( 'foo bar', 'fallback' );

		$this->assertSame( 'foobar_append', $result, 'Unexpected sanitized class.' );
		$this->assertSame( 1, $filter->get_call_count(), 'The filter was not applied once.' );
		$this->assertSame(
			array( 'foobar', 'foo bar', 'fallback' ),
			$filter->get_args()[0],
			'The filter did not receive the sanitized class, original classname, and fallback.'
		);
	}
}
