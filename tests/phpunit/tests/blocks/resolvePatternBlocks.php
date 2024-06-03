<?php
/**
 * Tests for resolve_pattern_blocks.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.6.0
 *
 * @group blocks
 * @covers resolve_pattern_blocks
 */
class Tests_Blocks_ResolvePatternBlocks extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		register_block_pattern(
			'core/test',
			array(
				'title'       => 'Test',
				'content'     => '<!-- wp:paragraph -->Hello<!-- /wp:paragraph --><!-- wp:paragraph -->World<!-- /wp:paragraph -->',
				'description' => 'Test pattern.',
			)
		);
		register_block_pattern(
			'core/recursive',
			array(
				'title'       => 'Recursive',
				'content'     => '<!-- wp:paragraph -->Recursive<!-- /wp:paragraph --><!-- wp:pattern {"slug":"core/recursive"} /-->',
				'description' => 'Recursive pattern.',
			)
		);
	}

	public function tear_down() {
		unregister_block_pattern( 'core/test' );
		unregister_block_pattern( 'core/recursive' );

		parent::tear_down();
	}

	/**
	 * @dataProvider data_should_resolve_pattern_blocks_as_expected
	 *
	 * @ticket 61228
	 *
	 * @param string $blocks   A string representing blocks that need resolving.
	 * @param string $expected Expected result.
	 */
	public function test_should_resolve_pattern_blocks_as_expected( $blocks, $expected ) {
		$actual = resolve_pattern_blocks( parse_blocks( $blocks ) );
		$this->assertSame( $expected, serialize_blocks( $actual ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_resolve_pattern_blocks_as_expected() {
		return array(
			// Works without attributes, leaves the block as is.
			'pattern with no slug attribute' => array( '<!-- wp:pattern /-->', '<!-- wp:pattern /-->' ),
			// Resolves the pattern.
			'test pattern'                   => array( '<!-- wp:pattern {"slug":"core/test"} /-->', '<!-- wp:paragraph -->Hello<!-- /wp:paragraph --><!-- wp:paragraph -->World<!-- /wp:paragraph -->' ),
			// Skips recursive patterns.
			'recursive pattern'              => array( '<!-- wp:pattern {"slug":"core/recursive"} /-->', '<!-- wp:paragraph -->Recursive<!-- /wp:paragraph -->' ),
			// Resolves the pattern within a block.
			'pattern within a block'         => array( '<!-- wp:group --><!-- wp:paragraph -->Before<!-- /wp:paragraph --><!-- wp:pattern {"slug":"core/test"} /--><!-- wp:paragraph -->After<!-- /wp:paragraph --><!-- /wp:group -->', '<!-- wp:group --><!-- wp:paragraph -->Before<!-- /wp:paragraph --><!-- wp:paragraph -->Hello<!-- /wp:paragraph --><!-- wp:paragraph -->World<!-- /wp:paragraph --><!-- wp:paragraph -->After<!-- /wp:paragraph --><!-- /wp:group -->' ),
		);
	}
}
