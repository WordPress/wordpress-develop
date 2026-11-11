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

		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $user->display_name, 'display_name should default to "User <suffix>" when login is an email.' );
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $user->nickname, 'nickname should default to "User <suffix>" when login is an email.' );
		$this->assertMatchesRegularExpression( '/^user-[0-9a-f]{8}$/', $user->user_nicename, 'user_nicename should start with "user-" prefix followed by hex suffix.' );
		$suffix = substr( $user->user_nicename, 5 );
		$this->assertSame( 'User ' . $suffix, $user->display_name, 'display_name should share the same random suffix as user_nicename.' );
		$this->assertSame( 'User ' . $suffix, $user->nickname, 'nickname should share the same random suffix as user_nicename.' );
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
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $user->nickname, 'nickname should still default to "User <suffix>" when not explicitly supplied.' );
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
		$this->assertNotSame( $user1->display_name, $user2->display_name, 'Generated display_name values must be unique.' );
		$this->assertNotSame( $user1->nickname, $user2->nickname, 'Generated nickname values must be unique.' );
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
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $user->display_name, 'display_name should default to "User <suffix>".' );
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $user->nickname, 'nickname should default to "User <suffix>".' );
		$this->assertMatchesRegularExpression( '/^user-[0-9a-f]{8}$/', $user->user_nicename, 'user_nicename should start with "user-".' );
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
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $user->display_name, 'display_name should default to "User <suffix>" for complex email logins.' );
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $user->nickname, 'nickname should default to "User <suffix>" for complex email logins.' );
		$this->assertMatchesRegularExpression( '/^user-[0-9a-f]{8}$/', $user->user_nicename, 'user_nicename should use random slug prefix.' );
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

	/**
	 * Tests that email usernames do not leak into downstream consumer outputs:
	 * post bylines, feeds, comment authors, and REST API user responses.
	 *
	 * @ticket 66446
	 */
	public function test_email_login_downstream_privacy_in_bylines_comments_and_rest() {
		$email   = 'author.privacy@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass'  => 'Password123!',
				'role'       => 'author',
			)
		);

		$this->assertNotWPError( $user_id, 'Author creation should succeed.' );

		// 1. Post byline & author link.
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $user_id,
				'post_title'   => 'Sample Post',
				'post_content' => 'Sample content.',
			)
		);

		$post                  = get_post( $post_id );
		$GLOBALS['post']       = $post;
		$GLOBALS['authordata'] = get_userdata( $user_id );

		$byline = get_the_author();
		$this->assertStringNotContainsString( '@', $byline, 'Post byline must not contain "@".' );
		$this->assertStringNotContainsString( 'example.com', $byline, 'Post byline must not contain the email domain.' );
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $byline, 'Post byline should match "User <suffix>".' );

		$author_url = get_author_posts_url( $user_id );
		$this->assertStringNotContainsString( '@', $author_url, 'Author posts URL must not contain "@".' );
		$this->assertStringNotContainsString( 'example.com', $author_url, 'Author posts URL must not contain the email domain.' );

		// 2. Comments via comment submission by logged-in user.
		wp_set_current_user( $user_id );
		$comment = wp_handle_comment_submission(
			array(
				'comment_post_ID' => $post_id,
				'comment'         => 'A test comment.',
				'comment_parent'  => 0,
			)
		);

		$this->assertInstanceOf( 'WP_Comment', $comment, 'wp_handle_comment_submission should return a WP_Comment.' );
		$this->assertStringNotContainsString( '@', $comment->comment_author, 'Comment author must not contain "@".' );
		$this->assertStringNotContainsString( 'example.com', $comment->comment_author, 'Comment author must not contain email domain.' );
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $comment->comment_author, 'Comment author should match "User <suffix>".' );

		// 3. REST API response via controller.
		$controller = new WP_REST_Users_Controller();
		$request    = new WP_REST_Request( 'GET', '/wp/v2/users/' . $user_id );
		$request->set_param( 'id', $user_id );
		$request->set_param( 'context', 'view' );
		$response = $controller->get_item( $request );
		$data     = $response->get_data();

		$this->assertStringNotContainsString( '@', $data['name'], 'REST user name must not contain "@".' );
		$this->assertStringNotContainsString( 'example.com', $data['name'], 'REST user name must not contain email domain.' );
		$this->assertStringNotContainsString( '@', $data['slug'], 'REST user slug must not contain "@".' );
		$this->assertStringNotContainsString( 'example.com', $data['slug'], 'REST user slug must not contain email domain.' );
	}

	/**
	 * Tests that wp_insert_user() preserves existing display_name and nickname on update when omitted.
	 *
	 * When integrations call wp_insert_user() to update specific fields (e.g. description),
	 * omitted display_name and nickname must remain unchanged across successive updates.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_preserves_display_name_and_nickname_on_update_when_omitted() {
		$email   = 'sync.update@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass'  => 'Password123!',
			)
		);

		$initial_user         = get_userdata( $user_id );
		$initial_display_name = $initial_user->display_name;
		$initial_nickname     = $initial_user->nickname;
		$initial_nicename     = $initial_user->user_nicename;

		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $initial_display_name );
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $initial_nickname );

		// First update: change description, omitting name fields.
		$update_1_id = wp_insert_user(
			array(
				'ID'          => $user_id,
				'user_login'  => $email,
				'description' => 'First sync update.',
			)
		);
		$this->assertSame( $user_id, $update_1_id );

		$user_after_first_update = get_userdata( $user_id );
		$this->assertSame( $initial_display_name, $user_after_first_update->display_name, 'display_name should be preserved after first update.' );
		$this->assertSame( $initial_nickname, $user_after_first_update->nickname, 'nickname should be preserved after first update.' );
		$this->assertSame( $initial_nicename, $user_after_first_update->user_nicename, 'user_nicename should be preserved after first update.' );
		$this->assertSame( 'First sync update.', $user_after_first_update->description );

		// Second update: change description again, omitting name fields.
		$update_2_id = wp_insert_user(
			array(
				'ID'          => $user_id,
				'user_login'  => $email,
				'description' => 'Second sync update.',
			)
		);
		$this->assertSame( $user_id, $update_2_id );

		$user_after_second_update = get_userdata( $user_id );
		$this->assertSame( $initial_display_name, $user_after_second_update->display_name, 'display_name should remain unchanged across subsequent updates.' );
		$this->assertSame( $initial_nickname, $user_after_second_update->nickname, 'nickname should remain unchanged across subsequent updates.' );
		$this->assertSame( $initial_nicename, $user_after_second_update->user_nicename, 'user_nicename should remain unchanged across subsequent updates.' );
		$this->assertSame( 'Second sync update.', $user_after_second_update->description );
	}

	/**
	 * Tests that user profile public display options omit user_login when it is an email address.
	 *
	 * Tests both the initial render and subsequent display options after editing the nickname.
	 *
	 * @ticket 66446
	 */
	public function test_profile_screen_public_display_options_omits_email_username() {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$email   = 'profile.email@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass'  => 'Password123!',
			)
		);

		$profile_user   = get_userdata( $user_id );
		$public_display = wp_get_user_profile_display_name_options( $profile_user );

		$this->assertNotContains( $email, $public_display, 'Email address must not be present in public display options on initial render.' );
		$this->assertContains( $profile_user->nickname, $public_display, 'Default anonymous nickname should be present in public display options.' );

		// Update nickname and verify the dropdown updates to include it while still omitting email login.
		wp_update_user(
			array(
				'ID'       => $user_id,
				'nickname' => 'UpdatedNickname',
			)
		);

		$updated_user           = get_userdata( $user_id );
		$updated_public_display = wp_get_user_profile_display_name_options( $updated_user );

		$this->assertContains( 'UpdatedNickname', $updated_public_display, 'Updated nickname should be available in public display options.' );
		$this->assertNotContains( $email, $updated_public_display, 'Email address must still be omitted after editing nickname.' );

		// Add first and last name to verify full name combinations while still omitting email address.
		wp_update_user(
			array(
				'ID'         => $user_id,
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
			)
		);

		$named_user           = get_userdata( $user_id );
		$named_public_display = wp_get_user_profile_display_name_options( $named_user );

		$this->assertContains( 'Jane', $named_public_display, 'First name should be in display options.' );
		$this->assertContains( 'Doe', $named_public_display, 'Last name should be in display options.' );
		$this->assertContains( 'Jane Doe', $named_public_display, 'First and last name should be in display options.' );
		$this->assertContains( 'Doe Jane', $named_public_display, 'Last and first name should be in display options.' );
		$this->assertNotContains( $email, $named_public_display, 'Email address must not be present when first/last names are populated.' );

		// Standard username should include user_login in public display options.
		$std_user_id        = self::factory()->user->create( array( 'user_login' => 'standardprofile' ) );
		$std_user           = get_userdata( $std_user_id );
		$std_public_display = wp_get_user_profile_display_name_options( $std_user );

		$this->assertContains( 'standardprofile', $std_public_display, 'Standard non-email user_login should be included in display options.' );
	}

	/**
	 * Tests that wpmu_create_user() safely anonymizes defaults when given an email username on Multisite.
	 *
	 * @ticket 66446
	 * @group ms-required
	 */
	public function test_wpmu_create_user_with_email_login_anonymizes_defaults() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$email   = 'ms.member@example.com';
		$user_id = wpmu_create_user( $email, 'Password123!', $email );

		$this->assertIsInt( $user_id, 'wpmu_create_user should succeed and return integer ID.' );

		$user = get_userdata( $user_id );
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $user->display_name, 'display_name should default to "User <suffix>" on Multisite.' );
		$this->assertMatchesRegularExpression( '/^User [0-9a-f]{8}$/', $user->nickname, 'nickname should default to "User <suffix>" on Multisite.' );
		$this->assertMatchesRegularExpression( '/^user-[0-9a-f]{8}$/', $user->user_nicename, 'user_nicename should start with "user-" on Multisite.' );
	}

	/**
	 * Tests that updating an existing user whose display_name is already an email address
	 * preserves the display_name without altering it.
	 *
	 * @ticket 66446
	 */
	public function test_wp_update_user_preserves_existing_email_display_name() {
		// Simulate a legacy account created prior to this privacy change where display_name was saved as the email.
		$user_id = wp_insert_user(
			array(
				'user_login'   => 'legacyuser',
				'user_email'   => 'legacy@example.com',
				'user_pass'    => 'Password123!',
				'display_name' => 'legacy@example.com',
			)
		);

		$user_before = get_userdata( $user_id );
		$this->assertSame( 'legacy@example.com', $user_before->display_name, 'Initial display_name should be set to email.' );

		// Update another field without specifying display_name.
		$updated_id = wp_update_user(
			array(
				'ID'          => $user_id,
				'description' => 'Updated biography text.',
			)
		);

		$this->assertSame( $user_id, $updated_id, 'wp_update_user should succeed.' );

		$user_after = get_userdata( $user_id );
		$this->assertSame( 'legacy@example.com', $user_after->display_name, 'Existing display_name must remain unchanged after update.' );
		$this->assertSame( 'Updated biography text.', $user_after->description, 'Description should be updated.' );
	}

	/**
	 * Tests that updating a standard non-email user preserves or derives nicename correctly.
	 *
	 * @ticket 66446
	 */
	public function test_wp_insert_user_update_with_standard_user_preserves_nicename() {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'standardupdater',
				'user_email' => 'standardupdater@example.com',
				'user_pass'  => 'Password123!',
			)
		);

		$user_before = get_userdata( $user_id );
		$this->assertSame( 'standardupdater', $user_before->user_nicename, 'Initial nicename should match login.' );

		$updated_id = wp_update_user(
			array(
				'ID'          => $user_id,
				'description' => 'Bio update.',
			)
		);

		$this->assertSame( $user_id, $updated_id, 'wp_update_user should return the user ID.' );

		$user_after = get_userdata( $user_id );
		$this->assertSame( 'standardupdater', $user_after->user_nicename, 'Standard user nicename should be preserved.' );
	}
}
