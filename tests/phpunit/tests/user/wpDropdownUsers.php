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
	 * Passing `'autocomplete' => true` should render a text input + hidden
	 * helper instead of a <select> element.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_arg_renders_inputs_not_select() {
		self::factory()->user->create( array( 'user_login' => 'autocomplete_user' ) );

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
	}

	/**
	 * In autocomplete mode the hidden input should carry the `name` attribute
	 * so that form submission sends the user ID under the right key.
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

		$this->assertStringContainsString( 'name="reassign_user"', $found );
		// The visible text input should NOT carry the name (the hidden helper does).
		$this->assertStringNotContainsString( '<select', $found );
	}

	/**
	 * When a user is pre-selected in autocomplete mode the visible input
	 * should show their display name and the hidden helper should hold the ID.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_selected_user_is_pre_populated() {
		$u = self::factory()->user->create_and_get(
			array(
				'display_name' => 'Jane Doe',
			)
		);

		$found = wp_dropdown_users(
			array(
				'echo'         => false,
				'selected'     => $u->ID,
				'autocomplete' => true,
			)
		);

		$this->assertStringContainsString( 'value="Jane Doe"', $found );
		$this->assertStringContainsString( 'value="' . $u->ID . '"', $found );
	}

	/**
	 * The `wp_dropdown_users_autocomplete` filter must be able to override the mode.
	 *
	 * @ticket 19867
	 */
	public function test_wp_dropdown_users_autocomplete_filter_can_enable_autocomplete() {
		self::factory()->user->create();

		add_filter( 'wp_dropdown_users_autocomplete', '__return_true' );
		$found = wp_dropdown_users( array( 'echo' => false ) );
		remove_filter( 'wp_dropdown_users_autocomplete', '__return_true' );

		$this->assertStringContainsString( 'wp-suggest-user', $found );
		$this->assertStringNotContainsString( '<select', $found );
	}

	/**
	 * The `wp_dropdown_users_autocomplete` filter must be able to disable autocomplete
	 * even when the arg is explicitly set to true.
	 *
	 * @ticket 19867
	 */
	public function test_wp_dropdown_users_autocomplete_filter_can_disable_autocomplete() {
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
	 * The `wp_dropdown_users_args` filter must still fire in autocomplete mode
	 * so existing hooks are not broken.
	 *
	 * @ticket 19867
	 */
	public function test_wp_dropdown_users_args_filter_fires_in_autocomplete_mode() {
		self::factory()->user->create();
		$fired = false;

		$cb = static function ( $args ) use ( &$fired ) {
			$fired = true;
			return $args;
		};

		add_filter( 'wp_dropdown_users_args', $cb );
		wp_dropdown_users(
			array(
				'echo'         => false,
				'autocomplete' => true,
			)
		);
		remove_filter( 'wp_dropdown_users_args', $cb );

		$this->assertTrue( $fired, 'wp_dropdown_users_args filter should fire even in autocomplete mode.' );
	}

	/**
	 * The `wp_dropdown_users` HTML output filter must still fire in autocomplete mode.
	 *
	 * @ticket 19867
	 */
	public function test_wp_dropdown_users_html_filter_fires_in_autocomplete_mode() {
		self::factory()->user->create();
		$fired = false;

		$cb = static function ( $html ) use ( &$fired ) {
			$fired = true;
			return $html;
		};

		add_filter( 'wp_dropdown_users', $cb );
		wp_dropdown_users(
			array(
				'echo'         => false,
				'autocomplete' => true,
			)
		);
		remove_filter( 'wp_dropdown_users', $cb );

		$this->assertTrue( $fired, 'wp_dropdown_users HTML filter should fire even in autocomplete mode.' );
	}

	/**
	 * Calls with an explicit `include` list should never auto-enable autocomplete,
	 * even on a "large" site, because the result set is already bounded.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_not_auto_enabled_when_include_is_set() {
		$u = self::factory()->user->create();

		// Force the site to look "large" via the filter.
		add_filter( 'wp_is_large_user_count', '__return_true' );

		$found = wp_dropdown_users(
			array(
				'echo'    => false,
				'include' => array( $u ),
			)
		);

		remove_filter( 'wp_is_large_user_count', '__return_true' );

		// Must still render a <select> because 'include' was passed.
		$this->assertStringContainsString( '<select', $found );
		$this->assertStringNotContainsString( 'wp-suggest-user', $found );
	}

	/**
	 * Calls with `show_option_all` should never auto-enable autocomplete,
	 * because that option has no autocomplete equivalent.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_not_auto_enabled_when_show_option_all_is_set() {
		self::factory()->user->create();

		add_filter( 'wp_is_large_user_count', '__return_true' );

		$found = wp_dropdown_users(
			array(
				'echo'            => false,
				'show_option_all' => 'All Users',
			)
		);

		remove_filter( 'wp_is_large_user_count', '__return_true' );

		$this->assertStringContainsString( '<select', $found );
		$this->assertStringContainsString( 'All Users', $found );
	}

	/**
	 * The data-autocomplete-label attribute should match the rendered text in the
	 * classic <select> drop-down for each value of the `show` arg.
	 *
	 * @ticket 19867
	 *
	 * @dataProvider data_autocomplete_label_matches_show_arg
	 */
	public function test_autocomplete_label_matches_show_arg( string $show, string $expected_template ) {
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
