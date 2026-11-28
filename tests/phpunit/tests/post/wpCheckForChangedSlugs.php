<?php

/**
 * Tests for wp_check_for_changed_slugs().
 *
 * @group post
 *
 * @covers ::wp_check_for_changed_slugs
 */
class Tests_Post_WpCheckForChangedSlugs extends WP_UnitTestCase {

	/**
	 * Returns the post after the change, with a new slug.
	 *
	 * @param WP_Post $post_before Post before the change.
	 * @param string  $new_slug    New slug.
	 * @return WP_Post Post after the change.
	 */
	private function with_new_slug( $post_before, $new_slug ) {
		$post            = clone $post_before;
		$post->post_name = $new_slug;

		return $post;
	}

	/**
	 * Tests that the old slug of a published post is stored.
	 *
	 * @ticket 66495
	 */
	public function test_should_store_the_old_slug_of_a_published_post() {
		$post_id     = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'first-slug',
			)
		);
		$post_before = get_post( $post_id );

		wp_check_for_changed_slugs( $post_id, $this->with_new_slug( $post_before, 'second-slug' ), $post_before );

		$this->assertSame( array( 'first-slug' ), get_post_meta( $post_id, '_wp_old_slug' ) );
	}

	/**
	 * Tests that nothing is stored when the slug does not change.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_store_anything_when_the_slug_does_not_change() {
		$post_id     = self::factory()->post->create( array( 'post_name' => 'same-slug' ) );
		$post_before = get_post( $post_id );

		wp_check_for_changed_slugs( $post_id, clone $post_before, $post_before );

		$this->assertSame( array(), get_post_meta( $post_id, '_wp_old_slug' ) );
	}

	/**
	 * Tests that nothing is stored for a post that is not published.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_statuses_that_are_not_published
	 *
	 * @param string $status Status of the post.
	 */
	public function test_should_not_store_the_old_slug_of_a_post_that_is_not_published( $status ) {
		$post_id     = self::factory()->post->create(
			array(
				'post_status' => $status,
				'post_name'   => 'first-slug',
			)
		);
		$post_before = get_post( $post_id );

		wp_check_for_changed_slugs( $post_id, $this->with_new_slug( $post_before, 'second-slug' ), $post_before );

		$this->assertSame( array(), get_post_meta( $post_id, '_wp_old_slug' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_statuses_that_are_not_published() {
		return array(
			'draft'   => array( 'draft' ),
			'pending' => array( 'pending' ),
			'private' => array( 'private' ),
		);
	}

	/**
	 * Tests that nothing is stored for a post type that is hierarchical.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_store_the_old_slug_of_a_hierarchical_post_type() {
		$page_id     = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'first-slug',
			)
		);
		$page_before = get_post( $page_id );

		wp_check_for_changed_slugs( $page_id, $this->with_new_slug( $page_before, 'second-slug' ), $page_before );

		$this->assertSame( array(), get_post_meta( $page_id, '_wp_old_slug' ) );
	}

	/**
	 * Tests that the old slug of an attachment with the status `inherit` is stored.
	 *
	 * @ticket 66495
	 */
	public function test_should_store_the_old_slug_of_an_attachment() {
		$attachment_id     = self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_name'      => 'first-slug',
			)
		);
		$attachment_before = get_post( $attachment_id );

		$this->assertSame( 'inherit', $attachment_before->post_status );

		wp_check_for_changed_slugs( $attachment_id, $this->with_new_slug( $attachment_before, 'second-slug' ), $attachment_before );

		$this->assertSame( array( 'first-slug' ), get_post_meta( $attachment_id, '_wp_old_slug' ) );
	}

	/**
	 * Tests that a slug that is empty is not stored.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_store_an_empty_old_slug() {
		$post_id     = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post_before = get_post( $post_id );

		$post_before->post_name = '';

		wp_check_for_changed_slugs( $post_id, $this->with_new_slug( $post_before, 'second-slug' ), $post_before );

		$this->assertSame( array(), get_post_meta( $post_id, '_wp_old_slug' ) );
	}

	/**
	 * Tests that an old slug is only stored once.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_store_an_old_slug_twice() {
		$post_id     = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'first-slug',
			)
		);
		$post_before = get_post( $post_id );
		add_post_meta( $post_id, '_wp_old_slug', 'first-slug' );

		wp_check_for_changed_slugs( $post_id, $this->with_new_slug( $post_before, 'second-slug' ), $post_before );

		$this->assertSame( array( 'first-slug' ), get_post_meta( $post_id, '_wp_old_slug' ) );
	}

	/**
	 * Tests that a new slug that was used before is removed from the old slugs.
	 *
	 * @ticket 66495
	 */
	public function test_should_remove_the_new_slug_from_the_old_slugs() {
		$post_id     = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'second-slug',
			)
		);
		$post_before = get_post( $post_id );
		add_post_meta( $post_id, '_wp_old_slug', 'first-slug' );

		// The post goes back to its first slug.
		wp_check_for_changed_slugs( $post_id, $this->with_new_slug( $post_before, 'first-slug' ), $post_before );

		$this->assertSame( array( 'second-slug' ), get_post_meta( $post_id, '_wp_old_slug' ) );
	}

	/**
	 * Tests that the old slug is stored when a post is updated, and that the function runs on the updates of posts and attachments.
	 *
	 * @ticket 66495
	 */
	public function test_should_run_when_a_post_is_updated() {
		$this->assertSame( 12, has_action( 'post_updated', 'wp_check_for_changed_slugs' ) );
		$this->assertSame( 12, has_action( 'attachment_updated', 'wp_check_for_changed_slugs' ) );

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'first-slug',
			)
		);

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'second-slug',
			)
		);

		$this->assertSame( array( 'first-slug' ), get_post_meta( $post_id, '_wp_old_slug' ) );
	}
}
