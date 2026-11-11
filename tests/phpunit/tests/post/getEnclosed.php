<?php
/**
 * Tests for get_enclosed().
 *
 * @group post
 *
 * @covers ::get_enclosed
 */
class Tests_Post_GetEnclosed extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_array_when_post_has_no_enclosure() {
		$post_id = self::factory()->post->create();

		$this->assertSame( array(), get_enclosed( $post_id ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_enclosure_urls_from_meta() {
		$post_id = self::factory()->post->create();

		add_post_meta( $post_id, 'enclosure', "http://example.com/audio.mp3\n12345\naudio/mpeg\n" );
		add_post_meta( $post_id, 'enclosure', "https://example.org/video.mp4\n67890\nvideo/mp4\n" );
		add_post_meta( $post_id, 'unrelated', 'ignored' );

		$this->assertSame(
			array(
				'http://example.com/audio.mp3',
				'https://example.org/video.mp4',
			),
			get_enclosed( $post_id )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_trims_url_from_first_line_only() {
		$post_id = self::factory()->post->create();

		add_post_meta( $post_id, 'enclosure', "  http://example.com/sound.mp3  \n999\naudio/mpeg\n" );

		$this->assertSame( array( 'http://example.com/sound.mp3' ), get_enclosed( $post_id ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_modify_enclosed_urls() {
		$post_id = self::factory()->post->create();

		add_post_meta( $post_id, 'enclosure', "http://example.com/audio.mp3\n12345\naudio/mpeg\n" );

		$filter = new MockAction();
		add_filter(
			'get_enclosed',
			static function ( $enclosed, $filtered_post_id ) use ( $filter ) {
				$filter->filter( $enclosed, $filtered_post_id );
				$enclosed[] = 'http://example.com/filtered.mp3';
				return $enclosed;
			},
			10,
			2
		);

		$result = get_enclosed( $post_id );

		$this->assertSame(
			array(
				'http://example.com/audio.mp3',
				'http://example.com/filtered.mp3',
			),
			$result
		);
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame(
			array(
				array( 'http://example.com/audio.mp3' ),
				$post_id,
			),
			$filter->get_args()[0]
		);
	}
}
