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
	 * Original HTTP_USER_AGENT value.
	 */
	private ?string $original_user_agent;

	/**
	 * Original $wp_scripts global.
	 *
	 * @var WP_Scripts|null
	 */
	private $original_wp_scripts;

	public function set_up() {
		parent::set_up();
		$this->original_http_host  = $_SERVER['HTTP_HOST'] ?? null;
		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

		// A secure origin so client-side media processing is enabled.
		$_SERVER['HTTP_HOST'] = 'localhost';

		/*
		 * Cross-origin isolation relies on Document-Isolation-Policy, so the
		 * enqueue is gated on Chromium 137+. The PHPUnit bootstrap defines no
		 * User-Agent at all, which reads as "not Chromium".
		 */
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

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

		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		$GLOBALS['wp_scripts']     = $this->original_wp_scripts;
		$GLOBALS['current_screen'] = null;

		remove_all_filters( 'wp_client_side_media_processing_enabled' );
		parent::tear_down();
	}

	/**
	 * @ticket 65661
	 */
	public function test_script_enqueued() {
		wp_enqueue_media_library_upload();

		$this->assertTrue( wp_script_is( 'media-library-upload', 'enqueued' ) );
	}

	/**
	 * @ticket 65661
	 */
	public function test_script_not_enqueued_when_client_side_processing_disabled() {
		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		wp_enqueue_media_library_upload();

		$this->assertFalse( wp_script_is( 'media-library-upload', 'enqueued' ) );
	}

	/**
	 * Document-Isolation-Policy is Chromium-only, so a browser that can never
	 * be cross-origin isolated must not download the pipeline bundles for a
	 * script that could only no-op.
	 *
	 * @ticket 65661
	 */
	public function test_script_not_enqueued_for_non_chromium_user_agent() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:127.0) Gecko/20100101 Firefox/127.0';

		wp_enqueue_media_library_upload();

		$this->assertFalse( wp_script_is( 'media-library-upload', 'enqueued' ) );
	}

	/**
	 * @ticket 65661
	 */
	public function test_script_not_enqueued_for_older_chromium() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36';

		wp_enqueue_media_library_upload();

		$this->assertFalse( wp_script_is( 'media-library-upload', 'enqueued' ) );
	}

	/**
	 * The script depends on media-views and the shared pipeline glue, not
	 * wp-block-editor, so block editor bundles are not dragged onto
	 * the Media Library page.
	 *
	 * @ticket 65661
	 */
	public function test_dependencies() {
		wp_enqueue_media_library_upload();

		$script = wp_scripts()->registered['media-library-upload'];
		$this->assertContains( 'media-views', $script->deps );
		$this->assertContains( 'media-upload-pipeline', $script->deps );
		$this->assertNotContains( 'wp-block-editor', $script->deps );

		// The pipeline packages are pulled in through the shared glue script.
		$pipeline = wp_scripts()->registered['media-upload-pipeline'];
		$this->assertContains( 'wp-upload-media', $pipeline->deps );
		$this->assertContains( 'wp-media-utils', $pipeline->deps );
		$this->assertNotContains( 'wp-block-editor', $pipeline->deps );
		$this->assertNotContains( 'media-views', $pipeline->deps );
	}

	/**
	 * @ticket 65661
	 */
	public function test_inline_settings_expose_all_keys() {
		wp_enqueue_media_library_upload();

		$before = wp_scripts()->get_data( 'media-upload-pipeline', 'before' );
		$inline = implode( "\n", (array) $before );

		$this->assertStringContainsString( 'window._wpMediaUploadPipelineSettings', $inline );

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

	/**
	 * The inline settings must be exactly the JSON encoding of
	 * wp_get_media_library_upload_settings(), so the script consumes the
	 * same values the server computes.
	 *
	 * @ticket 65661
	 */
	public function test_inline_settings_match_upload_settings() {
		wp_enqueue_media_library_upload();

		$before = wp_scripts()->get_data( 'media-upload-pipeline', 'before' );
		$inline = implode( "\n", (array) $before );

		$this->assertStringContainsString(
			wp_json_encode( wp_get_media_library_upload_settings() ),
			$inline
		);
	}

	/**
	 * @ticket 65661
	 */
	public function test_allowed_mime_types_respect_upload_mimes_filter() {
		add_filter(
			'upload_mimes',
			static function ( $mimes ) {
				unset( $mimes['gif'] );
				return $mimes;
			}
		);

		$settings = wp_get_media_library_upload_settings();

		$this->assertArrayNotHasKey( 'gif', $settings['allowedMimeTypes'] );
	}

	/**
	 * @ticket 65661
	 */
	public function test_image_strip_meta_filter() {
		add_filter( 'image_strip_meta', '__return_false' );

		$settings = wp_get_media_library_upload_settings();

		$this->assertFalse( $settings['imageStripMeta'] );
	}

	/**
	 * @ticket 65661
	 */
	public function test_image_max_bit_depth_filter() {
		add_filter(
			'image_max_bit_depth',
			static function () {
				return 8;
			}
		);

		$settings = wp_get_media_library_upload_settings();

		$this->assertSame( 8, $settings['imageMaxBitDepth'] );
	}

	/**
	 * @ticket 65661
	 */
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

	/**
	 * @ticket 65661
	 */
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
