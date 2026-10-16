<?php

/**
 * Tests for get_the_category_list().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::get_the_category_list
 */
class Tests_Category_GetTheCategoryList extends WP_UnitTestCase {

	/**
	 * ID of the parent category.
	 *
	 * @var int
	 */
	private static $parent_id;

	/**
	 * ID of the child category.
	 *
	 * @var int
	 */
	private static $child_id;

	/**
	 * ID of a top-level category.
	 *
	 * @var int
	 */
	private static $other_id;

	/**
	 * ID of a post in the child and top-level categories.
	 *
	 * @var int
	 */
	private static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$parent_id = $factory->category->create(
			array(
				'name' => 'Parent',
				'slug' => 'parent',
			)
		);
		self::$child_id  = $factory->category->create(
			array(
				'name'   => 'Child',
				'slug'   => 'child',
				'parent' => self::$parent_id,
			)
		);
		self::$other_id  = $factory->category->create(
			array(
				'name' => 'Other',
				'slug' => 'other',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_category' => array( self::$child_id, self::$other_id ),
			)
		);
	}

	/**
	 * Gets the plain permalink of a category.
	 *
	 * @param int $category_id Category ID.
	 * @return string Category link.
	 */
	private function category_link( $category_id ) {
		return 'http://' . WP_TESTS_DOMAIN . '/?cat=' . $category_id;
	}

	/**
	 * Tests the unordered list built without a separator.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_parents_in_list
	 *
	 * @param string $parents  How to display the category parents.
	 * @param string $expected Expected list, with %child%, %parent% and %other% placeholders for the links.
	 */
	public function test_builds_unordered_list_without_separator( $parents, $expected ) {
		$expected = str_replace(
			array( '%child%', '%parent%', '%other%' ),
			array( $this->category_link( self::$child_id ), $this->category_link( self::$parent_id ), $this->category_link( self::$other_id ) ),
			$expected
		);

		$this->assertSame( $expected, get_the_category_list( '', $parents, self::$post_id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_parents_in_list() {
		return array(
			'without parents'         => array(
				'',
				'<ul class="post-categories">' .
					"\n\t" . '<li><a href="%child%" rel="category">Child</a></li>' .
					"\n\t" . '<li><a href="%other%" rel="category">Other</a></li>' .
				'</ul>',
			),
			'multiple parent links'   => array(
				'multiple',
				'<ul class="post-categories">' .
					"\n\t" . '<li><a href="%parent%">Parent</a><a href="%child%" rel="category">Child</a></li>' .
					"\n\t" . '<li><a href="%other%" rel="category">Other</a></li>' .
				'</ul>',
			),
			'single link with parent' => array(
				'single',
				'<ul class="post-categories">' .
					"\n\t" . '<li><a href="%child%"  rel="category">ParentChild</a></li>' .
					"\n\t" . '<li><a href="%other%"  rel="category">Other</a></li>' .
				'</ul>',
			),
			'uppercase parents value' => array(
				'MULTIPLE',
				'<ul class="post-categories">' .
					"\n\t" . '<li><a href="%parent%">Parent</a><a href="%child%" rel="category">Child</a></li>' .
					"\n\t" . '<li><a href="%other%" rel="category">Other</a></li>' .
				'</ul>',
			),
			'unknown parents value'   => array(
				'unknown',
				'<ul class="post-categories">' .
					"\n\t" . '<li><a href="%child%" rel="category">Child</a></li>' .
					"\n\t" . '<li><a href="%other%" rel="category">Other</a></li>' .
				'</ul>',
			),
		);
	}

	/**
	 * Tests the list joined with a separator.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_parents_with_separator
	 *
	 * @param string $parents  How to display the category parents.
	 * @param string $expected Expected list, with %child%, %parent% and %other% placeholders for the links.
	 */
	public function test_joins_links_with_separator( $parents, $expected ) {
		$expected = str_replace(
			array( '%child%', '%parent%', '%other%' ),
			array( $this->category_link( self::$child_id ), $this->category_link( self::$parent_id ), $this->category_link( self::$other_id ) ),
			$expected
		);

		$this->assertSame( $expected, get_the_category_list( ' | ', $parents, self::$post_id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_parents_with_separator() {
		return array(
			'without parents'         => array(
				'',
				'<a href="%child%" rel="category">Child</a> | <a href="%other%" rel="category">Other</a>',
			),
			'multiple parent links'   => array(
				'multiple',
				'<a href="%parent%">Parent</a> | <a href="%child%" rel="category">Child</a> | <a href="%other%" rel="category">Other</a>',
			),
			'single link with parent' => array(
				'single',
				'<a href="%child%" rel="category">Parent | Child</a> | <a href="%other%" rel="category">Other</a>',
			),
		);
	}

	/**
	 * Tests that the rel attribute includes "tag" when pretty permalinks are used.
	 *
	 * @ticket 65819
	 */
	public function test_uses_category_tag_rel_with_pretty_permalinks() {
		$this->set_permalink_structure( '/%postname%/' );
		// Registers the category permastruct again for the new permalink structure.
		create_initial_taxonomies();

		$this->assertSame(
			'<a href="http://' . WP_TESTS_DOMAIN . '/category/parent/child/" rel="category tag">Child</a>, <a href="http://' . WP_TESTS_DOMAIN . '/category/other/" rel="category tag">Other</a>',
			get_the_category_list( ', ', '', self::$post_id )
		);
	}

	/**
	 * Tests that the current post is used when no post ID is passed.
	 *
	 * @ticket 65819
	 */
	public function test_uses_current_post_by_default() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		$this->assertSame(
			'<a href="' . $this->category_link( self::$child_id ) . '" rel="category">Child</a>, <a href="' . $this->category_link( self::$other_id ) . '" rel="category">Other</a>',
			get_the_category_list( ', ' )
		);
	}

	/**
	 * Tests that a post type without categories gets an empty string.
	 *
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_post_type_without_categories() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$filter = new MockAction();
		add_filter( 'the_category', array( $filter, 'filter' ), 10, 3 );

		$this->assertSame( '', get_the_category_list( ', ', 'single', $page_id ) );
		$this->assertSame( array( array( '', ', ', 'single' ) ), $filter->get_args(), 'The the_category filter should receive the empty list.' );
	}

	/**
	 * Tests that "Uncategorized" is returned when the post has no categories.
	 *
	 * @ticket 65819
	 */
	public function test_returns_uncategorized_without_categories() {
		add_filter( 'the_category_list', '__return_empty_array' );

		$filter = new MockAction();
		add_filter( 'the_category', array( $filter, 'filter' ), 10, 3 );

		$this->assertSame( 'Uncategorized', get_the_category_list( ', ', 'multiple', self::$post_id ) );
		$this->assertSame( array( array( 'Uncategorized', ', ', 'multiple' ) ), $filter->get_args(), 'The the_category filter should receive the default text.' );
	}

	/**
	 * Tests that the the_category_list filter receives the categories and post ID and controls the list.
	 *
	 * @ticket 65819
	 */
	public function test_the_category_list_filter() {
		$filter = new MockAction();
		add_filter( 'the_category_list', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'the_category_list',
			static function () {
				return array( get_category( self::$other_id ) );
			},
			20
		);

		$this->assertSame(
			'<a href="' . $this->category_link( self::$other_id ) . '" rel="category">Other</a>',
			get_the_category_list( ', ', '', self::$post_id ),
			'The filtered categories should be listed.'
		);

		$args = $filter->get_args();

		$this->assertCount( 1, $args, 'The filter should run once.' );
		$this->assertSame( array( self::$child_id, self::$other_id ), wp_list_pluck( $args[0][0], 'term_id' ), 'The filter should receive the post categories.' );
		$this->assertSame( self::$post_id, $args[0][1], 'The filter should receive the post ID.' );
	}

	/**
	 * Tests that the the_category filter receives the list, separator and parents, and can replace the list.
	 *
	 * @ticket 65819
	 */
	public function test_the_category_filter() {
		$filter = new MockAction();
		add_filter( 'the_category', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'the_category',
			static function () {
				return 'filtered';
			},
			20
		);

		$this->assertSame( 'filtered', get_the_category_list( ', ', 'multiple', self::$post_id ) );
		$this->assertSame(
			array(
				array(
					'<a href="' . $this->category_link( self::$parent_id ) . '">Parent</a>, <a href="' . $this->category_link( self::$child_id ) . '" rel="category">Child</a>, <a href="' . $this->category_link( self::$other_id ) . '" rel="category">Other</a>',
					', ',
					'multiple',
				),
			),
			$filter->get_args()
		);
	}
}
