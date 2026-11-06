<?php

/**
 * Tests for the classes and the attributes of the current category in Walker_Category::start_el().
 *
 * @group taxonomy
 * @group category
 * @group walker
 *
 * @covers Walker_Category::start_el
 */
class Tests_Category_WalkerCategoryCurrentCategory extends WP_UnitTestCase {

	/**
	 * IDs of the categories, from the top to the bottom of a branch: a grandparent, a parent and a child.
	 *
	 * @var int[]
	 */
	protected static $grandparent_id;
	protected static $parent_id;
	protected static $child_id;
	protected static $other_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$grandparent_id = $factory->category->create( array( 'name' => 'Walker Grandparent' ) );
		self::$parent_id      = $factory->category->create(
			array(
				'name'   => 'Walker Parent',
				'parent' => self::$grandparent_id,
			)
		);
		self::$child_id       = $factory->category->create(
			array(
				'name'   => 'Walker Child',
				'parent' => self::$parent_id,
			)
		);
		self::$other_id       = $factory->category->create( array( 'name' => 'Walker Other' ) );
	}

	/**
	 * Returns the output of the method for a category.
	 *
	 * @param int       $category_id      ID of the category.
	 * @param int|int[] $current_category The current category, or categories.
	 * @return string The output.
	 */
	private function get_output( $category_id, $current_category ) {
		$walker = new Walker_Category();
		$output = '';
		$args   = array(
			'use_desc_for_title' => 0,
			'style'              => 'list',
			'show_count'         => 0,
			'feed'               => '',
			'feed_image'         => '',
			'feed_type'          => '',
			'current_category'   => $current_category,
		);

		$walker->start_el( $output, get_term( $category_id ), 0, $args );

		return $output;
	}

	/**
	 * Returns the classes of the item in some output.
	 *
	 * @param string $output Output of the walker.
	 * @return string[] The classes.
	 */
	private function get_classes( $output ) {
		$processor = new WP_HTML_Tag_Processor( $output );
		$processor->next_tag( 'li' );

		return preg_split( '/\s+/', (string) $processor->get_attribute( 'class' ), -1, PREG_SPLIT_NO_EMPTY );
	}

	/**
	 * Returns the value of the `aria-current` attribute of the link in some output.
	 *
	 * @param string $output Output of the walker.
	 * @return string|null The value, or null when the link has none.
	 */
	private function get_aria_current( $output ) {
		$processor = new WP_HTML_Tag_Processor( $output );
		$processor->next_tag( 'a' );

		return $processor->get_attribute( 'aria-current' );
	}

	/**
	 * Tests that the current category has the class of the current category, and that its link is the current page.
	 *
	 * @ticket 66497
	 */
	public function test_should_mark_the_current_category() {
		$output = $this->get_output( self::$parent_id, self::$parent_id );

		$this->assertContains( 'current-cat', $this->get_classes( $output ) );
		$this->assertNotContains( 'current-cat-parent', $this->get_classes( $output ) );
		$this->assertSame( 'page', $this->get_aria_current( $output ) );
	}

	/**
	 * Tests that the parent of the current category has the class of a parent, and its link is not the current page.
	 *
	 * @ticket 66497
	 */
	public function test_should_mark_the_parent_of_the_current_category() {
		$output = $this->get_output( self::$parent_id, self::$child_id );

		$this->assertContains( 'current-cat-parent', $this->get_classes( $output ) );
		$this->assertNotContains( 'current-cat', $this->get_classes( $output ) );
		$this->assertNull( $this->get_aria_current( $output ) );
	}

	/**
	 * Tests that an ancestor of the current category has the class of an ancestor.
	 *
	 * @ticket 66497
	 */
	public function test_should_mark_the_ancestors_of_the_current_category() {
		$grandparent = $this->get_classes( $this->get_output( self::$grandparent_id, self::$child_id ) );
		$parent      = $this->get_classes( $this->get_output( self::$parent_id, self::$child_id ) );

		$this->assertContains( 'current-cat-ancestor', $grandparent );
		$this->assertNotContains( 'current-cat-parent', $grandparent, 'A grandparent is not the parent.' );
		$this->assertContains( 'current-cat-ancestor', $parent, 'The parent is an ancestor too.' );
	}

	/**
	 * Tests that the other categories have no class of the current category, and no attribute.
	 *
	 * @ticket 66497
	 *
	 * @dataProvider data_unrelated_categories
	 *
	 * @param string $category Name of the property that has the ID of the category.
	 */
	public function test_should_not_mark_the_categories_that_are_not_related( $category ) {
		$output  = $this->get_output( self::${$category}, self::$parent_id );
		$classes = $this->get_classes( $output );

		$this->assertNotContains( 'current-cat', $classes );
		$this->assertNotContains( 'current-cat-parent', $classes );
		$this->assertNotContains( 'current-cat-ancestor', $classes );
		$this->assertNull( $this->get_aria_current( $output ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unrelated_categories() {
		return array(
			'another category'       => array( 'other_id' ),
			'a child of the current' => array( 'child_id' ),
		);
	}

	/**
	 * Tests that a category that is not an ancestor of the current one is not marked, but a parent of it is.
	 *
	 * @ticket 66497
	 */
	public function test_should_not_mark_a_parent_of_the_current_category_as_the_current_one() {
		$classes = $this->get_classes( $this->get_output( self::$grandparent_id, self::$parent_id ) );

		$this->assertContains( 'current-cat-parent', $classes );
		$this->assertNotContains( 'current-cat', $classes );
	}

	/**
	 * Tests that every category of a list of current categories is marked.
	 *
	 * @ticket 66497
	 */
	public function test_should_mark_every_category_of_a_list_of_current_categories() {
		$current = array( self::$other_id, self::$child_id );

		$this->assertContains( 'current-cat', $this->get_classes( $this->get_output( self::$other_id, $current ) ) );
		$this->assertContains( 'current-cat', $this->get_classes( $this->get_output( self::$child_id, $current ) ) );
		$this->assertContains( 'current-cat-parent', $this->get_classes( $this->get_output( self::$parent_id, $current ) ) );
		$this->assertNotContains( 'current-cat', $this->get_classes( $this->get_output( self::$parent_id, $current ) ) );
	}

	/**
	 * Tests that no category is marked without a current category.
	 *
	 * @ticket 66497
	 *
	 * @dataProvider data_no_current_category
	 *
	 * @param mixed $current_category Value without a current category.
	 */
	public function test_should_not_mark_anything_without_a_current_category( $current_category ) {
		$output = $this->get_output( self::$parent_id, $current_category );

		$this->assertSame( array( 'cat-item', 'cat-item-' . self::$parent_id ), $this->get_classes( $output ) );
		$this->assertNull( $this->get_aria_current( $output ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_current_category() {
		return array(
			'zero'        => array( 0 ),
			'empty array' => array( array() ),
			'false'       => array( false ),
			'empty'       => array( '' ),
		);
	}
}
