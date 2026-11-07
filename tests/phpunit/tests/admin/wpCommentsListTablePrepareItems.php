<?php

/**
 * Tests for WP_Comments_List_Table::prepare_items() and the small methods of the table.
 *
 * @group admin
 * @group comments
 */
class Tests_Admin_wpCommentsListTablePrepareItems extends WP_UnitTestCase {

	/**
	 * The list table.
	 *
	 * @var WP_Comments_List_Table
	 */
	private $table;

	/**
	 * ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * ID of a post.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * ID of a page.
	 *
	 * @var int
	 */
	private static $page_id;

	/**
	 * The comments of the fixtures, by name.
	 *
	 * @var int[]
	 */
	private static $comments = array();

	/**
	 * The original $_REQUEST.
	 *
	 * @var array
	 */
	private $original_request;

	/**
	 * The original values of the globals of the table.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * Creates the users, the posts and the comments.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$post_id  = $factory->post->create( array( 'post_title' => 'The post' ) );
		self::$page_id  = $factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'The page',
			)
		);

		$comments = array(
			'approved' => array(
				'comment_post_ID'  => self::$post_id,
				'comment_author'   => 'Charlie',
				'comment_content'  => 'An approved needle comment',
				'comment_approved' => '1',
				'comment_date_gmt' => '2020-03-01 10:00:00',
			),
			'pending'  => array(
				'comment_post_ID'  => self::$post_id,
				'comment_author'   => 'Alice',
				'comment_content'  => 'A pending comment',
				'comment_approved' => '0',
				'comment_date_gmt' => '2020-03-02 10:00:00',
			),
			'spam'     => array(
				'comment_post_ID'  => self::$post_id,
				'comment_author'   => 'Bob',
				'comment_content'  => 'A spam comment',
				'comment_approved' => 'spam',
				'comment_date_gmt' => '2020-03-03 10:00:00',
			),
			'trash'    => array(
				'comment_post_ID'  => self::$post_id,
				'comment_author'   => 'Dave',
				'comment_content'  => 'A trashed comment',
				'comment_approved' => 'trash',
				'comment_date_gmt' => '2020-03-04 10:00:00',
			),
			'page'     => array(
				'comment_post_ID'  => self::$page_id,
				'comment_author'   => 'Erin',
				'comment_content'  => 'A comment on a page',
				'comment_approved' => '1',
				'comment_date_gmt' => '2020-03-05 10:00:00',
			),
			'pingback' => array(
				'comment_post_ID'  => self::$post_id,
				'comment_author'   => 'Frank',
				'comment_content'  => 'A pingback',
				'comment_type'     => 'pingback',
				'comment_approved' => '1',
				'comment_date_gmt' => '2020-03-06 10:00:00',
			),
			'note'     => array(
				'comment_post_ID'  => self::$post_id,
				'comment_author'   => 'Grace',
				'comment_content'  => 'A note',
				'comment_type'     => 'note',
				'comment_approved' => '1',
				'comment_date_gmt' => '2020-03-07 10:00:00',
			),
			'by_admin' => array(
				'comment_post_ID'  => self::$post_id,
				'comment_author'   => 'Heidi',
				'comment_content'  => 'A comment of the administrator',
				'comment_approved' => '1',
				'user_id'          => self::$admin_id,
				'comment_date_gmt' => '2020-03-08 10:00:00',
			),
		);

		foreach ( $comments as $name => $args ) {
			self::$comments[ $name ] = $factory->comment->create( $args );
		}
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );

		$this->original_request = $_REQUEST;

		foreach ( array( 'mode', 'post_id', 'comment_status', 'comment_type', 'search' ) as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$this->original_globals[ $name ] = $GLOBALS[ $name ];
			}
		}

		$_REQUEST = array();

		$this->table = _get_list_table( 'WP_Comments_List_Table', array( 'screen' => 'edit-comments' ) );
	}

	public function tear_down() {
		$_REQUEST = $this->original_request;

		foreach ( array( 'mode', 'post_id', 'comment_status', 'comment_type', 'search' ) as $name ) {
			if ( array_key_exists( $name, $this->original_globals ) ) {
				$GLOBALS[ $name ] = $this->original_globals[ $name ];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Returns the author names of the items of the table, in the order of the table.
	 *
	 * @return string[] The names.
	 */
	private function get_item_authors() {
		return wp_list_pluck( $this->table->items, 'comment_author' );
	}

	/**
	 * Tests the comments that are listed by default.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_list_the_approved_and_pending_comments_by_default() {
		$this->table->prepare_items();

		$authors = $this->get_item_authors();

		sort( $authors );

		$this->assertSame( array( 'Alice', 'Charlie', 'Erin', 'Frank', 'Heidi' ), $authors, 'Spam, trash and notes should not be listed.' );
		$this->assertSame( 5, $this->table->get_pagination_arg( 'total_items' ) );
		$this->assertSame( 'all', $GLOBALS['comment_status'] );
		$this->assertSame( '', $GLOBALS['comment_type'] );
		$this->assertSame( '', $GLOBALS['search'] );
	}

	/**
	 * Tests the comments of each status.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 *
	 * @dataProvider data_statuses
	 *
	 * @param string   $status   The comment status.
	 * @param string[] $expected The authors of the expected comments.
	 */
	public function test_should_list_the_comments_of_the_status( $status, $expected ) {
		$_REQUEST['comment_status'] = $status;

		$this->table->prepare_items();

		$authors = $this->get_item_authors();

		sort( $authors );

		$this->assertSame( $expected, $authors );
		$this->assertSame( $status, $GLOBALS['comment_status'] );
		$this->assertSame( count( $expected ), $this->table->get_pagination_arg( 'total_items' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_statuses() {
		return array(
			'pending'  => array( 'moderated', array( 'Alice' ) ),
			'approved' => array( 'approved', array( 'Charlie', 'Erin', 'Frank', 'Heidi' ) ),
			'spam'     => array( 'spam', array( 'Bob' ) ),
			'trash'    => array( 'trash', array( 'Dave' ) ),
		);
	}

	/**
	 * Tests that a status that is not known is the status of all the comments.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_use_all_the_comments_for_an_unknown_status() {
		$_REQUEST['comment_status'] = 'unknown';

		$this->table->prepare_items();

		$this->assertSame( 'all', $GLOBALS['comment_status'] );
		$this->assertCount( 5, $this->table->items );
	}

	/**
	 * Tests the comments of the user.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_list_the_comments_of_the_user() {
		$_REQUEST['comment_status'] = 'mine';
		$_REQUEST['user_id']        = self::$admin_id;

		$this->table->prepare_items();

		$this->assertSame( array( 'Heidi' ), $this->get_item_authors() );
		$this->assertSame( 'mine', $GLOBALS['comment_status'] );
	}

	/**
	 * Tests the type of the comments.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_list_the_comments_of_the_type() {
		$_REQUEST['comment_type'] = 'pingback';

		$this->table->prepare_items();

		$this->assertSame( array( 'Frank' ), $this->get_item_authors() );
		$this->assertSame( 'pingback', $GLOBALS['comment_type'] );
	}

	/**
	 * Tests that the types of the notes and the reactions are not used.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 *
	 * @dataProvider data_hidden_types
	 *
	 * @param string $type The comment type.
	 */
	public function test_should_not_list_the_notes_and_the_reactions( $type ) {
		$_REQUEST['comment_type'] = $type;

		$this->table->prepare_items();

		$this->assertSame( '', $GLOBALS['comment_type'] );
		$this->assertNotContains( 'Grace', $this->get_item_authors() );
		$this->assertCount( 5, $this->table->items );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_hidden_types() {
		return array(
			'note'     => array( 'note' ),
			'reaction' => array( 'reaction' ),
		);
	}

	/**
	 * Tests the search.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_search_the_comments() {
		$_REQUEST['s'] = 'needle';

		$this->table->prepare_items();

		$this->assertSame( array( 'Charlie' ), $this->get_item_authors() );
		$this->assertSame( 'needle', $GLOBALS['search'] );
	}

	/**
	 * Tests the post type.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_list_the_comments_of_the_post_type() {
		$_REQUEST['post_type'] = 'page';

		$this->table->prepare_items();

		$this->assertSame( array( 'Erin' ), $this->get_item_authors() );
	}

	/**
	 * Tests the order.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_order_the_comments() {
		$_REQUEST['orderby'] = 'comment_author';
		$_REQUEST['order']   = 'asc';

		$this->table->prepare_items();

		$this->assertSame( array( 'Alice', 'Charlie', 'Erin', 'Frank', 'Heidi' ), $this->get_item_authors() );

		$_REQUEST['order'] = 'desc';

		$this->table->prepare_items();

		$this->assertSame( array( 'Heidi', 'Frank', 'Erin', 'Charlie', 'Alice' ), $this->get_item_authors() );
	}

	/**
	 * Tests the comments of the post of the request.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::__construct
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_list_the_comments_of_the_post_of_the_request() {
		$_REQUEST['p'] = self::$page_id;

		$table = _get_list_table( 'WP_Comments_List_Table', array( 'screen' => 'edit-comments' ) );
		$table->prepare_items();

		$this->assertSame( self::$page_id, $GLOBALS['post_id'] );
		$this->assertSame( array( 'Erin' ), wp_list_pluck( $table->items, 'comment_author' ) );
	}

	/**
	 * Tests the pending comments of the posts of the comments.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_count_the_pending_comments_of_the_posts() {
		$this->table->prepare_items();

		$this->assertSame( 1, $this->table->pending_count[ self::$post_id ] );
		$this->assertSame( 0, $this->table->pending_count[ self::$page_id ] );
	}

	/**
	 * Tests the number of comments per page, the extra comments, and the pages.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_paginate_the_comments() {
		update_user_option( self::$admin_id, 'edit_comments_per_page', 2 );

		$_REQUEST['orderby'] = 'comment_author';
		$_REQUEST['order']   = 'asc';

		$this->table->prepare_items();

		$this->assertSame( array( 'Alice', 'Charlie' ), $this->get_item_authors() );
		$this->assertSame( array( 'Erin', 'Frank' ), wp_list_pluck( $this->table->extra_items, 'comment_author' ), 'A few extra comments are loaded.' );
		$this->assertSame( 5, $this->table->get_pagination_arg( 'total_items' ) );
		$this->assertSame( 3, $this->table->get_pagination_arg( 'total_pages' ) );
		$this->assertSame( 2, $this->table->get_pagination_arg( 'per_page' ) );
	}

	/**
	 * Tests the second page.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_list_the_comments_of_the_second_page() {
		update_user_option( self::$admin_id, 'edit_comments_per_page', 2 );

		$_REQUEST['orderby'] = 'comment_author';
		$_REQUEST['order']   = 'asc';
		$_REQUEST['paged']   = 2;

		$this->table->prepare_items();

		$this->assertSame( array( 'Erin', 'Frank' ), $this->get_item_authors() );
		$this->assertSame( array( 'Heidi' ), wp_list_pluck( $this->table->extra_items, 'comment_author' ) );
	}

	/**
	 * Tests the number and the start of the request.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_use_the_number_and_the_start_of_the_request() {
		update_user_option( self::$admin_id, 'edit_comments_per_page', 2 );

		$_REQUEST['orderby'] = 'comment_author';
		$_REQUEST['order']   = 'asc';
		$_REQUEST['number']  = 3;
		$_REQUEST['start']   = 1;

		$this->table->prepare_items();

		$this->assertSame( array( 'Charlie', 'Erin' ), $this->get_item_authors() );
		$this->assertSame( array( 'Frank' ), wp_list_pluck( $this->table->extra_items, 'comment_author' ) );
	}

	/**
	 * Tests the offset of an AJAX request.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_add_the_offset_of_an_ajax_request_to_the_start() {
		update_user_option( self::$admin_id, 'edit_comments_per_page', 2 );
		add_filter( 'wp_doing_ajax', '__return_true' );

		$_REQUEST['orderby'] = 'comment_author';
		$_REQUEST['order']   = 'asc';
		$_REQUEST['offset']  = 1;

		$this->table->prepare_items();

		$this->assertSame( array( 'Charlie', 'Erin' ), $this->get_item_authors() );
	}

	/**
	 * Tests that the offset is only used in an AJAX request.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_ignore_the_offset_when_it_is_not_an_ajax_request() {
		update_user_option( self::$admin_id, 'edit_comments_per_page', 2 );

		$_REQUEST['orderby'] = 'comment_author';
		$_REQUEST['order']   = 'asc';
		$_REQUEST['offset']  = 1;

		$this->table->prepare_items();

		$this->assertSame( array( 'Alice', 'Charlie' ), $this->get_item_authors() );
	}

	/**
	 * Tests the filter of the query arguments.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_filter_the_query_arguments() {
		$received = null;

		add_filter(
			'comments_list_table_query_args',
			static function ( $args ) use ( &$received ) {
				$received = $args;

				$args['search'] = 'needle';

				return $args;
			}
		);

		$_REQUEST['comment_status'] = 'approved';
		$_REQUEST['s']              = 'ignored';
		$_REQUEST['post_type']      = 'post';

		$this->table->prepare_items();

		$this->assertSame( 'approve', $received['status'] );
		$this->assertSame( 'ignored', $received['search'] );
		$this->assertSame( 'post', $received['post_type'] );
		$this->assertSame( 0, $received['offset'] );
		$this->assertTrue( $received['update_comment_post_cache'] );
		$this->assertSame( array( 'Charlie' ), $this->get_item_authors() );
		$this->assertSame( 1, $this->table->get_pagination_arg( 'total_items' ), 'The total should use the filtered arguments.' );
	}

	/**
	 * Tests the mode of the table.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 *
	 * @dataProvider data_modes
	 *
	 * @param string $requested The mode of the request.
	 * @param string $expected  The expected mode.
	 */
	public function test_should_set_the_mode_of_the_request( $requested, $expected ) {
		$_REQUEST['mode'] = $requested;

		$this->table->prepare_items();

		$this->assertSame( $expected, $GLOBALS['mode'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_modes() {
		return array(
			'excerpt'        => array( 'excerpt', 'excerpt' ),
			'list'           => array( 'list', 'list' ),
			'something else' => array( 'something', 'list' ),
		);
	}

	/**
	 * Tests the default mode.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::prepare_items
	 */
	public function test_should_use_the_list_mode_by_default() {
		$this->table->prepare_items();

		$this->assertSame( 'list', $GLOBALS['mode'] );
	}

	/**
	 * Tests the number of comments per page.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::get_per_page
	 */
	public function test_get_per_page() {
		$this->assertSame( 20, $this->table->get_per_page() );

		update_user_option( self::$admin_id, 'edit_comments_per_page', 7 );

		$this->assertSame( 7, $this->table->get_per_page() );
	}

	/**
	 * Tests the filter of the number of comments per page.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::get_per_page
	 */
	public function test_get_per_page_is_filtered_with_the_status() {
		$received = null;

		add_filter(
			'comments_per_page',
			static function ( $per_page, $status ) use ( &$received ) {
				$received = array( $per_page, $status );

				return 3;
			},
			10,
			2
		);

		$this->assertSame( 3, $this->table->get_per_page( 'spam' ) );
		$this->assertSame( array( 20, 'spam' ), $received );
		$this->assertSame( 3, $this->table->get_per_page() );
		$this->assertSame( array( 20, 'all' ), $received );
	}

	/**
	 * Tests the post of the request in the constructor.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::__construct
	 *
	 * @dataProvider data_posts_of_the_request
	 *
	 * @param mixed $requested The post of the request.
	 * @param int   $expected  The expected post ID.
	 */
	public function test_constructor_sets_the_post_of_the_request( $requested, $expected ) {
		if ( null !== $requested ) {
			$_REQUEST['p'] = $requested;
		}

		_get_list_table( 'WP_Comments_List_Table', array( 'screen' => 'edit-comments' ) );

		$this->assertSame( $expected, $GLOBALS['post_id'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_posts_of_the_request() {
		return array(
			'no post'  => array( null, 0 ),
			'a post'   => array( '12', 12 ),
			'negative' => array( '-5', 5 ),
			'text'     => array( 'text', 0 ),
		);
	}

	/**
	 * Tests the avatar filter of the constructor.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::__construct
	 */
	public function test_constructor_adds_the_avatar_filter_when_avatars_are_shown() {
		update_option( 'show_avatars', 1 );

		remove_all_filters( 'comment_author' );
		$table = new WP_Comments_List_Table( array( 'screen' => 'edit-comments' ) );

		$this->assertSame( 10, has_filter( 'comment_author', array( $table, 'floated_admin_avatar' ) ) );

		update_option( 'show_avatars', 0 );

		remove_all_filters( 'comment_author' );
		$table = new WP_Comments_List_Table( array( 'screen' => 'edit-comments' ) );

		$this->assertFalse( has_filter( 'comment_author', array( $table, 'floated_admin_avatar' ) ) );
	}

	/**
	 * Tests the table that is made for the comments.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::__construct
	 */
	public function test_constructor_sets_the_arguments_of_the_table() {
		$this->assertSame( 'comments', $this->table->_args['plural'] );
		$this->assertSame( 'comment', $this->table->_args['singular'] );
		$this->assertTrue( $this->table->_args['ajax'] );
	}

	/**
	 * Tests the avatar before the name of the author.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::floated_admin_avatar
	 */
	public function test_floated_admin_avatar() {
		update_option( 'show_avatars', 1 );

		$comment_id = self::$comments['approved'];

		$output = $this->table->floated_admin_avatar( 'Charlie', $comment_id );

		$this->assertStringStartsWith( '<img ', $output );
		$this->assertStringEndsWith( ' Charlie', $output );
		$this->assertStringContainsString( "width='32'", $output );
	}

	/**
	 * Tests the name of the author without an avatar.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::floated_admin_avatar
	 */
	public function test_floated_admin_avatar_without_avatars() {
		update_option( 'show_avatars', 0 );

		$this->assertSame( ' Charlie', $this->table->floated_admin_avatar( 'Charlie', self::$comments['approved'] ) );
	}

	/**
	 * Tests who can use the table in AJAX requests.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::ajax_user_can
	 */
	public function test_ajax_user_can() {
		$this->assertTrue( $this->table->ajax_user_can() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse( $this->table->ajax_user_can() );
	}

	/**
	 * Tests the message when there are no comments.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::no_items
	 *
	 * @dataProvider data_no_items
	 *
	 * @param string $status   The comment status.
	 * @param string $expected The expected message.
	 */
	public function test_no_items( $status, $expected ) {
		$GLOBALS['comment_status'] = $status;

		ob_start();
		$this->table->no_items();

		$this->assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_items() {
		return array(
			'pending'  => array( 'moderated', 'No comments awaiting moderation.' ),
			'trash'    => array( 'trash', 'No comments found in Trash.' ),
			'all'      => array( 'all', 'No comments found.' ),
			'approved' => array( 'approved', 'No comments found.' ),
			'spam'     => array( 'spam', 'No comments found.' ),
		);
	}

	/**
	 * Tests the current action for the button that deletes all the comments.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::current_action
	 *
	 * @dataProvider data_delete_all_parameters
	 *
	 * @param string $parameter The parameter of the request.
	 */
	public function test_current_action_for_delete_all( $parameter ) {
		$_REQUEST[ $parameter ] = '1';

		$this->assertSame( 'delete_all', $this->table->current_action() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_delete_all_parameters() {
		return array(
			'top button'    => array( 'delete_all' ),
			'bottom button' => array( 'delete_all2' ),
		);
	}

	/**
	 * Tests the current action of a bulk action.
	 *
	 * @ticket 66602
	 *
	 * @covers WP_Comments_List_Table::current_action
	 */
	public function test_current_action_for_a_bulk_action() {
		$_REQUEST['action'] = 'approve';

		$this->assertSame( 'approve', $this->table->current_action() );

		$_REQUEST['action']     = '-1';
		$_REQUEST['delete_all'] = '1';

		$this->assertSame( 'delete_all', $this->table->current_action(), 'The delete all button should win.' );
	}
}
