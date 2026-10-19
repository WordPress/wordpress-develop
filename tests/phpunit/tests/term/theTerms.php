<?php

/**
 * Tests for the_terms().
 *
 * @group taxonomy
 *
 * @covers ::the_terms
 */
class Tests_Term_TheTerms extends WP_UnitTestCase {

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
	 * Tests that the_terms() outputs formatted term links with default and custom arguments.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_formatted_term_links() {
		$link_1 = '<a href="' . esc_url( get_term_link( self::$tag_1, 'post_tag' ) ) . '" rel="tag">Alpha</a>';
		$link_2 = '<a href="' . esc_url( get_term_link( self::$tag_2, 'post_tag' ) ) . '" rel="tag">Beta</a>';

		$default_output = get_echo( 'the_terms', array( self::$post_id, 'post_tag' ) );
		$this->assertSame( $link_1 . ', ' . $link_2, $default_output );

		$custom_output = get_echo( 'the_terms', array( self::$post_id, 'post_tag', 'Tags: ', ' | ', '.' ) );
		$this->assertSame( 'Tags: ' . $link_1 . ' | ' . $link_2 . '.', $custom_output );
	}

	/**
	 * Tests that the_terms() returns false and outputs nothing when get_the_term_list() returns a WP_Error.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_and_outputs_nothing_on_wp_error() {
		ob_start();
		$result = the_terms( self::$post_id, 'invalid_taxonomy' );
		$output = ob_get_clean();

		$this->assertFalse( $result );
		$this->assertSame( '', $output );
	}

	/**
	 * Tests that the_terms() applies the 'the_terms' filter to the output.
	 *
	 * @ticket 65819
	 */
	public function test_applies_the_terms_filter() {
		$expected_list = get_the_term_list( self::$post_id, 'post_tag', '(', ' - ', ')' );

		$filter = new MockAction();
		add_filter( 'the_terms', array( $filter, 'filter' ), 10, 5 );
		add_filter(
			'the_terms',
			static function () {
				return 'filtered terms output';
			},
			20
		);

		$output = get_echo( 'the_terms', array( self::$post_id, 'post_tag', '(', ' - ', ')' ) );

		$this->assertSame( 'filtered terms output', $output );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame(
			array( $expected_list, 'post_tag', '(', ' - ', ')' ),
			$filter->get_args()[0]
		);
	}
}
