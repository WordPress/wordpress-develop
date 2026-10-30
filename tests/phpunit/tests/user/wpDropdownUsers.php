<?php

/**
 * Test functions in wp-includes/user.php
 *
 * @group user
 */
class Tests_User_wpDropdownUsers extends WP_UnitTestCase {

	/**
	 * @ticket 31251
	 */
	public function test_default_value_of_show_should_be_display_name() {

		// Create a user with a different display_name.
		$u = self::factory()->user->create(
			array(
				'user_login'   => 'foo',
				'display_name' => 'Foo Person',
			)
		);

		$found = wp_dropdown_users(
			array(
				'echo' => false,
			)
		);

		$expected = "<option value='$u'>Foo Person</option>";

		$this->assertStringContainsString( $expected, $found );
	}

	/**
	 * @ticket 31251
	 */
	public function test_show_should_display_display_name_show_is_specified_as_empty() {

		// Create a user with a different display_name.
		$u = self::factory()->user->create(
			array(
				'user_login'   => 'foo',
				'display_name' => 'Foo Person',
			)
		);

		// Get the result of a non-default, but acceptable input for 'show' parameter to wp_dropdown_users().
		$found = wp_dropdown_users(
			array(
				'echo' => false,
				'show' => '',
			)
		);

		$expected = "<option value='$u'>Foo Person</option>";

		$this->assertStringContainsString( $expected, $found );
	}

	/**
	 * @ticket 31251
	 */
	public function test_show_should_display_user_property_when_the_value_of_show_is_a_valid_user_property() {

		// Create a user with a different display_name.
		$u = self::factory()->user->create(
			array(
				'user_login'   => 'foo',
				'display_name' => 'Foo Person',
			)
		);

		// Get the result of a non-default, but acceptable input for 'show' parameter to wp_dropdown_users().
		$found = wp_dropdown_users(
			array(
				'echo' => false,
				'show' => 'user_login',
			)
		);

		$expected = "<option value='$u'>foo</option>";

		$this->assertStringContainsString( $expected, $found );
	}

	/**
	 * @ticket 31251
	 */
	public function test_show_display_name_with_login() {

		// Create a user with a different display_name.
		$u = self::factory()->user->create(
			array(
				'user_login'   => 'foo',
				'display_name' => 'Foo Person',
			)
		);

		// Get the result of a non-default, but acceptable input for 'show' parameter to wp_dropdown_users().
		$found = wp_dropdown_users(
			array(
				'echo' => false,
				'show' => 'display_name_with_login',
			)
		);

		$expected = "<option value='$u'>Foo Person (foo)</option>";

		$this->assertStringContainsString( $expected, $found );
	}

	/**
	 * @ticket 31251
	 */
	public function test_include_selected() {
		$users = self::factory()->user->create_many( 2 );

		$found = wp_dropdown_users(
			array(
				'echo'             => false,
				'include'          => $users[0],
				'selected'         => $users[1],
				'include_selected' => true,
				'show'             => 'user_login',
			)
		);

		$user1 = get_userdata( $users[1] );
		$this->assertStringContainsString( $user1->user_login, $found );
	}

	/**
	 * @ticket 51370
	 */
	public function test_include_selected_with_non_existing_user_id() {
		$found = wp_dropdown_users(
			array(
				'echo'             => false,
				'selected'         => PHP_INT_MAX,
				'include_selected' => true,
				'show'             => 'user_login',
			)
		);

		$this->assertStringNotContainsString( (string) PHP_INT_MAX, $found );
	}

	/**
	 * @ticket 38135
	 */
	public function test_role() {
		$u1 = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$u2 = self::factory()->user->create_and_get( array( 'role' => 'author' ) );

		$found = wp_dropdown_users(
			array(
				'echo' => false,
				'role' => 'author',
				'show' => 'user_login',
			)
		);

		$this->assertStringNotContainsString( $u1->user_login, $found );
		$this->assertStringContainsString( $u2->user_login, $found );
	}

	/**
	 * @ticket 38135
	 */
	public function test_role__in() {
		$u1 = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$u2 = self::factory()->user->create_and_get( array( 'role' => 'author' ) );

		$found = wp_dropdown_users(
			array(
				'echo'     => false,
				'role__in' => array( 'author', 'editor' ),
				'show'     => 'user_login',
			)
		);

		$this->assertStringNotContainsString( $u1->user_login, $found );
		$this->assertStringContainsString( $u2->user_login, $found );
	}

	/**
	 * @ticket 38135
	 */
	public function test_role__not_in() {
		$u1 = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$u2 = self::factory()->user->create_and_get( array( 'role' => 'author' ) );

		$found = wp_dropdown_users(
			array(
				'echo'         => false,
				'role__not_in' => array( 'subscriber', 'editor' ),
				'show'         => 'user_login',
			)
		);

		$this->assertStringNotContainsString( $u1->user_login, $found );
		$this->assertStringContainsString( $u2->user_login, $found );
	}

	/**
	 * @ticket 39090
	 */
	public function test_repeated_identical_calls_do_not_run_duplicate_queries() {
		self::factory()->user->create_many( 2, array( 'role' => 'author' ) );

		// Prime the cache with an initial call.
		wp_dropdown_users( array( 'echo' => false ) );

		$queries_before = get_num_queries();

		wp_dropdown_users( array( 'echo' => false ) );

		$queries_after = get_num_queries();

		$this->assertSame( $queries_before, $queries_after, 'A second call with identical arguments should be served from cache and not run any additional database queries.' );
	}

	/**
	 * @ticket 39090
	 */
	public function test_cache_is_invalidated_when_a_user_is_created_between_calls() {
		$found_before = wp_dropdown_users(
			array(
				'echo' => false,
				'show' => 'user_login',
			)
		);

		$queries_before = get_num_queries();

		$new_user = self::factory()->user->create_and_get( array( 'user_login' => 'newly-created-user' ) );

		$found_after = wp_dropdown_users(
			array(
				'echo' => false,
				'show' => 'user_login',
			)
		);

		$queries_after = get_num_queries();

		$this->assertGreaterThan( $queries_before, $queries_after, 'Creating a user should invalidate the cache, so the next call should run a new query rather than reusing the stale cached result.' );
		$this->assertStringNotContainsString( $new_user->user_login, $found_before );
		$this->assertStringContainsString( $new_user->user_login, $found_after );
	}

	/**
	 * @ticket 66012
	 * @group ms-required
	 */
	public function test_multisite_users_in_blog_id() {
		$blog_id = self::factory()->blog->create();
		$users   = self::factory()->user->create_many( 2 );

		add_user_to_blog( $blog_id, $users[0], 'author' );
		add_user_to_blog( $blog_id, $users[1], 'author' );

		// The main site has no authors, so the dropdown should be empty.
		$found_main_site = wp_dropdown_users(
			array(
				'echo'    => false,
				'role'    => 'author',
				'show'    => 'user_login',
				'blog_id' => get_current_blog_id(),
			)
		);

		$this->assertSame( '', $found_main_site );

		$found_sub_site = wp_dropdown_users(
			array(
				'echo'    => false,
				'role'    => 'author',
				'show'    => 'user_login',
				'blog_id' => $blog_id,
			)
		);

		$user1 = get_userdata( $users[0] );
		$user2 = get_userdata( $users[1] );

		$this->assertStringContainsString( $user1->user_login, $found_sub_site );
		$this->assertStringContainsString( $user2->user_login, $found_sub_site );
	}
}
