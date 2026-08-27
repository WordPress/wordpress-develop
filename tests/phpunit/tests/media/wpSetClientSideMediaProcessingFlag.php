<?php

/**
 * Tests for wp_set_client_side_media_processing_flag().
 *
 * @group media
 * @covers ::wp_set_client_side_media_processing_flag
 */
class Tests_Media_wpSetClientSideMediaProcessingFlag extends WP_UnitTestCase {

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

	public function set_up() {
		parent::set_up();

		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$this->original_http_host  = $_SERVER['HTTP_HOST'] ?? null;
		$this->original_https      = $_SERVER['HTTPS'] ?? null;

		// Ensure client-side media processing is considered enabled.
		$_SERVER['HTTP_HOST'] = 'localhost';

		$GLOBALS['wp_scripts'] = new WP_Scripts();
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

		unset( $GLOBALS['wp_scripts'] );

		remove_all_filters( 'wp_client_side_media_processing_enabled' );

		parent::tear_down();
	}

	/**
	 * Returns the inline scripts added before the 'wp-block-editor' script.
	 *
	 * @return string Concatenated inline scripts, or an empty string if none.
	 */
	private function get_block_editor_before_scripts(): string {
		$before = wp_scripts()->get_data( 'wp-block-editor', 'before' );

		if ( ! is_array( $before ) ) {
			return '';
		}

		return implode( "\n", array_filter( $before ) );
	}

	/**
	 * @ticket 65648
	 */
	public function test_sets_heic_upload_support_flag_for_non_chromium_browsers() {
		// Safari 26 on macOS.
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15';

		wp_set_client_side_media_processing_flag();

		$before = $this->get_block_editor_before_scripts();

		$this->assertStringContainsString( 'window.__clientSideMediaProcessing = true;', $before, 'The client-side media processing flag should be set.' );
		$this->assertStringContainsString( 'window.__heicUploadSupport = true;', $before, 'The HEIC upload support flag should be set for non-Chromium browsers.' );
		$this->assertStringNotContainsString( 'window.__documentIsolationPolicy', $before, 'The Document-Isolation-Policy flag should not be set for non-Chromium browsers.' );
	}

	/**
	 * @ticket 65648
	 */
	public function test_sets_heic_upload_support_flag_for_chromium_browsers() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

		wp_set_client_side_media_processing_flag();

		$before = $this->get_block_editor_before_scripts();

		$this->assertStringContainsString( 'window.__clientSideMediaProcessing = true;', $before, 'The client-side media processing flag should be set.' );
		$this->assertStringContainsString( 'window.__heicUploadSupport = true;', $before, 'The HEIC upload support flag should be set for Chromium browsers.' );
		$this->assertStringContainsString( 'window.__documentIsolationPolicy = true;', $before, 'The Document-Isolation-Policy flag should be set for Chromium 137+.' );
	}

	/**
	 * @ticket 65648
	 */
	public function test_does_not_set_flags_when_client_side_media_processing_is_disabled() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15';

		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		wp_set_client_side_media_processing_flag();

		$before = $this->get_block_editor_before_scripts();

		$this->assertStringNotContainsString( 'window.__clientSideMediaProcessing', $before, 'The client-side media processing flag should not be set when the feature is disabled.' );
		$this->assertStringNotContainsString( 'window.__heicUploadSupport', $before, 'The HEIC upload support flag should not be set when the feature is disabled.' );
	}
}
