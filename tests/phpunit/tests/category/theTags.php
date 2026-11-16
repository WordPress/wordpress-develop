<?php

/**
 * Tests for the_tags().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::the_tags
 */
class Tests_Category_TheTags extends WP_UnitTestCase {

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
	 * Tests that the_tags() outputs formatted tag links with default and custom before, separator, and after strings.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_formatted_tag_links() {
		$GLOBALS['post'] = self::$post;

		$link_1 = '<a href="' . esc_url( get_tag_link( self::$tag_1->term_id ) ) . '" rel="tag">Alpha</a>';
		$link_2 = '<a href="' . esc_url( get_tag_link( self::$tag_2->term_id ) ) . '" rel="tag">Beta</a>';

		$default_output = get_echo( 'the_tags' );
		$this->assertSame( 'Tags: ' . $link_1 . ', ' . $link_2, $default_output );

		$custom_output = get_echo( 'the_tags', array( 'Filed under: ', ' | ', '.' ) );
		$this->assertSame( 'Filed under: ' . $link_1 . ' | ' . $link_2 . '.', $custom_output );
	}

	/**
	 * Tests that the_tags() outputs nothing when get_the_tag_list() returns a WP_Error.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_nothing_when_get_the_tag_list_returns_wp_error() {
		$GLOBALS['post'] = self::$post;

		add_filter(
			'the_tags',
			static function () {
				return new WP_Error( 'tag_error', 'Failed to retrieve tags.' );
			}
		);

		$output = get_echo( 'the_tags' );

		$this->assertSame( '', $output );
	}
}
