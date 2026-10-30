<?php

/**
 * Tests for sanitize_title_for_query().
 *
 * @group formatting
 *
 * @covers ::sanitize_title_for_query
 */
class Tests_Formatting_SanitizeTitleForQuery extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_uses_query_context() {
		$captured_context = null;

		add_filter(
			'sanitize_title',
			static function ( $title, $raw_title, $context ) use ( &$captured_context ) {
				$captured_context = $context;
				return $title;
			},
			10,
			3
		);

		sanitize_title_for_query( 'Hello World' );

		$this->assertSame( 'query', $captured_context );
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_sanitize_title_for_query
	 *
	 * @param string $title    Title to sanitize.
	 * @param string $expected Expected sanitized title.
	 */
	public function test_sanitize_title_for_query( $title, $expected ) {
		$this->assertSame( $expected, sanitize_title_for_query( $title ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_sanitize_title_for_query() {
		return array(
			'empty string' => array( '', '' ),
			'simple title' => array( 'Hello World', 'hello-world' ),
			'strips tags'  => array( '<strong>Hello</strong> World', 'hello-world' ),
			'punctuation'  => array( 'Hello, World!', 'hello-world' ),
		);
	}
}
