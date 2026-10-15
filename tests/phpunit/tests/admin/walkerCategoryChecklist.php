<?php

/**
 * Tests for the Walker_Category_Checklist class.
 *
 * @group admin
 * @group taxonomy
 * @group walker
 *
 * @coversDefaultClass Walker_Category_Checklist
 */
class Tests_Admin_Walker_Category_Checklist extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/class-walker-category-checklist.php';
	}

	/**
	 * Creates a category object without touching the database.
	 *
	 * @param array $fields Fields to override.
	 * @return stdClass Category object.
	 */
	private function get_category( array $fields = array() ) {
		return (object) array_merge(
			array(
				'term_id' => 5,
				'name'    => 'News',
				'parent'  => 0,
			),
			$fields
		);
	}

	/**
	 * Generates the list item of a category and returns a tag processor positioned at its LI element.
	 *
	 * @param array $args Arguments passed to the walker.
	 * @return WP_HTML_Tag_Processor Tag processor.
	 */
	private function get_checkbox_item_processor( array $args ) {
		$walker = new Walker_Category_Checklist();
		$output = '';

		$walker->start_el( $output, $this->get_category(), 0, $args );

		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'LI' ), 'The output should start with a list item.' );

		return $processor;
	}

	/**
	 * Tests the markup that opens and closes a level and closes a list item.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_lvl
	 * @covers ::end_lvl
	 * @covers ::end_el
	 *
	 * @dataProvider data_depths
	 *
	 * @param int    $depth  Depth of the level.
	 * @param string $indent Expected indentation.
	 */
	public function test_level_and_element_wrappers( $depth, $indent ) {
		$walker = new Walker_Category_Checklist();

		$start_lvl = 'before';
		$walker->start_lvl( $start_lvl, $depth );

		$end_lvl = 'before';
		$walker->end_lvl( $end_lvl, $depth );

		$end_el = 'before';
		$walker->end_el( $end_el, $this->get_category(), $depth );

		$this->assertSame( "before{$indent}<ul class='children'>\n", $start_lvl, 'A level should open with an indented list.' );
		$this->assertSame( "before{$indent}</ul>\n", $end_lvl, 'A level should close with an indented list end tag.' );
		$this->assertSame( "before</li>\n", $end_el, 'An element should close with a list item end tag.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_depths() {
		return array(
			'top level'    => array( 0, '' ),
			'first level'  => array( 1, "\t" ),
			'second level' => array( 2, "\t\t" ),
		);
	}

	/**
	 * Tests the list item generated in list-only mode.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 *
	 * @dataProvider data_list_only_items
	 *
	 * @param array  $args     Arguments passed to the walker.
	 * @param string $expected Expected list item, without the leading newline.
	 */
	public function test_start_el_in_list_only_mode( $args, $expected ) {
		$args['list_only'] = true;

		$walker = new Walker_Category_Checklist();
		$output = '';

		$walker->start_el( $output, $this->get_category(), 0, $args );

		$this->assertSame( "\n" . $expected, $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_list_only_items() {
		$unselected = '<div class="category" data-term-id=5 tabindex="0" role="checkbox" aria-checked="false">News</div>';
		$selected   = '<div class="category selected" data-term-id=5 tabindex="0" role="checkbox" aria-checked="true">News</div>';

		return array(
			'no selected or popular categories' => array( array(), '<li>' . $unselected ),
			'empty selected categories'         => array( array( 'selected_cats' => array() ), '<li>' . $unselected ),
			'selected category'                 => array( array( 'selected_cats' => array( 4, 5 ) ), '<li>' . $selected ),
			'selected category as a string'     => array( array( 'selected_cats' => array( '5' ) ), '<li>' . $selected ),
			'another selected category'         => array( array( 'selected_cats' => array( 50 ) ), '<li>' . $unselected ),
			'popular category'                  => array( array( 'popular_cats' => array( 5 ) ), '<li class="popular-category">' . $unselected ),
			'popular category as a string'      => array( array( 'popular_cats' => array( '5' ) ), '<li class="popular-category">' . $unselected ),
			'another popular category'          => array( array( 'popular_cats' => array( 50 ) ), '<li>' . $unselected ),
			'popular and selected category'     => array(
				array(
					'popular_cats'  => array( 5 ),
					'selected_cats' => array( 5 ),
				),
				'<li class="popular-category">' . $selected,
			),
		);
	}

	/**
	 * Tests the checkbox generated for a category.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 *
	 * @dataProvider data_checkbox_items
	 *
	 * @param array       $args        Arguments passed to the walker.
	 * @param string      $name        Expected name of the checkbox.
	 * @param string      $id_prefix   Expected prefix of the list item and checkbox IDs.
	 * @param bool        $is_checked  Whether the checkbox is expected to be checked.
	 * @param bool        $is_disabled Whether the checkbox is expected to be disabled.
	 * @param string|null $li_class    Expected class of the list item.
	 */
	public function test_start_el_generates_checkbox( $args, $name, $id_prefix, $is_checked, $is_disabled, $li_class ) {
		$processor = $this->get_checkbox_item_processor( $args );

		$li_id = $processor->get_attribute( 'id' );

		$this->assertStringStartsWith( $id_prefix, $li_id, 'The list item ID should start with the taxonomy and term ID.' );
		$this->assertSame( $li_class, $processor->get_attribute( 'class' ), 'The list item class did not match.' );

		$this->assertTrue( $processor->next_tag( 'LABEL' ), 'The list item should contain a label.' );
		$this->assertSame( 'selectit', $processor->get_attribute( 'class' ), 'The label class did not match.' );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The label should contain an input.' );
		$this->assertSame( 'checkbox', $processor->get_attribute( 'type' ), 'The input should be a checkbox.' );
		$this->assertSame( '5', $processor->get_attribute( 'value' ), 'The checkbox value should be the term ID.' );
		$this->assertSame( $name, $processor->get_attribute( 'name' ), 'The checkbox name did not match.' );
		$this->assertStringStartsWith( $id_prefix, $processor->get_attribute( 'id' ), 'The checkbox ID should start with the taxonomy and term ID.' );
		$this->assertNotSame( $li_id, $processor->get_attribute( 'id' ), 'The checkbox and the list item should have different IDs.' );
		$this->assertSame( $is_checked ? 'checked' : null, $processor->get_attribute( 'checked' ), 'The checked state did not match.' );
		$this->assertSame( $is_disabled ? 'disabled' : null, $processor->get_attribute( 'disabled' ), 'The disabled state did not match.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_checkbox_items() {
		return array(
			'default arguments'              => array( array(), 'post_category[]', 'in-category-5-', false, false, null ),
			'empty taxonomy'                 => array( array( 'taxonomy' => '' ), 'post_category[]', 'in-category-5-', false, false, null ),
			'category taxonomy'              => array( array( 'taxonomy' => 'category' ), 'post_category[]', 'in-category-5-', false, false, null ),
			'another taxonomy'               => array( array( 'taxonomy' => 'genre' ), 'tax_input[genre][]', 'in-genre-5-', false, false, null ),
			'selected category'              => array( array( 'selected_cats' => array( 4, 5 ) ), 'post_category[]', 'in-category-5-', true, false, null ),
			'selected category as a string'  => array( array( 'selected_cats' => array( '5' ) ), 'post_category[]', 'in-category-5-', true, false, null ),
			'another selected category'      => array( array( 'selected_cats' => array( 50 ) ), 'post_category[]', 'in-category-5-', false, false, null ),
			'disabled'                       => array( array( 'disabled' => true ), 'post_category[]', 'in-category-5-', false, true, null ),
			'disabled is false'              => array( array( 'disabled' => false ), 'post_category[]', 'in-category-5-', false, false, null ),
			'list_only is false'             => array( array( 'list_only' => false ), 'post_category[]', 'in-category-5-', false, false, null ),
			'popular category'               => array( array( 'popular_cats' => array( 5 ) ), 'post_category[]', 'in-category-5-', false, false, 'popular-category' ),
			'another popular category'       => array( array( 'popular_cats' => array( 50 ) ), 'post_category[]', 'in-category-5-', false, false, null ),
			'popular, selected and disabled' => array(
				array(
					'taxonomy'      => 'genre',
					'popular_cats'  => array( 5 ),
					'selected_cats' => array( 5 ),
					'disabled'      => true,
				),
				'tax_input[genre][]',
				'in-genre-5-',
				true,
				true,
				'popular-category',
			),
		);
	}

	/**
	 * Tests that the category name is filtered and escaped.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 *
	 * @dataProvider data_list_only_modes
	 *
	 * @param bool $list_only Whether to generate the list-only markup.
	 */
	public function test_start_el_filters_and_escapes_category_name( $list_only ) {
		$filter = new MockAction();
		add_filter( 'the_category', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'the_category',
			static function () {
				return 'Tom & <b>Jerry</b>';
			},
			20
		);

		$walker = new Walker_Category_Checklist();
		$output = '';
		$args   = array( 'list_only' => $list_only );

		$walker->start_el( $output, $this->get_category(), 0, $args );

		$this->assertStringContainsString( 'Tom &amp; &lt;b&gt;Jerry&lt;/b&gt;', $output, 'The filtered name should be escaped and displayed.' );
		$this->assertStringNotContainsString( 'News', $output, 'The unfiltered name should not be displayed.' );
		$this->assertSame( array( array( 'News', '', '' ) ), $filter->get_args(), 'The filter should receive the name and two empty strings.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_list_only_modes() {
		return array(
			'checkbox markup'  => array( false ),
			'list-only markup' => array( true ),
		);
	}

	/**
	 * Tests that child categories are nested in a child list.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_lvl
	 * @covers ::end_lvl
	 * @covers ::start_el
	 * @covers ::end_el
	 */
	public function test_walk_nests_child_categories() {
		$categories = array(
			$this->get_category(),
			$this->get_category(
				array(
					'term_id' => 6,
					'name'    => 'Sports',
					'parent'  => 5,
				)
			),
			$this->get_category(
				array(
					'term_id' => 7,
					'name'    => 'Opinion',
				)
			),
		);

		$walker = new Walker_Category_Checklist();
		$output = $walker->walk(
			$categories,
			0,
			array(
				'list_only'     => true,
				'selected_cats' => array( 6 ),
			)
		);

		$this->assertSame(
			"\n" . '<li><div class="category" data-term-id=5 tabindex="0" role="checkbox" aria-checked="false">News</div>' .
			"<ul class='children'>\n" .
			"\n" . '<li><div class="category selected" data-term-id=6 tabindex="0" role="checkbox" aria-checked="true">Sports</div>' . "</li>\n" .
			"</ul>\n" .
			"</li>\n" .
			"\n" . '<li><div class="category" data-term-id=7 tabindex="0" role="checkbox" aria-checked="false">Opinion</div>' . "</li>\n",
			$output
		);
	}
}
