<?php

/**
 * Tests for get_private_posts_cap_sql().
 *
 * @group post
 *
 * @covers ::get_private_posts_cap_sql
 */
class Tests_Post_GetPrivatePostsCapSql extends WP_UnitTestCase {

	/**
	 * Tests that only the published posts are included for a logged-out visitor.
	 *
	 * @ticket 66495
	 */
	public function test_should_only_include_published_posts_for_visitors() {
		wp_set_current_user( 0 );

		$this->assertSame(
			"( ( post_type = 'post' AND ( post_status = 'publish' ) ) )",
			get_private_posts_cap_sql( 'post' )
		);
	}

	/**
	 * Tests that users who can read private posts get all of them.
	 *
	 * @ticket 66495
	 */
	public function test_should_include_all_private_posts_for_users_who_can_read_them() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame(
			"( ( post_type = 'post' AND ( post_status = 'publish' OR post_status = 'private' ) ) )",
			get_private_posts_cap_sql( 'post' )
		);
	}

	/**
	 * Tests that other users get their own private posts.
	 *
	 * @ticket 66495
	 */
	public function test_should_include_the_own_private_posts_of_other_users() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertSame(
			"( ( post_type = 'post' AND ( post_status = 'publish' OR post_status = 'private' AND post_author = $user_id ) ) )",
			get_private_posts_cap_sql( 'post' )
		);
	}

	/**
	 * Tests that the capability of the post type is used.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_the_capability_of_the_post_type() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		// Editors can read private posts and pages, but the capability of this post type is one that they do not have.
		register_post_type(
			'foo-private',
			array(
				'capability_type' => 'foo_private',
				'map_meta_cap'    => true,
			)
		);

		$result = get_private_posts_cap_sql( 'foo-private' );

		unregister_post_type( 'foo-private' );

		$this->assertStringNotContainsString( "OR post_status = 'private' )", $result, 'Editors cannot read all private posts of this post type.' );
		$this->assertStringContainsString( 'AND post_author =', $result, 'Editors can only read their own private posts of this post type.' );
	}

	/**
	 * Tests that a list of post types is supported.
	 *
	 * @ticket 66495
	 */
	public function test_should_support_a_list_of_post_types() {
		wp_set_current_user( 0 );

		$this->assertSame(
			"( ( post_type = 'post' AND ( post_status = 'publish' ) ) OR ( post_type = 'page' AND ( post_status = 'publish' ) ) )",
			get_private_posts_cap_sql( array( 'post', 'page' ) )
		);
	}

	/**
	 * Tests that the condition matches nothing when the post type does not exist.
	 *
	 * @ticket 66495
	 */
	public function test_should_match_nothing_for_a_post_type_that_does_not_exist() {
		$this->assertSame( '1 = 0', get_private_posts_cap_sql( 'a-post-type-that-does-not-exist' ) );
	}

	/**
	 * Tests that the condition is not prefixed with WHERE, and is the same as the one without the full query.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_roles
	 *
	 * @param string $role Role of the user. An empty string is a logged-out visitor.
	 */
	public function test_should_return_the_same_as_get_posts_by_author_sql_without_the_full_query( $role ) {
		wp_set_current_user( '' === $role ? 0 : self::factory()->user->create( array( 'role' => $role ) ) );

		$result = get_private_posts_cap_sql( 'post' );

		$this->assertSame( get_posts_by_author_sql( 'post', false ), $result );
		$this->assertStringStartsNotWith( 'WHERE', $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_roles() {
		return array(
			'visitor'       => array( '' ),
			'subscriber'    => array( 'subscriber' ),
			'editor'        => array( 'editor' ),
			'administrator' => array( 'administrator' ),
		);
	}
}
