<?php

/**
 * Tests for the _update_term_count_on_transition_post_status function.
 *
 * See Tests_Term_WpSetObjectTerms for tests that cover changing terms on a post when saving it.
 *
 * @group taxonomy
 *
 * @covers ::_update_term_count_on_transition_post_status
 */
class Tests_Taxonomy_UpdateTermCountOnTransitionPostStatus extends WP_UnitTestCase {

	/**
	 * @var int Post ID.
	 */
	protected static $post_id;

	/**
	 * @var int Term ID.
	 */
	protected static $term_id;

	/**
	 * @var string Post type.
	 */
	protected static $post_type = 'post';

	/**
	 * @var string Taxonomy name.
	 */
	protected static $taxonomy = 'category';

	/**
	 * Create shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_type'   => self::$post_type,
				'post_status' => 'publish',
			)
		);

		self::$term_id = $factory->term->create(
			array(
				'taxonomy' => self::$taxonomy,
				'name'     => 'Test Category',
			)
		);

		wp_set_object_terms( self::$post_id, self::$term_id, self::$taxonomy );
	}

	/**
	 * Test that the term count is updated when a post is published.
	 *
	 * @ticket 42522
	 */
	public function test_update_term_count_on_publish() {
		$this->assertTermCount( 1, self::$term_id );

		// Change post status to draft.
		wp_update_post(
			array(
				'ID'          => self::$post_id,
				'post_status' => 'draft',
			)
		);

		$this->assertTermCount( 0, self::$term_id );

		// Change post status back to publish.
		wp_update_post(
			array(
				'ID'          => self::$post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertTermCount( 1, self::$term_id );
	}

	/**
	 * Test that the term count is updated when a post is moved to trash.
	 *
	 * @ticket 42522
	 */
	public function test_update_term_count_on_trash() {
		$this->assertTermCount( 1, self::$term_id );

		// Move post to trash.
		wp_trash_post( self::$post_id );

		$this->assertTermCount( 0, self::$term_id );
	}

	/**
	 * Test that the term count is updated when a post is restored from trash.
	 *
	 * @ticket 42522
	 */
	public function test_update_term_count_on_restore() {
		$this->assertTermCount( 1, self::$term_id );

		// Move post to trash.
		wp_trash_post( self::$post_id );

		$this->assertTermCount( 0, self::$term_id, 'Post is in trash.' );

		// Restore post from trash.
		wp_untrash_post( self::$post_id );

		$this->assertTermCount( 0, self::$term_id, 'Post is in draft after untrashing.' );

		// re-publish post.
		wp_publish_post( self::$post_id );

		$this->assertTermCount( 1, self::$term_id, 'Post is in publish after publishing.' );
	}

	/**
	 * Test that the term count is updated when a post is deleted permanently.
	 *
	 * @ticket 42522
	 */
	public function test_update_term_count_on_delete() {
		$this->assertTermCount( 1, self::$term_id );

		// Delete post permanently.
		wp_delete_post( self::$post_id, true );

		$this->assertTermCount( 0, self::$term_id );
	}

	/**
	 * Test that the term count is not recalculated when neither the terms nor the post status change.
	 *
	 * @ticket 42522
	 */
	public function test_term_count_is_not_recalculated_when_status_does_not_change() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => self::$post_type,
				'post_status' => 'publish',
			)
		);

		wp_set_object_terms(
			$post_id,
			self::$term_id,
			self::$taxonomy
		);
		$edited_term_taxonomy_count = did_action( 'edited_term_taxonomy' );

		// Change something about the post but not its status.
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => get_post( $post_id )->post_content . ' - updated',
			)
		);

		$this->assertSame( 0, did_action( 'edited_term_taxonomy' ) - $edited_term_taxonomy_count, 'Term taxonomy count should not be recalculated when post status does not change.' );
		$this->assertTermCount( 2, self::$term_id );
	}

	/**
	 * Assert that the term count is correct.
	 *
	 * @since 6.9.0
	 *
	 * @param int $expected_count Expected term count.
	 * @param int $term_id        Term ID.
	 */
	protected function assertTermCount( $expected_count, $term_id, $message = '' ) {
		$term = get_term( $term_id );
		$this->assertSame( $expected_count, $term->count, $message );
	}
}
