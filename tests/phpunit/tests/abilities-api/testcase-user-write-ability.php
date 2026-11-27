<?php

declare( strict_types=1 );

/**
 * Base test case for the abilities that write users: core/user-create,
 * core/user-update and core/user-delete.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.3.0
 */
abstract class WP_Abilities_User_Write_UnitTestCase extends WP_UnitTestCase {

	/**
	 * The name of the ability under test, set by each test class.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = '';

	/**
	 * The user abilities, which every test unregisters before and after it runs.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const USER_ABILITIES = array( 'core/users-query', 'core/user-create', 'core/user-update', 'core/user-delete' );

	/**
	 * An ID that no user has.
	 *
	 * @since 7.3.0
	 *
	 * @var int
	 */
	protected const MISSING_ID = 987654321;

	/**
	 * Stands, in the tables of refused attributes, for the message a name of 251 letters
	 * gets when schema validation is skipped.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const LONG_NAME_MESSAGE = 'message of a name of 251 letters';

	/**
	 * The default roles, one shared user each.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const ROLES = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

	/**
	 * The custom roles of the shared users, with their capabilities.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, list<string>>
	 */
	protected const CUSTOM_ROLES = array(
		'creator'         => array( 'read', 'create_users' ),
		'promoter'        => array( 'read', 'promote_users' ),
		'lister_promoter' => array( 'read', 'promote_users', 'list_users' ),
	);

	/**
	 * The fields only a caller who may edit the user can read.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const PRIVATE_FIELDS = array( 'username', 'email', 'first_name', 'last_name', 'nickname', 'locale', 'registered', 'roles' );

	/**
	 * Every top-level output field.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const ALL_FIELDS = array( 'id', 'name', 'description', 'url', 'link', 'slug', 'avatar_urls', 'username', 'email', 'first_name', 'last_name', 'nickname', 'locale', 'registered', 'roles' );

	/**
	 * The fields returned when none are requested.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const DEFAULT_FIELDS = array( 'id', 'name', 'link', 'slug', 'avatar_urls' );

	/**
	 * The status and message of each error code with a fixed message.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, array{0: int, 1: string}>
	 */
	protected const ERRORS = array(
		'user_cannot_create'      => array( 403, 'Sorry, you are not allowed to create new users.' ),
		'user_not_found'          => array( 404, 'Invalid user ID.' ),
		'user_cannot_edit'        => array( 403, 'Sorry, you are not allowed to edit this user.' ),
		'user_cannot_edit_roles'  => array( 403, 'Sorry, you are not allowed to edit roles of this user.' ),
		'user_cannot_assign_role' => array( 403, 'Sorry, you are not allowed to give users that role.' ),
		'user_cannot_delete'      => array( 403, 'Sorry, you are not allowed to delete this user.' ),
		'user_invalid_reassign'   => array( 400, 'Invalid user ID for reassignment.' ),
		'user_create_failed'      => array( 500, 'Error creating new user.' ),
		'user_delete_failed'      => array( 500, 'The user cannot be deleted.' ),
	);

	/**
	 * Shared user IDs keyed by role or fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	protected static $user_ids = array();

	/**
	 * The ID of a second site on multisite.
	 *
	 * @since 7.3.0
	 *
	 * @var int
	 */
	protected static $site_id = 0;

	/**
	 * The passwords of 24 characters generated during the current test.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected $generated_passwords = array();

	/**
	 * How often each watched filter ran during the current test.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	protected $filter_runs = array();

	/**
	 * Creates the shared users and, on multisite, a second site.
	 *
	 * Each user of a role has the username `uwt_<role>` and the email
	 * `uwt-<role>@example.test`.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/bookmark.php';

		foreach ( self::CUSTOM_ROLES as $role => $capabilities ) {
			add_role( "uwt_{$role}", "UWT {$role}", array_fill_keys( $capabilities, true ) );
		}

		$roles = array_merge( self::ROLES, array_map( static fn( $role ) => "uwt_{$role}", array_keys( self::CUSTOM_ROLES ) ) );
		foreach ( $roles as $role ) {
			$name                    = str_replace( 'uwt_', '', $role );
			self::$user_ids[ $name ] = $factory->user->create(
				array(
					'role'       => $role,
					'user_login' => "uwt_{$name}",
					'user_email' => "uwt-{$name}@example.test",
				)
			);
		}

		self::$user_ids['manager'] = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'uwt_manager',
				'user_email' => 'uwt-manager@example.test',
			)
		);

		if ( ! is_multisite() ) {
			return;
		}

		grant_super_admin( self::$user_ids['manager'] );

		self::$site_id = (int) $factory->blog->create();

		self::$user_ids['outsider'] = $factory->user->create(
			array(
				'role'       => 'author',
				'user_login' => 'uwt_outsider',
				'user_email' => 'uwt-outsider@example.test',
			)
		);
		remove_user_from_blog( self::$user_ids['outsider'], get_current_blog_id() );
		add_user_to_blog( self::$site_id, self::$user_ids['outsider'], 'author' );

		self::$user_ids['outside_super_admin'] = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'uwt_outside_super_admin',
				'user_email' => 'uwt-outside-super-admin@example.test',
			)
		);
		remove_user_from_blog( self::$user_ids['outside_super_admin'], get_current_blog_id() );
		grant_super_admin( self::$user_ids['outside_super_admin'] );

		self::$user_ids['network_editor'] = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'uwt_network_editor',
				'user_email' => 'uwt-network-editor@example.test',
			)
		);
		get_userdata( self::$user_ids['network_editor'] )->add_cap( 'manage_network_users' );
	}

	/**
	 * Removes the custom roles, the super admins and the second site, and forgets the fixtures.
	 *
	 * @since 7.3.0
	 */
	public static function wpTearDownAfterClass(): void {
		if ( is_multisite() ) {
			foreach ( array( 'manager', 'outside_super_admin' ) as $name ) {
				if ( isset( self::$user_ids[ $name ] ) ) {
					revoke_super_admin( self::$user_ids[ $name ] );
				}
			}
			if ( self::$site_id ) {
				wp_delete_site( self::$site_id );
			}
		}

		foreach ( array_keys( self::CUSTOM_ROLES ) as $role ) {
			remove_role( "uwt_{$role}" );
		}

		self::$user_ids = array();
		self::$site_id  = 0;
	}

	/**
	 * Clears the sent mail, unregisters the user abilities, registers the user category
	 * and records the generated passwords.
	 *
	 * @since 7.3.0
	 */
	public function set_up(): void {
		parent::set_up();

		reset_phpmailer_instance();

		foreach ( self::USER_ABILITIES as $ability_name ) {
			if ( wp_has_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
		}

		if ( ! wp_has_ability_category( 'user' ) ) {
			global $wp_current_filter;
			$wp_current_filter[] = 'wp_abilities_api_categories_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
			wp_register_ability_category(
				'user',
				array(
					'label'       => 'User',
					'description' => 'User.',
				)
			);
			array_pop( $wp_current_filter );
		}

		$this->generated_passwords = array();
		$this->filter_runs         = array();
		add_filter(
			'random_password',
			function ( $password, $length ) {
				if ( 24 === (int) $length ) {
					$this->generated_passwords[] = $password;
				}

				return $password;
			},
			10,
			2
		);
	}

	/**
	 * Unregisters the user abilities and the category.
	 *
	 * @since 7.3.0
	 */
	public function tear_down(): void {
		foreach ( self::USER_ABILITIES as $ability_name ) {
			if ( wp_has_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
		}

		if ( wp_has_ability_category( 'user' ) ) {
			wp_unregister_ability_category( 'user' );
		}

		reset_phpmailer_instance();

		parent::tear_down();
	}

	/**
	 * Registers the user abilities inside a faked init action.
	 *
	 * @since 7.3.0
	 */
	protected function register_abilities(): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		( new WP_Abilities_Users() )->register();
		array_pop( $wp_current_filter );
	}

	/**
	 * Registers the user abilities and returns the execute callback of the ability under test.
	 *
	 * The callback calls private methods, so tests that reach the checks behind the
	 * permission check capture it from the registration arguments.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, callable> The execute callback, keyed by argument name.
	 */
	protected function get_ability_callbacks(): array {
		$ability   = static::ABILITY;
		$callbacks = array();

		add_filter(
			'wp_register_ability_args',
			static function ( array $args, string $name ) use ( &$callbacks, $ability ): array {
				if ( $ability === $name && isset( $args['execute_callback'] ) ) {
					$callbacks = array( 'execute_callback' => $args['execute_callback'] );
				}

				return $args;
			},
			10,
			2
		);

		$this->register_abilities();

		$this->assertArrayHasKey( 'execute_callback', $callbacks, "The {$ability} ability should be registered with callbacks." );

		return $callbacks;
	}

	/**
	 * Runs the registered ability under test with the given input.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input The ability input.
	 * @return mixed The ability result.
	 */
	protected function run_ability( $input ) {
		$ability = static::ABILITY;

		$this->assertTrue( wp_has_ability( $ability ), "The {$ability} ability should be registered." );

		return wp_get_ability( $ability )->execute( $input );
	}

	/**
	 * Makes the abilities skip schema validation of their input.
	 *
	 * @since 7.3.0
	 */
	protected function skip_input_validation(): void {
		add_filter( 'wp_ability_validate_input', '__return_true' );
	}

	/**
	 * Returns the message a name of 251 letters gets when schema validation is skipped,
	 * and turns validation on again.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $input The ability input, with a name of 251 letters.
	 * @return string The message.
	 */
	protected function get_long_name_message( array $input ): string {
		$this->skip_input_validation();
		$result = $this->run_ability( $input );
		remove_filter( 'wp_ability_validate_input', '__return_true' );

		return $result instanceof WP_Error ? $result->get_error_message() : 'A name of 251 letters should be refused.';
	}

	/**
	 * Logs in as the shared user with the given role or fixture name, or logs out.
	 *
	 * @since 7.3.0
	 *
	 * @param string $name The role or fixture name, or an empty string to log out.
	 */
	protected function login_as( string $name ): void {
		wp_set_current_user( '' === $name ? 0 : self::$user_ids[ $name ] );
	}

	/**
	 * Creates a user for a test that changes it.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $login The username, also used for the email.
	 * @param array<string, mixed> $args  Optional. More user arguments. Default empty array.
	 * @return int The user ID.
	 */
	protected function create_target( string $login, array $args = array() ): int {
		return self::factory()->user->create(
			array_merge(
				array(
					'role'       => 'author',
					'user_login' => $login,
					'user_email' => "{$login}@example.test",
				),
				$args
			)
		);
	}

	/**
	 * Counts the runs of a filter from now on.
	 *
	 * @since 7.3.0
	 *
	 * @param string $hook The filter.
	 */
	protected function watch_filter( string $hook ): void {
		$this->filter_runs[ $hook ] = 0;
		add_filter(
			$hook,
			function ( $value ) use ( $hook ) {
				++$this->filter_runs[ $hook ];
				return $value;
			}
		);
	}

	/**
	 * Asserts that a watched filter did not run.
	 *
	 * @since 7.3.0
	 *
	 * @param string $hook    The filter.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_filter_not_run( string $hook, string $context ): void {
		$this->assertSame( 0, $this->filter_runs[ $hook ] ?? null, "{$context}: the {$hook} filter should not run." );
	}

	/**
	 * Takes a snapshot of the stored users and what they own: every user row and user
	 * meta row (roles, capabilities and site memberships included), the authors and
	 * statuses of the posts, the owners of the links, the super admins and the mail sent.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The snapshot.
	 */
	protected function snapshot(): array {
		global $wpdb;

		return array(
			'users'        => $wpdb->get_results( "SELECT * FROM {$wpdb->users} ORDER BY ID", ARRAY_A ),
			'user_meta'    => $wpdb->get_results( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} ORDER BY umeta_id", ARRAY_A ),
			'posts'        => $wpdb->get_results( "SELECT ID, post_author, post_status FROM {$wpdb->posts} ORDER BY ID", ARRAY_A ),
			'links'        => $wpdb->get_results( "SELECT link_id, link_owner FROM {$wpdb->links} ORDER BY link_id", ARRAY_A ),
			'super_admins' => is_multisite() ? get_site_option( 'site_admins', array() ) : array(),
			'mail'         => wp_list_pluck( $this->get_mails(), 'subject' ),
		);
	}

	/**
	 * Asserts that a refused call changed nothing and sent no mail.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $before  The snapshot taken before the call.
	 * @param string               $context What the call was, for the assertion messages.
	 */
	protected function assert_unchanged( array $before, string $context ): void {
		$after = $this->snapshot();

		$this->assertSame( array(), $after['mail'], "{$context}: no mail should be sent." );
		$this->assertSame( $before, $after, "{$context}: nothing should change." );
	}

	/**
	 * Returns the mail sent since the last reset.
	 *
	 * @since 7.3.0
	 *
	 * @return list<array{to: list<string>, subject: string, body: string}> The mails, with
	 *                                                                      the recipient addresses.
	 */
	protected function get_mails(): array {
		$mails = array();
		foreach ( tests_retrieve_phpmailer_instance()->mock_sent as $mail ) {
			$mails[] = array(
				'to'      => array_map( 'strtolower', array_column( $mail['to'], 0 ) ),
				'subject' => $mail['subject'],
				'body'    => $mail['body'],
			);
		}

		return $mails;
	}

	/**
	 * Returns the site name as the mails show it.
	 *
	 * @since 7.3.0
	 *
	 * @return string The site name.
	 */
	protected function get_mail_site_name(): string {
		return wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	}

	/**
	 * Reads a user through core/users-query, as the current user.
	 *
	 * @since 7.3.0
	 *
	 * @param int          $user_id The user ID.
	 * @param list<string> $fields  Optional. The fields. Default the default fields.
	 * @return array<string, mixed>|null The user item, or null when it was not returned.
	 */
	protected function query_user( int $user_id, array $fields = array() ): ?array {
		$this->assertTrue( wp_has_ability( 'core/users-query' ), 'The core/users-query ability should be registered.' );

		$input = array( 'where' => array( 'ids' => array( $user_id ) ) );
		if ( ! empty( $fields ) ) {
			$input['fields'] = $fields;
		}

		$result = wp_get_ability( 'core/users-query' )->execute( $input );

		$this->assertIsArray( $result, 'The user should be readable through core/users-query.' );

		return $result['items'][0] ?? null;
	}

	/**
	 * Asserts that a write result is a user item equal to what core/users-query shows.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed        $result  The ability result.
	 * @param list<string> $fields  The requested fields, or an empty list for the default ones.
	 * @param string       $context What the call was, for the assertion messages.
	 * @return int The user ID.
	 */
	protected function assert_user_item( $result, array $fields, string $context ): int {
		$this->assertIsArray( $result, "{$context}: the call should succeed." );
		$this->assertArrayHasKey( 'id', $result, "{$context}: the output should hold the ID." );
		$this->assertIsInt( $result['id'], "{$context}: the ID should be an integer." );

		$this->assertSame( $this->query_user( $result['id'], $fields ), $result, "{$context}: the output should be what core/users-query shows for the stored user." );

		return $result['id'];
	}

	/**
	 * Asserts that a result is an error with the given code and status.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $code    The expected error code.
	 * @param int    $status  The expected error status.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_error( $result, string $code, int $status, string $context ): void {
		$this->assertWPError( $result, "{$context}: the call should fail." );
		$this->assertSame( $code, $result->get_error_code(), "{$context}: the error code should be {$code}." );

		$data = $result->get_error_data();
		$this->assertIsArray( $data, "{$context}: the error should carry data." );
		$this->assertSame( $status, $data['status'] ?? null, "{$context}: the error status should be {$status}." );
	}

	/**
	 * Asserts that a result is an error with a fixed message, and its status, which is
	 * 401 instead of 403 when nobody is logged in.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $code    The expected error code.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_core_error( $result, string $code, string $context ): void {
		list( $status, $message ) = self::ERRORS[ $code ];

		if ( 403 === $status && ! is_user_logged_in() ) {
			$status = 401;
		}
		if ( 'user_cannot_delete' === $code && is_multisite() ) {
			$message = 'Sorry, you are not allowed to remove users.';
		}

		$this->assert_error( $result, $code, $status, $context );
		$this->assertSame( $message, $result->get_error_message(), "{$context}: the message should be the {$code} message." );
	}

	/**
	 * Asserts that a result is a 400 error about one input value.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed       $result  The ability result.
	 * @param string      $code    The expected error code.
	 * @param string      $param   The input path of the value.
	 * @param string      $context What the call was, for the assertion messages.
	 * @param string|null $message Optional. The expected message. Default null, any message.
	 */
	protected function assert_value_error( $result, string $code, string $param, string $context, ?string $message = null ): void {
		$this->assert_error( $result, $code, 400, $context );

		if ( null !== $message ) {
			$this->assertSame( $message, $result->get_error_message(), "{$context}: the message should be the one for {$param}." );
		}

		$this->assertNotSame( '', $result->get_error_message(), "{$context}: the message should give the reason." );
		$this->assertSame( array( $param => $result->get_error_message() ), $result->get_error_data()['params'] ?? null, "{$context}: the params should hold only {$param}, with the message." );
	}

	/**
	 * Asserts that a result is the error of a call that the permission check refused.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_refused( $result, string $context ): void {
		$this->assertWPError( $result, "{$context}: the call should fail." );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), "{$context}: the permission check should refuse the call." );
	}

	/**
	 * Asserts that a result is the error of input the schema refused.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_invalid_input( $result, string $context ): void {
		$this->assertWPError( $result, "{$context}: the call should fail." );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), "{$context}: the schema should refuse the input." );
	}

	/**
	 * Asserts that no generated password shows in a result or in the mail sent.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_password_not_leaked( $result, string $context ): void {
		$haystacks = array( 'output' => (string) wp_json_encode( is_wp_error( $result ) ? array( $result->get_error_messages(), $result->get_error_data() ) : $result ) );
		foreach ( $this->get_mails() as $index => $mail ) {
			$haystacks[ "mail {$index}" ] = $mail['subject'] . "\n" . $mail['body'];
		}

		foreach ( $this->generated_passwords as $password ) {
			foreach ( $haystacks as $where => $haystack ) {
				$this->assertStringNotContainsString( $password, $haystack, "{$context}: the generated password should not be in the {$where}." );
			}
		}
	}

	/**
	 * Normalizes a schema for comparison: removes the descriptions, sorts the keys of
	 * objects and sorts lists of plain values.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $schema The schema.
	 * @return mixed The normalized schema.
	 */
	protected function normalize_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		if ( isset( $schema['description'] ) && is_string( $schema['description'] ) ) {
			unset( $schema['description'] );
		}

		$schema = array_map( array( $this, 'normalize_schema' ), $schema );

		if ( array_is_list( $schema ) ) {
			if ( count( $schema ) === count( array_filter( $schema, 'is_scalar' ) ) ) {
				sort( $schema );
			}
		} else {
			ksort( $schema );
		}

		return $schema;
	}

	/**
	 * Returns the schema of the attributes that create and update share.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The property schemas, keyed by attribute.
	 */
	protected function get_profile_property_schemas(): array {
		return array(
			'email'       => array(
				'type'      => 'string',
				'format'    => 'email',
				'maxLength' => 100,
			),
			'name'        => array(
				'type'      => 'string',
				'minLength' => 1,
				'maxLength' => 250,
			),
			'first_name'  => array( 'type' => 'string' ),
			'last_name'   => array( 'type' => 'string' ),
			'nickname'    => array(
				'type'      => 'string',
				'minLength' => 1,
			),
			'slug'        => array(
				'type'      => 'string',
				'minLength' => 1,
			),
			'description' => array( 'type' => 'string' ),
			'url'         => array( 'type' => 'string' ),
			'locale'      => array(
				'type' => 'string',
				'enum' => array_merge( array( '', 'en_US' ), get_available_languages() ),
			),
			'roles'       => array(
				'type'        => 'array',
				'uniqueItems' => true,
				'minItems'    => 1,
				'items'       => array(
					'type' => 'string',
					'enum' => array_keys( wp_roles()->roles ),
				),
			),
		);
	}

	/**
	 * Returns the schema of `where` of update and delete.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The schema.
	 */
	protected function get_where_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => array(
				'id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
		);
	}

	/**
	 * Asserts that the output schema of the ability under test is the core/users-query item.
	 *
	 * @since 7.3.0
	 */
	protected function assert_output_schema_is_the_query_item(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( static::ABILITY ), 'The ability should be registered.' );

		$schema = wp_get_ability( static::ABILITY )->get_output_schema();
		$item   = wp_get_ability( 'core/users-query' )->get_output_schema()['properties']['items']['items'];

		$this->assertSame( array( 'id' ), $schema['required'] ?? null, 'The output should require the ID.' );
		$this->assertFalse( $schema['additionalProperties'] ?? null, 'The output should not take unknown keys.' );
		$this->assertSame( $this->normalize_schema( $item ), $this->normalize_schema( $schema ), 'The output should be the core/users-query user item.' );
	}

	/**
	 * Asserts the registration of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @param string $label       The label.
	 * @param bool   $destructive Whether the ability is destructive.
	 * @param bool   $idempotent  Whether the ability is idempotent.
	 */
	protected function assert_registration( string $label, bool $destructive, bool $idempotent ): void {
		$this->register_abilities();

		$ability = wp_get_ability( static::ABILITY );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The ability should be registered.' );
		$this->assertSame( $label, $ability->get_label(), 'The ability should have its label.' );
		$this->assertSame( 'user', $ability->get_category(), 'The ability should use the user category.' );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ), 'The ability should be shown in the run route.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertFalse( $annotations['readonly'] ?? null, 'The ability should not be marked read-only.' );
		$this->assertSame( $destructive, $annotations['destructive'] ?? null, 'The destructive annotation should be set.' );
		$this->assertSame( $idempotent, $annotations['idempotent'] ?? null, 'The idempotent annotation should be set.' );
	}

	/**
	 * Returns input keys that are not attributes, with a value for each.
	 *
	 * @return array<string, array{key: string, value: mixed}> The keys and values.
	 */
	public static function data_keys_outside_the_allow_list(): array {
		return array(
			'password'            => array( 'password', 'uwt-chosen-password' ),
			'user_pass'           => array( 'user_pass', 'uwt-chosen-password' ),
			'role'                => array( 'role', 'administrator' ),
			'meta_input'          => array( 'meta_input', array( 'uwt_meta' => 'set' ) ),
			'meta'                => array( 'meta', array( 'uwt_meta' => 'set' ) ),
			'user_activation_key' => array( 'user_activation_key', 'uwt-key' ),
			'capabilities'        => array( 'wp_capabilities', array( 'administrator' => true ) ),
			'user_level'          => array( 'user_level', 10 ),
			'id'                  => array( 'id', 1 ),
		);
	}

	/**
	 * Returns the keys outside the allow list as one `data` fragment.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The keys and values.
	 */
	protected function get_keys_outside_the_allow_list(): array {
		$data = array();
		foreach ( self::data_keys_outside_the_allow_list() as $row ) {
			$data[ $row[0] ] = $row[1];
		}

		return $data;
	}

	/**
	 * Asserts that none of the keys outside the allow list reached a user.
	 *
	 * @since 7.3.0
	 *
	 * @param int    $user_id        The user ID.
	 * @param string $password_hash  The password hash the user should still have, or an
	 *                               empty string when the user is new.
	 * @param string $context        What the call was, for the assertion messages.
	 */
	protected function assert_keys_outside_the_allow_list_ignored( int $user_id, string $password_hash, string $context ): void {
		$user = get_userdata( $user_id );

		$this->assertInstanceOf( WP_User::class, $user, "{$context}: the user should exist." );
		$this->assertFalse( wp_check_password( 'uwt-chosen-password', $user->user_pass, $user_id ), "{$context}: the password from the input should not be set." );
		if ( '' !== $password_hash ) {
			$this->assertSame( $password_hash, $user->user_pass, "{$context}: the password should not change." );
		}
		$this->assertNotContains( 'administrator', $user->roles, "{$context}: the role from the input should not be given." );
		$this->assertFalse( $user->has_cap( 'manage_options' ), "{$context}: no capability from the input should be given." );
		$this->assertSame( array(), get_user_meta( $user_id, 'uwt_meta' ), "{$context}: no meta from the input should be stored." );
		$this->assertNotSame( 'uwt-key', $user->user_activation_key, "{$context}: the activation key from the input should not be stored." );
	}

	/**
	 * Returns profile attributes that are refused, with the error each gets.
	 *
	 * The users of the shared fixtures have the username `uwt_<role>`, the email
	 * `uwt-<role>@example.test` and the slug `uwt_<role>`. A test that uses these rows
	 * also creates a user whose slug is `team-desk`.
	 *
	 * @return array<string, array{data: array<string, string>, code: string, param: string, message: string|null}>
	 *         The `data`, the error code, the input path and the message, or null when
	 *         the message is not fixed.
	 */
	public static function data_profile_attributes_refused(): array {
		return array(
			'email of another user'                   => array( array( 'email' => 'uwt-editor@example.test' ), 'user_exists', 'data[email]', 'Sorry, that email address is already used!' ),
			'email of another user in another case'   => array( array( 'email' => 'UWT-Editor@Example.TEST' ), 'user_exists', 'data[email]', 'Sorry, that email address is already used!' ),
			'name longer once stored'                 => array( array( 'name' => str_repeat( 'a', 249 ) . '&' ), 'user_invalid_attribute', 'data[name]', self::LONG_NAME_MESSAGE ),
			'email stored differently'                => array( array( 'email' => 'uwt&stored@example.test' ), 'user_invalid_attribute', 'data[email]', 'Invalid email address.' ),
			'name with nothing left after sanitizing' => array( array( 'name' => '<b></b>' ), 'user_invalid_attribute', 'data[name]', null ),
			'nickname with nothing left'              => array( array( 'nickname' => '<i> </i>' ), 'user_invalid_attribute', 'data[nickname]', null ),
			'slug with nothing left'                  => array( array( 'slug' => '!!!' ), 'user_invalid_attribute', 'data[slug]', 'Invalid slug.' ),
			'slug of another user'                    => array( array( 'slug' => 'uwt_editor' ), 'user_exists', 'data[slug]', 'Sorry, that slug is already used by another user.' ),
			'slug of another user once cleaned'       => array( array( 'slug' => 'Team/Desk' ), 'user_exists', 'data[slug]', 'Sorry, that slug is already used by another user.' ),
			'slug of 51 characters'                   => array( array( 'slug' => str_repeat( 'a', 51 ) ), 'user_invalid_attribute', 'data[slug]', null ),
			'url with nothing left'                   => array( array( 'url' => 'javascript:alert(1)' ), 'user_invalid_attribute', 'data[url]', 'Invalid URL.' ),
			'url of 101 characters'                   => array( array( 'url' => 'https://example.test/' . str_repeat( 'a', 80 ) ), 'user_invalid_attribute', 'data[url]', null ),
		);
	}

	/**
	 * Returns an email of 101 characters and a name of 251 characters, each with schema
	 * validation on and skipped.
	 *
	 * @return array<string, array{data: array<string, string>, skip_validation: bool, param: string}>
	 *         The `data`, whether to skip validation, and the input path.
	 */
	public static function data_values_over_the_length_limits(): array {
		$email = array( 'email' => str_repeat( 'a', 88 ) . '@example.test' );
		$name  = array( 'name' => str_repeat( 'a', 251 ) );

		return array(
			'email of 101 characters'                     => array( $email, false, 'data[email]' ),
			'name of 251 characters'                      => array( $name, false, 'data[name]' ),
			'email of 101 characters, validation skipped' => array( $email, true, 'data[email]' ),
			'name of 251 characters, validation skipped'  => array( $name, true, 'data[name]' ),
		);
	}

	/**
	 * Asserts that a value over a length limit was refused: by the schema, or with the
	 * error about the value when schema validation was skipped.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result          The ability result.
	 * @param bool   $skip_validation Whether schema validation was skipped.
	 * @param string $param           The input path of the value.
	 * @param string $context         What the call was, for the assertion messages.
	 */
	protected function assert_over_the_length_limit( $result, bool $skip_validation, string $param, string $context ): void {
		if ( $skip_validation ) {
			$this->assert_value_error( $result, 'user_invalid_attribute', $param, $context );
		} else {
			$this->assert_invalid_input( $result, $context );
		}
	}

	/**
	 * Returns text values with quotes and backslashes.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, string> The values, keyed by attribute.
	 */
	protected function get_quoted_values(): array {
		return array(
			'name'        => 'Sam O\'Neil "Q" \\ end',
			'first_name'  => 'Sam \\ O\'Neil',
			'last_name'   => 'O\'Neil "the \\ second"',
			'nickname'    => '\'Q\' \\\\ "Sam"',
			'description' => 'Says "hi" and \'bye\' \\ with C:\\path',
		);
	}

	/**
	 * Asserts that the text values with quotes and backslashes were stored as given.
	 *
	 * @since 7.3.0
	 *
	 * @param int    $user_id The user ID.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_quoted_values_stored( int $user_id, string $context ): void {
		$values = $this->get_quoted_values();
		$user   = get_userdata( $user_id );

		$this->assertSame( $values['name'], $user->display_name, "{$context}: the name should be stored as given." );
		foreach ( array( 'first_name', 'last_name', 'nickname', 'description' ) as $key ) {
			$this->assertSame( $values[ $key ], get_user_meta( $user_id, $key, true ), "{$context}: the {$key} should be stored as given." );
		}
	}
}
