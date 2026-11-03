<?php

/**
 * Tests for wp_insert_user().
 *
 * @group user
 * @covers ::wp_insert_user
 */
class Tests_User_WpInsertUser extends WP_UnitTestCase {

	/**
	 * Tests that inserting a user with an email username defaults display_name,
	 * nickname, and user_nicename safely to avoid leaking the email address.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_with_email_login_defaults_display_name_and_nickname_to_user() {
		$email   = 'member.privacy@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass'  => 'Password123!',
			)
		);

		$this->assertNotWPError( $user_id, 'wp_insert_user should succeed with an email login.' );

		$user = get_userdata( $user_id );
		$this->assertInstanceOf( 'WP_User', $user, 'get_userdata should return a WP_User instance.' );

		$this->assertSame( 'User', $user->display_name, 'display_name should default to "User" when login is an email.' );
		$this->assertSame( 'User', $user->nickname, 'nickname should default to "User" when login is an email.' );
		$this->assertStringStartsWith( 'user-', $user->user_nicename, 'user_nicename should start with "user-" prefix.' );
		$this->assertStringNotContainsString( '@', $user->user_nicename, 'user_nicename must not contain "@".' );
		$this->assertStringNotContainsString( 'example.com', $user->user_nicename, 'user_nicename must not contain the email domain.' );
		$this->assertLessThanOrEqual( 50, strlen( $user->user_nicename ), 'user_nicename must not exceed 50 characters.' );
	}

	/**
	 * Tests that first and last name take priority over the default display name when provided.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_with_email_login_respects_first_and_last_name_for_display_name() {
		$email   = 'jane.doe@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass'  => 'Password123!',
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
			)
		);

		$this->assertNotWPError( $user_id, 'wp_insert_user should succeed.' );

		$user = get_userdata( $user_id );
		$this->assertSame( 'Jane Doe', $user->display_name, 'display_name should be built from first and last name.' );
		$this->assertSame( 'User', $user->nickname, 'nickname should still default to "User" when not explicitly supplied.' );
	}

	/**
	 * Tests that single first name or last name is used for display name when provided.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_with_email_login_respects_single_name_for_display_name() {
		$email   = 'onlyfirst@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass'  => 'Password123!',
				'first_name' => 'Alex',
			)
		);

		$this->assertNotWPError( $user_id, 'wp_insert_user should succeed.' );

		$user = get_userdata( $user_id );
		$this->assertSame( 'Alex', $user->display_name, 'display_name should use first name when last name is empty.' );
	}

	/**
	 * Tests that explicitly supplied display_name and nickname are respected even if login is an email.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_with_email_login_respects_explicit_display_name_and_nickname() {
		$email   = 'custom.names@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => 'Password123!',
				'display_name' => 'Custom Display',
				'nickname'     => 'CustomNick',
			)
		);

		$this->assertNotWPError( $user_id, 'wp_insert_user should succeed.' );

		$user = get_userdata( $user_id );
		$this->assertSame( 'Custom Display', $user->display_name, 'Explicit display_name should be preserved.' );
		$this->assertSame( 'CustomNick', $user->nickname, 'Explicit nickname should be preserved.' );
	}

	/**
	 * Tests that explicitly supplied user_nicename is respected even if login is an email.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_with_email_login_respects_explicit_user_nicename() {
		$email   = 'custom.slug@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login'    => $email,
				'user_email'    => $email,
				'user_pass'     => 'Password123!',
				'user_nicename' => 'custom-author-slug',
			)
		);

		$this->assertNotWPError( $user_id, 'wp_insert_user should succeed.' );

		$user = get_userdata( $user_id );
		$this->assertSame( 'custom-author-slug', $user->user_nicename, 'Explicit user_nicename should be preserved.' );
	}

	/**
	 * Tests that standard non-email usernames continue to use the username as fallback for display_name,
	 * nickname, and user_nicename.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_with_standard_username_preserves_username_fallbacks() {
		$username = 'standarduser';
		$user_id  = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => 'standarduser@example.com',
				'user_pass'  => 'Password123!',
			)
		);

		$this->assertNotWPError( $user_id, 'wp_insert_user should succeed with standard username.' );

		$user = get_userdata( $user_id );
		$this->assertSame( $username, $user->display_name, 'display_name should default to standard username.' );
		$this->assertSame( $username, $user->nickname, 'nickname should default to standard username.' );
		$this->assertSame( $username, $user->user_nicename, 'user_nicename should default to sanitized username.' );
	}

	/**
	 * Tests that multiple users with email logins get distinct user_nicename values.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_with_email_login_generates_unique_nicenames() {
		$email1   = 'user.one@example.com';
		$email2   = 'user.two@example.com';
		$user_id1 = wp_insert_user(
			array(
				'user_login' => $email1,
				'user_email' => $email1,
				'user_pass'  => 'Password123!',
			)
		);
		$user_id2 = wp_insert_user(
			array(
				'user_login' => $email2,
				'user_email' => $email2,
				'user_pass'  => 'Password123!',
			)
		);

		$this->assertNotWPError( $user_id1, 'User 1 creation should succeed.' );
		$this->assertNotWPError( $user_id2, 'User 2 creation should succeed.' );

		$user1 = get_userdata( $user_id1 );
		$user2 = get_userdata( $user_id2 );

		$this->assertNotSame( $user1->user_nicename, $user2->user_nicename, 'Generated user_nicename values must be unique.' );
	}

	/**
	 * Tests that updating a user with an email username preserves the existing user_nicename
	 * when user_nicename is not passed in the update data.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_update_preserves_existing_user_nicename_for_email_login() {
		$email   = 'update.member@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass'  => 'Password123!',
			)
		);

		$user_before   = get_userdata( $user_id );
		$orig_nicename = $user_before->user_nicename;

		// Perform an update without supplying user_nicename.
		$updated_id = wp_update_user(
			array(
				'ID'          => $user_id,
				'description' => 'Updated bio description.',
			)
		);

		$this->assertSame( $user_id, $updated_id, 'wp_update_user should return the user ID.' );

		$user_after = get_userdata( $user_id );
		$this->assertSame( $orig_nicename, $user_after->user_nicename, 'Updating a user must preserve existing user_nicename.' );
		$this->assertSame( 'Updated bio description.', $user_after->description, 'User description should be updated.' );
	}

	/**
	 * Tests that wp_create_user() also defaults display_name and nickname to 'User'
	 * when called with an email address as username.
	 *
	 * @ticket 66446
	 */
	public function test_wp_create_user_with_email_login_defaults_display_name_and_nickname_to_user() {
		$email   = 'created.user@example.com';
		$user_id = wp_create_user( $email, 'Password123!', $email );

		$this->assertNotWPError( $user_id, 'wp_create_user should succeed.' );

		$user = get_userdata( $user_id );
		$this->assertSame( 'User', $user->display_name, 'display_name should default to "User".' );
		$this->assertSame( 'User', $user->nickname, 'nickname should default to "User".' );
		$this->assertStringStartsWith( 'user-', $user->user_nicename, 'user_nicename should start with "user-".' );
	}

	/**
	 * Tests that complex email address formats as login are treated as email logins.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_with_complex_email_login() {
		$email   = 'First.Last+tag@sub.domain.example.co.uk';
		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass'  => 'Password123!',
			)
		);

		$this->assertNotWPError( $user_id, 'wp_insert_user should succeed with complex email format.' );

		$user = get_userdata( $user_id );
		$this->assertSame( 'User', $user->display_name, 'display_name should default to "User" for complex email logins.' );
		$this->assertSame( 'User', $user->nickname, 'nickname should default to "User" for complex email logins.' );
		$this->assertStringStartsWith( 'user-', $user->user_nicename, 'user_nicename should use random slug prefix.' );
		$this->assertStringNotContainsString( 'First.Last', $user->user_nicename, 'user_nicename must not leak the email local-part.' );
		$this->assertStringNotContainsString( 'sub.domain', $user->user_nicename, 'user_nicename must not leak the email domain.' );
	}

	/**
	 * Tests that filters pre_user_display_name, pre_user_nickname, and pre_user_nicename
	 * can override the default values when desired.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_with_email_login_allows_filter_overrides() {
		$filter_display  = static function () {
			return 'Filtered Display';
		};
		$filter_nickname = static function () {
			return 'Filtered Nick';
		};
		$filter_nicename = static function () {
			return 'filtered-slug';
		};

		add_filter( 'pre_user_display_name', $filter_display );
		add_filter( 'pre_user_nickname', $filter_nickname );
		add_filter( 'pre_user_nicename', $filter_nicename );

		$email   = 'filterable@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass'  => 'Password123!',
			)
		);

		$user = get_userdata( $user_id );
		$this->assertSame( 'Filtered Display', $user->display_name, 'pre_user_display_name filter should override display_name.' );
		$this->assertSame( 'Filtered Nick', $user->nickname, 'pre_user_nickname filter should override nickname.' );
		$this->assertSame( 'filtered-slug', $user->user_nicename, 'pre_user_nicename filter should override user_nicename.' );
	}
}
