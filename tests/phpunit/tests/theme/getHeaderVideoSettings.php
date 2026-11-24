<?php
/**
 * Test get_header_video_settings().
 *
 * @group theme
 *
 * @covers ::get_header_video_settings
 */
class Tests_Theme_GetHeaderVideoSettings extends WP_UnitTestCase {

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		remove_all_filters( 'header_video_settings' );
		remove_all_filters( 'get_header_video_url' );
		parent::tear_down();
	}

	/**
	 * Tests that get_header_video_settings() returns default structure when no video is configured.
	 *
	 * @ticket 38172
	 */
	public function test_get_header_video_settings_default_structure() {
		$settings = get_header_video_settings();

		$this->assertIsArray( $settings, 'Header video settings should return an array.' );
		$this->assertArrayHasKey( 'mimeType', $settings );
		$this->assertArrayHasKey( 'posterUrl', $settings );
		$this->assertArrayHasKey( 'videoUrl', $settings );
		$this->assertArrayHasKey( 'width', $settings );
		$this->assertArrayHasKey( 'height', $settings );
		$this->assertArrayHasKey( 'minWidth', $settings );
		$this->assertArrayHasKey( 'minHeight', $settings );
		$this->assertArrayHasKey( 'l10n', $settings );

		$this->assertSame( 900, $settings['minWidth'], 'Default minWidth should be 900.' );
		$this->assertSame( 500, $settings['minHeight'], 'Default minHeight should be 500.' );
		$this->assertSame( '', $settings['mimeType'], 'Default mimeType should be empty when no video is set.' );
		$this->assertIsArray( $settings['l10n'], 'l10n setting should be an array.' );
		$this->assertArrayHasKey( 'pause', $settings['l10n'] );
		$this->assertArrayHasKey( 'play', $settings['l10n'] );
		$this->assertArrayHasKey( 'pauseSpeak', $settings['l10n'] );
		$this->assertArrayHasKey( 'playSpeak', $settings['l10n'] );
	}

	/**
	 * Tests that get_header_video_settings() sets youtube mimeType for YouTube video URLs.
	 *
	 * @ticket 38172
	 *
	 * @dataProvider data_youtube_urls
	 *
	 * @param string $youtube_url YouTube URL.
	 */
	public function test_get_header_video_settings_with_youtube_url( $youtube_url ) {
		add_filter(
			'get_header_video_url',
			static function () use ( $youtube_url ) {
				return $youtube_url;
			}
		);

		$settings = get_header_video_settings();

		$this->assertSame( 'video/x-youtube', $settings['mimeType'], 'Mime type for YouTube URL should be video/x-youtube.' );
		$this->assertSame( set_url_scheme( $youtube_url ), $settings['videoUrl'], 'Video URL should match YouTube URL with current scheme.' );
	}

	/**
	 * Data provider for YouTube URLs.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_youtube_urls() {
		return array(
			'youtube watch URL'        => array( 'https://www.youtube.com/watch?v=1234567890' ),
			'youtube watch URL no www' => array( 'https://youtube.com/watch?v=1234567890' ),
			'youtu.be short URL'       => array( 'https://youtu.be/1234567890' ),
		);
	}

	/**
	 * Tests that get_header_video_settings() correctly resolves filetype for direct video files.
	 *
	 * @ticket 38172
	 */
	public function test_get_header_video_settings_with_direct_video_file() {
		$video_url = 'https://example.com/sample-header-video.mp4';
		add_filter(
			'get_header_video_url',
			static function () use ( $video_url ) {
				return $video_url;
			}
		);

		$settings = get_header_video_settings();

		$this->assertSame( 'video/mp4', $settings['mimeType'], 'Mime type for .mp4 URL should be video/mp4.' );
		$this->assertSame( set_url_scheme( $video_url ), $settings['videoUrl'], 'Video URL should match the direct video URL with current scheme.' );
	}

	/**
	 * Tests that header_video_settings filter allows modifying the settings array.
	 *
	 * @ticket 38172
	 */
	public function test_header_video_settings_filter() {
		add_filter(
			'header_video_settings',
			static function ( $settings ) {
				$settings['minWidth'] = 1200;
				$settings['custom']   = 'custom_value';
				return $settings;
			}
		);

		$settings = get_header_video_settings();

		$this->assertSame( 1200, $settings['minWidth'], 'minWidth should be modified by the filter.' );
		$this->assertSame( 'custom_value', $settings['custom'], 'Custom setting should be present from filter.' );
	}
}
