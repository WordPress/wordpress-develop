<?php

/**
 * Tests for walk_category_dropdown_tree().
 *
 * @group taxonomy
 * @group category
 * @group walker
 *
 * @covers ::walk_category_dropdown_tree
 */
class Tests_Category_WalkCategoryDropdownTree extends WP_UnitTestCase {

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
	 * Tests that walk_category_dropdown_tree() uses Walker_CategoryDropdown by default
	 * when no custom walker is provided.
	 *
	 * @ticket 65819
	 */
	public function test_uses_default_walker_category_dropdown() {
		$elements = array( self::$parent, self::$child );
		$args     = array(
			'selected'    => 0,
			'show_count'  => 0,
			'value_field' => 'term_id',
		);

		$default_walker = new Walker_CategoryDropdown();
		$expected       = $default_walker->walk( $elements, 0, $args );

		$this->assertStringContainsString( 'value="' . self::$child->term_id . '"', $expected );
		$this->assertSame( $expected, walk_category_dropdown_tree( $elements, 0, $args ) );
	}

	/**
	 * Tests that walk_category_dropdown_tree() falls back to Walker_CategoryDropdown
	 * when the 'walker' argument is not a Walker instance.
	 *
	 * @ticket 65819
	 */
	public function test_falls_back_to_default_walker_when_walker_arg_is_invalid() {
		$elements = array( self::$parent, self::$child );
		$args     = array(
			'selected'    => 0,
			'show_count'  => 0,
			'value_field' => 'term_id',
			'walker'      => new stdClass(),
		);

		$default_walker = new Walker_CategoryDropdown();
		$expected       = $default_walker->walk( $elements, 0, $args );

		$this->assertStringContainsString( 'value="' . self::$child->term_id . '"', $expected );
		$this->assertSame( $expected, walk_category_dropdown_tree( $elements, 0, $args ) );
	}

	/**
	 * Tests that walk_category_dropdown_tree() uses a custom Walker instance
	 * when passed in $args['walker'].
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
				$output .= 'option:' . $data_object->slug . '|';
			}
		};

		$elements = array( self::$parent, self::$child );
		$args     = array(
			'walker' => $custom_walker,
		);

		$this->assertSame(
			'option:parent-category|option:child-category|',
			walk_category_dropdown_tree( $elements, 0, $args )
		);
	}
}
