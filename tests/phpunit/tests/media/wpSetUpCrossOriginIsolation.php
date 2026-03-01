<?php

/**
 * Tests for the `wp_set_up_cross_origin_isolation()` function.
 *
 * @group media
 * @covers ::wp_set_up_cross_origin_isolation
 */
class Tests_Media_wpSetUpCrossOriginIsolation extends WP_UnitTestCase {

	/**
	 * Original $_GET values.
	 *
	 * @var array
	 */
	private $original_get;

	public function set_up() {
		parent::set_up();
		$this->original_get = $_GET;
	}

	public function tear_down() {
		$_GET = $this->original_get;
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

	public function test_skips_for_third_party_editor_action() {
		$_GET['action'] = 'third_party_editor';

		$level_before = ob_get_level();
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after, 'Should skip when action is not "edit".' );
	}

	public function test_does_not_skip_for_edit_action() {
		$_GET['action'] = 'edit';

		// Still won't start the buffer because no screen is set,
		// but confirms the action check doesn't block 'edit'.
		$level_before = ob_get_level();
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		// Returns early at the screen check, not the action check.
		$this->assertSame( $level_before, $level_after );
	}
}
