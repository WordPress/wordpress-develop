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
	 * Unassigned term object.
	 *
	 * @var WP_Term
	 */
	private $other_term;

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

		$this->other_term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => self::TAXONOMY,
				'name'     => 'Other Term',
				'slug'     => 'other-term',
			)
		);

		$this->post = self::factory()->post->create_and_get();
		wp_set_object_terms( $this->post->ID, $this->term->term_id, self::TAXONOMY );
	}

	/**
	 * Cleans up test fixtures after each test method.
	 */
	public function tear_down() {
		unregister_taxonomy( self::TAXONOMY );

		parent::tear_down();
	}

	/**
	 * Tests that has_term() returns true when the post has the specified term
	 * by ID, name, slug, or an array of values, and accepts a post ID or WP_Post object.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_when_post_has_matching_term() {
		$this->assertTrue( has_term( $this->term->term_id, self::TAXONOMY, $this->post->ID ) );
		$this->assertTrue( has_term( $this->term->name, self::TAXONOMY, $this->post->ID ) );
		$this->assertTrue( has_term( $this->term->slug, self::TAXONOMY, $this->post->ID ) );
		$this->assertTrue( has_term( array( $this->other_term->slug, $this->term->slug ), self::TAXONOMY, $this->post->ID ) );
		$this->assertTrue( has_term( $this->term->term_id, self::TAXONOMY, $this->post ) );
	}

	/**
	 * Tests that has_term() with an empty term check returns true when the post
	 * has at least one term and false when it has no terms.
	 *
	 * @ticket 65819
	 */
	public function test_empty_term_checks_if_post_has_any_term() {
		$this->assertTrue( has_term( '', self::TAXONOMY, $this->post->ID ) );

		$unassigned_post_id = self::factory()->post->create();

		$this->assertFalse( has_term( '', self::TAXONOMY, $unassigned_post_id ) );
	}

	/**
	 * Tests that has_term() defaults to the global post when no post parameter is passed.
	 *
	 * @ticket 65819
	 */
	public function test_defaults_to_global_post_when_post_is_omitted() {
		$GLOBALS['post'] = $this->post;

		$this->assertTrue( has_term( $this->term->term_id, self::TAXONOMY ) );
		$this->assertFalse( has_term( $this->other_term->term_id, self::TAXONOMY ) );
	}

	/**
	 * Tests that has_term() returns false when the post does not have the term
	 * or when the post does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_matching_term_or_invalid_post() {
		$this->assertFalse( has_term( $this->other_term->term_id, self::TAXONOMY, $this->post->ID ) );
		$this->assertFalse( has_term( $this->other_term->name, self::TAXONOMY, $this->post->ID ) );
		$this->assertFalse( has_term( $this->other_term->slug, self::TAXONOMY, $this->post->ID ) );
		$this->assertFalse( has_term( $this->term->term_id, self::TAXONOMY, 99999999 ) );
	}

	/**
	 * Tests that has_term() returns false when an invalid or non-existent taxonomy is passed.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_invalid_taxonomy() {
		$this->assertFalse( has_term( $this->term->term_id, 'non_existent_tax', $this->post->ID ) );
		$this->assertFalse( has_term( $this->term->term_id, '', $this->post->ID ) );
	}
}
