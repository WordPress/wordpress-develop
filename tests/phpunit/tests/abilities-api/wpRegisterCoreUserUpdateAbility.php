<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-user-write-ability.php';

/**
 * Tests for the core/user-update ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Users
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreUserUpdateAbility extends WP_Abilities_User_Write_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/user-update';

	/**
	 * Builds the input that updates a user.
	 *
	 * @since 7.3.0
	 *
	 * @param int                  $user_id The user ID.
	 * @param array<string, mixed> $data    The attributes.
	 * @param list<string>|null    $fields  Optional. The fields, or null to send none. Default null.
	 * @return array<string, mixed> The input.
	 */
	private function update_input( int $user_id, array $data, ?array $fields = null ): array {
		$input = array(
			'where' => array( 'id' => $user_id ),
			'data'  => $data,
		);
		if ( null !== $fields ) {
			$input['fields'] = $fields;
		}

		return $input;
	}

	/**
	 * Returns the roles of a user, read from the database.
	 *
	 * @since 7.3.0
	 *
	 * @param int $user_id The user ID.
	 * @return list<string> The roles.
	 */
	private function get_stored_roles( int $user_id ): array {
		return array_values( ( new WP_User( $user_id ) )->roles );
	}

	/**
	 * The ability is registered in the user category with its label and annotations.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_registers_the_ability(): void {
		$this->assert_registration( 'Update User', false, true );
	}

	/**
	 * The input schema takes `where` with the ID, the attributes in `data` without the
	 * username, and the fields of core/users-query.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_input_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The update ability should be registered.' );

		$expected = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where', 'data' ),
			'properties'           => array(
				'where'  => $this->get_where_schema(),
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'minProperties'        => 1,
					'properties'           => $this->get_profile_property_schemas(),
				),
				'fields' => wp_get_ability( 'core/users-query' )->get_input_schema()['properties']['fields'],
			),
		);

		$this->assertSame( $this->normalize_schema( $expected ), $this->normalize_schema( wp_get_ability( self::ABILITY )->get_input_schema() ) );
	}

	/**
	 * The output schema is the core/users-query user item.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_output_schema(): void {
		$this->assert_output_schema_is_the_query_item();
	}

	/**
	 * The roles and the biography are changed, and the output shows the new state.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_updates_roles_and_description(): void {
		$user_id = $this->create_target( 'uwtmaya', array( 'role' => 'editor' ) );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$fields = array( 'name', 'description', 'roles' );
		$result = $this->run_ability(
			$this->update_input(
				$user_id,
				array(
					'roles'       => array( 'author' ),
					'description' => 'Writes about hardware.',
				),
				$fields
			)
		);

		$this->assert_user_item( $result, $fields, 'Changing the roles and the biography' );
		$this->assertSameSets( array( 'id', 'name', 'description', 'roles' ), array_keys( $result ), 'The output should hold exactly the requested fields and the ID.' );
		$this->assertSame( array( 'author' ), $result['roles'], 'The output should show the new role.' );
		$this->assertSame( 'Writes about hardware.', $result['description'], 'The output should show the new biography.' );
		$this->assertSame( array( 'author' ), $this->get_stored_roles( $user_id ), 'The user should be an author only.' );
	}

	/**
	 * Every attribute is stored, the output shows the stored user, and the username and
	 * password stay.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_updates_every_attribute(): void {
		$user_id = $this->create_target( 'uwtevery' );
		$before  = get_userdata( $user_id );
		$hash    = $before->user_pass;

		$data = array(
			'email'       => 'uwt-every-new@example.test',
			'name'        => 'Every Attribute',
			'first_name'  => 'Every',
			'last_name'   => 'Attribute',
			'nickname'    => 'every',
			'slug'        => 'every-attribute',
			'description' => 'A plain biography.',
			'url'         => 'https://example.test/every',
			'locale'      => 'de_DE',
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( $user_id, $data, self::ALL_FIELDS ) );

		$this->assert_user_item( $result, self::ALL_FIELDS, 'Changing every attribute' );

		$user = get_userdata( $user_id );
		$this->assertSame( $data['email'], $user->user_email, 'The email should be stored.' );
		$this->assertSame( $data['name'], $user->display_name, 'The name should be stored.' );
		$this->assertSame( $data['first_name'], $user->first_name, 'The first name should be stored.' );
		$this->assertSame( $data['last_name'], $user->last_name, 'The last name should be stored.' );
		$this->assertSame( $data['nickname'], $user->nickname, 'The nickname should be stored.' );
		$this->assertSame( $data['slug'], $user->user_nicename, 'The slug should be stored.' );
		$this->assertSame( $data['description'], $user->description, 'The biography should be stored.' );
		$this->assertSame( $data['url'], $user->user_url, 'The URL should be stored.' );
		$this->assertSame( 'de_DE', get_user_meta( $user_id, 'locale', true ), 'The locale should be stored.' );
		$this->assertSame( 'uwtevery', $user->user_login, 'The username should stay.' );
		$this->assertSame( $hash, $user->user_pass, 'The password should stay.' );
		$this->assertSame( array( 'author' ), $this->get_stored_roles( $user_id ), 'The roles should stay.' );
	}

	/**
	 * The previous address is told about a changed email only, also when only the
	 * letter case changed, and no password mail is sent.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_email_changes
	 *
	 * @param array<string, string> $data         The attributes.
	 * @param bool                  $mail_expected Whether the previous address gets a mail.
	 */
	public function test_mails_the_previous_address_when_the_email_changes( array $data, bool $mail_expected ): void {
		$user_id = $this->create_target( 'uwtmail' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assertIsArray( $this->run_ability( $this->update_input( $user_id, $data ) ), 'The update should succeed.' );

		if ( isset( $data['email'] ) ) {
			$this->assertSame( $data['email'], get_userdata( $user_id )->user_email, 'The email should be stored as sent.' );
		}

		$mails    = $this->get_mails();
		$expected = array();
		if ( $mail_expected ) {
			$expected[] = array(
				'to'      => array( 'uwtmail@example.test' ),
				'subject' => '[' . $this->get_mail_site_name() . '] Email Changed',
			);
		}

		$this->assertSame(
			$expected,
			array_map(
				static function ( $mail ) {
					return array(
						'to'      => $mail['to'],
						'subject' => $mail['subject'],
					);
				},
				$mails
			),
			'Only the change of the email should be mailed, to the previous address.'
		);
	}

	/**
	 * Returns updates with and without a change of the email.
	 *
	 * @return array<string, array{data: array<string, string>, mail_expected: bool}>
	 *         The attributes, and whether a mail is expected.
	 */
	public static function data_email_changes(): array {
		return array(
			'email left out'      => array( array( 'name' => 'Mail Target' ), false ),
			'same email'          => array( array( 'email' => 'uwtmail@example.test' ), false ),
			'letter case changed' => array( array( 'email' => 'UwtMail@Example.test' ), true ),
			'new email'           => array( array( 'email' => 'uwtmail-new@example.test' ), true ),
		);
	}

	/**
	 * The roles are replaced by exactly the given ones, in order, also when they equal
	 * the current ones, without a profile save.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_role_replacements
	 *
	 * @param list<string> $current The current roles.
	 * @param list<string> $roles   The roles sent.
	 */
	public function test_replaces_the_roles( array $current, array $roles ): void {
		$user_id = $this->create_target( 'uwtroles', array( 'role' => array_shift( $current ) ) );
		foreach ( $current as $role ) {
			get_userdata( $user_id )->add_role( $role );
		}

		$this->login_as( 'manager' );
		$this->register_abilities();

		$profile_updates = did_action( 'profile_update' );
		$result          = $this->run_ability( $this->update_input( $user_id, array( 'roles' => $roles ), array( 'roles' ) ) );

		$this->assertIsArray( $result, 'The roles should be replaced.' );
		$this->assertSame( $roles, $result['roles'], 'The output should show the roles in order.' );
		$this->assertSame( $roles, $this->get_stored_roles( $user_id ), 'The user should hold exactly the roles, in order.' );
		$this->assertSame( $profile_updates, did_action( 'profile_update' ), 'No profile save should run.' );
		$this->assertSame( array(), $this->get_mails(), 'No mail should be sent.' );
	}

	/**
	 * Returns current roles and new roles.
	 *
	 * @return array<string, array{current: list<string>, roles: list<string>}> The roles.
	 */
	public static function data_role_replacements(): array {
		return array(
			'one role for another' => array( array( 'administrator' ), array( 'editor' ) ),
			'one role for two'     => array( array( 'administrator' ), array( 'author', 'editor' ) ),
			'the same role'        => array( array( 'author' ), array( 'author' ) ),
			'reversed order'       => array( array( 'author', 'editor' ), array( 'editor', 'author' ) ),
		);
	}

	/**
	 * Nobody logged in is refused by the permission check, and by the callback with a
	 * 401 before the user is looked up, whatever `data` holds.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_updates_while_logged_out
	 *
	 * @param bool                 $existing Whether the target exists.
	 * @param array<string, mixed> $data     The attributes.
	 */
	public function test_refuses_logged_out_callers( bool $existing, array $data ): void {
		$user_id   = $existing ? self::$user_ids['author'] : self::MISSING_ID;
		$callbacks = $this->get_ability_callbacks();
		$this->login_as( '' );

		$before = $this->snapshot();

		$this->assert_refused( $this->run_ability( $this->update_input( $user_id, $data ) ), 'Updating while logged out' );
		$this->assert_core_error( $callbacks['execute_callback']( $this->update_input( $user_id, $data ) ), 'user_cannot_edit', 'Updating while logged out behind the gate' );
		$this->assert_unchanged( $before, 'Updating while logged out' );
	}

	/**
	 * Returns updates of a logged-out caller.
	 *
	 * @return array<string, array{existing: bool, data: array<string, mixed>}> Whether the
	 *                                                                           target exists, and the attributes.
	 */
	public static function data_updates_while_logged_out(): array {
		return array(
			'profile'           => array( true, array( 'name' => 'Logged Out' ) ),
			'roles'             => array( true, array( 'roles' => array( 'editor' ) ) ),
			'invalid attribute' => array( true, array( 'name' => str_repeat( 'a', 249 ) . '&' ) ),
			'missing user'      => array( false, array( 'name' => 'Logged Out' ) ),
		);
	}

	/**
	 * An ID that no user has is the not-found error.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_ids_of_no_user
	 *
	 * @param mixed $id                The ID.
	 * @param bool  $skip_validation   Whether to skip schema validation.
	 */
	public function test_reports_a_missing_user( $id, bool $skip_validation ): void {
		if ( $skip_validation ) {
			$this->skip_input_validation();
		}

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_core_error(
			$this->run_ability(
				array(
					'where' => array( 'id' => $id ),
					'data'  => array( 'name' => 'Nobody' ),
				)
			),
			'user_not_found',
			'Updating a user that does not exist'
		);
		$this->assert_unchanged( $before, 'Updating a user that does not exist' );
	}

	/**
	 * Returns IDs that name no user.
	 *
	 * @return array<string, array{id: mixed, skip_validation: bool}> The ID, and whether
	 *                                                                 to skip validation.
	 */
	public static function data_ids_of_no_user(): array {
		return array(
			'missing ID'                      => array( self::MISSING_ID, false ),
			'zero, validation skipped'        => array( 0, true ),
			'negative ID, validation skipped' => array( -1, true ),
		);
	}

	/**
	 * On multisite a user who is not a member of the current site is not found, for
	 * every caller.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_callers_and_users_of_another_site
	 *
	 * @param string               $caller The caller, as a fixture name.
	 * @param string               $target The target, as a fixture name.
	 * @param array<string, mixed> $data   The attributes.
	 */
	public function test_reports_a_user_of_another_site( string $caller, string $target, array $data ): void {
		$this->login_as( $caller );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_core_error( $this->run_ability( $this->update_input( self::$user_ids[ $target ], $data ) ), 'user_not_found', 'Updating a user of another site' );
		$this->assert_unchanged( $before, 'Updating a user of another site' );
	}

	/**
	 * Returns callers and users who are not members of the current site.
	 *
	 * @return array<string, array{caller: string, target: string, data: array<string, mixed>}>
	 *         The caller, the target and the attributes.
	 */
	public static function data_callers_and_users_of_another_site(): array {
		return array(
			'site administrator, profile'  => array( 'administrator', 'outsider', array( 'first_name' => 'Other' ) ),
			'super admin, profile'         => array( 'manager', 'outsider', array( 'first_name' => 'Other' ) ),
			'super admin, roles'           => array( 'manager', 'outsider', array( 'roles' => array( 'editor' ) ) ),
			'super admin updating oneself' => array( 'outside_super_admin', 'outside_super_admin', array( 'first_name' => 'Self' ) ),
		);
	}

	/**
	 * A caller without the capability the keys of `data` need is refused before
	 * anything else is checked.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_updates_without_the_capability
	 *
	 * @param string               $caller The caller, as a fixture name.
	 * @param bool                 $own    Whether the caller updates themself.
	 * @param array<string, mixed> $data   The attributes.
	 * @param string               $code   The error code.
	 */
	public function test_refuses_callers_without_the_capability( string $caller, bool $own, array $data, string $code ): void {
		$user_id = $own ? self::$user_ids[ $caller ] : $this->create_target( 'uwtcapability' );

		$this->login_as( $caller );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_core_error( $this->run_ability( $this->update_input( $user_id, $data ) ), $code, 'Updating without the capability' );
		$this->assert_unchanged( $before, 'Updating without the capability' );
	}

	/**
	 * Returns updates the caller may not make.
	 *
	 * @return array<string, array{caller: string, self: bool, data: array<string, mixed>, code: string}>
	 *         The caller, whether the target is the caller, the attributes and the error code.
	 */
	public static function data_updates_without_the_capability(): array {
		$profile = array( 'name' => 'Not Allowed' );
		$roles   = array( 'roles' => array( 'editor' ) );

		return array(
			'editor, profile'               => array( 'editor', false, $profile, 'user_cannot_edit' ),
			'author, profile'               => array( 'author', false, $profile, 'user_cannot_edit' ),
			'contributor, profile'          => array( 'contributor', false, $profile, 'user_cannot_edit' ),
			'subscriber, profile'           => array( 'subscriber', false, $profile, 'user_cannot_edit' ),
			'promoter, profile'             => array( 'promoter', false, $profile, 'user_cannot_edit' ),
			'lister and promoter, profile'  => array( 'lister_promoter', false, $profile, 'user_cannot_edit' ),
			'editor, roles'                 => array( 'editor', false, $roles, 'user_cannot_edit_roles' ),
			'author, roles'                 => array( 'author', false, $roles, 'user_cannot_edit_roles' ),
			'contributor, roles'            => array( 'contributor', false, $roles, 'user_cannot_edit_roles' ),
			'subscriber, roles'             => array( 'subscriber', false, $roles, 'user_cannot_edit_roles' ),
			'editor, roles and profile'     => array( 'editor', false, array_merge( $roles, $profile ), 'user_cannot_edit_roles' ),
			'promoter, roles and profile'   => array( 'promoter', false, array_merge( $roles, $profile ), 'user_cannot_edit' ),
			'editor, invalid attribute'     => array( 'editor', false, array( 'name' => str_repeat( 'a', 249 ) . '&' ), 'user_cannot_edit' ),
			'editor giving themself a role' => array( 'editor', true, array( 'roles' => array( 'administrator' ) ), 'user_cannot_edit_roles' ),
			'subscriber changing own roles' => array( 'subscriber', true, $roles, 'user_cannot_edit_roles' ),
		);
	}

	/**
	 * An editor changes the letter case of their own email, which applies at once, but
	 * cannot take the email of another user in another case.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_editor_updates_their_own_email(): void {
		$editor_id = self::$user_ids['editor'];

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( $editor_id, array( 'email' => 'UWT-Editor@example.test' ), array( 'email' ) ) );

		$this->assertSame(
			array(
				'id'    => $editor_id,
				'email' => 'UWT-Editor@example.test',
			),
			$result,
			'The output should show the new email.'
		);
		$this->assertSame( 'UWT-Editor@example.test', get_userdata( $editor_id )->user_email, 'The new email should apply at once.' );

		reset_phpmailer_instance();
		$before = $this->snapshot();

		$this->assert_value_error(
			$this->run_ability( $this->update_input( $editor_id, array( 'email' => 'UWT-AUTHOR@example.test' ) ) ),
			'user_exists',
			'data[email]',
			'Taking the email of another user in another case',
			'Sorry, that email address is already used!'
		);
		$this->assert_unchanged( $before, 'Taking the email of another user in another case' );
	}

	/**
	 * An administrator who is not a super admin cannot give themself a role without
	 * `edit_users`, also next to a role with it.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_own_roles_without_edit_users
	 *
	 * @param list<string> $roles The roles.
	 */
	public function test_refuses_lowering_your_own_role( array $roles ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_core_error( $this->run_ability( $this->update_input( self::$user_ids['administrator'], array( 'roles' => $roles ) ) ), 'user_cannot_assign_role', 'Lowering your own role' );
		$this->assert_unchanged( $before, 'Lowering your own role' );
	}

	/**
	 * Returns own roles that hold one without `edit_users`.
	 *
	 * @return array<string, array{roles: list<string>}> The roles.
	 */
	public static function data_own_roles_without_edit_users(): array {
		return array(
			'editor'                   => array( array( 'editor' ) ),
			'administrator and editor' => array( array( 'administrator', 'editor' ) ),
			'subscriber'               => array( array( 'subscriber' ) ),
		);
	}

	/**
	 * An administrator who is not a super admin keeps their own role when they set it
	 * again, since it lets them edit users.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_administrator_sets_their_own_role_again(): void {
		$user_id = self::$user_ids['administrator'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		if ( is_multisite() ) {
			$this->assertFalse( is_super_admin( $user_id ), 'The administrator should not be a super admin.' );
		}

		$result = $this->run_ability( $this->update_input( $user_id, array( 'roles' => array( 'administrator' ) ), array( 'roles' ) ) );

		$this->assertSame(
			array(
				'id'    => $user_id,
				'roles' => array( 'administrator' ),
			),
			$result,
			'The output should show the role.'
		);
		$this->assertSame( array( 'administrator' ), $this->get_stored_roles( $user_id ), 'The administrator should hold exactly the administrator role.' );
	}

	/**
	 * On multisite a super admin can lower their own role.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 */
	public function test_super_admin_lowers_their_own_role(): void {
		$user_id = $this->create_target( 'uwtownrole', array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );

		wp_set_current_user( $user_id );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( $user_id, array( 'roles' => array( 'editor' ) ), array( 'roles' ) ) );

		$this->assertSame(
			array(
				'id'    => $user_id,
				'roles' => array( 'editor' ),
			),
			$result,
			'The output should show the new role.'
		);
		$this->assertSame( array( 'editor' ), $this->get_stored_roles( $user_id ), 'The super admin should be an editor of the site.' );
	}

	/**
	 * A role that is not editable is refused.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_ways_a_role_is_not_editable
	 *
	 * @param bool $keyed Whether the filter keeps the key of `editor` with an empty entry
	 *                    instead of removing it.
	 */
	public function test_refuses_a_role_that_is_not_editable( bool $keyed ): void {
		add_filter(
			'editable_roles',
			static function ( $roles ) use ( $keyed ) {
				if ( $keyed ) {
					$roles['editor'] = false;
				} else {
					unset( $roles['editor'] );
				}
				return $roles;
			}
		);

		$user_id = $this->create_target( 'uwtnoteditable' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_core_error( $this->run_ability( $this->update_input( $user_id, array( 'roles' => array( 'author', 'editor' ) ) ) ), 'user_cannot_assign_role', 'Giving a role that is not editable' );
		$this->assert_unchanged( $before, 'Giving a role that is not editable' );
	}

	/**
	 * Returns the ways a filter makes `editor` not editable.
	 *
	 * @return array<string, array{keyed: bool}> Whether the entry is kept empty.
	 */
	public static function data_ways_a_role_is_not_editable(): array {
		return array(
			'role removed'       => array( false ),
			'entry set to false' => array( true ),
		);
	}

	/**
	 * A role that was removed after the ability was registered is refused.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_refuses_a_role_removed_after_registration(): void {
		$user_id = $this->create_target( 'uwtgone' );

		add_role( 'uwt_gone', 'Gone', array( 'read' => true ) );
		$this->login_as( 'manager' );
		$this->register_abilities();
		remove_role( 'uwt_gone' );

		$before = $this->snapshot();

		$this->assert_value_error(
			$this->run_ability( $this->update_input( $user_id, array( 'roles' => array( 'uwt_gone' ) ) ) ),
			'user_invalid_attribute',
			'data[roles]',
			'Giving a removed role',
			'The role uwt_gone does not exist.'
		);
		$this->assert_unchanged( $before, 'Giving a removed role' );
	}

	/**
	 * Attributes that cannot be applied are refused before the save runs.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_profile_attributes_refused
	 *
	 * @param array<string, string> $data    The attributes.
	 * @param string                $code    The error code.
	 * @param string                $param   The input path.
	 * @param string|null           $message The message, or null when it is not fixed.
	 */
	public function test_refuses_attributes( array $data, string $code, string $param, ?string $message ): void {
		// The user has a URL, so a refused URL that cleared it would show as a change.
		$user_id = $this->create_target( 'uwtrefused', array( 'user_url' => 'https://example.test/kept' ) );
		$this->create_target( 'uwtteamdesk', array( 'user_nicename' => 'team-desk' ) );

		$this->login_as( 'manager' );
		$this->register_abilities();

		if ( self::LONG_NAME_MESSAGE === $message ) {
			$message = $this->get_long_name_message( $this->update_input( $user_id, array( 'name' => str_repeat( 'a', 251 ) ) ) );
		}

		$before = $this->snapshot();

		$this->watch_filter( 'pre_user_login' );

		$this->assert_value_error( $this->run_ability( $this->update_input( $user_id, $data ) ), $code, $param, 'Updating with a refused attribute', $message );
		$this->assert_unchanged( $before, 'Updating with a refused attribute' );
		$this->assert_filter_not_run( 'pre_user_login', 'Updating with a refused attribute' );
	}

	/**
	 * An email of 101 characters and a name of 251 characters are refused by the schema,
	 * and with the error about the value when validation is skipped, before the save runs.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_values_over_the_length_limits
	 *
	 * @param array<string, string> $data            The attribute.
	 * @param bool                  $skip_validation Whether to skip schema validation.
	 * @param string                $param           The input path.
	 */
	public function test_refuses_values_over_the_length_limits( array $data, bool $skip_validation, string $param ): void {
		$user_id = $this->create_target( 'uwtlong' );

		if ( $skip_validation ) {
			$this->skip_input_validation();
		}

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->watch_filter( 'pre_user_login' );
		$this->watch_filter( 'wpmu_validate_user_signup' );

		$this->assert_over_the_length_limit( $this->run_ability( $this->update_input( $user_id, $data ) ), $skip_validation, $param, 'Updating with a value over the length limit' );
		$this->assert_unchanged( $before, 'Updating with a value over the length limit' );
		$this->assert_filter_not_run( 'pre_user_login', 'Updating with a value over the length limit' );
		$this->assert_filter_not_run( 'wpmu_validate_user_signup', 'Updating with a value over the length limit' );
	}

	/**
	 * An email of 100 characters and a name of 250 characters are accepted and stored.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_accepts_values_at_the_length_limits(): void {
		$user_id = $this->create_target( 'uwtlimits' );
		$data    = array(
			'email' => str_repeat( 'b', 87 ) . '@example.test',
			'name'  => str_repeat( 'c', 250 ),
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assertIsArray( $this->run_ability( $this->update_input( $user_id, $data ) ), 'Values at the limits should be accepted.' );

		$user = get_userdata( $user_id );
		$this->assertSame( $data['email'], $user->user_email, 'The email of 100 characters should be stored.' );
		$this->assertSame( $data['name'], $user->display_name, 'The name of 250 characters should be stored.' );
	}

	/**
	 * Quotes and backslashes in text values are stored as given.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_stores_quotes_and_backslashes(): void {
		$user_id = $this->create_target( 'uwtquotes' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$fields = array( 'name', 'description', 'first_name', 'last_name', 'nickname' );
		$this->assert_user_item( $this->run_ability( $this->update_input( $user_id, $this->get_quoted_values(), $fields ) ), $fields, 'Updating with quotes and backslashes' );
		$this->assert_quoted_values_stored( $user_id, 'Updating with quotes and backslashes' );
	}

	/**
	 * The locale is stored, and the output shows the locale the user gets.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_locales
	 *
	 * @param string $locale The locale.
	 */
	public function test_sets_the_locale( string $locale ): void {
		$user_id = $this->create_target( 'uwtlocale', array( 'locale' => 'es_ES' ) );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( $user_id, array( 'locale' => $locale ), array( 'locale' ) ) );

		$this->assertSame( $locale, get_user_meta( $user_id, 'locale', true ), 'The locale should be stored.' );
		$this->assertSame(
			array(
				'id'     => $user_id,
				'locale' => get_user_locale( $user_id ),
			),
			$result,
			'The output should show the locale of the user.'
		);
	}

	/**
	 * Returns locales that can be set.
	 *
	 * @return array<string, array{locale: string}> The locales.
	 */
	public static function data_locales(): array {
		return array(
			'site locale'        => array( '' ),
			'English'            => array( 'en_US' ),
			'installed language' => array( 'de_DE' ),
		);
	}

	/**
	 * An empty URL clears the URL, and the user's own slug can be sent again.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_clears_the_url_and_keeps_the_slug(): void {
		$user_id = $this->create_target( 'uwtsameslug', array( 'user_url' => 'https://example.test/old' ) );
		$slug    = get_userdata( $user_id )->user_nicename;

		$this->login_as( 'manager' );
		$this->register_abilities();

		$first  = $this->run_ability(
			$this->update_input(
				$user_id,
				array(
					'url'  => '',
					'slug' => $slug,
				),
				array( 'url', 'slug' )
			)
		);
		$second = $this->run_ability( $this->update_input( $user_id, array( 'slug' => $slug ), array( 'url', 'slug' ) ) );

		$expected = array(
			'id'   => $user_id,
			'url'  => '',
			'slug' => $slug,
		);
		$this->assertSame( $expected, $first, 'The first update should clear the URL and keep the slug.' );
		$this->assertSame( $expected, $second, 'The same slug should be accepted a second time.' );
		$this->assertSame( '', get_userdata( $user_id )->user_url, 'The URL should be cleared.' );
	}

	/**
	 * The slug is cleaned as a title before it is saved, so a slash becomes a hyphen,
	 * also when that gives the slug the user already has.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_slugs_before_a_slug_with_a_slash
	 *
	 * @param string $slug The slug of the user before the update.
	 */
	public function test_saves_a_slash_in_the_slug_as_a_hyphen( string $slug ): void {
		$user_id = $this->create_target( 'uwtslash', array( 'user_nicename' => $slug ) );

		$this->set_permalink_structure( '/%postname%/' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$fields = array( 'slug', 'link' );
		$result = $this->run_ability( $this->update_input( $user_id, array( 'slug' => 'Team/Desk' ), $fields ) );

		$this->assert_user_item( $result, $fields, 'Updating with a slash in the slug' );
		$this->assertSame( 'team-desk', get_userdata( $user_id )->user_nicename, 'The slash should be stored as a hyphen.' );
		$this->assertSame( 'team-desk', $result['slug'], 'The output should show the stored slug.' );
		$this->assertSame( get_author_posts_url( $user_id, 'team-desk' ), $result['link'], 'The link should be the author URL of the stored slug.' );
	}

	/**
	 * Returns the slugs a user has before `Team/Desk` is sent as the slug.
	 *
	 * @return array<string, array{slug: string}> The slugs.
	 */
	public static function data_slugs_before_a_slug_with_a_slash(): array {
		return array(
			'another slug'                      => array( 'uwtslash' ),
			'the same slug in another spelling' => array( 'team-desk' ),
		);
	}

	/**
	 * A caller who may only change roles reads back the ID only, or the public fields
	 * when they may list users.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_callers_who_may_only_change_roles
	 *
	 * @param string       $caller The caller, as a fixture name.
	 * @param list<string> $keys   The keys of the output.
	 */
	public function test_output_of_callers_who_may_only_change_roles( string $caller, array $keys ): void {
		$user_id = $this->create_target( 'uwtpromoted' );

		$this->login_as( $caller );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( $user_id, array( 'roles' => array( 'editor' ) ), array( 'name', 'slug', 'username', 'email', 'roles' ) ) );

		$this->assertIsArray( $result, 'The roles should be changed.' );
		$this->assertSameSets( $keys, array_keys( $result ), 'Only the fields the caller may read should be returned.' );
		$this->assertSame( $user_id, $result['id'], 'The output should hold the ID.' );
		$this->assertSame( array( 'editor' ), $this->get_stored_roles( $user_id ), 'The user should be an editor.' );
	}

	/**
	 * Returns callers who may change roles but not edit users.
	 *
	 * @return array<string, array{caller: string, keys: list<string>}> The caller and the keys of the output.
	 */
	public static function data_callers_who_may_only_change_roles(): array {
		return array(
			'promoter'            => array( 'promoter', array( 'id' ) ),
			'lister and promoter' => array( 'lister_promoter', array( 'id', 'name', 'slug' ) ),
		);
	}

	/**
	 * On multisite a site administrator changes the roles of a super admin and reads
	 * back the public fields only.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 */
	public function test_site_administrator_changes_roles_of_a_super_admin(): void {
		$user_id = $this->create_target( 'uwtsuperroles', array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( $user_id, array( 'roles' => array( 'editor' ) ), array( 'name', 'username', 'roles' ) ) );

		$this->assertIsArray( $result, 'The roles should be changed.' );
		$this->assertSameSets( array( 'id', 'name' ), array_keys( $result ), 'Only the public fields should be returned.' );
		$this->assertSame( array( 'editor' ), $this->get_stored_roles( $user_id ), 'The super admin should be an editor of the site.' );
		$this->assertTrue( is_super_admin( $user_id ), 'The user should stay a super admin.' );
	}

	/**
	 * On multisite profile attributes of another member need the network capability,
	 * and those of a super admin need a super admin.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_profile_changes_on_multisite
	 *
	 * @param string $caller      The caller, as a fixture name.
	 * @param bool   $super_admin Whether the target is a super admin.
	 * @param bool   $allowed     Whether the change is allowed.
	 */
	public function test_profile_changes_on_multisite( string $caller, bool $super_admin, bool $allowed ): void {
		$user_id = $this->create_target( 'uwtnetwork' );
		if ( $super_admin ) {
			grant_super_admin( $user_id );
		}

		$this->login_as( $caller );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability( $this->update_input( $user_id, array( 'first_name' => 'Changed' ) ) );

		if ( $allowed ) {
			$this->assertIsArray( $result, 'The change should be allowed.' );
			$this->assertSame( 'Changed', get_userdata( $user_id )->first_name, 'The first name should be stored.' );
		} else {
			$this->assert_core_error( $result, 'user_cannot_edit', 'Changing a profile without the network capability' );
			$this->assert_unchanged( $before, 'Changing a profile without the network capability' );
		}
	}

	/**
	 * Returns profile changes on multisite.
	 *
	 * @return array<string, array{caller: string, super_admin: bool, allowed: bool}>
	 *         The caller, whether the target is a super admin, and whether it is allowed.
	 */
	public static function data_profile_changes_on_multisite(): array {
		return array(
			'site administrator, member'       => array( 'administrator', false, false ),
			'site administrator, super admin'  => array( 'administrator', true, false ),
			'network user editor, super admin' => array( 'network_editor', true, false ),
			'network user editor, member'      => array( 'network_editor', false, true ),
			'super admin, super admin'         => array( 'manager', true, true ),
		);
	}

	/**
	 * A save that fails refuses the call, and the roles stay.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_failing_saves
	 *
	 * @param string   $hook     The filter.
	 * @param callable $callback The filter callback.
	 * @param string   $code     The error code.
	 */
	public function test_refuses_a_failing_save( string $hook, callable $callback, string $code ): void {
		$user_id = $this->create_target( 'uwtfailing' );
		add_filter( $hook, $callback );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_error(
			$this->run_ability(
				$this->update_input(
					$user_id,
					array(
						'name'  => 'Failing',
						'roles' => array( 'editor' ),
					)
				)
			),
			$code,
			500,
			'Updating with a failing save'
		);
		$this->assert_unchanged( $before, 'Updating with a failing save' );
		$this->assertSame( array( 'author' ), $this->get_stored_roles( $user_id ), 'The roles should stay.' );
	}

	/**
	 * Returns filters that make the save fail.
	 *
	 * @return array<string, array{hook: string, callback: callable, code: string}> The filter,
	 *                                                                              its callback and the error code.
	 */
	public static function data_failing_saves(): array {
		return array(
			'empty username' => array( 'pre_user_login', '__return_empty_string', 'empty_user_login' ),
			'empty data'     => array( 'wp_pre_insert_user_data', '__return_empty_array', 'empty_data' ),
		);
	}

	/**
	 * Input that the schema refuses gets the schema error, and nothing changes.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_inputs_the_schema_refuses
	 *
	 * @param array<string, mixed> $input The input, where `where.id` 0 stands for the target.
	 */
	public function test_refuses_input_the_schema_refuses( array $input ): void {
		$user_id = $this->create_target( 'uwtschema' );
		if ( isset( $input['where']['id'] ) && 'target' === $input['where']['id'] ) {
			$input['where']['id'] = $user_id;
		}

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_invalid_input( $this->run_ability( $input ), 'Updating with input the schema refuses' );
		$this->assert_unchanged( $before, 'Updating with input the schema refuses' );
	}

	/**
	 * Returns inputs the schema refuses; the ID `target` stands for the user to update.
	 *
	 * @return array<string, array{input: array<string, mixed>}> The inputs.
	 */
	public static function data_inputs_the_schema_refuses(): array {
		$where = array( 'id' => 'target' );
		$rows  = array(
			'no where'             => array( array( 'data' => array( 'name' => 'Schema' ) ) ),
			'no data'              => array( array( 'where' => $where ) ),
			'empty data'           => array(
				array(
					'where' => $where,
					'data'  => array(),
				),
			),
			'ID of zero'           => array(
				array(
					'where' => array( 'id' => 0 ),
					'data'  => array( 'name' => 'Schema' ),
				),
			),
			'ID as text'           => array(
				array(
					'where' => array( 'id' => 'abc' ),
					'data'  => array( 'name' => 'Schema' ),
				),
			),
			'unknown where key'    => array(
				array(
					'where' => array(
						'id'   => 'target',
						'slug' => 'uwtschema',
					),
					'data'  => array( 'name' => 'Schema' ),
				),
			),
			'username'             => array(
				array(
					'where' => $where,
					'data'  => array( 'username' => 'uwtrenamed' ),
				),
			),
			'empty name'           => array(
				array(
					'where' => $where,
					'data'  => array( 'name' => '' ),
				),
			),
			'invalid email'        => array(
				array(
					'where' => $where,
					'data'  => array( 'email' => 'not-an-email' ),
				),
			),
			'locale not installed' => array(
				array(
					'where' => $where,
					'data'  => array( 'locale' => 'klingon' ),
				),
			),
			'empty roles'          => array(
				array(
					'where' => $where,
					'data'  => array( 'roles' => array() ),
				),
			),
			'unregistered role'    => array(
				array(
					'where' => $where,
					'data'  => array( 'roles' => array( 'BeSharp' ) ),
				),
			),
			'repeated role'        => array(
				array(
					'where' => $where,
					'data'  => array( 'roles' => array( 'editor', 'editor' ) ),
				),
			),
			'unknown field'        => array(
				array(
					'where'  => $where,
					'data'   => array( 'name' => 'Schema' ),
					'fields' => array( 'password' ),
				),
			),
		);

		foreach ( self::data_keys_outside_the_allow_list() as $name => $row ) {
			$rows[ "data with {$name}" ] = array(
				array(
					'where' => $where,
					'data'  => array( $row[0] => $row[1] ),
				),
			);
		}

		return $rows;
	}

	/**
	 * With schema validation skipped, the callback refuses what the schema would.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_inputs_checked_when_validation_is_skipped
	 *
	 * @param array<string, mixed> $data    The attributes.
	 * @param string               $param   The input path.
	 * @param string|null          $message The message, or null when it is not fixed.
	 */
	public function test_checks_input_when_validation_is_skipped( array $data, string $param, ?string $message ): void {
		$user_id = $this->create_target( 'uwtskipped' );

		$this->skip_input_validation();
		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_value_error( $this->run_ability( $this->update_input( $user_id, $data ) ), 'user_invalid_attribute', $param, 'Updating without validation', $message );
		$this->assert_unchanged( $before, 'Updating without validation' );
	}

	/**
	 * Returns attributes that only the callback can refuse once validation is skipped.
	 *
	 * @return array<string, array{data: array<string, mixed>, param: string, message: string|null}>
	 *         The attributes, the input path and the message.
	 */
	public static function data_inputs_checked_when_validation_is_skipped(): array {
		return array(
			'no attributes'        => array( array(), 'data', null ),
			'only unknown keys'    => array( array( 'password' => 'uwt-chosen-password' ), 'data', null ),
			'empty roles'          => array( array( 'roles' => array() ), 'data[roles]', null ),
			'unregistered role'    => array( array( 'roles' => array( 'uwt_unregistered' ) ), 'data[roles]', 'The role uwt_unregistered does not exist.' ),
			'invalid email'        => array( array( 'email' => 'not-an-email' ), 'data[email]', null ),
			'locale not installed' => array( array( 'locale' => 'klingon' ), 'data[locale]', null ),
		);
	}

	/**
	 * Keys outside the attributes never reach the save, when validation is skipped and
	 * when the callback is called directly; the username and password stay.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_ways_past_the_schema
	 *
	 * @param bool $direct Whether to call the callback directly.
	 */
	public function test_ignores_keys_outside_the_allow_list( bool $direct ): void {
		$user_id   = $this->create_target( 'uwtallowlist' );
		$stored    = get_userdata( $user_id );
		$hash      = $stored->user_pass;
		$callbacks = $this->get_ability_callbacks();
		$this->login_as( 'manager' );

		$saved_logins = array();
		add_filter(
			'wp_pre_insert_user_data',
			static function ( $data, $update, $id, $userdata ) use ( &$saved_logins ) {
				$saved_logins[] = $userdata['user_login'] ?? null;
				return $data;
			},
			10,
			4
		);

		$fields = array(
			'username'        => 'uwtrenamed',
			'user_login'      => 'uwtsentlogin',
			'user_nicename'   => 'uwt-sent-slug',
			'user_registered' => '2001-02-03 04:05:06',
			'display_name'    => 'Sent Display Name',
		);
		$input  = $this->update_input( $user_id, array_merge( array( 'first_name' => 'Allowed' ), $this->get_keys_outside_the_allow_list(), $fields ) );
		if ( $direct ) {
			$result = $callbacks['execute_callback']( $input );
		} else {
			$this->skip_input_validation();
			$result = $this->run_ability( $input );
		}

		$this->assertIsArray( $result, 'The attributes should be applied.' );

		$user = get_userdata( $user_id );
		$this->assertSame( 'Allowed', $user->first_name, 'The first name should be stored.' );
		$this->assertSame( $stored->user_login, $user->user_login, 'The username should stay.' );
		$this->assertSame( $stored->user_nicename, $user->user_nicename, 'The slug should stay.' );
		$this->assertSame( $stored->user_registered, $user->user_registered, 'The registration date should stay.' );
		$this->assertSame( $stored->display_name, $user->display_name, 'The display name should stay.' );
		$this->assertSame( array( $stored->user_login ), array_unique( $saved_logins ), 'The save should receive the stored login.' );
		$this->assertSame( array( 'author' ), $this->get_stored_roles( $user_id ), 'The roles should stay.' );
		$this->assert_keys_outside_the_allow_list_ignored( $user_id, $hash, 'Updating with keys outside the attributes' );
	}

	/**
	 * Returns the ways past the schema.
	 *
	 * @return array<string, array{direct: bool}> Whether to call the callback directly.
	 */
	public static function data_ways_past_the_schema(): array {
		return array(
			'validation skipped' => array( false ),
			'direct call'        => array( true ),
		);
	}

	/**
	 * Core's user hooks fire as the caller, and the hooks of the other ways of updating
	 * users do not.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_fires_core_hooks_only(): void {
		$user_id = $this->create_target( 'uwthooks' );

		$fired = array();
		foreach ( array( 'rest_pre_insert_user', 'rest_insert_user', 'rest_after_insert_user', 'rest_prepare_user' ) as $hook ) {
			add_filter(
				$hook,
				static function ( $value ) use ( &$fired, $hook ) {
					$fired[] = $hook;
					return $value;
				}
			);
		}

		$current_users = array();
		add_action(
			'profile_update',
			static function () use ( &$current_users ) {
				$current_users[] = get_current_user_id();
			}
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assertIsArray( $this->run_ability( $this->update_input( $user_id, array( 'name' => 'Hooked' ) ) ), 'The user should be updated.' );
		$this->assertSame( array( self::$user_ids['manager'] ), $current_users, 'The profile_update action should fire once, as the caller.' );
		$this->assertSame( array(), $fired, 'No hook of the other ways of updating users should fire.' );
	}
}
