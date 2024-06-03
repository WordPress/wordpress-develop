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
 */
class Tests_Blocks_Resolve_Pattern_Blocks extends WP_UnitTestCase {

	/**
	 * @dataProvider data_all
	 *
	 * @param string $input
     * @param string $expected
	 */
	public function test_all( $input, $expected ) {
		$actual = resolve_pattern_blocks( parse_blocks( $input ) );
		$this->assertSame( $expected, serialize_blocks( $actual ) );
	}

	public function data_all() {
		return array(
			// Works without attributes, leaves the block as is.
			array( '<!-- wp:pattern /-->', '<!-- wp:pattern /-->' ),
		);
	}
}
