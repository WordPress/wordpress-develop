<?php

/**
 * Tests for wp_get_audio_extensions() and wp_get_video_extensions().
 *
 * @group media
 */
class Tests_Media_WpGetAudioVideoExtensions extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @covers ::wp_get_audio_extensions
	 */
	public function test_returns_default_audio_extensions() {
		$this->assertSame(
			array( 'mp3', 'ogg', 'flac', 'm4a', 'wav' ),
			wp_get_audio_extensions()
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::wp_get_audio_extensions
	 */
	public function test_filter_can_modify_audio_extensions() {
		add_filter(
			'wp_audio_extensions',
			static function () {
				return array( 'mp3', 'aac' );
			}
		);

		$this->assertSame( array( 'mp3', 'aac' ), wp_get_audio_extensions() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::wp_get_video_extensions
	 */
	public function test_returns_default_video_extensions() {
		$this->assertSame(
			array( 'mp4', 'm4v', 'webm', 'ogv', 'flv' ),
			wp_get_video_extensions()
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::wp_get_video_extensions
	 */
	public function test_filter_can_modify_video_extensions() {
		add_filter(
			'wp_video_extensions',
			static function () {
				return array( 'mp4', 'mkv' );
			}
		);

		$this->assertSame( array( 'mp4', 'mkv' ), wp_get_video_extensions() );
	}
}
