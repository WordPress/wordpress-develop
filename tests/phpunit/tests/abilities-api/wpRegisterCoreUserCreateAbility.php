<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-user-write-ability.php';

/**
 * Tests for the core/user-create ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Users
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreUserCreateAbility extends WP_Abilities_User_Write_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/user-create';

	/**
	 * Builds the input that creates a user.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $username The username, also used for the email.
	 * @param array<string, mixed> $data     Optional. More attributes. Default empty array.
	 * @return array<string, mixed> The input.
	 */
	private function create_input( string $username, array $data = array() ): array {
		return array(
			'data' => array_merge(
				array(
					'username' => $username,
					'email'    => "{$username}@example.test",
				),
				$data
			),
		);
	}

	/**
	 * Asserts that no user with the given username exists.
	 *
	 * @since 7.3.0
	 *
	 * @param string $username The username.
	 * @param string $context  What the call was, for the assertion messages.
	 */
	private function assert_no_user( string $username, string $context ): void {
		$this->assertFalse( get_user_by( 'login', $username ), "{$context}: no user should be created." );
	}

	/**
	 * The ability is registered in the user category with its label and annotations.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_registers_the_ability(): void {
		$this->assert_registration( 'Create User', false, false );
	}

	/**
	 * The input schema takes the attributes in `data`, with the username and the email
	 * required, and the fields of core/users-query.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_input_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The create ability should be registered.' );

		$expected = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'data' ),
			'properties'           => array(
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'username', 'email' ),
					'properties'           => array_merge(
						array(
							'username' => array(
								'type'      => 'string',
								'minLength' => 1,
							),
						),
						$this->get_profile_property_schemas()
					),
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
	 * A user is created with the given attributes and roles, and the output holds
	 * exactly the requested fields.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_creates_a_user_with_chosen_fields(): void {
		$this->login_as( 'manager' );
		$this->register_abilities();

		$fields = array( 'name', 'slug', 'username', 'email', 'registered', 'roles' );
		$result = $this->run_ability(
			array(
				'data'   => array(
					'username'   => 'uwtmcarter',
					'email'      => 'm.carter@example.test',
					'name'       => 'Maya Carter',
					'first_name' => 'Maya',
					'last_name'  => 'Carter',
					'roles'      => array( 'editor' ),
				),
				'fields' => $fields,
			)
		);

		$user_id = $this->assert_user_item( $result, $fields, 'Creating an editor' );
		$user    = get_userdata( $user_id );

		$this->assertSameSets( array_merge( array( 'id' ), $fields ), array_keys( $result ), 'The output should hold exactly the requested fields and the ID.' );
		$this->assertSame( 'uwtmcarter', $user->user_login, 'The username should be stored.' );
		$this->assertSame( 'm.carter@example.test', $user->user_email, 'The email should be stored.' );
		$this->assertSame( 'Maya Carter', $user->display_name, 'The name should be stored.' );
		$this->assertSame( 'Maya', $user->first_name, 'The first name should be stored.' );
		$this->assertSame( 'Carter', $user->last_name, 'The last name should be stored.' );
		$this->assertSame( array( 'editor' ), $result['roles'], 'The output should show the editor role.' );
		$this->assertSame( array( 'editor' ), array_values( $user->roles ), 'The user should be an editor only.' );
	}

	/**
	 * The minimum input gives the default fields, takes the name, nickname and slug from
	 * the username, gives the default role, sends the two mails without the password,
	 * and sets a generated password.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_creates_a_user_from_the_minimum_input(): void {
		update_option( 'default_role', 'author' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$result  = $this->run_ability( $this->create_input( 'uwtjdoe' ) );
		$user_id = $this->assert_user_item( $result, array(), 'Creating a user from the minimum input' );
		$user    = get_userdata( $user_id );

		$this->assertSameSets( self::DEFAULT_FIELDS, array_keys( $result ), 'The output should hold the default fields.' );
		$this->assertSame( 'uwtjdoe', $user->display_name, 'The name should be the username.' );
		$this->assertSame( 'uwtjdoe', $user->nickname, 'The nickname should be the username.' );
		$this->assertSame( 'uwtjdoe', $user->user_nicename, 'The slug should be the username.' );

		if ( is_multisite() ) {
			$this->assertTrue( is_user_member_of_blog( $user_id ), 'The user should be a member of the current site.' );
			$this->assertSame( array(), array_values( $user->roles ), 'The user should have no role on multisite.' );
		} else {
			$this->assertSame( array( 'author' ), array_values( $user->roles ), 'The user should have the default role.' );
		}

		$this->assertNotEmpty( $this->generated_passwords, 'A password of 24 characters should be generated.' );
		$this->assertTrue( wp_check_password( end( $this->generated_passwords ), $user->user_pass, $user_id ), 'The generated password should be the stored one.' );
		$this->assert_password_not_leaked( $result, 'Creating a user from the minimum input' );

		$site  = $this->get_mail_site_name();
		$mails = $this->get_mails();
		$this->assertSameSets( array( "[{$site}] New User Registration", "[{$site}] Login Details" ), wp_list_pluck( $mails, 'subject' ), 'Exactly the notice and the login details should be sent.' );

		foreach ( $mails as $mail ) {
			if ( "[{$site}] New User Registration" === $mail['subject'] ) {
				$this->assertSame( array( strtolower( get_option( 'admin_email' ) ) ), $mail['to'], 'The notice should go to the site admin email.' );
				$this->assertStringContainsString( 'uwtjdoe', $mail['body'], 'The notice should hold the username.' );
				$this->assertStringContainsString( 'uwtjdoe@example.test', $mail['body'], 'The notice should hold the email.' );
			} else {
				$this->assertSame( array( 'uwtjdoe@example.test' ), $mail['to'], 'The login details should go to the new user.' );
				$this->assertStringContainsString( 'uwtjdoe', $mail['body'], 'The login details should hold the username.' );
				$this->assertStringContainsString( 'action=rp', $mail['body'], 'The login details should link to setting a password.' );
			}
		}
	}

	/**
	 * On multisite the network admin also gets Core's notice when it is on.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 */
	public function test_sends_the_network_notice_when_it_is_on(): void {
		update_site_option( 'registrationnotification', 'yes' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$result = $this->run_ability( $this->create_input( 'uwtnotice' ) );

		$this->assertIsArray( $result, 'The user should be created.' );

		$site     = $this->get_mail_site_name();
		$subjects = wp_list_pluck( $this->get_mails(), 'subject' );
		$this->assertSameSets( array( "[{$site}] New User Registration", "[{$site}] Login Details", 'New User Registration: uwtnotice' ), $subjects, 'The network notice should be sent as well.' );
		$this->assert_password_not_leaked( $result, 'Creating a user with the network notice on' );
	}

	/**
	 * The user ends with exactly the given roles, in the given order, also when they are
	 * sent as a comma-separated string.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_role_lists
	 *
	 * @param list<string>|string $sent  The roles as sent.
	 * @param list<string>        $roles The roles the user should hold.
	 */
	public function test_gives_the_roles_in_order( $sent, array $roles ): void {
		$this->login_as( 'manager' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'username' => 'uwtroles',
					'email'    => 'uwtroles@example.test',
					'roles'    => $sent,
				),
				'fields' => array( 'roles' ),
			)
		);

		$this->assertIsArray( $result, 'The user should be created.' );
		$this->assertSame( $roles, $result['roles'], 'The output should show the roles in order.' );
		$this->assertSame( $roles, array_values( get_userdata( $result['id'] )->roles ), 'The user should hold exactly the roles, in order.' );
	}

	/**
	 * Returns lists of roles.
	 *
	 * @return array<string, array{sent: list<string>|string, roles: list<string>}> The roles as
	 *                                                                              sent and as held.
	 */
	public static function data_role_lists(): array {
		return array(
			'one role'              => array( array( 'editor' ), array( 'editor' ) ),
			'two roles'             => array( array( 'author', 'editor' ), array( 'author', 'editor' ) ),
			'three roles'           => array( array( 'subscriber', 'contributor', 'author' ), array( 'subscriber', 'contributor', 'author' ) ),
			'comma-separated roles' => array( 'subscriber,contributor,author', array( 'subscriber', 'contributor', 'author' ) ),
		);
	}

	/**
	 * A caller without `create_users` is refused by the permission check, and gets the
	 * schema error for invalid input.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_callers_without_create_users
	 *
	 * @param string $caller The caller, as a fixture name, or empty when logged out.
	 */
	public function test_refuses_callers_without_create_users( string $caller ): void {
		$this->login_as( $caller );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_refused( $this->run_ability( $this->create_input( 'uwtrefused' ) ), 'Creating without create_users' );
		$this->assert_unchanged( $before, 'Creating without create_users' );

		$this->assert_invalid_input( $this->run_ability( array( 'data' => array( 'username' => 'uwtrefused' ) ) ), 'Creating without create_users and without an email' );
	}

	/**
	 * Returns callers without `create_users`.
	 *
	 * @return array<string, array{caller: string}> The callers.
	 */
	public static function data_callers_without_create_users(): array {
		return array(
			'editor'      => array( 'editor' ),
			'author'      => array( 'author' ),
			'contributor' => array( 'contributor' ),
			'subscriber'  => array( 'subscriber' ),
			'promoter'    => array( 'promoter' ),
			'logged out'  => array( '' ),
		);
	}

	/**
	 * On multisite a site administrator cannot create users while the network does not
	 * let site administrators add users.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 */
	public function test_refuses_a_site_administrator_while_the_network_does_not_let_them_add_users(): void {
		update_site_option( 'add_new_users', 0 );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_refused( $this->run_ability( $this->create_input( 'uwtsiteadmin' ) ), 'A site administrator creating a user' );
		$this->assert_unchanged( $before, 'A site administrator creating a user' );
	}

	/**
	 * The callback checks `create_users` again, whatever the input holds.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_callers_and_inputs_behind_the_gate
	 *
	 * @param string               $caller The caller, as a fixture name, or empty when logged out.
	 * @param array<string, mixed> $data   The `data`.
	 */
	public function test_checks_create_users_again( string $caller, array $data ): void {
		$callbacks = $this->get_ability_callbacks();
		$this->login_as( $caller );

		$before = $this->snapshot();

		$this->assert_core_error( $callbacks['execute_callback']( array( 'data' => $data ) ), 'user_cannot_create', 'Creating behind the gate' );
		$this->assert_unchanged( $before, 'Creating behind the gate' );
	}

	/**
	 * Returns callers without `create_users` and inputs.
	 *
	 * @return array<string, array{caller: string, data: array<string, mixed>}> The callers and `data`.
	 */
	public static function data_callers_and_inputs_behind_the_gate(): array {
		$valid   = array(
			'username' => 'uwtbehind',
			'email'    => 'uwtbehind@example.test',
		);
		$invalid = array(
			'username' => 'uwt_editor',
			'email'    => 'not-an-email',
			'roles'    => array( 'administrator' ),
		);

		return array(
			'subscriber, valid input'   => array( 'subscriber', $valid ),
			'subscriber, invalid input' => array( 'subscriber', $invalid ),
			'logged out, valid input'   => array( '', $valid ),
			'logged out, invalid input' => array( '', $invalid ),
		);
	}

	/**
	 * A role that was removed after the ability was registered is refused.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_refuses_a_role_removed_after_registration(): void {
		add_role( 'uwt_gone', 'Gone', array( 'read' => true ) );
		$this->login_as( 'manager' );
		$this->register_abilities();
		remove_role( 'uwt_gone' );

		$before = $this->snapshot();

		$this->assert_value_error(
			$this->run_ability( $this->create_input( 'uwtgone', array( 'roles' => array( 'uwt_gone' ) ) ) ),
			'user_invalid_attribute',
			'data[roles]',
			'Creating with a removed role',
			'The role uwt_gone does not exist.'
		);
		$this->assert_unchanged( $before, 'Creating with a removed role' );
	}

	/**
	 * A role that is not editable is refused, also next to an editable one.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_roles_with_one_not_editable
	 *
	 * @param list<string> $roles   The roles; `editor` is not editable.
	 * @param bool         $keyed   Whether the filter keeps the key of `editor` with an
	 *                              empty entry instead of removing it.
	 */
	public function test_refuses_a_role_that_is_not_editable( array $roles, bool $keyed ): void {
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

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_core_error( $this->run_ability( $this->create_input( 'uwtnoteditable', array( 'roles' => $roles ) ) ), 'user_cannot_assign_role', 'Creating with a role that is not editable' );
		$this->assert_unchanged( $before, 'Creating with a role that is not editable' );
	}

	/**
	 * Returns role lists that hold `editor`.
	 *
	 * @return array<string, array{roles: list<string>, keyed: bool}> The roles, and whether
	 *                                                               the entry is kept empty.
	 */
	public static function data_roles_with_one_not_editable(): array {
		return array(
			'only the role'              => array( array( 'editor' ), false ),
			'after another'              => array( array( 'author', 'editor' ), false ),
			'before another'             => array( array( 'editor', 'author' ), false ),
			'entry set to false'         => array( array( 'editor' ), true ),
			'entry false, after another' => array( array( 'author', 'editor' ), true ),
		);
	}

	/**
	 * Attributes that cannot be applied are refused before the save or the signup
	 * validation runs.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_attributes_refused_on_create
	 *
	 * @param array<string, string> $data    The attributes.
	 * @param string                $code    The error code.
	 * @param string                $param   The input path.
	 * @param string|null           $message The message, or null when it is not fixed.
	 * @param bool                  $accept  Optional. Whether a filter accepts every username. Default false.
	 */
	public function test_refuses_attributes( array $data, string $code, string $param, ?string $message, bool $accept = false ): void {
		add_filter(
			'illegal_user_logins',
			static function () {
				return array( 'uwtnope' );
			}
		);
		if ( $accept ) {
			add_filter( 'validate_username', '__return_true' );
		}

		$this->login_as( 'manager' );
		$this->register_abilities();

		if ( self::LONG_NAME_MESSAGE === $message ) {
			$message = $this->get_long_name_message( $this->create_input( 'uwtrefused', array( 'name' => str_repeat( 'a', 251 ) ) ) );
		}

		$this->create_target( 'uwtteamdesk', array( 'user_nicename' => 'team-desk' ) );

		$before = $this->snapshot();

		$this->watch_filter( 'pre_user_login' );
		$this->watch_filter( 'wpmu_validate_user_signup' );

		$this->assert_value_error( $this->run_ability( $this->create_input( 'uwtrefused', $data ) ), $code, $param, 'Creating with a refused attribute', $message );
		$this->assert_unchanged( $before, 'Creating with a refused attribute' );
		$this->assert_filter_not_run( 'pre_user_login', 'Creating with a refused attribute' );
		$this->assert_filter_not_run( 'wpmu_validate_user_signup', 'Creating with a refused attribute' );
	}

	/**
	 * Returns attributes refused on create.
	 *
	 * @return array<string, array{0: array<string, string>, 1: string, 2: string, 3: string|null, 4?: bool}>
	 *         The attributes, the error code, the input path, the message, and whether a
	 *         filter accepts every username.
	 */
	public static function data_attributes_refused_on_create(): array {
		return array_merge(
			array(
				'username changed by sanitizing'   => array( array( 'username' => 'uwt new!' ), 'user_invalid_attribute', 'data[username]', null ),
				'username changed by sanitizing, accepted by a filter' => array( array( 'username' => 'uwtnew!' ), 'user_invalid_attribute', 'data[username]', null, true ),
				'illegal username'                 => array( array( 'username' => 'uwtnope' ), 'user_invalid_attribute', 'data[username]', null ),
				'illegal username in another case' => array( array( 'username' => 'UWTNOPE' ), 'user_invalid_attribute', 'data[username]', null ),
				'username of 61 characters'        => array( array( 'username' => str_repeat( 'a', 61 ) ), 'user_invalid_attribute', 'data[username]', null ),
				'username of another user'         => array( array( 'username' => 'uwt_editor' ), 'user_exists', 'data[username]', 'Sorry, that username already exists!' ),
			),
			self::data_profile_attributes_refused()
		);
	}

	/**
	 * An email of 101 characters and a name of 251 characters are refused by the schema,
	 * and with the error about the value when validation is skipped, before the save or
	 * the signup validation runs.
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
		if ( $skip_validation ) {
			$this->skip_input_validation();
		}

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->watch_filter( 'pre_user_login' );
		$this->watch_filter( 'wpmu_validate_user_signup' );

		$this->assert_over_the_length_limit( $this->run_ability( $this->create_input( 'uwtlong', $data ) ), $skip_validation, $param, 'Creating with a value over the length limit' );
		$this->assert_unchanged( $before, 'Creating with a value over the length limit' );
		$this->assert_filter_not_run( 'pre_user_login', 'Creating with a value over the length limit' );
		$this->assert_filter_not_run( 'wpmu_validate_user_signup', 'Creating with a value over the length limit' );
	}

	/**
	 * The role checks run before the attribute checks, and the attribute checks run in
	 * order; the first failure is the error.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_inputs_with_two_failures
	 *
	 * @param array<string, mixed> $data    The attributes.
	 * @param string               $code    The error code.
	 * @param string|null          $param   The input path, or null for an error without one.
	 * @param string|null          $message The message, or null when it is not fixed.
	 */
	public function test_reports_the_first_failing_check( array $data, string $code, ?string $param, ?string $message ): void {
		add_filter(
			'editable_roles',
			static function ( $roles ) {
				unset( $roles['editor'] );
				return $roles;
			}
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability( $this->create_input( 'uwtorder', $data ) );

		if ( null === $param ) {
			$this->assert_core_error( $result, $code, 'Creating with two failures' );
		} else {
			$this->assert_value_error( $result, $code, $param, 'Creating with two failures', $message );
		}
		$this->assert_unchanged( $before, 'Creating with two failures' );
	}

	/**
	 * Returns attributes with two failures, and the first one.
	 *
	 * @return array<string, array{data: array<string, mixed>, code: string, param: string|null, message: string|null}>
	 *         The attributes, the error code, the input path and the message.
	 */
	public static function data_inputs_with_two_failures(): array {
		return array(
			'role before username'  => array(
				array(
					'username' => 'uwt_editor',
					'roles'    => array( 'editor' ),
				),
				'user_cannot_assign_role',
				null,
				null,
			),
			'username before email' => array(
				array(
					'username' => 'uwt_editor',
					'email'    => 'uwt-author@example.test',
				),
				'user_exists',
				'data[username]',
				'Sorry, that username already exists!',
			),
			'email before name'     => array(
				array(
					'email' => 'uwt-author@example.test',
					'name'  => str_repeat( 'a', 249 ) . '&',
				),
				'user_exists',
				'data[email]',
				'Sorry, that email address is already used!',
			),
			'name before nickname'  => array(
				array(
					'name'     => str_repeat( 'a', 249 ) . '&',
					'nickname' => '<i></i>',
				),
				'user_invalid_attribute',
				'data[name]',
				null,
			),
			'nickname before slug'  => array(
				array(
					'nickname' => '<i></i>',
					'slug'     => '!!!',
				),
				'user_invalid_attribute',
				'data[nickname]',
				null,
			),
			'slug before url'       => array(
				array(
					'slug' => '!!!',
					'url'  => 'javascript:alert(1)',
				),
				'user_invalid_attribute',
				'data[slug]',
				'Invalid slug.',
			),
		);
	}

	/**
	 * Values at the length limits are accepted and stored.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_accepts_values_at_the_length_limits(): void {
		$data = array(
			'username' => str_repeat( 'a', 60 ),
			'email'    => str_repeat( 'b', 87 ) . '@example.test',
			'name'     => str_repeat( 'c', 250 ),
			'slug'     => str_repeat( 'd', 50 ),
			'url'      => 'https://example.test/' . str_repeat( 'e', 79 ),
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'data' => $data ) );

		$this->assertIsArray( $result, 'Values at the limits should be accepted.' );

		$user = get_userdata( $result['id'] );
		$this->assertSame( $data['username'], $user->user_login, 'The username of 60 characters should be stored.' );
		$this->assertSame( $data['email'], $user->user_email, 'The email of 100 characters should be stored.' );
		$this->assertSame( $data['name'], $user->display_name, 'The name of 250 characters should be stored.' );
		$this->assertSame( $data['slug'], $user->user_nicename, 'The slug of 50 characters should be stored.' );
		$this->assertSame( $data['url'], $user->user_url, 'The URL of 100 characters should be stored.' );
	}

	/**
	 * Quotes and backslashes in text values are stored as given.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_stores_quotes_and_backslashes(): void {
		$this->login_as( 'manager' );
		$this->register_abilities();

		$fields = array( 'name', 'description', 'first_name', 'last_name', 'nickname' );
		$result = $this->run_ability(
			array_merge(
				$this->create_input( 'uwtquotes', $this->get_quoted_values() ),
				array( 'fields' => $fields )
			)
		);

		$user_id = $this->assert_user_item( $result, $fields, 'Creating with quotes and backslashes' );
		$this->assert_quoted_values_stored( $user_id, 'Creating with quotes and backslashes' );
	}

	/**
	 * The slug is cleaned as a title before it is saved, so a slash becomes a hyphen.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_saves_a_slash_in_the_slug_as_a_hyphen(): void {
		$this->set_permalink_structure( '/%postname%/' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$fields = array( 'slug', 'link' );
		$result = $this->run_ability(
			array_merge(
				$this->create_input( 'uwtslash', array( 'slug' => 'Team/Desk' ) ),
				array( 'fields' => $fields )
			)
		);

		$user_id = $this->assert_user_item( $result, $fields, 'Creating with a slash in the slug' );

		$this->assertSame( 'team-desk', get_userdata( $user_id )->user_nicename, 'The slash should be stored as a hyphen.' );
		$this->assertSame( 'team-desk', $result['slug'], 'The output should show the stored slug.' );
		$this->assertSame( get_author_posts_url( $user_id, 'team-desk' ), $result['link'], 'The link should be the author URL of the stored slug.' );
	}

	/**
	 * On multisite a site administrator who may add users reads back the public fields
	 * of the new user only.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 */
	public function test_site_administrator_reads_back_public_fields(): void {
		update_site_option( 'add_new_users', 1 );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability(
			array_merge(
				$this->create_input( 'uwtsiteadded' ),
				array( 'fields' => array( 'name', 'slug', 'username', 'email', 'roles', 'registered' ) )
			)
		);

		$this->assertIsArray( $result, 'The site administrator should create the user.' );
		$this->assertSameSets( array( 'id', 'name', 'slug' ), array_keys( $result ), 'Only the public fields should be returned.' );
		$this->assertInstanceOf( WP_User::class, get_user_by( 'login', 'uwtsiteadded' ), 'The user should be created.' );
	}

	/**
	 * A caller who may create users but neither edit nor list them reads back the ID only.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_caller_without_list_users_reads_back_the_id_only(): void {
		if ( is_multisite() ) {
			update_site_option( 'add_new_users', 1 );
		}

		$this->login_as( 'creator' );
		$this->register_abilities();

		$result = $this->run_ability(
			array_merge(
				$this->create_input( 'uwtcreated' ),
				array( 'fields' => array( 'name', 'username', 'email' ) )
			)
		);

		$user = get_user_by( 'login', 'uwtcreated' );

		$this->assertInstanceOf( WP_User::class, $user, 'The user should be created.' );
		$this->assertSame( array( 'id' => $user->ID ), $result, 'The output should hold the ID only.' );
	}

	/**
	 * On multisite the new user becomes a member of the current site only.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 */
	public function test_adds_the_user_to_the_current_site_only(): void {
		$this->login_as( 'manager' );
		$this->register_abilities();

		$main_id = $this->run_ability( $this->create_input( 'uwtmainsite' ) )['id'] ?? 0;

		switch_to_blog( self::$site_id );
		$sub_id = $this->run_ability( $this->create_input( 'uwtsubsite' ) )['id'] ?? 0;
		restore_current_blog();

		$this->assertTrue( is_user_member_of_blog( $main_id, get_current_blog_id() ), 'A user created on the main site should be a member of it.' );
		$this->assertFalse( is_user_member_of_blog( $main_id, self::$site_id ), 'A user created on the main site should not be a member of the other site.' );
		$this->assertTrue( is_user_member_of_blog( $sub_id, self::$site_id ), 'A user created on the other site should be a member of it.' );
		$this->assertFalse( is_user_member_of_blog( $sub_id, get_current_blog_id() ), 'A user created on the other site should not be a member of the main site.' );
	}

	/**
	 * On multisite what Core's signup validation refuses is refused with its message,
	 * before the save runs.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_signups_refused
	 *
	 * @param string $username  The username.
	 * @param string $email     The email.
	 * @param string $core_code The code of Core's error to take the message from.
	 * @param string $param     The input path.
	 */
	public function test_refuses_what_core_signup_validation_refuses( string $username, string $email, string $core_code, string $param ): void {
		update_site_option( 'banned_email_domains', array( 'banned.test' ) );
		add_filter(
			'wpmu_validate_user_signup',
			static function ( $result ) {
				if ( 'uwtfiltered' === $result['user_name'] ) {
					$result['errors']->add( 'uwt_refused', 'Refused by a filter.' );
				}
				return $result;
			}
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$expected = wpmu_validate_user_signup( $username, $email )['errors']->get_error_message( $core_code );
		$this->assertNotSame( '', $expected, 'Core should refuse the signup.' );

		$before = $this->snapshot();

		$this->watch_filter( 'pre_user_login' );

		$this->assert_value_error(
			$this->run_ability(
				array(
					'data' => array(
						'username' => $username,
						'email'    => $email,
					),
				)
			),
			'user_invalid_attribute',
			$param,
			'Creating what signup validation refuses',
			$expected
		);
		$this->assert_unchanged( $before, 'Creating what signup validation refuses' );
		$this->assert_filter_not_run( 'pre_user_login', 'Creating what signup validation refuses' );
	}

	/**
	 * Returns signups that Core refuses.
	 *
	 * @return array<string, array{username: string, email: string, core_code: string, param: string}>
	 *         The username, the email, Core's code and the input path.
	 */
	public static function data_signups_refused(): array {
		return array(
			'username with a dash'    => array( 'uwt-dash1', 'uwt-dash1@example.test', 'user_name', 'data[username]' ),
			'banned email domain'     => array( 'uwtbanned', 'uwtbanned@banned.test', 'user_email', 'data[email]' ),
			'username before email'   => array( 'uwt-dash2', 'uwt-dash2@banned.test', 'user_name', 'data[username]' ),
			'error added by a filter' => array( 'uwtfiltered', 'uwtfiltered@example.test', 'uwt_refused', 'data' ),
		);
	}

	/**
	 * On multisite, when adding the new user to the site fails, the user is deleted again
	 * and the error is returned.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_refusals_of_adding_to_the_site
	 *
	 * @param WP_Error|false $refusal The value of the filter.
	 * @param string         $code    The error code.
	 * @param string         $message The message.
	 * @param int            $status  The error status.
	 */
	public function test_deletes_the_user_when_adding_to_the_site_fails( $refusal, string $code, string $message, int $status ): void {
		update_site_option( 'registrationnotification', 'yes' );
		add_filter(
			'can_add_user_to_blog',
			static function () use ( $refusal ) {
				return $refusal;
			}
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability( $this->create_input( 'uwtnotadded' ) );

		$this->assert_error( $result, $code, $status, 'Creating while adding to the site fails' );
		$this->assertSame( $message, $result->get_error_message(), 'The message of the first error should be kept.' );
		$this->assert_no_user( 'uwtnotadded', 'Creating while adding to the site fails' );

		$after = $this->snapshot();
		$this->assertSame( array_diff_key( $before, array( 'mail' => true ) ), array_diff_key( $after, array( 'mail' => true ) ), 'Nothing else should change.' );
		$this->assertSame( array(), array_diff( $after['mail'], array( 'New User Registration: uwtnotadded' ) ), 'No mail but the network notice should be sent.' );
	}

	/**
	 * Returns values of the `can_add_user_to_blog` filter that refuse.
	 *
	 * @return array<string, array{refusal: WP_Error|false, code: string, message: string, status: int}>
	 *         The value, the error code, the message and the status.
	 */
	public static function data_refusals_of_adding_to_the_site(): array {
		return array(
			'error'             => array( new WP_Error( 'uwt_not_added', 'Not added by a filter.' ), 'uwt_not_added', 'Not added by a filter.', 500 ),
			'error with status' => array( new WP_Error( 'uwt_forbidden', 'Forbidden by a filter.', array( 'status' => 403 ) ), 'uwt_forbidden', 'Forbidden by a filter.', 403 ),
			'false'             => array( false, 'user_cannot_be_added', 'User cannot be added to this site.', 500 ),
		);
	}

	/**
	 * A save that fails refuses the call, and nothing is created or mailed.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_failing_saves
	 *
	 * @param string   $hook                 The filter.
	 * @param callable $callback             The filter callback.
	 * @param string   $single_site_code     The error code on single site.
	 * @param int      $single_site_status   The error status on single site.
	 */
	public function test_refuses_a_failing_save( string $hook, callable $callback, string $single_site_code, int $single_site_status ): void {
		add_filter( $hook, $callback );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability( $this->create_input( 'uwtfailing', array( 'roles' => array( 'editor' ) ) ) );

		if ( is_multisite() ) {
			$this->assert_core_error( $result, 'user_create_failed', 'Creating with a failing save' );
		} elseif ( 'user_invalid_attribute' === $single_site_code ) {
			$this->assert_value_error( $result, $single_site_code, 'data[username]', 'Creating with a failing save' );
		} else {
			$this->assert_error( $result, $single_site_code, $single_site_status, 'Creating with a failing save' );
		}

		$this->assert_unchanged( $before, 'Creating with a failing save' );
	}

	/**
	 * Returns filters that make the save fail.
	 *
	 * @return array<string, array{hook: string, callback: callable, single_site_code: string, single_site_status: int}>
	 *         The filter, its callback and the error on single site.
	 */
	public static function data_failing_saves(): array {
		return array(
			'empty username' => array( 'pre_user_login', '__return_empty_string', 'user_invalid_attribute', 400 ),
			'empty data'     => array( 'wp_pre_insert_user_data', '__return_empty_array', 'empty_data', 500 ),
		);
	}

	/**
	 * A mail that cannot be sent does not change the result.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_succeeds_when_a_mail_cannot_be_sent(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$result = $this->run_ability( $this->create_input( 'uwtnomail' ) );

		$this->assertIsArray( $result, 'The create should succeed.' );
		$this->assertInstanceOf( WP_User::class, get_user_by( 'login', 'uwtnomail' ), 'The user should be created.' );
	}

	/**
	 * Input that the schema refuses gets the schema error, and nothing changes.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_inputs_the_schema_refuses
	 *
	 * @param array<string, mixed> $input The input.
	 */
	public function test_refuses_input_the_schema_refuses( array $input ): void {
		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_invalid_input( $this->run_ability( $input ), 'Creating with input the schema refuses' );
		$this->assert_unchanged( $before, 'Creating with input the schema refuses' );
	}

	/**
	 * Returns inputs the schema refuses.
	 *
	 * @return array<string, array{input: array<string, mixed>}> The inputs.
	 */
	public static function data_inputs_the_schema_refuses(): array {
		$valid = array(
			'username' => 'uwtschema',
			'email'    => 'uwtschema@example.test',
		);

		$rows = array(
			'no data'              => array( array() ),
			'data not an object'   => array( array( 'data' => 'uwtschema' ) ),
			'no username'          => array( array( 'data' => array( 'email' => 'uwtschema@example.test' ) ) ),
			'no email'             => array( array( 'data' => array( 'username' => 'uwtschema' ) ) ),
			'empty username'       => array( array( 'data' => array_merge( $valid, array( 'username' => '' ) ) ) ),
			'empty name'           => array( array( 'data' => array_merge( $valid, array( 'name' => '' ) ) ) ),
			'empty nickname'       => array( array( 'data' => array_merge( $valid, array( 'nickname' => '' ) ) ) ),
			'empty slug'           => array( array( 'data' => array_merge( $valid, array( 'slug' => '' ) ) ) ),
			'invalid email'        => array( array( 'data' => array_merge( $valid, array( 'email' => 'not-an-email' ) ) ) ),
			'name not a string'    => array( array( 'data' => array_merge( $valid, array( 'name' => 5 ) ) ) ),
			'locale not installed' => array( array( 'data' => array_merge( $valid, array( 'locale' => 'klingon' ) ) ) ),
			'empty roles'          => array( array( 'data' => array_merge( $valid, array( 'roles' => array() ) ) ) ),
			'unregistered role'    => array( array( 'data' => array_merge( $valid, array( 'roles' => array( 'ilovesteak' ) ) ) ) ),
			'repeated role'        => array( array( 'data' => array_merge( $valid, array( 'roles' => array( 'editor', 'editor' ) ) ) ) ),
			'empty roles string'   => array( array( 'data' => array_merge( $valid, array( 'roles' => '' ) ) ) ),
			'unknown field'        => array(
				array(
					'data'   => $valid,
					'fields' => array( 'password' ),
				),
			),
			'unknown key'          => array(
				array(
					'data' => $valid,
					'id'   => 156,
				),
			),
		);

		foreach ( self::data_keys_outside_the_allow_list() as $name => $row ) {
			$rows[ "data with {$name}" ] = array( array( 'data' => array_merge( $valid, array( $row[0] => $row[1] ) ) ) );
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
	 * @param array<string, mixed> $data    The `data`.
	 * @param string               $param   The input path.
	 * @param string|null          $message The message, or null when it is not fixed.
	 */
	public function test_checks_input_when_validation_is_skipped( array $data, string $param, ?string $message ): void {
		$this->skip_input_validation();
		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_value_error( $this->run_ability( array( 'data' => $data ) ), 'user_invalid_attribute', $param, 'Creating without validation', $message );
		$this->assert_unchanged( $before, 'Creating without validation' );
	}

	/**
	 * Returns `data` that only the callback can refuse once validation is skipped.
	 *
	 * @return array<string, array{data: array<string, mixed>, param: string, message: string|null}>
	 *         The `data`, the input path and the message.
	 */
	public static function data_inputs_checked_when_validation_is_skipped(): array {
		$valid = array(
			'username' => 'uwtskipped',
			'email'    => 'uwtskipped@example.test',
		);

		return array(
			'no username'          => array( array( 'email' => 'uwtskipped@example.test' ), 'data[username]', null ),
			'no email'             => array( array( 'username' => 'uwtskipped' ), 'data[email]', null ),
			'empty roles'          => array( array_merge( $valid, array( 'roles' => array() ) ), 'data[roles]', null ),
			'unregistered role'    => array( array_merge( $valid, array( 'roles' => array( 'uwt_unregistered' ) ) ), 'data[roles]', 'The role uwt_unregistered does not exist.' ),
			'invalid email'        => array( array_merge( $valid, array( 'email' => 'not-an-email' ) ), 'data[email]', null ),
			'locale not installed' => array( array_merge( $valid, array( 'locale' => 'klingon' ) ), 'data[locale]', null ),
		);
	}

	/**
	 * Keys outside the attributes never reach the save, when validation is skipped and
	 * when the callback is called directly.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_ways_past_the_schema
	 *
	 * @param bool $direct Whether to call the callback directly.
	 */
	public function test_ignores_keys_outside_the_allow_list( bool $direct ): void {
		$callbacks = $this->get_ability_callbacks();
		$this->login_as( 'manager' );

		$fields = array(
			'user_login'      => 'uwtsentlogin',
			'user_nicename'   => 'uwt-sent-slug',
			'user_registered' => '2001-02-03 04:05:06',
			'display_name'    => 'Sent Display Name',
		);
		$input  = $this->create_input( 'uwtallowlist', array_merge( $this->get_keys_outside_the_allow_list(), $fields ) );
		$start  = time();
		if ( $direct ) {
			$result = $callbacks['execute_callback']( $input );
		} else {
			$this->skip_input_validation();
			$result = $this->run_ability( $input );
		}

		$this->assertIsArray( $result, 'The attributes should be applied.' );
		$this->assert_keys_outside_the_allow_list_ignored( $result['id'], '', 'Creating with keys outside the attributes' );

		$user = get_userdata( $result['id'] );
		$this->assertSame( 'uwtallowlist', $user->user_login, 'The login should be the username.' );
		$this->assertSame( 'uwtallowlist', $user->user_nicename, 'The slug should be the one derived from the username.' );
		$this->assertSame( 'uwtallowlist', $user->display_name, 'The display name should be the one given without a name.' );
		$this->assertFalse( get_user_by( 'login', 'uwtsentlogin' ), 'No user should have the login sent as a key.' );
		$this->assertGreaterThanOrEqual( $start - 1, strtotime( $user->user_registered . ' UTC' ), 'The registration date should be now, not the one sent.' );
		$this->assertLessThanOrEqual( time() + 1, strtotime( $user->user_registered . ' UTC' ), 'The registration date should not be in the future.' );
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
	 * Core's user hooks fire as the caller, and the hooks of the other ways of creating
	 * users do not.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_fires_core_hooks_only(): void {
		$fired = array();
		foreach ( array( 'rest_pre_insert_user', 'rest_insert_user', 'rest_after_insert_user', 'rest_prepare_user', 'edit_user_created_user' ) as $hook ) {
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
			'user_register',
			static function () use ( &$current_users ) {
				$current_users[] = get_current_user_id();
			}
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assertIsArray( $this->run_ability( $this->create_input( 'uwthooks' ) ), 'The user should be created.' );
		$this->assertSame( array( self::$user_ids['manager'] ), $current_users, 'The user_register action should fire once, as the caller.' );
		$this->assertSame( array(), $fired, 'No hook of the other ways of creating users should fire.' );
	}
}
