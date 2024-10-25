<?php

/**
 * Tests for the _update_term_count_on_transition_post_status function.
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
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		register_post_type( self::$post_type, array( 'public' => true ) );
		register_taxonomy( self::$taxonomy, self::$post_type, array( 'public' => true ) );
	}

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
	 * Test that the term count is updated when a post is removed from a term.
	 *
	 * @ticket 42522
	 */
	public function test_update_term_count_on_remove_term() {
		$this->assertTermCount( 1, self::$term_id );

		// Remove post from term.
		wp_set_object_terms( self::$post_id, array(), self::$taxonomy );

		$this->assertTermCount( 0, self::$term_id );
	}

	/**
	 * Test that the term count is updated when a post is added to a term.
	 *
	 * @ticket 42522
	 */
	public function test_update_term_count_on_add_term() {
		$this->assertTermCount( 1, self::$term_id );

		// Add post to another term.
		$term_id2 = self::factory()->term->create(
			array(
				'taxonomy' => self::$taxonomy,
				'name'     => 'Test Category 2',
			)
		);

		wp_set_object_terms( self::$post_id, array( self::$term_id, $term_id2 ), self::$taxonomy );

		$this->assertTermCount( 1, self::$term_id );
		$this->assertTermCount( 1, $term_id2 );
	}

	/**
	 * Test that the term count is updated when a post is added to a term.
	 *
	 * @ticket 42522
	 */
	public function test_update_term_count_on_add_new_post_with_term() {
		$this->assertTermCount( 1, self::$term_id );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => self::$post_type,
				'post_status' => 'publish',
			)
		);

		wp_set_object_terms( $post_id, self::$term_id, self::$taxonomy );

		$this->assertTermCount( 2, self::$term_id );
	}

	/**
	 * Assert that the term count is correct.
	 *
	 * @param int $expected_count Expected term count.
	 * @param int $term_id        Term ID.
	 */
	protected function assertTermCount( $expected_count, $term_id, $message = '' ) {
		$term = get_term( $term_id );
		$this->assertSame( $expected_count, $term->count, $message );
	}
}
