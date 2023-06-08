<?php

class Tests_Post_wpUntrashPost extends WP_UnitTestCase {
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

	public function test_untrash_post() {
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
		add_action(
			'untrash_post',
			function ( $post_id, $previous_status ) {
				$this->assertSame( $this->trashed_post->ID, $post_id, 'untrash_post first parameter should be the trashed post ID.' );
				$this->assertSame( $this->trashed_post->post_status, $previous_status, 'untrash_post second parameter should be the previous trashed post status.' );
			},
			10,
			2
		);
		add_action(
			'untrashed_post',
			function ( $post_id, $previous_status ) {
				$this->assertSame( $this->trashed_post->ID, $post_id, 'untrashed_post first parameter should be the trashed post ID.' );
				$this->assertSame( $this->trashed_post->post_status, $previous_status, 'untrashed_post second parameter should be the previous trashed post status.' );
			},
			10,
			2
		);

		$result = wp_untrash_post( $this->trashed_post->ID );

		$this->assertInstanceOf( 'WP_Post', $result, 'wp_untrash_post returned value should be an instance of WP_Post.' );

		$expected_hooks = array(
			array( 'filter', 'pre_untrash_post' ),
			array( 'action', 'untrash_post' ),
			array( 'action', 'untrashed_post' ),
		);

		foreach ( $expected_hooks as $hook ) {
			$hook_type = $hook[0];
			$hook_tag  = $hook[1];

			if ( 'filter' === $hook_type ) {
				$this->assertGreaterThan( 0, did_filter( $hook_tag ), "$hook_tag filter was not called." );

				continue;
			}

			$this->assertGreaterThan( 0, did_action( $hook_tag ), "$hook_tag action was not called." );
		}

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
}
