<?php

/**
 * Tests for Walker_Category::start_lvl(), end_lvl() and end_el().
 *
 * @group taxonomy
 * @group category
 * @group walker
 *
 * @covers Walker_Category::start_lvl
 * @covers Walker_Category::end_lvl
 * @covers Walker_Category::end_el
 */
class Tests_Category_WalkerCategoryLevels extends WP_UnitTestCase {

	/**
	 * Walker used in the tests.
	 *
	 * @var Walker_Category
	 */
	protected $walker;

	public function set_up() {
		parent::set_up();

		$this->walker = new Walker_Category();
	}

	/**
	 * Tests that the list of the children is opened with its class, for the list style.
	 *
	 * @ticket 66497
	 */
	public function test_start_lvl_should_open_the_list_of_children_for_the_list_style() {
		$output = '';

		$this->walker->start_lvl( $output, 0, array( 'style' => 'list' ) );

		$this->assertSame( "<ul class='children'>", trim( $output ) );
	}

	/**
	 * Tests that the list of the children is closed, for the list style.
	 *
	 * @ticket 66497
	 */
	public function test_end_lvl_should_close_the_list_of_children_for_the_list_style() {
		$output = '';

		$this->walker->end_lvl( $output, 0, array( 'style' => 'list' ) );

		$this->assertSame( '</ul>', trim( $output ) );
	}

	/**
	 * Tests that the list of the children is indented with one tab for each level.
	 *
	 * @ticket 66497
	 *
	 * @dataProvider data_depths
	 *
	 * @param int $depth Depth of the level.
	 */
	public function test_levels_should_be_indented_with_a_tab_for_each_depth( $depth ) {
		$open  = '';
		$close = '';
		$args  = array( 'style' => 'list' );

		$this->walker->start_lvl( $open, $depth, $args );
		$this->walker->end_lvl( $close, $depth, $args );

		$this->assertStringStartsWith( str_repeat( "\t", $depth ) . '<ul', $open );
		$this->assertStringStartsWith( str_repeat( "\t", $depth ) . '</ul>', $close );
		$this->assertSame( $depth, strlen( $open ) - strlen( ltrim( $open, "\t" ) ) );
		$this->assertSame( $depth, strlen( $close ) - strlen( ltrim( $close, "\t" ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_depths() {
		return array(
			'top level' => array( 0 ),
			'one'       => array( 1 ),
			'two'       => array( 2 ),
			'four'      => array( 4 ),
		);
	}

	/**
	 * Tests that nothing is added for the levels of the styles that are not a list.
	 *
	 * @ticket 66497
	 *
	 * @dataProvider data_styles_that_are_not_lists
	 *
	 * @param string $style Style of the output.
	 */
	public function test_levels_should_add_nothing_for_styles_that_are_not_lists( $style ) {
		$open  = 'before ';
		$close = 'before ';
		$args  = array( 'style' => $style );

		$this->walker->start_lvl( $open, 2, $args );
		$this->walker->end_lvl( $close, 2, $args );

		$this->assertSame( 'before ', $open );
		$this->assertSame( 'before ', $close );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_styles_that_are_not_lists() {
		return array(
			'none'        => array( 'none' ),
			'break'       => array( 'break' ),
			'empty'       => array( '' ),
			'another one' => array( 'a-style' ),
		);
	}

	/**
	 * Tests that the item of the list is closed, for the list style.
	 *
	 * @ticket 66497
	 */
	public function test_end_el_should_close_the_item_for_the_list_style() {
		$output   = '';
		$category = self::factory()->category->create_and_get();

		$this->walker->end_el( $output, $category, 0, array( 'style' => 'list' ) );

		$this->assertSame( '</li>', trim( $output ) );
	}

	/**
	 * Tests that nothing is added to close the element of the styles that are not a list.
	 *
	 * @ticket 66497
	 *
	 * @dataProvider data_styles_that_are_not_lists
	 *
	 * @param string $style Style of the output.
	 */
	public function test_end_el_should_add_nothing_for_styles_that_are_not_lists( $style ) {
		$output   = 'before ';
		$category = self::factory()->category->create_and_get();

		$this->walker->end_el( $output, $category, 0, array( 'style' => $style ) );

		$this->assertSame( 'before ', $output );
	}

	/**
	 * Tests that the methods add to the output that is already there.
	 *
	 * @ticket 66497
	 */
	public function test_methods_should_add_to_the_existing_output() {
		$category = self::factory()->category->create_and_get();
		$args     = array( 'style' => 'list' );
		$output   = 'start ';

		$this->walker->start_lvl( $output, 0, $args );
		$this->walker->end_lvl( $output, 0, $args );
		$this->walker->end_el( $output, $category, 0, $args );

		$this->assertStringStartsWith( 'start ', $output );
		$this->assertSame( 1, substr_count( $output, "<ul class='children'>" ) );
		$this->assertSame( 1, substr_count( $output, '</ul>' ) );
		$this->assertSame( 1, substr_count( $output, '</li>' ) );
	}

	/**
	 * Tests that the levels and the items are used to build a nested list.
	 *
	 * @ticket 66497
	 */
	public function test_should_build_a_nested_list_with_walk() {
		$parent = self::factory()->category->create( array( 'name' => 'Walker Parent' ) );
		$child  = self::factory()->category->create(
			array(
				'name'   => 'Walker Child',
				'parent' => $parent,
			)
		);

		$output = wp_list_categories(
			array(
				'echo'       => false,
				'hide_empty' => false,
				'include'    => array( $parent, $child ),
				'title_li'   => '',
			)
		);

		$this->assertSame( 1, substr_count( $output, "<ul class='children'>" ), 'The child should be in a list of children.' );
		$this->assertSame( 2, substr_count( $output, '</li>' ), 'Each category should be closed.' );
		$this->assertLessThan( strpos( $output, 'Walker Child' ), strpos( $output, 'Walker Parent' ) );
	}
}
