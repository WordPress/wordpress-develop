<?php

/**
 * Tests for enqueueing the "Add New Media File" client-side upload integration.
 *
 * @group media
 * @covers ::wp_enqueue_media_new_upload
 */
class Tests_Media_wpEnqueueMediaNewUpload extends WP_UnitTestCase {

	/**
	 * Original HTTP_HOST value.
	 */
	private ?string $original_http_host;

	/**
	 * Original $wp_scripts global.
	 *
	 * @var WP_Scripts|null
	 */
	private $original_wp_scripts;

	public function set_up() {
		parent::set_up();
		$this->original_http_host = $_SERVER['HTTP_HOST'] ?? null;

		// A secure origin so client-side media processing is enabled.
		$_SERVER['HTTP_HOST'] = 'localhost';

		/*
		 * The script is registered in the admin-only branch of
		 * wp_default_scripts(), so default scripts must be (re)registered
		 * from an admin context.
		 */
		set_current_screen( 'media' );
		$this->original_wp_scripts = $GLOBALS['wp_scripts'] ?? null;
		$GLOBALS['wp_scripts']     = new WP_Scripts();
	}

	public function tear_down() {
		if ( null === $this->original_http_host ) {
			unset( $_SERVER['HTTP_HOST'] );
		} else {
			$_SERVER['HTTP_HOST'] = $this->original_http_host;
		}

		$GLOBALS['wp_scripts']     = $this->original_wp_scripts;
		$GLOBALS['current_screen'] = null;

		remove_all_filters( 'wp_client_side_media_processing_enabled' );
		parent::tear_down();
	}

	/**
	 * @ticket 65662
	 */
	public function test_script_enqueued() {
		wp_enqueue_media_new_upload();

		$this->assertTrue( wp_script_is( 'media-new-upload', 'enqueued' ) );
	}

	/**
	 * @ticket 65662
	 */
	public function test_script_not_enqueued_when_client_side_processing_disabled() {
		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		wp_enqueue_media_new_upload();

		$this->assertFalse( wp_script_is( 'media-new-upload', 'enqueued' ) );
	}

	/**
	 * The script depends on plupload-handlers (whose UI helpers it reuses)
	 * and wp-upload-media, not on media-views or wp-block-editor, so the
	 * heavy Media Library and block editor bundles are not dragged onto
	 * the "Add New Media File" screen.
	 *
	 * @ticket 65662
	 */
	public function test_dependencies() {
		wp_enqueue_media_new_upload();

		$script = wp_scripts()->registered['media-new-upload'];
		$this->assertContains( 'plupload-handlers', $script->deps );
		$this->assertContains( 'wp-upload-media', $script->deps );
		$this->assertNotContains( 'media-views', $script->deps );
		$this->assertNotContains( 'wp-block-editor', $script->deps );
	}

	/**
	 * @ticket 65662
	 */
	public function test_inline_settings_expose_all_keys() {
		wp_enqueue_media_new_upload();

		$before = wp_scripts()->get_data( 'media-new-upload', 'before' );
		$inline = implode( "\n", (array) $before );

		$this->assertStringContainsString( 'window._wpMediaNewUploadSettings', $inline );

		foreach ( array(
			'maxUploadFileSize',
			'allowedMimeTypes',
			'allImageSizes',
			'bigImageSizeThreshold',
			'imageStripMeta',
			'imageMaxBitDepth',
		) as $key ) {
			$this->assertStringContainsString( $key, $inline );
		}
	}
}
