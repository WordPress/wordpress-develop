<?php

/**
 * Tests for cross-origin isolation functions.
 *
 * @group media
 * @covers ::wp_set_up_cross_origin_isolation
 * @covers ::wp_start_cross_origin_isolation_output_buffer
 * @covers ::wp_is_client_side_media_processing_enabled
 */
class Tests_Media_wpCrossOriginIsolation extends WP_UnitTestCase {

	/**
	 * Original HTTP_USER_AGENT value.
	 */
	private ?string $original_user_agent;

	/**
	 * Original HTTP_HOST value.
	 */
	private ?string $original_http_host;

	/**
	 * Original HTTPS value.
	 */
	private ?string $original_https;

	/**
	 * Original $_GET['action'] value.
	 */
	private ?string $original_get_action;

	public function set_up() {
		parent::set_up();
		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$this->original_http_host  = $_SERVER['HTTP_HOST'] ?? null;
		$this->original_https      = $_SERVER['HTTPS'] ?? null;
		$this->original_get_action = $_GET['action'] ?? null;
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

		if ( null === $this->original_https ) {
			unset( $_SERVER['HTTPS'] );
		} else {
			$_SERVER['HTTPS'] = $this->original_https;
		}

		if ( null === $this->original_get_action ) {
			unset( $_GET['action'] );
		} else {
			$_GET['action'] = $this->original_get_action;
		}

		// Clean up any output buffers started during tests.
		while ( ob_get_level() > 1 ) {
			ob_end_clean();
		}

		remove_all_filters( 'wp_client_side_media_processing_enabled' );
		parent::tear_down();
	}

	/**
	 * @ticket 64766
	 */
	public function test_returns_early_when_client_side_processing_disabled() {
		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		// Should not error or start an output buffer.
		$level_before = ob_get_level();
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}

	/**
	 * @ticket 64766
	 */
	public function test_returns_early_when_no_screen() {
		// No screen is set, so it should return early.
		$level_before = ob_get_level();
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after );
	}

	/**
	 * This test must run in a separate process because the output buffer
	 * callback sends HTTP headers via header(), which would fail in the
	 * main PHPUnit process where output has already started.
	 *
	 * @ticket 64766
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_starts_output_buffer_for_chrome_137() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

		$level_before = ob_get_level();
		wp_start_cross_origin_isolation_output_buffer();
		$level_after = ob_get_level();

		$this->assertSame( $level_before + 1, $level_after, 'Output buffer should be started for Chrome 137.' );

		ob_end_clean();
	}

	/**
	 * @ticket 64766
	 */
	public function test_does_not_start_output_buffer_for_chrome_136() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36';

		$level_before = ob_get_level();
		wp_start_cross_origin_isolation_output_buffer();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after, 'Output buffer should not be started for Chrome < 137.' );
	}

	/**
	 * @ticket 64766
	 */
	public function test_does_not_start_output_buffer_for_firefox() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; rv:128.0) Gecko/20100101 Firefox/128.0';

		$level_before = ob_get_level();
		wp_start_cross_origin_isolation_output_buffer();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after, 'Output buffer should not be started for Firefox.' );
	}

	/**
	 * @ticket 64766
	 */
	public function test_does_not_start_output_buffer_for_safari() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';

		$level_before = ob_get_level();
		wp_start_cross_origin_isolation_output_buffer();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after, 'Output buffer should not be started for Safari.' );
	}

	/**
	 * @ticket 64803
	 */
	public function test_client_side_processing_disabled_on_non_secure_origin() {
		$_SERVER['HTTP_HOST'] = 'example.com';
		$_SERVER['HTTPS']     = '';

		$this->assertFalse(
			wp_is_client_side_media_processing_enabled(),
			'Client-side media processing should be disabled on non-secure, non-localhost origins.'
		);
	}

	/**
	 * @ticket 64803
	 */
	public function test_client_side_processing_enabled_on_localhost() {
		$_SERVER['HTTP_HOST'] = 'localhost';
		$_SERVER['HTTPS']     = '';

		$this->assertTrue(
			wp_is_client_side_media_processing_enabled(),
			'Client-side media processing should be enabled on localhost.'
		);
	}

	/**
	 * This test must run in a separate process because the output buffer
	 * callback sends HTTP headers via header(), which would fail in the
	 * main PHPUnit process where output has already started.
	 *
	 * @ticket 64766
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_output_buffer_adds_crossorigin_attributes() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

		// Start an outer buffer to capture the callback-processed output.
		ob_start();

		wp_start_cross_origin_isolation_output_buffer();
		echo '<img src="https://external.example.com/image.jpg" />';

		// Flush the inner buffer to trigger the callback, sending processed output to the outer buffer.
		ob_end_flush();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'crossorigin="anonymous"', $output );
	}
}
