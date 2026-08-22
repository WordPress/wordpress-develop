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

	/**
	 * Original $_GET['p'] value.
	 */
	private ?string $original_get_p;

	/**
	 * Original $pagenow value.
	 */
	private ?string $original_pagenow;

	public function set_up() {
		parent::set_up();
		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$this->original_http_host  = $_SERVER['HTTP_HOST'] ?? null;
		$this->original_https      = $_SERVER['HTTPS'] ?? null;
		$this->original_get_action = $_GET['action'] ?? null;
		$this->original_get_p      = $_GET['p'] ?? null;
		$this->original_pagenow    = $GLOBALS['pagenow'] ?? null;
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

		if ( null === $this->original_get_p ) {
			unset( $_GET['p'] );
		} else {
			$_GET['p'] = $this->original_get_p;
		}

		// Clean up any output buffers started during tests.
		while ( ob_get_level() > 1 ) {
			ob_end_clean();
		}

		if ( null === $this->original_pagenow ) {
			unset( $GLOBALS['pagenow'] );
		} else {
			$GLOBALS['pagenow'] = $this->original_pagenow;
		}

		$GLOBALS['current_screen'] = null;

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
	 * The site editor home route on a classic theme skips DIP, because the
	 * editor renders the front end in a same-origin iframe and must reach its
	 * `contentDocument` to neutralize interactive elements. DIP would block
	 * that access.
	 *
	 * @ticket 65399
	 *
	 * @dataProvider data_classic_theme_site_editor_home_routes
	 *
	 * @param array $get The $_GET state representing the home route.
	 */
	public function test_skips_cross_origin_isolation_for_classic_theme_site_editor_home( array $get ) {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';
		$_SERVER['HTTP_HOST']       = 'localhost';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		switch_theme( 'twentytwentyone' );
		set_current_screen( 'site-editor' );
		$GLOBALS['pagenow'] = 'site-editor.php';

		unset( $_GET['p'] );
		foreach ( $get as $key => $value ) {
			$_GET[ $key ] = $value;
		}

		$level_before = ob_get_level();
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before, $level_after, 'DIP should be skipped on the classic-theme site editor home route.' );
	}

	/**
	 * Data provider for the classic-theme site editor home route.
	 *
	 * @return array[]
	 */
	public function data_classic_theme_site_editor_home_routes() {
		return array(
			'no p query var'   => array( array() ),
			'p query var is /' => array( array( 'p' => '/' ) ),
		);
	}

	/**
	 * The site editor on a classic theme still sets up cross-origin isolation
	 * for routes other than the home route.
	 *
	 * @ticket 65399
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_sets_up_cross_origin_isolation_for_classic_theme_site_editor_non_home_route() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';
		$_SERVER['HTTP_HOST']       = 'localhost';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		switch_theme( 'twentytwentyone' );
		set_current_screen( 'site-editor' );
		$GLOBALS['pagenow'] = 'site-editor.php';

		$_GET['p'] = '/page/about';

		$level_before = ob_get_level();
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before + 1, $level_after, 'DIP should be set up on a non-home site editor route.' );

		ob_end_clean();
	}

	/**
	 * The site editor on a block theme always sets up cross-origin isolation,
	 * including on the home route, because block themes do not render the
	 * classic site preview iframe.
	 *
	 * @ticket 65399
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_sets_up_cross_origin_isolation_for_block_theme_site_editor_home() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';
		$_SERVER['HTTP_HOST']       = 'localhost';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		switch_theme( 'twentytwentyfour' );
		set_current_screen( 'site-editor' );
		$GLOBALS['pagenow'] = 'site-editor.php';

		unset( $_GET['p'] );

		$level_before = ob_get_level();
		wp_set_up_cross_origin_isolation();
		$level_after = ob_get_level();

		$this->assertSame( $level_before + 1, $level_after, 'DIP should be set up on the block-theme site editor home route.' );

		ob_end_clean();
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
	 * Verifies that setting the client-side media processing flag does not
	 * clobber the script module dependencies of the upload-media script.
	 *
	 * Re-registering `@wordpress/vips/worker` via WP_Scripts::add_data(),
	 * which overwrites rather than merges, dropped the module dependencies
	 * declared in the packages asset file. This removed
	 * `@wordpress/video-conversion/worker` from the import map and broke
	 * animated GIF to video conversion.
	 *
	 * @ticket 65664
	 *
	 * @covers ::wp_set_client_side_media_processing_flag
	 */
	public function test_set_flag_preserves_upload_media_module_dependencies() {
		add_filter( 'wp_client_side_media_processing_enabled', '__return_true' );

		$before = wp_scripts()->get_data( 'wp-upload-media', 'module_dependencies' );

		wp_set_client_side_media_processing_flag();

		$after = wp_scripts()->get_data( 'wp-upload-media', 'module_dependencies' );

		$this->assertSame(
			$before,
			$after,
			'The module dependencies of the upload-media script should not be modified.'
		);

		$ids = array();
		foreach ( (array) $after as $module ) {
			$ids[] = is_array( $module ) ? $module['id'] : $module;
		}

		$this->assertContains(
			'@wordpress/vips/worker',
			$ids,
			'The vips worker should be a module dependency of the upload-media script.'
		);
		$this->assertContains(
			'@wordpress/video-conversion/worker',
			$ids,
			'The video-conversion worker should be a module dependency of the upload-media script.'
		);
	}

	/**
	 * Verifies that cross-origin elements get crossorigin="anonymous" added.
	 *
	 * @ticket 64766
	 * @ticket 65930
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider data_elements_that_should_get_crossorigin
	 *
	 * @param string $html HTML input to process.
	 */
	public function test_output_buffer_adds_crossorigin( $html ) {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

		ob_start();

		wp_start_cross_origin_isolation_output_buffer();
		echo $html;

		ob_end_flush();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'crossorigin="anonymous"', $output );
	}

	/**
	 * Data provider for elements that should receive crossorigin="anonymous".
	 *
	 * @return array[]
	 */
	public function data_elements_that_should_get_crossorigin() {
		return array(
			'cross-origin script'              => array(
				'<script src="https://external.example.com/script.js"></script>',
			),
			'cross-origin audio'               => array(
				'<audio src="https://external.example.com/audio.mp3"></audio>',
			),
			'cross-origin video'               => array(
				'<video src="https://external.example.com/video.mp4"></video>',
			),
			'cross-origin link stylesheet'     => array(
				'<link rel="stylesheet" href="https://external.example.com/style.css" />',
			),
			'cross-origin source inside video' => array(
				'<video><source src="https://external.example.com/video.mp4" type="video/mp4" /></video>',
			),
			'multiple cross-origin sources'    => array(
				'<video><source src="https://external.example.com/video.mp4" type="video/mp4" /><source src="https://external.example.com/video.webm" type="video/webm" /></video>',
			),
		);
	}

	/**
	 * Verifies that certain elements do not get crossorigin="anonymous" added.
	 *
	 * Images are excluded because under Document-Isolation-Policy:
	 * isolate-and-credentialless, the browser handles cross-origin images
	 * in credentialless mode without needing explicit CORS headers.
	 *
	 * @ticket 64766
	 * @ticket 65930
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider data_elements_that_should_not_get_crossorigin
	 *
	 * @param string $html HTML input to process.
	 */
	public function test_output_buffer_does_not_add_crossorigin( $html ) {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

		ob_start();

		wp_start_cross_origin_isolation_output_buffer();
		echo $html;

		ob_end_flush();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'crossorigin="anonymous"', $output );
	}

	/**
	 * A failed seek() must not leave the crossorigin attribute on the SOURCE element.
	 *
	 * WP_HTML_Tag_Processor::seek() returns false without moving the cursor once
	 * MAX_SEEK_OPS is exceeded. The cursor is then still on the SOURCE, so marking
	 * the element without checking the return value adds the attribute where it does
	 * nothing for CORS and leaves the parent media element unmarked.
	 *
	 * @ticket 65930
	 *
	 * @covers ::wp_add_crossorigin_attributes
	 */
	public function test_source_is_not_marked_when_seeking_the_parent_fails() {
		$this->setExpectedIncorrectUsage( 'WP_HTML_Tag_Processor::seek' );

		/*
		 * Marking a media element costs two seeks, one back to the parent and one
		 * forward to resume, so one more than half the budget outlasts it.
		 */
		$media_elements = intdiv( WP_HTML_Tag_Processor::MAX_SEEK_OPS, 2 ) + 1;

		$output = wp_add_crossorigin_attributes(
			str_repeat(
				'<video><source src="https://external.example.com/video.mp4" /></video>',
				$media_elements
			)
		);

		$this->assertSame(
			0,
			preg_match_all( '/<source\b[^>]*\bcrossorigin\b/i', $output ),
			'A SOURCE element must never receive the crossorigin attribute.'
		);

		$this->assertSame(
			$media_elements - 1,
			substr_count( $output, 'crossorigin="anonymous"' ),
			'Every media element reachable within the seek budget should have been marked.'
		);
	}

	/**
	 * Data provider for elements that should not receive crossorigin="anonymous".
	 *
	 * @return array[]
	 */
	public function data_elements_that_should_not_get_crossorigin() {
		return array(
			'cross-origin img'                        => array(
				'<img src="https://external.example.com/image.jpg" />',
			),
			'cross-origin img with srcset'            => array(
				'<img src="https://external.example.com/image.jpg" srcset="https://external.example.com/image-2x.jpg 2x" />',
			),
			'link with cross-origin imagesrcset only' => array(
				'<link rel="preload" as="image" imagesrcset="https://external.example.com/image.jpg 1x" href="/local-fallback.jpg" />',
			),
			'relative URL script'                     => array(
				'<script src="/wp-includes/js/wp-embed.min.js"></script>',
			),
			'source outside a media element'          => array(
				'<picture><source src="https://external.example.com/image.avif" /><img src="/local-image.jpg" /></picture>',
			),
			'source in a video with crossorigin'      => array(
				'<audio src="/local-audio.mp3"></audio><video crossorigin="use-credentials"><source src="https://external.example.com/video.mp4" /></video>',
			),
		);
	}

	/**
	 * Same-origin URLs should not get crossorigin="anonymous".
	 *
	 * Uses site_url() at runtime since the test domain varies by CI config.
	 *
	 * @ticket 64766
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_output_buffer_does_not_add_crossorigin_to_same_origin() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

		ob_start();

		wp_start_cross_origin_isolation_output_buffer();
		echo '<script src="' . site_url( '/wp-includes/js/wp-embed.min.js' ) . '"></script>';

		ob_end_flush();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'crossorigin="anonymous"', $output );
	}

	/**
	 * Elements that already have a crossorigin attribute should not be modified.
	 *
	 * @ticket 64766
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_output_buffer_does_not_override_existing_crossorigin() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

		ob_start();

		wp_start_cross_origin_isolation_output_buffer();
		echo '<script src="https://external.example.com/script.js" crossorigin="use-credentials"></script>';

		ob_end_flush();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'crossorigin="use-credentials"', $output, 'Existing crossorigin attribute should not be overridden.' );
		$this->assertStringNotContainsString( 'crossorigin="anonymous"', $output );
	}

	/**
	 * Multiple tags in the same output should each be handled correctly.
	 *
	 * @ticket 64766
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_output_buffer_handles_mixed_tags() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

		ob_start();

		wp_start_cross_origin_isolation_output_buffer();
		echo '<img src="https://external.example.com/image.jpg" />';
		echo '<script src="https://external.example.com/script.js"></script>';
		echo '<audio src="https://external.example.com/audio.mp3"></audio>';

		ob_end_flush();
		$output = ob_get_clean();

		// IMG should NOT have crossorigin.
		$this->assertStringContainsString( '<img src="https://external.example.com/image.jpg" />', $output, 'IMG should not be modified.' );

		// Script and audio should have crossorigin.
		$this->assertSame( 2, substr_count( $output, 'crossorigin="anonymous"' ), 'Script and audio should both get crossorigin, but not img.' );
	}

	/**
	 * IMG tags in the media manager templates must not receive
	 * crossorigin="anonymous", matching wp_add_crossorigin_attributes().
	 *
	 * Adding the attribute forces a CORS request that breaks previews of
	 * images served without Access-Control-Allow-Origin headers, such as
	 * media offloaded to a CDN.
	 *
	 * @ticket 65673
	 *
	 * @covers ::wp_print_media_templates
	 */
	public function test_print_media_templates_does_not_add_crossorigin_to_img() {
		require_once ABSPATH . WPINC . '/media-template.php';

		add_filter( 'wp_client_side_media_processing_enabled', '__return_true' );

		ob_start();
		wp_print_media_templates();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<img\b/i', $output, 'Expected the media templates to contain IMG tags.' );
		$this->assertDoesNotMatchRegularExpression( '/<img\b[^>]*\bcrossorigin\b/i', $output, 'IMG tags in the media templates must not receive a crossorigin attribute.' );
		$this->assertMatchesRegularExpression( '/<(?:audio|video)\b[^>]*crossorigin="anonymous"/i', $output, 'AUDIO and VIDEO tags in the media templates should still receive crossorigin="anonymous".' );
	}
}
