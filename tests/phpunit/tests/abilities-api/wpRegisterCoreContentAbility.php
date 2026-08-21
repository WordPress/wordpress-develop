<?php

declare( strict_types=1 );

/**
 * Tests for the core/read-content ability shipped with the Abilities API.
 *
 * @covers WP_Content_Abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreContentAbility extends WP_UnitTestCase {

	/**
	 * Shared user IDs keyed by role or fixture name.
	 *
	 * @since 7.1.0
	 *
	 * @var array<string, int>
	 */
	private static $user_ids = array();

	/**
	 * Shared post IDs keyed by fixture name.
	 *
	 * @since 7.1.0
	 *
	 * @var array<string, int>
	 */
	private static $post_ids = array();

	/**
	 * Creates shared users and posts for the content ability tests.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::$user_ids = array(
			'administrator'    => $factory->user->create( array( 'role' => 'administrator' ) ),
			'editor'           => $factory->user->create( array( 'role' => 'editor' ) ),
			'subscriber'       => $factory->user->create( array( 'role' => 'subscriber' ) ),
			'contributor'      => $factory->user->create( array( 'role' => 'contributor' ) ),
			'author'           => $factory->user->create( array( 'role' => 'author' ) ),
			'author_secondary' => $factory->user->create( array( 'role' => 'author' ) ),
		);

		self::$post_ids = array(
			'published'                  => $factory->post->create( array( 'post_status' => 'publish' ) ),
			'published_content'          => $factory->post->create(
				array(
					'post_title'   => 'Hello Content',
					'post_content' => 'Body here.',
					'post_status'  => 'publish',
				)
			),
			'subscriber_content'         => $factory->post->create(
				array(
					'post_title'   => 'Visible to subscribers',
					'post_content' => 'Rendered body for subscribers.',
					'post_status'  => 'publish',
				)
			),
			'readable_single'            => $factory->post->create(
				array(
					'post_title'   => 'Readable single',
					'post_content' => 'Readable single body.',
					'post_status'  => 'publish',
				)
			),
			'limited_role_content'       => $factory->post->create(
				array(
					'post_author'  => self::$user_ids['administrator'],
					'post_title'   => 'Readable title',
					'post_content' => 'Readable body for limited role.',
					'post_excerpt' => 'Readable excerpt.',
					'post_status'  => 'publish',
				)
			),
			'raw_content'                => $factory->post->create(
				array(
					'post_status'  => 'publish',
					'post_content' => 'Public body with raw block markup.',
				)
			),
			'password_protected_editor'  => $factory->post->create(
				array(
					'post_status'   => 'publish',
					'post_password' => 'secret',
					'post_content'  => 'Top secret body.',
				)
			),
			'password_protected_limited' => $factory->post->create(
				array(
					'post_author'   => self::$user_ids['administrator'],
					'post_status'   => 'publish',
					'post_password' => 'secret',
					'post_content'  => 'Hidden rendered body.',
				)
			),
		);
	}


	/**
	 * Sets up the content ability category for each test.
	 *
	 * @since 7.1.0
	 */
	public function setUp(): void {
		parent::setUp();

		if ( wp_has_ability( 'core/read-content' ) ) {
			wp_unregister_ability( 'core/read-content' );
		}

		$this->ensure_ability_category( 'content' );
	}

	/**
	 * Restores ability and post type state after each test.
	 *
	 * @since 7.1.0
	 */
	public function tearDown(): void {
		if ( wp_has_ability( 'core/read-content' ) ) {
			wp_unregister_ability( 'core/read-content' );
		}

		foreach ( array( 'post', 'page' ) as $post_type ) {
			$object = get_post_type_object( $post_type );
			if ( $object ) {
				$object->show_in_abilities = true;
			}
		}

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Ensures an ability category exists for an ability to attach to.
	 *
	 * @since 7.1.0
	 *
	 * @param string $slug The ability category slug.
	 */
	private function ensure_ability_category( string $slug ): void {
		if ( wp_has_ability_category( $slug ) ) {
			return;
		}

		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_categories_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		try {
			wp_register_ability_category(
				$slug,
				array(
					'label'       => ucfirst( $slug ),
					'description' => ucfirst( $slug ) . '.',
				)
			);
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * Registers the core/read-content ability inside a faked init action.
	 *
	 * @since 7.1.0
	 */
	private function register_ability(): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		try {
			( new WP_Content_Abilities() )->register();
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * Logs in as a user with the given role and returns the user ID.
	 *
	 * @param string $role The role to log in as.
	 * @return int The user ID.
	 */
	private function login_as( string $role ): int {
		$user_id = self::$user_ids[ $role ] ?? self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	/**
	 * Returns roles that can read public posts but cannot edit another user's post.
	 *
	 * @return array<string, array{role: string}> Role test cases.
	 */
	public function data_roles_without_edit_access_to_other_users_posts(): array {
		return array(
			'subscriber'  => array(
				'role' => 'subscriber',
			),
			'contributor' => array(
				'role' => 'contributor',
			),
			'author'      => array(
				'role' => 'author',
			),
		);
	}

	/**
	 * The ability is registered in the `content` category and flagged read-only.
	 *
	 * @since 7.1.0
	 */
	public function test_registers_core_read_content_ability(): void {
		$this->register_ability();

		$ability = wp_get_ability( 'core/read-content' );

		$this->assertNotNull( $ability, 'The core/read-content ability should be registered.' );
		$this->assertSame( 'core/read-content', $ability->get_name(), 'The registered ability should use the expected name.' );
		$this->assertSame( 'content', $ability->get_category(), 'The registered ability should use the content category.' );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ), 'The ability should be exposed in REST.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'], 'The ability should be marked read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The ability should be marked non-destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'The ability should be marked idempotent.' );
		$this->assertFalse( $annotations['open_world'], 'The ability should be marked closed-world; it only reads the local database.' );
	}

	/**
	 * The content ability is not registered when no post types are exposed to it.
	 *
	 * @since 7.1.0
	 */
	public function test_does_not_register_core_read_content_ability_without_exposed_post_types(): void {
		foreach ( array( 'post', 'page' ) as $post_type ) {
			$object = get_post_type_object( $post_type );
			$this->assertNotFalse( $object, "Precondition: the {$post_type} post type should exist." );

			$object->show_in_abilities = false;
		}

		$this->register_ability();

		$this->assertFalse( wp_has_ability( 'core/read-content' ), 'The content ability should not register without any exposed post types.' );
	}

	/**
	 * The input schema models mutually exclusive ID, slug, and query modes, each
	 * rejecting the other modes' properties and exposing only marked types.
	 *
	 * @since 7.1.0
	 */
	public function test_input_schema_models_mutually_exclusive_modes(): void {
		$this->register_ability();

		$schema = wp_get_ability( 'core/read-content' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'], 'The input schema should describe an object.' );
		$this->assertCount( 3, $schema['oneOf'], 'The input schema should expose exactly three modes.' );

		[ $by_id, $by_slug, $query ] = $schema['oneOf'];

		// All modes reject properties from the other modes.
		$this->assertSame( array( 'id' ), $by_id['required'], 'The by-ID mode should require an ID.' );
		$this->assertSame( array( 'post_type', 'slug' ), $by_slug['required'], 'The slug mode should require post type and slug.' );
		$this->assertSame( array( 'post_type' ), $query['required'], 'The query mode should require a post type.' );
		$this->assertFalse( $by_id['additionalProperties'], 'The by-ID mode should reject unrelated properties.' );
		$this->assertFalse( $by_slug['additionalProperties'], 'The slug mode should reject unrelated properties.' );
		$this->assertFalse( $query['additionalProperties'], 'The query mode should reject unrelated properties.' );

		// Query-only filters live only in the query mode, not the single-post modes.
		$this->assertArrayHasKey( 'include', $query['properties'], 'The query mode should support included post IDs.' );
		$this->assertArrayHasKey( 'per_page', $query['properties'], 'The query mode should support pagination.' );
		$this->assertArrayNotHasKey( 'per_page', $by_id['properties'], 'The by-ID mode should not accept query-only pagination.' );
		$this->assertArrayNotHasKey( 'include', $by_slug['properties'], 'The slug mode should not accept query-only included IDs.' );
		$this->assertArrayNotHasKey( 'slug', $query['properties'], 'The query mode should not accept slug; slug is a single-post mode.' );

		// Exposed post types appear in all modes that accept `post_type`.
		$this->assertContains( 'post', $query['properties']['post_type']['enum'], 'The query mode should include exposed posts.' );
		$this->assertContains( 'page', $by_id['properties']['post_type']['enum'], 'The by-ID guard should include exposed pages.' );
		$this->assertContains( 'page', $by_slug['properties']['post_type']['enum'], 'The slug mode should include exposed pages.' );

		$this->assertSame( 1, $query['properties']['include']['minItems'], 'The include option should require at least one post ID.' );
		$this->assertTrue( $query['properties']['include']['uniqueItems'], 'The include option should reject duplicate post IDs.' );
		$this->assertSame( 'integer', $query['properties']['include']['items']['type'], 'The include option should contain post IDs.' );
		$this->assertSame( 1, $query['properties']['include']['items']['minimum'], 'The include option should contain positive post IDs.' );

		$fields_enum = $query['properties']['fields']['items']['enum'];
		$this->assertContains( 'post_type', $fields_enum, 'The fields enum should expose the post type as post_type.' );
		$this->assertNotContains( 'type', $fields_enum, 'The fields enum should not expose the post type as type.' );
		$this->assertContains( 'content_raw', $fields_enum, 'The fields enum should include raw content.' );
		$this->assertContains( 'content_rendered', $fields_enum, 'The fields enum should include rendered content.' );
		$this->assertContains( 'title_raw', $fields_enum, 'The fields enum should include raw titles.' );
		$this->assertContains( 'title_rendered', $fields_enum, 'The fields enum should include rendered titles.' );
	}

	/**
	 * Branch-local defaults are omitted so the schema can compile in the client-side
	 * Abilities API validator. Runtime defaults are still applied by the ability.
	 *
	 * @since 7.1.0
	 */
	public function test_input_schema_omits_oneof_branch_defaults(): void {
		$this->register_ability();

		$schema = wp_get_ability( 'core/read-content' )->get_input_schema();
		$query  = $schema['oneOf'][2];

		$this->assertArrayNotHasKey( 'default', $query['properties']['status'], 'Status should rely on runtime defaults, not schema defaults.' );
		$this->assertArrayNotHasKey( 'default', $query['properties']['page'], 'Page should rely on runtime defaults, not schema defaults.' );
		$this->assertArrayNotHasKey( 'default', $query['properties']['per_page'], 'Per-page should rely on runtime defaults, not schema defaults.' );
	}

	/**
	 * Query-mode filters cannot be combined with a by-ID lookup: passing `per_page` alongside
	 * `id` is rejected outright rather than silently ignored.
	 *
	 * @since 7.1.0
	 */
	public function test_id_mode_rejects_query_only_params(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'       => 1,
				'per_page' => 10,
			)
		);

		$this->assertWPError( $result, 'Combining by-ID mode with query-only params should fail validation.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Invalid mode combinations should return an input error.' );
	}

	/**
	 * `post_type` is accepted alongside `id` as a guard: the by-ID mode still resolves the post.
	 *
	 * @since 7.1.0
	 */
	public function test_id_mode_accepts_post_type_guard(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published'];

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'        => $post_id,
				'post_type' => 'post',
			)
		);

		$this->assertIsArray( $result, 'A matching post type guard should allow the by-ID lookup.' );
		$this->assertSame( $post_id, $result['id'], 'The guarded by-ID lookup should return the requested post directly.' );
		$this->assertArrayNotHasKey( 'posts', $result, 'The guarded by-ID lookup should not return the query wrapper.' );
	}

	/**
	 * The output schema describes single-post and query response shapes.
	 *
	 * @since 7.1.0
	 */
	public function test_output_schema_describes_single_post_and_query_responses(): void {
		$this->register_ability();

		$ability      = wp_get_ability( 'core/read-content' );
		$input_schema = $ability->get_input_schema();
		$schema       = $ability->get_output_schema();
		$post_schema  = $schema['oneOf'][0];
		$query_schema = $schema['oneOf'][1];

		$this->assertSame( 'object', $schema['type'], 'The output schema should describe object responses.' );
		$this->assertCount( 2, $schema['oneOf'], 'The output schema should describe single-post and query responses.' );
		$this->assertSame( 'object', $post_schema['type'], 'The single-post response should be described as an object.' );
		$this->assertArrayNotHasKey( 'required', $post_schema, 'Individual post fields should remain optional.' );
		$this->assertFalse( $post_schema['additionalProperties'], 'Returned posts should not allow unknown properties.' );
		$this->assertArrayHasKey( 'post_type', $post_schema['properties'], 'The post schema should describe the post type as post_type.' );
		$this->assertArrayNotHasKey( 'type', $post_schema['properties'], 'The post schema should not expose the post type as type.' );
		$this->assertSame(
			$input_schema['oneOf'][2]['properties']['fields']['items']['enum'],
			array_keys( $post_schema['properties'] ),
			'The fields enum should match the post output schema properties.'
		);
		$this->assertArrayHasKey( 'content_raw', $post_schema['properties'], 'The post schema should describe raw content.' );
		$this->assertArrayHasKey( 'content_rendered', $post_schema['properties'], 'The post schema should describe rendered content.' );
		$this->assertSame( array( 'posts', 'total', 'total_pages' ), $query_schema['required'], 'The query wrapper should require all top-level properties.' );
		$this->assertArrayHasKey( 'total', $query_schema['properties'], 'The query schema should describe the total count.' );
		$this->assertArrayHasKey( 'total_pages', $query_schema['properties'], 'The query schema should describe page count.' );
	}

	/**
	 * A post type registered by another active plugin and flagged `show_in_abilities`
	 * is exposed by the ability, both in the input enum and in query results.
	 *
	 * @since 7.1.0
	 */
	public function test_exposes_a_post_type_registered_by_another_plugin(): void {
		register_post_type(
			'wpai_content_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor' ),
			)
		);

		try {
			$this->login_as( 'administrator' );
			$this->register_ability();

			// Query mode is the third `oneOf` branch; its `post_type` enum lists exposed types.
			$enum = wp_get_ability( 'core/read-content' )->get_input_schema()['oneOf'][2]['properties']['post_type']['enum'];
			$this->assertContains( 'wpai_content_cpt', $enum, 'Custom post types marked show_in_abilities should appear in the query enum.' );

			$post_id = self::factory()->post->create(
				array(
					'post_type'   => 'wpai_content_cpt',
					'post_status' => 'publish',
				)
			);

			$result = wp_get_ability( 'core/read-content' )->execute( array( 'post_type' => 'wpai_content_cpt' ) );
			$ids    = wp_list_pluck( $result['posts'], 'id' );

			$this->assertContains( $post_id, $ids, 'The custom post type should be queryable through the content ability.' );
		} finally {
			unregister_post_type( 'wpai_content_cpt' );
		}
	}

	/**
	 * A schema filter can expose a post type that is registered after the ability.
	 *
	 * @since 7.1.0
	 */
	public function test_schema_filter_exposes_late_registered_post_type(): void {
		$this->login_as( 'administrator' );

		$amend_schema = static function ( array $args, string $name ): array {
			if ( 'core/read-content' !== $name ) {
				return $args;
			}

			foreach ( $args['input_schema']['oneOf'] as $index => $mode ) {
				$args['input_schema']['oneOf'][ $index ]['properties']['post_type']['enum'][] = 'wpai_late_cpt';
			}

			return $args;
		};
		add_filter( 'wp_register_ability_args', $amend_schema, 10, 2 );

		try {
			$this->register_ability();
		} finally {
			remove_filter( 'wp_register_ability_args', $amend_schema, 10 );
		}

		$enum = wp_get_ability( 'core/read-content' )->get_input_schema()['oneOf'][2]['properties']['post_type']['enum'];
		$this->assertContains( 'wpai_late_cpt', $enum, 'The ability args filter should amend the frozen schema enum.' );

		register_post_type(
			'wpai_late_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor' ),
			)
		);

		try {
			$post_id = self::factory()->post->create(
				array(
					'post_type'   => 'wpai_late_cpt',
					'post_status' => 'publish',
				)
			);

			$result = wp_get_ability( 'core/read-content' )->execute(
				array(
					'post_type' => 'wpai_late_cpt',
					'fields'    => array( 'id' ),
				)
			);

			$this->assertSame( array( $post_id ), wp_list_pluck( $result['posts'], 'id' ), 'The late post type should be queryable after it becomes exposed.' );
		} finally {
			unregister_post_type( 'wpai_late_cpt' );
		}
	}

	/**
	 * A published post can be fetched by ID.
	 *
	 * @since 7.1.0
	 */
	public function test_get_single_published_post_by_id(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];

		$result = wp_get_ability( 'core/read-content' )->execute( array( 'id' => $post_id ) );

		$this->assertIsArray( $result, 'The by-ID lookup should return a post array.' );
		$this->assertSame( $post_id, $result['id'], 'The by-ID lookup should return the requested post directly.' );
		$this->assertSame( 'Hello Content', $result['title_rendered'], 'Rendered titles should be returned by default.' );
		$this->assertSame(
			array( 'id', 'post_type', 'status', 'date', 'slug', 'title_rendered' ),
			array_keys( $result ),
			'Omitted fields should return the lean default field set.'
		);
		$this->assertArrayNotHasKey( 'posts', $result, 'The by-ID lookup should not return the query wrapper.' );
	}

	/**
	 * Schema-valid object input behaves like its array form.
	 *
	 * WP_Ability validates `stdClass` as object input but does not coerce the value before
	 * passing it to the permission and execute callbacks, so both must preserve its fields.
	 *
	 * @since 7.1.0
	 */
	public function test_get_single_published_post_by_id_accepts_object_input(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];
		$ability = wp_get_ability( 'core/read-content' );
		$input   = (object) array(
			'id'     => $post_id,
			'fields' => array( 'id', 'title_rendered' ),
		);

		$this->assertTrue( $ability->validate_input( $input ), 'Object input should pass the registered schema.' );

		$result = $ability->execute( $input );

		$this->assertIsArray( $result, 'Object input should execute the by-ID lookup.' );
		$this->assertSame( $post_id, $result['id'], 'Object input should preserve the requested post ID.' );
		$this->assertSame( 'Hello Content', $result['title_rendered'], 'Object input should preserve the requested fields.' );
		$this->assertSame( array( 'id', 'title_rendered' ), array_keys( $result ), 'Object input should use the requested field projection.' );
	}

	/**
	 * A single post fetched by ID can return explicitly requested rendered and raw content.
	 *
	 * @since 7.1.0
	 */
	public function test_get_single_published_post_by_id_can_return_content_fields(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'post_type', 'content_rendered', 'content_raw' ),
			)
		);

		$this->assertSame( $post_id, $result['id'], 'The by-ID lookup should return the requested post.' );
		$this->assertSame( 'post', $result['post_type'], 'The by-ID lookup should return the post type as post_type.' );
		$this->assertStringContainsString( 'Body here.', $result['content_rendered'], 'Explicit content fields should include rendered content.' );
		$this->assertSame( 'Body here.', $result['content_raw'], 'Explicit content fields should include raw content.' );
		$this->assertArrayNotHasKey( 'posts', $result, 'The by-ID lookup should not return the query wrapper.' );
	}

	/**
	 * A missing post ID is denied before execution can probe the requested object.
	 *
	 * @since 7.1.0
	 */
	public function test_get_by_missing_id_is_denied(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute( array( 'id' => 999999 ) );

		$this->assertWPError( $result, 'Missing posts should be denied before execution probes object details.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Missing posts should fail closed as a permission error.' );
	}

	/**
	 * A post type guard mismatch is denied before execution can probe the requested object.
	 *
	 * @since 7.1.0
	 */
	public function test_get_by_id_with_mismatched_post_type_is_denied(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published'];

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'        => $post_id,
				'post_type' => 'page',
			)
		);

		$this->assertWPError( $result, 'Mismatched post type guards should deny the lookup.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Mismatched post type guards should fail closed as a permission error.' );
	}

	/**
	 * A post from a post type not exposed to abilities is denied.
	 *
	 * @since 7.1.0
	 */
	public function test_get_by_id_for_unexposed_post_type_is_denied(): void {
		register_post_type(
			'wpai_hidden_cpt',
			array(
				'public'       => true,
				'show_in_rest' => false,
				'supports'     => array( 'title', 'editor' ),
			)
		);

		try {
			$this->login_as( 'administrator' );

			$post_id = self::factory()->post->create(
				array(
					'post_type'   => 'wpai_hidden_cpt',
					'post_status' => 'publish',
				)
			);
			$this->assertGreaterThan( 0, $post_id, 'The hidden custom post should be created for the denial check.' );

			$this->register_ability();

			$result = wp_get_ability( 'core/read-content' )->execute( array( 'id' => $post_id ) );

			$this->assertWPError( $result, 'Posts from unexposed post types should be denied.' );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Unexposed post types should fail closed as a permission error.' );
		} finally {
			unregister_post_type( 'wpai_hidden_cpt' );
		}
	}

	/**
	 * A status that is public but not viewable is not exposed to read-only users.
	 *
	 * @since 7.1.0
	 */
	public function test_public_non_viewable_status_is_denied_for_read_only_users(): void {
		register_post_status(
			'wpai_public_hidden',
			array(
				'label'              => 'Public hidden',
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		try {
			$post_id = self::factory()->post->create(
				array(
					'post_status' => 'wpai_public_hidden',
				)
			);

			$this->login_as( 'subscriber' );
			$this->register_ability();

			$result = wp_get_ability( 'core/read-content' )->execute( array( 'id' => $post_id ) );

			$this->assertWPError( $result, 'Read-only users should not receive public statuses that are not viewable.' );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Non-viewable public statuses should fail closed for read-only users.' );
		} finally {
			unset( $GLOBALS['wp_post_statuses']['wpai_public_hidden'] );
		}
	}

	/**
	 * A status that is public but not viewable remains available to users who can edit it.
	 *
	 * @since 7.1.0
	 */
	public function test_public_non_viewable_status_is_readable_with_edit_access(): void {
		register_post_status(
			'wpai_public_hidden',
			array(
				'label'              => 'Public hidden',
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		try {
			$post_id = self::factory()->post->create(
				array(
					'post_title'  => 'Hidden public status',
					'post_status' => 'wpai_public_hidden',
				)
			);

			$this->login_as( 'administrator' );
			$this->register_ability();

			$result = wp_get_ability( 'core/read-content' )->execute( array( 'id' => $post_id ) );

			$this->assertIsArray( $result, 'Editors should be able to access posts they can edit even when the status is not publicly viewable.' );
			$this->assertSame( $post_id, $result['id'], 'The editable post should be returned.' );
			$this->assertSame( 'Hidden public status', $result['title_rendered'], 'The editable post should include normal default fields.' );
		} finally {
			unset( $GLOBALS['wp_post_statuses']['wpai_public_hidden'] );
		}
	}

	/**
	 * A post that inherits its status from a readable parent is readable.
	 *
	 * @since 7.1.0
	 */
	public function test_inherited_post_is_readable_when_parent_is_readable(): void {
		register_post_type(
			'wpai_inherit_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor' ),
			)
		);

		try {
			$parent_id = self::factory()->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => 'wpai_inherit_cpt',
					'post_status' => 'publish',
				)
			);
			$child_id  = self::factory()->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => 'wpai_inherit_cpt',
					'post_parent' => $parent_id,
					'post_status' => 'inherit',
					'post_title'  => 'Inherited child',
				)
			);

			$this->login_as( 'subscriber' );
			$this->register_ability();

			$result = wp_get_ability( 'core/read-content' )->execute( array( 'id' => $child_id ) );

			$this->assertIsArray( $result, 'Inherited posts should be readable when their parent is readable.' );
			$this->assertSame( $child_id, $result['id'], 'The inherited child should be returned.' );
			$this->assertSame( 'Inherited child', $result['title_rendered'], 'The inherited child should include normal default fields.' );
		} finally {
			unregister_post_type( 'wpai_inherit_cpt' );
		}
	}

	/**
	 * A post with an inherited status but no readable parent is denied.
	 *
	 * @since 7.1.0
	 */
	public function test_inherited_post_without_parent_is_denied_for_read_only_users(): void {
		register_post_type(
			'wpai_inherit_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor' ),
			)
		);

		try {
			$post_id = self::factory()->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => 'wpai_inherit_cpt',
					'post_status' => 'inherit',
				)
			);

			$this->login_as( 'subscriber' );
			$this->register_ability();

			$result = wp_get_ability( 'core/read-content' )->execute( array( 'id' => $post_id ) );

			$this->assertWPError( $result, 'Inherited posts without a readable parent should be denied.' );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Orphaned inherited posts should fail closed.' );
		} finally {
			unregister_post_type( 'wpai_inherit_cpt' );
		}
	}

	/**
	 * Query mode returns only published posts by default.
	 *
	 * @since 7.1.0
	 */
	public function test_query_returns_only_published_by_default(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$published = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$result = wp_get_ability( 'core/read-content' )->execute( array( 'post_type' => 'post' ) );
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $published, $ids, 'Published posts should be returned by default.' );
		$this->assertNotContains( $draft, $ids, 'Draft posts should not be returned by default.' );
	}

	/**
	 * Query mode can limit results to included IDs.
	 *
	 * @since 7.1.0
	 */
	public function test_query_include_limits_results(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$first  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$second = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$third  = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'include'   => array( $third, $first ),
				'fields'    => array( 'id' ),
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		sort( $ids );
		$expected = array( $first, $third );
		sort( $expected );

		$this->assertSame( $expected, $ids, 'Included post IDs should limit results without requiring caller order.' );
		$this->assertNotContains( $second, $ids, 'Posts outside include should not be returned.' );
	}

	/**
	 * Query results are ordered by post date, newest first, whatever order `include` uses.
	 *
	 * Pins both halves of what the schema advertises. The ability leaves `orderby` at the
	 * WP_Query default, matching the REST posts controller, and `include` only filters the
	 * query, so a caller that passes IDs in a chosen order must not expect them back in it.
	 *
	 * @since 7.1.0
	 */
	public function test_query_orders_posts_newest_first_regardless_of_include_order(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$oldest = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$middle = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-02-01 10:00:00',
			)
		);
		$newest = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-03-01 10:00:00',
			)
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				// Deliberately neither date order nor ID order.
				'include'   => array( $middle, $newest, $oldest ),
				'fields'    => array( 'id' ),
			)
		);

		$this->assertSame(
			array( $newest, $middle, $oldest ),
			wp_list_pluck( $result['posts'], 'id' ),
			'Results should be ordered by post date, newest first, not by the order of the include list.'
		);
	}

	/**
	 * Query include still respects the requested post type.
	 *
	 * @since 7.1.0
	 */
	public function test_query_include_respects_requested_post_type(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'include'   => array( $page_id, $post_id ),
				'fields'    => array( 'id' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['posts'], 'id' ), 'Include should not leak posts from other post types.' );
	}

	/**
	 * Query include still respects row-level permissions.
	 *
	 * @since 7.1.0
	 */
	public function test_query_include_respects_row_level_permissions(): void {
		$author_a = self::$user_ids['author'];
		$author_b = self::$user_ids['author_secondary'];

		$draft_a = self::factory()->post->create(
			array(
				'post_author' => $author_a,
				'post_status' => 'draft',
			)
		);
		$draft_b = self::factory()->post->create(
			array(
				'post_author' => $author_b,
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( $author_b );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'draft' ),
				'include'   => array( $draft_a, $draft_b ),
				'fields'    => array( 'id' ),
			)
		);

		$this->assertSame( array( $draft_b ), wp_list_pluck( $result['posts'], 'id' ), 'Include should not bypass row-level draft permissions.' );
	}

	/**
	 * Query mode can return included drafts with explicitly requested rendered and raw content.
	 *
	 * @since 7.1.0
	 */
	public function test_query_draft_include_can_return_content_fields(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$draft = self::factory()->post->create(
			array(
				'post_title'   => 'Draft content fields',
				'post_content' => 'Draft body for content fields.',
				'post_status'  => 'draft',
			)
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'draft' ),
				'include'   => array( $draft ),
				'fields'    => array( 'id', 'post_type', 'status', 'content_rendered', 'content_raw' ),
			)
		);

		$this->assertSame( array( $draft ), wp_list_pluck( $result['posts'], 'id' ), 'The draft query should return only the included draft.' );
		$this->assertSame( 'post', $result['posts'][0]['post_type'], 'Query responses should return the post type as post_type.' );
		$this->assertSame( 'draft', $result['posts'][0]['status'], 'The draft query should expose the requested draft status.' );
		$this->assertStringContainsString( 'Draft body for content fields.', $result['posts'][0]['content_rendered'], 'Draft query results should include rendered content when requested.' );
		$this->assertSame( 'Draft body for content fields.', $result['posts'][0]['content_raw'], 'Draft query results should include raw content when requested.' );
	}

	/**
	 * Querying by slug without a post type is rejected by the input schema.
	 *
	 * @since 7.1.0
	 */
	public function test_slug_mode_requires_post_type(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute( array( 'slug' => 'whatever' ) );

		$this->assertWPError( $result, 'Slug queries without a post type should fail validation.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Invalid slug queries should return an input error.' );
	}

	/**
	 * Slug mode returns a single post directly when paired with a post type.
	 *
	 * @since 7.1.0
	 */
	public function test_get_single_published_post_by_slug(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::factory()->post->create(
			array(
				'post_name'   => 'content-slug-mode',
				'post_title'  => 'Content Slug Mode',
				'post_status' => 'publish',
			)
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'content-slug-mode',
			)
		);

		$this->assertIsArray( $result, 'The slug lookup should return a post array.' );
		$this->assertSame( $post_id, $result['id'], 'The slug lookup should return the requested post directly.' );
		$this->assertSame( 'content-slug-mode', $result['slug'], 'The slug lookup should return the matching slug.' );
		$this->assertArrayNotHasKey( 'posts', $result, 'The slug lookup should not return the query wrapper.' );
		$this->assertArrayNotHasKey( 'total', $result, 'The slug lookup should not return query totals.' );
	}

	/**
	 * A single post fetched by slug can return explicitly requested rendered and raw content.
	 *
	 * @since 7.1.0
	 */
	public function test_get_single_published_post_by_slug_can_return_content_fields(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::factory()->post->create(
			array(
				'post_name'    => 'content-slug-fields',
				'post_title'   => 'Content Slug Fields',
				'post_content' => 'Slug body for content fields.',
				'post_status'  => 'publish',
			)
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'content-slug-fields',
				'fields'    => array( 'id', 'post_type', 'slug', 'content_rendered', 'content_raw' ),
			)
		);

		$this->assertSame( $post_id, $result['id'], 'The slug lookup should return the requested post.' );
		$this->assertSame( 'post', $result['post_type'], 'The slug lookup should return the post type as post_type.' );
		$this->assertSame( 'content-slug-fields', $result['slug'], 'The slug lookup should return the matching slug.' );
		$this->assertStringContainsString( 'Slug body for content fields.', $result['content_rendered'], 'Slug lookups should include rendered content when requested.' );
		$this->assertSame( 'Slug body for content fields.', $result['content_raw'], 'Slug lookups should include raw content when requested.' );
		$this->assertArrayNotHasKey( 'posts', $result, 'The slug lookup should not return the query wrapper.' );
	}

	/**
	 * A published post is not hidden behind more same-slug drafts than a page holds.
	 *
	 * The slug lookup is a singular WP_Query, so it returns every matching row and no page
	 * size applies. Bounding it with `post_name__in` and a page size would page straight past
	 * an older published post, because the query is ordered newest first.
	 *
	 * @since 7.1.0
	 */
	public function test_slug_lookup_is_not_bounded_by_a_page_size(): void {
		global $wpdb;

		// Author every post as the administrator, so the subscriber who reads them below can
		// only see the published one.
		$this->login_as( 'administrator' );

		// The published post owns the slug and is the oldest of the group.
		$published = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'wpai-slug-not-bounded',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);

		// Drafts skip slug uniqueness, so they can all share the slug. Create more of them
		// than the largest page the ability will ever return.
		for ( $i = 0; $i < 110; $i++ ) {
			self::factory()->post->create(
				array(
					'post_status' => 'draft',
					'post_name'   => 'wpai-slug-not-bounded',
					'post_date'   => '2026-03-01 10:00:00',
				)
			);
		}

		$sharing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s", 'wpai-slug-not-bounded' ) ); // phpcs:ignore WordPress.DB
		$this->assertGreaterThan( 100, $sharing, 'Precondition: more posts share the slug than a single page holds.' );

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'wpai-slug-not-bounded',
				'fields'    => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The published post should resolve even though the drafts fill more than a page.' );
		$this->assertSame( $published, $result['id'], 'The readable published post should still resolve.' );
	}

	/**
	 * A post whose slug is the literal string "0" is fetched in single-post slug mode.
	 *
	 * @since 7.1.0
	 */
	public function test_get_single_post_by_slug_zero(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		// Core regenerates an "empty" post_name from the title, so a post titled "0"
		// ends up with the literal slug "0".
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => '0',
				'post_name'   => '0',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( '0', get_post( $post_id )->post_name, 'Precondition: the post slug should be the literal string "0".' );

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'slug'      => '0',
			)
		);

		$this->assertIsArray( $result, 'The slug lookup should return a post array.' );
		$this->assertSame( $post_id, $result['id'], 'Slug mode should resolve the literal "0" slug to the post.' );
		$this->assertArrayNotHasKey( 'posts', $result, 'A "0" slug should not fall through to the query wrapper.' );
	}

	/**
	 * Query-only filters cannot be combined with slug mode.
	 *
	 * @since 7.1.0
	 */
	public function test_slug_mode_rejects_query_only_params(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'content-slug-mode',
				'per_page'  => 10,
			)
		);

		$this->assertWPError( $result, 'Combining slug mode with query-only params should fail validation.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Invalid slug mode combinations should return an input error.' );
	}

	/**
	 * A newer draft sharing a published post's slug does not shadow the published post.
	 *
	 * @since 7.1.0
	 */
	public function test_slug_lookup_prefers_published_post_over_newer_draft(): void {
		$published_id = self::factory()->post->create(
			array(
				'post_name'   => 'shadowed-slug',
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		// Drafts skip slug uniqueness, so a newer draft can share the published slug.
		$draft_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_name'   => 'shadowed-slug',
				'post_status' => 'draft',
				'post_date'   => '2026-06-01 10:00:00',
			)
		);

		$this->assertSame( 'shadowed-slug', get_post( $draft_id )->post_name, 'Precondition: the draft should share the published slug.' );
		$this->register_ability();

		foreach ( array( 'subscriber', 'editor' ) as $role ) {
			$this->login_as( $role );

			$result = wp_get_ability( 'core/read-content' )->execute(
				array(
					'post_type' => 'post',
					'slug'      => 'shadowed-slug',
				)
			);

			$this->assertIsArray( $result, "The slug lookup should succeed for a {$role}." );
			$this->assertSame( $published_id, $result['id'], "The slug lookup should resolve to the published post for a {$role}." );
		}
	}

	/**
	 * A slug held only by a draft resolves for its author and stays denied for readers.
	 *
	 * @since 7.1.0
	 */
	public function test_slug_lookup_resolves_draft_only_slug_by_readability(): void {
		$author_id = self::$user_ids['author'];
		$draft_id  = self::factory()->post->create(
			array(
				'post_author' => $author_id,
				'post_name'   => 'draft-only-slug',
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( $author_id );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'draft-only-slug',
			)
		);

		$this->assertIsArray( $result, 'The draft author should resolve their own draft by slug.' );
		$this->assertSame( $draft_id, $result['id'], 'The draft author should receive their own draft.' );

		$this->login_as( 'subscriber' );

		$denied = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'draft-only-slug',
			)
		);

		$this->assertWPError( $denied, 'Readers should not resolve a slug held only by an unreadable draft.' );
		$this->assertSame( 'ability_invalid_permissions', $denied->get_error_code(), 'Unreadable slug lookups should fail closed as a permission error.' );
	}

	/**
	 * Include is a query-only option and cannot be combined with single-post modes.
	 *
	 * @since 7.1.0
	 */
	public function test_include_cannot_be_combined_with_single_post_modes(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$by_id   = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'      => self::$post_ids['published'],
				'include' => array( self::$post_ids['published'] ),
			)
		);
		$by_slug = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'whatever',
				'include'   => array( self::$post_ids['published'] ),
			)
		);

		$this->assertWPError( $by_id, 'Include should fail validation in ID mode.' );
		$this->assertSame( 'ability_invalid_input', $by_id->get_error_code(), 'ID plus include should return an input error.' );
		$this->assertWPError( $by_slug, 'Include should fail validation in slug mode.' );
		$this->assertSame( 'ability_invalid_input', $by_slug->get_error_code(), 'Slug plus include should return an input error.' );
	}

	/**
	 * The `fields` filter limits the returned keys.
	 *
	 * @since 7.1.0
	 */
	public function test_fields_filter_limits_returned_keys(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'title_rendered' ),
			)
		);

		$this->assertSame(
			array( 'id', 'title_rendered' ),
			array_keys( $result ),
			'The fields filter should limit the response to exactly the requested keys.'
		);
	}

	/**
	 * An unknown requested field name fails schema validation.
	 *
	 * Unlike fields a post type does not support, which are omitted per post, a field
	 * name that is not part of the supported set is rejected before the ability executes.
	 *
	 * @since 7.1.0
	 */
	public function test_unknown_requested_field_fails_schema_validation(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => self::$post_ids['published_content'],
				'fields' => array( 'id', 'bogus_field' ),
			)
		);

		$this->assertWPError( $result, 'An unknown requested field should fail the request.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Unknown fields should use the invalid input error.' );
	}

	/**
	 * Logged-out users cannot run the ability.
	 *
	 * @since 7.1.0
	 */
	public function test_logged_out_user_is_denied(): void {
		wp_set_current_user( 0 );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute( array( 'post_type' => 'post' ) );

		$this->assertWPError( $result, 'Logged-out users should not be allowed to run the content ability.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Logged-out users should receive a permission error.' );
	}

	/**
	 * Subscribers can request rendered published content.
	 *
	 * @since 7.1.0
	 */
	public function test_subscriber_can_request_published_content(): void {
		$post_id = self::$post_ids['subscriber_content'];

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'fields'    => array( 'id', 'title_rendered', 'content_rendered' ),
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $post_id, $ids, 'Subscribers should be able to query readable published posts.' );
		$post_index = array_search( $post_id, $ids, true );
		$this->assertIsInt( $post_index, 'The published post should be present in the subscriber query response.' );
		$post = $result['posts'][ $post_index ];
		$this->assertSame( 'Visible to subscribers', $post['title_rendered'], 'Subscribers should receive rendered titles.' );
		$this->assertStringContainsString( 'Rendered body for subscribers.', $post['content_rendered'], 'Subscribers should receive rendered content.' );
		$this->assertArrayNotHasKey( 'content_raw', $post, 'Subscribers should not receive raw content without edit access.' );
	}

	/**
	 * Subscribers can fetch a published post by ID.
	 *
	 * @since 7.1.0
	 */
	public function test_subscriber_can_get_single_published_post_by_id(): void {
		$post_id = self::$post_ids['readable_single'];

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute( array( 'id' => $post_id ) );

		$this->assertIsArray( $result, 'Subscribers should be able to fetch a readable published post by ID.' );
		$this->assertSame( 'Readable single', $result['title_rendered'], 'Subscribers should receive the rendered title.' );
		$this->assertArrayNotHasKey( 'title_raw', $result, 'Subscribers should not receive raw titles without edit access.' );
		$this->assertArrayNotHasKey( 'content_raw', $result, 'Subscribers should not receive raw content without edit access.' );
		$this->assertArrayNotHasKey( 'content_rendered', $result, 'Rendered content should require an explicit field request.' );
	}

	/**
	 * Subscribers cannot request edit-context raw fields in query mode.
	 *
	 * @since 7.1.0
	 */
	public function test_subscriber_cannot_request_raw_fields_in_query_mode(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'fields'    => array( 'content_raw' ),
			)
		);

		$this->assertWPError( $result, 'Subscribers should not be able to request raw fields in query mode.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Subscriber raw-field query requests should return a permission error.' );
	}

	/**
	 * Subscribers cannot request edit-context raw fields for a single post.
	 *
	 * @since 7.1.0
	 */
	public function test_subscriber_cannot_request_raw_fields_for_single_post(): void {
		$post_id = self::$post_ids['published'];

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'content_raw' ),
			)
		);

		$this->assertWPError( $result, 'Subscribers should not be able to request raw fields by ID.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Subscriber raw-field by-ID requests should return a permission error.' );
	}

	/**
	 * Users who cannot edit another user's post do not receive raw fields by default.
	 *
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 *
	 * @param string $role The role to test.
	 */
	public function test_default_fields_omit_raw_fields_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$post_id = self::$post_ids['limited_role_content'];

		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute( array( 'id' => $post_id ) );

		$this->assertIsArray( $result, 'The readable published post should be returned.' );
		$this->assertSame( 'Readable title', $result['title_rendered'], 'Rendered title should remain visible.' );
		$this->assertArrayNotHasKey( 'title_raw', $result, 'Raw title should be omitted.' );
		$this->assertArrayNotHasKey( 'excerpt_raw', $result, 'Raw excerpt should be omitted.' );
		$this->assertArrayNotHasKey( 'content_raw', $result, 'Raw content should be omitted.' );
		$this->assertArrayNotHasKey( 'content_rendered', $result, 'Rendered content should be omitted from the lean default field set.' );
	}

	/**
	 * Users who cannot edit another user's post cannot explicitly request raw fields.
	 *
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 *
	 * @param string $role The role to test.
	 */
	public function test_raw_field_requests_are_denied_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$post_id = self::$post_ids['limited_role_content'];

		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'content_raw' ),
			)
		);

		$this->assertWPError( $result, 'Raw field requests should fail for users without edit access.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Raw field requests should require edit access to the post.' );
	}

	/**
	 * Subscribers cannot request draft posts.
	 *
	 * @since 7.1.0
	 */
	public function test_subscriber_cannot_request_draft_status(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'draft' ),
			)
		);

		$this->assertWPError( $result, 'Subscribers should not be allowed to query draft posts.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Subscriber draft queries should return a permission error.' );
	}

	/**
	 * Subscribers cannot request private posts.
	 *
	 * @since 7.1.0
	 */
	public function test_subscriber_cannot_request_private_status(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'private' ),
			)
		);

		$this->assertWPError( $result, 'Subscribers should not be allowed to query private posts.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Subscriber private queries should return a permission error.' );
	}

	/**
	 * An author can pass the draft gate but only sees their own drafts.
	 *
	 * @since 7.1.0
	 */
	public function test_author_cannot_see_other_authors_drafts(): void {
		$author_a = self::$user_ids['author'];
		$author_b = self::$user_ids['author_secondary'];

		$draft_a = self::factory()->post->create(
			array(
				'post_author' => $author_a,
				'post_status' => 'draft',
			)
		);
		$draft_b = self::factory()->post->create(
			array(
				'post_author' => $author_b,
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( $author_b );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'draft' ),
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $draft_b, $ids, 'Authors should see their own drafts.' );
		$this->assertNotContains( $draft_a, $ids, 'Authors should not see another author\'s drafts.' );
	}

	/**
	 * Query totals mirror WP_Query even when row-level permissions withhold rows.
	 *
	 * This matches the REST posts controller: `posts` only contains rows the current
	 * user can read, while `total` and `total_pages` describe the underlying query.
	 *
	 * @since 7.1.0
	 */
	public function test_query_totals_may_include_rows_withheld_by_row_level_permissions(): void {
		$author_a = self::$user_ids['author'];
		$author_b = self::$user_ids['author_secondary'];

		$draft_a = self::factory()->post->create(
			array(
				'post_author' => $author_a,
				'post_status' => 'draft',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$draft_b = self::factory()->post->create(
			array(
				'post_author' => $author_b,
				'post_status' => 'draft',
				'post_date'   => '2026-01-02 10:00:00',
			)
		);

		wp_set_current_user( $author_b );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'draft' ),
				'per_page'  => 1,
				'fields'    => array( 'id' ),
			)
		);

		$this->assertSame( array( $draft_b ), wp_list_pluck( $result['posts'], 'id' ), 'Authors should receive only drafts they can read.' );
		$this->assertNotContains( $draft_a, wp_list_pluck( $result['posts'], 'id' ), 'Rows withheld by row-level permissions should not be returned.' );
		$this->assertGreaterThan( count( $result['posts'] ), $result['total'], 'Totals may include rows withheld by row-level permission checks.' );
		$this->assertSame( 2, $result['total_pages'], 'Page counts should be based on the underlying query total, matching REST behavior.' );
	}

	/**
	 * The parent filter is rejected for non-hierarchical post types, mirroring REST.
	 *
	 * @since 7.1.0
	 */
	public function test_query_mode_rejects_parent_filter_for_non_hierarchical_post_type(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'parent'    => 0,
			)
		);

		$this->assertWPError( $result, 'The parent filter should be rejected for non-hierarchical post types.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'Unsupported parent filters should return a filter error.' );
	}

	/**
	 * The parent filter narrows hierarchical queries to children of the given post.
	 *
	 * @since 7.1.0
	 */
	public function test_query_mode_filters_pages_by_parent(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$parent_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$child_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_id,
				'post_status' => 'publish',
			)
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'page',
				'parent'    => $parent_id,
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertSame( array( $child_id ), $ids, 'The parent filter should return only the children of the given page.' );
	}

	/**
	 * The author filter is rejected for post types without author support, mirroring REST.
	 *
	 * @since 7.1.0
	 */
	public function test_query_mode_rejects_author_filter_for_post_type_without_author_support(): void {
		register_post_type(
			'wpai_no_author_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title' ),
			)
		);

		try {
			$this->login_as( 'administrator' );
			$this->register_ability();

			$result = wp_get_ability( 'core/read-content' )->execute(
				array(
					'post_type' => 'wpai_no_author_cpt',
					'author'    => self::$user_ids['author'],
				)
			);

			$this->assertWPError( $result, 'The author filter should be rejected for post types without author support.' );
			$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'Unsupported author filters should return a filter error.' );
		} finally {
			unregister_post_type( 'wpai_no_author_cpt' );
		}
	}

	/**
	 * The author filter narrows queries to posts by the given author.
	 *
	 * @since 7.1.0
	 */
	public function test_query_mode_filters_posts_by_author(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$mine_id  = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'publish',
			)
		);
		$other_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author_secondary'],
				'post_status' => 'publish',
			)
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'author'    => self::$user_ids['author'],
				'per_page'  => 100,
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $mine_id, $ids, 'The author filter should include the author\'s posts.' );
		$this->assertNotContains( $other_id, $ids, 'The author filter should exclude other authors\' posts.' );
	}

	/**
	 * Raw content is available to users who can edit the post.
	 *
	 * @since 7.1.0
	 */
	public function test_raw_content_visible_to_editor(): void {
		$post_id = self::$post_ids['raw_content'];

		$this->login_as( 'editor' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'content_raw' ),
			)
		);

		$this->assertSame(
			'Public body with raw block markup.',
			$result['content_raw'],
			'Editors should receive explicitly requested raw content.'
		);
	}

	/**
	 * Password-protected content is visible to users who can edit the post.
	 *
	 * @since 7.1.0
	 */
	public function test_password_protected_content_visible_to_editor(): void {
		$post_id = self::$post_ids['password_protected_editor'];

		$this->login_as( 'editor' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'content_raw', 'content_rendered' ),
			)
		);

		$this->assertSame(
			'Top secret body.',
			$result['content_raw'],
			'Editors should receive raw password-protected content.'
		);
		$this->assertStringContainsString(
			'Top secret body.',
			$result['content_rendered'],
			'Editors should receive rendered password-protected content.'
		);
	}

	/**
	 * Password-protected rendered content is withheld from users who cannot edit the post.
	 *
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 *
	 * @param string $role The role to test.
	 */
	public function test_password_protected_rendered_content_is_empty_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$post_id = self::$post_ids['password_protected_limited'];

		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'content_rendered', 'content_protected' ),
			)
		);

		$this->assertSame( '', $result['content_rendered'], 'Password-protected rendered content should be withheld.' );
		$this->assertTrue( $result['content_protected'], 'The protected flag should reveal the field is password-protected.' );
	}

	/**
	 * Password-protected excerpts render for users who can edit the post.
	 *
	 * @since 7.1.0
	 */
	public function test_password_protected_excerpt_visible_to_editor(): void {
		$this->login_as( 'editor' );
		$this->register_ability();

		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_excerpt'  => 'Top secret excerpt.',
			)
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'excerpt_rendered', 'excerpt_protected' ),
			)
		);

		$this->assertSame(
			"<p>Top secret excerpt.</p>\n",
			$result['excerpt_rendered'],
			'Editors should receive the real rendered excerpt for password-protected posts.'
		);
		$this->assertTrue( $result['excerpt_protected'], 'The protected flag should reveal the excerpt is password-protected.' );
	}

	/**
	 * Password-protected rendered excerpts are withheld from users who cannot edit the post.
	 *
	 * @since 7.1.0
	 */
	public function test_password_protected_rendered_excerpt_is_empty_for_subscriber(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author'   => self::$user_ids['administrator'],
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_excerpt'  => 'Hidden excerpt.',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'excerpt_rendered', 'excerpt_protected' ),
			)
		);

		$this->assertSame( '', $result['excerpt_rendered'], 'Password-protected rendered excerpts should be withheld.' );
		$this->assertTrue( $result['excerpt_protected'], 'The protected flag should reveal the excerpt is password-protected.' );
	}

	/**
	 * Rendered excerpts carry the REST API's `the_excerpt` markup (paragraph wrapping).
	 *
	 * @since 7.1.0
	 */
	public function test_excerpt_rendered_applies_the_excerpt_filters(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => self::$post_ids['limited_role_content'],
				'fields' => array( 'id', 'excerpt_rendered' ),
			)
		);

		$this->assertSame(
			"<p>Readable excerpt.</p>\n",
			$result['excerpt_rendered'],
			'Rendered excerpts should match the REST API excerpt filter output.'
		);
	}

	/**
	 * Rendered excerpt filters run with the requested post as the global context and restore
	 * the context that was active before the ability executed.
	 *
	 * @since 7.1.0
	 */
	public function test_excerpt_rendered_uses_and_restores_requested_post_context(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$target_id     = self::$post_ids['limited_role_content'];
		$surrounding   = get_post( self::$post_ids['published'] );
		$previous_post = $GLOBALS['post'] ?? null;

		$this->assertInstanceOf( \WP_Post::class, $surrounding, 'The surrounding post fixture should exist.' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Establishes a distinct context to verify the ability restores it.
		$GLOBALS['post'] = $surrounding;
		setup_postdata( $surrounding );

		$append_context_id = static function ( $excerpt ): string {
			return (string) $excerpt . '<!-- excerpt-context:' . get_the_ID() . ' -->';
		};
		add_filter( 'the_excerpt', $append_context_id, 20 );

		try {
			$result              = wp_get_ability( 'core/read-content' )->execute(
				array(
					'id'     => $target_id,
					'fields' => array( 'id', 'excerpt_rendered' ),
				)
			);
			$restored_context_id = get_the_ID();
		} finally {
			remove_filter( 'the_excerpt', $append_context_id, 20 );

			if ( $previous_post instanceof \WP_Post ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the context that preceded the test.
				$GLOBALS['post'] = $previous_post;
				setup_postdata( $previous_post );
			} else {
				unset( $GLOBALS['post'] );
				wp_reset_postdata();
			}
		}

		$this->assertStringContainsString(
			'<!-- excerpt-context:' . $target_id . ' -->',
			$result['excerpt_rendered'],
			'Excerpt filters should see the requested post as the current post.'
		);
		$this->assertSame(
			$surrounding->ID,
			$restored_context_id,
			'The surrounding post context should be restored after rendering.'
		);
	}

	/**
	 * The password gate is suspended only for posts the current user can edit.
	 *
	 * @since 7.1.0
	 */
	public function test_allow_password_content_only_unlocks_editable_posts(): void {
		$owned_id = self::factory()->post->create(
			array(
				'post_author'   => self::$user_ids['author'],
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		$other_id = self::$post_ids['password_protected_limited'];

		$this->login_as( 'author' );

		$ability = new WP_Content_Abilities();

		$this->assertFalse(
			$ability->allow_password_content( true, get_post( $owned_id ) ),
			'The filter should unlock a protected post the current user can edit.'
		);
		$this->assertTrue(
			$ability->allow_password_content( true, get_post( $other_id ) ),
			'The filter should keep the gate on a protected post the current user cannot edit.'
		);
		$this->assertFalse(
			$ability->allow_password_content( false, get_post( $other_id ) ),
			'The filter should leave posts that do not require a password ungated.'
		);
	}

	/**
	 * Rendering an editable protected post must not unlock other protected posts embedded in
	 * its content (e.g. through a shortcode or Query Loop block).
	 *
	 * @since 7.1.0
	 */
	public function test_password_filter_does_not_leak_other_protected_posts(): void {
		$hidden_id = self::factory()->post->create(
			array(
				'post_author'   => self::$user_ids['administrator'],
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_content'  => 'NESTED_SECRET_MARKER',
			)
		);

		$author_id = $this->login_as( 'author' );

		$owned_id = self::factory()->post->create(
			array(
				'post_author'   => $author_id,
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_content'  => '[read_content_nested id="' . $hidden_id . '"]',
			)
		);

		add_shortcode(
			'read_content_nested',
			static function ( $atts ): string {
				$id = is_array( $atts ) && isset( $atts['id'] ) ? (int) $atts['id'] : 0;

				return post_password_required( $id ) ? 'GATED' : (string) get_post_field( 'post_content', $id );
			}
		);

		$this->register_ability();

		try {
			$result = wp_get_ability( 'core/read-content' )->execute(
				array(
					'id'     => $owned_id,
					'fields' => array( 'id', 'content_rendered' ),
				)
			);
		} finally {
			remove_shortcode( 'read_content_nested' );
		}

		$this->assertStringNotContainsString(
			'NESTED_SECRET_MARKER',
			$result['content_rendered'],
			'Rendering an editable protected post must not unlock another protected post it embeds.'
		);
		$this->assertStringContainsString(
			'GATED',
			$result['content_rendered'],
			'The embedded protected post should still report as password-gated.'
		);
	}

	/**
	 * Query mode paginates with `page`/`per_page` and reports totals.
	 *
	 * @since 7.1.0
	 */
	public function test_query_paginates_and_reports_totals(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$page1 = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'per_page'  => 2,
				'page'      => 1,
			)
		);

		$this->assertCount( 2, $page1['posts'], 'The first page should honor the requested per_page value.' );
		$this->assertGreaterThanOrEqual( 3, $page1['total'], 'The query should report the total matching post count.' );
		$this->assertSame( (int) ceil( $page1['total'] / 2 ), $page1['total_pages'], 'The query should report the computed total page count.' );

		$page2 = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'per_page'  => 2,
				'page'      => 2,
			)
		);

		$this->assertNotEmpty( $page2['posts'], 'The second page should return remaining posts.' );
		$this->assertSame( $page1['total'], $page2['total'], 'Pagination should keep total counts stable across pages.' );
	}

	/**
	 * Query mode reports a total that matches the returned posts for an uncapped query.
	 *
	 * @since 7.1.0
	 */
	public function test_query_total_matches_returned_posts_when_uncapped(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'per_page'  => 100,
			)
		);

		$this->assertNotEmpty( $result['posts'], 'An uncapped query should return the readable posts.' );
		$this->assertSame( count( $result['posts'] ), $result['total'], 'An uncapped query should report a total equal to the number of returned posts.' );
		$this->assertSame( 1, $result['total_pages'], 'An uncapped query should fit on a single page.' );
	}

	/**
	 * The last page still reports the totals of the underlying query.
	 *
	 * Guards the boundary next to the out-of-range page error: the final page must not be
	 * mistaken for an overshoot.
	 *
	 * @since 7.1.0
	 */
	public function test_query_last_page_reports_totals(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'include'   => $ids,
				'per_page'  => 2,
				'page'      => 2,
				'fields'    => array( 'id' ),
			)
		);

		$this->assertCount( 1, $result['posts'], 'The last page should return the remaining post.' );
		$this->assertSame( 3, $result['total'], 'The last page should report the full total.' );
		$this->assertSame( 2, $result['total_pages'], 'The last page should report the full page count.' );
	}

	/**
	 * Paging past the last page reports an error rather than an empty collection.
	 *
	 * `WP_Query::set_found_posts()` skips the count when a page yields no rows, so without
	 * recovering the total an out-of-range page would report `total: 0, total_pages: 0`,
	 * which is indistinguishable from an empty collection. Match the REST posts controller
	 * by reporting this as a caller error instead.
	 *
	 * @since 7.1.0
	 */
	public function test_query_out_of_range_page_is_rejected_rather_than_reported_as_empty(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'include'   => $ids,
				'per_page'  => 2,
				'page'      => 99,
				'fields'    => array( 'id' ),
			)
		);

		$this->assertWPError( $result, 'A page beyond the last one should fail rather than return an empty list.' );
		$this->assertSame( 'content_invalid_page_number', $result->get_error_code(), 'Out-of-range pages should report a dedicated error code.' );
		$this->assertSame( 400, $result->get_error_data()['status'], 'An out-of-range page is a caller error.' );
	}

	/**
	 * A genuinely empty result set beyond the first page reports zero totals, not an error.
	 *
	 * The out-of-range guard only fires when the underlying query actually matched rows.
	 *
	 * @since 7.1.0
	 */
	public function test_query_empty_result_beyond_first_page_reports_zero_totals(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'include'   => array( 999999 ),
				'page'      => 2,
				'fields'    => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'An empty result set should not be treated as an out-of-range page.' );
		$this->assertSame( array(), $result['posts'], 'No posts match the query.' );
		$this->assertSame( 0, $result['total'], 'An empty result set reports a zero total.' );
		$this->assertSame( 0, $result['total_pages'], 'An empty result set reports zero pages.' );
	}

	/**
	 * Include returns every requested post when `per_page` is omitted.
	 *
	 * Without this the default page size silently truncates a batch load: a caller asking
	 * for a known set of IDs would receive only the first `per_page` of them.
	 *
	 * @since 7.1.0
	 */
	public function test_query_include_returns_every_requested_post_without_per_page(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		// More than DEFAULT_PER_PAGE (10) so truncation would be visible.
		$ids = self::factory()->post->create_many( 15, array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'include'   => $ids,
				'fields'    => array( 'id' ),
			)
		);

		$returned = wp_list_pluck( $result['posts'], 'id' );
		sort( $returned );
		sort( $ids );

		$this->assertSame( $ids, $returned, 'Every requested post ID should be returned on a single page.' );
		$this->assertSame( 15, $result['total'], 'The total should cover every requested post.' );
		$this->assertSame( 1, $result['total_pages'], 'Included posts should fit on a single page by default.' );
	}

	/**
	 * An explicit `per_page` still paginates an include request.
	 *
	 * @since 7.1.0
	 */
	public function test_query_include_honors_an_explicit_per_page(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 5, array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'include'   => $ids,
				'per_page'  => 2,
				'fields'    => array( 'id' ),
			)
		);

		$this->assertCount( 2, $result['posts'], 'An explicit per_page should paginate included posts.' );
		$this->assertSame( 5, $result['total'], 'The total should still cover every requested post.' );
		$this->assertSame( 3, $result['total_pages'], 'Page counts should follow the explicit per_page.' );
	}

	/**
	 * The include list is capped at the maximum page size.
	 *
	 * @since 7.1.0
	 */
	public function test_query_include_is_capped_at_the_maximum_page_size(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$schema = wp_get_ability( 'core/read-content' )->get_input_schema();
		$query  = $schema['oneOf'][2];

		$this->assertSame( $query['properties']['per_page']['maximum'], $query['properties']['include']['maxItems'], 'The include list should be capped at the maximum page size.' );

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'include'   => range( 1, $query['properties']['include']['maxItems'] + 1 ),
			)
		);

		$this->assertWPError( $result, 'An include list beyond the cap should be rejected as invalid input.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The cap should be enforced by schema validation.' );
	}

	/**
	 * Requesting rendered fields primes the post meta cache for the whole page.
	 *
	 * The rendered filter chains may read post meta, so priming avoids one lazy meta
	 * query per returned row.
	 *
	 * @since 7.1.0
	 */
	public function test_query_rendered_fields_prime_the_post_meta_cache(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$postmeta_queries = $this->count_post_meta_queries(
			static function () use ( $ids ) {
				return wp_get_ability( 'core/read-content' )->execute(
					array(
						'post_type' => 'post',
						'include'   => $ids,
						'fields'    => array( 'id', 'content_rendered' ),
					)
				);
			},
			$result
		);

		$this->assertCount( 3, $result['posts'], 'Precondition: the query should return the seeded posts.' );

		/*
		 * The rendered filter chains can read post meta per post. Without priming, each
		 * row lazily primes its own meta, which is one query per returned post.
		 */
		$this->assertSame( 1, $postmeta_queries, 'Rendered field requests should prime post meta with a single batched query, not one per returned post.' );
	}

	/**
	 * A lean projection keeps skipping the post meta cache priming.
	 *
	 * Nothing in the default field set renders a post, so the extra lookup stays skipped.
	 *
	 * @since 7.1.0
	 */
	public function test_query_lean_projection_does_not_prime_the_post_meta_cache(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$postmeta_queries = $this->count_post_meta_queries(
			static function () use ( $ids ) {
				return wp_get_ability( 'core/read-content' )->execute(
					array(
						'post_type' => 'post',
						'include'   => $ids,
						'fields'    => array( 'id' ),
					)
				);
			},
			$result
		);

		$this->assertCount( 3, $result['posts'], 'Precondition: the query should return the seeded posts.' );
		$this->assertSame( 0, $postmeta_queries, 'A lean projection should not read post meta at all.' );

		foreach ( $ids as $id ) {
			$this->assertFalse( wp_cache_get( $id, 'post_meta' ), 'A lean projection should not prime the post meta cache.' );
		}
	}

	/**
	 * Counts the post meta queries issued while running the given callback.
	 *
	 * Counts during the call rather than checking the cache afterwards: the rendered
	 * filter chains prime meta lazily, so an after-the-fact cache check passes either way.
	 *
	 * @since 7.1.0
	 *
	 * @param callable $callback Callback to run.
	 * @param mixed    $result   Set to the callback's return value.
	 * @return int Number of post meta queries issued.
	 */
	private function count_post_meta_queries( callable $callback, &$result ): int {
		global $wpdb;

		$postmeta_queries = 0;
		$spy              = static function ( $query ) use ( &$postmeta_queries, $wpdb ) {
			if ( is_string( $query ) && preg_match( '/FROM\s+`?' . preg_quote( $wpdb->postmeta, '/' ) . '`?/i', $query ) ) {
				++$postmeta_queries;
			}

			return $query;
		};

		wp_cache_flush();
		add_filter( 'query', $spy );
		try {
			$result = $callback();
		} finally {
			remove_filter( 'query', $spy );
		}

		return $postmeta_queries;
	}

	/**
	 * Query rows are kept, not dropped, when the requested fields project to nothing.
	 *
	 * @since 7.1.0
	 */
	public function test_query_keeps_posts_with_empty_field_projection(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		// `parent` never applies to the non-hierarchical `post` type, so every row
		// projects to an empty object.
		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'post_type' => 'post',
				'per_page'  => 100,
				'fields'    => array( 'parent' ),
			)
		);

		$this->assertNotEmpty( $result['posts'], 'Posts with an empty field projection should still be returned.' );
		$this->assertSame( count( $result['posts'] ), $result['total'], 'The reported total should match the returned posts when projections are empty.' );

		foreach ( $result['posts'] as $post_entry ) {
			$this->assertEquals( (object) array(), $post_entry, 'A post whose requested fields do not apply should be returned as an empty object.' );
		}
	}

	/**
	 * A single post whose requested fields project to nothing is returned as an empty object.
	 *
	 * @since 7.1.0
	 */
	public function test_single_post_returns_empty_object_for_empty_field_projection(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		// `parent` never applies to the non-hierarchical `post` type, so the projection is empty.
		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => self::$post_ids['published'],
				'fields' => array( 'parent' ),
			)
		);

		$this->assertEquals( (object) array(), $result, 'An empty single-post field projection should be returned as an empty object so it serializes as `{}`.' );
	}

	/**
	 * A single post fetched by ID is returned directly without query totals.
	 *
	 * @since 7.1.0
	 */
	public function test_single_post_returns_direct_post_object(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published'];

		$result = wp_get_ability( 'core/read-content' )->execute( array( 'id' => $post_id ) );

		$this->assertSame( $post_id, $result['id'], 'Single-post responses should include the requested post ID.' );
		$this->assertArrayNotHasKey( 'posts', $result, 'Single-post responses should not include the query posts wrapper.' );
		$this->assertArrayNotHasKey( 'total', $result, 'Single-post responses should not include query totals.' );
		$this->assertArrayNotHasKey( 'total_pages', $result, 'Single-post responses should not include query page totals.' );
	}

	/**
	 * Local and GMT date fields report the correct instant and offset on non-UTC sites.
	 *
	 * @since 7.1.0
	 */
	public function test_gmt_dates_are_utc_on_non_utc_sites(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		update_option( 'timezone_string', 'America/New_York' );

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-01-15 10:00:00',
			)
		);

		/*
		 * On insert, `wp_insert_post()` copies the post date onto the modified columns. Give
		 * the modified columns their own instant, so a field that read the post date where it
		 * meant the modified date cannot pass.
		 */
		$this->replace_cached_post_date_columns(
			$post_id,
			array(
				'post_modified'     => '2026-01-16 11:00:00',
				'post_modified_gmt' => '2026-01-16 16:00:00',
			)
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'date', 'date_gmt', 'modified', 'modified_gmt' ),
			)
		);

		$this->assertSame( '2026-01-15T10:00:00-05:00', $result['date'], 'The local date should carry the site timezone offset.' );
		$this->assertSame( '2026-01-15T15:00:00+00:00', $result['date_gmt'], 'The GMT date should be the UTC instant with a UTC offset.' );
		$this->assertSame( '2026-01-16T11:00:00-05:00', $result['modified'], 'The local modified date should carry the site timezone offset.' );
		$this->assertSame( '2026-01-16T16:00:00+00:00', $result['modified_gmt'], 'The GMT modified date should be the UTC instant with a UTC offset.' );
	}

	/**
	 * Drafts without a stored GMT date derive it from the local date and the site timezone.
	 *
	 * @since 7.1.0
	 */
	public function test_gmt_date_is_derived_from_local_date_for_drafts(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		update_option( 'timezone_string', 'America/New_York' );

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_date'   => '2026-01-15 10:00:00',
			)
		);

		$this->assertSame(
			'0000-00-00 00:00:00',
			get_post( $post_id )->post_date_gmt,
			'Precondition: drafts should have no stored GMT date.'
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'date_gmt' ),
			)
		);

		$this->assertSame(
			'2026-01-15T15:00:00+00:00',
			$result['date_gmt'],
			'The GMT date should be derived from the local date using the site timezone, not read the local wall-clock as UTC.'
		);
	}

	/**
	 * Replaces cached post columns so the ability sees a post object with custom dates.
	 *
	 * Core's schema keeps the date columns `NOT NULL`, but a post object can still reach
	 * the ability from a filter or an in-memory row where a date is null or otherwise
	 * differs from the database row.
	 *
	 * @since 7.1.0
	 *
	 * @param int      $post_id The post ID.
	 * @param array<string, mixed> $columns Post column values keyed by column name.
	 */
	private function replace_cached_post_date_columns( int $post_id, array $columns ): void {
		get_post( $post_id );

		$cached = wp_cache_get( $post_id, 'posts' );
		$this->assertInstanceOf( \stdClass::class, $cached, 'Precondition: the raw post row should be cached.' );
		$this->assertSame( 'raw', $cached->filter, 'Precondition: the cached row should be unsanitized.' );

		foreach ( $columns as $column => $value ) {
			$cached->$column = $value;
		}

		wp_cache_set( $post_id, $cached, 'posts' );

		foreach ( $columns as $column => $value ) {
			$this->assertSame( $value, get_post( $post_id )->$column, "Precondition: {$column} should have the test value." );
		}
	}

	/**
	 * Data provider for GMT date fields.
	 *
	 * @since 7.1.0
	 *
	 * @return array<string, array{field: string, gmt_column: string, local_column: string, local_date: string, expected: string}>
	 */
	public function data_gmt_date_fields(): array {
		return array(
			'date_gmt'     => array(
				'field'        => 'date_gmt',
				'gmt_column'   => 'post_date_gmt',
				'local_column' => 'post_date',
				'local_date'   => '2026-01-15 10:00:00',
				'expected'     => '2026-01-15T15:00:00+00:00',
			),
			'modified_gmt' => array(
				'field'        => 'modified_gmt',
				'gmt_column'   => 'post_modified_gmt',
				'local_column' => 'post_modified',
				'local_date'   => '2026-01-16 11:00:00',
				'expected'     => '2026-01-16T16:00:00+00:00',
			),
		);
	}

	/**
	 * A null stored GMT date falls back to the local date instead of the current time.
	 *
	 * `strtotime( ' UTC' )` resolves to the current time, so an unguarded null would
	 * report a fabricated "now" as the publication date.
	 *
	 * @since 7.1.0
	 *
	 * @dataProvider data_gmt_date_fields
	 *
	 * @param string $field        The ability output field to request.
	 * @param string $gmt_column   The cached GMT post column to null out.
	 * @param string $local_column The cached local post column to derive the GMT date from.
	 * @param string $local_date   The local date column value.
	 * @param string $expected     The expected GMT output.
	 */
	public function test_gmt_date_recovers_from_a_null_stored_gmt_date( string $field, string $gmt_column, string $local_column, string $local_date, string $expected ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		update_option( 'timezone_string', 'America/New_York' );

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-01-15 10:00:00',
			)
		);

		$this->replace_cached_post_date_columns(
			$post_id,
			array(
				$gmt_column   => null,
				$local_column => $local_date,
			)
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', $field ),
			)
		);

		$this->assertSame(
			$expected,
			$result[ $field ],
			'A null stored GMT date should be derived from the local date, not resolved to the current time.'
		);
	}

	/**
	 * A post with no usable date at all reports the documented empty-string sentinel.
	 *
	 * @since 7.1.0
	 *
	 * @dataProvider data_gmt_date_fields
	 *
	 * @param string $field        The ability output field to request.
	 * @param string $gmt_column   The cached GMT post column to null out.
	 * @param string $local_column The cached local post column to null out.
	 * @param string $local_date   Unused. Present to match the shared data provider shape.
	 * @param string $expected     Unused. Present to match the shared data provider shape.
	 */
	public function test_gmt_date_is_empty_when_no_usable_date_exists( string $field, string $gmt_column, string $local_column, string $local_date, string $expected ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->replace_cached_post_date_columns(
			$post_id,
			array(
				$gmt_column   => null,
				$local_column => null,
			)
		);

		$result = wp_get_ability( 'core/read-content' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', $field ),
			)
		);

		$this->assertSame( '', $result[ $field ], 'An unresolvable GMT date should be the empty-string sentinel.' );
	}

	/**
	 * The execute callback re-validates the lookup structurally when invoked directly.
	 *
	 * Gated transports never reach these branches: check_permission() resolves and
	 * denies the same lookups first. The registered callback still fails closed on
	 * structural lookup errors when invoked directly.
	 *
	 * @since 7.1.0
	 */
	public function test_execute_callback_returns_not_found_for_structural_lookup_failures(): void {
		$this->login_as( 'administrator' );

		$content = new WP_Content_Abilities();

		$missing = $content->execute_read_content( array( 'id' => 999999 ) );
		$this->assertWPError( $missing, 'A nonexistent post ID should fail the lookup.' );
		$this->assertSame( 'content_not_found', $missing->get_error_code(), 'Missing posts should map to the uniform not-found error.' );

		$mismatched = $content->execute_read_content(
			array(
				'id'        => self::$post_ids['published'],
				'post_type' => 'page',
			)
		);
		$this->assertWPError( $mismatched, 'A post type mismatch should fail the lookup.' );
		$this->assertSame( 'content_not_found', $mismatched->get_error_code(), 'Mismatched post types should map to the uniform not-found error.' );

		$missing_slug = $content->execute_read_content(
			array(
				'post_type' => 'post',
				'slug'      => 'no-such-slug',
			)
		);
		$this->assertWPError( $missing_slug, 'An unmatched slug should fail the lookup.' );
		$this->assertSame( 'content_not_found', $missing_slug->get_error_code(), 'Unmatched slugs should map to the uniform not-found error.' );
	}

	/**
	 * An author filter that does not resolve to a positive integer is rejected
	 * rather than silently dropped.
	 *
	 * On transports that skip schema validation a non-integer `author` would coerce
	 * to 0, which WP_Query treats as "no author filter" — returning every author's
	 * posts. The filter must fail closed instead of widening the result set.
	 *
	 * @since 7.1.0
	 */
	public function test_execute_callback_rejects_non_integer_author_filter(): void {
		$this->login_as( 'administrator' );
		$content = new WP_Content_Abilities();

		$result = $content->execute_read_content(
			array(
				'post_type' => 'post',
				'author'    => 'not-a-number',
			)
		);

		$this->assertWPError( $result, 'A non-integer author filter must not silently widen the query to all authors.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An unhonorable author filter should fail closed as an invalid filter.' );
	}

	/**
	 * A parent filter that is not a non-negative integer is rejected rather than
	 * coerced to 0 (top-level).
	 *
	 * Because 0 is a legitimate parent value (top-level posts), a non-integer value
	 * cannot be detected by a numeric bound; it must be rejected on the raw value so
	 * garbage does not silently become a top-level query.
	 *
	 * @since 7.1.0
	 */
	public function test_execute_callback_rejects_non_integer_parent_filter(): void {
		$this->login_as( 'administrator' );
		$content = new WP_Content_Abilities();

		$result = $content->execute_read_content(
			array(
				'post_type' => 'page',
				'parent'    => 'not-a-number',
			)
		);

		$this->assertWPError( $result, 'A non-integer parent filter must not silently coerce to a top-level (0) query.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An unhonorable parent filter should fail closed as an invalid filter.' );
	}

	/**
	 * An include filter that parses to no valid IDs is rejected rather than
	 * returning an unrestricted result set.
	 *
	 * WP_Query ignores an empty `post__in`, so an include list with no valid IDs
	 * would otherwise return every post of the type — the opposite of the caller's
	 * intent. The filter must fail closed instead.
	 *
	 * @since 7.1.0
	 */
	public function test_execute_callback_rejects_include_with_no_valid_ids(): void {
		$this->login_as( 'administrator' );

		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$content = new WP_Content_Abilities();

		$result = $content->execute_read_content(
			array(
				'post_type' => 'post',
				'include'   => array( 0 ),
			)
		);

		$this->assertWPError( $result, 'An include filter with no valid IDs must not fall through to an unrestricted query.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An empty-after-parsing include should fail closed as an invalid filter.' );
	}

	/**
	 * Valid filter values delivered as strings are still honored.
	 *
	 * The schema-less query-string transport delivers integers as strings; the
	 * stricter filter parsing must accept those so it only rejects genuinely
	 * unhonorable values, not well-formed ones.
	 *
	 * @since 7.1.0
	 */
	public function test_execute_callback_honors_string_author_filter(): void {
		$author_a = self::$user_ids['author'];
		$author_b = self::$user_ids['author_secondary'];

		$post_a = self::factory()->post->create(
			array(
				'post_author' => $author_a,
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $author_b,
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );
		$content = new WP_Content_Abilities();

		$result = $content->execute_read_content(
			array(
				'post_type' => 'post',
				'author'    => (string) $author_a,
				'fields'    => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'A valid numeric-string author filter should be honored, not rejected.' );
		$this->assertSame( array( $post_a ), wp_list_pluck( $result['posts'], 'id' ), 'The author filter should restrict results to the requested author.' );
	}
}
