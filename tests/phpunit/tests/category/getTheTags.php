<?php

/**
 * Tests for get_the_tags().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::get_the_tags
 */
class Tests_Category_GetTheTags extends WP_UnitTestCase {

	/**
	 * Tag object.
	 *
	 * @var WP_Term
	 */
	private static $tag;

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
				'name' => 'Test Tag',
				'slug' => 'test-tag',
			)
		);

		self::$post = $factory->post->create_and_get(
			array(
				'tags_input' => array( self::$tag->slug ),
			)
		);
	}

	/**
	 * Tests that get_the_tags() returns an array of WP_Term objects for a post ID or WP_Post object.
	 *
	 * @ticket 65819
	 */
	public function test_returns_tags_for_post_id_and_post_object() {
		$tags_by_id = get_the_tags( self::$post->ID );

		$this->assertIsArray( $tags_by_id );
		$this->assertCount( 1, $tags_by_id );
		$this->assertInstanceOf( WP_Term::class, $tags_by_id[0] );
		$this->assertSame( self::$tag->term_id, $tags_by_id[0]->term_id );
		$this->assertSame( 'post_tag', $tags_by_id[0]->taxonomy );

		$tags_by_object = get_the_tags( self::$post );

		$this->assertIsArray( $tags_by_object );
		$this->assertCount( 1, $tags_by_object );
		$this->assertSame( self::$tag->term_id, $tags_by_object[0]->term_id );
	}

	/**
	 * Tests that get_the_tags() defaults to the global post when no post argument is passed.
	 *
	 * @ticket 65819
	 */
	public function test_defaults_to_global_post_when_post_is_omitted() {
		$GLOBALS['post'] = self::$post;

		$tags = get_the_tags();

		$this->assertIsArray( $tags );
		$this->assertCount( 1, $tags );
		$this->assertSame( self::$tag->term_id, $tags[0]->term_id );
	}

	/**
	 * Tests that get_the_tags() returns false when a post has no tags or does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_when_no_tags_or_invalid_post() {
		$untagged_post_id = self::factory()->post->create();

		$this->assertFalse( get_the_tags( $untagged_post_id ) );
		$this->assertFalse( get_the_tags( 99999999 ) );
		$this->assertFalse( get_the_tags( 0 ) );
	}

	/**
	 * Tests that get_the_tags() applies the 'get_the_tags' filter.
	 *
	 * @ticket 65819
	 */
	public function test_applies_get_the_tags_filter() {
		$filter = new MockAction();
		add_filter( 'get_the_tags', array( $filter, 'filter' ) );

		$tags = get_the_tags( self::$post->ID );

		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( $tags ), $filter->get_args()[0] );
	}
}
