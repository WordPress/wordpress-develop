<?php

declare( strict_types=1 );

/**
 * Tests for the core/users-query ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Users
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreUsersAbility extends WP_UnitTestCase {

	/**
	 * The result of a query that matches no user, on any page.
	 *
	 * @since 7.2.0
	 * @var array<string, array<mixed>>
	 */
	const EMPTY_RESULT = array(
		'items'      => array(),
		'pagination' => array(
			'total'       => 0,
			'total_pages' => 0,
		),
	);

	/**
	 * Shared fixture IDs.
	 *
	 * @since 7.2.0
	 * @var array<string, int>
	 */
	private static $fixture_ids = array();

	/**
	 * Creates the shared fixtures and registers the core abilities.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$fixture_ids['administrator'] = $factory->user->create(
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

		self::$fixture_ids['editor'] = $factory->user->create(
			array(
				'role'          => 'editor',
				'user_login'    => 'core_users_ability_editor',
				'user_email'    => 'core-users-ability-editor@example.com',
				'user_nicename' => 'core-users-ability-editor',
			)
		);

		self::$fixture_ids['author'] = $factory->user->create(
			array(
				'role'          => 'author',
				'user_login'    => 'core_users_ability_author_current',
				'user_email'    => 'core-users-ability-author-current@example.com',
				'user_nicename' => 'core-users-ability-author-current',
			)
		);

		self::$fixture_ids['contributor'] = $factory->user->create(
			array(
				'role'          => 'contributor',
				'user_login'    => 'core_users_ability_contributor',
				'user_email'    => 'core-users-ability-contributor@example.com',
				'user_nicename' => 'core-users-ability-contributor',
			)
		);

		self::$fixture_ids['subscriber'] = $factory->user->create(
			array(
				'role'          => 'subscriber',
				'user_login'    => 'core_users_ability_subscriber',
				'user_email'    => 'core-users-ability-subscriber@example.com',
				'user_nicename' => 'core-users-ability-subscriber',
			)
		);

		self::$fixture_ids['public_author'] = $factory->user->create(
			array(
				'role'          => 'author',
				'user_login'    => 'core_users_ability_author',
				'user_email'    => 'core-users-ability-author@example.com',
				'user_nicename' => 'core-users-ability-author',
			)
		);

		$factory->post->create(
			array(
				'post_author' => self::$fixture_ids['public_author'],
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

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

		/*
		 * Restore the hooks right away instead of after the class. The first test of a run
		 * snapshots the hooks and every test resets them to that snapshot, so changes left
		 * here would leak into every later test whenever this class runs first.
		 */
		remove_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		remove_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );
	}

	/**
	 * Revokes the super admin fixture and cleans up registered abilities and categories.
	 *
	 * @since 7.2.0
	 */
	public static function wpTearDownAfterClass(): void {
		if ( is_multisite() ) {
			revoke_super_admin( self::$fixture_ids['administrator'] );
		}

		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}
	}

	/**
	 * Registers the core/users-query ability inside a faked init action.
	 *
	 * @since 7.2.0
	 */
	private function register_ability(): void {
		if ( wp_has_ability( 'core/users-query' ) ) {
			wp_unregister_ability( 'core/users-query' );
		}

		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		( new WP_Abilities_Users() )->register();
	}

	/**
	 * Registers the core/users-query ability and returns the callbacks it was registered with.
	 *
	 * The callbacks call private methods, so tests that skip input validation or the
	 * permission check capture them from the registration arguments.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, callable> The permission and execute callbacks, keyed by argument name.
	 */
	private function get_ability_callbacks(): array {
		$callbacks = array();

		add_filter(
			'wp_register_ability_args',
			static function ( array $args, string $name ) use ( &$callbacks ): array {
				if ( 'core/users-query' === $name ) {
					$callbacks = array(
						'permission_callback' => $args['permission_callback'],
						'execute_callback'    => $args['execute_callback'],
					);
				}

				return $args;
			},
			10,
			2
		);

		$this->register_ability();

		return $callbacks;
	}

	/**
	 * The ability is registered in the `user` category and flagged read-only.
	 *
	 * @ticket 64657
	 */
	public function test_core_users_ability_is_registered(): void {
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The users ability should be registered.' );
		$this->assertSame( 'user', $ability->get_category(), 'The users ability should use the user category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The users ability should be marked public.' );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ), 'The users ability should be exposed over REST.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'], 'The users ability should be marked read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The users ability should not be marked destructive.' );
	}

	/**
	 * The input schema takes every filter in one strict `where` object, and the output schema
	 * wraps every result in `items` and `pagination`.
	 *
	 * Neither schema has a combinator at its root, and neither does `where`.
	 *
	 * @ticket 64657
	 */
	public function test_core_users_input_schema_exposes_strict_filters(): void {
		$this->register_ability();

		$ability     = wp_get_ability( 'core/users-query' );
		$schema      = $ability->get_input_schema();
		$user_fields = array( 'id', 'name', 'description', 'url', 'link', 'slug', 'avatar_urls', 'username', 'email', 'first_name', 'last_name', 'nickname', 'locale', 'registered_date', 'roles' );

		$this->assertSame( 'object', $schema['type'], 'The users ability input schema should describe an object.' );
		$this->assertSame( array(), $schema['default'], 'The users ability input schema should default to an unfiltered query.' );
		$this->assertFalse( $schema['additionalProperties'], 'The users ability input schema should reject unknown root keys.' );
		foreach ( array( 'oneOf', 'anyOf', 'allOf' ) as $combinator ) {
			$this->assertArrayNotHasKey( $combinator, $schema, sprintf( 'The users ability input schema should not use %s at its root.', $combinator ) );
		}
		$this->assertSame( array( 'where', 'fields', 'pagination' ), array_keys( $schema['properties'] ), 'The users ability input schema should expose where, fields and pagination, in that order.' );

		$where = $schema['properties']['where'];

		$this->assertSame( 'object', $where['type'], 'The where option should describe an object.' );
		$this->assertFalse( $where['additionalProperties'], 'The where option should reject keys other than its filters.' );
		foreach ( array( 'oneOf', 'anyOf', 'allOf' ) as $combinator ) {
			$this->assertArrayNotHasKey( $combinator, $where, sprintf( 'The where option should not use %s.', $combinator ) );
		}
		foreach ( array( 'required', 'minProperties', 'maxProperties', 'title' ) as $keyword ) {
			$this->assertArrayNotHasKey( $keyword, $where, sprintf( 'The where option should not declare %s.', $keyword ) );
		}
		$this->assertSame(
			array( 'ids', 'slugs', 'usernames', 'roles', 'has_published_posts' ),
			array_keys( $where['properties'] ),
			'The where option should expose exactly the five filters, in that order.'
		);

		/*
		 * Each identifier list is capped at the maximum page size, so every user it names
		 * fits on one page.
		 */
		$per_page_maximum = $schema['properties']['pagination']['properties']['per_page']['maximum'];
		$item_types       = array(
			'ids'       => 'integer',
			'slugs'     => 'string',
			'usernames' => 'string',
		);

		foreach ( $item_types as $name => $item_type ) {
			$identifiers = $where['properties'][ $name ];

			$this->assertSame( 'array', $identifiers['type'], sprintf( 'The %s option should take a list.', $name ) );
			$this->assertTrue( $identifiers['uniqueItems'], sprintf( 'The %s option should reject duplicate items.', $name ) );
			$this->assertSame( 1, $identifiers['minItems'], sprintf( 'The %s option should require at least one item.', $name ) );
			$this->assertSame( 100, $identifiers['maxItems'], sprintf( 'The %s option should accept at most 100 items.', $name ) );
			$this->assertSame( $per_page_maximum, $identifiers['maxItems'], sprintf( 'The %s option should be capped at the maximum page size.', $name ) );
			$this->assertSame( $item_type, $identifiers['items']['type'], sprintf( 'The %1$s option should contain %2$s items.', $name, $item_type ) );
		}

		$this->assertSame( 1, $where['properties']['ids']['items']['minimum'], 'The ids option should contain positive user IDs.' );

		$roles = $where['properties']['roles'];

		$this->assertSame( 'array', $roles['type'], 'The roles option should take a list.' );
		$this->assertTrue( $roles['uniqueItems'], 'The roles option should reject duplicate role names.' );
		$this->assertSame( 1, $roles['minItems'], 'The roles option should require at least one role name.' );
		$this->assertSame( 'string', $roles['items']['type'], 'The roles option should contain role names.' );
		$this->assertSameSets( array_keys( wp_roles()->roles ), $roles['items']['enum'], 'The roles query enum should expose registered role names.' );

		$has_published_posts = $where['properties']['has_published_posts']['anyOf'];

		$this->assertCount( 2, $has_published_posts, 'The has_published_posts option should accept true or a list of post types.' );
		$this->assertArrayNotHasKey( 'title', $has_published_posts[0], 'The boolean has_published_posts branch should not have a title.' );
		$this->assertArrayNotHasKey( 'title', $has_published_posts[1], 'The post type has_published_posts branch should not have a title.' );
		$this->assertSame( 'boolean', $has_published_posts[0]['type'], 'The first has_published_posts branch should take a boolean.' );
		$this->assertSame( array( true ), $has_published_posts[0]['enum'], 'The first has_published_posts branch should accept only true.' );
		$this->assertSame( 'array', $has_published_posts[1]['type'], 'The second has_published_posts branch should take a list.' );
		$this->assertTrue( $has_published_posts[1]['uniqueItems'], 'The has_published_posts list should reject duplicate post types.' );
		$this->assertSame( 1, $has_published_posts[1]['minItems'], 'The has_published_posts list should require at least one post type.' );
		$this->assertSame( 'string', $has_published_posts[1]['items']['type'], 'The has_published_posts list should contain post type names.' );

		$post_type_names = $has_published_posts[1]['items']['enum'];
		$this->assertContains( 'post', $post_type_names, 'The has_published_posts enum should expose public post types.' );
		$this->assertNotContains( 'revision', $post_type_names, 'The has_published_posts enum should omit non-public post types.' );

		$fields = $schema['properties']['fields'];

		$this->assertSame( 'array', $fields['type'], 'The fields option should take a list.' );
		$this->assertTrue( $fields['uniqueItems'], 'The fields option should reject duplicate field names.' );
		$this->assertArrayNotHasKey( 'minItems', $fields, 'The fields option should accept an empty list, which selects the default fields.' );
		$this->assertSame( 'string', $fields['items']['type'], 'The fields option should contain field names.' );
		$this->assertSameSets( $user_fields, $fields['items']['enum'], 'The fields enum should expose every user field.' );
		$this->assertContains( 'roles', $fields['items']['enum'], 'The fields enum should expose the roles field.' );
		$this->assertContains( 'avatar_urls', $fields['items']['enum'], 'The fields enum should expose avatar_urls.' );

		$pagination = $schema['properties']['pagination'];

		$this->assertSame( 'object', $pagination['type'], 'The pagination option should describe an object.' );
		$this->assertFalse( $pagination['additionalProperties'], 'The pagination option should reject unknown keys.' );
		$this->assertArrayNotHasKey( 'required', $pagination, 'The pagination option should not require a page or a page size.' );
		$this->assertSameSets( array( 'page', 'per_page' ), array_keys( $pagination['properties'] ), 'The pagination option should expose exactly the page and the page size.' );
		$this->assertSame( 'integer', $pagination['properties']['page']['type'], 'The page should be an integer.' );
		$this->assertSame( 1, $pagination['properties']['page']['minimum'], 'The page should start at 1.' );
		$this->assertSame( 'integer', $pagination['properties']['per_page']['type'], 'The page size should be an integer.' );
		$this->assertSame( 1, $pagination['properties']['per_page']['minimum'], 'The page size should be at least 1.' );
		$this->assertSame( 100, $pagination['properties']['per_page']['maximum'], 'The page size should be capped at 100.' );

		$input_properties = array_merge( $schema['properties'], $where['properties'], $pagination['properties'] );
		foreach ( $input_properties as $name => $property ) {
			$this->assertArrayHasKey( 'description', $property, sprintf( 'The %s input property should have a description.', $name ) );
		}

		// The input takes only the root options, the where filters and the pagination values.
		$excluded_properties = array(
			'id',
			'include',
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
			$this->assertArrayNotHasKey( $excluded_property, $input_properties, sprintf( 'The input should not expose %s.', $excluded_property ) );
		}

		$output_schema = $ability->get_output_schema();

		$this->assertSame( 'object', $output_schema['type'], 'The users ability output schema should describe an object.' );
		$this->assertFalse( $output_schema['additionalProperties'], 'The output schema should not allow keys besides items and pagination.' );
		foreach ( array( 'oneOf', 'anyOf', 'allOf' ) as $combinator ) {
			$this->assertArrayNotHasKey( $combinator, $output_schema, sprintf( 'The output schema should not use %s at its root.', $combinator ) );
		}
		$this->assertSameSets( array( 'items', 'pagination' ), $output_schema['required'], 'Every response should require the wrapper fields.' );
		$this->assertSameSets( array( 'items', 'pagination' ), array_keys( $output_schema['properties'] ), 'The output schema should describe exactly the items and the pagination.' );
		$this->assertSame( 'array', $output_schema['properties']['items']['type'], 'The items output should be a list.' );

		$user_schema     = $output_schema['properties']['items']['items'];
		$user_properties = $user_schema['properties'];

		$this->assertSame( 'object', $user_schema['type'], 'Each item should describe a user object.' );
		$this->assertFalse( $user_schema['additionalProperties'], 'A user should not allow fields besides the user fields.' );
		$this->assertSame( array( 'id' ), $user_schema['required'], 'Only the always-returned id should be required in a user.' );
		$this->assertSameSets( $user_fields, array_keys( $user_properties ), 'A user should describe every user field.' );
		$this->assertSame( 'date-time', $user_properties['registered_date']['format'], 'The registered_date output schema should use date-time format.' );
		$this->assertSame( 'uri', $user_properties['link']['format'], 'The link output schema should use uri format.' );
		$this->assertSame( 'uri', $user_properties['avatar_urls']['additionalProperties']['format'], 'The avatar_urls output schema should use uri format.' );
		$this->assertArrayNotHasKey( 'format', $user_properties['url'], 'The url output schema must not declare a format, so the empty URL of a user without a website still validates.' );
		$this->assertSame( 'string', $user_properties['roles']['items']['type'], 'The roles output schema should describe role name strings.' );
		$this->assertArrayNotHasKey( 'enum', $user_properties['roles']['items'], 'The roles output schema must not pin an enum, so a role registered after the schema snapshot still validates.' );

		$output_pagination = $output_schema['properties']['pagination'];

		$this->assertSame( 'object', $output_pagination['type'], 'The pagination output should describe an object.' );
		$this->assertFalse( $output_pagination['additionalProperties'], 'The pagination output should not allow keys besides the totals.' );
		$this->assertSameSets( array( 'total', 'total_pages' ), $output_pagination['required'], 'The pagination output should require both totals.' );
		$this->assertSameSets( array( 'total', 'total_pages' ), array_keys( $output_pagination['properties'] ), 'The pagination output should describe exactly the totals.' );
		$this->assertSame( 'integer', $output_pagination['properties']['total']['type'], 'The total should be an integer.' );
		$this->assertSame( 'integer', $output_pagination['properties']['total_pages']['type'], 'The total_pages value should be an integer.' );
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
		$this->register_ability();

		$ability       = wp_get_ability( 'core/users-query' );
		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();

		$this->assertContains( 'avatar_urls', $input_schema['properties']['fields']['items']['enum'], 'The fields enum should keep declaring avatar_urls when avatars are disabled.' );
		$this->assertArrayHasKey( 'avatar_urls', $output_schema['properties']['items']['items']['properties'], 'The output schema should keep declaring avatar_urls when avatars are disabled.' );

		wp_set_current_user( self::$fixture_ids['public_author'] );
		$result = $ability->execute( array( 'where' => array( 'ids' => array( self::$fixture_ids['public_author'] ) ) ) );

		$this->assertIsArray( $result, 'The current user should still be readable when avatars are disabled.' );
		$this->assertArrayNotHasKey( 'avatar_urls', $result['items'][0], 'The ability result should omit avatar_urls when avatars are disabled.' );

		/*
		 * Enabling the option after registration takes effect immediately; the
		 * registration-time schema must not reject the field.
		 */
		update_option( 'show_avatars', 1 );
		$result = $ability->execute( array( 'where' => array( 'ids' => array( self::$fixture_ids['public_author'] ) ) ) );

		$this->assertIsArray( $result, 'Default-fields lookups should succeed after avatars are enabled post-registration.' );
		$this->assertArrayHasKey( 'avatar_urls', $result['items'][0], 'The ability result should include avatar_urls once avatars are enabled.' );
	}

	/**
	 * Omitted fields return a lean default shape.
	 *
	 * @ticket 64657
	 */
	public function test_omitted_fields_return_lean_defaults(): void {
		wp_set_current_user( self::$fixture_ids['public_author'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute( array( 'where' => array( 'ids' => array( self::$fixture_ids['public_author'] ) ) ) );

		$this->assertIsArray( $result, 'A current-user lookup should return an array.' );
		$this->assertSameSets(
			array( 'id', 'name', 'link', 'slug', 'avatar_urls' ),
			array_keys( $result['items'][0] ),
			'Omitted fields should return the lean default field set.'
		);
		$this->assertArrayNotHasKey( 'email', $result['items'][0], 'Default fields should not include sensitive user fields.' );
		$this->assertArrayNotHasKey( 'description', $result['items'][0], 'Default fields should omit less common read-context fields.' );
	}

	/**
	 * An empty fields list returns the lean default shape, like an omitted one.
	 *
	 * @ticket 64657
	 */
	public function test_empty_fields_return_lean_defaults(): void {
		wp_set_current_user( self::$fixture_ids['public_author'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
				'fields' => array(),
			)
		);

		$this->assertIsArray( $result, 'An empty fields list should be accepted.' );
		$this->assertSameSets(
			array( 'id', 'name', 'link', 'slug', 'avatar_urls' ),
			array_keys( $result['items'][0] ),
			'An empty fields list should return the lean default field set.'
		);
	}

	/**
	 * Logged-out users cannot run the ability.
	 *
	 * @ticket 64657
	 */
	public function test_core_users_requires_logged_in_user(): void {
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute( array() );

		$this->assertWPError( $result, 'Logged-out users should not be allowed to execute the users ability.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Logged-out users should receive an invalid permissions error.' );
	}

	/**
	 * A current user the query returns receives their own sensitive fields.
	 *
	 * The public author is returned to themselves, since they have a published post. Without
	 * permission to list users they still cannot filter by username, and `email` is not a
	 * filter at all.
	 *
	 * @ticket 64657
	 */
	public function test_visible_current_user_reads_their_own_sensitive_fields(): void {
		wp_set_current_user( self::$fixture_ids['public_author'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
				'fields' => array( 'id', 'email', 'username', 'roles' ),
			)
		);

		$this->assertIsArray( $result, 'A user should be able to read themselves by ID.' );
		$this->assertSameSets( array( 'id', 'email', 'username', 'roles' ), array_keys( $result['items'][0] ), 'The current user should receive only the requested fields.' );
		$this->assertSame( self::$fixture_ids['public_author'], $result['items'][0]['id'], 'The ID lookup should return the current user.' );
		$this->assertSame( 'core-users-ability-author@example.com', $result['items'][0]['email'], 'The current user should receive their own email.' );
		$this->assertSame( 'core_users_ability_author', $result['items'][0]['username'], 'The current user should receive their own username.' );
		$this->assertContains( 'author', $result['items'][0]['roles'], 'The current user should receive their own roles.' );

		$result = $ability->execute( array( 'where' => array( 'email' => 'core-users-ability-author@example.com' ) ) );
		$this->assertWPError( $result, 'A user should not be able to read themselves by email.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'An email filter for the current user should fail schema validation.' );

		$result = $ability->execute( array( 'where' => array( 'usernames' => array( 'core_users_ability_author' ) ) ) );
		$this->assertWPError( $result, 'A user who cannot list users should not be able to read themselves by username.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'A username filter should require permission to list users, even for the current user.' );

		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
				'fields' => array( 'id', 'registered_date' ),
			)
		);
		$this->assertIsArray( $result, 'A user should be able to request their registration date.' );
		$this->assertSame(
			gmdate( 'c', strtotime( get_userdata( self::$fixture_ids['public_author'] )->user_registered ) ),
			$result['items'][0]['registered_date'],
			'The registration date should be formatted as an ISO 8601 date-time string.'
		);
	}

	/**
	 * Public-author users can be read by ID or slug by logged-in users.
	 *
	 * @ticket 64657
	 */
	public function test_public_author_can_be_read_by_id_and_slug(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
				'fields' => array( 'id', 'slug', 'email' ),
			)
		);

		$this->assertIsArray( $result, 'A logged-in user should be able to read a public author by ID.' );
		$this->assertSame( self::$fixture_ids['public_author'], $result['items'][0]['id'], 'The ID lookup should return the public author.' );
		$this->assertSame( 'core-users-ability-author', $result['items'][0]['slug'], 'The public author slug should be returned.' );
		$this->assertArrayNotHasKey( 'email', $result['items'][0], 'Public-author access should not expose another user email.' );

		$result = $ability->execute( array( 'where' => array( 'slugs' => array( 'core-users-ability-author' ) ) ) );

		$this->assertIsArray( $result, 'A logged-in user should be able to read a public author by slug.' );
		$this->assertSame( self::$fixture_ids['public_author'], $result['items'][0]['id'], 'The slug lookup should return the public author.' );
	}

	/**
	 * An author whose posts are all private is not returned to callers who cannot list users.
	 *
	 * Only published posts make a user visible to such a caller, as WP_User_Query's
	 * has_published_posts matches published posts only. That holds for an ID and for a slug,
	 * even for an editor, who can read the private posts.
	 *
	 * @ticket 64657
	 */
	public function test_private_only_author_is_not_returned_without_list_users(): void {
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
		$this->register_ability();

		$this->assertFalse( current_user_can( 'list_users' ), 'An editor should not be able to list users, so only published posts make an author visible.' );
		$this->assertFalse( current_user_can( 'edit_user', $author_id ), 'An editor should not be able to edit the author.' );
		$this->assertTrue( current_user_can( 'read_private_posts' ), 'An editor should be able to read private posts.' );

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute(
			array(
				'where'  => array( 'slugs' => array( 'core-users-ability-private-author' ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The slug query from an editor should succeed.' );
		$this->assertSame( array(), $result['items'], 'An editor should not resolve an author whose posts are all private.' );

		$result = $ability->execute(
			array(
				'where'      => array( 'ids' => array( $author_id ) ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'The ID query from an editor should succeed.' );
		$this->assertSame( array(), $result['items'], 'An ID query from an editor should omit an author whose posts are all private.' );

		// A caller who cannot read the private posts does not find the author either.
		wp_set_current_user( self::$fixture_ids['subscriber'] );

		$result = $ability->execute(
			array(
				'where'  => array( 'slugs' => array( 'core-users-ability-private-author' ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( self::EMPTY_RESULT, $result, 'A subscriber should not resolve an author whose posts are all private.' );

		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => array( $author_id ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( self::EMPTY_RESULT, $result, 'A subscriber should not find an author whose posts are all private by ID.' );
	}

	/**
	 * A caller who can assign posts to other users gets no wider access to the users of the site.
	 *
	 * An editor can make any user of the site the author of a post, but cannot list users, so
	 * they only see public authors, like any other caller without that permission. A user whose
	 * only post is pending review is not returned for an ID or a slug, filtering by username is
	 * refused, and `email` is not a filter.
	 *
	 * @ticket 64657
	 */
	public function test_caller_who_can_assign_authors_gets_no_wider_access(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_login'    => 'core_users_ability_pending_author',
				'user_email'    => 'core-users-ability-pending-author@example.com',
				'user_nicename' => 'core-users-ability-pending-author',
				'display_name'  => 'Pending Author',
				'description'   => 'Writes posts that wait for review.',
				'user_url'      => 'https://example.com/pending-author',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'pending',
			)
		);

		wp_set_current_user( self::$fixture_ids['editor'] );
		$this->register_ability();

		$this->assertFalse( current_user_can( 'list_users' ), 'An editor should not be able to list users.' );
		$this->assertFalse( current_user_can( 'edit_user', $user_id ), 'An editor should not be able to edit the user.' );
		$this->assertTrue( current_user_can( 'edit_others_posts' ), 'An editor should be able to assign posts to other users.' );

		$ability = wp_get_ability( 'core/users-query' );
		$fields  = array( 'id', 'name', 'slug', 'link', 'description', 'url', 'username', 'email', 'roles' );

		$by_slug = $ability->execute(
			array(
				'where'  => array( 'slugs' => array( 'core-users-ability-pending-author' ) ),
				'fields' => $fields,
			)
		);

		$this->assertIsArray( $by_slug, 'A slug query for a user without public posts should not fail for an editor.' );
		$this->assertSame( self::EMPTY_RESULT, $by_slug, 'An editor should not resolve a user of the site without public posts by slug.' );

		$by_id = $ability->execute(
			array(
				'where'  => array( 'ids' => array( $user_id ) ),
				'fields' => $fields,
			)
		);

		$this->assertSame( $by_slug, $by_id, 'An ID query should return the same empty result as the slug query.' );

		$defaults = $ability->execute( array( 'where' => array( 'ids' => array( $user_id ) ) ) );

		$this->assertIsArray( $defaults, 'A default-fields lookup should succeed.' );
		$this->assertSame( array(), $defaults['items'], 'A default-fields query should not return the user either.' );

		$by_email = $ability->execute( array( 'where' => array( 'email' => 'core-users-ability-pending-author@example.com' ) ) );
		$this->assertWPError( $by_email, 'An email filter should fail for an editor.' );
		$this->assertSame( 'ability_invalid_input', $by_email->get_error_code(), 'An email filter from an editor should fail schema validation.' );

		$by_username = $ability->execute( array( 'where' => array( 'usernames' => array( 'core_users_ability_pending_author' ) ) ) );
		$this->assertWPError( $by_username, 'A username filter should still require permission to list users.' );
		$this->assertSame( 'ability_invalid_permissions', $by_username->get_error_code(), 'A username filter from an editor should be refused.' );

		wp_set_current_user( self::$fixture_ids['author'] );

		$this->assertFalse( current_user_can( 'edit_others_posts' ), 'An author should not be able to assign posts to other users.' );

		$result = $ability->execute( array( 'where' => array( 'slugs' => array( 'core-users-ability-pending-author' ) ) ) );
		$this->assertSame( self::EMPTY_RESULT, $result, 'A caller who cannot assign posts to other users should not resolve a user without public posts.' );
	}

	/**
	 * Filtering by username requires permission to list users across roles, and no role can
	 * filter by email.
	 *
	 * @ticket 64657
	 *
	 * @dataProvider data_roles_for_username_filter_permissions
	 *
	 * @param string $role           Current user's role.
	 * @param bool   $can_list_users Whether the role can list users, and so filter by username.
	 */
	public function test_roles_have_expected_username_filter_permissions( string $role, bool $can_list_users ): void {
		wp_set_current_user( self::$fixture_ids[ $role ] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute( array( 'where' => array( 'email' => 'core-users-ability-author@example.com' ) ) );
		$this->assertWPError( $result, sprintf( 'The %s role should not be able to resolve another user by email.', $role ) );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), sprintf( 'An email filter from the %s role should fail schema validation.', $role ) );

		$result = $ability->execute( array( 'where' => array( 'usernames' => array( 'core_users_ability_author' ) ) ) );
		if ( $can_list_users ) {
			$this->assertIsArray( $result, sprintf( 'The %s role should be able to resolve another user by username.', $role ) );
			$this->assertSame( self::$fixture_ids['public_author'], $result['items'][0]['id'], sprintf( 'The username lookup should return the public author for the %s role.', $role ) );
			return;
		}

		$this->assertWPError( $result, sprintf( 'The %s role should not be able to resolve another user by username.', $role ) );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), sprintf( 'Username lookup denial for the %s role should use the invalid permissions error.', $role ) );
	}

	/**
	 * Data provider for role-based username filter checks.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function data_roles_for_username_filter_permissions(): array {
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
	 *
	 * @dataProvider data_roles_without_list_users
	 *
	 * @param string $role Current user's role.
	 */
	public function test_empty_collection_mode_restricts_users_without_list_users_to_public_authors( string $role ): void {
		wp_set_current_user( self::$fixture_ids[ $role ] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute( array() );

		$this->assertIsArray( $result, sprintf( 'Collection mode should return an array for the %s role.', $role ) );

		$ids = wp_list_pluck( $result['items'], 'id' );

		$this->assertContains( self::$fixture_ids['public_author'], $ids, sprintf( 'Collection mode should include public authors for the %s role.', $role ) );
		$this->assertNotContains( self::$fixture_ids['administrator'], $ids, sprintf( 'Collection mode should omit non-author administrators for the %s role.', $role ) );
		$this->assertNotContains( self::$fixture_ids['subscriber'], $ids, sprintf( 'Collection mode should omit subscribers for the %s role.', $role ) );
		$this->assertNotContains( self::$fixture_ids[ $role ], $ids, sprintf( 'Collection mode should omit the current user without published posts for the %s role.', $role ) );
		$this->assertIsInt( $result['pagination']['total'], sprintf( 'Collection mode should include an integer total for the %s role.', $role ) );
		$this->assertIsInt( $result['pagination']['total_pages'], sprintf( 'Collection mode should include an integer total_pages value for the %s role.', $role ) );
	}

	/**
	 * Data provider for the roles that cannot list users.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_roles_without_list_users(): array {
		return array(
			'editor'      => array( 'editor' ),
			'author'      => array( 'author' ),
			'contributor' => array( 'contributor' ),
			'subscriber'  => array( 'subscriber' ),
		);
	}

	/**
	 * Calling the ability without input queries the collection with the default fields.
	 *
	 * The input schema defaults to an empty array, so the callbacks receive no `where`,
	 * `fields` or `pagination`.
	 *
	 * @ticket 64657
	 */
	public function test_execute_without_input_queries_the_collection(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute();

		$this->assertIsArray( $result, 'Calling the ability without input should succeed.' );
		$this->assertSameSets( array( 'items', 'pagination' ), array_keys( $result ), 'Calling the ability without input should return a collection.' );
		$this->assertNotEmpty( $result['items'], 'The collection should contain the users the administrator can list.' );
		$this->assertSameSets(
			array( 'id', 'name', 'link', 'slug', 'avatar_urls' ),
			array_keys( $result['items'][0] ),
			'Each user should have the default fields.'
		);
	}

	/**
	 * `where.ids` limits results to the requested users.
	 *
	 * @ticket 64657
	 */
	public function test_ids_filter_limits_results(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'      => array( 'ids' => array( self::$fixture_ids['public_author'], self::$fixture_ids['subscriber'] ) ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'An ID list query should return an array.' );
		$this->assertEqualSets(
			array( self::$fixture_ids['public_author'], self::$fixture_ids['subscriber'] ),
			wp_list_pluck( $result['items'], 'id' ),
			'Included user queries should return exactly the readable included users.'
		);
		$this->assertSame( 2, $result['pagination']['total'], 'Included user queries should report the matching included total.' );
	}

	/**
	 * `where.ids` returns every requested user when `pagination.per_page` is omitted.
	 *
	 * Without this the default page size silently truncates a batch load: a caller asking
	 * for a known set of IDs would receive only the first `per_page` of them.
	 *
	 * @ticket 64657
	 */
	public function test_ids_filter_returns_every_requested_user_without_per_page(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		// More than DEFAULT_PER_PAGE (10) so truncation would be visible.
		$ids = self::factory()->user->create_many( 15 );

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'ids' => $ids ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'An ID list query without per_page should return an array.' );
		$this->assertEqualSets( $ids, wp_list_pluck( $result['items'], 'id' ), 'Every requested user ID should be returned on a single page.' );
		$this->assertSame( 15, $result['pagination']['total'], 'The total should cover every requested user.' );
		$this->assertSame( 1, $result['pagination']['total_pages'], 'Included users should fit on a single page by default.' );
	}

	/**
	 * `where.slugs` and `where.usernames` return every named user when `pagination.per_page`
	 * is omitted, like `where.ids`.
	 *
	 * @ticket 64657
	 */
	public function test_slugs_and_usernames_return_every_named_user_without_per_page(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );
		$ids     = array();
		$filters = array(
			'slugs'     => array(),
			'usernames' => array(),
		);

		// More than the default page size of 10, so truncation would be visible.
		for ( $i = 1; $i <= 12; $i++ ) {
			$ids[] = self::factory()->user->create(
				array(
					'user_login'    => 'core_users_ability_batch_' . $i,
					'user_nicename' => 'core-users-ability-batch-' . $i,
				)
			);

			$filters['slugs'][]     = 'core-users-ability-batch-' . $i;
			$filters['usernames'][] = 'core_users_ability_batch_' . $i;
		}

		foreach ( $filters as $filter => $identifiers ) {
			$result = $ability->execute(
				array(
					'where'  => array( $filter => $identifiers ),
					'fields' => array( 'id' ),
				)
			);

			$this->assertIsArray( $result, sprintf( 'A query by %s without per_page should return an array.', $filter ) );
			$this->assertSameSets( $ids, wp_list_pluck( $result['items'], 'id' ), sprintf( 'Every user named by %s should be returned on a single page.', $filter ) );
			$this->assertSame( 12, $result['pagination']['total'], sprintf( 'The total should cover every user named by %s.', $filter ) );
			$this->assertSame( 1, $result['pagination']['total_pages'], sprintf( 'Users named by %s should fit on a single page by default.', $filter ) );
		}
	}

	/**
	 * An explicit `pagination.per_page` still paginates an ID list request.
	 *
	 * @ticket 64657
	 */
	public function test_ids_filter_honors_an_explicit_per_page(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ids = self::factory()->user->create_many( 5 );

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'      => array( 'ids' => $ids ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 2 ),
			)
		);

		$this->assertIsArray( $result, 'An ID list query with an explicit per_page should return an array.' );
		$this->assertCount( 2, $result['items'], 'An explicit per_page should paginate included users.' );
		$this->assertSame( 5, $result['pagination']['total'], 'The total should still cover every requested user.' );
		$this->assertSame( 3, $result['pagination']['total_pages'], 'Page counts should follow the explicit per_page.' );
	}

	/**
	 * The ID list is capped at the maximum page size.
	 *
	 * @ticket 64657
	 */
	public function test_ids_filter_is_capped_at_the_maximum_page_size(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability   = wp_get_ability( 'core/users-query' );
		$schema    = $ability->get_input_schema();
		$max_items = $schema['properties']['where']['properties']['ids']['maxItems'];

		$this->assertSame( $schema['properties']['pagination']['properties']['per_page']['maximum'], $max_items, 'The ID list should be capped at the maximum page size.' );

		$result = $ability->execute(
			array(
				'where' => array( 'ids' => range( 1, $max_items + 1 ) ),
			)
		);

		$this->assertWPError( $result, 'An ID list beyond the cap should be rejected as invalid input.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The cap should be enforced by schema validation.' );
	}

	/**
	 * Paging past the last page reports an error rather than an empty collection.
	 *
	 * @ticket 64657
	 */
	public function test_page_past_the_last_one_is_rejected(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'      => array( 'ids' => array( self::$fixture_ids['editor'], self::$fixture_ids['subscriber'] ) ),
				'fields'     => array( 'id' ),
				'pagination' => array(
					'page'     => 3,
					'per_page' => 1,
				),
			)
		);

		$this->assertWPError( $result, 'A page past the last one should fail rather than return an empty list.' );
		$this->assertSame( 'users_invalid_page_number', $result->get_error_code(), 'A page past the last one should report a dedicated error code.' );
		$this->assertSame( 404, $result->get_error_data()['status'], 'A page past the last one should be reported as not found.' );
	}

	/**
	 * A genuinely empty result set beyond the first page reports zero totals, not an error.
	 *
	 * @ticket 64657
	 */
	public function test_empty_result_set_beyond_the_first_page_is_not_an_error(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );
		$this->register_ability();

		// A subscriber only sees public authors in a collection, and the administrator has no posts.
		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'      => array( 'ids' => array( self::$fixture_ids['administrator'] ) ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'page' => 2 ),
			)
		);

		$this->assertSame( self::EMPTY_RESULT, $result, 'An empty result set should report zero totals on any page.' );
	}

	/**
	 * A query for one ID returns its user on the first page only.
	 *
	 * The result is paginated like any other, so a later page is past the last one. A user the
	 * caller cannot see makes an empty result instead, which is not an error on any page.
	 *
	 * @ticket 64657
	 */
	public function test_single_id_returns_the_user_on_the_first_page_only(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute(
			array(
				'where'      => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
				'fields'     => array( 'id' ),
				'pagination' => array(
					'page'     => 1,
					'per_page' => 10,
				),
			)
		);

		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => self::$fixture_ids['public_author'] ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$result,
			'The first page of a lookup should hold the requested user.'
		);

		$result = $ability->execute(
			array(
				'where'      => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'page' => 2 ),
			)
		);

		$this->assertWPError( $result, 'The second page of a lookup should fail rather than return an empty list.' );
		$this->assertSame( 'users_invalid_page_number', $result->get_error_code(), 'The second page of a lookup should report a page past the last one.' );
		$this->assertSame( 404, $result->get_error_data()['status'], 'The second page of a lookup should be reported as not found.' );

		$result = $ability->execute(
			array(
				'where'      => array( 'ids' => array( self::$fixture_ids['administrator'] ) ),
				'pagination' => array( 'page' => 2 ),
			)
		);

		$this->assertIsArray( $result, 'The second page of a query for an unreadable user should not fail.' );
		$this->assertSame( self::EMPTY_RESULT, $result, 'An unreadable user should make an empty result rather than a missing page.' );
	}

	/**
	 * Results keep the default ordering, not the order of the ID list.
	 *
	 * The ID list filters the results but does not order them, like `include` in the REST
	 * users controller, so results stay in WP_User_Query's default order, by login.
	 *
	 * @ticket 64657
	 */
	public function test_ids_order_does_not_control_the_result_order(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		/*
		 * WP_User_Query orders by user_login ascending by default, and
		 * 'core_users_ability_admin' sorts before 'core_users_ability_subscriber'.
		 */
		$expected = array( self::$fixture_ids['administrator'], self::$fixture_ids['subscriber'] );

		foreach ( array( $expected, array_reverse( $expected ) ) as $ids ) {
			$result = $ability->execute(
				array(
					'where'  => array( 'ids' => $ids ),
					'fields' => array( 'id' ),
				)
			);

			$this->assertIsArray( $result, 'An included user query should succeed.' );
			$this->assertSame(
				$expected,
				wp_list_pluck( $result['items'], 'id' ),
				'Included users should be ordered by login, whichever order the ID list uses.'
			);
		}
	}

	/**
	 * `where.slugs` and `where.usernames` return every named user, ordered by login whatever
	 * the order of the list.
	 *
	 * @ticket 64657
	 */
	public function test_slugs_and_usernames_return_every_named_user_ordered_by_login(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		/*
		 * WP_User_Query orders by user_login ascending by default, and
		 * 'core_users_ability_admin' sorts before 'core_users_ability_subscriber'.
		 */
		$expected = array( self::$fixture_ids['administrator'], self::$fixture_ids['subscriber'] );
		$filters  = array(
			'slugs'     => array( 'core-users-ability-admin', 'core-users-ability-subscriber' ),
			'usernames' => array( 'core_users_ability_admin', 'core_users_ability_subscriber' ),
		);

		foreach ( $filters as $filter => $identifiers ) {
			$lists = array(
				'login'   => $identifiers,
				'reverse' => array_reverse( $identifiers ),
			);

			foreach ( $lists as $order => $list ) {
				$result = $ability->execute(
					array(
						'where'  => array( $filter => $list ),
						'fields' => array( 'id' ),
					)
				);

				$this->assertIsArray( $result, sprintf( 'A query by %1$s in %2$s order should succeed.', $filter, $order ) );
				$this->assertSame(
					$expected,
					wp_list_pluck( $result['items'], 'id' ),
					sprintf( 'Users named by %1$s in %2$s order should be returned by login.', $filter, $order )
				);
			}
		}
	}

	/**
	 * The ID list still respects row-level read permissions.
	 *
	 * @ticket 64657
	 */
	public function test_ids_filter_respects_read_permissions(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'      => array( 'ids' => array( self::$fixture_ids['administrator'], self::$fixture_ids['public_author'] ) ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'An ID list query from a subscriber should return an array.' );
		$this->assertSame(
			array( self::$fixture_ids['public_author'] ),
			wp_list_pluck( $result['items'], 'id' ),
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
			'not_viewable_pt',
			array(
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		$this->assertContains( 'not_viewable_pt', get_post_types( array( 'public' => true ) ), 'The post type should be in the public set.' );
		$this->assertFalse( is_post_type_viewable( 'not_viewable_pt' ), 'The post type should not be publicly viewable.' );

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
				'post_type'   => 'not_viewable_pt',
			)
		);

		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$schema = $ability->get_input_schema();
		$this->assertNotContains(
			'not_viewable_pt',
			$schema['properties']['where']['properties']['has_published_posts']['anyOf'][1]['items']['enum'],
			'The post type should not be offered in the has_published_posts enum.'
		);

		$result = $ability->execute(
			array(
				'where'      => array( 'has_published_posts' => true ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'A boolean published-posts filter should execute.' );
		$this->assertNotContains(
			$author_id,
			wp_list_pluck( $result['items'], 'id' ),
			'A boolean published-posts filter should not match an author whose only post type is not publicly viewable.'
		);

		// The same author stays hidden from a caller who only sees public authors.
		wp_set_current_user( self::$fixture_ids['subscriber'] );

		$result = $ability->execute(
			array(
				'where'  => array( 'slugs' => array( 'core-users-ability-hidden-author' ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( self::EMPTY_RESULT, $result, 'A non-viewable post type should not make its author publicly readable.' );
	}

	/**
	 * A caller with no published posts is not returned, even for their own ID.
	 *
	 * A caller without permission to list users only sees public authors, themselves
	 * included. They read their own account through the `core/get-user-info` ability
	 * instead, as the REST `/users/me` endpoint does.
	 *
	 * @ticket 64657
	 */
	public function test_collection_mode_excludes_self_without_published_posts(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$collection = $ability->execute(
			array(
				'where'      => array( 'ids' => array( self::$fixture_ids['subscriber'] ) ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $collection, 'A query for the caller\'s own ID should return an array.' );
		$this->assertSame( array(), $collection['items'], 'Collection mode should exclude a caller with no published posts, even when they include their own ID.' );
		$this->assertSame( 0, $collection['pagination']['total'], 'The excluded self should not be counted in the collection total.' );

		$single = $ability->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['subscriber'] ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $single, 'A single-user self lookup should return an array.' );
		$this->assertSame( self::EMPTY_RESULT, $single, 'Without per_page, the caller should not be returned for their own ID either.' );
	}

	/**
	 * The current user is not returned on a site they are not a member of.
	 *
	 * A super admin can act on any site of the network without being a member of it, but the
	 * query only returns users of the current site, even for the caller's own ID. Other users
	 * who are not members of the site are not returned either.
	 *
	 * @ticket 64657
	 *
	 * @group ms-required
	 */
	public function test_current_user_is_not_returned_on_a_site_they_are_not_a_member_of(): void {
		$super_admin_id = self::factory()->user->create();
		grant_super_admin( $super_admin_id );
		remove_user_from_blog( $super_admin_id, get_current_blog_id() );

		$non_member_id = self::factory()->user->create();
		remove_user_from_blog( $non_member_id, get_current_blog_id() );

		$this->assertFalse( is_user_member_of_blog( $super_admin_id ), 'The super admin should not be a member of the current site.' );

		wp_set_current_user( $super_admin_id );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => array( $super_admin_id ) ),
				'fields' => array( 'id', 'username' ),
			)
		);

		$this->assertIsArray( $result, 'A super admin should be able to query their own ID on a site they are not a member of.' );
		$this->assertSame( array(), $result['items'], 'The query should not return the current user on a site they are not a member of.' );
		$this->assertSame( 0, $result['pagination']['total'], 'The current user should not be counted on a site they are not a member of.' );

		$result = $ability->execute( array( 'where' => array( 'ids' => array( $non_member_id ) ) ) );

		$this->assertSame( self::EMPTY_RESULT, $result, 'Another user who is not a member of the current site should stay unreadable.' );
	}

	/**
	 * A public author who is not a member of the site is not returned.
	 *
	 * A super admin can publish posts on a site without being a member of it. The query only
	 * returns users of the current site, so those posts do not make them visible by ID or
	 * slug, and a caller who can list every user does not find them by username either.
	 *
	 * @ticket 64657
	 *
	 * @group ms-required
	 */
	public function test_public_author_who_is_not_a_site_member_is_not_returned(): void {
		$super_admin_id = self::factory()->user->create(
			array(
				'user_login'    => 'core_users_ability_network_author',
				'user_email'    => 'core-users-ability-network-author@example.com',
				'user_nicename' => 'core-users-ability-network-author',
				'description'   => 'Publishes posts across the network.',
			)
		);
		grant_super_admin( $super_admin_id );
		remove_user_from_blog( $super_admin_id, get_current_blog_id() );
		self::factory()->post->create(
			array(
				'post_author' => $super_admin_id,
				'post_status' => 'publish',
			)
		);

		$this->assertFalse( is_user_member_of_blog( $super_admin_id ), 'The publishing super admin should not be a member of the current site.' );

		wp_set_current_user( self::$fixture_ids['subscriber'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute(
			array(
				'where'  => array( 'slugs' => array( 'core-users-ability-network-author' ) ),
				'fields' => array( 'id', 'description', 'email' ),
			)
		);

		$this->assertSame( self::EMPTY_RESULT, $result, 'A slug query should not return a public author who is not a member of the site.' );

		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => array( $super_admin_id ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( self::EMPTY_RESULT, $result, 'An ID query should not find a public author who is not a member of the site.' );

		// The administrator fixture is a super admin, who can list and edit every user of the network.
		wp_set_current_user( self::$fixture_ids['administrator'] );

		$result = $ability->execute( array( 'where' => array( 'usernames' => array( 'core_users_ability_network_author' ) ) ) );

		$this->assertSame( self::EMPTY_RESULT, $result, 'A username query should not find a user who is not a member of the site.' );
	}

	/**
	 * Filters combine with AND, so only the users that every given filter matches are returned.
	 *
	 * @ticket 64657
	 */
	public function test_identifier_filters_combine_with_and(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$by_ids_and_slugs = $ability->execute(
			array(
				'where'  => array(
					'ids'   => array( self::$fixture_ids['subscriber'], self::$fixture_ids['public_author'] ),
					'slugs' => array( 'core-users-ability-subscriber', 'core-users-ability-editor' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $by_ids_and_slugs, 'IDs plus slugs should pass validation.' );
		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => self::$fixture_ids['subscriber'] ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$by_ids_and_slugs,
			'IDs plus slugs should return only the user both lists name.'
		);

		$by_ids_and_usernames = $ability->execute(
			array(
				'where'  => array(
					'ids'       => array( self::$fixture_ids['subscriber'] ),
					'usernames' => array( 'core_users_ability_editor' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $by_ids_and_usernames, 'IDs plus usernames should pass validation.' );
		$this->assertSame( self::EMPTY_RESULT, $by_ids_and_usernames, 'IDs plus usernames with no user in common should return an empty list.' );
	}

	/**
	 * Two identifier filters that name the same user return that user.
	 *
	 * `where` takes any combination of its filters, but `email` is not one of them, so an ID
	 * list next to an email still fails schema validation.
	 *
	 * @ticket 64657
	 */
	public function test_where_with_two_identifier_filters_naming_one_user_returns_that_user(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$by_id_and_email = $ability->execute(
			array(
				'where' => array(
					'ids'   => array( self::$fixture_ids['subscriber'] ),
					'email' => 'core-users-ability-subscriber@example.com',
				),
			)
		);

		$this->assertWPError( $by_id_and_email, 'An ID plus an email should fail validation.' );
		$this->assertSame( 'ability_invalid_input', $by_id_and_email->get_error_code(), 'An ID plus an email should return an input error.' );

		$by_username_and_slug = $ability->execute(
			array(
				'where'  => array(
					'usernames' => array( 'core_users_ability_subscriber' ),
					'slugs'     => array( 'core-users-ability-subscriber' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $by_username_and_slug, 'A username plus a slug should pass validation.' );
		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => self::$fixture_ids['subscriber'] ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$by_username_and_slug,
			'A username plus a slug that name the same user should return that user.'
		);
	}

	/**
	 * A `where` key other than the five filters fails schema validation.
	 *
	 * Identifiers are only taken as the `ids`, `slugs` and `usernames` lists, so `id`,
	 * `slug`, `username` and `include` are rejected, and so is `email`, which is not a filter.
	 *
	 * @ticket 64657
	 *
	 * @dataProvider data_unsupported_where_keys
	 *
	 * @param string $key   The `where` key.
	 * @param mixed  $value The value given for the key.
	 */
	public function test_unsupported_where_key_fails_schema_validation( string $key, $value ): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute( array( 'where' => array( $key => $value ) ) );

		$this->assertWPError( $result, sprintf( 'The %s where key should fail the request.', $key ) );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), sprintf( 'The %s where key should fail schema validation.', $key ) );
	}

	/**
	 * Data provider for `where` keys outside the five filters.
	 *
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	public static function data_unsupported_where_keys(): array {
		return array(
			'id'       => array( 'id', 1 ),
			'email'    => array( 'email', 'core-users-ability-admin@example.com' ),
			'username' => array( 'username', 'core_users_ability_admin' ),
			'slug'     => array( 'slug', 'core-users-ability-admin' ),
			'include'  => array( 'include', array( 1 ) ),
		);
	}

	/**
	 * Filters and pagination values at the root fail schema validation.
	 *
	 * The root only accepts `where`, `fields` and `pagination`. A filter or a page given
	 * outside its object is rejected, rather than ignored in favor of an unfiltered
	 * collection query.
	 *
	 * @ticket 64657
	 */
	public function test_filters_and_pagination_at_the_root_fail_schema_validation(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$by_ids = $ability->execute( array( 'ids' => array( self::$fixture_ids['subscriber'] ) ) );

		$this->assertWPError( $by_ids, 'An ID list at the root should not return a collection.' );
		$this->assertSame( 'ability_invalid_input', $by_ids->get_error_code(), 'An ID list at the root should return an input error.' );

		$by_slugs = $ability->execute( array( 'slugs' => array( 'core-users-ability-subscriber' ) ) );

		$this->assertWPError( $by_slugs, 'A slug list at the root should not return a collection.' );
		$this->assertSame( 'ability_invalid_input', $by_slugs->get_error_code(), 'A slug list at the root should return an input error.' );

		$second_page = $ability->execute( array( 'page' => 2 ) );

		$this->assertWPError( $second_page, 'A page at the root should not return a collection.' );
		$this->assertSame( 'ability_invalid_input', $second_page->get_error_code(), 'A page at the root should return an input error.' );
	}

	/**
	 * Collection mode for users without list_users honors public post type filters.
	 *
	 * @ticket 64657
	 */
	public function test_collection_mode_for_users_without_list_users_uses_public_post_types(): void {
		register_post_type(
			'public_pt',
			array(
				'public'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_type(
			'private_pt',
			array(
				'public' => false,
			)
		);

		$public_author_id  = self::factory()->user->create( array( 'role' => 'author' ) );
		$private_author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		self::factory()->post->create(
			array(
				'post_author' => $public_author_id,
				'post_status' => 'publish',
				'post_type'   => 'public_pt',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $private_author_id,
				'post_status' => 'publish',
				'post_type'   => 'private_pt',
			)
		);

		$this->assertFalse( get_post_type_object( 'public_pt' )->show_in_rest, 'The public fixture post type should remain hidden from REST.' );

		wp_set_current_user( self::$fixture_ids['subscriber'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );
		$schema  = $ability->get_input_schema();
		$enum    = $schema['properties']['where']['properties']['has_published_posts']['anyOf'][1]['items']['enum'];

		$this->assertContains( 'public_pt', $enum, 'The has_published_posts enum should include public post types even when hidden from REST.' );
		$this->assertNotContains( 'private_pt', $enum, 'The has_published_posts enum should omit private post types.' );

		$result = $ability->execute(
			array(
				'where'      => array( 'has_published_posts' => array( 'public_pt' ) ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'A public post type author query should return an array.' );
		$ids = wp_list_pluck( $result['items'], 'id' );
		$this->assertContains( $public_author_id, $ids, 'The query should include authors of the requested public post type.' );
		$this->assertNotContains( self::$fixture_ids['public_author'], $ids, 'The query should exclude authors without posts in the requested public post type.' );
		$this->assertNotContains( $private_author_id, $ids, 'The query should exclude authors of private post types.' );

		$result = $ability->execute(
			array(
				'where'  => array( 'has_published_posts' => array( 'private_pt' ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertWPError( $result, 'Private post type filters should fail schema validation.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Private post type filters should use the invalid input error.' );
	}

	/**
	 * A published-posts filter left with no publicly viewable post type matches no users.
	 *
	 * The input schema is a registration-time snapshot, so it still accepts a post type
	 * unregistered since. Filtering by it must return an empty collection rather than
	 * drop the filter and return every user.
	 *
	 * @ticket 64657
	 */
	public function test_published_posts_filter_without_a_viewable_post_type_matches_no_users(): void {
		register_post_type( 'unregistered_pt', array( 'public' => true ) );

		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		unregister_post_type( 'unregistered_pt' );

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'has_published_posts' => array( 'unregistered_pt' ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( self::EMPTY_RESULT, $result, 'A filter by a post type that is no longer viewable should match no users.' );
	}

	/**
	 * Administrators can query by role and receive roles.
	 *
	 * @ticket 64657
	 */
	public function test_admin_can_query_by_role_and_receive_roles(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'      => array( 'roles' => array( 'author' ) ),
				'fields'     => array( 'id', 'roles' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'An administrator role query should return an array.' );
		$this->assertContains( self::$fixture_ids['public_author'], wp_list_pluck( $result['items'], 'id' ), 'The author role query should include the public author.' );
		foreach ( $result['items'] as $user ) {
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
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
				'fields' => array( 'id', 'email', 'roles' ),
			)
		);

		$this->assertIsArray( $result, sprintf( 'The %s role should be able to execute a public-author lookup.', $role ) );
		$this->assertSame( self::$fixture_ids['public_author'], $result['items'][0]['id'], sprintf( 'The %s role should receive the requested public author.', $role ) );
		$this->assertSame( $can_view_sensitive, array_key_exists( 'email', $result['items'][0] ), sprintf( 'The %s role email visibility should match expectations.', $role ) );
		$this->assertSame( $can_view_roles, array_key_exists( 'roles', $result['items'][0] ), sprintf( 'The %s role roles visibility should match expectations.', $role ) );

		if ( $can_view_sensitive ) {
			$this->assertSame( 'core-users-ability-author@example.com', $result['items'][0]['email'], sprintf( 'The %s role should receive the public author email when allowed.', $role ) );
		}

		if ( ! $can_view_roles ) {
			return;
		}

		$this->assertContains( 'author', $result['items'][0]['roles'], sprintf( 'The %s role should receive the public author role when allowed.', $role ) );
	}

	/**
	 * Data provider for role-based field visibility checks.
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
			$this->register_ability();

			$result = wp_get_ability( 'core/users-query' )->execute(
				array(
					'where'  => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
					'fields' => array( 'id', 'roles' ),
				)
			);

			$this->assertIsArray( $result, 'A list-only caller should still resolve a public author.' );
			$this->assertArrayNotHasKey( 'roles', $result['items'][0], 'A caller who cannot edit a user must not receive that user\'s roles.' );
		} finally {
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
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		// Register the role only after the ability (and its output schema) exist.
		add_role( 'core_users_ability_late_role', 'Late Role', array( 'read' => true ) );
		$user_id = self::factory()->user->create( array( 'role' => 'core_users_ability_late_role' ) );

		try {
			$result = wp_get_ability( 'core/users-query' )->execute(
				array(
					'where'  => array( 'ids' => array( $user_id ) ),
					'fields' => array( 'id', 'roles' ),
				)
			);

			$this->assertIsArray( $result, 'Reading a user with a role added after registration should not fail output validation.' );
			$this->assertContains( 'core_users_ability_late_role', $result['items'][0]['roles'], 'The late-registered role should be returned.' );
		} finally {
			remove_role( 'core_users_ability_late_role' );
		}
	}

	/**
	 * Role filtering requires list_users.
	 *
	 * @ticket 64657
	 */
	public function test_role_filter_requires_list_users(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute( array( 'where' => array( 'roles' => array( 'author' ) ) ) );

		$this->assertWPError( $result, 'A subscriber should not be able to filter users by role.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Role filter denial should use the invalid permissions error.' );
	}

	/**
	 * Restricted fields are omitted per user instead of failing the whole result.
	 *
	 * @ticket 64657
	 */
	public function test_restricted_requested_fields_are_omitted_per_user(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
				'fields' => array( 'id', 'email', 'roles' ),
			)
		);

		$this->assertIsArray( $result, 'A public-author lookup should return an array.' );
		$this->assertSame( array( 'id' ), array_keys( $result['items'][0] ), 'Restricted requested fields should be omitted instead of failing the request.' );
		$this->assertSame( self::$fixture_ids['public_author'], $result['items'][0]['id'], 'The public-author lookup should still return unrestricted fields.' );
	}

	/**
	 * A fully redacted field request still returns the user's ID.
	 *
	 * The `id` field is always included, matching the REST users controller
	 * where `id` is present in every context. This also guarantees each user
	 * serializes as a JSON object (`{}`-shaped, never `[]`), honoring the
	 * declared type of the items.
	 *
	 * @ticket 64657
	 */
	public function test_fully_redacted_field_request_still_returns_id(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
				'fields' => array( 'email', 'roles' ),
			)
		);

		$this->assertIsArray( $result, 'A fully redacted field request should still succeed.' );
		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => self::$fixture_ids['public_author'] ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$result,
			'A fully redacted field request should return only the user ID.'
		);
		$this->assertSame(
			sprintf( '{"items":[{"id":%d}],"pagination":{"total":1,"total_pages":1}}', self::$fixture_ids['public_author'] ),
			wp_json_encode( $result ),
			'A fully redacted field request should serialize the user as a JSON object.'
		);

		$collection = $ability->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['public_author'] ) ),
				'fields' => array( 'email' ),
			)
		);

		$this->assertIsArray( $collection, 'A collection with redacted fields should return the wrapper.' );
		$this->assertSame(
			array( 'id' => self::$fixture_ids['public_author'] ),
			$collection['items'][0],
			'Redacted collection entries should still contain the user ID.'
		);
	}

	/**
	 * Schema-valid object input behaves like its array form.
	 *
	 * Schema validation accepts `stdClass` input, so it must not be discarded and
	 * silently turned into an unrestricted collection query.
	 *
	 * @ticket 64657
	 */
	public function test_object_input_behaves_like_array_input(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			(object) array(
				'where'  => array( 'ids' => array( self::$fixture_ids['subscriber'] ) ),
				'fields' => array( 'id', 'email' ),
			)
		);

		$this->assertIsArray( $result, 'Object input should execute the ID query.' );
		$this->assertSame( self::$fixture_ids['subscriber'], $result['items'][0]['id'], 'Object input must resolve the requested user, not fall back to an unfiltered query.' );
		$this->assertSame( 'core-users-ability-subscriber@example.com', $result['items'][0]['email'], 'Object input should honor the requested fields.' );
	}

	/**
	 * Object `where` and `pagination` values behave like their array forms.
	 *
	 * Schema validation accepts a `stdClass` for every object in the input, so neither
	 * value may be discarded, whether the input itself is an array or an object.
	 *
	 * @ticket 64657
	 */
	public function test_object_where_and_pagination_behave_like_arrays(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$lookup = array(
			'where'  => (object) array( 'ids' => array( self::$fixture_ids['subscriber'] ) ),
			'fields' => array( 'id', 'email' ),
		);

		$expected_lookup = array(
			'items'      => array(
				array(
					'id'    => self::$fixture_ids['subscriber'],
					'email' => 'core-users-ability-subscriber@example.com',
				),
			),
			'pagination' => array(
				'total'       => 1,
				'total_pages' => 1,
			),
		);

		$this->assertSame( $expected_lookup, $ability->execute( $lookup ), 'An object where in array input should run the lookup.' );
		$this->assertSame( $expected_lookup, $ability->execute( (object) $lookup ), 'An object where in object input should run the lookup.' );

		/*
		 * 'core_users_ability_admin' sorts before 'core_users_ability_subscriber', so the
		 * second page of one user holds the subscriber.
		 */
		$collection = array(
			'where'      => (object) array( 'ids' => array( self::$fixture_ids['administrator'], self::$fixture_ids['subscriber'] ) ),
			'fields'     => array( 'id' ),
			'pagination' => (object) array(
				'page'     => 2,
				'per_page' => 1,
			),
		);

		$expected_collection = array(
			'items'      => array(
				array( 'id' => self::$fixture_ids['subscriber'] ),
			),
			'pagination' => array(
				'total'       => 2,
				'total_pages' => 2,
			),
		);

		$this->assertSame( $expected_collection, $ability->execute( $collection ), 'Object where and pagination values in array input should filter and page the collection.' );
		$this->assertSame( $expected_collection, $ability->execute( (object) $collection ), 'Object where and pagination values in object input should filter and page the collection.' );
	}

	/**
	 * String input from callers that bypass the REST run controller is normalized.
	 *
	 * Schema validation accepts 'true' for a boolean, a CSV string for an array, and
	 * a numeric string for an integer, but only the REST run controller converts
	 * them to the schema types. A direct WP_Ability::execute() call passes them as
	 * given, so the normalizers must accept the string forms instead of silently
	 * dropping the filters they carry.
	 *
	 * @ticket 64657
	 */
	public function test_string_input_from_direct_calls_is_normalized(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute(
			array(
				'where'      => array( 'has_published_posts' => 'true' ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'A string boolean published-posts filter should execute.' );
		$ids = wp_list_pluck( $result['items'], 'id' );
		$this->assertContains( self::$fixture_ids['public_author'], $ids, 'The published-posts filter should include public authors.' );
		$this->assertNotContains( self::$fixture_ids['subscriber'], $ids, 'A string "true" must enable the published-posts filter rather than being silently dropped.' );

		$result = $ability->execute(
			array(
				'where'      => array( 'roles' => 'editor' ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'A CSV roles filter should execute.' );
		$ids = wp_list_pluck( $result['items'], 'id' );
		$this->assertContains( self::$fixture_ids['editor'], $ids, 'The roles filter should include editors.' );
		$this->assertNotContains( self::$fixture_ids['subscriber'], $ids, 'A CSV roles value must filter by role rather than being silently dropped.' );

		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => (string) self::$fixture_ids['subscriber'] ),
				'fields' => 'id,email',
			)
		);

		$this->assertIsArray( $result, 'A CSV ID list should execute.' );
		$this->assertSame( array( self::$fixture_ids['subscriber'] ), wp_list_pluck( $result['items'], 'id' ), 'A string ID list must limit the query to the listed IDs.' );
		$this->assertSame( 'core-users-ability-subscriber@example.com', $result['items'][0]['email'], 'A CSV fields value must select the requested fields.' );

		/*
		 * IDs that are distinct as strings but equal as integers ('7' vs '07')
		 * pass schema validation; they must still collapse to one filtered ID.
		 */
		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => array( (string) self::$fixture_ids['subscriber'], '0' . self::$fixture_ids['subscriber'] ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'A string-duplicate ID list should execute.' );
		$this->assertSame( array( self::$fixture_ids['subscriber'] ), wp_list_pluck( $result['items'], 'id' ), 'String-duplicate IDs must be deduplicated, not silently drop the ID list.' );
		$this->assertSame( 1, $result['pagination']['total'], 'String-duplicate IDs should count as one included user.' );

		// Schema validation also accepts numeric strings for integers.
		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => array( (string) self::$fixture_ids['subscriber'] ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'A numeric-string ID lookup should execute.' );
		$this->assertSame( self::$fixture_ids['subscriber'], $result['items'][0]['id'], 'A numeric-string ID in a list must resolve the user.' );

		$result = $ability->execute(
			array(
				'fields'     => array( 'id' ),
				'pagination' => array(
					'page'     => '2',
					'per_page' => '1',
				),
			)
		);

		$this->assertIsArray( $result, 'Numeric-string pagination should execute.' );
		$this->assertCount( 1, $result['items'], 'A numeric-string per_page must limit the page size.' );
		$this->assertGreaterThan( 1, $result['pagination']['total_pages'], 'A numeric-string per_page must be reflected in total_pages.' );
	}

	/**
	 * A float ID without a fractional part resolves the user.
	 *
	 * Schema validation accepts such a float as an integer, and only the REST run
	 * controller converts input to the schema types.
	 *
	 * @ticket 64657
	 */
	public function test_float_id_resolves_the_user(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'ids' => array( (float) self::$fixture_ids['subscriber'] ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => self::$fixture_ids['subscriber'] ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$result,
			'A float ID that validation accepts should resolve the user.'
		);
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
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['administrator'] ) ),
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
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['administrator'] ) ),
				'fields' => array( 'id', 'avatar_urls' ),
			)
		);

		$this->assertIsArray( $result, 'Requesting avatar_urls while avatars are disabled should still succeed.' );
		$this->assertSame( array( 'id' ), array_keys( $result['items'][0] ), 'The avatar_urls field should be omitted while avatars are disabled.' );
	}

	/**
	 * Creates a user whose stored email is empty.
	 *
	 * WordPress allows this: wp_insert_user() rejects an empty login and an empty
	 * nicename, but never an empty email. Imports and single sign-on plugins can
	 * leave such rows behind.
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

		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'ids' => array( $user_id ) ),
				'fields' => array( 'id', 'email' ),
			)
		);

		$this->assertIsArray( $result, 'A user with an empty stored email should still be readable.' );
		$this->assertArrayHasKey( 'email', $result['items'][0], 'A viewable email field should be present even when there is no address.' );
		$this->assertNull( $result['items'][0]['email'], 'An empty stored email should be reported as null.' );
	}

	/**
	 * A stored email that is_email() rejects is reported as null.
	 *
	 * WordPress runs sanitize_email() when saving, which repairs `a@@b.c` to `a@b.c`,
	 * one character too short for is_email(). The declared `email` format only holds
	 * because such values become null.
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

		$stored_email = get_userdata( $user_id )->user_email;

		$this->assertSame( 'a@b.c', $stored_email, 'WordPress should repair the invalid address.' );
		$this->assertFalse( is_email( $stored_email ), 'The stored address should still be rejected by is_email().' );

		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'ids' => array( $user_id ) ),
				'fields' => array( 'id', 'email' ),
			)
		);

		$this->assertIsArray( $result, 'A user with an unusable stored email should still be readable.' );
		$this->assertNull( $result['items'][0]['email'], 'An email that is_email() rejects should be reported as null.' );
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

		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'fields'     => array( 'id', 'email' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'One user with an empty stored email should not fail the whole collection.' );

		$ids = wp_list_pluck( $result['items'], 'id' );
		$this->assertContains( self::$fixture_ids['administrator'], $ids, 'Users with valid emails should still be returned.' );
		$this->assertContains( $user_id, $ids, 'The user with an empty stored email should still be returned.' );
	}

	/**
	 * A zero registration date is reported as null instead of failing the output.
	 *
	 * The zero date is the `user_registered` column default, so rows inserted without a
	 * date carry it. It formats with a negative year, which the `date-time` output format
	 * rejects, so passing it through would fail the lookup, and a whole collection page.
	 *
	 * @ticket 64657
	 */
	public function test_zero_registered_date_is_reported_as_null(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'            => 'subscriber',
				'user_registered' => '0000-00-00 00:00:00',
			)
		);

		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute(
			array(
				'where'  => array( 'ids' => array( $user_id ) ),
				'fields' => array( 'id', 'registered_date' ),
			)
		);

		$this->assertIsArray( $result, 'A user with a zero registration date should still be readable.' );
		$this->assertArrayHasKey( 'registered_date', $result['items'][0], 'A viewable registration date should be present even when the stored date is unusable.' );
		$this->assertNull( $result['items'][0]['registered_date'], 'A zero registration date should be reported as null.' );

		$collection = $ability->execute(
			array(
				'where'  => array( 'ids' => array( $user_id, self::$fixture_ids['administrator'] ) ),
				'fields' => array( 'id', 'registered_date' ),
			)
		);

		$this->assertIsArray( $collection, 'One user with a zero registration date should not fail the whole collection.' );

		$registered_dates = wp_list_pluck( $collection['items'], 'registered_date', 'id' );
		$this->assertNull( $registered_dates[ $user_id ], 'The zero registration date should be reported as null in the collection.' );
		$this->assertIsString( $registered_dates[ self::$fixture_ids['administrator'] ], 'A valid registration date in the same collection should still be reported.' );
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
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		add_filter(
			'pre_get_avatar_data',
			static function ( $args ) {
				$args['url'] = false;

				return $args;
			}
		);

		$result = wp_get_ability( 'core/users-query' )->execute( array( 'where' => array( 'ids' => array( self::$fixture_ids['administrator'] ) ) ) );

		$this->assertIsArray( $result, 'A suppressed avatar URL should not fail a default-fields lookup.' );
		$this->assertSame( self::$fixture_ids['administrator'], $result['items'][0]['id'], 'The lookup should still resolve the requested user.' );
		$this->assertSame(
			array(
				24 => null,
				48 => null,
				96 => null,
			),
			$result['items'][0]['avatar_urls'],
			'Every avatar size should be reported as null when no URL can be resolved.'
		);
	}

	/**
	 * Avatar sizes that resolve to a URL survive when other sizes do not.
	 *
	 * @ticket 64657
	 */
	public function test_partially_suppressed_avatar_urls_keep_the_resolved_sizes(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		add_filter(
			'pre_get_avatar_data',
			static function ( $args ) {
				if ( 24 === $args['size'] ) {
					$args['url'] = false;
				}

				return $args;
			}
		);

		$result = wp_get_ability( 'core/users-query' )->execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['administrator'] ) ),
				'fields' => array( 'id', 'avatar_urls' ),
			)
		);

		$this->assertIsArray( $result, 'A partially suppressed avatar set should not fail the lookup.' );
		$this->assertSameSets( array( 24, 48, 96 ), array_keys( $result['items'][0]['avatar_urls'] ), 'Every avatar size should still be reported.' );
		$this->assertNull( $result['items'][0]['avatar_urls'][24], 'A size with no resolvable URL should be null.' );
		$this->assertIsString( $result['items'][0]['avatar_urls'][48], 'The 48px size resolves, so it should keep its URL.' );
		$this->assertIsString( $result['items'][0]['avatar_urls'][96], 'The 96px size resolves, so it should keep its URL.' );
	}

	/**
	 * An ID that matches no user the caller can see returns an empty list, whether the user
	 * does not exist or is hidden from the caller.
	 *
	 * @ticket 64657
	 */
	public function test_missing_or_unreadable_user_id_returns_an_empty_list(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );
		$this->register_ability();

		$ability = wp_get_ability( 'core/users-query' );

		$result = $ability->execute( array( 'where' => array( 'ids' => array( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) ) ) );

		$this->assertIsArray( $result, 'A missing user ID should not fail the query.' );
		$this->assertSame( self::EMPTY_RESULT, $result, 'A missing user ID should return an empty list with zero totals.' );

		wp_set_current_user( self::$fixture_ids['subscriber'] );

		$result = $ability->execute( array( 'where' => array( 'ids' => array( self::$fixture_ids['administrator'] ) ) ) );

		$this->assertSame( self::EMPTY_RESULT, $result, 'The ID of a user the caller cannot see should return an empty list like a missing one.' );
	}

	/**
	 * Called directly, the execute callback returns an empty list for an ID that matches no
	 * user the current user can see.
	 *
	 * A user the current user cannot see is reported like a missing one.
	 *
	 * @ticket 64657
	 */
	public function test_execute_callback_returns_an_empty_list_for_missing_or_unreadable_ids(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		$missing = $execute( array( 'where' => array( 'ids' => array( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) ) ) );

		$this->assertIsArray( $missing, 'A nonexistent user ID should not fail the query.' );
		$this->assertSame( self::EMPTY_RESULT, $missing, 'A nonexistent user ID should leave an empty list with zero totals.' );

		$unreadable = $execute( array( 'where' => array( 'ids' => array( self::$fixture_ids['administrator'] ) ) ) );

		$this->assertIsArray( $unreadable, 'A user the current user cannot read should not fail the query.' );
		$this->assertSame( $missing, $unreadable, 'A user the current user cannot read should be reported like a missing one.' );
	}

	/**
	 * The execute callback rejects filters it cannot honor, instead of dropping them and
	 * widening the query.
	 *
	 * Gated transports validate the input first, so these values never reach the callback
	 * through them. The checks are kept so that a direct call still fails closed, as the
	 * content query does with its filters.
	 *
	 * @ticket 64657
	 */
	public function test_execute_callback_rejects_filters_it_cannot_honor(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute( array( 'where' => array( 'ids' => array( 0 ) ) ) );
		$this->assertWPError( $result, 'An ID list with no valid IDs must not fall through to an unrestricted query.' );
		$this->assertSame( 'users_invalid_filter', $result->get_error_code(), 'An empty-after-parsing ID list should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where[ids]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the ID list to the error message.'
		);

		$result = $execute( array( 'where' => array( 'roles' => array( 5 ) ) ) );
		$this->assertWPError( $result, 'A roles filter with no role names must not fall through to an unfiltered query.' );
		$this->assertSame( 'users_invalid_filter', $result->get_error_code(), 'An empty-after-parsing roles filter should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where[roles]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the roles filter to the error message.'
		);

		$result = $execute( array( 'where' => array( 'has_published_posts' => false ) ) );
		$this->assertWPError( $result, 'A has_published_posts value that is neither true nor a list of post types must not be dropped.' );
		$this->assertSame( 'users_invalid_filter', $result->get_error_code(), 'An unhonorable has_published_posts filter should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where[has_published_posts]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the has_published_posts filter to the error message.'
		);
	}

	/**
	 * The execute callback rejects a `where` that is not an object, instead of running an
	 * unfiltered collection query.
	 *
	 * Gated transports validate the input first, so such a value never reaches the callback
	 * through them. The permission callback lets it through so that the execute callback can
	 * report it. An empty string still counts as an empty object, as in schema validation.
	 *
	 * @ticket 64657
	 */
	public function test_execute_callback_rejects_a_where_that_is_not_an_object(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );

		$callbacks = $this->get_ability_callbacks();

		foreach ( array( 'id=5', 5, true, null ) as $where ) {
			$input = array( 'where' => $where );
			$label = wp_json_encode( $where );

			$this->assertTrue( $callbacks['permission_callback']( $input ), sprintf( 'The permission callback should let the %s where through.', $label ) );

			$result = $callbacks['execute_callback']( $input );

			$this->assertWPError( $result, sprintf( 'The %s where must not fall through to an unfiltered query.', $label ) );
			$this->assertSame( 'users_invalid_filter', $result->get_error_code(), sprintf( 'The %s where should fail closed as an invalid filter.', $label ) );
			$this->assertSame(
				array( 'where' => $result->get_error_message() ),
				$result->get_error_data()['params'],
				sprintf( 'The error data should map the %s where to the error message.', $label )
			);
		}

		$this->assertSame(
			$callbacks['execute_callback'](
				array(
					'where'  => array(),
					'fields' => array( 'id' ),
				)
			),
			$callbacks['execute_callback'](
				array(
					'where'  => '',
					'fields' => array( 'id' ),
				)
			),
			'An empty string where should query the collection like an empty object.'
		);
	}

	/**
	 * The execute callback refuses a role filter to a caller who cannot list users, instead
	 * of dropping it and listing every public author.
	 *
	 * Gated transports never reach this branch, because the permission callback denies the
	 * same request first.
	 *
	 * @ticket 64657
	 */
	public function test_execute_callback_refuses_a_role_filter_without_list_users(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute( array( 'where' => array( 'roles' => array( 'author' ) ) ) );

		$this->assertWPError( $result, 'A subscriber must not filter users by role through a direct call.' );
		$this->assertSame( 'users_cannot_filter_by_role', $result->get_error_code(), 'The refusal should name the role filter.' );
		$this->assertSame( 403, $result->get_error_data()['status'], 'The refusal should be reported as forbidden.' );
	}

	/**
	 * The execute callback refuses a username filter to a caller who cannot list users,
	 * instead of dropping it and listing every public author.
	 *
	 * Gated transports never reach this branch, because the permission callback denies the
	 * same request first.
	 *
	 * @ticket 64657
	 */
	public function test_execute_callback_refuses_a_username_filter_without_list_users(): void {
		wp_set_current_user( self::$fixture_ids['subscriber'] );

		$callbacks = $this->get_ability_callbacks();
		$input     = array( 'where' => array( 'usernames' => array( 'core_users_ability_author' ) ) );

		$this->assertFalse( $callbacks['permission_callback']( $input ), 'The permission callback should deny a username filter without list_users.' );

		$result = $callbacks['execute_callback']( $input );

		$this->assertWPError( $result, 'A subscriber must not filter users by username through a direct call.' );
		$this->assertSame( 'users_cannot_filter_by_username', $result->get_error_code(), 'The refusal should name the username filter.' );
		$this->assertSame( 403, $result->get_error_data()['status'], 'The username filter refusal should be reported as forbidden.' );
	}

	/**
	 * A fractional ID does not resolve a user, instead of being truncated onto another one.
	 *
	 * Schema validation rejects a fraction, so this only matters for callers that skip it,
	 * such as a direct call to the permission or execute callback. The permission callback
	 * never looks users up, so it lets the request through, and the execute callback, left
	 * without a valid ID, fails closed.
	 *
	 * @ticket 64657
	 */
	public function test_fractional_id_does_not_resolve_a_user(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );

		$callbacks = $this->get_ability_callbacks();
		$input     = array( 'where' => array( 'ids' => array( self::$fixture_ids['subscriber'] + 0.5 ) ) );

		$this->assertTrue( $callbacks['permission_callback']( $input ), 'The permission callback should let a fractional ID through without looking it up.' );

		$result = $callbacks['execute_callback']( $input );
		$this->assertWPError( $result, 'The execute callback should not resolve a fractional ID.' );
		$this->assertSame( 'users_invalid_filter', $result->get_error_code(), 'A list holding only a fractional ID should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where[ids]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the fractional ID list to the error message.'
		);
	}

	/**
	 * The execute callback reads only whole numbers of at least 1 as user IDs.
	 *
	 * Schema validation rejects every other value, so this only matters for callers that skip
	 * it. Other values are dropped rather than truncated or made positive, and a list left
	 * without an ID fails closed instead of widening the query.
	 *
	 * @ticket 64657
	 */
	public function test_execute_callback_drops_ids_that_are_not_positive_whole_numbers(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute( array( 'where' => array( 'ids' => array( 0, -3, 5.5 ) ) ) );
		$this->assertWPError( $result, 'A list without a whole number of at least 1 must not fall through to an unrestricted query.' );
		$this->assertSame( 'users_invalid_filter', $result->get_error_code(), 'A list of unusable IDs should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where[ids]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the list of unusable IDs to the error message.'
		);

		$result = $execute( array( 'where' => array( 'ids' => array( -self::$fixture_ids['subscriber'] ) ) ) );
		$this->assertWPError( $result, 'A negative ID must not be read as the positive one.' );
		$this->assertSame( 'users_invalid_filter', $result->get_error_code(), 'A list holding only a negative ID should fail closed as an invalid filter.' );

		$expected = array(
			'items'      => array(
				array( 'id' => self::$fixture_ids['subscriber'] ),
			),
			'pagination' => array(
				'total'       => 1,
				'total_pages' => 1,
			),
		);

		$result = $execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['subscriber'], self::$fixture_ids['subscriber'] + 0.5 ) ),
				'fields' => array( 'id' ),
			)
		);
		$this->assertSame( $expected, $result, 'A fractional ID next to a valid one should be dropped, leaving the valid user.' );

		// Truncating the fraction would add the administrator.
		$result = $execute(
			array(
				'where'  => array( 'ids' => array( self::$fixture_ids['subscriber'], self::$fixture_ids['administrator'] + 0.5 ) ),
				'fields' => array( 'id' ),
			)
		);
		$this->assertSame( $expected, $result, 'A fractional ID should be dropped rather than truncated onto another user.' );
	}

	/**
	 * The execute callback rejects a slug or username list left without a string, instead
	 * of dropping it and widening the query.
	 *
	 * Schema validation rejects such a list, so this only matters for callers that skip it.
	 *
	 * @ticket 64657
	 */
	public function test_execute_callback_rejects_slug_and_username_lists_without_a_string(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		foreach ( array( 'slugs', 'usernames' ) as $filter ) {
			$result = $execute( array( 'where' => array( $filter => array( 5 ) ) ) );

			$this->assertWPError( $result, sprintf( 'A list of %s without a string must not fall through to an unfiltered query.', $filter ) );
			$this->assertSame( 'users_invalid_filter', $result->get_error_code(), sprintf( 'A list of %s left empty after parsing should fail closed as an invalid filter.', $filter ) );
			$this->assertSame(
				array( 'where[' . $filter . ']' => $result->get_error_message() ),
				$result->get_error_data()['params'],
				sprintf( 'The error data should map the list of %s to the error message.', $filter )
			);
		}
	}

	/**
	 * Pagination values that are not whole numbers fall back to the defaults, instead of
	 * being coerced to a page or page size the caller did not ask for.
	 *
	 * @ticket 64657
	 */
	public function test_execute_callback_defaults_pagination_it_cannot_parse(): void {
		wp_set_current_user( self::$fixture_ids['administrator'] );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'fields'     => array( 'id' ),
				'pagination' => array(
					'page'     => 'last',
					'per_page' => 1.5,
				),
			)
		);

		$this->assertIsArray( $result, 'The query should run with the default pagination.' );
		$this->assertCount( $result['pagination']['total'], $result['items'], 'The default page size should hold every fixture user on the first page.' );
		$this->assertSame( 1, $result['pagination']['total_pages'], 'The default page size should fit every fixture user on one page.' );
	}
}
