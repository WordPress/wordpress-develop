<?php

/**
 * Tests for the `wp_set_up_cross_origin_isolation()` function.
 *
 * @group media
 * @covers ::wp_set_up_cross_origin_isolation
 */
class Tests_Media_wpSetUpCrossOriginIsolation extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
	}

	public function tear_down() {
		remove_all_filters( 'wp_client_side_media_processing_enabled' );
		parent::tear_down();
	}

	public function test_returns_early_when_client_side_processing_disabled() {
		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		// Should not error or start an output buffer.
		$level_before = ob_get_level();
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}

	public function test_returns_early_when_no_screen() {
		// No screen is set, so it should return early.
		$level_before = ob_get_level();
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}
}
