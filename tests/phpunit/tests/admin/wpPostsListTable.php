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
	 * @ticket 64932
	 *
	 * @covers WP_Posts_List_Table::display_rows
	 * @covers WP_Posts_List_Table::set_hierarchical_display
	 * @covers WP_Posts_List_Table::column_title
	 */
	public function test_child_page_row_title_has_hierarchy_description() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = $this->get_hierarchical_page_list_output(
			array(
				self::$top[1],
				self::$children[1][1],
			)
		);

		$expected = sprintf(
			'<span aria-hidden="true">&#8212;</span> ' .
			'<a class="row-title" href="%1$s" aria-describedby="post-hierarchy-%2$d">Child 1</a>' .
			'<span id="post-hierarchy-%2$d" class="hidden">Child of Top Level Page 1</span>',
			get_edit_post_link( self::$children[1][1]->ID ),
			self::$children[1][1]->ID
		);

		$this->assertStringContainsString( $expected, $output );
	}

	/**
	 * @ticket 64932
	 *
	 * @covers WP_Posts_List_Table::display_rows
	 * @covers WP_Posts_List_Table::set_hierarchical_display
	 * @covers WP_Posts_List_Table::column_title
	 */
	public function test_top_level_page_row_title_does_not_have_hierarchy_description() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = $this->get_hierarchical_page_list_output(
			array(
				self::$top[1],
			)
		);

		$this->assertStringContainsString(
			sprintf(
				'<a class="row-title" href="%s">Top Level Page 1</a>',
				get_edit_post_link( self::$top[1]->ID )
			),
			$output
		);
		$this->assertStringNotContainsString( 'aria-describedby="post-hierarchy-', $output );
		$this->assertStringNotContainsString( 'class="hidden">Child of ', $output );
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
	 * Gets the output for a hierarchical page list table.
	 *
	 * @param WP_Post[] $posts Posts to display.
	 * @return string List table rows output.
	 */
	protected function get_hierarchical_page_list_output( array $posts ) {
		$_REQUEST['paged']   = 1;
		$GLOBALS['per_page'] = 20;

		ob_start();
		$this->table->set_hierarchical_display( true );
		$this->table->display_rows( $posts );
		$output = ob_get_clean();

		unset( $_REQUEST['paged'] );
		unset( $GLOBALS['per_page'] );

		return $output;
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
	 * Renders the title column for a post in a given list view mode.
	 *
	 * @param WP_Post $post       The post to render.
	 * @param string  $mode_value The list view mode ('list' or 'excerpt').
	 * @return string The rendered output.
	 */
	protected function render_column_title( $post, $mode_value ) {
		global $mode;
		$mode = $mode_value;

		$table = _get_list_table( 'WP_Posts_List_Table', array( 'screen' => 'edit-post' ) );
		$table->set_hierarchical_display( false );

		ob_start();
		$table->column_title( $post );
		return ob_get_clean();
	}

	/**
	 * @ticket 65022
	 *
	 * @dataProvider data_no_title_excerpt_visibility
	 *
	 * @covers WP_Posts_List_Table::column_title
	 * @covers WP_Posts_List_Table::get_no_title_excerpt
	 *
	 * @param string $mode_value  The list view mode ('list' or 'excerpt').
	 * @param array  $post_args   Overrides for the post to create.
	 * @param bool   $should_show Whether the trimmed excerpt should be shown.
	 */
	public function test_no_title_excerpt_visibility( $mode_value, $post_args, $should_show ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$post = self::factory()->post->create_and_get(
			array_merge(
				array(
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => '',
					'post_excerpt' => 'Alpha beta gamma delta epsilon.',
				),
				$post_args
			)
		);

		$output = $this->render_column_title( $post, $mode_value );

		if ( $should_show ) {
			$this->assertStringContainsString( 'class="trimmed-post-excerpt"', $output );
		} else {
			$this->assertStringNotContainsString( 'class="trimmed-post-excerpt"', $output );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_no_title_excerpt_visibility() {
		return array(
			'compact view, untitled'  => array( 'list', array(), true ),
			'extended view, untitled' => array( 'excerpt', array(), false ),
			'compact view, has title' => array( 'list', array( 'post_title' => 'A real title' ), false ),
			'compact view, password'  => array( 'list', array( 'post_password' => 'secret' ), false ),
		);
	}

	/**
	 * @ticket 65022
	 *
	 * @covers WP_Posts_List_Table::column_title
	 * @covers WP_Posts_List_Table::get_no_title_excerpt
	 */
	public function test_no_title_excerpt_is_trimmed_to_15_words() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$words = array();
		for ( $n = 1; $n <= 20; $n++ ) {
			$words[] = 'word' . $n;
		}

		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => '',
				'post_excerpt' => implode( ' ', $words ),
				'post_status'  => 'publish',
			)
		);

		$output = $this->render_column_title( $post, 'list' );

		$this->assertStringContainsString( 'word15', $output, 'The 15th word should be present.' );
		$this->assertStringNotContainsString( 'word16', $output, 'The 16th word should be trimmed.' );
		$this->assertStringContainsString( '&hellip;', $output, 'The excerpt should end with an ellipsis.' );
	}

	/**
	 * Ensures excerpt output is escaped, including markup reintroduced by the `wp_trim_words` filter.
	 *
	 * @ticket 65022
	 *
	 * @covers WP_Posts_List_Table::column_title
	 * @covers WP_Posts_List_Table::get_no_title_excerpt
	 */
	public function test_no_title_excerpt_is_escaped() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => '',
				'post_excerpt' => 'Some excerpt text.',
				'post_status'  => 'publish',
			)
		);

		add_filter( 'wp_trim_words', array( $this, 'filter_wp_trim_words_return_markup' ) );
		$output = $this->render_column_title( $post, 'list' );
		remove_filter( 'wp_trim_words', array( $this, 'filter_wp_trim_words_return_markup' ) );

		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $output, 'The markup should be escaped.' );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $output, 'Raw markup should not be output.' );
	}

	/**
	 * Filter callback returning markup for the `wp_trim_words` filter.
	 *
	 * @return string
	 */
	public function filter_wp_trim_words_return_markup() {
		return '<script>alert(1)</script>';
	}

	/**
	 * @ticket 65022
	 *
	 * @covers WP_Posts_List_Table::get_no_title_excerpt
	 */
	public function test_no_title_excerpt_hidden_when_user_cannot_read_post() {
		$author = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$post = self::factory()->post->create_and_get(
			array(
				'post_author'  => $author,
				'post_title'   => '',
				'post_excerpt' => 'Private draft excerpt.',
				'post_status'  => 'draft',
			)
		);

		// A subscriber cannot read another user's draft.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$output = $this->render_column_title( $post, 'list' );

		$this->assertStringNotContainsString( 'class="trimmed-post-excerpt"', $output );
	}

	/**
	 * @ticket 65022
	 *
	 * @covers WP_Posts_List_Table::column_cb
	 * @covers WP_Posts_List_Table::get_no_title_excerpt
	 */
	public function test_checkbox_label_includes_no_title_excerpt() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		global $mode;
		$mode = 'list';

		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => '',
				'post_excerpt' => 'Hello world example excerpt.',
				'post_status'  => 'publish',
			)
		);

		$GLOBALS['post'] = $post;

		$table = _get_list_table( 'WP_Posts_List_Table', array( 'screen' => 'edit-post' ) );

		ob_start();
		$table->column_cb( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Select (no title) Hello world example excerpt.', $output );
	}

	/**
	 * Tests that `WP_Posts_List_Table::handle_row_actions()` strips HTML from the
	 * post title used within the row action `aria-label` attributes.
	 *
	 * The title is escaped by `_draft_or_post_title()`, so any HTML it contains
	 * survives `esc_attr()` and is announced as literal text by screen readers.
	 *
	 * @ticket 65729
	 *
	 * @covers WP_Posts_List_Table::handle_row_actions
	 */
	public function test_handle_row_actions_should_strip_html_from_aria_labels() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$post = self::factory()->post->create_and_get(
			array(
				'post_title'  => 'my<div>post',
				'post_status' => 'publish',
			)
		);

		$GLOBALS['post'] = $post;

		$table = _get_list_table( 'WP_Posts_List_Table', array( 'screen' => 'edit-post' ) );

		$handle_row_actions = new ReflectionMethod( $table, 'handle_row_actions' );
		if ( PHP_VERSION_ID < 80100 ) {
			$handle_row_actions->setAccessible( true );
		}
		$output = $handle_row_actions->invoke( $table, $post, 'title', 'title' );
		if ( PHP_VERSION_ID < 80100 ) {
			$handle_row_actions->setAccessible( false );
		}

		unset( $GLOBALS['post'] );

		$this->assertStringNotContainsString( '&lt;div&gt;', $output, 'The escaped HTML was not stripped from the title.' );
		$this->assertStringContainsString( 'aria-label="Edit &#8220;mypost&#8221;"', $output, 'The "edit" aria-label did not contain the stripped title.' );
	}
}
