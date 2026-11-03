<?php

/**
 * Tests for has_tag().
 *
 * @group taxonomy
 * @group post_tag
 *
 * @covers ::has_tag
 */
class Tests_Category_HasTag extends WP_UnitTestCase {

	/**
	 * Tag object assigned to post.
	 *
	 * @var WP_Term
	 */
	private static $tag;

	/**
	 * Unassigned tag object.
	 *
	 * @var WP_Term
	 */
	private static $other_tag;

	/**
	 * Post object assigned to self::$tag.
	 *
	 * @var WP_Post
	 */
	private static $post;

	/**
	 * Creates shared tag and post fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$tag = $factory->tag->create_and_get(
			array(
				'name' => 'Assigned Tag',
				'slug' => 'assigned-tag',
			)
		);

		self::$other_tag = $factory->tag->create_and_get(
			array(
				'name' => 'Other Tag',
				'slug' => 'other-tag',
			)
		);

		self::$post = $factory->post->create_and_get();
		wp_set_object_terms( self::$post->ID, self::$tag->term_id, 'post_tag' );
	}

	/**
	 * Tests that has_tag() returns true when the post has the specified tag
	 * by ID, name, slug, or an array of values, and accepts a post ID or WP_Post object.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_when_post_has_matching_tag() {
		$this->assertTrue( has_tag( self::$tag->term_id, self::$post->ID ) );
		$this->assertTrue( has_tag( self::$tag->name, self::$post->ID ) );
		$this->assertTrue( has_tag( self::$tag->slug, self::$post->ID ) );
		$this->assertTrue( has_tag( array( self::$other_tag->slug, self::$tag->slug ), self::$post->ID ) );
		$this->assertTrue( has_tag( self::$tag->term_id, self::$post ) );
	}

	/**
	 * Tests that has_tag() with an empty tag check returns true when the post
	 * has at least one tag and false when it has no tags.
	 *
	 * @ticket 65819
	 */
	public function test_empty_tag_checks_if_post_has_any_tag() {
		$this->assertTrue( has_tag( '', self::$post->ID ) );

		$untagged_post_id = self::factory()->post->create();

		$this->assertFalse( has_tag( '', $untagged_post_id ) );
	}

	/**
	 * Tests that has_tag() defaults to the global post when no post parameter is passed.
	 *
	 * @ticket 65819
	 */
	public function test_defaults_to_global_post_when_post_is_omitted() {
		$GLOBALS['post'] = self::$post;

		$this->assertTrue( has_tag( self::$tag->term_id ) );
		$this->assertFalse( has_tag( self::$other_tag->term_id ) );
	}

	/**
	 * Tests that has_tag() returns false when the post does not have the tag
	 * or when the post does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_matching_tag_or_invalid_post() {
		$this->assertFalse( has_tag( self::$other_tag->term_id, self::$post->ID ) );
		$this->assertFalse( has_tag( self::$other_tag->name, self::$post->ID ) );
		$this->assertFalse( has_tag( self::$other_tag->slug, self::$post->ID ) );
		$this->assertFalse( has_tag( self::$tag->term_id, 99999999 ) );
	}

	/**
	 * Tests that an integer tag argument only checks against term IDs, not numeric names or slugs.
	 *
	 * @ticket 65819
	 */
	public function test_integer_tag_matches_only_term_id_not_numeric_name_or_slug() {
		$numeric_name = '12345';
		$tag          = self::factory()->tag->create_and_get(
			array(
				'name' => $numeric_name,
				'slug' => $numeric_name,
			)
		);

		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, $tag->term_id, 'post_tag' );

		// String matches the name/slug.
		$this->assertTrue( has_tag( $numeric_name, $post_id ) );

		// Integer does not match because term_id is different from the numeric name.
		$this->assertFalse( has_tag( (int) $numeric_name, $post_id ) );
	}
}
