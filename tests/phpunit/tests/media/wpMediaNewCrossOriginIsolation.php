<?php

/**
 * Tests for cross-origin isolation on the "Add New Media File" screen.
 *
 * @group media
 * @covers ::wp_set_up_cross_origin_isolation
 */
class Tests_Media_wpMediaNewCrossOriginIsolation extends WP_UnitTestCase {

	/**
	 * Original HTTP_USER_AGENT value.
	 */
	private ?string $original_user_agent;

	/**
	 * Original HTTP_HOST value.
	 */
	private ?string $original_http_host;

	public function set_up() {
		parent::set_up();
		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$this->original_http_host  = $_SERVER['HTTP_HOST'] ?? null;
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

		// Clean up any output buffers started during tests.
		while ( ob_get_level() > 1 ) {
			ob_end_clean();
		}

		remove_all_filters( 'wp_client_side_media_processing_enabled' );
		unset( $GLOBALS['current_screen'] );
		parent::tear_down();
	}

	/**
	 * Sets up the environment for the isolation happy path: a secure
	 * origin, a Chromium 137+ User-Agent, and a user who can upload.
	 */
	private function set_up_isolation_environment() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';
		$_SERVER['HTTP_HOST']       = 'localhost';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * The isolation callback must be wired to the screen's load hook in
	 * default-filters.php: the buffer has to start before media-new.php
	 * produces any output, and none of the gating below runs at all if
	 * the hook is missing.
	 *
	 * @ticket 65662
	 */
	public function test_hooked_to_load_media_new() {
		$this->assertSame( 10, has_action( 'load-media-new.php', 'wp_set_up_cross_origin_isolation' ) );
	}

	/**
	 * @ticket 65662
	 */
	public function test_no_buffer_when_client_side_processing_disabled() {
		$this->set_up_isolation_environment();

		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		$level_before = ob_get_level();
		set_current_screen( 'media' );
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}

	/**
	 * @ticket 65662
	 */
	public function test_no_buffer_when_logged_out() {
		$this->set_up_isolation_environment();

		wp_set_current_user( 0 );

		$level_before = ob_get_level();
		set_current_screen( 'media' );
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}

	/**
	 * @ticket 65662
	 */
	public function test_no_buffer_when_user_cannot_upload() {
		$this->set_up_isolation_environment();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$level_before = ob_get_level();
		set_current_screen( 'media' );
		wp_set_up_cross_origin_isolation();
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
	 * @ticket 65662
	 */
	public function test_starts_output_buffer_for_chromium() {
		$this->set_up_isolation_environment();

		$level_before = ob_get_level();
		set_current_screen( 'media' );
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before + 1, $level_after, 'Output buffer should be started on media-new.php for Chromium 137+.' );

		ob_end_clean();
	}

	/**
	 * @ticket 65662
	 */
	public function test_no_buffer_for_firefox() {
		$this->set_up_isolation_environment();
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; rv:128.0) Gecko/20100101 Firefox/128.0';

		$level_before = ob_get_level();
		set_current_screen( 'media' );
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after, 'Output buffer should not be started for non-Chromium browsers.' );
	}
}
