<?php

/**
 * Tests for the Walker_PageDropdown class.
 *
 * @group post
 * @group walker
 *
 * @coversDefaultClass Walker_PageDropdown
 */
class Tests_Post_Walker_PageDropdown extends WP_UnitTestCase {

	/**
	 * Creates a page object without touching the database.
	 *
	 * @param array $fields Fields to override.
	 * @return stdClass Page object.
	 */
	private function get_page( array $fields = array() ) {
		return (object) array_merge(
			array(
				'ID'          => 7,
				'post_title'  => 'About',
				'post_name'   => 'about',
				'post_status' => 'publish',
				'post_parent' => 0,
			),
			$fields
		);
	}

	/**
	 * Tests the option element generated for a page.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 *
	 * @dataProvider data_start_el
	 *
	 * @param array  $args     Arguments passed to the walker.
	 * @param int    $depth    Depth of the page.
	 * @param string $expected Expected option element, without the leading tab and trailing newline.
	 */
	public function test_start_el( $args, $depth, $expected ) {
		$args = array_merge( array( 'selected' => 0 ), $args );

		$walker = new Walker_PageDropdown();
		$output = '';

		$walker->start_el( $output, $this->get_page(), $depth, $args );

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
				'<option class="level-0" value="7">About</option>',
			),
			'first level of depth'                 => array(
				array(),
				1,
				'<option class="level-1" value="7">&nbsp;&nbsp;&nbsp;About</option>',
			),
			'second level of depth'                => array(
				array(),
				2,
				'<option class="level-2" value="7">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;About</option>',
			),
			'selected by integer ID'               => array(
				array( 'selected' => 7 ),
				0,
				'<option class="level-0" value="7" selected="selected">About</option>',
			),
			'selected by string ID'                => array(
				array( 'selected' => '7' ),
				0,
				'<option class="level-0" value="7" selected="selected">About</option>',
			),
			'another page is selected'             => array(
				array( 'selected' => 70 ),
				0,
				'<option class="level-0" value="7">About</option>',
			),
			'selected value is an empty string'    => array(
				array( 'selected' => '' ),
				0,
				'<option class="level-0" value="7">About</option>',
			),
			'selected value is false'              => array(
				array( 'selected' => false ),
				0,
				'<option class="level-0" value="7">About</option>',
			),
			'post name as the value field'         => array(
				array( 'value_field' => 'post_name' ),
				0,
				'<option class="level-0" value="about">About</option>',
			),
			'selection uses the ID with another value field' => array(
				array(
					'value_field' => 'post_name',
					'selected'    => 7,
				),
				0,
				'<option class="level-0" value="about" selected="selected">About</option>',
			),
			'post name does not select the page'   => array(
				array(
					'value_field' => 'post_name',
					'selected'    => 'about',
				),
				0,
				'<option class="level-0" value="about">About</option>',
			),
			'unknown value field falls back to ID' => array(
				array( 'value_field' => 'does_not_exist' ),
				0,
				'<option class="level-0" value="7">About</option>',
			),
			'with depth and selection'             => array(
				array( 'selected' => 7 ),
				1,
				'<option class="level-1" value="7" selected="selected">&nbsp;&nbsp;&nbsp;About</option>',
			),
		);
	}

	/**
	 * Tests the title displayed for a page.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 *
	 * @dataProvider data_page_titles
	 *
	 * @param string $post_title Page title.
	 * @param string $expected   Expected option text.
	 */
	public function test_start_el_displays_escaped_title( $post_title, $expected ) {
		$walker = new Walker_PageDropdown();
		$output = '';
		$args   = array( 'selected' => 0 );

		$walker->start_el( $output, $this->get_page( array( 'post_title' => $post_title ) ), 0, $args );

		$this->assertSame( "\t" . '<option class="level-0" value="7">' . $expected . '</option>' . "\n", $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_page_titles() {
		return array(
			'plain title'            => array( 'Contact us', 'Contact us' ),
			'title with markup'      => array( 'Tom & <b>Jerry</b> "quoted"', 'Tom &amp; &lt;b&gt;Jerry&lt;/b&gt; &quot;quoted&quot;' ),
			'empty title'            => array( '', '#7 (no title)' ),
			'zero title'             => array( '0', '0' ),
			'title with only spaces' => array( '  ', '  ' ),
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
		$walker = new Walker_PageDropdown();
		$output = '';
		$args   = array(
			'value_field' => 'post_name',
			'selected'    => 0,
		);

		$walker->start_el( $output, $this->get_page( array( 'post_name' => 'about"><script>' ) ), 0, $args );

		$this->assertSame( "\t" . '<option class="level-0" value="about&quot;&gt;&lt;script&gt;">About</option>' . "\n", $output );
	}

	/**
	 * Tests that the option is appended to the existing output.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 */
	public function test_start_el_appends_to_output() {
		$walker = new Walker_PageDropdown();
		$output = '<select>';
		$args   = array( 'selected' => 0 );

		$walker->start_el( $output, $this->get_page(), 0, $args );

		$this->assertSame( '<select>' . "\t" . '<option class="level-0" value="7">About</option>' . "\n", $output );
	}

	/**
	 * Tests that the page title is filtered before it is escaped.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 *
	 * @dataProvider data_titles_for_list_pages_filter
	 *
	 * @param string $post_title Page title.
	 * @param string $received   Title the filter is expected to receive.
	 */
	public function test_start_el_applies_list_pages_filter( $post_title, $received ) {
		$page = $this->get_page( array( 'post_title' => $post_title ) );

		$filter = new MockAction();
		add_filter( 'list_pages', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'list_pages',
			static function () {
				return 'Filtered <em>title</em>';
			},
			20
		);

		$walker = new Walker_PageDropdown();
		$output = '';
		$args   = array( 'selected' => 0 );

		$walker->start_el( $output, $page, 0, $args );

		$this->assertSame( "\t" . '<option class="level-0" value="7">Filtered &lt;em&gt;title&lt;/em&gt;</option>' . "\n", $output, 'The filtered title should be escaped and displayed.' );
		$this->assertSame( array( array( $received, $page ) ), $filter->get_args(), 'The filter should receive the title and the page.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_titles_for_list_pages_filter() {
		return array(
			'page with a title'    => array( 'About', 'About' ),
			'page without a title' => array( '', '#7 (no title)' ),
		);
	}

	/**
	 * Tests that child pages are nested under their parents.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 */
	public function test_walk_indents_child_pages() {
		$pages = array(
			$this->get_page(),
			$this->get_page(
				array(
					'ID'          => 8,
					'post_title'  => 'Team',
					'post_name'   => 'team',
					'post_parent' => 7,
				)
			),
			$this->get_page(
				array(
					'ID'          => 9,
					'post_title'  => 'Founders',
					'post_name'   => 'founders',
					'post_parent' => 8,
				)
			),
			$this->get_page(
				array(
					'ID'         => 10,
					'post_title' => 'Contact',
					'post_name'  => 'contact',
				)
			),
		);

		$walker = new Walker_PageDropdown();
		$output = $walker->walk( $pages, 0, array( 'selected' => 9 ) );

		$this->assertSame(
			"\t" . '<option class="level-0" value="7">About</option>' . "\n" .
			"\t" . '<option class="level-1" value="8">&nbsp;&nbsp;&nbsp;Team</option>' . "\n" .
			"\t" . '<option class="level-2" value="9" selected="selected">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Founders</option>' . "\n" .
			"\t" . '<option class="level-0" value="10">Contact</option>' . "\n",
			$output
		);
	}
}
