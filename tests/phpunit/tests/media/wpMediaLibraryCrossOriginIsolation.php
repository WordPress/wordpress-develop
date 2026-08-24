<?php

/**
 * Tests for cross-origin isolation in the Media Library grid.
 *
 * @group media
 * @covers ::wp_get_media_library_mode
 * @covers ::wp_set_up_media_library_cross_origin_isolation
 */
class Tests_Media_wpMediaLibraryCrossOriginIsolation extends WP_UnitTestCase {

	/**
	 * Original HTTP_USER_AGENT value.
	 */
	private ?string $original_user_agent;

	/**
	 * Original HTTP_HOST value.
	 */
	private ?string $original_http_host;

	/**
	 * Original $_GET['mode'] value.
	 */
	private ?string $original_get_mode;

	public function set_up() {
		parent::set_up();
		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$this->original_http_host  = $_SERVER['HTTP_HOST'] ?? null;
		$this->original_get_mode   = $_GET['mode'] ?? null;
	}

	public function tear_down() {
		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		if ( null === $this->original_http_host ) {
			unset( $_SERVER['HTTP_HOST'] );
		} else {
			$_SERVER['HTTP_HOST'] = $this->original_http_host;
		}

		if ( null === $this->original_get_mode ) {
			unset( $_GET['mode'] );
		} else {
			$_GET['mode'] = $this->original_get_mode;
		}

		// Clean up any output buffers started during tests.
		while ( ob_get_level() > 1 ) {
			ob_end_clean();
		}

		remove_all_filters( 'wp_client_side_media_processing_enabled' );
		parent::tear_down();
	}

	/**
	 * Sets up the environment for the isolation happy path: a secure
	 * origin, a Chromium 137+ User-Agent, and a user who can upload.
	 */
	private function set_up_grid_isolation_environment() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';
		$_SERVER['HTTP_HOST']       = 'localhost';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * The isolation callback must be wired to the screen's load hook in
	 * default-filters.php: the buffer has to start before upload.php
	 * produces any output, and none of the gating below runs at all if
	 * the hook is missing.
	 *
	 * @ticket 65661
	 */
	public function test_hooked_to_load_upload() {
		$this->assertSame( 10, has_action( 'load-upload.php', 'wp_set_up_media_library_cross_origin_isolation' ) );
	}

	/**
	 * @ticket 65661
	 */
	public function test_mode_defaults_to_grid() {
		unset( $_GET['mode'] );

		$this->assertSame( 'grid', wp_get_media_library_mode() );
	}

	/**
	 * @ticket 65661
	 */
	public function test_mode_from_query_string() {
		$_GET['mode'] = 'list';

		$this->assertSame( 'list', wp_get_media_library_mode() );
	}

	/**
	 * @ticket 65661
	 */
	public function test_invalid_query_string_mode_falls_back_to_grid() {
		$_GET['mode'] = 'bogus';

		$this->assertSame( 'grid', wp_get_media_library_mode() );
	}

	/**
	 * A non-canonical query string mode is rejected, matching upload.php.
	 *
	 * upload.php compares the raw value strictly, so `?mode=GRID` falls
	 * back to the user option rather than being normalized to `grid`.
	 *
	 * @ticket 65661
	 */
	public function test_non_canonical_query_string_mode_falls_back_to_user_option() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'media_library_mode', 'list' );

		$_GET['mode'] = 'GRID';

		$this->assertSame( 'list', wp_get_media_library_mode() );
	}

	/**
	 * @ticket 65661
	 */
	public function test_mode_from_user_option() {
		unset( $_GET['mode'] );

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'media_library_mode', 'list' );

		$this->assertSame( 'list', wp_get_media_library_mode() );
	}

	/**
	 * upload.php only renders grid mode for the exact saved value 'grid', so
	 * any other saved value must be returned verbatim rather than collapsed
	 * to 'grid'. Otherwise a page that upload.php renders in list mode - and
	 * that has no client-side pipeline - would be cross-origin isolated.
	 *
	 * @ticket 65661
	 */
	public function test_unknown_user_option_mode_is_not_treated_as_grid() {
		unset( $_GET['mode'] );

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'media_library_mode', 'cards' );

		$this->assertNotSame( 'grid', wp_get_media_library_mode() );
	}

	/**
	 * @ticket 65661
	 */
	public function test_no_buffer_for_unknown_user_option_mode() {
		$this->set_up_grid_isolation_environment();
		unset( $_GET['mode'] );

		update_user_option( get_current_user_id(), 'media_library_mode', 'cards' );

		$level_before = ob_get_level();
		wp_set_up_media_library_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}

	/**
	 * @ticket 65661
	 */
	public function test_no_buffer_when_client_side_processing_disabled() {
		$this->set_up_grid_isolation_environment();
		$_GET['mode'] = 'grid';

		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		$level_before = ob_get_level();
		wp_set_up_media_library_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}

	/**
	 * @ticket 65661
	 */
	public function test_no_buffer_in_list_mode() {
		$this->set_up_grid_isolation_environment();
		$_GET['mode'] = 'list';

		$level_before = ob_get_level();
		wp_set_up_media_library_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}

	/**
	 * @ticket 65661
	 */
	public function test_no_buffer_when_logged_out() {
		$this->set_up_grid_isolation_environment();
		$_GET['mode'] = 'grid';

		wp_set_current_user( 0 );

		$level_before = ob_get_level();
		wp_set_up_media_library_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}

	/**
	 * @ticket 65661
	 */
	public function test_no_buffer_when_user_cannot_upload() {
		$this->set_up_grid_isolation_environment();
		$_GET['mode'] = 'grid';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$level_before = ob_get_level();
		wp_set_up_media_library_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}

	/**
	 * This test must run in a separate process because the output buffer
	 * callback sends HTTP headers via header(), which would fail in the
	 * main PHPUnit process where output has already started.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @ticket 65661
	 */
	public function test_starts_output_buffer_in_grid_mode_for_chromium() {
		$this->set_up_grid_isolation_environment();
		$_GET['mode'] = 'grid';

		$level_before = ob_get_level();
		wp_set_up_media_library_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before + 1, $level_after, 'Output buffer should be started on the grid for Chromium 137+.' );

		ob_end_clean();
	}

	/**
	 * @ticket 65661
	 */
	public function test_no_buffer_for_firefox() {
		$this->set_up_grid_isolation_environment();
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; rv:128.0) Gecko/20100101 Firefox/128.0';
		$_GET['mode']               = 'grid';

		$level_before = ob_get_level();
		wp_set_up_media_library_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after, 'Output buffer should not be started for non-Chromium browsers.' );
	}
}
