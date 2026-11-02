<?php

/**
 * Tests for extract_serialized_parent_block() and remove_serialized_parent_block().
 *
 * @group blocks
 * @group block-hooks
 */
class Tests_Blocks_SerializedParentBlock extends WP_UnitTestCase {

	/**
	 * Tests that the parent block's delimiters are kept and its inner content is dropped.
	 *
	 * @ticket 65819
	 *
	 * @covers ::extract_serialized_parent_block
	 *
	 * @dataProvider data_serialized_parent_blocks
	 *
	 * @param string $serialized_block Serialized parent block.
	 * @param string $parent_block     Expected parent block without its inner content.
	 */
	public function test_extract_serialized_parent_block( $serialized_block, $parent_block ) {
		$this->assertSame( $parent_block, extract_serialized_parent_block( $serialized_block ) );
	}

	/**
	 * Tests that the parent block's delimiters are dropped and its inner content is kept.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_serialized_parent_block
	 *
	 * @dataProvider data_serialized_parent_blocks
	 *
	 * @param string $serialized_block Serialized parent block.
	 * @param string $parent_block     Unused; the parent block without its inner content.
	 * @param string $inner_content    Expected inner content.
	 */
	public function test_remove_serialized_parent_block( $serialized_block, $parent_block, $inner_content ) {
		$this->assertSame( $inner_content, remove_serialized_parent_block( $serialized_block ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public static function data_serialized_parent_blocks() {
		return array(
			'parent with inner blocks'           => array(
				'<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
				'<!-- wp:group --><!-- /wp:group -->',
				'<div class="wp-block-group"><!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph --></div>',
			),
			'parent with attributes'             => array(
				'<!-- wp:group {"tagName":"section","metadata":{"ignoredHookedBlocks":["core/loginout"]}} --><section class="wp-block-group">Text</section><!-- /wp:group -->',
				'<!-- wp:group {"tagName":"section","metadata":{"ignoredHookedBlocks":["core/loginout"]}} --><!-- /wp:group -->',
				'<section class="wp-block-group">Text</section>',
			),
			'parent without inner content'       => array(
				'<!-- wp:post-content --><!-- /wp:post-content -->',
				'<!-- wp:post-content --><!-- /wp:post-content -->',
				'',
			),
			'nested block of the same type'      => array(
				'<!-- wp:group --><!-- wp:group --><p>Inner</p><!-- /wp:group --><!-- /wp:group -->',
				'<!-- wp:group --><!-- /wp:group -->',
				'<!-- wp:group --><p>Inner</p><!-- /wp:group -->',
			),
			'inner content with an HTML comment' => array(
				'<!-- wp:navigation --><!-- A note --><!-- wp:navigation-link {"label":"Home"} /--><!-- /wp:navigation -->',
				'<!-- wp:navigation --><!-- /wp:navigation -->',
				'<!-- A note --><!-- wp:navigation-link {"label":"Home"} /-->',
			),
			'whitespace around inner content'    => array(
				"<!-- wp:group -->\n<p>Text</p>\n<!-- /wp:group -->",
				'<!-- wp:group --><!-- /wp:group -->',
				"\n<p>Text</p>\n",
			),
		);
	}
}
