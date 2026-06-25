<?php

declare( strict_types=1 );

/**
 * Tests for the core/users ability.
 *
 * @covers WP_Users_Abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreUsersAbility extends WP_UnitTestCase {

	/**
	 * Shared fixture IDs.
	 *
	 * @since 6.9.0
	 * @var array<string, int>
	 */
	private static $fixture_ids = array();

	/**
	 * Administrator user ID.
	 *
	 * @since 6.9.0
	 * @var int
	 */
	private $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @since 6.9.0
	 * @var int
	 */
	private $subscriber_id;

	/**
	 * Author user ID with a published post.
	 *
	 * @since 6.9.0
	 * @var int
	 */
	private $public_author_id;

	/**
	 * Author post ID.
	 *
	 * @since 6.9.0
	 * @var int
	 */
	private $public_post_id;

	/**
	 * Original show_avatars option.
	 *
	 * @since 6.9.0
	 * @var mixed
	 */
	private $show_avatars;

	/**
	 * Set up before the class.
	 *
	 * @since 6.9.0
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );

		self::$fixture_ids['administrator'] = self::factory()->user->create(
			array(
				'role'          => 'administrator',
				'user_login'    => 'core_users_ability_admin',
				'user_email'    => 'core-users-ability-admin@example.com',
				'user_nicename' => 'core-users-ability-admin',
			)
		);

		self::$fixture_ids['editor'] = self::factory()->user->create(
			array(
				'role'          => 'editor',
				'user_login'    => 'core_users_ability_editor',
				'user_email'    => 'core-users-ability-editor@example.com',
				'user_nicename' => 'core-users-ability-editor',
			)
		);

		self::$fixture_ids['author'] = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_login'    => 'core_users_ability_author_current',
				'user_email'    => 'core-users-ability-author-current@example.com',
				'user_nicename' => 'core-users-ability-author-current',
			)
		);

		self::$fixture_ids['contributor'] = self::factory()->user->create(
			array(
				'role'          => 'contributor',
				'user_login'    => 'core_users_ability_contributor',
				'user_email'    => 'core-users-ability-contributor@example.com',
				'user_nicename' => 'core-users-ability-contributor',
			)
		);

		self::$fixture_ids['subscriber'] = self::factory()->user->create(
			array(
				'role'          => 'subscriber',
				'user_login'    => 'core_users_ability_subscriber',
				'user_email'    => 'core-users-ability-subscriber@example.com',
				'user_nicename' => 'core-users-ability-subscriber',
			)
		);

		self::$fixture_ids['public_author'] = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_login'    => 'core_users_ability_author',
				'user_email'    => 'core-users-ability-author@example.com',
				'user_nicename' => 'core-users-ability-author',
			)
		);

		self::$fixture_ids['public_post'] = self::factory()->post->create(
			array(
				'post_author' => self::$fixture_ids['public_author'],
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
	}

	/**
	 * Tear down after the class.
	 *
	 * @since 6.9.0
	 */
	public static function tear_down_after_class(): void {
		wp_delete_post( self::$fixture_ids['public_post'], true );

		foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber', 'public_author' ) as $fixture_name ) {
			wp_delete_user( self::$fixture_ids[ $fixture_name ] );
		}

		self::$fixture_ids = array();

		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		parent::tear_down_after_class();
	}

	/**
	 * Set up test case.
	 *
	 * @since 6.9.0
	 */
	public function set_up(): void {
		parent::set_up();

		$this->show_avatars = get_option( 'show_avatars' );
		update_option( 'show_avatars', 1 );

		$this->admin_id         = self::$fixture_ids['administrator'];
		$this->subscriber_id    = self::$fixture_ids['subscriber'];
		$this->public_author_id = self::$fixture_ids['public_author'];
		$this->public_post_id   = self::$fixture_ids['public_post'];

		$this->register_core_users_ability();
	}

	/**
	 * Tear down test case.
	 *
	 * @since 6.9.0
	 */
	public function tear_down(): void {
		if ( wp_has_ability( 'core/users' ) ) {
			wp_unregister_ability( 'core/users' );
		}

		update_option( 'show_avatars', $this->show_avatars );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Registers the core/users ability inside a faked init action.
	 *
	 * @since 6.9.0
	 */
	private function register_core_users_ability(): void {
		if ( wp_has_ability( 'core/users' ) ) {
			wp_unregister_ability( 'core/users' );
		}

		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		try {
			( new WP_Users_Abilities() )->register();
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * The ability is registered in the `user` category and flagged read-only.
	 *
	 * @ticket 64146
	 */
	public function test_core_users_ability_is_registered(): void {
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/users' );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The users ability should be registered.' );
		$this->assertSame( 'user', $ability->get_category(), 'The users ability should use the user category.' );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ), 'The users ability should be exposed over REST.' );
		$this->assertTrue( $ability->get_meta_item( 'pagination', false ), 'The users ability should advertise pagination support.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'], 'The users ability should be marked read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The users ability should not be marked destructive.' );
	}


	/**
	 * The input schema exposes strict single-user and collection modes.
	 *
	 * @ticket 64146
	 */
	public function test_core_users_input_schema_exposes_strict_modes(): void {
		$this->register_core_users_ability();

		$schema = wp_get_ability( 'core/users' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'], 'The users ability input schema should describe an object.' );
		$this->assertEquals( (object) array(), $schema['default'], 'The users ability input schema should default to empty collection mode.' );
		$this->assertCount( 5, $schema['oneOf'], 'The users ability input schema should expose four lookup modes and collection mode.' );

		$this->assertSame( array( 'id' ), $schema['oneOf'][0]['required'], 'The first input mode should require an ID.' );
		$this->assertSame( array( 'user_email' ), $schema['oneOf'][1]['required'], 'The second input mode should require a user email.' );
		$this->assertSame( array( 'user_login' ), $schema['oneOf'][2]['required'], 'The third input mode should require a user login.' );
		$this->assertSame( array( 'user_nicename' ), $schema['oneOf'][3]['required'], 'The fourth input mode should require a user nicename.' );
		$this->assertArrayNotHasKey( 'required', $schema['oneOf'][4], 'Collection mode should allow an empty request.' );

		$collection_properties = $schema['oneOf'][4]['properties'];
		$this->assertEqualSets(
			array( 'roles', 'has_published_posts', 'fields', 'page', 'per_page' ),
			array_keys( $collection_properties ),
			'Collection mode should expose only the supported query parameters.'
		);
		$excluded_properties = array(
			'search',
			'include',
			'exclude',
			'email',
			'username',
			'slug',
			'user_email',
			'user_login',
			'user_nicename',
			'order',
			'orderby',
			'search_columns',
			'offset',
			'context',
			'who',
			'capabilities',
		);
		foreach ( $excluded_properties as $excluded_property ) {
			$this->assertArrayNotHasKey( $excluded_property, $collection_properties, sprintf( 'Collection mode should not expose %s.', $excluded_property ) );
		}

		$fields = $schema['oneOf'][4]['properties']['fields']['items']['enum'];
		$this->assertContains( 'roles', $fields, 'The fields enum should expose the roles field.' );
		$this->assertContains( 'avatar_urls', $fields, 'The fields enum should expose avatar_urls when avatars are enabled.' );

		$role_names = $schema['oneOf'][4]['properties']['roles']['items']['enum'];
		$this->assertEqualSets( array_keys( wp_roles()->roles ), $role_names, 'The roles query enum should expose registered role names.' );

		$post_type_names = $schema['oneOf'][4]['properties']['has_published_posts']['oneOf'][1]['items']['enum'];
		$this->assertContains( 'post', $post_type_names, 'The has_published_posts enum should expose public post types.' );
		$this->assertNotContains( 'revision', $post_type_names, 'The has_published_posts enum should omit non-public post types.' );

		$output_schema   = wp_get_ability( 'core/users' )->get_output_schema();
		$user_properties = $output_schema['properties']['users']['items']['properties'];
		$this->assertSame( 'date-time', $user_properties['user_registered']['format'], 'The user_registered output schema should use date-time format.' );
		$this->assertEqualSets( array_keys( wp_roles()->roles ), $user_properties['roles']['items']['enum'], 'The roles output enum should expose registered role names.' );
	}

	/**
	 * Avatar fields are not requestable when avatars are disabled.
	 *
	 * @ticket 64146
	 */
	public function test_avatar_urls_respects_show_avatars_option(): void {
		update_option( 'show_avatars', 0 );
		$this->register_core_users_ability();

		$ability       = wp_get_ability( 'core/users' );
		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();

		$this->assertNotContains( 'avatar_urls', $input_schema['oneOf'][0]['properties']['fields']['items']['enum'], 'The fields enum should omit avatar_urls when avatars are disabled.' );
		$this->assertArrayNotHasKey( 'avatar_urls', $output_schema['properties']['users']['items']['properties'], 'The output schema should omit avatar_urls when avatars are disabled.' );

		wp_set_current_user( $this->subscriber_id );
		$result = $ability->execute( array( 'id' => $this->subscriber_id ) );

		$this->assertIsArray( $result, 'The current user should still be readable when avatars are disabled.' );
		$this->assertArrayNotHasKey( 'avatar_urls', $result['users'][0], 'The ability result should omit avatar_urls when avatars are disabled.' );
	}

	/**
	 * Logged-out users cannot run the ability.
	 *
	 * @ticket 64146
	 */
	public function test_core_users_requires_logged_in_user(): void {
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/users' )->execute( array() );

		$this->assertWPError( $result, 'Logged-out users should not be allowed to execute the users ability.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Logged-out users should receive an invalid permissions error.' );
	}

	/**
	 * The current user can read themselves by ID, user email, and user login.
	 *
	 * @ticket 64146
	 */
	public function test_current_user_can_read_themselves_by_sensitive_identifiers(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/users' );

		$result = $ability->execute(
			array(
				'id'     => $this->subscriber_id,
				'fields' => array( 'id', 'user_email', 'user_login', 'roles' ),
			)
		);

		$this->assertIsArray( $result, 'A user should be able to read themselves by ID.' );
		$this->assertSame( $this->subscriber_id, $result['users'][0]['id'], 'The ID lookup should return the current user.' );
		$this->assertSame( 'core-users-ability-subscriber@example.com', $result['users'][0]['user_email'], 'The current user should receive their own email.' );
		$this->assertSame( 'core_users_ability_subscriber', $result['users'][0]['user_login'], 'The current user should receive their own login.' );
		$this->assertContains( 'subscriber', $result['users'][0]['roles'], 'The current user should receive their own roles.' );

		$result = $ability->execute( array( 'user_email' => 'core-users-ability-subscriber@example.com' ) );
		$this->assertIsArray( $result, 'A user should be able to read themselves by email.' );
		$this->assertSame( $this->subscriber_id, $result['users'][0]['id'], 'The email lookup should return the current user.' );

		$result = $ability->execute( array( 'user_login' => 'core_users_ability_subscriber' ) );
		$this->assertIsArray( $result, 'A user should be able to read themselves by login.' );
		$this->assertSame( $this->subscriber_id, $result['users'][0]['id'], 'The login lookup should return the current user.' );

		$result = $ability->execute(
			array(
				'id'     => $this->subscriber_id,
				'fields' => array( 'id', 'user_registered' ),
			)
		);
		$this->assertIsArray( $result, 'A user should be able to request their registration date.' );
		$this->assertSame(
			gmdate( 'c', strtotime( get_userdata( $this->subscriber_id )->user_registered ) ),
			$result['users'][0]['user_registered'],
			'The registration date should be formatted as an ISO 8601 date-time string.'
		);
	}

	/**
	 * Public-author users can be read by ID or user nicename by logged-in users.
	 *
	 * @ticket 64146
	 */
	public function test_public_author_can_be_read_by_id_and_user_nicename(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/users' );

		$result = $ability->execute(
			array(
				'id'     => $this->public_author_id,
				'fields' => array( 'id', 'user_nicename', 'user_email' ),
			)
		);

		$this->assertIsArray( $result, 'A logged-in user should be able to read a public author by ID.' );
		$this->assertSame( $this->public_author_id, $result['users'][0]['id'], 'The ID lookup should return the public author.' );
		$this->assertSame( 'core-users-ability-author', $result['users'][0]['user_nicename'], 'The public author nicename should be returned.' );
		$this->assertArrayNotHasKey( 'user_email', $result['users'][0], 'Public-author access should not expose another user email.' );

		$result = $ability->execute( array( 'user_nicename' => 'core-users-ability-author' ) );

		$this->assertIsArray( $result, 'A logged-in user should be able to read a public author by nicename.' );
		$this->assertSame( $this->public_author_id, $result['users'][0]['id'], 'The nicename lookup should return the public author.' );
	}

	/**
	 * User email and login lookups for another user require list or edit permissions across roles.
	 *
	 * @ticket 64146
	 *
	 * @dataProvider data_roles_for_sensitive_identifier_lookup_permissions
	 *
	 * @param string $role        Current user's role.
	 * @param bool   $can_resolve Whether the role can resolve another user by sensitive identifiers.
	 */
	public function test_roles_have_expected_sensitive_identifier_lookup_permissions( string $role, bool $can_resolve ): void {
		wp_set_current_user( self::$fixture_ids[ $role ] );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/users' );

		$result = $ability->execute( array( 'user_email' => 'core-users-ability-author@example.com' ) );
		if ( $can_resolve ) {
			$this->assertIsArray( $result, sprintf( 'The %s role should be able to resolve another user by email.', $role ) );
			$this->assertSame( $this->public_author_id, $result['users'][0]['id'], sprintf( 'The email lookup should return the public author for the %s role.', $role ) );
		} else {
			$this->assertWPError( $result, sprintf( 'The %s role should not be able to resolve another user by email.', $role ) );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), sprintf( 'Email lookup denial for the %s role should use the invalid permissions error.', $role ) );
		}

		$result = $ability->execute( array( 'user_login' => 'core_users_ability_author' ) );
		if ( $can_resolve ) {
			$this->assertIsArray( $result, sprintf( 'The %s role should be able to resolve another user by login.', $role ) );
			$this->assertSame( $this->public_author_id, $result['users'][0]['id'], sprintf( 'The login lookup should return the public author for the %s role.', $role ) );
			return;
		}

		$this->assertWPError( $result, sprintf( 'The %s role should not be able to resolve another user by login.', $role ) );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), sprintf( 'Login lookup denial for the %s role should use the invalid permissions error.', $role ) );
	}

	/**
	 * Data provider for role-based sensitive identifier lookup checks.
	 *
	 * @ticket 64146
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function data_roles_for_sensitive_identifier_lookup_permissions(): array {
		return array(
			'administrator' => array( 'administrator', true ),
			'editor'        => array( 'editor', false ),
			'author'        => array( 'author', false ),
			'contributor'   => array( 'contributor', false ),
			'subscriber'    => array( 'subscriber', false ),
		);
	}

	/**
	 * Empty collection mode returns only public authors for users without list_users.
	 *
	 * @ticket 64146
	 */
	public function test_empty_collection_mode_restricts_users_without_list_users_to_public_authors(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/users' )->execute( array() );

		$this->assertIsArray( $result, 'Collection mode should return an array for logged-in users.' );
		$this->assertContains( $this->public_author_id, wp_list_pluck( $result['users'], 'id' ), 'Collection mode should include public authors.' );
		$this->assertNotContains( $this->admin_id, wp_list_pluck( $result['users'], 'id' ), 'Collection mode should omit non-author administrators for users without list_users.' );
		$this->assertNotContains( $this->subscriber_id, wp_list_pluck( $result['users'], 'id' ), 'Collection mode should omit subscribers for users without list_users.' );
		$this->assertIsInt( $result['total'], 'Collection mode should include an integer total.' );
		$this->assertIsInt( $result['total_pages'], 'Collection mode should include an integer total_pages value.' );
	}

	/**
	 * Collection mode for users without list_users honors public post type filters.
	 *
	 * @ticket 64146
	 */
	public function test_collection_mode_for_users_without_list_users_uses_public_post_types(): void {
		register_post_type(
			'wp_public_pt',
			array(
				'public'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_type(
			'wp_private_pt',
			array(
				'public' => false,
			)
		);

		$public_author_id  = self::factory()->user->create( array( 'role' => 'author' ) );
		$private_author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$public_post_id    = self::factory()->post->create(
			array(
				'post_author' => $public_author_id,
				'post_status' => 'publish',
				'post_type'   => 'wp_public_pt',
			)
		);
		$private_post_id   = self::factory()->post->create(
			array(
				'post_author' => $private_author_id,
				'post_status' => 'publish',
				'post_type'   => 'wp_private_pt',
			)
		);

		try {
			$this->assertFalse( get_post_type_object( 'wp_public_pt' )->show_in_rest, 'The public fixture post type should remain hidden from REST.' );

			wp_set_current_user( $this->subscriber_id );
			$this->register_core_users_ability();

			$ability = wp_get_ability( 'core/users' );
			$schema  = $ability->get_input_schema();
			$enum    = $schema['oneOf'][4]['properties']['has_published_posts']['oneOf'][1]['items']['enum'];

			$this->assertContains( 'wp_public_pt', $enum, 'The has_published_posts enum should include public post types even when hidden from REST.' );
			$this->assertNotContains( 'wp_private_pt', $enum, 'The has_published_posts enum should omit private post types.' );

			$result = $ability->execute(
				array(
					'has_published_posts' => array( 'wp_public_pt' ),
					'fields'              => array( 'id' ),
					'per_page'            => 100,
				)
			);

			$this->assertIsArray( $result, 'A public post type author query should return an array.' );
			$ids = wp_list_pluck( $result['users'], 'id' );
			$this->assertContains( $public_author_id, $ids, 'The query should include authors of the requested public post type.' );
			$this->assertNotContains( $this->public_author_id, $ids, 'The query should exclude authors without posts in the requested public post type.' );
			$this->assertNotContains( $private_author_id, $ids, 'The query should exclude authors of private post types.' );

			$result = $ability->execute(
				array(
					'has_published_posts' => array( 'wp_private_pt' ),
					'fields'              => array( 'id' ),
				)
			);

			$this->assertWPError( $result, 'Private post type filters should fail schema validation.' );
			$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Private post type filters should use the invalid input error.' );
		} finally {
			wp_delete_post( $public_post_id, true );
			wp_delete_post( $private_post_id, true );
			wp_delete_user( $public_author_id );
			wp_delete_user( $private_author_id );
			unregister_post_type( 'wp_public_pt' );
			unregister_post_type( 'wp_private_pt' );
		}
	}

	/**
	 * Administrators can query by role and receive roles.
	 *
	 * @ticket 64146
	 */
	public function test_admin_can_query_by_role_and_receive_roles(): void {
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/users' )->execute(
			array(
				'roles'    => array( 'author' ),
				'fields'   => array( 'id', 'roles' ),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $result, 'An administrator role query should return an array.' );
		$this->assertContains( $this->public_author_id, wp_list_pluck( $result['users'], 'id' ), 'The author role query should include the public author.' );
		foreach ( $result['users'] as $user ) {
			$this->assertContains( 'author', $user['roles'], 'Each user returned by an author role query should include the author role.' );
		}
	}

	/**
	 * Field visibility for another public author matches the current user's role.
	 *
	 * @ticket 64146
	 *
	 * @dataProvider data_roles_for_another_public_author_field_visibility
	 *
	 * @param string $role               Current user's role.
	 * @param bool   $can_view_sensitive Whether the role can view another user's sensitive fields.
	 * @param bool   $can_view_roles     Whether the role can view another user's roles.
	 */
	public function test_roles_have_expected_field_visibility_for_another_public_author( string $role, bool $can_view_sensitive, bool $can_view_roles ): void {
		wp_set_current_user( self::$fixture_ids[ $role ] );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/users' )->execute(
			array(
				'id'     => $this->public_author_id,
				'fields' => array( 'id', 'user_email', 'roles' ),
			)
		);

		$this->assertIsArray( $result, sprintf( 'The %s role should be able to execute a public-author lookup.', $role ) );
		$this->assertSame( $this->public_author_id, $result['users'][0]['id'], sprintf( 'The %s role should receive the requested public author.', $role ) );
		$this->assertSame( $can_view_sensitive, array_key_exists( 'user_email', $result['users'][0] ), sprintf( 'The %s role email visibility should match expectations.', $role ) );
		$this->assertSame( $can_view_roles, array_key_exists( 'roles', $result['users'][0] ), sprintf( 'The %s role roles visibility should match expectations.', $role ) );

		if ( $can_view_sensitive ) {
			$this->assertSame( 'core-users-ability-author@example.com', $result['users'][0]['user_email'], sprintf( 'The %s role should receive the public author email when allowed.', $role ) );
		}

		if ( ! $can_view_roles ) {
			return;
		}

		$this->assertContains( 'author', $result['users'][0]['roles'], sprintf( 'The %s role should receive the public author role when allowed.', $role ) );
	}

	/**
	 * Data provider for role-based field visibility checks.
	 *
	 * @ticket 64146
	 *
	 * @return array<string, array{0: string, 1: bool, 2: bool}>
	 */
	public static function data_roles_for_another_public_author_field_visibility(): array {
		return array(
			'administrator' => array( 'administrator', true, true ),
			'editor'        => array( 'editor', false, false ),
			'author'        => array( 'author', false, false ),
			'contributor'   => array( 'contributor', false, false ),
			'subscriber'    => array( 'subscriber', false, false ),
		);
	}

	/**
	 * Role filtering requires list_users.
	 *
	 * @ticket 64146
	 */
	public function test_role_filter_requires_list_users(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/users' )->execute( array( 'roles' => array( 'author' ) ) );

		$this->assertWPError( $result, 'A subscriber should not be able to filter users by role.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Role filter denial should use the invalid permissions error.' );
	}

	/**
	 * Restricted fields are omitted per user instead of failing the whole result.
	 *
	 * @ticket 64146
	 */
	public function test_restricted_requested_fields_are_omitted_per_user(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/users' )->execute(
			array(
				'id'     => $this->public_author_id,
				'fields' => array( 'id', 'user_email', 'roles' ),
			)
		);

		$this->assertIsArray( $result, 'A public-author lookup should return an array.' );
		$this->assertSame( array( 'id' ), array_keys( $result['users'][0] ), 'Restricted requested fields should be omitted instead of failing the request.' );
		$this->assertSame( $this->public_author_id, $result['users'][0]['id'], 'The public-author lookup should still return unrestricted fields.' );
	}

	/**
	 * Missing or inaccessible single-user lookups fail closed.
	 *
	 * @ticket 64146
	 */
	public function test_missing_single_user_lookup_fails_closed(): void {
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/users' )->execute( array( 'id' => 999999 ) );

		$this->assertWPError( $result, 'Missing single-user lookups should fail closed.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Missing single-user lookups should use the invalid permissions error.' );
	}
}
