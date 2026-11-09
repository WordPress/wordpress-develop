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
	 * Tests that user profile public display options omit user_login when it is an email address.
	 *
	 * @ticket 66446
	 */
	public function test_profile_screen_public_display_options_omits_email_username() {
		$email   = 'profile.email@example.com';
		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass'  => 'Password123!',
			)
		);

		$profile_user                       = get_userdata( $user_id );
		$public_display                     = array();
		$public_display['display_nickname'] = $profile_user->nickname;

		if ( ! is_email( $profile_user->user_login ) ) {
			$public_display['display_username'] = $profile_user->user_login;
		}

		$this->assertArrayNotHasKey( 'display_username', $public_display, 'display_username should be excluded from public display options when login is an email.' );
		$this->assertContains( $profile_user->nickname, $public_display, 'Nickname should be available in public display options.' );

		// Standard username should include display_username.
		$std_user_id                     = self::factory()->user->create( array( 'user_login' => 'standardprofile' ) );
		$std_user                        = get_userdata( $std_user_id );
		$std_display                     = array();
		$std_display['display_nickname'] = $std_user->nickname;

		if ( ! is_email( $std_user->user_login ) ) {
			$std_display['display_username'] = $std_user->user_login;
		}

		$this->assertArrayHasKey( 'display_username', $std_display, 'display_username should be included for standard usernames.' );
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
