<?php

/**
 * Tests for walk_category_tree().
 *
 * @group taxonomy
 * @group category
 * @group walker
 *
 * @covers ::walk_category_tree
 */
class Tests_Category_WalkCategoryTree extends WP_UnitTestCase {

	/**
	 * Parent category object.
	 *
	 * @var WP_Term
	 */
	private static $parent;

	/**
	 * Child category object.
	 *
	 * @var WP_Term
	 */
	private static $child;

	/**
	 * Creates shared category fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$parent = $factory->category->create_and_get(
			array(
				'name' => 'Parent Category',
				'slug' => 'parent-category',
			)
		);

		self::$child = $factory->category->create_and_get(
			array(
				'name'   => 'Child Category',
				'slug'   => 'child-category',
				'parent' => self::$parent->term_id,
			)
		);
	}

	/**
	 * Tests that walk_category_tree() uses Walker_Category by default when no custom walker is provided.
	 *
	 * @ticket 65819
	 */
	public function test_uses_default_walker_category() {
		$this->set_permalink_structure( '' );
		$elements = array( self::$parent, self::$child );
		$args     = array(
			'style'              => 'list',
			'use_desc_for_title' => 0,
		);

		$expected = "\t" . '<li class="cat-item cat-item-' . self::$parent->term_id . '"><a href="http://' . WP_TESTS_DOMAIN . '/?cat=' . self::$parent->term_id . '">Parent Category</a>' . "\n" .
			"<ul class='children'>\n" .
			"\t" . '<li class="cat-item cat-item-' . self::$child->term_id . '"><a href="http://' . WP_TESTS_DOMAIN . '/?cat=' . self::$child->term_id . '">Child Category</a>' . "\n" .
			"</li>\n</ul>\n</li>\n";

		$this->assertSame( $expected, walk_category_tree( $elements, 0, $args ) );
	}

	/**
	 * Tests that walk_category_tree() falls back to Walker_Category when the 'walker' argument
	 * is not a Walker instance.
	 *
	 * @ticket 65819
	 */
	public function test_falls_back_to_default_walker_when_walker_arg_is_invalid() {
		$this->set_permalink_structure( '' );
		$elements = array( self::$parent, self::$child );
		$args     = array(
			'style'              => 'list',
			'use_desc_for_title' => 0,
			'walker'             => new stdClass(),
		);

		$expected = "\t" . '<li class="cat-item cat-item-' . self::$parent->term_id . '"><a href="http://' . WP_TESTS_DOMAIN . '/?cat=' . self::$parent->term_id . '">Parent Category</a>' . "\n" .
			"<ul class='children'>\n" .
			"\t" . '<li class="cat-item cat-item-' . self::$child->term_id . '"><a href="http://' . WP_TESTS_DOMAIN . '/?cat=' . self::$child->term_id . '">Child Category</a>' . "\n" .
			"</li>\n</ul>\n</li>\n";

		$this->assertSame( $expected, walk_category_tree( $elements, 0, $args ) );
	}

	/**
	 * Tests that walk_category_tree() uses a custom Walker instance when passed in $args['walker'].
	 *
	 * @ticket 65819
	 */
	public function test_uses_custom_walker_instance_when_provided() {
		$custom_walker = new class() extends Walker {
			public $tree_type = 'category';
			public $db_fields = array(
				'parent' => 'parent',
				'id'     => 'term_id',
			);

			public function start_el( &$output, $data_object, $depth = 0, $args = array(), $current_object_id = 0 ) {
				$output .= 'custom:' . $data_object->slug . '|';
			}
		};

		$elements = array( self::$parent, self::$child );
		$args     = array(
			'walker' => $custom_walker,
		);

		$this->assertSame(
			'custom:parent-category|custom:child-category|',
			walk_category_tree( $elements, 0, $args )
		);
	}
}
