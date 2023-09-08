<?php
/**
 * Tests for template part rendering.
 *
 * @package WordPress
 *
 * @group block-templates
 */
class Tests_Block_Template_Part extends WP_UnitTestCase {
	/**
	 * @ticket 59318
	 */
	public function test_render_block_core_template_part_missing_template() {
		$output = render_block_core_template_part( [] );
		$this->assertNotNull( $output );
		$this->assertEquals( '', $output );
	}
}
