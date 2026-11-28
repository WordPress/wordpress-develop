<?php
/**
 * Test get_header_video_settings().
 *
 * @group themes
 *
 * @covers ::get_header_video_settings
 */
class Tests_Theme_GetHeaderVideoSettings extends WP_UnitTestCase {

	/**
	 * Tests that get_header_video_settings() resolves the expected mimeType for video URLs.
	 *
	 * @ticket 38172
	 *
	 * @dataProvider data_video_url_mime_types
	 *
	 * @param string $video_url     Video URL.
	 * @param string $expected_mime Expected MIME type.
	 */
	public function test_get_header_video_settings_resolves_mime_type( $video_url, $expected_mime ) {
		add_filter(
			'get_header_video_url',
			static function () use ( $video_url ) {
				return $video_url;
			}
		);

		$settings = get_header_video_settings();

		$this->assertSame( $expected_mime, $settings['mimeType'], 'MIME type does not match expected player type.' );
		if ( empty( $video_url ) ) {
			$this->assertFalse( $settings['videoUrl'], 'Video URL should be false when no video URL is configured.' );
		} else {
			$this->assertSame( set_url_scheme( $video_url ), $settings['videoUrl'], 'Video URL should match input URL with current scheme.' );
		}
	}

	/**
	 * Data provider for video URL to MIME type resolution.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_video_url_mime_types() {
		return array(
			'youtube watch URL'             => array( 'https://www.youtube.com/watch?v=1234567890', 'video/x-youtube' ),
			'youtube watch URL without www' => array( 'https://youtube.com/watch?v=1234567890', 'video/x-youtube' ),
			'youtube short URL'             => array( 'https://youtu.be/1234567890', 'video/x-youtube' ),
			'direct mp4 video file'         => array( 'https://example.com/header-video.mp4', 'video/mp4' ),
			'direct webm video file'        => array( 'https://example.com/header-video.webm', 'video/webm' ),
			'direct mov video file'         => array( 'https://example.com/header-video.mov', 'video/quicktime' ),
			'unrecognized file extension'   => array( 'https://example.com/header.xyz', '' ),
			'empty video URL'               => array( '', '' ),
		);
	}
}
