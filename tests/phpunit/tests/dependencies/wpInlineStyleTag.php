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
	 * Data provider for {@see self::test_inline_style_tag_normalizes_whitespace()}.
	 *
	 * @return array<non-falsy-string, array{ 0: string|null, 1: non-empty-string }>
	 */
	public function data_inline_style_tag_whitespace(): array {
		return array(
			'no whitespace'            => array( 'a{}', "\na{}\n" ),
			'newlines'                 => array( "\n\na{}\n\n", "\na{}\n" ),
			'all CSS whitespace'       => array( " \t\f\r\na{}\n\r\f\t ", "\na{}\n" ),
			'inner whitespace kept'    => array( "\ta{}\n\n\tb{}\t", "\na{}\n\n\tb{}\n" ),
			'only whitespace'          => array( " \t\f\r\n", "\n\n" ),
			'empty string'             => array( '', "\n\n" ),
			'null from a filter'       => array( null, "\n\n" ),
			'non-whitespace preserved' => array( "\u{00A0}a{}\u{00A0}", "\n\u{00A0}a{}\u{00A0}\n" ),
		);
	}

	/**
	 * Tests that leading and trailing CSS whitespace is replaced by a single newline.
	 *
	 * @ticket 51325
	 *
	 * @dataProvider data_inline_style_tag_whitespace
	 *
	 * @param string|null      $data     CSS passed to the function.
	 * @param non-empty-string $expected Expected contents of the STYLE element.
	 */
	public function test_inline_style_tag_normalizes_whitespace( ?string $data, string $expected ): void {
		$processor = new WP_HTML_Tag_Processor( wp_get_inline_style_tag( $data ) ); // @phpstan-ignore argument.type

		$this->assertTrue( $processor->next_tag( 'STYLE' ), 'Expected a STYLE tag.' );
		$this->assertSame( $expected, $processor->get_modifiable_text() );
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
