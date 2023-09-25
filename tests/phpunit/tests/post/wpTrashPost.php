<?php

/**
 * @group post
 *
 * @covers ::wp_trash_post
 */
class Tests_Post_WpTrashPost extends WP_UnitTestCase {
	/**
	 * @var WP_Post
	 */
	protected $post;

	public function set_up() {
		parent::set_up();

		$this->post = $this->factory()->post->create_and_get(
			array(
				'post_status' => 'draft',
			)
		);
	}

	/**
	 * Tests that wp_trash_post() returns a WP_Post object
	 * and sets the correct post meta to trash a post.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_trash_post
	 */
	public function test_trash_post() {
		$result = wp_trash_post( $this->post->ID );

		$this->assertInstanceOf( 'WP_Post', $result, 'wp_trash_post returned value should be an instance of WP_Post.' );

		$trashed = get_posts(
			array(
				'post_status' => 'trash',
				'fields'      => 'ids',
			)
		);

		$this->assertContains( $this->post->ID, $trashed, 'The post should be trashed.' );

		$trashed_post_metas = get_post_meta( $this->post->ID );

		$this->assertArrayHasKey( '_wp_trash_meta_status', $trashed_post_metas, 'Trashed post should have _wp_trash_meta_status meta set.' );
		$this->assertCount( 1, $trashed_post_metas['_wp_trash_meta_status'], 'Trashed post should have only one _wp_trash_meta_status meta set.' );
		$this->assertSame( $this->post->post_status, reset( $trashed_post_metas['_wp_trash_meta_status'] ), 'Trashed post should have _wp_trash_meta_status meta set to previous post status.' );
		$this->assertArrayHasKey( '_wp_trash_meta_time', $trashed_post_metas, 'Trashed post should have _wp_trash_meta_time meta set.' );
		$this->assertCount( 1, $trashed_post_metas['_wp_trash_meta_time'], 'Trashed post should have only one _wp_trash_meta_time meta set.' );
	}

	/**
	 * Tests that wp_trash_post() applies 'pre_trash_post' filters
	 * and passes the expected values to callbacks.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_trash_post
	 */
	public function test_pre_trash_post_hook() {
		add_filter(
			'pre_trash_post',
			function ( $trash, $post, $previous_status ) {
				$this->assertNull( $trash, 'pre_trash_post first parameter should be null.' );
				$this->assertSame( $this->post->ID, $post->ID, 'pre_trash_post second parameter should be the trashed post ID.' );
				$this->assertSame( $this->post->post_status, $previous_status, 'pre_trash_post third parameter should be the previous trashed post status.' );

				return $trash;
			},
			10,
			3
		);

		wp_trash_post( $this->post->ID );

		$this->assertGreaterThan( 0, did_filter( 'pre_trash_post' ), 'pre_trash_post filter was not called.' );
	}

	/**
	 * Tests that wp_trash_post() triggers the 'wp_trash_post' action
	 * and passes the expected values to callbacks.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_trash_post
	 */
	public function test_wp_trash_post_hook() {
		add_action(
			'wp_trash_post',
			function ( $post_id, $previous_status ) {
				$this->assertSame( $this->post->ID, $post_id, 'wp_trash_post first parameter should be the trashed post ID.' );
				$this->assertSame( $this->post->post_status, $previous_status, 'wp_trash_post second parameter should be the previous trashed post status.' );
			},
			10,
			2
		);

		wp_trash_post( $this->post->ID );

		$this->assertGreaterThan( 0, did_action( 'wp_trash_post' ), 'wp_trash_post action was not called.' );
	}

	/**
	 * Tests that wp_trash_post() triggers the 'trashed_post' action
	 * and passes the expected values to callbacks.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_trash_post
	 */
	public function test_trashed_post_hook() {
		add_action(
			'trashed_post',
			function ( $post_id, $previous_status ) {
				$this->assertSame( $this->post->ID, $post_id, 'trashed_post first parameter should be the trashed post ID.' );
				$this->assertSame( $this->post->post_status, $previous_status, 'trashed_post second parameter should be the previous trashed post status.' );
			},
			10,
			2
		);

		wp_trash_post( $this->post->ID );

		$this->assertGreaterThan( 0, did_action( 'trashed_post' ), 'trashed_post action was not called.' );
	}
}
