<?php

/**
 * Tests for the `_update_term_count_on_transition_post_status()` function.
 *
 * See `Tests_Term_WpSetObjectTerms` for tests that cover changing terms on a post when saving it.
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
		// Create a mock action for checking the `edited_term_taxonomy` hook call count.
		$edited_term_taxonomy_action = new MockAction();
		add_action( 'edited_term_taxonomy', array( $edited_term_taxonomy_action, 'action' ) );

		// Create a mock action for checking the `update_term_count` hook call count.
		$update_term_count_action = new MockAction();
		add_action( 'update_term_count', array( $update_term_count_action, 'action' ) );

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
		$edited_term_taxonomy_count     = $edited_term_taxonomy_action->get_call_count();
		$update_term_count_action_count = $update_term_count_action->get_call_count();

		// Change something about the post but not its status.
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => get_post( $post_id )->post_content . ' - updated',
			)
		);

		$this->assertSame( 0, $edited_term_taxonomy_action->get_call_count() - $edited_term_taxonomy_count, 'Term taxonomy count should not be recalculated when post status does not change.' );
		$this->assertSame( 0, $update_term_count_action->get_call_count() - $update_term_count_action_count, 'The `update_term_count` action should not run when term taxonomy count is not recalculated.' );
		$this->assertTermCount( 2, self::$term_id );
	}

	/**
	 * Test that the term count is not recalculated when both the old and new status are included in term counts.
	 *
	 * This accounts for a transition such as draft -> pending.
	 *
	 * @ticket 63562
	 */
	public function test_term_count_is_not_recalculated_when_both_status_are_counted() {
		// Create a mock action for checking the `edited_term_taxonomy` hook call count.
		$edited_term_taxonomy_action = new MockAction();
		add_action( 'edited_term_taxonomy', array( $edited_term_taxonomy_action, 'action' ) );

		// Create a mock action for checking the `update_term_count` hook call count.
		$update_term_count_action = new MockAction();
		add_action( 'update_term_count', array( $update_term_count_action, 'action' ) );

		// Register a custom status that is included in term counts.
		register_post_status(
			'counted',
			array(
				'label'  => 'Counted',
				'public' => true,
			)
		);

		add_filter(
			'update_post_term_count_statuses',
			static function ( $status ) {
				$status[] = 'counted';
				return $status;
			}
		);

		// Change the post to another status that is included in term counts.
		wp_update_post(
			array(
				'ID'          => self::$post_id,
				'post_status' => 'counted',
			)
		);

		$this->assertSame( 0, $edited_term_taxonomy_action->get_call_count(), 'Term taxonomy count should not be recalculated when both statuses are included in term counts.' );
		$this->assertSame( 0, $update_term_count_action->get_call_count(), 'The `update_term_count` action should not run when term taxonomy count is not recalculated.' );
		$this->assertTermCount( 1, self::$term_id, 'Term count should remain unchanged when transitioning between post statuses that are counted.' );
	}

	/**
	 * Test that the term count is not recalculated when neither the old nor new status are included in term counts.
	 *
	 * This accounts for a transition such as draft -> pending.
	 *
	 * @ticket 63562
	 */
	public function test_term_count_is_not_recalculated_when_neither_status_is_counted() {
		// Create a mock action for checking the `edited_term_taxonomy` hook call count.
		$edited_term_taxonomy_action = new MockAction();
		add_action( 'edited_term_taxonomy', array( $edited_term_taxonomy_action, 'action' ) );

		// Create a mock action for checking the `update_term_count` hook call count.
		$update_term_count_action = new MockAction();
		add_action( 'update_term_count', array( $update_term_count_action, 'action' ) );

		// Change post status to draft.
		wp_update_post(
			array(
				'ID'          => self::$post_id,
				'post_status' => 'draft',
			)
		);

		$edited_term_taxonomy_count     = $edited_term_taxonomy_action->get_call_count();
		$update_term_count_action_count = $update_term_count_action->get_call_count();

		// Change the post to another status that is not included in term counts.
		wp_update_post(
			array(
				'ID'          => self::$post_id,
				'post_status' => 'pending',
			)
		);

		$this->assertSame( 0, $edited_term_taxonomy_action->get_call_count() - $edited_term_taxonomy_count, 'Term taxonomy count should not be recalculated when neither new nor old post status is included in term counts.' );
		$this->assertSame( 0, $update_term_count_action->get_call_count() - $update_term_count_action_count, 'The `update_term_count` action should not run when term taxonomy count is not recalculated.' );
		$this->assertTermCount( 0, self::$term_id, 'Term count should remain unchanged when transitioning between post statuses that are not counted.' );
	}

	/**
	 * Test to ensure that the `update_post_term_count_statuses` filter is respected.
	 *
	 * @ticket 63562
	 */
	public function test_update_post_term_count_statuses_filter_is_respected() {
		// Create a mock action for checking the `edited_term_taxonomy` hook call count.
		$edited_term_taxonomy_action = new MockAction();
		add_action( 'edited_term_taxonomy', array( $edited_term_taxonomy_action, 'action' ) );

		// Create a mock action for checking the `update_term_count` hook call count.
		$update_term_count_action = new MockAction();
		add_action( 'update_term_count', array( $update_term_count_action, 'action' ) );

		$custom_taxonomy = 'category_with_pending';

		// Add a custom taxonomy that includes 'pending' in its term counts.
		register_taxonomy(
			$custom_taxonomy,
			self::$post_type
		);
		add_filter(
			'update_post_term_count_statuses',
			static function ( array $statuses, WP_Taxonomy $taxonomy ) use ( $custom_taxonomy ): array {
				if ( $custom_taxonomy === $taxonomy->name ) {
					$statuses[] = 'pending';
				}

				return $statuses;
			},
			10,
			2
		);

		// Change post status to draft and give it a term to count.
		wp_update_post(
			array(
				'ID'          => self::$post_id,
				'post_status' => 'draft',
			)
		);
		$custom_term_id = self::factory()->term->create(
			array(
				'taxonomy' => $custom_taxonomy,
				'name'     => 'Hello',
			)
		);
		wp_set_object_terms(
			self::$post_id,
			$custom_term_id,
			$custom_taxonomy
		);

		$edited_term_taxonomy_count     = $edited_term_taxonomy_action->get_call_count();
		$update_term_count_action_count = $update_term_count_action->get_call_count();

		// Change the post to another status that is included in term counts for one of its two taxonomies.
		wp_update_post(
			array(
				'ID'          => self::$post_id,
				'post_status' => 'pending',
			)
		);

		$this->assertSame( 1, $edited_term_taxonomy_action->get_call_count() - $edited_term_taxonomy_count, 'Term taxonomy count should respect the statuses returned by the update_post_term_count_statuses filter.' );
		$this->assertSame( 1, $update_term_count_action->get_call_count() - $update_term_count_action_count, 'The `update_term_count` action should run when term taxonomy count is recalculated.' );
		$this->assertTermCount( 0, self::$term_id, 'Term count for the default taxonomy should remain zero since "pending" is not included in its countable statuses.' );
		$this->assertTermCount( 1, $custom_term_id, 'Term count for the custom taxonomy should be updated to 1 because the "pending" status is included via the update_post_term_count_statuses filter.' );
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
