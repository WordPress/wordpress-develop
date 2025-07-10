<?php
/**
 * @group media
 * @covers ::wp_playlist_shortcode
 */
class Tests_Media_WpPlaylistShortcode extends WP_UnitTestCase {

	/**
	 * @ticket 63583
	 */
	public function test_should_load_scripts_when_first_playlist_is_invalid() {
		$scripts_loaded = false;

		add_action(
			'wp_playlist_scripts',
			function () use ( &$scripts_loaded ) {
				$scripts_loaded = true;
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/uploads/small-audio.mp3'
		);

		wp_playlist_shortcode( array( 'ids' => '999999' ) );
		wp_playlist_shortcode( array( 'ids' => $attachment_id ) );

		$this->assertTrue( $scripts_loaded, 'Scripts should load even when first playlist has invalid ID' );

		remove_all_actions( 'wp_playlist_scripts' );
	}
}
