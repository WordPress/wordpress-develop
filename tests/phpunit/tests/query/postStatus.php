<?php

/**
 * @group query
 */
class Tests_Query_PostStatus extends WP_UnitTestCase {
	public static $editor_user_id;
	public static $author_user_id;
	public static $subscriber_user_id;
	public static $post_ids;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_user_id     = $factory->user->create( array( 'role' => 'editor' ) );
		self::$author_user_id     = $factory->user->create( array( 'role' => 'author' ) );
		self::$subscriber_user_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		self::$post_ids['editor_private_post'] = $factory->post->create(
			array(
				'post_author' => self::$editor_user_id,
				'post_status' => 'private',
			)
		);
		self::$post_ids['author_private_post'] = $factory->post->create(
			array(
				'post_author' => self::$author_user_id,
				'post_status' => 'private',
			)
		);

		// Custom status with private=true.
		register_post_status( 'privatefoo', array( 'private' => true ) );
		self::$post_ids['editor_privatefoo_post'] = $factory->post->create(
			array(
				'post_author' => self::$editor_user_id,
				'post_status' => 'privatefoo',
			)
		);
		self::$post_ids['author_privatefoo_post'] = $factory->post->create(
			array(
				'post_author' => self::$author_user_id,
				'post_status' => 'privatefoo',
			)
		);
		_unregister_post_status( 'privatefoo' );

		self::register_custom_post_objects();

		self::$post_ids['wptests_pt1_p1'] = $factory->post->create(
			array(
				'post_type'   => 'wptests_pt1',
				'post_status' => 'private',
				'post_author' => self::$editor_user_id,
			)
		);

		self::$post_ids['wptests_pt1_p2'] = $factory->post->create(
			array(
				'post_type'   => 'wptests_pt1',
				'post_status' => 'publish',
				'post_author' => self::$editor_user_id,
			)
		);

		self::$post_ids['wptests_pt2_p1'] = $factory->post->create(
			array(
				'post_type'   => 'wptests_pt2',
				'post_status' => 'private',
				'post_author' => self::$editor_user_id,
			)
		);

		self::$post_ids['wptests_pt2_p2'] = $factory->post->create(
			array(
				'post_type'   => 'wptests_pt2',
				'post_status' => 'publish',
				'post_author' => self::$editor_user_id,
			)
		);
	}

	public function setUp() {
		parent::setUp();
		self::register_custom_post_objects();
	}

	/**
	 * Register custom post types and statuses used in multiple tests.
	 *
	 * CPTs and CPSs are reset between each test run so need to be registered
	 * in both the wpSetUpBeforeClass() and setUp() methods.
	 */
	public static function register_custom_post_objects() {
		register_post_type(
			'wptests_pt1',
			array(
				'exclude_from_search' => false,
				'capabilities'        => array(
					'read_private_posts' => 'read_private_pt1s',
				),
			)
		);

		register_post_type(
			'wptests_pt2',
			array(
				'exclude_from_search' => false,
			)
		);
	}

	public function test_any_should_not_include_statuses_where_exclude_from_search_is_true() {
		register_post_status( 'foo', array( 'exclude_from_search' => true ) );

		$q = new WP_Query(
			array(
				'post_status' => array( 'any' ),
			)
		);

		$this->assertContains( "post_status <> 'foo'", $q->request );
	}

	public function test_any_should_include_statuses_where_exclude_from_search_is_false() {
		register_post_status( 'foo', array( 'exclude_from_search' => false ) );

		$q = new WP_Query(
			array(
				'post_status' => array( 'any' ),
			)
		);

		$this->assertNotContains( "post_status <> 'foo'", $q->request );
	}

	public function test_private_should_be_included_if_perm_is_false() {
		$q = new WP_Query(
			array(
				'post_status' => array( 'private' ),
				'perm'        => false,
			)
		);

		$expected = array(
			self::$post_ids['editor_private_post'],
			self::$post_ids['author_private_post'],
		);

		$this->assertSameSets( $expected, wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function test_private_should_not_be_included_for_non_author_if_perm_is_not_false() {
		// Current user is 0.

		$q = new WP_Query(
			array(
				'post_status' => array( 'private' ),
				'perm'        => 'editable',
			)
		);

		$this->assertEmpty( $q->posts );
	}

	public function test_private_should_be_included_only_for_current_user_if_perm_is_readable_and_user_cannot_read_others_posts() {
		wp_set_current_user( self::$author_user_id );

		$q = new WP_Query(
			array(
				'post_status' => array( 'private' ),
				'perm'        => 'readable',
			)
		);

		$expected = array(
			self::$post_ids['author_private_post'],
		);

		$this->assertSameSets( $expected, wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function test_private_should_be_included_for_all_users_if_perm_is_readable_and_user_can_read_others_posts() {
		wp_set_current_user( self::$editor_user_id );

		$q = new WP_Query(
			array(
				'post_status' => array( 'private' ),
				'perm'        => 'readable',
			)
		);

		$expected = array(
			self::$post_ids['author_private_post'],
			self::$post_ids['editor_private_post'],
		);

		$this->assertSameSets( $expected, wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function test_private_should_be_included_only_for_current_user_if_perm_is_editable_and_user_cannot_read_others_posts() {
		wp_set_current_user( self::$author_user_id );

		$q = new WP_Query(
			array(
				'post_status' => array( 'private' ),
				'perm'        => 'editable',
			)
		);

		$expected = array(
			self::$post_ids['author_private_post'],
		);

		$this->assertSameSets( $expected, wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function test_private_should_be_included_for_all_users_if_perm_is_editable_and_user_can_read_others_posts() {
		wp_set_current_user( self::$editor_user_id );

		$q = new WP_Query(
			array(
				'post_status' => array( 'private' ),
				'perm'        => 'editable',
			)
		);

		$expected = array(
			self::$post_ids['author_private_post'],
			self::$post_ids['editor_private_post'],
		);

		$this->assertSameSets( $expected, wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function test_all_public_post_stati_should_be_included_when_no_post_status_is_provided() {
		register_post_status( 'foo', array( 'public' => true ) );

		$q = new WP_Query(
			array(
				'posts_per_page' => 1, // Or the query will short-circuit.
			)
		);

		foreach ( get_post_stati( array( 'public' => true ) ) as $status ) {
			$this->assertContains( "post_status = '$status'", $q->request );
		}
	}

	public function test_protected_should_not_be_included_when_not_in_the_admin() {
		register_post_status( 'foo', array( 'protected' => true ) );

		$q = new WP_Query(
			array(
				'posts_per_page' => 1, // Or the query will short-circuit.
			)
		);

		$this->assertNotContains( "post_status = 'foo", $q->request );
	}

	public function test_protected_should_be_included_when_in_the_admin() {
		set_current_screen( 'dashboard' );
		register_post_status(
			'foo',
			array(
				'protected'              => true,
				'show_in_admin_all_list' => true,
			)
		);

		$q = new WP_Query(
			array(
				'posts_per_page' => -1, // Or the query will short-circuit.
			)
		);

		$this->assertContains( "post_status = 'foo", $q->request );
		set_current_screen( 'front' );
	}

	public function test_private_statuses_should_be_included_when_current_user_can_read_private_posts() {
		wp_set_current_user( self::$editor_user_id );

		register_post_status( 'privatefoo', array( 'private' => true ) );

		$q = new WP_Query(
			array(
				'posts_per_page' => -1,
			)
		);

		$this->assertContains( self::$post_ids['author_privatefoo_post'], wp_list_pluck( $q->posts, 'ID' ) );
		$this->assertContains( self::$post_ids['editor_privatefoo_post'], wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function test_private_statuses_should_not_be_included_when_current_user_cannot_read_private_posts() {
		wp_set_current_user( self::$author_user_id );

		register_post_status( 'privatefoo', array( 'private' => true ) );

		$q = new WP_Query(
			array(
				'posts_per_page' => -1, // Or the query will short-circuit.
			)
		);

		$expected = array(
			self::$post_ids['author_privatefoo_post'],
		);

		$this->assertContains( self::$post_ids['author_privatefoo_post'], wp_list_pluck( $q->posts, 'ID' ) );
		$this->assertNotContains( self::$post_ids['editor_privatefoo_post'], wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function test_single_post_with_nonpublic_status_should_not_be_shown_to_logged_out_users() {
		register_post_type( 'foo_pt' );
		register_post_status( 'foo_ps', array( 'public' => false ) );
		$p = self::factory()->post->create( array( 'post_status' => 'foo_ps' ) );

		$q = new WP_Query(
			array(
				'p' => $p,
			)
		);

		$this->assertEmpty( $q->posts );
	}

	public function test_single_post_with_nonpublic_status_should_not_be_shown_for_any_user() {
		register_post_type( 'foo_pt' );
		register_post_status( 'foo_ps', array( 'public' => false ) );
		$p = self::factory()->post->create(
			array(
				'post_status' => 'foo_ps',
				'post_author' => self::$author_user_id,
			)
		);

		wp_set_current_user( self::$editor_user_id );

		$q = new WP_Query(
			array(
				'p' => $p,
			)
		);

		$this->assertEmpty( $q->posts );
	}

	public function test_single_post_with_nonpublic_and_protected_status_should_not_be_shown_for_user_who_cannot_edit_others_posts() {
		register_post_type( 'foo_pt' );
		register_post_status(
			'foo_ps',
			array(
				'public'    => false,
				'protected' => true,
			)
		);
		$p = self::factory()->post->create(
			array(
				'post_status' => 'foo_ps',
				'post_author' => self::$editor_user_id,
			)
		);

		wp_set_current_user( self::$author_user_id );

		$q = new WP_Query(
			array(
				'p' => $p,
			)
		);

		$this->assertEmpty( $q->posts );
	}

	public function test_single_post_with_nonpublic_and_protected_status_should_be_shown_for_user_who_can_edit_others_posts() {
		register_post_type( 'foo_pt' );
		register_post_status(
			'foo_ps',
			array(
				'public'    => false,
				'protected' => true,
			)
		);
		$p = self::factory()->post->create(
			array(
				'post_status' => 'foo_ps',
				'post_author' => self::$author_user_id,
			)
		);

		wp_set_current_user( self::$editor_user_id );

		$q = new WP_Query(
			array(
				'p' => $p,
			)
		);

		$this->assertSame( array( $p ), wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function test_single_post_with_nonpublic_and_private_status_should_not_be_shown_for_user_who_cannot_edit_others_posts() {
		register_post_type( 'foo_pt' );
		register_post_status(
			'foo_ps',
			array(
				'public'  => false,
				'private' => true,
			)
		);
		$p = self::factory()->post->create(
			array(
				'post_status' => 'foo_ps',
				'post_author' => self::$editor_user_id,
			)
		);

		wp_set_current_user( self::$author_user_id );

		$q = new WP_Query(
			array(
				'p' => $p,
			)
		);

		$this->assertEmpty( $q->posts );
	}

	public function test_single_post_with_nonpublic_and_private_status_should_be_shown_for_user_who_can_edit_others_posts() {
		register_post_type( 'foo_pt' );
		register_post_status(
			'foo_ps',
			array(
				'public'  => false,
				'private' => true,
			)
		);
		$p = self::factory()->post->create(
			array(
				'post_status' => 'foo_ps',
				'post_author' => self::$author_user_id,
			)
		);

		wp_set_current_user( self::$editor_user_id );

		$q = new WP_Query(
			array(
				'p' => $p,
			)
		);

		$this->assertSame( array( $p ), wp_list_pluck( $q->posts, 'ID' ) );
	}

	/**
	 * @ticket 48653
	 */
	public function test_single_post_with_nonexisting_status_should_not_be_shown_for_user_who_cannot_edit_others_posts() {
		register_post_type( 'foo_pt' );
		register_post_status( 'foo_ps', array( 'public' => true ) );
		$p = self::factory()->post->create(
			array(
				'post_status' => 'foo_ps',
				'post_author' => self::$editor_user_id,
			)
		);
		_unregister_post_status( 'foo_ps' );

		wp_set_current_user( self::$author_user_id );

		$q = new WP_Query(
			array(
				'p' => $p,
			)
		);

		$this->assertEmpty( $q->posts );
	}

	/**
	 * @ticket 48653
	 */
	public function test_single_post_with_nonexisting_status_should_be_shown_for_user_who_can_edit_others_posts() {
		register_post_type( 'foo_pt' );
		register_post_status( 'foo_ps', array( 'public' => true ) );
		$p = self::factory()->post->create(
			array(
				'post_status' => 'foo_ps',
				'post_author' => self::$author_user_id,
			)
		);
		_unregister_post_status( 'foo_ps' );

		wp_set_current_user( self::$editor_user_id );

		$q = new WP_Query(
			array(
				'p' => $p,
			)
		);

		$this->assertSame( array( $p ), wp_list_pluck( $q->posts, 'ID' ) );
	}

	/**
	 * @ticket 29167
	 */
	public function test_specific_post_should_be_returned_if_trash_is_one_of_the_requested_post_statuses() {
		$p1 = self::factory()->post->create( array( 'post_status' => 'trash' ) );
		$p2 = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$q = new WP_Query(
			array(
				'p'           => $p1,
				'post_status' => array( 'trash', 'publish' ),
			)
		);

		$this->assertContains( $p1, wp_list_pluck( $q->posts, 'ID' ) );
	}

	/**
	 * @ticket 48556
	 * @ticket 13509
	 */
	public function test_non_singular_queries_using_post_type_any_should_respect_post_type_read_private_posts_cap() {
		$post_ids = self::$post_ids;

		wp_set_current_user( 0 );

		$q = new WP_Query(
			array(
				'post_type' => 'any',
			)
		);

		$this->assertSameSets( array( $post_ids['wptests_pt1_p2'], $post_ids['wptests_pt2_p2'] ), wp_list_pluck( $q->posts, 'ID' ) );

		wp_set_current_user( self::$subscriber_user_id );
		$GLOBALS['current_user']->add_cap( 'read_private_pt1s' );

		$q = new WP_Query(
			array(
				'post_type' => 'any',
			)
		);

		$this->assertSameSets( array( $post_ids['wptests_pt1_p1'], $post_ids['wptests_pt1_p2'], $post_ids['wptests_pt2_p2'] ), wp_list_pluck( $q->posts, 'ID' ) );
	}

	/**
	 * @ticket 48556
	 * @ticket 13509
	 */
	public function test_non_singular_queries_using_multiple_post_type_should_respect_post_type_read_private_posts_cap() {
		wp_set_current_user( 0 );

		$post_ids = self::$post_ids;

		$q = new WP_Query(
			array(
				'post_type'      => 'any',
				'posts_per_page' => -1,
			)
		);

		$this->assertSameSets( array( $post_ids['wptests_pt1_p2'], $post_ids['wptests_pt2_p2'] ), wp_list_pluck( $q->posts, 'ID' ) );

		wp_set_current_user( self::$subscriber_user_id );
		$GLOBALS['current_user']->add_cap( 'read_private_pt1s' );

		$q = new WP_Query(
			array(
				'post_type'      => array( 'wptests_pt1', 'wptests_pt2' ),
				'posts_per_page' => -1,
			)
		);

		$this->assertSameSets( array( $post_ids['wptests_pt1_p1'], $post_ids['wptests_pt1_p2'], $post_ids['wptests_pt2_p2'] ), wp_list_pluck( $q->posts, 'ID' ) );
	}
}
