<?php

/**
 * Tests for how wp_kses() handles foreign content (SVG and MathML).
 *
 * @group kses
 *
 * @covers ::wp_kses
 */
class Tests_Kses_wpKsesForeignContent extends WP_UnitTestCase {

	/**
	 * Tests that content after SVG or MathML markup is kept.
	 *
	 * @dataProvider data_content_after_foreign_content
	 *
	 * @param string $html   Input HTML.
	 * @param string $before Content before the foreign content, expected at the start of the output.
	 * @param string $after  Content after the foreign content, expected at the end of the output.
	 */
	public function test_content_after_foreign_content_is_kept( $html, $before, $after ) {
		$sanitized = wp_kses_post( $html );

		$this->assertStringStartsWith( $before, $sanitized, 'Content before the foreign content should be kept.' );
		$this->assertStringEndsWith( $after, $sanitized, 'Content after the foreign content should be kept.' );
	}

	/**
	 * Tests that the legacy parser keeps content after SVG or MathML markup.
	 *
	 * This documents the behavior before the HTML API parser became the default.
	 *
	 * @dataProvider data_content_after_foreign_content
	 *
	 * @param string $html   Input HTML.
	 * @param string $before Content before the foreign content, expected at the start of the output.
	 * @param string $after  Content after the foreign content, expected at the end of the output.
	 */
	public function test_content_after_foreign_content_is_kept_by_legacy_parser( $html, $before, $after ) {
		add_filter( 'wp_kses_force_legacy_parser', '__return_true' );

		$sanitized = wp_kses_post( $html );

		$this->assertStringStartsWith( $before, $sanitized, 'Content before the foreign content should be kept.' );
		$this->assertStringEndsWith( $after, $sanitized, 'Content after the foreign content should be kept.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function data_content_after_foreign_content() {
		return array(
			'MathML with an unclosed element'          => array(
				'<p>a</p><math><mi>x</math><p>after</p>',
				'<p>a</p>',
				'<p>after</p>',
			),
			'Valid MathML with HTML in a text element' => array(
				'<p>1</p><math><mo>+</mo><mtext><b>x</b></mtext></math><p>2</p>',
				'<p>1</p>',
				'<p>2</p>',
			),
			'SVG with an unclosed element'             => array(
				'<p>a</p><svg><path d="M0"></svg><p>after</p>',
				'<p>a</p>',
				'<p>after</p>',
			),
			'SVG with an HTML element that breaks out' => array(
				'<p>a</p><svg><p>x</p></svg><p>after</p>',
				'<p>a</p>',
				'<p>after</p>',
			),
		);
	}
}
