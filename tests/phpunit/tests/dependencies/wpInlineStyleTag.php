<?php

/**
 * Test wp_get_inline_style_tag() and wp_print_inline_style_tag().
 *
 * @group dependencies
 * @group styles
 * @covers ::wp_get_inline_style_tag
 * @covers ::wp_print_inline_style_tag
 */
class Tests_Dependencies_wpInlineStyleTag extends WP_UnitTestCase {

	/**
	 * @ticket 51325
	 */
	public function test_get_inline_style_tag_with_attributes() {
		$expected = <<<'HTML'
			<style id="test-inline-css" nonce="test-nonce">
			body { color: #123456; }
			</style>

			HTML;

		$this->assertEqualHTML(
			$expected,
			wp_get_inline_style_tag(
				$this->get_test_css(),
				array(
					'id'    => 'test-inline-css',
					'nonce' => 'test-nonce',
				)
			)
		);
	}

	/**
	 * @ticket 51325
	 */
	public function test_print_inline_style_tag_prints_get_inline_style_tag() {
		$attributes = array( 'id' => 'test-inline-css' );

		$this->assertSame(
			wp_get_inline_style_tag( $this->get_test_css(), $attributes ),
			get_echo(
				'wp_print_inline_style_tag',
				array( $this->get_test_css(), $attributes )
			)
		);
	}

	/**
	 * @ticket 51325
	 */
	public function test_inline_style_attributes_filter() {
		$filtered_data = null;

		add_filter(
			'wp_inline_style_attributes',
			static function ( array $attributes, string $data ) use ( &$filtered_data ) {
				$attributes['nonce'] = 'test-nonce';
				$filtered_data       = $data;
				return $attributes;
			},
			10,
			2
		);

		$expected = <<<'HTML'
			<style nonce="test-nonce">
			body { color: #123456; }
			</style>

			HTML;

		$this->assertEqualHTML( $expected, wp_get_inline_style_tag( $this->get_test_css() ) );
		$this->assertSame( "\n{$this->get_test_css()}\n", $filtered_data );
	}

	/**
	 * Test the behavior of generated style tag attributes passed different values and types of values.
	 *
	 * @ticket 51325
	 */
	public function test_inline_style_tag_attribute_value_types() {
		$expected = <<<'HTML'
			<style
				true
				null
				empty-string=""
				0-string="0"
				1-string="1"
				0-numeric="0"
				1-numeric="1"
			>
			body { color: #123456; }
			</style>

			HTML;

		$this->assertEqualHTML(
			$expected,
			wp_get_inline_style_tag(
				$this->get_test_css(),
				array( // @phpstan-ignore argument.type
					'true'         => true,
					'false'        => false,
					'null'         => null,
					'empty-string' => '',
					'0-string'     => '0',
					'1-string'     => '1',
					'0-numeric'    => 0,
					'1-numeric'    => 1,
				)
			)
		);
	}

	/**
	 * Test the behavior of generated style tag repeated attributes.
	 *
	 * HTML will ignore case-insensitive repeated attributes. Ensure that the handling of input
	 * attributes aligns with expectations.
	 *
	 * @ticket 51325
	 */
	public function test_inline_style_tag_repeat_attributes() {
		$expected = <<<'HTML'
			<style test="test-a">
			body { color: #123456; }
			</style>

			HTML;

		$this->assertEqualHTML(
			$expected,
			wp_get_inline_style_tag(
				$this->get_test_css(),
				array(
					'test' => 'test-a',
					'tesT' => 'tesT-b',
					'teST' => 'teST-c',
					'tEST' => 'tEST-d',
					'TEST' => 'TEST-e',
				)
			)
		);
	}

	/**
	 * Tests that a non-string value, such as from a misbehaving filter, does not cause a fatal error.
	 *
	 * @ticket 51325
	 */
	public function test_inline_style_tag_with_null_data() {
		$this->assertEqualHTML(
			"<style>\n\n</style>\n",
			wp_get_inline_style_tag( null ) // @phpstan-ignore argument.type
		);
	}

	/**
	 * @ticket 51325
	 */
	public function test_inline_style_tag_escapes_closing_style_tag() {
		$css       = 'body::after { content: "</style>"; } body::before { content: "<script>"; }';
		$processor = new WP_HTML_Tag_Processor( wp_get_inline_style_tag( $css ) );

		$this->assertTrue( $processor->next_tag(), 'Expected a STYLE tag.' );
		$this->assertSame( 'STYLE', $processor->get_tag() );
		$text = $processor->get_modifiable_text();
		$this->assertStringNotContainsStringIgnoringCase( '</style', $text, 'Expected the closing STYLE tag to be escaped.' );
		$this->assertStringContainsString( 'body::before { content: "<script>"; }', $text, 'Expected the rest of the CSS to be preserved.' );
		$this->assertFalse( $processor->next_tag(), 'Expected the CSS to not break out of the STYLE element.' );
	}

	/**
	 * Gets test CSS code.
	 *
	 * @return non-falsy-string
	 */
	private function get_test_css(): string {
		return 'body { color: #123456; }';
	}
}
