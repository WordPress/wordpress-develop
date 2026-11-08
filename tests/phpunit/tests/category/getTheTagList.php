<?php

/**
 * Tests for get_the_tag_list().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::get_the_tag_list
 */
class Tests_Category_GetTheTagList extends WP_UnitTestCase {

	/**
	 * First tag object.
	 *
	 * @var WP_Term
	 */
	private static $tag_1;

	/**
	 * Second tag object.
	 *
	 * @var WP_Term
	 */
	private static $tag_2;

	/**
	 * Post object assigned to both tags.
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
		self::$tag_1 = $factory->tag->create_and_get(
			array(
				'name' => 'Alpha',
				'slug' => 'alpha',
			)
		);

		self::$tag_2 = $factory->tag->create_and_get(
			array(
				'name' => 'Beta',
				'slug' => 'beta',
			)
		);

		self::$post = $factory->post->create_and_get(
			array(
				'tags_input' => array( self::$tag_1->slug, self::$tag_2->slug ),
			)
		);
	}

	/**
	 * Tests that get_the_tag_list() formats post tag links with before, separator, and after strings
	 * and defaults to the global post when no post ID is passed.
	 *
	 * @ticket 65819
	 */
	public function test_returns_formatted_tag_links() {
		$GLOBALS['post'] = self::$post;

		$link_1 = '<a href="' . esc_url( get_tag_link( self::$tag_1->term_id ) ) . '" rel="tag">Alpha</a>';
		$link_2 = '<a href="' . esc_url( get_tag_link( self::$tag_2->term_id ) ) . '" rel="tag">Beta</a>';

		$this->assertSame(
			$link_1 . $link_2,
			get_the_tag_list()
		);

		$this->assertSame(
			'Tags: ' . $link_1 . ', ' . $link_2 . '.',
			get_the_tag_list( 'Tags: ', ', ', '.', self::$post->ID )
		);
	}

	/**
	 * Tests that get_the_tag_list() returns false when a post has no tags or does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_when_no_tags_or_invalid_post() {
		$untagged_post_id = self::factory()->post->create();

		$this->assertFalse( get_the_tag_list( '', ', ', '', $untagged_post_id ) );
		$this->assertFalse( get_the_tag_list( '', ', ', '', 99999999 ) );
	}

	/**
	 * Tests that get_the_tag_list() applies the 'the_tags' filter with all arguments.
	 *
	 * @ticket 65819
	 */
	public function test_applies_the_tags_filter() {
		$link_1 = '<a href="' . esc_url( get_tag_link( self::$tag_1->term_id ) ) . '" rel="tag">Alpha</a>';
		$link_2 = '<a href="' . esc_url( get_tag_link( self::$tag_2->term_id ) ) . '" rel="tag">Beta</a>';

		$filter = new MockAction();
		add_filter( 'the_tags', array( $filter, 'filter' ), 10, 5 );
		add_filter(
			'the_tags',
			static function () {
				return 'filtered tag list';
			},
			20
		);

		$result = get_the_tag_list( '(', ' | ', ')', self::$post->ID );

		$this->assertSame( 'filtered tag list', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame(
			array( '(' . $link_1 . ' | ' . $link_2 . ')', '(', ' | ', ')', self::$post->ID ),
			$filter->get_args()[0]
		);
	}
}
