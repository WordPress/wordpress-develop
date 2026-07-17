<?php

/**
 * Tests for enqueueing the Media Library client-side upload integration.
 *
 * @group media
 * @covers ::wp_enqueue_media_library_upload
 * @covers ::wp_get_media_library_upload_settings
 */
class Tests_Media_wpEnqueueMediaLibraryUpload extends WP_UnitTestCase {

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
		set_current_screen( 'upload' );
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

	public function test_script_enqueued() {
		wp_enqueue_media_library_upload();

		$this->assertTrue( wp_script_is( 'media-library-upload', 'enqueued' ) );
	}

	public function test_script_not_enqueued_when_client_side_processing_disabled() {
		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		wp_enqueue_media_library_upload();

		$this->assertFalse( wp_script_is( 'media-library-upload', 'enqueued' ) );
	}

	/**
	 * The script depends on media-views and wp-upload-media, not
	 * wp-block-editor, so block editor bundles are not dragged onto
	 * the Media Library page.
	 */
	public function test_dependencies() {
		wp_enqueue_media_library_upload();

		$script = wp_scripts()->registered['media-library-upload'];
		$this->assertContains( 'media-views', $script->deps );
		$this->assertContains( 'wp-upload-media', $script->deps );
		$this->assertNotContains( 'wp-block-editor', $script->deps );
	}

	public function test_inline_settings_expose_all_keys() {
		wp_enqueue_media_library_upload();

		$before = wp_scripts()->get_data( 'media-library-upload', 'before' );
		$inline = implode( "\n", (array) $before );

		$this->assertStringContainsString( 'window._wpMediaLibraryUploadSettings', $inline );

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

	public function test_big_image_size_threshold_filter() {
		add_filter(
			'big_image_size_threshold',
			static function () {
				return 4096;
			}
		);

		$settings = wp_get_media_library_upload_settings();

		$this->assertSame( 4096, $settings['bigImageSizeThreshold'] );
	}

	public function test_settings_value_types() {
		$settings = wp_get_media_library_upload_settings();

		$this->assertIsInt( $settings['maxUploadFileSize'] );
		$this->assertIsArray( $settings['allowedMimeTypes'] );
		$this->assertIsArray( $settings['allImageSizes'] );
		$this->assertIsInt( $settings['bigImageSizeThreshold'] );
		$this->assertIsBool( $settings['imageStripMeta'] );
		$this->assertIsInt( $settings['imageMaxBitDepth'] );
	}
}
