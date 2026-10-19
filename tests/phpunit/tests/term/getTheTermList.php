<?php

/**
 * Tests for get_the_term_list().
 *
 * @group taxonomy
 *
 * @covers ::get_the_term_list
 */
class Tests_Term_GetTheTermList extends WP_UnitTestCase {

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
	 * Post ID assigned to both tags.
	 *
	 * @var int
	 */
	private static $post_id;

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

		self::$post_id = $factory->post->create(
			array(
				'tags_input' => array( self::$tag_1->slug, self::$tag_2->slug ),
			)
		);
	}

	/**
	 * Tests that get_the_term_list() formats term links with default and custom before, separator, and after strings.
	 *
	 * @ticket 65819
	 */
	public function test_returns_formatted_term_links() {
		$link_1 = '<a href="' . esc_url( get_term_link( self::$tag_1, 'post_tag' ) ) . '" rel="tag">Alpha</a>';
		$link_2 = '<a href="' . esc_url( get_term_link( self::$tag_2, 'post_tag' ) ) . '" rel="tag">Beta</a>';

		$this->assertSame(
			$link_1 . $link_2,
			get_the_term_list( self::$post_id, 'post_tag' )
		);

		$this->assertSame(
			'Tags: ' . $link_1 . ', ' . $link_2 . '.',
			get_the_term_list( self::$post_id, 'post_tag', 'Tags: ', ', ', '.' )
		);
	}

	/**
	 * Tests that get_the_term_list() returns false when the post has no terms in the taxonomy or does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_when_no_terms_or_invalid_post() {
		$untagged_post_id = self::factory()->post->create();

		$this->assertFalse( get_the_term_list( $untagged_post_id, 'post_tag' ) );
		$this->assertFalse( get_the_term_list( 99999999, 'post_tag' ) );
	}

	/**
	 * Tests that get_the_term_list() returns a WP_Error when the taxonomy is invalid.
	 *
	 * @ticket 65819
	 */
	public function test_returns_wp_error_for_invalid_taxonomy() {
		$result = get_the_term_list( self::$post_id, 'invalid_taxonomy' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_taxonomy', $result->get_error_code() );
	}

	/**
	 * Tests that get_the_term_list() returns a WP_Error when get_term_link() fails for a term.
	 *
	 * @ticket 65819
	 */
	public function test_returns_wp_error_when_get_term_link_returns_wp_error() {
		add_filter(
			'get_the_terms',
			static function () {
				return array( 99999999 );
			}
		);

		$result = get_the_term_list( self::$post_id, 'post_tag' );

		$this->assertWPError( $result );
	}

	/**
	 * Tests that get_the_term_list() applies the 'term_links-{$taxonomy}' filter.
	 *
	 * @ticket 65819
	 */
	public function test_applies_term_links_taxonomy_filter() {
		$link_1 = '<a href="' . esc_url( get_term_link( self::$tag_1, 'post_tag' ) ) . '" rel="tag">Alpha</a>';
		$link_2 = '<a href="' . esc_url( get_term_link( self::$tag_2, 'post_tag' ) ) . '" rel="tag">Beta</a>';

		$filter = new MockAction();
		add_filter( 'term_links-post_tag', array( $filter, 'filter' ) );
		add_filter(
			'term_links-post_tag',
			static function () {
				return array( '<a href="https://example.org/custom" rel="tag">Custom</a>' );
			},
			20
		);

		$result = get_the_term_list( self::$post_id, 'post_tag', '(', ', ', ')' );

		$this->assertSame( '(<a href="https://example.org/custom" rel="tag">Custom</a>)', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( array( $link_1, $link_2 ) ), $filter->get_args()[0] );
	}
}
