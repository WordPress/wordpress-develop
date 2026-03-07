<?php
/**
 * Tests for wp_supports_ai().
 *
 * @group ai-client
 * @covers ::wp_supports_ai
 */

class Tests_WP_Supports_AI extends WP_UnitTestCase {
	/**
	 * {@inheritDoc}
	 */
	public function tear_down() {
		// Remove the WP_DISABLE_AI constant if it was defined during tests.
		remove_all_filters( 'wp_supports_ai' );

		parent::tear_down();
	}

	/**
	 * Test that wp_supports_ai() defaults to true.
	 *
	 * @ticket 64591
	 */
	public function test_defaults_to_true() {
		$this->assertTrue( wp_supports_ai() );
	}

	/**
	 * Tests that the wp_supports_ai filter can disable/enable AI features.
	 */
	public function test_filter_can_disable_ai_features() {
		add_filter( 'wp_supports_ai', '__return_false' );
		$this->assertFalse( wp_supports_ai() );

		// Try a later filter to re-enable AI and confirm that it works.
		add_filter( 'wp_supports_ai', '__return_true' );
		$this->assertTrue( wp_supports_ai() );
	}
}
