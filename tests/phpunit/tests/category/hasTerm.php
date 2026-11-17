<?php

/**
 * Tests for has_term().
 *
 * @group taxonomy
 *
 * @covers ::has_term
 */
class Tests_Category_HasTerm extends WP_UnitTestCase {

	const TAXONOMY = 'wptests_tax';

	/**
	 * Term object assigned to post.
	 *
	 * @var WP_Term
	 */
	private $term;

	/**
	 * Post object assigned to $term.
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * Sets up test fixtures before each test method.
	 */
	public function set_up() {
		parent::set_up();

		register_taxonomy( self::TAXONOMY, 'post' );

		$this->term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => self::TAXONOMY,
				'name'     => 'Assigned Term',
				'slug'     => 'assigned-term',
			)
		);

		$this->post = self::factory()->post->create_and_get();
		wp_set_object_terms( $this->post->ID, $this->term->term_id, self::TAXONOMY );
	}

	/**
	 * Tests that has_term() defaults to the global post when no post parameter is passed.
	 *
	 * @ticket 65819
	 */
	public function test_has_term_falls_back_to_global_post() {
		$GLOBALS['post'] = $this->post;

		$this->assertTrue( has_term( $this->term->term_id, self::TAXONOMY ) );
	}

	/**
	 * Tests that has_term() returns false when the post does not exist or no post is in scope.
	 *
	 * @ticket 65819
	 */
	public function test_has_term_returns_false_for_missing_post() {
		$this->assertFalse( has_term( $this->term->term_id, self::TAXONOMY, 99999999 ) );
		$this->assertFalse( has_term( $this->term->term_id, self::TAXONOMY ) );
	}

	/**
	 * Tests that has_term() returns false when an invalid or non-existent taxonomy is passed.
	 *
	 * @ticket 65819
	 */
	public function test_has_term_returns_false_for_invalid_taxonomy() {
		$this->assertFalse( has_term( $this->term->term_id, 'non_existent_tax', $this->post->ID ) );
	}
}
