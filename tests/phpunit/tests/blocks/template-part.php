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
	public function test_returns_empty_string_when_template_is_missing() {
		$output = render_block_core_template_part( array() );
		$this->assertNotNull( $output );
		$this->assertEquals( '', $output );
	}
}
