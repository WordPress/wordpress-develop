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

	/**
	 * Tests that `WP_Posts_List_Table::handle_row_actions()` conditionally renders
	 * Quick Edit markup depending on the presence of the 'inline hide-if-no-js' action.
	 *
	 * @ticket 16502
	 *
	 * @covers WP_Posts_List_Table::handle_row_actions
	 *
	 * @dataProvider data_post_types
	 *
	 * @param bool   $has_inline_action Whether the 'inline hide-if-no-js' should be present.
	 * @param bool   $expected          Whether the Quick Edit markup should be present.
	 * @param string $post_type         The post type to test.
	 * @param bool   $hierarchical      Optional. Whether the post type is hierarchical.
	 *                                  For custom post types only.
	 *                                  If hierarchical, the 'page_row_actions' hook is used,
	 *                                  otherwise 'post_row_actions'.
	 *                                  Default false.
	 */
	public function test_handle_row_actions_should_conditionally_render_quick_edit_markup( $has_inline_action, $expected, $post_type, $hierarchical = false ) {
		$hook = 'page' === $post_type || $hierarchical ? 'page_row_actions' : 'post_row_actions';
		add_filter(
			$hook,
			static function( $actions ) use ( $has_inline_action ) {
				if ( $has_inline_action ) {
					$actions['inline hide-if-no-js'] = 'Should render';
				} else {
					unset( $actions['inline hide-if-no-js'] );
				}
				return $actions;
			}
		);

		if ( str_contains( $post_type, 'cpt' ) ) {
			register_post_type(
				$post_type,
				array(
					'labels'       => array( 'name' => $post_type ),
					'hierarchical' => $hierarchical,
				)
			);
		}

		$post = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Post Title',
				'post_type'  => $post_type,
			)
		);

		$handle_row_actions = new ReflectionMethod( $this->table, 'handle_row_actions' );

		$handle_row_actions->setAccessible( true );
		$actual = $handle_row_actions->invokeArgs( $this->table, array( $post, 'primary', 'primary' ) );
		$handle_row_actions->setAccessible( false );

		if ( $expected ) {
			$this->assertStringContainsString( 'inline hide-if-no-js', $actual );
		} else {
			$this->assertStringNotContainsString( 'inline hide-if-no-js', $actual );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_post_types() {
		return array(
			'a post with the "inline hide-if-no-js" action'    => array(
				'has_inline_action' => true,
				'expected'          => true,
				'post_type'         => 'post',
			),
			'a page with the "inline hide-if-no-js" action'    => array(
				'has_inline_action' => true,
				'expected'          => true,
				'post_type'         => 'page',
			),
			'a hierarchical custom post type with the "inline hide-if-no-js" action' => array(
				'has_inline_action' => true,
				'expected'          => true,
				'post_type'         => 'my_cpt',
				'hierarchical'      => true,
			),
			'a non-hierarchical custom post type with the "inline hide-if-no-js" action' => array(
				'has_inline_action' => true,
				'expected'          => true,
				'post_type'         => 'my_cpt',
				'hierarchical'      => false,
			),
			'a post without the "inline hide-if-no-js" action' => array(
				'has_inline_action' => false,
				'expected'          => false,
				'post_type'         => 'post',
			),
			'a page without the "inline hide-if-no-js" action' => array(
				'has_inline_action' => false,
				'expected'          => false,
				'post_type'         => 'page',
			),
			'a hierarchical custom post type without the "inline hide-if-no-js" action' => array(
				'has_inline_action' => false,
				'expected'          => false,
				'post_type'         => 'my_cpt',
				'hierarchical'      => true,
			),
			'a non-hierarchical custom post type without the "inline hide-if-no-js" action' => array(
				'has_inline_action' => false,
				'expected'          => false,
				'post_type'         => 'my_cpt',
				'hierarchical'      => false,
			),
		);
	}

}
