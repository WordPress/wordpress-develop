<?php

/**
 * Tests for the Walker_CategoryDropdown class.
 *
 * @group taxonomy
 * @group category
 * @group walker
 *
 * @coversDefaultClass Walker_CategoryDropdown
 */
class Tests_Category_Walker_CategoryDropdown extends WP_UnitTestCase {

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
				'slug'    => 'news',
				'parent'  => 0,
				'count'   => 1234,
			),
			$fields
		);
	}

	/**
	 * Tests the option element generated for a category.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 *
	 * @dataProvider data_start_el
	 *
	 * @param array  $args     Arguments passed to the walker.
	 * @param int    $depth    Depth of the category.
	 * @param string $expected Expected option element, without the leading tab and trailing newline.
	 */
	public function test_start_el( $args, $depth, $expected ) {
		$args = array_merge(
			array(
				'selected'   => 0,
				'show_count' => 0,
			),
			$args
		);

		$walker = new Walker_CategoryDropdown();
		$output = '';

		$walker->start_el( $output, $this->get_category(), $depth, $args );

		$this->assertSame( "\t" . $expected . "\n", $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_start_el() {
		return array(
			'default arguments'                    => array(
				array(),
				0,
				'<option class="level-0" value="5">News</option>',
			),
			'first level of depth'                 => array(
				array(),
				1,
				'<option class="level-1" value="5">&nbsp;&nbsp;&nbsp;News</option>',
			),
			'second level of depth'                => array(
				array(),
				2,
				'<option class="level-2" value="5">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;News</option>',
			),
			'selected by integer ID'               => array(
				array( 'selected' => 5 ),
				0,
				'<option class="level-0" value="5" selected="selected">News</option>',
			),
			'selected by string ID'                => array(
				array( 'selected' => '5' ),
				0,
				'<option class="level-0" value="5" selected="selected">News</option>',
			),
			'another category is selected'         => array(
				array( 'selected' => 50 ),
				0,
				'<option class="level-0" value="5">News</option>',
			),
			'selected value is an empty string'    => array(
				array( 'selected' => '' ),
				0,
				'<option class="level-0" value="5">News</option>',
			),
			'selected value is false'              => array(
				array( 'selected' => false ),
				0,
				'<option class="level-0" value="5">News</option>',
			),
			'slug as the value field'              => array(
				array( 'value_field' => 'slug' ),
				0,
				'<option class="level-0" value="news">News</option>',
			),
			'selected by slug'                     => array(
				array(
					'value_field' => 'slug',
					'selected'    => 'news',
				),
				0,
				'<option class="level-0" value="news" selected="selected">News</option>',
			),
			'ID does not select with a slug value' => array(
				array(
					'value_field' => 'slug',
					'selected'    => 5,
				),
				0,
				'<option class="level-0" value="news">News</option>',
			),
			'unknown value field falls back to ID' => array(
				array(
					'value_field' => 'does_not_exist',
					'selected'    => 5,
				),
				0,
				'<option class="level-0" value="5" selected="selected">News</option>',
			),
			'with the post count'                  => array(
				array( 'show_count' => 1 ),
				0,
				'<option class="level-0" value="5">News&nbsp;&nbsp;(1,234)</option>',
			),
			'with depth, selection and post count' => array(
				array(
					'selected'   => 5,
					'show_count' => true,
				),
				1,
				'<option class="level-1" value="5" selected="selected">&nbsp;&nbsp;&nbsp;News&nbsp;&nbsp;(1,234)</option>',
			),
		);
	}

	/**
	 * Tests that the option value is escaped.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 */
	public function test_start_el_escapes_value() {
		$walker = new Walker_CategoryDropdown();
		$output = '';

		$walker->start_el(
			$output,
			$this->get_category( array( 'slug' => 'news"><script>' ) ),
			0,
			array(
				'value_field' => 'slug',
				'selected'    => 0,
				'show_count'  => 0,
			)
		);

		$this->assertSame( "\t" . '<option class="level-0" value="news&quot;&gt;&lt;script&gt;">News</option>' . "\n", $output );
	}

	/**
	 * Tests that the option is appended to the existing output.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 */
	public function test_start_el_appends_to_output() {
		$walker = new Walker_CategoryDropdown();
		$output = '<select>';
		$args   = array(
			'selected'   => 0,
			'show_count' => 0,
		);

		$walker->start_el( $output, $this->get_category(), 0, $args );

		$this->assertSame( '<select>' . "\t" . '<option class="level-0" value="5">News</option>' . "\n", $output );
	}

	/**
	 * Tests that the category name is filtered.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 */
	public function test_start_el_applies_list_cats_filter() {
		$category = $this->get_category();

		$filter = new MockAction();
		add_filter( 'list_cats', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'list_cats',
			static function () {
				return 'Filtered name';
			},
			20
		);

		$walker = new Walker_CategoryDropdown();
		$output = '';
		$args   = array(
			'selected'   => 0,
			'show_count' => 0,
		);

		$walker->start_el( $output, $category, 0, $args );

		$this->assertSame( "\t" . '<option class="level-0" value="5">Filtered name</option>' . "\n", $output, 'The filtered name should be displayed.' );
		$this->assertSame( array( array( 'News', $category ) ), $filter->get_args(), 'The filter should receive the name and the category.' );
	}

	/**
	 * Tests that child categories are nested under their parents.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 */
	public function test_walk_indents_child_categories() {
		$categories = array(
			$this->get_category(),
			$this->get_category(
				array(
					'term_id' => 6,
					'name'    => 'Sports',
					'slug'    => 'sports',
					'parent'  => 5,
				)
			),
			$this->get_category(
				array(
					'term_id' => 7,
					'name'    => 'Football',
					'slug'    => 'football',
					'parent'  => 6,
				)
			),
			$this->get_category(
				array(
					'term_id' => 8,
					'name'    => 'Opinion',
					'slug'    => 'opinion',
				)
			),
		);

		$walker = new Walker_CategoryDropdown();
		$output = $walker->walk(
			$categories,
			0,
			array(
				'selected'   => 7,
				'show_count' => 0,
			)
		);

		$this->assertSame(
			"\t" . '<option class="level-0" value="5">News</option>' . "\n" .
			"\t" . '<option class="level-1" value="6">&nbsp;&nbsp;&nbsp;Sports</option>' . "\n" .
			"\t" . '<option class="level-2" value="7" selected="selected">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Football</option>' . "\n" .
			"\t" . '<option class="level-0" value="8">Opinion</option>' . "\n",
			$output
		);
	}
}
