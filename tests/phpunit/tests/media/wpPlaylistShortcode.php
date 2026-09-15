<?php
/**
 * @group media
 * @covers ::wp_playlist_shortcode
 */
class Tests_Media_Wp_Playlist_Shortcode extends WP_UnitTestCase {

	/**
	 * @ticket 63583
	 */
	public function test_should_load_scripts_exactly_once_when_first_playlist_is_invalid() {
		global $wp_scripts;

		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/uploads/small-audio.mp3'
		);

		wp_playlist_shortcode( array( 'ids' => '999999' ) );
		wp_playlist_shortcode( array( 'ids' => (string) $attachment_id ) );
		wp_playlist_shortcode( array( 'ids' => (string) $attachment_id ) );

		$queue_count = array_count_values( $wp_scripts->queue );

		$this->assertArrayHasKey( 'wp-playlist', $queue_count, 'wp-playlist handle should be in the queue.' );
		$this->assertSame( 1, $queue_count['wp-playlist'], 'The wp-playlist script handle should appear exactly once in the queue.' );
	}
}
