<?php

class Tests_Post_wpTrashPost extends WP_UnitTestCase {
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

	public function test_trash_post() {
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
		add_action(
			'wp_trash_post',
			function ( $post_id, $previous_status ) {
				$this->assertSame( $this->post->ID, $post_id, 'wp_trash_post first parameter should be the trashed post ID.' );
				$this->assertSame( $this->post->post_status, $previous_status, 'wp_trash_post second parameter should be the previous trashed post status.' );
			},
			10,
			2
		);
		add_action(
			'trashed_post',
			function ( $post_id, $previous_status ) {
				$this->assertSame( $this->post->ID, $post_id, 'trashed_post first parameter should be the trashed post ID.' );
				$this->assertSame( $this->post->post_status, $previous_status, 'trashed_post second parameter should be the previous trashed post status.' );
			},
			10,
			2
		);

		$result = wp_trash_post( $this->post->ID );

		$this->assertInstanceOf( 'WP_Post', $result, 'wp_trash_post returned value should be an instance of WP_Post.' );

		$expected_hooks = array(
			array( 'filter', 'pre_trash_post' ),
			array( 'action', 'wp_trash_post' ),
			array( 'action', 'trashed_post' ),
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

		$this->assertContains( $this->post->ID, $trashed, 'The post should be trashed.' );

		$trashed_post_metas = get_post_meta( $this->post->ID );

		$this->assertArrayHasKey( '_wp_trash_meta_status', $trashed_post_metas, 'Trashed post should have _wp_trash_meta_status meta set.' );
		$this->assertCount( 1, $trashed_post_metas['_wp_trash_meta_status'], 'Trashed post should have only one _wp_trash_meta_status meta set.' );
		$this->assertSame( $this->post->post_status, reset( $trashed_post_metas['_wp_trash_meta_status'] ), 'Trashed post should have _wp_trash_meta_status meta set to previous post status.' );
		$this->assertArrayHasKey( '_wp_trash_meta_time', $trashed_post_metas, 'Trashed post should have _wp_trash_meta_time meta set.' );
		$this->assertCount( 1, $trashed_post_metas['_wp_trash_meta_time'], 'Trashed post should have only one _wp_trash_meta_time meta set.' );
	}
}
