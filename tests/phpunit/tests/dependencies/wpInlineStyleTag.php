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
	 * Data provider for test_core_inline_styles_have_filtered_attributes().
	 *
	 * @return array<non-falsy-string, array{ set_up: Closure(): void, print_callback: callable(): void, attribute_name: non-falsy-string, attribute_value: non-falsy-string }>
	 */
	public function data_core_inline_styles(): array {
		return array(
			'custom_background' => array(
				'set_up'          => static function (): void {
					add_theme_support( 'custom-background' );
					set_theme_mod( 'background_color', 'ff0000' );
				},
				'print_callback'  => '_custom_background_cb',
				'attribute_name'  => 'id',
				'attribute_value' => 'custom-background-css',
			),
			'custom_css'        => array(
				'set_up'          => static function (): void {
					add_filter(
						'wp_get_custom_css',
						static function (): string {
							return 'body { color: #123456; }';
						}
					);
				},
				'print_callback'  => 'wp_custom_css_cb',
				'attribute_name'  => 'id',
				'attribute_value' => 'wp-custom-css',
			),
			'custom_logo'       => array(
				'set_up'          => static function (): void {
					remove_theme_support( 'custom-header' );
					add_theme_support( 'custom-logo', array( 'header-text' => array( 'site-title' ) ) );
					set_theme_mod( 'header_text', false );
				},
				'print_callback'  => '_custom_logo_header_styles',
				'attribute_name'  => 'id',
				'attribute_value' => 'custom-logo-css',
			),
			'font_faces'        => array(
				'set_up'          => static function (): void {},
				'print_callback'  => static function (): void {
					wp_print_font_faces(
						array(
							'Inter' => array(
								array(
									'font-family' => 'Inter',
									'src'         => array( 'https://example.com/inter.woff2' ),
								),
							),
						)
					);
				},
				'attribute_name'  => 'class',
				'attribute_value' => 'wp-fonts-local',
			),
			'recent_comments'   => array(
				'set_up'          => static function (): void {
					add_theme_support( 'widgets' );
				},
				'print_callback'  => static function (): void {
					( new WP_Widget_Recent_Comments() )->recent_comments_style();
				},
				'attribute_name'  => 'nonce',
				'attribute_value' => 'test-nonce',
			),
		);
	}

	/**
	 * Tests that inline styles printed by core outside of WP_Styles have filtered attributes.
	 *
	 * @ticket 51325
	 *
	 * @covers ::_custom_background_cb
	 * @covers ::wp_custom_css_cb
	 * @covers ::_custom_logo_header_styles
	 * @covers WP_Font_Face::generate_and_print
	 * @covers WP_Widget_Recent_Comments::recent_comments_style
	 *
	 * @dataProvider data_core_inline_styles
	 *
	 * @param Closure(): void    $set_up          Set up callback.
	 * @param callable(): void   $print_callback  Callback which prints the inline style.
	 * @param non-falsy-string   $attribute_name  Name of an attribute the STYLE tag is expected to have.
	 * @param non-falsy-string   $attribute_value Expected value of the attribute.
	 */
	public function test_core_inline_styles_have_filtered_attributes( Closure $set_up, callable $print_callback, string $attribute_name, string $attribute_value ): void {
		$set_up();

		$filter = new MockAction();
		add_filter( 'wp_inline_style_attributes', array( $filter, 'filter' ) );
		add_filter(
			'wp_inline_style_attributes',
			static function ( array $attributes ): array {
				$attributes['nonce'] = 'test-nonce';
				return $attributes;
			}
		);

		$processor = new WP_HTML_Tag_Processor( get_echo( $print_callback ) );

		$this->assertTrue( $processor->next_tag( 'STYLE' ), 'Expected a STYLE tag to be printed.' );
		$this->assertSame( $attribute_value, $processor->get_attribute( $attribute_name ) );
		$this->assertSame( 'test-nonce', $processor->get_attribute( 'nonce' ) );
		$this->assertFalse( $processor->next_tag(), 'Expected only one tag to be printed.' );
		$this->assertSame( 1, $filter->get_call_count(), 'Expected the filter to be applied once.' );
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
