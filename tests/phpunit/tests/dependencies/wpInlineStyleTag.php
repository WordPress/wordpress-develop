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

	private $css = 'body { color: #123456; }';

	/**
	 * @ticket 51325
	 */
	public function test_get_inline_style_tag_with_attributes() {
		$this->assertSame(
			"<style id=\"test-inline-css\" nonce=\"test-nonce\">\n{$this->css}\n</style>\n",
			wp_get_inline_style_tag(
				$this->css,
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
			wp_get_inline_style_tag( $this->css, $attributes ),
			get_echo(
				'wp_print_inline_style_tag',
				array( $this->css, $attributes )
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
			static function ( $attributes, $data ) use ( &$filtered_data ) {
				$attributes['nonce'] = 'test-nonce';
				$filtered_data       = $data;
				return $attributes;
			},
			10,
			2
		);

		$this->assertSame(
			"<style nonce=\"test-nonce\">\n{$this->css}\n</style>\n",
			wp_get_inline_style_tag( $this->css )
		);
		$this->assertSame( "\n{$this->css}\n", $filtered_data );
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
				$this->css,
				array(
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
				$this->css,
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
	 * @ticket 51325
	 */
	public function test_inline_style_tag_escapes_closing_style_tag() {
		$this->assertSame(
			"<style>\nbody::after { content: \"\\3c\\2fstyle>\"; }\n</style>\n",
			wp_get_inline_style_tag( 'body::after { content: "</style>"; }' )
		);
	}
}
