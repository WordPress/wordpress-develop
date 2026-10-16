<?php

/**
 * Tests that post slugs do not collide with attachment slugs.
 *
 * @ticket 34972
 *
 * @group post
 * @group slugs
 * @covers ::wp_unique_post_slug
 */
class Tests_Post_WpUniquePostSlugAttachments extends WP_UnitTestCase {

	private function create_attachment( $slug ) {
		return self::factory()->attachment->create(
			array(
				'post_name'      => $slug,
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	private function create_post( $slug, $type = 'post', $status = 'publish' ) {
		return self::factory()->post->create(
			array(
				'post_type'   => $type,
				'post_status' => $status,
				'post_name'   => $slug,
				'post_title'  => $slug,
			)
		);
	}

	/**
	 * The bug from the ticket: a post created after an attachment used to
	 * get the same slug.
	 */
	public function test_post_does_not_reuse_attachment_slug() {
		$attachment_id = $this->create_attachment( 'my-slug' );
		$post_id       = $this->create_post( 'my-slug' );

		$this->assertSame( 'my-slug', get_post_field( 'post_name', $attachment_id ) );
		$this->assertSame( 'my-slug-2', get_post_field( 'post_name', $post_id ) );
	}

	public function test_attachment_does_not_reuse_post_slug() {
		$post_id       = $this->create_post( 'my-slug' );
		$attachment_id = $this->create_attachment( 'my-slug' );

		$this->assertSame( 'my-slug', get_post_field( 'post_name', $post_id ) );
		$this->assertSame( 'my-slug-2', get_post_field( 'post_name', $attachment_id ) );
	}

	public function test_page_does_not_reuse_attachment_slug() {
		$attachment_id = $this->create_attachment( 'my-slug' );
		$page_id       = $this->create_post( 'my-slug', 'page' );

		$this->assertSame( 'my-slug', get_post_field( 'post_name', $attachment_id ) );
		$this->assertSame( 'my-slug-2', get_post_field( 'post_name', $page_id ) );
	}

	public function test_suffix_increments_for_multiple_posts() {
		$this->create_attachment( 'my-slug' );
		$first  = $this->create_post( 'my-slug' );
		$second = $this->create_post( 'my-slug' );

		$this->assertSame( 'my-slug-2', get_post_field( 'post_name', $first ) );
		$this->assertSame( 'my-slug-3', get_post_field( 'post_name', $second ) );
	}

	public function test_updating_post_keeps_its_own_slug() {
		$post_id = $this->create_post( 'my-slug' );

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'New title',
			)
		);

		$this->assertSame( 'my-slug', get_post_field( 'post_name', $post_id ) );
	}

	public function test_updating_attachment_keeps_its_own_slug() {
		$attachment_id = $this->create_attachment( 'my-slug' );

		wp_update_post(
			array(
				'ID'         => $attachment_id,
				'post_title' => 'New title',
			)
		);

		$this->assertSame( 'my-slug', get_post_field( 'post_name', $attachment_id ) );
	}

	public function test_renaming_post_to_existing_attachment_slug_gets_suffix() {
		$attachment_id = $this->create_attachment( 'taken' );
		$post_id       = $this->create_post( 'free' );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'taken',
			)
		);

		$this->assertSame( 'taken', get_post_field( 'post_name', $attachment_id ) );
		$this->assertSame( 'taken-2', get_post_field( 'post_name', $post_id ) );
	}

	public function test_draft_published_later_does_not_reuse_attachment_slug() {
		$attachment_id = $this->create_attachment( 'my-slug' );
		$post_id       = $this->create_post( '', 'post', 'draft' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_name'   => 'my-slug',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'my-slug', get_post_field( 'post_name', $attachment_id ) );
		$this->assertSame( 'my-slug-2', get_post_field( 'post_name', $post_id ) );
	}

	public function test_wp_unique_post_slug_directly() {
		$this->create_attachment( 'direct' );

		$this->assertSame( 'direct-2', wp_unique_post_slug( 'direct', 0, 'publish', 'post', 0 ) );
		$this->assertSame( 'unused', wp_unique_post_slug( 'unused', 0, 'publish', 'post', 0 ) );
	}

	public function test_non_hierarchical_custom_post_type_does_not_reuse_attachment_slug() {
		register_post_type( 'book', array( 'public' => true ) );

		$this->create_attachment( 'my-slug' );
		$book_id = $this->create_post( 'my-slug', 'book' );

		$this->assertSame( 'my-slug-2', get_post_field( 'post_name', $book_id ) );

		unregister_post_type( 'book' );
	}

	/**
	 * Documents current behavior outside the scope of this ticket: posts and
	 * pages may still share a slug. Remove this test if that is ever changed.
	 */
	public function test_post_and_page_may_still_share_a_slug() {
		$post_id = $this->create_post( 'my-slug' );
		$page_id = $this->create_post( 'my-slug', 'page' );

		$this->assertSame( 'my-slug', get_post_field( 'post_name', $post_id ) );
		$this->assertSame( 'my-slug', get_post_field( 'post_name', $page_id ) );
	}
}