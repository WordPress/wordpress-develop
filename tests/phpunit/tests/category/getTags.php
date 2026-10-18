<?php

/**
 * Tests for get_tags().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::get_tags
 */
class Tests_Category_GetTags extends WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Tag IDs with assigned posts.
	 *
	 * @var int[]
	 */
	private static $tag_ids = array();

	/**
	 * Unassigned tag ID.
	 *
	 * @var int
	 */
	private static $empty_tag_id;

	/**
	 * Creates shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();

		self::$tag_ids = array(
			$factory->tag->create(
				array(
					'name' => 'Alpha Tag',
					'slug' => 'alpha-tag',
				)
			),
			$factory->tag->create(
				array(
					'name' => 'Beta Tag',
					'slug' => 'beta-tag',
				)
			),
			$factory->tag->create(
				array(
					'name' => 'Gamma Tag',
					'slug' => 'gamma-tag',
				)
			),
		);

		wp_set_post_tags( self::$post_id, self::$tag_ids );

		self::$empty_tag_id = $factory->tag->create(
			array(
				'name' => 'Zeta Empty Tag',
				'slug' => 'zeta-empty-tag',
			)
		);
	}

	/**
	 * Tests that get_tags() returns an array of post_tag term objects by default.
	 *
	 * @ticket 65819
	 */
	public function test_returns_post_tag_terms_by_default() {
		$tags = get_tags();

		$this->assertIsArray( $tags );
		$this->assertCount( count( self::$tag_ids ), $tags );

		foreach ( $tags as $tag ) {
			$this->assertInstanceOf( WP_Term::class, $tag );
			$this->assertSame( 'post_tag', $tag->taxonomy );
		}
	}

	/**
	 * Tests that get_tags() includes empty tags when hide_empty is false.
	 *
	 * @ticket 65819
	 */
	public function test_includes_empty_tags_when_hide_empty_is_false() {
		$tags    = get_tags( array( 'hide_empty' => false ) );
		$tag_ids = wp_list_pluck( $tags, 'term_id' );

		$this->assertContains( self::$empty_tag_id, $tag_ids );
		$this->assertCount( count( self::$tag_ids ) + 1, $tags );
	}

	/**
	 * Tests that get_tags() returns an empty array when no tags match the query.
	 *
	 * @ticket 65819
	 */
	public function test_returns_empty_array_when_no_tags_found() {
		$tags = get_tags( array( 'search' => 'non_existent_search_query_term' ) );

		$this->assertSame( array(), $tags );
	}

	/**
	 * Tests that get_tags() supports the fields argument.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_fields_argument
	 *
	 * @param string $fields Fields argument value.
	 */
	public function test_supports_fields_argument( $fields ) {
		$tags = get_tags(
			array(
				'fields'     => $fields,
				'hide_empty' => false,
			)
		);

		$all_ids = array_merge( self::$tag_ids, array( self::$empty_tag_id ) );

		if ( 'ids' === $fields ) {
			$expected = $all_ids;
		} elseif ( 'names' === $fields ) {
			$expected = array( 'Alpha Tag', 'Beta Tag', 'Gamma Tag', 'Zeta Empty Tag' );
		} elseif ( 'slugs' === $fields ) {
			$expected = array( 'alpha-tag', 'beta-tag', 'gamma-tag', 'zeta-empty-tag' );
		}

		$this->assertSameSets( $expected, $tags );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_fields_argument() {
		return array(
			'ids'   => array( 'ids' ),
			'names' => array( 'names' ),
			'slugs' => array( 'slugs' ),
		);
	}

	/**
	 * Tests that get_tags() respects orderby and order arguments.
	 *
	 * @ticket 65819
	 */
	public function test_respects_orderby_and_order() {
		$tags = get_tags(
			array(
				'orderby'    => 'name',
				'order'      => 'DESC',
				'hide_empty' => false,
			)
		);

		$this->assertSame(
			array( 'Zeta Empty Tag', 'Gamma Tag', 'Beta Tag', 'Alpha Tag' ),
			wp_list_pluck( $tags, 'name' )
		);

		$shuffled_ids    = array( self::$tag_ids[1], self::$empty_tag_id, self::$tag_ids[0], self::$tag_ids[2] );
		$tags_by_include = get_tags(
			array(
				'include'    => $shuffled_ids,
				'orderby'    => 'include',
				'hide_empty' => false,
			)
		);

		$this->assertSame(
			$shuffled_ids,
			wp_list_pluck( $tags_by_include, 'term_id' )
		);
	}

	/**
	 * Tests that the get_tags filter is applied with tags and arguments.
	 *
	 * @ticket 65819
	 */
	public function test_applies_get_tags_filter() {
		$filter_ran  = false;
		$filter_tags = null;
		$filter_args = null;

		add_filter(
			'get_tags',
			static function ( $tags, $args ) use ( &$filter_ran, &$filter_tags, &$filter_args ) {
				$filter_ran  = true;
				$filter_tags = $tags;
				$filter_args = $args;
				return array( 'filtered_tag_output' );
			},
			10,
			2
		);

		$result = get_tags( array( 'hide_empty' => false ) );

		$this->assertTrue( $filter_ran, 'The get_tags filter did not run.' );
		$this->assertIsArray( $filter_tags, 'The filter incoming tags were not an array.' );
		$this->assertIsArray( $filter_args, 'The filter arguments were not an array.' );

		$all_ids = array_merge( self::$tag_ids, array( self::$empty_tag_id ) );
		$this->assertSameSets( $all_ids, wp_list_pluck( $filter_tags, 'term_id' ) );
		$this->assertSame( 'post_tag', $filter_args['taxonomy'] );
		$this->assertFalse( $filter_args['hide_empty'] );
		$this->assertSame( array( 'filtered_tag_output' ), $result );
	}

	/**
	 * Tests that taxonomy can be overridden via arguments.
	 *
	 * @ticket 65819
	 */
	public function test_allows_taxonomy_override_via_args() {
		$category_id = self::factory()->category->create(
			array(
				'name' => 'Custom Category',
				'slug' => 'custom-category',
			)
		);

		$terms = get_tags(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'include'    => array( $category_id ),
			)
		);

		$this->assertIsArray( $terms );
		$this->assertCount( 1, $terms );
		$this->assertSame( 'category', $terms[0]->taxonomy );
		$this->assertSame( $category_id, $terms[0]->term_id );
	}
}
