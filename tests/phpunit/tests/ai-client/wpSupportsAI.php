<?php
/**
 * Tests for wp_supports_ai().
 *
 * @group ai-client
 * @covers ::wp_supports_ai
 */

class Tests_WP_Supports_AI extends WP_UnitTestCase {
	/**
	 * Test that wp_supports_ai() defaults to true.
	 *
	 * @ticket 64591
	 */
	public function test_defaults_to_true(): void {
		$this->assertTrue( wp_supports_ai() );
	}

	/**
	 * Tests that the wp_supports_ai filter can disable/enable AI features.
	 */
	public function test_filter_can_disable_ai_features(): void {
		add_filter( 'wp_supports_ai', '__return_false' );
		$this->assertFalse( wp_supports_ai() );

		// Try a later filter to re-enable AI and confirm that it works.
		add_filter( 'wp_supports_ai', '__return_true' );
		$this->assertTrue( wp_supports_ai() );
	}
}
