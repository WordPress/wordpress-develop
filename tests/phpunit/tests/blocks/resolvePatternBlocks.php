<?php
/**
 * Tests for the resolve_pattern_blocks function.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.5.0
 *
 * @group blocks
 * @covers resolve_pattern_blocks
 */
class Tests_Blocks_ResolvePatternBlocks extends WP_UnitTestCase {

	/**
	 * ACME.
	 *
	 * @ticket 49927
	 *
	 * @covers ::resolve_pattern_blocks
	 */
	public function test_resolve_pattern_blocks() {
		$pattern_block = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(
				'content' => 'Hello, world!',
			),
		);

		$expected = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(
				'content' => 'Hello, world!',
			),
		);

		$actual = resolve_pattern_blocks( $pattern_block );

		$this->assertSame( $expected, $actual );
	}
}
