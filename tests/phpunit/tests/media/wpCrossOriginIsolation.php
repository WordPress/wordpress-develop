<?php

/**
 * Tests for cross-origin isolation functions.
 *
 * @group media
 * @covers ::wp_set_up_cross_origin_isolation
 * @covers ::wp_send_document_isolation_policy_header
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

		$this->assertFalse( wp_set_up_cross_origin_isolation() );
	}

	/**
	 * @ticket 64766
	 */
	public function test_returns_early_when_no_screen() {
		$this->assertFalse( wp_set_up_cross_origin_isolation() );
	}

	/**
	 * @ticket 64766
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_sends_header_for_chrome_137() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

		$this->assertTrue( wp_send_document_isolation_policy_header(), 'The Document-Isolation-Policy header should be sent for Chrome 137.' );
	}

	/**
	 * @ticket 64766
	 */
	public function test_does_not_send_header_for_chrome_136() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36';

		$this->assertFalse( wp_send_document_isolation_policy_header(), 'The Document-Isolation-Policy header should not be sent for Chrome < 137.' );
	}

	/**
	 * @ticket 64766
	 */
	public function test_does_not_send_header_for_firefox() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; rv:128.0) Gecko/20100101 Firefox/128.0';

		$this->assertFalse( wp_send_document_isolation_policy_header(), 'The Document-Isolation-Policy header should not be sent for Firefox.' );
	}

	/**
	 * @ticket 64766
	 */
	public function test_does_not_send_header_for_safari() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';

		$this->assertFalse( wp_send_document_isolation_policy_header(), 'The Document-Isolation-Policy header should not be sent for Safari.' );
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

		$this->assertFalse( wp_set_up_cross_origin_isolation(), 'DIP should be skipped on the classic-theme site editor home route.' );
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

		$this->assertTrue( wp_set_up_cross_origin_isolation(), 'DIP should be set up on a non-home site editor route.' );
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

		$this->assertTrue( wp_set_up_cross_origin_isolation(), 'DIP should be set up on the block-theme site editor home route.' );
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
	 * The deprecated output buffer helper only sends the header now.
	 *
	 * @ticket 65930
	 *
	 * @expectedDeprecated wp_start_cross_origin_isolation_output_buffer
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_deprecated_output_buffer_helper_does_not_buffer() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

		$level_before = ob_get_level();
		wp_start_cross_origin_isolation_output_buffer();

		$this->assertSame( $level_before, ob_get_level(), 'No output buffer should be started.' );
	}

	/**
	 * The deprecated attribute injector leaves the HTML untouched.
	 *
	 * Under Document-Isolation-Policy: isolate-and-credentialless, cross-origin
	 * scripts, styles, images, audio, and video load without a crossorigin
	 * attribute. Adding one forces a CORS request that fails for resources
	 * served without Access-Control-Allow-Origin headers.
	 *
	 * @ticket 65930
	 *
	 * @expectedDeprecated wp_add_crossorigin_attributes
	 *
	 * @dataProvider data_cross_origin_elements
	 *
	 * @param string $html HTML input to process.
	 */
	public function test_deprecated_attribute_injector_returns_html_unchanged( $html ) {
		$this->assertSame( $html, wp_add_crossorigin_attributes( $html ) );
	}

	/**
	 * Data provider of cross-origin elements that used to receive crossorigin="anonymous".
	 *
	 * @return array[]
	 */
	public function data_cross_origin_elements() {
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
		);
	}

	/**
	 * The media manager templates must not carry crossorigin="anonymous".
	 *
	 * Adding the attribute forces a CORS request that breaks previews and
	 * playback of media served without Access-Control-Allow-Origin headers,
	 * such as media offloaded to a CDN.
	 *
	 * @ticket 65673
	 * @ticket 65930
	 *
	 * @covers ::wp_print_media_templates
	 */
	public function test_print_media_templates_does_not_add_crossorigin() {
		require_once ABSPATH . WPINC . '/media-template.php';

		add_filter( 'wp_client_side_media_processing_enabled', '__return_true' );

		ob_start();
		wp_print_media_templates();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<(?:img|audio|video)\b/i', $output, 'Expected the media templates to contain media tags.' );
		$this->assertDoesNotMatchRegularExpression( '/<(?:img|audio|video)\b[^>]*\bcrossorigin\b/i', $output, 'Media tags in the media templates must not receive a crossorigin attribute.' );
	}
}
