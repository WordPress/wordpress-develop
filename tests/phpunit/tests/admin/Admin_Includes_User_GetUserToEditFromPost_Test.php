<?php

/**
 * @group admin
 * @group user
 *
 * @covers ::_get_user_to_edit_from_post
 * @covers ::_get_edit_user_error_fields
 * @covers ::_get_user_edit_form_posted_option
 */
class Admin_Includes_User_GetUserToEditFromPost_Test extends WP_UnitTestCase {

	/**
	 * Cleans up globals after each test.
	 */
	public function tear_down() {
		$_POST = array();
		unset( $GLOBALS['_wp_user_edit_posted_options'] );

		parent::tear_down();
	}

	/**
	 * Tests that safe submitted values are repopulated without changing stored data.
	 *
	 * @ticket 26962
	 */
	public function test_get_user_to_edit_from_post_repopulates_safe_values_without_saving() {
		$administrator_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$user_id          = self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'user_email'   => 'stored@example.com',
				'first_name'   => 'Stored',
				'description'  => 'Stored bio',
				'rich_editing' => 'true',
			)
		);

		wp_set_current_user( $administrator_id );
		update_user_option( $user_id, 'admin_color', 'modern' );
		update_user_option( $user_id, 'show_admin_bar_front', 'false' );

		$_POST = array(
			'first_name'          => 'Posted',
			'display_name'        => 'Posted Display Name',
			'email'               => 'not-an-email-address',
			'description'         => 'Posted bio',
			'rich_editing'        => 'false',
			'syntax_highlighting' => 'false',
			'comment_shortcuts'   => 'true',
			'admin_bar_front'     => '1',
			'admin_color'         => 'midnight',
			'use_ssl'             => '1',
			'role'                => 'editor',
			'pass1'               => 'secret-pass',
			'pass2'               => '',
		);

		$errors = new WP_Error();
		$errors->add( 'invalid_email', 'Invalid email.', array( 'form-field' => 'email' ) );
		$errors->add( 'pass', 'Passwords do not match.', array( 'form-field' => 'pass1' ) );

		$user = _get_user_to_edit_from_post( $user_id, $errors );

		$this->assertSame( 'Posted', $user->first_name );
		$this->assertSame( 'Posted Display Name', $user->display_name );
		$this->assertSame( 'Stored bio', get_userdata( $user_id )->description );
		$this->assertSame( 'Posted bio', $user->description );
		$this->assertSame( 'stored@example.com', $user->user_email );
		$this->assertSame( 'false', $user->rich_editing );
		$this->assertSame( 'false', $user->syntax_highlighting );
		$this->assertSame( 'true', $user->comment_shortcuts );
		$this->assertSame( 'true', $user->show_admin_bar_front );
		$this->assertSame( '1', $user->use_ssl );
		$this->assertSame( array( 'editor' ), $user->roles );

		$stored_user = get_userdata( $user_id );

		$this->assertSame( 'Stored', $stored_user->first_name );
		$this->assertSame( 'Stored bio', $stored_user->description );
		$this->assertSame( array( 'subscriber' ), $stored_user->roles );
		$this->assertSame( 'modern', get_user_option( 'admin_color', $user_id ) );
		$this->assertFalse( _get_admin_bar_pref( 'front', $user_id ) );
	}

	/**
	 * Tests that posted user options are only used for the matching user.
	 *
	 * @ticket 26962
	 */
	public function test_get_user_edit_form_posted_option_only_overrides_matching_user() {
		$user_id       = self::factory()->user->create();
		$other_user_id = self::factory()->user->create();

		$GLOBALS['_wp_user_edit_posted_options'] = array(
			'user_id' => $user_id,
			'options' => array(
				'admin_color' => 'midnight',
			),
		);

		$this->assertSame(
			'midnight',
			_get_user_edit_form_posted_option( 'modern', 'admin_color', get_userdata( $user_id ) )
		);
		$this->assertSame(
			'modern',
			_get_user_edit_form_posted_option( 'modern', 'admin_color', get_userdata( $other_user_id ) )
		);
	}
}
