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

	/**
	 * The 'autocomplete' argument renders a text input plus a hidden helper,
	 * not a <select> element.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_arg_renders_inputs_not_select() {
		self::factory()->user->create();

		$found = wp_dropdown_users(
			array(
				'echo'         => false,
				'autocomplete' => true,
			)
		);

		$this->assertStringNotContainsString( '<select', $found );
		$this->assertStringContainsString( 'wp-suggest-user', $found );
		$this->assertStringContainsString( 'wp-suggest-user-helper', $found );
		$this->assertStringContainsString( 'type="hidden"', $found );
		$this->assertStringContainsString( 'data-autocomplete-type="site_search"', $found );
	}

	/**
	 * In autocomplete mode the hidden helper carries the `name` attribute so the
	 * form submits the selected user ID under the expected key.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_hidden_input_has_correct_name() {
		self::factory()->user->create();

		$found = wp_dropdown_users(
			array(
				'echo'         => false,
				'name'         => 'reassign_user',
				'autocomplete' => true,
			)
		);

		$this->assertMatchesRegularExpression( '/<input type="hidden" name="reassign_user"[^>]*wp-suggest-user-helper/', $found );
		$this->assertStringNotContainsString( '<select', $found );
	}

	/**
	 * A pre-selected user populates the visible input with the display value and
	 * the hidden helper with the ID.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_selected_user_is_pre_populated() {
		$user = self::factory()->user->create_and_get( array( 'display_name' => 'Jane Doe' ) );

		$found = wp_dropdown_users(
			array(
				'echo'         => false,
				'selected'     => $user->ID,
				'autocomplete' => true,
			)
		);

		$this->assertStringContainsString( 'value="Jane Doe"', $found );
		$this->assertStringContainsString( 'value="' . $user->ID . '"', $found );
	}

	/**
	 * The `wp_dropdown_users_autocomplete` filter can enable autocomplete.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_filter_can_enable() {
		self::factory()->user->create();

		add_filter( 'wp_dropdown_users_autocomplete', '__return_true' );
		$found = wp_dropdown_users( array( 'echo' => false ) );
		remove_filter( 'wp_dropdown_users_autocomplete', '__return_true' );

		$this->assertStringContainsString( 'wp-suggest-user', $found );
		$this->assertStringNotContainsString( '<select', $found );
	}

	/**
	 * The `wp_dropdown_users_autocomplete` filter can disable autocomplete even
	 * when the argument is explicitly true.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_filter_can_disable() {
		self::factory()->user->create();

		add_filter( 'wp_dropdown_users_autocomplete', '__return_false' );
		$found = wp_dropdown_users(
			array(
				'echo'         => false,
				'autocomplete' => true,
			)
		);
		remove_filter( 'wp_dropdown_users_autocomplete', '__return_false' );

		$this->assertStringContainsString( '<select', $found );
		$this->assertStringNotContainsString( 'wp-suggest-user', $found );
	}

	/**
	 * On a large site, the admin auto-enables autocomplete.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_auto_enabled_on_large_admin_site() {
		self::factory()->user->create();
		set_current_screen( 'users' );
		add_filter( 'wp_is_large_user_count', '__return_true' );

		$found = wp_dropdown_users( array( 'echo' => false ) );

		remove_filter( 'wp_is_large_user_count', '__return_true' );
		set_current_screen( 'front' );

		$this->assertStringContainsString( 'wp-suggest-user', $found );
		$this->assertStringNotContainsString( '<select', $found );
	}

	/**
	 * Auto-enable does not fire outside the admin, even on a large site.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_not_auto_enabled_outside_admin() {
		self::factory()->user->create();
		set_current_screen( 'front' );
		add_filter( 'wp_is_large_user_count', '__return_true' );

		$found = wp_dropdown_users( array( 'echo' => false ) );

		remove_filter( 'wp_is_large_user_count', '__return_true' );

		$this->assertStringContainsString( '<select', $found );
	}

	/**
	 * Auto-enable is skipped when an explicit 'include' list bounds the results.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_not_auto_enabled_when_include_is_set() {
		$user = self::factory()->user->create();
		set_current_screen( 'users' );
		add_filter( 'wp_is_large_user_count', '__return_true' );

		$found = wp_dropdown_users(
			array(
				'echo'    => false,
				'include' => array( $user ),
			)
		);

		remove_filter( 'wp_is_large_user_count', '__return_true' );
		set_current_screen( 'front' );

		$this->assertStringContainsString( '<select', $found );
	}

	/**
	 * Auto-enable is skipped when 'show_option_all' is requested, since that
	 * placeholder has no autocomplete equivalent.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_not_auto_enabled_when_show_option_all_is_set() {
		self::factory()->user->create();
		set_current_screen( 'users' );
		add_filter( 'wp_is_large_user_count', '__return_true' );

		$found = wp_dropdown_users(
			array(
				'echo'            => false,
				'show_option_all' => 'All Users',
			)
		);

		remove_filter( 'wp_is_large_user_count', '__return_true' );
		set_current_screen( 'front' );

		$this->assertStringContainsString( '<select', $found );
		$this->assertStringContainsString( 'All Users', $found );
	}

	/**
	 * Existing hooks still fire in autocomplete mode.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_mode_still_fires_existing_filters() {
		self::factory()->user->create();

		$args_fired = false;
		$html_fired = false;

		$args_cb = static function ( $args ) use ( &$args_fired ) {
			$args_fired = true;
			return $args;
		};
		$html_cb = static function ( $html ) use ( &$html_fired ) {
			$html_fired = true;
			return $html;
		};

		add_filter( 'wp_dropdown_users_args', $args_cb );
		add_filter( 'wp_dropdown_users', $html_cb );
		wp_dropdown_users(
			array(
				'echo'         => false,
				'autocomplete' => true,
			)
		);
		remove_filter( 'wp_dropdown_users_args', $args_cb );
		remove_filter( 'wp_dropdown_users', $html_cb );

		$this->assertTrue( $args_fired, 'wp_dropdown_users_args should fire in autocomplete mode.' );
		$this->assertTrue( $html_fired, 'wp_dropdown_users should fire in autocomplete mode.' );
	}

	/**
	 * The autocomplete label template matches the requested `show` value.
	 *
	 * @ticket 19867
	 *
	 * @dataProvider data_autocomplete_label_matches_show_arg
	 *
	 * @param string $show              The 'show' argument.
	 * @param string $expected_template The expected data-autocomplete-label value.
	 */
	public function test_autocomplete_label_matches_show_arg( $show, $expected_template ) {
		self::factory()->user->create();

		$found = wp_dropdown_users(
			array(
				'echo'         => false,
				'show'         => $show,
				'autocomplete' => true,
			)
		);

		$this->assertStringContainsString(
			'data-autocomplete-label="' . esc_attr( $expected_template ) . '"',
			$found
		);
	}

	/**
	 * Data provider for test_autocomplete_label_matches_show_arg.
	 *
	 * @return array[]
	 */
	public function data_autocomplete_label_matches_show_arg() {
		return array(
			'display_name'            => array( 'display_name', '{{display_name}}' ),
			'display_name_with_login' => array( 'display_name_with_login', '{{display_name}} ({{user_login}})' ),
			'user_login'              => array( 'user_login', '{{user_login}}' ),
			'user_email'              => array( 'user_email', '{{user_email}}' ),
			'unknown_field_fallback'  => array( 'user_registered', '{{display_name}}' ),
		);
	}
}
