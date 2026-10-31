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
		$this->original_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
		$this->original_http_host  = isset( $_SERVER['HTTP_HOST'] ) && is_string( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : null;
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

		remove_all_filters( 'wp_client_side_media_processing_enabled' );
		unset( $GLOBALS['current_screen'] );
		parent::tear_down();
	}

	/**
	 * Sets up the environment for the isolation happy path: a secure
	 * origin, a Chromium 137+ User-Agent, and a user who can upload.
	 */
	private function set_up_isolation_environment(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';
		$_SERVER['HTTP_HOST']       = 'localhost';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * The isolation callback must be wired to the screen's load hook in
	 * default-filters.php: the header has to be sent before media-new.php
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
	public function test_no_isolation_when_client_side_processing_disabled() {
		$this->set_up_isolation_environment();

		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		set_current_screen( 'media' );

		$this->assertFalse( wp_set_up_cross_origin_isolation() );
	}

	/**
	 * @ticket 65662
	 */
	public function test_no_isolation_when_logged_out() {
		$this->set_up_isolation_environment();

		wp_set_current_user( 0 );

		set_current_screen( 'media' );

		$this->assertFalse( wp_set_up_cross_origin_isolation() );
	}

	/**
	 * @ticket 65662
	 */
	public function test_no_isolation_when_user_cannot_upload() {
		$this->set_up_isolation_environment();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		set_current_screen( 'media' );

		$this->assertFalse( wp_set_up_cross_origin_isolation() );
	}

	/**
	 * Runs in a separate process because header() cannot send headers in
	 * the main PHPUnit process, where output has already started.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @ticket 65662
	 */
	public function test_sends_header_for_chromium() {
		$this->set_up_isolation_environment();

		set_current_screen( 'media' );

		$this->assertTrue( wp_set_up_cross_origin_isolation(), 'The Document-Isolation-Policy header should be sent on media-new.php for Chromium 137+.' );
	}

	/**
	 * @ticket 65662
	 */
	public function test_no_isolation_for_firefox() {
		$this->set_up_isolation_environment();
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; rv:128.0) Gecko/20100101 Firefox/128.0';

		set_current_screen( 'media' );

		$this->assertFalse( wp_set_up_cross_origin_isolation(), 'The Document-Isolation-Policy header should not be sent for non-Chromium browsers.' );
	}
}
