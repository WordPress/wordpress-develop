<?php

/**
 * Tests for the `media_handle_sideload()` function.
 *
 * @group media
 * @covers ::media_handle_sideload
 */
class Tests_Media_mediaHandleSideload extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	public function tear_down() {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Sideloads a copy of a test image.
	 *
	 * @param int $post_id The post ID passed to `media_handle_sideload()`.
	 * @return string The path of the sideloaded file.
	 */
	private function sideload( $post_id ) {
		$tmp_name = wp_tempnam( 'canola.jpg' );
		copy( DIR_TESTDATA . '/images/canola.jpg', $tmp_name );

		$attachment_id = media_handle_sideload(
			array(
				'name'     => 'canola.jpg',
				'tmp_name' => $tmp_name,
			),
			$post_id
		);

		$this->assertIsInt( $attachment_id );

		return get_attached_file( $attachment_id );
	}

	/**
	 * @ticket 43119
	 *
	 * @dataProvider data_empty_post_id
	 *
	 * @param mixed $post_id An empty post ID.
	 */
	public function test_empty_post_id_ignores_global_post( $post_id ) {
		$GLOBALS['post'] = self::factory()->post->create_and_get( array( 'post_date' => '2018-01-01 00:00:00' ) );

		$this->assertStringContainsString( current_time( '/Y/m/' ), $this->sideload( $post_id ) );
	}

	/**
	 * Data provider for test_empty_post_id_ignores_global_post().
	 *
	 * @return array[]
	 */
	public function data_empty_post_id() {
		return array(
			'zero'  => array( 0 ),
			'null'  => array( null ),
			'false' => array( false ),
		);
	}

	/**
	 * @ticket 43119
	 */
	public function test_post_id_uses_post_date() {
		$post_id = self::factory()->post->create( array( 'post_date' => '2018-01-01 00:00:00' ) );

		$this->assertStringContainsString( '/2018/01/', $this->sideload( $post_id ) );
	}
}
