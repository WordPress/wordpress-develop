<?php

/**
 * Tests for enqueueing the media frame client-side upload integration.
 *
 * @group media
 * @covers ::wp_enqueue_media_frame_upload
 * @covers ::wp_get_media_library_upload_settings
 * @covers ::wp_is_cross_origin_isolated_request
 */
class Tests_Media_wpEnqueueMediaFrameUpload extends WP_UnitTestCase {

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

	/**
	 * @global WP_Scripts|null $wp_scripts
	 */
	public function set_up() {
		global $wp_scripts;

		parent::set_up();
		$this->original_http_host  = isset( $_SERVER['HTTP_HOST'] ) && is_string( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : null;
		$this->original_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;

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
		$this->original_wp_scripts = $wp_scripts;
		$wp_scripts                = new WP_Scripts();

		// The screen was cross-origin isolated, as wp_set_up_cross_origin_isolation() records.
		$GLOBALS['_wp_cross_origin_isolated'] = true;
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
		unset( $GLOBALS['_wp_cross_origin_isolated'] );

		remove_all_filters( 'wp_client_side_media_processing_enabled' );
		parent::tear_down();
	}

	/**
	 * @ticket 65661
	 */
	public function test_script_enqueued() {
		wp_enqueue_media_frame_upload();

		$this->assertTrue( wp_script_is( 'media-frame-upload', 'enqueued' ) );
	}

	/**
	 * A screen wp_set_up_cross_origin_isolation() did not isolate cannot run
	 * the pipeline, so the media frame there keeps its classic uploads and
	 * does not download the pipeline bundles.
	 *
	 * @ticket 65661
	 */
	public function test_script_not_enqueued_when_request_not_isolated() {
		unset( $GLOBALS['_wp_cross_origin_isolated'] );

		$this->assertFalse( wp_is_cross_origin_isolated_request() );

		wp_enqueue_media_frame_upload();

		$this->assertFalse( wp_script_is( 'media-frame-upload', 'enqueued' ) );
	}

	/**
	 * The block editor, the site editor and the block widgets screen load the
	 * media modal through wp_enqueue_media(), so that is where the frame
	 * integration is enqueued on an isolated screen.
	 *
	 * @ticket 65661
	 */
	public function test_wp_enqueue_media_enqueues_script_on_isolated_screen() {
		set_current_screen( 'post' );

		wp_enqueue_media();

		$this->assertTrue( wp_script_is( 'media-frame-upload', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'media-editor', 'enqueued' ) );
	}

	/**
	 * @ticket 65661
	 */
	public function test_wp_enqueue_media_skips_script_when_request_not_isolated() {
		unset( $GLOBALS['_wp_cross_origin_isolated'] );
		set_current_screen( 'options-general' );

		wp_enqueue_media();

		$this->assertFalse( wp_script_is( 'media-frame-upload', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'media-editor', 'enqueued' ) );
	}

	/**
	 * @ticket 65661
	 */
	public function test_script_not_enqueued_when_client_side_processing_disabled() {
		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		wp_enqueue_media_frame_upload();

		$this->assertFalse( wp_script_is( 'media-frame-upload', 'enqueued' ) );
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

		wp_enqueue_media_frame_upload();

		$this->assertFalse( wp_script_is( 'media-frame-upload', 'enqueued' ) );
	}

	/**
	 * @ticket 65661
	 */
	public function test_script_not_enqueued_for_older_chromium() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36';

		wp_enqueue_media_frame_upload();

		$this->assertFalse( wp_script_is( 'media-frame-upload', 'enqueued' ) );
	}

	/**
	 * The script depends on media-views and the shared pipeline glue, not
	 * wp-block-editor, so block editor bundles are not dragged onto
	 * the Media Library page.
	 *
	 * @ticket 65661
	 */
	public function test_dependencies() {
		wp_enqueue_media_frame_upload();

		$script = wp_scripts()->registered['media-frame-upload'];
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
		wp_enqueue_media_frame_upload();

		$inline = wp_scripts()->get_inline_script_data( 'media-upload-pipeline', 'before' );

		$this->assertStringContainsString( 'window._wpMediaUploadPipelineSettings', $inline );

		foreach ( array(
			'maxUploadFileSize',
			'allowedMimeTypes',
			'allImageSizes',
			'bigImageSizeThreshold',
			'imageStripMeta',
			'imageMaxBitDepth',
			'generateAnimatedImageSubsizes',
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
		wp_enqueue_media_frame_upload();

		$inline = wp_scripts()->get_inline_script_data( 'media-upload-pipeline', 'before' );

		$settings = array_merge( wp_get_media_library_upload_settings(), array( 'isBlockEditor' => false ) );
		$json     = wp_json_encode( $settings, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );
		$this->assertIsString( $json );
		$this->assertStringContainsString( $json, $inline );
	}

	/**
	 * A plugin calling wp_enqueue_media() on media-new.php enqueues both
	 * screen scripts, which share the pipeline script and its settings.
	 *
	 * @ticket 65661
	 */
	public function test_inline_settings_added_once_when_both_scripts_enqueued() {
		wp_enqueue_media_frame_upload();
		wp_enqueue_media_new_upload();

		$inline = wp_scripts()->get_inline_script_data( 'media-upload-pipeline', 'before' );

		$this->assertTrue( wp_script_is( 'media-frame-upload', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'media-new-upload', 'enqueued' ) );
		$this->assertSame( 1, substr_count( $inline, '_wpMediaUploadPipelineSettings' ) );
	}

	/**
	 * On block editor screens the editor configures the upload-media store,
	 * so the pipeline must not render a second provider.
	 *
	 * @ticket 65661
	 */
	public function test_inline_settings_flag_block_editor_screen() {
		set_current_screen( 'post' );
		get_current_screen()->is_block_editor( true );

		wp_enqueue_media_frame_upload();

		$inline = wp_scripts()->get_inline_script_data( 'media-upload-pipeline', 'before' );

		$this->assertStringContainsString( '"isBlockEditor":true', $inline );
	}

	/**
	 * wp_max_upload_size() passes the `upload_size_limit` filter result through
	 * unchanged; only the pipeline settings clamp it.
	 *
	 * @ticket 65661
	 */
	public function test_max_upload_file_size_clamps_negative_filter_result() {
		add_filter(
			'upload_size_limit',
			static function () {
				return -1;
			}
		);

		$settings = wp_get_media_library_upload_settings();

		$this->assertSame( -1, wp_max_upload_size() );
		$this->assertSame( 0, $settings['maxUploadFileSize'] );
	}

	/**
	 * @ticket 65661
	 */
	public function test_generate_animated_image_subsizes_filter() {
		add_filter( 'wp_generate_animated_image_subsizes', '__return_false' );

		$settings = wp_get_media_library_upload_settings();

		$this->assertFalse( $settings['generateAnimatedImageSubsizes'] );
	}

	/**
	 * The Media Library pipeline and the REST index the block editor reads
	 * must apply the same image processing filters.
	 *
	 * @ticket 65661
	 */
	public function test_image_settings_match_rest_index() {
		add_filter(
			'big_image_size_threshold',
			static function () {
				return 4096;
			}
		);
		add_filter( 'wp_generate_animated_image_subsizes', '__return_false' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$index    = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/' ) )->get_data();
		$settings = wp_get_media_library_upload_settings();

		$this->assertSame( $index['image_sizes'], $settings['allImageSizes'] );
		$this->assertSame( $index['image_size_threshold'], $settings['bigImageSizeThreshold'] );
		$this->assertSame( $index['image_strip_meta'], $settings['imageStripMeta'] );
		$this->assertSame( $index['image_max_bit_depth'], $settings['imageMaxBitDepth'] );
		$this->assertSame( $index['generate_animated_image_subsizes'], $settings['generateAnimatedImageSubsizes'] );
	}

	/**
	 * @ticket 65661
	 */
	public function test_allowed_mime_types_respect_upload_mimes_filter() {
		add_filter(
			'upload_mimes',
			static function ( array $mimes ): array {
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
