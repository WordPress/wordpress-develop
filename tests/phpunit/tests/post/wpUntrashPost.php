<?php

/**
 * @group post
 *
 * @covers ::wp_untrash_post
 */
class Tests_Post_WpUntrashPost extends WP_UnitTestCase {
	/**
	 * @var WP_Post
	 */
	protected $trashed_post;

	public function set_up() {
		parent::set_up();

		$this->trashed_post = wp_trash_post(
			$this->factory()->post->create(
				array(
					'post_status' => 'draft',
				)
			)
		);
	}

	/**
	 * Tests that wp_untrash_post() returns a WP_Post object,
	 * removes post meta for an untrashed post and sets it to a 'Draft'.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_untrash_post
	 */
	public function test_untrash_post() {
		$result = wp_untrash_post( $this->trashed_post->ID );

		$this->assertInstanceOf( 'WP_Post', $result, 'wp_untrash_post returned value should be an instance of WP_Post.' );

		$trashed = get_posts(
			array(
				'post_status' => 'trash',
				'fields'      => 'ids',
			)
		);

		$this->assertNotContains( $this->trashed_post->ID, $trashed, 'Untrashed post should not belong to trashed posts anymore.' );

		$untrashed_post_metas = get_post_meta( $this->trashed_post->ID );

		$this->assertArrayNotHasKey( '_wp_trash_meta_status', $untrashed_post_metas, 'Untrashed post should not have _wp_trash_meta_status meta anymore.' );
		$this->assertArrayNotHasKey( '_wp_trash_meta_time', $untrashed_post_metas, 'Untrashed post should not have _wp_trash_meta_time meta anymore.' );

		$post = get_post( $this->trashed_post->ID );

		$this->assertSame( 'draft', $post->post_status, 'Untrashed post should have its previous status set correctly.' );
	}

	/**
	 * Tests that wp_untrash_post() applies 'pre_untrash_post' filters
	 * and passes the expected values to callbacks.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_untrash_post
	 */
	public function test_pre_untrash_post_hook() {
		add_filter(
			'pre_untrash_post',
			function ( $trash, $post, $previous_status ) {
				$this->assertNull( $trash, 'pre_untrash_post first parameter should be null.' );
				$this->assertSame( $this->trashed_post->ID, $post->ID, 'pre_untrash_post second parameter should be the trashed post ID.' );
				$this->assertSame( $this->trashed_post->post_status, $previous_status, 'pre_untrash_post third parameter should be the previous trashed post status.' );

				return $trash;
			},
			10,
			3
		);

		wp_untrash_post( $this->trashed_post->ID );

		$this->assertGreaterThan( 0, did_filter( 'pre_untrash_post' ), 'pre_untrash_post filter was not called.' );
	}

	/**
	 * Tests that wp_untrash_post() triggers the 'untrash_post' action
	 * and passes the expected values to callbacks.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_untrash_post
	 */
	public function test_untrash_post_hook() {
		add_action(
			'untrash_post',
			function ( $post_id, $previous_status ) {
				$this->assertSame( $this->trashed_post->ID, $post_id, 'untrash_post first parameter should be the trashed post ID.' );
				$this->assertSame( $this->trashed_post->post_status, $previous_status, 'untrash_post second parameter should be the previous trashed post status.' );
			},
			10,
			2
		);

		wp_untrash_post( $this->trashed_post->ID );

		$this->assertGreaterThan( 0, did_action( 'untrash_post' ), 'untrash_post action was not called.' );
	}

	/**
	 * Tests that wp_untrash_post() triggers the 'untrashed_post' action
	 * and passes the expected values to callbacks.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_untrash_post
	 */
	public function test_untrashed_post_hook() {
		add_action(
			'untrashed_post',
			function ( $post_id, $previous_status ) {
				$this->assertSame( $this->trashed_post->ID, $post_id, 'untrashed_post first parameter should be the trashed post ID.' );
				$this->assertSame( $this->trashed_post->post_status, $previous_status, 'untrashed_post second parameter should be the previous trashed post status.' );
			},
			10,
			2
		);

		wp_untrash_post( $this->trashed_post->ID );

		$this->assertGreaterThan( 0, did_action( 'untrashed_post' ), 'untrashed_post action was not called.' );
	}
}
