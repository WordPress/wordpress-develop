<?php

/**
 * @group admin
 */
class Tests_Admin_wpPostsListTable extends WP_UnitTestCase {
	protected static $top           = array();
	protected static $children      = array();
	protected static $grandchildren = array();
	protected static $post_ids      = array();

	/**
	 * @var WP_Posts_List_Table
	 */
	protected $table;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Posts_List_Table', array( 'screen' => 'edit-page' ) );
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Note that our top/children/grandchildren arrays are 1-indexed.

		// Create top-level pages.
		$num_posts = 5;
		foreach ( range( 1, $num_posts ) as $i ) {
			$p = $factory->post->create_and_get(
				array(
					'post_type'  => 'page',
					'post_title' => sprintf( 'Top Level Page %d', $i ),
				)
			);

			self::$top[ $i ]  = $p;
			self::$post_ids[] = $p->ID;
		}

		// Create child pages.
		$num_children = 3;
		foreach ( self::$top as $top => $top_page ) {
			foreach ( range( 1, $num_children ) as $i ) {
				$p = $factory->post->create_and_get(
					array(
						'post_type'   => 'page',
						'post_parent' => $top_page->ID,
						'post_title'  => sprintf( 'Child %d', $i ),
					)
				);

				self::$children[ $top ][ $i ] = $p;
				self::$post_ids[]             = $p->ID;
			}
		}

		// Create grand-child pages for the third and fourth top-level pages.
		$num_grandchildren = 3;
		foreach ( range( 3, 4 ) as $top ) {
			foreach ( self::$children[ $top ] as $child => $child_page ) {
				foreach ( range( 1, $num_grandchildren ) as $i ) {
					$p = $factory->post->create_and_get(
						array(
							'post_type'   => 'page',
							'post_parent' => $child_page->ID,
							'post_title'  => sprintf( 'Grandchild %d', $i ),
						)
					);

					self::$grandchildren[ $top ][ $child ][ $i ] = $p;
					self::$post_ids[]                            = $p->ID;
				}
			}
		}
	}

	/**
	 * @ticket 15459
	 *
	 * @covers WP_Posts_List_Table::display_rows
	 * @covers WP_Posts_List_Table::set_hierarchical_display
	 */
	public function test_list_hierarchical_pages_first_page() {
		$this->_test_list_hierarchical_page(
			array(
				'paged'          => 1,
				'posts_per_page' => 2,
			),
			array(
				self::$top[1]->ID,
				self::$children[1][1]->ID,
			)
		);
	}

	/**
	 * @ticket 15459
	 *
	 * @covers WP_Posts_List_Table::display_rows
	 * @covers WP_Posts_List_Table::set_hierarchical_display
	 */
	public function test_list_hierarchical_pages_second_page() {
		$this->_test_list_hierarchical_page(
			array(
				'paged'          => 2,
				'posts_per_page' => 2,
			),
			array(
				self::$top[1]->ID,
				self::$children[1][2]->ID,
				self::$children[1][3]->ID,
			)
		);
	}

	/**
	 * @ticket 15459
	 *
	 * @covers WP_Posts_List_Table::display_rows
	 * @covers WP_Posts_List_Table::set_hierarchical_display
	 */
	public function test_search_hierarchical_pages_first_page() {
		$this->_test_list_hierarchical_page(
			array(
				'paged'          => 1,
				'posts_per_page' => 2,
				's'              => 'Child',
			),
			array(
				self::$children[1][1]->ID,
				self::$children[1][2]->ID,
			)
		);
	}

	/**
	 * @ticket 15459
	 *
	 * @covers WP_Posts_List_Table::display_rows
	 * @covers WP_Posts_List_Table::set_hierarchical_display
	 */
	public function test_search_hierarchical_pages_second_page() {
		$this->_test_list_hierarchical_page(
			array(
				'paged'          => 2,
				'posts_per_page' => 2,
				's'              => 'Top',
			),
			array(
				self::$top[3]->ID,
				self::$top[4]->ID,
			)
		);
	}

	/**
	 * @ticket 15459
	 *
	 * @covers WP_Posts_List_Table::display_rows
	 * @covers WP_Posts_List_Table::set_hierarchical_display
	 */
	public function test_grandchildren_hierarchical_pages_first_page() {
		// Page 6 is the first page with grandchildren.
		$this->_test_list_hierarchical_page(
			array(
				'paged'          => 6,
				'posts_per_page' => 2,
			),
			array(
				self::$top[3]->ID,
				self::$children[3][1]->ID,
				self::$grandchildren[3][1][1]->ID,
				self::$grandchildren[3][1][2]->ID,
			)
		);
	}

	/**
	 * @ticket 15459
	 *
	 * @covers WP_Posts_List_Table::display_rows
	 * @covers WP_Posts_List_Table::set_hierarchical_display
	 */
	public function test_grandchildren_hierarchical_pages_second_page() {
		// Page 7 is the second page with grandchildren.
		$this->_test_list_hierarchical_page(
			array(
				'paged'          => 7,
				'posts_per_page' => 2,
			),
			array(
				self::$top[3]->ID,
				self::$children[3][1]->ID,
				self::$grandchildren[3][1][3]->ID,
				self::$children[3][2]->ID,
			)
		);
	}

	/**
	 * Helper function to test the output of a page which uses `WP_Posts_List_Table`.
	 *
	 * @param array $args         Query args for the list of pages.
	 * @param array $expected_ids Expected IDs of pages returned.
	 */
	protected function _test_list_hierarchical_page( array $args, array $expected_ids ) {
		$matches = array();

		$_REQUEST['paged']   = $args['paged'];
		$GLOBALS['per_page'] = $args['posts_per_page'];

		$args = array_merge(
			array(
				'post_type' => 'page',
			),
			$args
		);

		// Mimic the behavior of `wp_edit_posts_query()`:
		if ( ! isset( $args['orderby'] ) ) {
			$args['orderby']                = 'menu_order title';
			$args['order']                  = 'asc';
			$args['posts_per_page']         = -1;
			$args['posts_per_archive_page'] = -1;
		}

		// Effectively ignore the output until retrieving it later via `getActualOutput()`.
		$this->expectOutputRegex( '`.`' );

		$pages = new WP_Query( $args );

		$this->table->set_hierarchical_display( true );
		$this->table->display_rows( $pages->posts );
		$output = $this->getActualOutput();

		// Clean up.
		unset( $_REQUEST['paged'] );
		unset( $GLOBALS['per_page'] );

		preg_match_all( '|<tr[^>]*>|', $output, $matches );

		$this->assertCount( count( $expected_ids ), array_keys( $matches[0] ) );

		foreach ( $expected_ids as $id ) {
			$this->assertStringContainsString( sprintf( 'id="post-%d"', $id ), $output );
		}
	}

	/**
	 * Tests that a top-level page link has an aria-label with the title and "(Edit)".
	 *
	 * @ticket 62006
	 *
	 * @covers WP_Posts_List_Table::column_title
	 */
	public function test_top_level_page_aria_label() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post  = self::$top[1];
		$title = apply_filters( 'the_title', $post->post_title, $post->ID );

		// level=0 → top-level page.
		ob_start();
		$this->table->single_row( $post, 0 );
		$output = ob_get_clean();

		// Expected: "Title" (Edit)  — using curly quotes as the translatable string uses &#8220;/&#8221;.
		$this->assertStringContainsString( 'aria-label=', $output );
		$this->assertStringContainsString( esc_attr( $title ), $output );
		$this->assertStringContainsString( '(Edit)', html_entity_decode( $output, ENT_QUOTES | ENT_HTML5 ) );
		$this->assertStringNotContainsString( 'subpage', $output );

		wp_set_current_user( 0 );
	}

	/**
	 * Tests that a child page link includes "subpage of" with the parent page title in its aria-label.
	 *
	 * @ticket 62006
	 *
	 * @covers WP_Posts_List_Table::column_title
	 */
	public function test_child_page_aria_label_includes_parent_name() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$child        = self::$children[1][1];
		$parent       = self::$top[1];
		$child_title  = apply_filters( 'the_title', $child->post_title, $child->ID );
		$parent_title = apply_filters( 'the_title', $parent->post_title, $parent->ID );

		// level=1 → direct child page.
		ob_start();
		$this->table->single_row( $child, 1 );
		$output = ob_get_clean();

		$decoded = html_entity_decode( $output, ENT_QUOTES | ENT_HTML5 );

		$this->assertStringContainsString( 'aria-label=', $output );
		$this->assertStringContainsString( 'subpage of', $decoded );
		$this->assertStringContainsString( $child_title, $decoded );
		$this->assertStringContainsString( $parent_title, $decoded );
		$this->assertStringContainsString( '(Edit)', $decoded );

		wp_set_current_user( 0 );
	}

	/**
	 * Tests that a grandchild page link includes "subpage of" with its immediate parent title in its aria-label.
	 *
	 * @ticket 62006
	 *
	 * @covers WP_Posts_List_Table::column_title
	 */
	public function test_grandchild_page_aria_label_includes_immediate_parent_name() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$grandchild       = self::$grandchildren[3][1][1];
		$child            = self::$children[3][1];
		$grandchild_title = apply_filters( 'the_title', $grandchild->post_title, $grandchild->ID );
		$parent_title     = apply_filters( 'the_title', $child->post_title, $child->ID );

		// level=2 → grandchild page; immediate parent is the child page.
		ob_start();
		$this->table->single_row( $grandchild, 2 );
		$output = ob_get_clean();

		$decoded = html_entity_decode( $output, ENT_QUOTES | ENT_HTML5 );

		$this->assertStringContainsString( 'aria-label=', $output );
		$this->assertStringContainsString( 'subpage of', $decoded );
		$this->assertStringContainsString( $grandchild_title, $decoded );
		$this->assertStringContainsString( $parent_title, $decoded );
		$this->assertStringContainsString( '(Edit)', $decoded );

		wp_set_current_user( 0 );
	}

	/**
	 * Tests that a non-editable page (trashed) does not receive an aria-label on the title span.
	 *
	 * @ticket 62006
	 *
	 * @covers WP_Posts_List_Table::column_title
	 */
	public function test_trashed_page_title_has_no_aria_label() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$trashed = self::factory()->post->create_and_get(
			array(
				'post_type'   => 'page',
				'post_status' => 'trash',
				'post_parent' => self::$top[1]->ID,
			)
		);

		ob_start();
		$this->table->single_row( $trashed, 1 );
		$output = ob_get_clean();

		// Trashed posts render as <span>, not <a class="row-title">, so no aria-label on the title.
		$this->assertStringNotContainsString( 'class="row-title"', $output );
		$this->assertStringNotContainsString( 'aria-label="&#8220;', $output );

		wp_set_current_user( 0 );
	}

	/**
	 * @ticket 37407
	 *
	 * @covers WP_Posts_List_Table::extra_tablenav
	 */
	public function test_filter_button_should_not_be_shown_if_there_are_no_posts() {
		// Set post type to a non-existent one.
		$this->table->screen->post_type = 'foo';

		ob_start();
		$this->table->extra_tablenav( 'top' );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'id="post-query-submit"', $output );
	}

	/**
	 * @ticket 37407
	 *
	 * @covers WP_Posts_List_Table::extra_tablenav
	 */
	public function test_months_dropdown_should_not_be_shown_if_there_are_no_posts() {
		// Set post type to a non-existent one.
		$this->table->screen->post_type = 'foo';

		ob_start();
		$this->table->extra_tablenav( 'top' );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'id="filter-by-date"', $output );
	}

	/**
	 * @ticket 37407
	 *
	 * @covers WP_Posts_List_Table::extra_tablenav
	 */
	public function test_category_dropdown_should_not_be_shown_if_there_are_no_posts() {
		// Set post type to a non-existent one.
		$this->table->screen->post_type = 'foo';

		ob_start();
		$this->table->extra_tablenav( 'top' );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'id="cat"', $output );
	}

	/**
	 * @ticket 38341
	 *
	 * @covers WP_Posts_List_Table::extra_tablenav
	 */
	public function test_empty_trash_button_should_not_be_shown_if_there_are_no_posts() {
		// Set post type to a non-existent one.
		$this->table->screen->post_type = 'foo';

		ob_start();
		$this->table->extra_tablenav( 'top' );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'id="delete_all"', $output );
	}

	/**
	 * @ticket 42066
	 *
	 * @covers WP_Posts_List_Table::get_views
	 */
	public function test_get_views_should_return_views_by_default() {
		global $avail_post_stati;

		$avail_post_stati_backup = $avail_post_stati;
		$avail_post_stati        = get_available_post_statuses();

		$actual           = $this->table->get_views();
		$avail_post_stati = $avail_post_stati_backup;

		$expected = array(
			'all'     => '<a href="edit.php?post_type=page">All <span class="count">(38)</span></a>',
			'publish' => '<a href="edit.php?post_status=publish&#038;post_type=page">Published <span class="count">(38)</span></a>',
		);

		$this->assertSame( $expected, $actual );
	}
}
