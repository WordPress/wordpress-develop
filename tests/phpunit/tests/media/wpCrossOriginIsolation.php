<?php

/**
 * Tests for cross-origin isolation functions.
 *
 * @group media
 * @covers ::wp_set_up_cross_origin_isolation
 * @covers ::wp_start_cross_origin_isolation_output_buffer
 */
class Tests_Media_wpCrossOriginIsolation extends WP_UnitTestCase {

	/**
	 * Original HTTP_USER_AGENT value.
	 *
	 * @var string|null
	 */
	private $original_user_agent;

	public function set_up() {
		parent::set_up();
		$this->original_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
	}

	public function tear_down() {
		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
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
