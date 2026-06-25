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
	}

	/**
	 * Tear down after the class.
	 *
	 * @since 6.9.0
	 */
	public static function tear_down_after_class(): void {
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

		$this->admin_id = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'user_login'   => 'core_users_ability_admin',
				'user_email'   => 'core-users-ability-admin@example.com',
				'user_nicename' => 'core-users-ability-admin',
			)
		);

		$this->subscriber_id = self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'user_login'   => 'core_users_ability_subscriber',
				'user_email'   => 'core-users-ability-subscriber@example.com',
				'user_nicename' => 'core-users-ability-subscriber',
			)
		);

		$this->public_author_id = self::factory()->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'core_users_ability_author',
				'user_email'   => 'core-users-ability-author@example.com',
				'user_nicename' => 'core-users-ability-author',
			)
		);

		$this->public_post_id = self::factory()->post->create(
			array(
				'post_author' => $this->public_author_id,
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		$this->register_core_users_ability();
	}

	/**
	 * Tear down test case.
	 *
	 * @since 6.9.0
	 */
	public function tear_down(): void {
		wp_delete_post( $this->public_post_id, true );
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
	 * The ability is registered in the user category and flagged read-only.
	 *
	 * @ticket 64146
	 */
	public function test_core_users_ability_is_registered(): void {
		$ability = wp_get_ability( 'core/users' );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( 'user', $ability->get_category() );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ) );
		$this->assertTrue( $ability->get_meta_item( 'pagination', false ) );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
	}

	/**
	 * The input schema exposes strict single-user and collection modes.
	 *
	 * @ticket 64146
	 */
	public function test_core_users_input_schema_exposes_strict_modes(): void {
		$schema = wp_get_ability( 'core/users' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertEquals( (object) array(), $schema['default'] );
		$this->assertCount( 5, $schema['oneOf'] );

		$this->assertSame( array( 'id' ), $schema['oneOf'][0]['required'] );
		$this->assertSame( array( 'user_email' ), $schema['oneOf'][1]['required'] );
		$this->assertSame( array( 'user_login' ), $schema['oneOf'][2]['required'] );
		$this->assertSame( array( 'user_nicename' ), $schema['oneOf'][3]['required'] );
		$this->assertArrayNotHasKey( 'required', $schema['oneOf'][4] );

		$collection_properties = $schema['oneOf'][4]['properties'];
		$this->assertSameSets(
			array( 'roles', 'has_published_posts', 'fields', 'page', 'per_page' ),
			array_keys( $collection_properties )
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
			$this->assertArrayNotHasKey( $excluded_property, $collection_properties );
		}

		$fields = $schema['oneOf'][4]['properties']['fields']['items']['enum'];
		$this->assertContains( 'roles', $fields );
		$this->assertContains( 'avatar_urls', $fields );

		$output_schema   = wp_get_ability( 'core/users' )->get_output_schema();
		$user_properties = $output_schema['properties']['users']['items']['properties'];
		$this->assertSame( 'date-time', $user_properties['user_registered']['format'] );
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

		$this->assertNotContains( 'avatar_urls', $input_schema['oneOf'][0]['properties']['fields']['items']['enum'] );
		$this->assertArrayNotHasKey( 'avatar_urls', $output_schema['properties']['users']['items']['properties'] );

		wp_set_current_user( $this->subscriber_id );
		$result = $ability->execute( array( 'id' => $this->subscriber_id ) );

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'avatar_urls', $result['users'][0] );
	}

	/**
	 * Logged-out users cannot run the ability.
	 *
	 * @ticket 64146
	 */
	public function test_core_users_requires_logged_in_user(): void {
		$result = wp_get_ability( 'core/users' )->execute( array() );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * The current user can read themselves by ID, user email, and user login.
	 *
	 * @ticket 64146
	 */
	public function test_current_user_can_read_themselves_by_sensitive_identifiers(): void {
		wp_set_current_user( $this->subscriber_id );

		$ability = wp_get_ability( 'core/users' );

		$result = $ability->execute(
			array(
				'id'     => $this->subscriber_id,
				'fields' => array( 'id', 'user_email', 'user_login', 'roles' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( $this->subscriber_id, $result['users'][0]['id'] );
		$this->assertSame( 'core-users-ability-subscriber@example.com', $result['users'][0]['user_email'] );
		$this->assertSame( 'core_users_ability_subscriber', $result['users'][0]['user_login'] );
		$this->assertContains( 'subscriber', $result['users'][0]['roles'] );

		$result = $ability->execute( array( 'user_email' => 'core-users-ability-subscriber@example.com' ) );
		$this->assertIsArray( $result );
		$this->assertSame( $this->subscriber_id, $result['users'][0]['id'] );

		$result = $ability->execute( array( 'user_login' => 'core_users_ability_subscriber' ) );
		$this->assertIsArray( $result );
		$this->assertSame( $this->subscriber_id, $result['users'][0]['id'] );

		$result = $ability->execute( array( 'id' => $this->subscriber_id, 'fields' => array( 'id', 'user_registered' ) ) );
		$this->assertIsArray( $result );
		$this->assertSame(
			gmdate( 'c', strtotime( get_userdata( $this->subscriber_id )->user_registered ) ),
			$result['users'][0]['user_registered']
		);
	}

	/**
	 * Public-author users can be read by ID or user nicename by logged-in users.
	 *
	 * @ticket 64146
	 */
	public function test_public_author_can_be_read_by_id_and_user_nicename(): void {
		wp_set_current_user( $this->subscriber_id );

		$ability = wp_get_ability( 'core/users' );

		$result = $ability->execute(
			array(
				'id'     => $this->public_author_id,
				'fields' => array( 'id', 'user_nicename', 'user_email' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( $this->public_author_id, $result['users'][0]['id'] );
		$this->assertSame( 'core-users-ability-author', $result['users'][0]['user_nicename'] );
		$this->assertArrayNotHasKey( 'user_email', $result['users'][0] );

		$result = $ability->execute( array( 'user_nicename' => 'core-users-ability-author' ) );

		$this->assertIsArray( $result );
		$this->assertSame( $this->public_author_id, $result['users'][0]['id'] );
	}

	/**
	 * User email and login lookups for another user require list or edit permissions.
	 *
	 * @ticket 64146
	 */
	public function test_user_email_and_login_lookup_for_another_user_requires_permission(): void {
		wp_set_current_user( $this->subscriber_id );

		$ability = wp_get_ability( 'core/users' );

		$result = $ability->execute( array( 'user_email' => 'core-users-ability-author@example.com' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );

		$result = $ability->execute( array( 'user_login' => 'core_users_ability_author' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );

		wp_set_current_user( $this->admin_id );

		$result = $ability->execute( array( 'user_email' => 'core-users-ability-author@example.com' ) );
		$this->assertIsArray( $result );
		$this->assertSame( $this->public_author_id, $result['users'][0]['id'] );

		$result = $ability->execute( array( 'user_login' => 'core_users_ability_author' ) );
		$this->assertIsArray( $result );
		$this->assertSame( $this->public_author_id, $result['users'][0]['id'] );
	}

	/**
	 * Empty collection mode returns only public authors for users without list_users.
	 *
	 * @ticket 64146
	 */
	public function test_empty_collection_mode_restricts_users_without_list_users_to_public_authors(): void {
		wp_set_current_user( $this->subscriber_id );

		$result = wp_get_ability( 'core/users' )->execute( array() );

		$this->assertIsArray( $result );
		$this->assertContains( $this->public_author_id, wp_list_pluck( $result['users'], 'id' ) );
		$this->assertNotContains( $this->admin_id, wp_list_pluck( $result['users'], 'id' ) );
		$this->assertNotContains( $this->subscriber_id, wp_list_pluck( $result['users'], 'id' ) );
		$this->assertIsInt( $result['total'] );
		$this->assertIsInt( $result['total_pages'] );
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
			$this->assertFalse( get_post_type_object( 'wp_public_pt' )->show_in_rest );

			wp_set_current_user( $this->subscriber_id );

			$ability = wp_get_ability( 'core/users' );
			$result  = $ability->execute(
				array(
					'has_published_posts' => array( 'wp_public_pt' ),
					'fields'              => array( 'id' ),
					'per_page'            => 100,
				)
			);

			$this->assertIsArray( $result );
			$ids = wp_list_pluck( $result['users'], 'id' );
			$this->assertContains( $public_author_id, $ids );
			$this->assertNotContains( $this->public_author_id, $ids );
			$this->assertNotContains( $private_author_id, $ids );

			$result = $ability->execute(
				array(
					'has_published_posts' => array( 'wp_private_pt' ),
					'fields'              => array( 'id' ),
				)
			);

			$this->assertIsArray( $result );
			$this->assertSame( array(), $result['users'] );
			$this->assertSame( 0, $result['total'] );
			$this->assertSame( 0, $result['total_pages'] );
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

		$result = wp_get_ability( 'core/users' )->execute(
			array(
				'roles'    => array( 'author' ),
				'fields'   => array( 'id', 'roles' ),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $result );
		$this->assertContains( $this->public_author_id, wp_list_pluck( $result['users'], 'id' ) );
		foreach ( $result['users'] as $user ) {
			$this->assertContains( 'author', $user['roles'] );
		}
	}

	/**
	 * Role filtering requires list_users.
	 *
	 * @ticket 64146
	 */
	public function test_role_filter_requires_list_users(): void {
		wp_set_current_user( $this->subscriber_id );

		$result = wp_get_ability( 'core/users' )->execute( array( 'roles' => array( 'author' ) ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Restricted fields are omitted per user instead of failing the whole result.
	 *
	 * @ticket 64146
	 */
	public function test_restricted_requested_fields_are_omitted_per_user(): void {
		wp_set_current_user( $this->subscriber_id );

		$result = wp_get_ability( 'core/users' )->execute(
			array(
				'id'     => $this->public_author_id,
				'fields' => array( 'id', 'user_email', 'roles' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( array( 'id' ), array_keys( $result['users'][0] ) );
		$this->assertSame( $this->public_author_id, $result['users'][0]['id'] );
	}
}
