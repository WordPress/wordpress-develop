<?php

/**
 * @group block-supports
 *
 * @covers ::wp_strip_custom_css_from_blocks
 */
class Tests_Block_Supports_WpStripCustomCssFromBlocks extends WP_UnitTestCase {

	/**
	 * Tests that style.css is stripped from block attributes.
	 *
	 * @ticket 64771
	 *
	 * @dataProvider data_strips_css_from_blocks
	 *
	 * @param string $content  Post content containing blocks.
	 * @param string $message  Assertion message.
	 */
	public function test_strips_css_from_blocks( $content, $message ) {
		$result = wp_unslash( wp_strip_custom_css_from_blocks( $content ) );
		$blocks = parse_blocks( $result );

		$this->assertArrayNotHasKey( 'css', $blocks[0]['attrs']['style'] ?? array(), $message );
		$this->assertArrayNotHasKey( 'style', $blocks[0]['attrs'] ?? array(), 'style key should be fully removed when css was the only property.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_strips_css_from_blocks() {
		return array(
			'single block' => array(
				'content' => '<!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>Hello</p><!-- /wp:paragraph -->',
				'message' => 'style.css should be stripped from block attributes.',
			),
		);
	}

	/**
	 * Tests that style.css is stripped from nested inner blocks.
	 *
	 * @ticket 64771
	 */
	public function test_strips_css_from_inner_blocks() {
		$content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>Hello</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$result = wp_unslash( wp_strip_custom_css_from_blocks( $content ) );
		$blocks = parse_blocks( $result );

		$inner_block = $blocks[0]['innerBlocks'][0];
		$this->assertArrayNotHasKey( 'css', $inner_block['attrs']['style'] ?? array(), 'style.css should be stripped from inner block attributes.' );
	}

	/**
	 * Tests that content without blocks is returned unchanged.
	 *
	 * @ticket 64771
	 */
	public function test_returns_non_block_content_unchanged() {
		$content = '<p>This is plain HTML content with no blocks.</p>';

		$result = wp_strip_custom_css_from_blocks( $content );

		$this->assertSame( $content, $result, 'Non-block content should be returned unchanged.' );
	}

	/**
	 * Tests that content without style.css attributes is returned unchanged.
	 *
	 * @ticket 64771
	 */
	public function test_returns_unchanged_when_no_css_attributes() {
		$content = '<!-- wp:paragraph {"style":{"color":{"text":"#ff0000"}}} --><p class="has-text-color" style="color:#ff0000">Hello</p><!-- /wp:paragraph -->';

		$result = wp_strip_custom_css_from_blocks( $content );

		$this->assertSame( $content, $result, 'Content without style.css attributes should be returned unchanged.' );
	}

	/**
	 * Tests that other style properties are preserved when css is stripped.
	 *
	 * @ticket 64771
	 */
	public function test_preserves_other_style_properties() {
		$content = '<!-- wp:paragraph {"style":{"css":"color: red;","color":{"text":"#ff0000"}}} --><p>Hello</p><!-- /wp:paragraph -->';

		$result = wp_unslash( wp_strip_custom_css_from_blocks( $content ) );
		$blocks = parse_blocks( $result );

		$this->assertArrayNotHasKey( 'css', $blocks[0]['attrs']['style'], 'style.css should be stripped.' );
		$this->assertSame( '#ff0000', $blocks[0]['attrs']['style']['color']['text'], 'Other style properties should be preserved.' );
	}

	/**
	 * Tests that empty style object is cleaned up after stripping css.
	 *
	 * @ticket 64771
	 */
	public function test_cleans_up_empty_style_object() {
		$content = '<!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>Hello</p><!-- /wp:paragraph -->';

		$result = wp_unslash( wp_strip_custom_css_from_blocks( $content ) );
		$blocks = parse_blocks( $result );

		$this->assertArrayNotHasKey( 'style', $blocks[0]['attrs'], 'Empty style object should be cleaned up after stripping css.' );
	}

	/**
	 * Tests that slashed content is handled correctly.
	 *
	 * @ticket 64771
	 */
	public function test_handles_slashed_content() {
		$content = '<!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>Hello</p><!-- /wp:paragraph -->';
		$slashed = wp_slash( $content );

		$result = wp_strip_custom_css_from_blocks( $slashed );
		$blocks = parse_blocks( wp_unslash( $result ) );

		$this->assertArrayNotHasKey( 'css', $blocks[0]['attrs']['style'] ?? array(), 'style.css should be stripped even from slashed content.' );
	}
}
