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
		$script_load_count = 0;

		add_action(
			'wp_playlist_scripts',
			function () use ( &$script_load_count ) {
				$script_load_count++;
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/uploads/small-audio.mp3'
		);

		wp_playlist_shortcode( array( 'ids' => '999999' ) );
		wp_playlist_shortcode( array( 'ids' => (string)$attachment_id ) );
		wp_playlist_shortcode( array( 'ids' => (string)$attachment_id ) );

		$this->assertSame( 1, $script_load_count, 'The playlist scripts should be loaded exactly once.' );

		remove_all_actions( 'wp_playlist_scripts' );
	}
}
