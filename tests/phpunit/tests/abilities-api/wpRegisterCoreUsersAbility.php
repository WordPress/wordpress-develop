<?php

declare( strict_types = 1 );

/**
 * Tests for the core/read-users ability.
 *
 * @covers WP_Users_Abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreUsersAbility extends WP_UnitTestCase {

	/**
	 * Shared fixture IDs.
	 *
	 * @since 7.1.0
	 * @var array<string, int>
	 */
	private static $fixture_ids = array();

	/**
	 * Administrator user ID.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	private $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	private $subscriber_id;

	/**
	 * Author user ID with a published post.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	private $public_author_id;

	/**
	 * Author post ID.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	private $public_post_id;

	/**
	 * Original show_avatars option.
	 *
	 * @since 7.1.0
	 * @var mixed
	 */
	private $show_avatars;

	/**
	 * Set up before the class.
	 *
	 * @since 7.1.0
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

		if ( is_multisite() ) {
			grant_super_admin( self::$fixture_ids['administrator'] );
		}

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
	 * @since 7.1.0
	 */
	public static function tear_down_after_class(): void {
		wp_delete_post( self::$fixture_ids['public_post'], true );

		if ( is_multisite() ) {
			revoke_super_admin( self::$fixture_ids['administrator'] );
		}

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
	 * @since 7.1.0
	 */
	public function set_up(): void {
		parent::set_up();

		$this->show_avatars = get_option( 'show_avatars' );
		update_option( 'show_avatars', 1 );

		$this->admin_id         = self::$fixture_ids['administrator'];
		$this->subscriber_id    = self::$fixture_ids['subscriber'];
		$this->public_author_id = self::$fixture_ids['public_author'];
		$this->public_post_id   = self::$fixture_ids['public_post'];
	}

	/**
	 * Tear down test case.
	 *
	 * @since 7.1.0
	 */
	public function tear_down(): void {
		if ( wp_has_ability( 'core/read-users' ) ) {
			wp_unregister_ability( 'core/read-users' );
		}

		update_option( 'show_avatars', $this->show_avatars );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Registers the core/read-users ability inside a faked init action.
	 *
	 * @since 7.1.0
	 */
	private function register_core_users_ability(): void {
		if ( wp_has_ability( 'core/read-users' ) ) {
			wp_unregister_ability( 'core/read-users' );
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
	 * @ticket 64657
	 */
	public function test_core_users_ability_is_registered(): void {
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/read-users' );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The users ability should be registered.' );
		$this->assertSame( 'user', $ability->get_category(), 'The users ability should use the user category.' );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ), 'The users ability should be exposed over REST.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'], 'The users ability should be marked read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The users ability should not be marked destructive.' );
	}

	/**
	 * The input schema exposes strict single-user and collection modes.
	 *
	 * @ticket 64657
	 */
	public function test_core_users_input_schema_exposes_strict_modes(): void {
		$this->register_core_users_ability();

		$schema = wp_get_ability( 'core/read-users' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'], 'The users ability input schema should describe an object.' );
		$this->assertEquals( (object) array(), $schema['default'], 'The users ability input schema should default to empty collection mode.' );
		$this->assertCount( 5, $schema['oneOf'], 'The users ability input schema should expose four lookup modes and collection mode.' );

		$this->assertSame( array( 'id' ), $schema['oneOf'][0]['required'], 'The first input mode should require an ID.' );
		$this->assertSame( array( 'email' ), $schema['oneOf'][1]['required'], 'The second input mode should require an email.' );
		$this->assertSame( array( 'username' ), $schema['oneOf'][2]['required'], 'The third input mode should require a username.' );
		$this->assertSame( array( 'slug' ), $schema['oneOf'][3]['required'], 'The fourth input mode should require a slug.' );
		$this->assertArrayNotHasKey( 'required', $schema['oneOf'][4], 'Collection mode should allow an empty request.' );

		$collection_properties = $schema['oneOf'][4]['properties'];
		$this->assertEqualSets(
			array( 'roles', 'has_published_posts', 'include', 'fields', 'page', 'per_page' ),
			array_keys( $collection_properties ),
			'Collection mode should expose only the supported query parameters.'
		);
		$excluded_properties = array(
			'search',
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
		$this->assertSame( 1, $schema['oneOf'][4]['properties']['fields']['minItems'], 'The fields option should require at least one field when provided.' );

		$role_names = $schema['oneOf'][4]['properties']['roles']['items']['enum'];
		$this->assertEqualSets( array_keys( wp_roles()->roles ), $role_names, 'The roles query enum should expose registered role names.' );

		$post_type_names = $schema['oneOf'][4]['properties']['has_published_posts']['oneOf'][1]['items']['enum'];
		$this->assertContains( 'post', $post_type_names, 'The has_published_posts enum should expose public post types.' );
		$this->assertNotContains( 'revision', $post_type_names, 'The has_published_posts enum should omit non-public post types.' );

		$this->assertSame( 1, $collection_properties['include']['minItems'], 'The include option should require at least one user ID.' );
		$this->assertTrue( $collection_properties['include']['uniqueItems'], 'The include option should reject duplicate user IDs.' );
		$this->assertSame( 'integer', $collection_properties['include']['items']['type'], 'The include option should contain user IDs.' );
		$this->assertSame( 1, $collection_properties['include']['items']['minimum'], 'The include option should contain positive user IDs.' );

		$output_schema     = wp_get_ability( 'core/read-users' )->get_output_schema();
		$user_schema       = $output_schema['oneOf'][0];
		$collection_schema = $output_schema['oneOf'][1];
		$user_properties   = $user_schema['properties'];

		$this->assertCount( 2, $output_schema['oneOf'], 'The output schema should describe single-user and collection responses.' );
		$this->assertArrayNotHasKey( 'required', $user_schema, 'Single-user fields should remain optional.' );
		$this->assertSame( array( 'users', 'total', 'total_pages' ), $collection_schema['required'], 'Collection responses should require the wrapper fields.' );
		$this->assertSame( 'date-time', $user_properties['registered_date']['format'], 'The registered_date output schema should use date-time format.' );
		$this->assertSame( 'string', $user_properties['roles']['items']['type'], 'The roles output schema should describe role name strings.' );
		$this->assertArrayNotHasKey( 'enum', $user_properties['roles']['items'], 'The roles output schema must not pin an enum, so a role registered after the schema snapshot still validates.' );
	}

	/**
	 * Avatar availability is enforced when formatting, not in the schemas.
	 *
	 * The registered schemas are a registration-time snapshot, so they always
	 * declare avatar_urls; the show_avatars option is honored per call instead,
	 * even when it changes after the ability is registered.
	 *
	 * @ticket 64657
	 */
	public function test_avatar_urls_respects_show_avatars_option(): void {
		update_option( 'show_avatars', 0 );
		$this->register_core_users_ability();

		$ability       = wp_get_ability( 'core/read-users' );
		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();

		$this->assertContains( 'avatar_urls', $input_schema['oneOf'][0]['properties']['fields']['items']['enum'], 'The fields enum should keep declaring avatar_urls when avatars are disabled.' );
		$this->assertArrayHasKey( 'avatar_urls', $output_schema['oneOf'][0]['properties'], 'The output schema should keep declaring avatar_urls when avatars are disabled.' );

		wp_set_current_user( $this->subscriber_id );
		$result = $ability->execute( array( 'id' => $this->subscriber_id ) );

		$this->assertIsArray( $result, 'The current user should still be readable when avatars are disabled.' );
		$this->assertArrayNotHasKey( 'avatar_urls', $result, 'The ability result should omit avatar_urls when avatars are disabled.' );

		// Enabling the option after registration takes effect immediately; the
		// registration-time schema must not reject the field.
		update_option( 'show_avatars', 1 );
		$result = $ability->execute( array( 'id' => $this->subscriber_id ) );

		$this->assertIsArray( $result, 'Default-fields lookups should succeed after avatars are enabled post-registration.' );
		$this->assertArrayHasKey( 'avatar_urls', $result, 'The ability result should include avatar_urls once avatars are enabled.' );
	}

	/**
	 * Omitted fields return a lean default shape.
	 *
	 * @ticket 64657
	 */
	public function test_omitted_fields_return_lean_defaults(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute( array( 'id' => $this->subscriber_id ) );

		$this->assertIsArray( $result, 'A current-user lookup should return an array.' );
		$this->assertSame(
			array( 'id', 'name', 'link', 'slug', 'avatar_urls' ),
			array_keys( $result ),
			'Omitted fields should return the lean default field set.'
		);
		$this->assertArrayNotHasKey( 'email', $result, 'Default fields should not include sensitive user fields.' );
		$this->assertArrayNotHasKey( 'description', $result, 'Default fields should omit less common read-context fields.' );
	}

	/**
	 * Logged-out users cannot run the ability.
	 *
	 * @ticket 64657
	 */
	public function test_core_users_requires_logged_in_user(): void {
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute( array() );

		$this->assertWPError( $result, 'Logged-out users should not be allowed to execute the users ability.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Logged-out users should receive an invalid permissions error.' );
	}

	/**
	 * The current user can read themselves by ID, email, and username.
	 *
	 * @ticket 64657
	 */
	public function test_current_user_can_read_themselves_by_sensitive_identifiers(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/read-users' );

		$result = $ability->execute(
			array(
				'id'     => $this->subscriber_id,
				'fields' => array( 'id', 'email', 'username', 'roles' ),
			)
		);

		$this->assertIsArray( $result, 'A user should be able to read themselves by ID.' );
		$this->assertSame( $this->subscriber_id, $result['id'], 'The ID lookup should return the current user.' );
		$this->assertSame( 'core-users-ability-subscriber@example.com', $result['email'], 'The current user should receive their own email.' );
		$this->assertSame( 'core_users_ability_subscriber', $result['username'], 'The current user should receive their own username.' );
		$this->assertContains( 'subscriber', $result['roles'], 'The current user should receive their own roles.' );

		$result = $ability->execute( array( 'email' => 'core-users-ability-subscriber@example.com' ) );
		$this->assertIsArray( $result, 'A user should be able to read themselves by email.' );
		$this->assertSame( $this->subscriber_id, $result['id'], 'The email lookup should return the current user.' );

		$result = $ability->execute( array( 'username' => 'core_users_ability_subscriber' ) );
		$this->assertIsArray( $result, 'A user should be able to read themselves by username.' );
		$this->assertSame( $this->subscriber_id, $result['id'], 'The username lookup should return the current user.' );

		$result = $ability->execute(
			array(
				'id'     => $this->subscriber_id,
				'fields' => array( 'id', 'registered_date' ),
			)
		);
		$this->assertIsArray( $result, 'A user should be able to request their registration date.' );
		$this->assertSame(
			gmdate( 'c', strtotime( get_userdata( $this->subscriber_id )->user_registered ) ),
			$result['registered_date'],
			'The registration date should be formatted as an ISO 8601 date-time string.'
		);
	}

	/**
	 * Public-author users can be read by ID or slug by logged-in users.
	 *
	 * @ticket 64657
	 */
	public function test_public_author_can_be_read_by_id_and_slug(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/read-users' );

		$result = $ability->execute(
			array(
				'id'     => $this->public_author_id,
				'fields' => array( 'id', 'slug', 'email' ),
			)
		);

		$this->assertIsArray( $result, 'A logged-in user should be able to read a public author by ID.' );
		$this->assertSame( $this->public_author_id, $result['id'], 'The ID lookup should return the public author.' );
		$this->assertSame( 'core-users-ability-author', $result['slug'], 'The public author slug should be returned.' );
		$this->assertArrayNotHasKey( 'email', $result, 'Public-author access should not expose another user email.' );

		$result = $ability->execute( array( 'slug' => 'core-users-ability-author' ) );

		$this->assertIsArray( $result, 'A logged-in user should be able to read a public author by slug.' );
		$this->assertSame( $this->public_author_id, $result['id'], 'The slug lookup should return the public author.' );
	}

	/**
	 * A single-user lookup resolves an author whose posts are all private, while
	 * collection mode does not.
	 *
	 * The two modes enforce access differently. A single-user lookup calls
	 * count_user_posts(), which counts private posts for a caller holding
	 * read_private_posts, matching the REST users controller. Collection mode is
	 * filtered by WP_User_Query's has_published_posts, which matches published posts
	 * only, so it never returns such an author.
	 *
	 * This exposes nothing new. An editor who can read the private post already sees
	 * its author through the post itself.
	 *
	 * @ticket 64657
	 */
	public function test_private_only_author_resolves_by_lookup_but_not_in_a_collection(): void {
		$author_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_nicename' => 'core-users-ability-private-author',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $author_id,
				'post_status' => 'private',
				'post_type'   => 'post',
			)
		);

		wp_set_current_user( self::$fixture_ids['editor'] );
		$this->register_core_users_ability();

		$this->assertFalse( current_user_can( 'list_users' ), 'An editor should not be able to list users.' );
		$this->assertFalse( current_user_can( 'edit_user', $author_id ), 'An editor should not be able to edit the author.' );
		$this->assertTrue( current_user_can( 'read_private_posts' ), 'An editor should be able to read private posts.' );

		$ability = wp_get_ability( 'core/read-users' );

		$result = $ability->execute(
			array(
				'slug'   => 'core-users-ability-private-author',
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'An editor should resolve an author whose posts are all private.' );
		$this->assertSame( $author_id, $result['id'], 'The slug lookup should return the private-only author.' );

		$result = $ability->execute(
			array(
				'include'  => array( $author_id ),
				'fields'   => array( 'id' ),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $result, 'The collection request should succeed.' );
		$this->assertSame( array(), $result['users'], 'Collection mode should omit an author whose posts are all private.' );

		// The lookup is gated on the capability, not merely on being logged in.
		wp_set_current_user( $this->subscriber_id );

		$result = $ability->execute(
			array(
				'slug'   => 'core-users-ability-private-author',
				'fields' => array( 'id' ),
			)
		);

		$this->assertWPError( $result, 'A subscriber should not resolve an author whose posts are all private.' );
	}

	/**
	 * Email and username lookups for another user require list or edit permissions across roles.
	 *
	 * @ticket 64657
	 *
	 * @dataProvider data_roles_for_sensitive_identifier_lookup_permissions
	 *
	 * @param string $role        Current user's role.
	 * @param bool   $can_resolve Whether the role can resolve another user by sensitive identifiers.
	 */
	public function test_roles_have_expected_sensitive_identifier_lookup_permissions( string $role, bool $can_resolve ): void {
		wp_set_current_user( self::$fixture_ids[ $role ] );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/read-users' );

		$result = $ability->execute( array( 'email' => 'core-users-ability-author@example.com' ) );
		if ( $can_resolve ) {
			$this->assertIsArray( $result, sprintf( 'The %s role should be able to resolve another user by email.', $role ) );
			$this->assertSame( $this->public_author_id, $result['id'], sprintf( 'The email lookup should return the public author for the %s role.', $role ) );
		} else {
			$this->assertWPError( $result, sprintf( 'The %s role should not be able to resolve another user by email.', $role ) );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), sprintf( 'Email lookup denial for the %s role should use the invalid permissions error.', $role ) );
		}

		$result = $ability->execute( array( 'username' => 'core_users_ability_author' ) );
		if ( $can_resolve ) {
			$this->assertIsArray( $result, sprintf( 'The %s role should be able to resolve another user by username.', $role ) );
			$this->assertSame( $this->public_author_id, $result['id'], sprintf( 'The username lookup should return the public author for the %s role.', $role ) );
			return;
		}

		$this->assertWPError( $result, sprintf( 'The %s role should not be able to resolve another user by username.', $role ) );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), sprintf( 'Username lookup denial for the %s role should use the invalid permissions error.', $role ) );
	}

	/**
	 * Data provider for role-based sensitive identifier lookup checks.
	 *
	 * @ticket 64657
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
	 * @ticket 64657
	 */
	public function test_empty_collection_mode_restricts_users_without_list_users_to_public_authors(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute( array() );

		$this->assertIsArray( $result, 'Collection mode should return an array for logged-in users.' );
		$this->assertContains( $this->public_author_id, wp_list_pluck( $result['users'], 'id' ), 'Collection mode should include public authors.' );
		$this->assertNotContains( $this->admin_id, wp_list_pluck( $result['users'], 'id' ), 'Collection mode should omit non-author administrators for users without list_users.' );
		$this->assertNotContains( $this->subscriber_id, wp_list_pluck( $result['users'], 'id' ), 'Collection mode should omit subscribers for users without list_users.' );
		$this->assertIsInt( $result['total'], 'Collection mode should include an integer total.' );
		$this->assertIsInt( $result['total_pages'], 'Collection mode should include an integer total_pages value.' );
	}

	/**
	 * Collection include limits results to the requested users.
	 *
	 * @ticket 64657
	 */
	public function test_collection_include_limits_results(): void {
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute(
			array(
				'include'  => array( $this->public_author_id, $this->subscriber_id ),
				'fields'   => array( 'id' ),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $result, 'An included user query should return an array.' );
		$this->assertEqualSets(
			array( $this->public_author_id, $this->subscriber_id ),
			wp_list_pluck( $result['users'], 'id' ),
			'Included user queries should return exactly the readable included users.'
		);
		$this->assertSame( 2, $result['total'], 'Included user queries should report the matching included total.' );
	}

	/**
	 * Collection results keep the default ordering, not the order of the include list.
	 *
	 * The query deliberately leaves `orderby` alone rather than setting it to
	 * `include`, so that queries over the same users can share a WP_User_Query cache
	 * entry. This test fails if that ordering is ever applied.
	 *
	 * @ticket 64657
	 */
	public function test_include_order_does_not_control_the_result_order(): void {
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/read-users' );

		// WP_User_Query orders by user_login ascending by default, and
		// 'core_users_ability_admin' sorts before 'core_users_ability_subscriber'.
		$expected = array( $this->admin_id, $this->subscriber_id );

		foreach ( array( $expected, array_reverse( $expected ) ) as $include ) {
			$result = $ability->execute(
				array(
					'include' => $include,
					'fields'  => array( 'id' ),
				)
			);

			$this->assertIsArray( $result, 'An included user query should succeed.' );
			$this->assertSame(
				$expected,
				wp_list_pluck( $result['users'], 'id' ),
				'Included users should be ordered by login, whichever order the include list uses.'
			);
		}
	}

	/**
	 * Collection include still respects row-level read permissions.
	 *
	 * @ticket 64657
	 */
	public function test_collection_include_respects_read_permissions(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute(
			array(
				'include'  => array( $this->admin_id, $this->public_author_id ),
				'fields'   => array( 'id' ),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $result, 'An included user query should return an array.' );
		$this->assertSame(
			array( $this->public_author_id ),
			wp_list_pluck( $result['users'], 'id' ),
			'Included user queries should omit users the current user cannot read.'
		);
	}

	/**
	 * A post type that is public but not publicly viewable never makes a user visible.
	 *
	 * `public => true` does not mean a post type can be viewed on the front end, so the
	 * ability uses is_post_type_viewable() everywhere. A boolean has_published_posts
	 * must not fall back to WP_User_Query's own reading of `true`, which resolves to
	 * get_post_types( array( 'public' => true ) ) and would match such an author.
	 *
	 * @ticket 64657
	 */
	public function test_public_but_not_viewable_post_type_never_exposes_an_author(): void {
		register_post_type(
			'wpai_not_viewable_pt',
			array(
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		$this->assertContains( 'wpai_not_viewable_pt', get_post_types( array( 'public' => true ) ), 'The post type should be in the public set.' );
		$this->assertFalse( is_post_type_viewable( 'wpai_not_viewable_pt' ), 'The post type should not be publicly viewable.' );

		$author_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_nicename' => 'core-users-ability-hidden-author',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $author_id,
				'post_status' => 'publish',
				'post_type'   => 'wpai_not_viewable_pt',
			)
		);

		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/read-users' );

		$schema = $ability->get_input_schema();
		$this->assertNotContains(
			'wpai_not_viewable_pt',
			$schema['oneOf'][4]['properties']['has_published_posts']['oneOf'][1]['items']['enum'],
			'The post type should not be offered in the has_published_posts enum.'
		);

		$result = $ability->execute(
			array(
				'has_published_posts' => true,
				'fields'              => array( 'id' ),
				'per_page'            => 100,
			)
		);

		$this->assertIsArray( $result, 'A boolean published-posts filter should execute.' );
		$this->assertNotContains(
			$author_id,
			wp_list_pluck( $result['users'], 'id' ),
			'A boolean published-posts filter should not match an author whose only post type is not publicly viewable.'
		);

		// The same author stays hidden from the single-user lookup used for public authors.
		wp_set_current_user( $this->subscriber_id );

		$result = $ability->execute(
			array(
				'slug'   => 'core-users-ability-hidden-author',
				'fields' => array( 'id' ),
			)
		);

		$this->assertWPError( $result, 'A non-viewable post type should not make its author publicly readable.' );

		unregister_post_type( 'wpai_not_viewable_pt' );
	}

	/**
	 * Collection mode excludes a caller with no published posts, even via include,
	 * while single-user mode still lets them read themselves.
	 *
	 * This mirrors WordPress core: a caller without permission to list users only sees
	 * public authors in a collection, and reads their own account through a single-user
	 * lookup (like the REST `/users/me` endpoint) rather than the collection.
	 *
	 * @ticket 64657
	 */
	public function test_collection_mode_excludes_self_without_published_posts(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/read-users' );

		$collection = $ability->execute(
			array(
				'include'  => array( $this->subscriber_id ),
				'fields'   => array( 'id' ),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $collection, 'A collection include query should return an array.' );
		$this->assertSame( array(), $collection['users'], 'Collection mode should exclude a caller with no published posts, even when they include their own ID.' );
		$this->assertSame( 0, $collection['total'], 'The excluded self should not be counted in the collection total.' );

		$single = $ability->execute(
			array(
				'id'     => $this->subscriber_id,
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $single, 'A single-user self lookup should return an array.' );
		$this->assertSame( $this->subscriber_id, $single['id'], 'Single-user mode should still let the caller read themselves.' );
	}

	/**
	 * Include is a collection-only option.
	 *
	 * @ticket 64657
	 */
	public function test_include_cannot_be_combined_with_single_user_modes(): void {
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$by_id = wp_get_ability( 'core/read-users' )->execute(
			array(
				'id'      => $this->subscriber_id,
				'include' => array( $this->subscriber_id ),
			)
		);

		$by_username = wp_get_ability( 'core/read-users' )->execute(
			array(
				'username' => 'core_users_ability_subscriber',
				'include'  => array( $this->subscriber_id ),
			)
		);

		$this->assertWPError( $by_id, 'ID plus include should fail validation.' );
		$this->assertWPError( $by_username, 'Username plus include should fail validation.' );
		$this->assertSame( 'ability_invalid_input', $by_id->get_error_code(), 'ID plus include should return an input error.' );
		$this->assertSame( 'ability_invalid_input', $by_username->get_error_code(), 'Username plus include should return an input error.' );
	}

	/**
	 * Collection mode for users without list_users honors public post type filters.
	 *
	 * @ticket 64657
	 */
	public function test_collection_mode_for_users_without_list_users_uses_public_post_types(): void {
		register_post_type(
			'wpai_public_pt',
			array(
				'public'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_type(
			'wpai_private_pt',
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
				'post_type'   => 'wpai_public_pt',
			)
		);
		$private_post_id   = self::factory()->post->create(
			array(
				'post_author' => $private_author_id,
				'post_status' => 'publish',
				'post_type'   => 'wpai_private_pt',
			)
		);

		try {
			$this->assertFalse( get_post_type_object( 'wpai_public_pt' )->show_in_rest, 'The public fixture post type should remain hidden from REST.' );

			wp_set_current_user( $this->subscriber_id );
			$this->register_core_users_ability();

			$ability = wp_get_ability( 'core/read-users' );
			$schema  = $ability->get_input_schema();
			$enum    = $schema['oneOf'][4]['properties']['has_published_posts']['oneOf'][1]['items']['enum'];

			$this->assertContains( 'wpai_public_pt', $enum, 'The has_published_posts enum should include public post types even when hidden from REST.' );
			$this->assertNotContains( 'wpai_private_pt', $enum, 'The has_published_posts enum should omit private post types.' );

			$result = $ability->execute(
				array(
					'has_published_posts' => array( 'wpai_public_pt' ),
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
					'has_published_posts' => array( 'wpai_private_pt' ),
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
			unregister_post_type( 'wpai_public_pt' );
			unregister_post_type( 'wpai_private_pt' );
		}
	}

	/**
	 * Administrators can query by role and receive roles.
	 *
	 * @ticket 64657
	 */
	public function test_admin_can_query_by_role_and_receive_roles(): void {
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute(
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
	 * @ticket 64657
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

		$result = wp_get_ability( 'core/read-users' )->execute(
			array(
				'id'     => $this->public_author_id,
				'fields' => array( 'id', 'email', 'roles' ),
			)
		);

		$this->assertIsArray( $result, sprintf( 'The %s role should be able to execute a public-author lookup.', $role ) );
		$this->assertSame( $this->public_author_id, $result['id'], sprintf( 'The %s role should receive the requested public author.', $role ) );
		$this->assertSame( $can_view_sensitive, array_key_exists( 'email', $result ), sprintf( 'The %s role email visibility should match expectations.', $role ) );
		$this->assertSame( $can_view_roles, array_key_exists( 'roles', $result ), sprintf( 'The %s role roles visibility should match expectations.', $role ) );

		if ( $can_view_sensitive ) {
			$this->assertSame( 'core-users-ability-author@example.com', $result['email'], sprintf( 'The %s role should receive the public author email when allowed.', $role ) );
		}

		if ( ! $can_view_roles ) {
			return;
		}

		$this->assertContains( 'author', $result['roles'], sprintf( 'The %s role should receive the public author role when allowed.', $role ) );
	}

	/**
	 * Data provider for role-based field visibility checks.
	 *
	 * @ticket 64657
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
	 * A caller who can list users but not edit them does not receive another
	 * user's roles.
	 *
	 * `list_users` grants no edit rights, so it must not disclose a user's roles,
	 * matching the REST users controller, which confines `roles` to users the
	 * caller can edit.
	 *
	 * @ticket 64657
	 */
	public function test_list_users_without_edit_does_not_expose_other_users_roles(): void {
		add_role(
			'core_users_ability_list_only',
			'List Only',
			array(
				'read'       => true,
				'list_users' => true,
			)
		);

		$lister_id = self::factory()->user->create( array( 'role' => 'core_users_ability_list_only' ) );

		try {
			wp_set_current_user( $lister_id );
			$this->register_core_users_ability();

			$result = wp_get_ability( 'core/read-users' )->execute(
				array(
					'id'     => $this->public_author_id,
					'fields' => array( 'id', 'roles' ),
				)
			);

			$this->assertIsArray( $result, 'A list-only caller should still resolve a public author.' );
			$this->assertArrayNotHasKey( 'roles', $result, 'A caller who cannot edit a user must not receive that user\'s roles.' );
		} finally {
			wp_delete_user( $lister_id );
			remove_role( 'core_users_ability_list_only' );
		}
	}

	/**
	 * A role registered after the ability's schema snapshot does not fail output
	 * validation when a returned user holds it.
	 *
	 * The output schema is a registration-time snapshot; a role added afterwards
	 * still appears on a user, so the roles output must not be pinned to a frozen
	 * enum that would reject the value and fail the whole call.
	 *
	 * @ticket 64657
	 */
	public function test_roles_registered_after_registration_do_not_fail_output_validation(): void {
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		// Register the role only after the ability (and its output schema) exist.
		add_role( 'core_users_ability_late_role', 'Late Role', array( 'read' => true ) );
		$user_id = self::factory()->user->create( array( 'role' => 'core_users_ability_late_role' ) );

		try {
			$result = wp_get_ability( 'core/read-users' )->execute(
				array(
					'id'     => $user_id,
					'fields' => array( 'id', 'roles' ),
				)
			);

			$this->assertIsArray( $result, 'Reading a user with a role added after registration should not fail output validation.' );
			$this->assertContains( 'core_users_ability_late_role', $result['roles'], 'The late-registered role should be returned.' );
		} finally {
			wp_delete_user( $user_id );
			remove_role( 'core_users_ability_late_role' );
		}
	}

	/**
	 * Role filtering requires list_users.
	 *
	 * @ticket 64657
	 */
	public function test_role_filter_requires_list_users(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute( array( 'roles' => array( 'author' ) ) );

		$this->assertWPError( $result, 'A subscriber should not be able to filter users by role.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Role filter denial should use the invalid permissions error.' );
	}

	/**
	 * Restricted fields are omitted per user instead of failing the whole result.
	 *
	 * @ticket 64657
	 */
	public function test_restricted_requested_fields_are_omitted_per_user(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute(
			array(
				'id'     => $this->public_author_id,
				'fields' => array( 'id', 'email', 'roles' ),
			)
		);

		$this->assertIsArray( $result, 'A public-author lookup should return an array.' );
		$this->assertSame( array( 'id' ), array_keys( $result ), 'Restricted requested fields should be omitted instead of failing the request.' );
		$this->assertSame( $this->public_author_id, $result['id'], 'The public-author lookup should still return unrestricted fields.' );
	}

	/**
	 * A fully redacted field request still returns the user's ID.
	 *
	 * The `id` field is always included, matching the REST users controller
	 * where `id` is present in every context. This also guarantees the result
	 * serializes as a JSON object (`{}`-shaped, never `[]`), honoring the
	 * declared output type without returning a top-level empty object, which
	 * REST post-processing (`_fields`) cannot handle.
	 *
	 * @ticket 64657
	 */
	public function test_fully_redacted_field_request_still_returns_id(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/read-users' );

		$result = $ability->execute(
			array(
				'id'     => $this->public_author_id,
				'fields' => array( 'email', 'roles' ),
			)
		);

		$this->assertIsArray( $result, 'A fully redacted field request should still succeed.' );
		$this->assertSame( array( 'id' => $this->public_author_id ), $result, 'A fully redacted field request should return only the user ID.' );
		$this->assertSame(
			sprintf( '{"id":%d}', $this->public_author_id ),
			wp_json_encode( $result ),
			'A fully redacted field request should serialize as a JSON object.'
		);

		$collection = $ability->execute(
			array(
				'include' => array( $this->public_author_id ),
				'fields'  => array( 'email' ),
			)
		);

		$this->assertIsArray( $collection, 'A collection with redacted fields should return the wrapper.' );
		$this->assertSame(
			array( 'id' => $this->public_author_id ),
			$collection['users'][0],
			'Redacted collection entries should still contain the user ID.'
		);
	}

	/**
	 * Schema-valid object input behaves like its array form.
	 *
	 * Schema validation accepts `stdClass` input (the input schema's own
	 * `default` is one), so it must not be discarded and silently turned into
	 * an unrestricted collection query.
	 *
	 * @ticket 64657
	 */
	public function test_object_input_behaves_like_array_input(): void {
		wp_set_current_user( $this->subscriber_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute(
			(object) array(
				'id'     => $this->subscriber_id,
				'fields' => array( 'id', 'email' ),
			)
		);

		$this->assertIsArray( $result, 'Object input should execute the single-user lookup.' );
		$this->assertSame( $this->subscriber_id, $result['id'], 'Object input must resolve the requested user, not fall back to collection mode.' );
		$this->assertSame( 'core-users-ability-subscriber@example.com', $result['email'], 'Object input should honor the requested fields.' );
	}

	/**
	 * REST-style string input is coerced by the normalizers.
	 *
	 * Read-only abilities run over REST `GET`, where booleans arrive as the
	 * string 'true', arrays may arrive as CSV strings, and integers arrive as
	 * numeric strings. Those values pass schema validation, which coerces only
	 * for the check, so the normalizers must accept the string forms instead
	 * of silently dropping the filters they carry.
	 *
	 * @ticket 64657
	 */
	public function test_rest_style_string_input_is_coerced(): void {
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$ability = wp_get_ability( 'core/read-users' );

		$result = $ability->execute(
			array(
				'has_published_posts' => 'true',
				'fields'              => array( 'id' ),
				'per_page'            => 100,
			)
		);

		$this->assertIsArray( $result, 'A string boolean published-posts filter should execute.' );
		$ids = wp_list_pluck( $result['users'], 'id' );
		$this->assertContains( $this->public_author_id, $ids, 'The published-posts filter should include public authors.' );
		$this->assertNotContains( $this->subscriber_id, $ids, 'A string "true" must enable the published-posts filter rather than being silently dropped.' );

		$result = $ability->execute(
			array(
				'roles'    => 'editor',
				'fields'   => array( 'id' ),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $result, 'A CSV roles filter should execute.' );
		$ids = wp_list_pluck( $result['users'], 'id' );
		$this->assertContains( self::$fixture_ids['editor'], $ids, 'The roles filter should include editors.' );
		$this->assertNotContains( $this->subscriber_id, $ids, 'A CSV roles value must filter by role rather than being silently dropped.' );

		$result = $ability->execute(
			array(
				'include' => (string) $this->subscriber_id,
				'fields'  => 'id,email',
			)
		);

		$this->assertIsArray( $result, 'A CSV include filter should execute.' );
		$this->assertSame( array( $this->subscriber_id ), wp_list_pluck( $result['users'], 'id' ), 'A string include value must limit the query to the included IDs.' );
		$this->assertSame( 'core-users-ability-subscriber@example.com', $result['users'][0]['email'], 'A CSV fields value must select the requested fields.' );

		// IDs that are distinct as strings but equal as integers ('7' vs '07')
		// pass schema validation; they must still collapse to one filtered ID.
		$result = $ability->execute(
			array(
				'include' => array( (string) $this->subscriber_id, '0' . $this->subscriber_id ),
				'fields'  => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'A string-duplicate include filter should execute.' );
		$this->assertSame( array( $this->subscriber_id ), wp_list_pluck( $result['users'], 'id' ), 'String-duplicate IDs must be deduplicated, not silently drop the include filter.' );
		$this->assertSame( 1, $result['total'], 'String-duplicate IDs should count as one included user.' );

		// Scalars arrive as numeric strings over GET.
		$result = $ability->execute(
			array(
				'id'     => (string) $this->subscriber_id,
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'A numeric-string ID lookup should execute.' );
		$this->assertSame( $this->subscriber_id, $result['id'], 'A numeric-string ID must resolve the single-user lookup.' );

		$result = $ability->execute(
			array(
				'per_page' => '1',
				'page'     => '2',
				'fields'   => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'Numeric-string pagination should execute.' );
		$this->assertCount( 1, $result['users'], 'A numeric-string per_page must limit the page size.' );
		$this->assertGreaterThan( 1, $result['total_pages'], 'A numeric-string per_page must be reflected in total_pages.' );
	}

	/**
	 * An unknown requested field name fails schema validation.
	 *
	 * Unlike inaccessible fields, which are omitted per user, a field name that is
	 * not part of the supported set is rejected before the ability executes.
	 *
	 * @ticket 64657
	 */
	public function test_unknown_requested_field_fails_schema_validation(): void {
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute(
			array(
				'id'     => $this->admin_id,
				'fields' => array( 'id', 'bogus_field' ),
			)
		);

		$this->assertWPError( $result, 'An unknown requested field should fail the request.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Unknown fields should use the invalid input error.' );
	}

	/**
	 * Requesting an unavailable field omits it instead of failing.
	 *
	 * When avatars are disabled, avatar_urls stays requestable (the schema is a
	 * registration-time snapshot) and is omitted per user, matching how
	 * permission-restricted fields behave.
	 *
	 * @ticket 64657
	 */
	public function test_unavailable_requested_field_is_omitted(): void {
		update_option( 'show_avatars', 0 );
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute(
			array(
				'id'     => $this->admin_id,
				'fields' => array( 'id', 'avatar_urls' ),
			)
		);

		$this->assertIsArray( $result, 'Requesting avatar_urls while avatars are disabled should still succeed.' );
		$this->assertSame( array( 'id' ), array_keys( $result ), 'The avatar_urls field should be omitted while avatars are disabled.' );
	}

	/**
	 * Creates a user whose stored email is empty.
	 *
	 * WordPress allows this: wp_insert_user() rejects an empty login and an empty
	 * nicename, but never an empty email. Imports and single sign-on plugins can
	 * leave such rows behind.
	 *
	 * @ticket 64657
	 *
	 * @return int The new user ID.
	 */
	private function create_user_without_email(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => '',
			)
		);
		clean_user_cache( $user_id );

		$this->assertSame( '', get_userdata( $user_id )->user_email, 'The fixture user should have an empty stored email.' );

		return $user_id;
	}

	/**
	 * A user with an empty stored email is still readable.
	 *
	 * WP_Ability::execute() validates the result against the output schema, so an
	 * email the schema cannot describe must be reported as null rather than passed
	 * through, which would fail the whole lookup.
	 *
	 * @ticket 64657
	 */
	public function test_empty_stored_email_does_not_fail_single_user_output(): void {
		$user_id = $this->create_user_without_email();

		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute(
			array(
				'id'     => $user_id,
				'fields' => array( 'id', 'email' ),
			)
		);

		$this->assertIsArray( $result, 'A user with an empty stored email should still be readable.' );
		$this->assertArrayHasKey( 'email', $result, 'A viewable email field should be present even when there is no address.' );
		$this->assertNull( $result['email'], 'An empty stored email should be reported as null.' );
	}

	/**
	 * A stored email that is_email() rejects is reported as null.
	 *
	 * WordPress runs sanitize_email() when saving. Depending on the WordPress
	 * version, saving `a@@b.c` either stores an empty string or repairs it to
	 * `a@b.c`, which is one character too short for is_email(). The declared
	 * `email` format only holds because such values become null.
	 *
	 * @ticket 64657
	 */
	public function test_invalid_stored_email_is_reported_as_null(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => 'a@@b.c',
			)
		);
		clean_user_cache( $user_id );

		$stored_email = get_userdata( $user_id )->user_email;

		$this->assertContains( $stored_email, array( '', 'a@b.c' ), 'WordPress should either discard or repair the invalid address.' );
		$this->assertFalse( is_email( $stored_email ), 'The stored address should still be rejected by is_email().' );

		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute(
			array(
				'id'     => $user_id,
				'fields' => array( 'id', 'email' ),
			)
		);

		$this->assertIsArray( $result, 'A user with an unusable stored email should still be readable.' );
		$this->assertNull( $result['email'], 'An email that is_email() rejects should be reported as null.' );
	}

	/**
	 * One user with an empty stored email does not break a whole collection page.
	 *
	 * Collection output is validated as a single value, so an invalid field on one
	 * row rejects every row on the page, not just the offending user.
	 *
	 * @ticket 64657
	 */
	public function test_empty_stored_email_does_not_fail_collection_output(): void {
		$user_id = $this->create_user_without_email();

		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute(
			array(
				'fields'   => array( 'id', 'email' ),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $result, 'One user with an empty stored email should not fail the whole collection.' );

		$ids = wp_list_pluck( $result['users'], 'id' );
		$this->assertContains( $this->admin_id, $ids, 'Users with valid emails should still be returned.' );
		$this->assertContains( $user_id, $ids, 'The user with an empty stored email should still be returned.' );
	}

	/**
	 * A suppressed avatar URL does not break the default field set.
	 *
	 * get_avatar_url() documents `string|false` as its return type, and plugins that
	 * disable Gravatar short-circuit the avatar data. Since avatar_urls is part of the
	 * default field set, a false URL would fail every call rather than only the ones
	 * that opt in.
	 *
	 * @ticket 64657
	 */
	public function test_suppressed_avatar_url_does_not_fail_output(): void {
		update_option( 'show_avatars', 1 );
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		add_filter(
			'pre_get_avatar_data',
			static function ( $args ) {
				$args['url'] = false;

				return $args;
			}
		);

		$result = wp_get_ability( 'core/read-users' )->execute( array( 'id' => $this->admin_id ) );

		$this->assertIsArray( $result, 'A suppressed avatar URL should not fail a default-fields lookup.' );
		$this->assertSame( $this->admin_id, $result['id'], 'The lookup should still resolve the requested user.' );
		$this->assertSame(
			array(
				24 => null,
				48 => null,
				96 => null,
			),
			$result['avatar_urls'],
			'Every avatar size should be reported as null when no URL can be resolved.'
		);
	}

	/**
	 * Avatar sizes that resolve to a URL survive when other sizes do not.
	 *
	 * @ticket 64657
	 */
	public function test_partially_suppressed_avatar_urls_keep_the_resolved_sizes(): void {
		update_option( 'show_avatars', 1 );
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		add_filter(
			'pre_get_avatar_data',
			static function ( $args ) {
				if ( 24 === $args['size'] ) {
					$args['url'] = false;
				}

				return $args;
			}
		);

		$result = wp_get_ability( 'core/read-users' )->execute(
			array(
				'id'     => $this->admin_id,
				'fields' => array( 'id', 'avatar_urls' ),
			)
		);

		$this->assertIsArray( $result, 'A partially suppressed avatar set should not fail the lookup.' );
		$this->assertSame( array( 24, 48, 96 ), array_keys( $result['avatar_urls'] ), 'Every avatar size should still be reported.' );
		$this->assertNull( $result['avatar_urls'][24], 'A size with no resolvable URL should be null.' );
		$this->assertIsString( $result['avatar_urls'][48], 'A size that resolves should keep its URL.' );
		$this->assertIsString( $result['avatar_urls'][96], 'A size that resolves should keep its URL.' );
	}

	/**
	 * Missing or inaccessible single-user lookups fail closed.
	 *
	 * @ticket 64657
	 */
	public function test_missing_single_user_lookup_fails_closed(): void {
		wp_set_current_user( $this->admin_id );
		$this->register_core_users_ability();

		$result = wp_get_ability( 'core/read-users' )->execute( array( 'id' => 999999 ) );

		$this->assertWPError( $result, 'Missing single-user lookups should fail closed.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Missing single-user lookups should use the invalid permissions error.' );
	}
}
