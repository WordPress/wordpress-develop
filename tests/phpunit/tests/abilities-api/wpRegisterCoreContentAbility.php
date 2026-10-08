<?php

declare( strict_types=1 );

/**
 * Tests for the core/content-query ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Content
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreContentAbility extends WP_UnitTestCase {

	/**
	 * Globals that rendering a post changes: the global post and the globals that
	 * setup_postdata() populates.
	 *
	 * @since 7.2.0
	 *
	 * @var list<string>
	 */
	private const LOOP_GLOBALS = array(
		'post',
		'id',
		'authordata',
		'currentday',
		'currentmonth',
		'page',
		'pages',
		'multipage',
		'more',
		'numpages',
	);

	/**
	 * A post ID that tests create a post with through `import_id`, so data providers can
	 * write the ID in other forms.
	 *
	 * @since 7.2.0
	 *
	 * @var int
	 */
	private const IMPORTED_POST_ID = 1234567;

	/**
	 * Shared user IDs keyed by role or fixture name.
	 *
	 * @since 7.2.0
	 *
	 * @var array<string, int>
	 */
	private static $user_ids = array();

	/**
	 * Shared post IDs keyed by fixture name.
	 *
	 * @since 7.2.0
	 *
	 * @var array<string, int>
	 */
	private static $post_ids = array();

	/**
	 * Creates shared users and posts for the content ability tests.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
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
			'published'            => $factory->post->create( array( 'post_status' => 'publish' ) ),
			'published_content'    => $factory->post->create(
				array(
					'post_title'   => 'Hello Content',
					'post_content' => 'Body here.',
					'post_status'  => 'publish',
				)
			),
			'limited_role_content' => $factory->post->create(
				array(
					'post_author'  => self::$user_ids['administrator'],
					'post_title'   => 'Readable title',
					'post_content' => 'Readable body for limited role.',
					'post_excerpt' => 'Readable excerpt.',
					'post_status'  => 'publish',
				)
			),
			'password_protected'   => $factory->post->create(
				array(
					'post_author'   => self::$user_ids['administrator'],
					'post_status'   => 'publish',
					'post_password' => 'secret',
					'post_content'  => 'Top secret body.',
				)
			),
		);
	}

	/**
	 * Sets up the content ability category for each test.
	 *
	 * @since 7.2.0
	 */
	public function set_up(): void {
		parent::set_up();

		if ( wp_has_ability( 'core/content-query' ) ) {
			wp_unregister_ability( 'core/content-query' );
		}

		$this->ensure_ability_category( 'content' );
	}

	/**
	 * Removes the ability and its category, and resets the post types, after each test.
	 *
	 * @since 7.2.0
	 */
	public function tear_down(): void {
		if ( wp_has_ability( 'core/content-query' ) ) {
			wp_unregister_ability( 'core/content-query' );
		}

		if ( wp_has_ability_category( 'content' ) ) {
			wp_unregister_ability_category( 'content' );
		}

		/*
		 * Keep this reset even though the parent set_up() also resets the post types. A test
		 * may turn off `show_in_abilities` on `post` and `page`, and the next class's
		 * wpSetUpBeforeClass() runs before the next set_up(). A class that registers the core
		 * abilities there, such as Tests_REST_API_WpRestAbilitiesContentController, would then
		 * register no content ability, and all of its tests would fail.
		 */
		$this->reset_post_types();

		parent::tear_down();
	}

	/**
	 * Ensures an ability category exists for an ability to attach to.
	 *
	 * @since 7.2.0
	 *
	 * @param string $slug The ability category slug.
	 */
	private function ensure_ability_category( string $slug ): void {
		if ( wp_has_ability_category( $slug ) ) {
			return;
		}

		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_categories_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		wp_register_ability_category(
			$slug,
			array(
				'label'       => ucfirst( $slug ),
				'description' => ucfirst( $slug ) . '.',
			)
		);
	}

	/**
	 * Registers the core/content-query ability inside a faked init action.
	 *
	 * @since 7.2.0
	 */
	private function register_ability(): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		( new WP_Abilities_Content() )->register();
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_registers_core_content_query_ability(): void {
		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );

		$this->assertNotNull( $ability, 'The core/content-query ability should be registered.' );
		$this->assertSame( 'core/content-query', $ability->get_name(), 'The registered ability should use the expected name.' );
		$this->assertSame( 'content', $ability->get_category(), 'The registered ability should use the content category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The ability should be marked public.' );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ), 'The ability should be exposed in REST.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'], 'The ability should be marked read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The ability should be marked non-destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'The ability should be marked idempotent.' );
	}

	/**
	 * The content ability is not registered when no post types are exposed to it.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_does_not_register_core_content_query_ability_without_exposed_post_types(): void {
		foreach ( array( 'post', 'page' ) as $post_type ) {
			$object = get_post_type_object( $post_type );
			$this->assertInstanceOf( WP_Post_Type::class, $object, "Precondition: the {$post_type} post type should exist." );

			$object->show_in_abilities = false;
		}

		$this->register_ability();

		$this->assertFalse( wp_has_ability( 'core/content-query' ), 'The content ability should not register without any exposed post types.' );
	}

	/**
	 * The input schema models mutually exclusive ID, slug, and query modes, each
	 * rejecting the other modes' properties and exposing only marked types.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_input_schema_models_mutually_exclusive_modes(): void {
		$this->register_ability();

		$schema = wp_get_ability( 'core/content-query' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'], 'The input schema should describe an object.' );
		$this->assertCount( 3, $schema['oneOf'], 'The input schema should expose exactly three modes.' );

		[ $by_id, $by_slug, $query ] = $schema['oneOf'];

		// All modes reject properties from the other modes.
		$this->assertSame( array( 'id' ), $by_id['required'], 'The by-ID mode should require an ID.' );
		$this->assertSame( array( 'type', 'slug' ), $by_slug['required'], 'The slug mode should require post type and slug.' );
		$this->assertSame( array( 'type' ), $query['required'], 'The query mode should require a post type.' );
		$this->assertFalse( $by_id['additionalProperties'], 'The by-ID mode should reject unrelated properties.' );
		$this->assertFalse( $by_slug['additionalProperties'], 'The slug mode should reject unrelated properties.' );
		$this->assertFalse( $query['additionalProperties'], 'The query mode should reject unrelated properties.' );

		// Query-only filters live only in the query mode, not the single-post modes.
		$this->assertArrayHasKey( 'include', $query['properties'], 'The query mode should support included post IDs.' );
		$this->assertArrayHasKey( 'per_page', $query['properties'], 'The query mode should support pagination.' );
		$this->assertArrayNotHasKey( 'per_page', $by_id['properties'], 'The by-ID mode should not accept query-only pagination.' );
		$this->assertArrayNotHasKey( 'include', $by_slug['properties'], 'The slug mode should not accept query-only included IDs.' );
		$this->assertArrayNotHasKey( 'slug', $query['properties'], 'The query mode should not accept slug; slug is a single-post mode.' );

		// Exposed post types appear in all modes that accept `type`.
		$this->assertContains( 'post', $query['properties']['type']['enum'], 'The query mode should include exposed posts.' );
		$this->assertContains( 'page', $by_id['properties']['type']['enum'], 'The by-ID guard should include exposed pages.' );
		$this->assertContains( 'page', $by_slug['properties']['type']['enum'], 'The slug mode should include exposed pages.' );

		$this->assertSame( 1, $query['properties']['include']['minItems'], 'The include option should require at least one post ID.' );
		$this->assertTrue( $query['properties']['include']['uniqueItems'], 'The include option should reject duplicate post IDs.' );
		$this->assertSame( 'integer', $query['properties']['include']['items']['type'], 'The include option should contain post IDs.' );
		$this->assertSame( 1, $query['properties']['include']['items']['minimum'], 'The include option should contain positive post IDs.' );

		$fields_enum = $query['properties']['fields']['items']['enum'];
		$this->assertContains( 'type', $fields_enum, 'The fields enum should expose the post type as type.' );
		$this->assertNotContains( 'post_type', $fields_enum, 'The fields enum should not expose the post type as post_type.' );
		$this->assertContains( 'content_raw', $fields_enum, 'The fields enum should include raw content.' );
		$this->assertContains( 'content_rendered', $fields_enum, 'The fields enum should include rendered content.' );
		$this->assertContains( 'title_raw', $fields_enum, 'The fields enum should include raw titles.' );
		$this->assertContains( 'title_rendered', $fields_enum, 'The fields enum should include rendered titles.' );
	}

	/**
	 * Branch-local defaults are omitted so the schema can compile in the client-side
	 * Abilities API validator. Runtime defaults are still applied by the ability.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_input_schema_omits_oneof_branch_defaults(): void {
		$this->register_ability();

		$schema = wp_get_ability( 'core/content-query' )->get_input_schema();
		$query  = $schema['oneOf'][2];

		$this->assertArrayNotHasKey( 'default', $query['properties']['status'], 'Status should rely on runtime defaults, not schema defaults.' );
		$this->assertArrayNotHasKey( 'default', $query['properties']['page'], 'Page should rely on runtime defaults, not schema defaults.' );
		$this->assertArrayNotHasKey( 'default', $query['properties']['per_page'], 'Per-page should rely on runtime defaults, not schema defaults.' );
	}

	/**
	 * Query-mode filters cannot be combined with a by-ID lookup: passing `per_page` alongside
	 * `id` is rejected outright rather than silently ignored.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_id_mode_rejects_query_only_params(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'       => 1,
				'per_page' => 10,
			)
		);

		$this->assertWPError( $result, 'Combining by-ID mode with query-only params should fail validation.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Invalid mode combinations should return an input error.' );
	}

	/**
	 * `type` is accepted alongside `id` as a guard: the by-ID mode still resolves the post.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_id_mode_accepts_post_type_guard(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published'];

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'   => $post_id,
				'type' => 'post',
			)
		);

		$this->assertIsArray( $result, 'A matching post type guard should allow the by-ID lookup.' );
		$this->assertSame( $post_id, $result['id'], 'The guarded by-ID lookup should return the requested post directly.' );
		$this->assertArrayNotHasKey( 'posts', $result, 'The guarded by-ID lookup should not return the query wrapper.' );
	}

	/**
	 * The output schema describes single-post and query response shapes.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_output_schema_describes_single_post_and_query_responses(): void {
		$this->register_ability();

		$ability      = wp_get_ability( 'core/content-query' );
		$input_schema = $ability->get_input_schema();
		$schema       = $ability->get_output_schema();
		$post_schema  = $schema['oneOf'][0];
		$query_schema = $schema['oneOf'][1];

		$this->assertSame( 'object', $schema['type'], 'The output schema should describe object responses.' );
		$this->assertCount( 2, $schema['oneOf'], 'The output schema should describe single-post and query responses.' );
		$this->assertSame( 'object', $post_schema['type'], 'The single-post response should be described as an object.' );
		$this->assertArrayNotHasKey( 'required', $post_schema, 'Individual post fields should remain optional.' );
		$this->assertFalse( $post_schema['additionalProperties'], 'Returned posts should not allow unknown properties.' );
		$this->assertArrayHasKey( 'type', $post_schema['properties'], 'The post schema should describe the post type as type.' );
		$this->assertArrayNotHasKey( 'post_type', $post_schema['properties'], 'The post schema should not expose the post type as post_type.' );
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_exposes_a_post_type_registered_by_another_plugin(): void {
		register_post_type(
			'content_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor' ),
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		// Query mode is the third `oneOf` branch; its `type` enum lists exposed types.
		$enum = wp_get_ability( 'core/content-query' )->get_input_schema()['oneOf'][2]['properties']['type']['enum'];
		$this->assertContains( 'content_cpt', $enum, 'Custom post types marked show_in_abilities should appear in the query enum.' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'content_cpt',
				'post_status' => 'publish',
			)
		);

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'type' => 'content_cpt' ) );
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $post_id, $ids, 'The custom post type should be queryable through the content ability.' );
	}

	/**
	 * Returns `show_in_abilities` values other than `true`.
	 *
	 * @return array<string, array{value: mixed}> Non-boolean `show_in_abilities` test cases.
	 */
	public function data_show_in_abilities_values_other_than_true(): array {
		return array(
			'array of operations' => array(
				'value' => array( 'create' ),
			),
			'string "false"'      => array(
				'value' => 'false',
			),
			'integer 1'           => array(
				'value' => 1,
			),
		);
	}

	/**
	 * Only a `show_in_abilities` value of `true` exposes a post type, so other values, such as
	 * arrays, can be given a meaning later.
	 *
	 * @ticket 66268
	 * @dataProvider data_show_in_abilities_values_other_than_true
	 * @since 7.2.0
	 *
	 * @param mixed $value The `show_in_abilities` value to register the post type with.
	 */
	public function test_does_not_expose_a_post_type_with_a_show_in_abilities_value_other_than_true( $value ): void {
		register_post_type(
			'content_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => $value,
			)
		);

		$this->assertFalse( get_post_type_object( 'content_cpt' )->show_in_abilities, 'The post type object should hold false.' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'content_cpt',
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		// Query mode is the third `oneOf` branch; its `type` enum lists exposed types.
		$enum = wp_get_ability( 'core/content-query' )->get_input_schema()['oneOf'][2]['properties']['type']['enum'];
		$this->assertNotContains( 'content_cpt', $enum, 'The post type should not appear in the query enum.' );

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => $post_id ) );
		$this->assertWPError( $result, 'A post of the post type should not be readable by ID.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'The by-ID lookup should be denied.' );
	}

	/**
	 * A schema filter can expose a post type that is registered after the ability.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_schema_filter_exposes_late_registered_post_type(): void {
		$this->login_as( 'administrator' );

		$amend_schema = static function ( array $args, string $name ): array {
			if ( 'core/content-query' !== $name ) {
				return $args;
			}

			foreach ( $args['input_schema']['oneOf'] as $index => $mode ) {
				$args['input_schema']['oneOf'][ $index ]['properties']['type']['enum'][] = 'late_cpt';
			}

			return $args;
		};
		add_filter( 'wp_register_ability_args', $amend_schema, 10, 2 );
		$this->register_ability();

		$enum = wp_get_ability( 'core/content-query' )->get_input_schema()['oneOf'][2]['properties']['type']['enum'];
		$this->assertContains( 'late_cpt', $enum, 'The ability args filter should amend the frozen schema enum.' );

		register_post_type(
			'late_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor' ),
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'late_cpt',
				'post_status' => 'publish',
			)
		);

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'late_cpt',
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['posts'], 'id' ), 'The late post type should be queryable after it becomes exposed.' );
	}

	/**
	 * A published post can be fetched by ID.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_get_single_published_post_by_id(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => $post_id ) );

		$this->assertIsArray( $result, 'The by-ID lookup should return a post array.' );
		$this->assertSame( $post_id, $result['id'], 'The by-ID lookup should return the requested post directly.' );
		$this->assertSame( 'Hello Content', $result['title_rendered'], 'Rendered titles should be returned by default.' );
		$this->assertSame(
			array( 'id', 'type', 'status', 'date', 'slug', 'title_rendered' ),
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_get_single_published_post_by_id_accepts_object_input(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];
		$ability = wp_get_ability( 'core/content-query' );
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_get_single_published_post_by_id_can_return_content_fields(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'type', 'content_rendered', 'content_raw' ),
			)
		);

		$this->assertSame( $post_id, $result['id'], 'The by-ID lookup should return the requested post.' );
		$this->assertSame( 'post', $result['type'], 'The by-ID lookup should return the post type as type.' );
		$this->assertStringContainsString( 'Body here.', $result['content_rendered'], 'Explicit content fields should include rendered content.' );
		$this->assertSame( 'Body here.', $result['content_raw'], 'Explicit content fields should include raw content.' );
		$this->assertArrayNotHasKey( 'posts', $result, 'The by-ID lookup should not return the query wrapper.' );
	}

	/**
	 * A missing post ID is denied before execution can probe the requested object.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_get_by_missing_id_is_denied(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );

		$this->assertWPError( $result, 'Missing posts should be denied before execution probes object details.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Missing posts should fail closed as a permission error.' );
	}

	/**
	 * An ID beyond the integer range is denied instead of wrapping around onto another post.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_get_by_id_beyond_the_integer_range_is_denied(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		// Floats near 2^64 are 4096 apart, so 2^64 + N is exact for a multiple of 4096 and casts to N.
		$aliased_id = self::factory()->post->create(
			array(
				'import_id'   => 4096 * 1024,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 4096 * 1024, $aliased_id, 'The aliased post should have the requested ID.' );

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => 2 ** 64 + $aliased_id ) );

		$this->assertWPError( $result, 'An ID beyond the integer range should not resolve a post.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'An ID beyond the integer range should fail closed as a permission error.' );
	}

	/**
	 * A post type guard mismatch is denied before execution can probe the requested object.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_get_by_id_with_mismatched_post_type_is_denied(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published'];

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'   => $post_id,
				'type' => 'page',
			)
		);

		$this->assertWPError( $result, 'Mismatched post type guards should deny the lookup.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Mismatched post type guards should fail closed as a permission error.' );
	}

	/**
	 * A post from a post type not exposed to abilities is denied.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_get_by_id_for_unexposed_post_type_is_denied(): void {
		register_post_type(
			'hidden_cpt',
			array(
				'public'       => true,
				'show_in_rest' => false,
				'supports'     => array( 'title', 'editor' ),
			)
		);

		$this->login_as( 'administrator' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'hidden_cpt',
				'post_status' => 'publish',
			)
		);
		$this->assertGreaterThan( 0, $post_id, 'The hidden custom post should be created for the denial check.' );

		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => $post_id ) );

		$this->assertWPError( $result, 'Posts from unexposed post types should be denied.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Unexposed post types should fail closed as a permission error.' );
	}

	/**
	 * A status that is public but not viewable is not exposed to read-only users.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_public_non_viewable_status_is_denied_for_read_only_users(): void {
		register_post_status(
			'public_hidden',
			array(
				'label'              => 'Public hidden',
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'public_hidden',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => $post_id ) );

		$this->assertWPError( $result, 'Read-only users should not receive public statuses that are not viewable.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Non-viewable public statuses should fail closed for read-only users.' );
	}

	/**
	 * A status that is public but not viewable remains available to users who can edit it.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_public_non_viewable_status_is_readable_with_edit_access(): void {
		register_post_status(
			'public_hidden',
			array(
				'label'              => 'Public hidden',
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Hidden public status',
				'post_status' => 'public_hidden',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => $post_id ) );

		$this->assertIsArray( $result, 'Editors should be able to access posts they can edit even when the status is not publicly viewable.' );
		$this->assertSame( $post_id, $result['id'], 'The editable post should be returned.' );
		$this->assertSame( 'Hidden public status', $result['title_rendered'], 'The editable post should include normal default fields.' );
	}

	/**
	 * A post that inherits its status from a readable parent is readable.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_inherited_post_is_readable_when_parent_is_readable(): void {
		register_post_type(
			'inherit_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor' ),
			)
		);

		$parent_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'inherit_cpt',
				'post_status' => 'publish',
			)
		);
		$child_id  = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'inherit_cpt',
				'post_parent' => $parent_id,
				'post_status' => 'inherit',
				'post_title'  => 'Inherited child',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => $child_id ) );

		$this->assertIsArray( $result, 'Inherited posts should be readable when their parent is readable.' );
		$this->assertSame( $child_id, $result['id'], 'The inherited child should be returned.' );
		$this->assertSame( 'Inherited child', $result['title_rendered'], 'The inherited child should include normal default fields.' );
	}

	/**
	 * A post with an inherited status but no readable parent is denied.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_inherited_post_without_parent_is_denied_for_read_only_users(): void {
		register_post_type(
			'inherit_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor' ),
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'inherit_cpt',
				'post_status' => 'inherit',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => $post_id ) );

		$this->assertWPError( $result, 'Inherited posts without a readable parent should be denied.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Orphaned inherited posts should fail closed.' );
	}

	/**
	 * Query mode returns only published posts by default.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_returns_only_published_by_default(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$published = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'type' => 'post' ) );
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $published, $ids, 'Published posts should be returned by default.' );
		$this->assertNotContains( $draft, $ids, 'Draft posts should not be returned by default.' );
	}

	/**
	 * Query results are ordered by post date, newest first, whatever order `include` uses.
	 *
	 * Pins both halves of what the schema advertises. The ability leaves `orderby` at the
	 * WP_Query default, matching the REST posts controller, and `include` only filters the
	 * query, so a caller that passes IDs in a chosen order must not expect them back in it.
	 *
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				// Deliberately neither date order nor ID order.
				'include' => array( $middle, $newest, $oldest ),
				'fields'  => array( 'id' ),
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
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				'include' => array( $page_id, $post_id ),
				'fields'  => array( 'id' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['posts'], 'id' ), 'Include should not leak posts from other post types.' );
	}

	/**
	 * Query include accepts a single post ID, as the input schema does.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_include_accepts_a_single_post_id(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published'];

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				'include' => $post_id,
				'fields'  => array( 'id' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['posts'], 'id' ), 'A single included post ID should limit the query to that post.' );
	}

	/**
	 * Query include still respects row-level permissions.
	 *
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				'status'  => array( 'draft' ),
				'include' => array( $draft_a, $draft_b ),
				'fields'  => array( 'id' ),
			)
		);

		$this->assertSame( array( $draft_b ), wp_list_pluck( $result['posts'], 'id' ), 'Include should not bypass row-level draft permissions.' );
	}

	/**
	 * Query mode can return included drafts with explicitly requested rendered and raw content.
	 *
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				'status'  => array( 'draft' ),
				'include' => array( $draft ),
				'fields'  => array( 'id', 'type', 'status', 'content_rendered', 'content_raw' ),
			)
		);

		$this->assertSame( array( $draft ), wp_list_pluck( $result['posts'], 'id' ), 'The draft query should return only the included draft.' );
		$this->assertSame( 'post', $result['posts'][0]['type'], 'Query responses should return the post type as type.' );
		$this->assertSame( 'draft', $result['posts'][0]['status'], 'The draft query should expose the requested draft status.' );
		$this->assertStringContainsString( 'Draft body for content fields.', $result['posts'][0]['content_rendered'], 'Draft query results should include rendered content when requested.' );
		$this->assertSame( 'Draft body for content fields.', $result['posts'][0]['content_raw'], 'Draft query results should include raw content when requested.' );
	}

	/**
	 * Querying by slug without a post type is rejected by the input schema.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_slug_mode_requires_post_type(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'slug' => 'whatever' ) );

		$this->assertWPError( $result, 'Slug queries without a post type should fail validation.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Invalid slug queries should return an input error.' );
	}

	/**
	 * Slug mode returns a single post directly when paired with a post type.
	 *
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type' => 'post',
				'slug' => 'content-slug-mode',
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
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'post',
				'slug'   => 'content-slug-fields',
				'fields' => array( 'id', 'type', 'slug', 'content_rendered', 'content_raw' ),
			)
		);

		$this->assertSame( $post_id, $result['id'], 'The slug lookup should return the requested post.' );
		$this->assertSame( 'post', $result['type'], 'The slug lookup should return the post type as type.' );
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_slug_lookup_is_not_bounded_by_a_page_size(): void {
		global $wpdb;

		/*
		 * Author every post as the administrator, so the subscriber who reads them below can
		 * only see the published one.
		 */
		$this->login_as( 'administrator' );

		// The published post owns the slug and is the oldest of the group.
		$published = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'slug-not-bounded',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);

		/*
		 * Drafts skip slug uniqueness, so they can all share the slug. Create more of them
		 * than the largest page the ability will ever return.
		 */
		for ( $i = 0; $i < 110; $i++ ) {
			self::factory()->post->create(
				array(
					'post_status' => 'draft',
					'post_name'   => 'slug-not-bounded',
					'post_date'   => '2026-03-01 10:00:00',
				)
			);
		}

		$sharing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s", 'slug-not-bounded' ) ); // phpcs:ignore WordPress.DB
		$this->assertGreaterThan( 100, $sharing, 'Precondition: more posts share the slug than a single page holds.' );

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'post',
				'slug'   => 'slug-not-bounded',
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The published post should resolve even though the drafts fill more than a page.' );
		$this->assertSame( $published, $result['id'], 'The readable published post should still resolve.' );
	}

	/**
	 * A post whose slug is the literal string "0" is fetched in single-post slug mode.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_get_single_post_by_slug_zero(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		/*
		 * Core regenerates an "empty" post_name from the title, so a post titled "0"
		 * ends up with the literal slug "0".
		 */
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => '0',
				'post_name'   => '0',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( '0', get_post( $post_id )->post_name, 'Precondition: the post slug should be the literal string "0".' );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type' => 'post',
				'slug' => '0',
			)
		);

		$this->assertIsArray( $result, 'The slug lookup should return a post array.' );
		$this->assertSame( $post_id, $result['id'], 'Slug mode should resolve the literal "0" slug to the post.' );
		$this->assertArrayNotHasKey( 'posts', $result, 'A "0" slug should not fall through to the query wrapper.' );
	}

	/**
	 * Query-only filters cannot be combined with slug mode.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_slug_mode_rejects_query_only_params(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'     => 'post',
				'slug'     => 'content-slug-mode',
				'per_page' => 10,
			)
		);

		$this->assertWPError( $result, 'Combining slug mode with query-only params should fail validation.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Invalid slug mode combinations should return an input error.' );
	}

	/**
	 * Returns a role that cannot read another user's draft and a role that can.
	 *
	 * @return array<string, array{role: string}> Role test cases.
	 */
	public function data_roles_with_and_without_access_to_others_drafts(): array {
		return array(
			'subscriber' => array(
				'role' => 'subscriber',
			),
			'editor'     => array(
				'role' => 'editor',
			),
		);
	}

	/**
	 * A newer draft sharing a published post's slug does not shadow the published post.
	 *
	 * @ticket 66268
	 * @dataProvider data_roles_with_and_without_access_to_others_drafts
	 * @since 7.2.0
	 *
	 * @param string $role The role to look up the slug as.
	 */
	public function test_slug_lookup_prefers_published_post_over_newer_draft( string $role ): void {
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
		$this->login_as( $role );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type' => 'post',
				'slug' => 'shadowed-slug',
			)
		);

		$this->assertIsArray( $result, 'The slug lookup should succeed.' );
		$this->assertSame( $published_id, $result['id'], 'The slug lookup should resolve to the published post.' );
	}

	/**
	 * A slug held only by a draft resolves for its author and stays denied for readers.
	 *
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type' => 'post',
				'slug' => 'draft-only-slug',
			)
		);

		$this->assertIsArray( $result, 'The draft author should resolve their own draft by slug.' );
		$this->assertSame( $draft_id, $result['id'], 'The draft author should receive their own draft.' );

		$this->login_as( 'subscriber' );

		$denied = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type' => 'post',
				'slug' => 'draft-only-slug',
			)
		);

		$this->assertWPError( $denied, 'Readers should not resolve a slug held only by an unreadable draft.' );
		$this->assertSame( 'ability_invalid_permissions', $denied->get_error_code(), 'Unreadable slug lookups should fail closed as a permission error.' );
	}

	/**
	 * Returns field sets that a request is checked against with read or with edit access.
	 *
	 * @return array<string, array{fields: list<string>}> Field set test cases.
	 */
	public function data_read_and_edit_fields(): array {
		return array(
			'read fields' => array(
				'fields' => array( 'id' ),
			),
			'edit fields' => array(
				'fields' => array( 'id', 'content_raw' ),
			),
		);
	}

	/**
	 * A slug lookup resolves only posts of the requested post type, also when a query filter
	 * adds post types to single views.
	 *
	 * WP_Query treats the lookup's `name` query as a single view, so a `pre_get_posts`
	 * callback without an is_main_query() check also runs for it. The newest post sharing
	 * the slug would otherwise win, even from a post type that is not exposed.
	 *
	 * @ticket 66268
	 * @dataProvider data_read_and_edit_fields
	 * @since 7.2.0
	 *
	 * @param list<string> $fields The fields to request.
	 */
	public function test_slug_lookup_skips_posts_of_other_post_types( array $fields ): void {
		register_post_type(
			'hidden_cpt',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor' ),
			)
		);

		$post_id   = self::factory()->post->create(
			array(
				'post_name'   => 'shared-slug',
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$other_ids = array(
			self::factory()->post->create(
				array(
					'post_type'   => 'page',
					'post_name'   => 'shared-slug',
					'post_status' => 'publish',
					'post_date'   => '2026-03-01 10:00:00',
				)
			),
			self::factory()->post->create(
				array(
					'post_type'   => 'hidden_cpt',
					'post_name'   => 'shared-slug',
					'post_status' => 'publish',
					'post_date'   => '2026-06-01 10:00:00',
				)
			),
		);

		foreach ( $other_ids as $other_id ) {
			$this->assertSame( 'shared-slug', get_post( $other_id )->post_name, 'Precondition: newer posts of other types should share the slug.' );
		}

		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( $query->is_single() ) {
					$query->set( 'post_type', array( 'post', 'page', 'hidden_cpt' ) );
				}
			}
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'post',
				'slug'   => 'shared-slug',
				'fields' => $fields,
			)
		);

		$this->assertIsArray( $result, 'The slug lookup should succeed.' );
		$this->assertSame( $post_id, $result['id'], 'The slug lookup should resolve to the post of the requested type.' );
	}

	/**
	 * Include is a query-only option and cannot be combined with single-post modes.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_include_cannot_be_combined_with_single_post_modes(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$by_id   = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'      => self::$post_ids['published'],
				'include' => array( self::$post_ids['published'] ),
			)
		);
		$by_slug = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				'slug'    => 'whatever',
				'include' => array( self::$post_ids['published'] ),
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_fields_filter_limits_returned_keys(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * The `id` is returned even when the requested fields leave it out.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_fields_filter_always_includes_the_id(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'title_rendered' ),
			)
		);

		$this->assertSame( array( 'id', 'title_rendered' ), array_keys( $result ), 'The ID should be returned along with the requested fields.' );
		$this->assertSame( $post_id, $result['id'], 'The returned ID should be the requested post.' );
	}

	/**
	 * An unknown requested field name fails schema validation.
	 *
	 * Unlike fields a post type does not support, which are omitted per post, a field
	 * name that is not part of the supported set is rejected before the ability executes.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_unknown_requested_field_fails_schema_validation(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_logged_out_user_is_denied(): void {
		wp_set_current_user( 0 );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'type' => 'post' ) );

		$this->assertWPError( $result, 'Logged-out users should not be allowed to run the content ability.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Logged-out users should receive a permission error.' );
	}

	/**
	 * Subscribers can request rendered published content.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_subscriber_can_request_published_content(): void {
		$post_id = self::$post_ids['published_content'];

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'post',
				'fields' => array( 'id', 'title_rendered', 'content_rendered' ),
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $post_id, $ids, 'Subscribers should be able to query readable published posts.' );
		$post_index = array_search( $post_id, $ids, true );
		$this->assertIsInt( $post_index, 'The published post should be present in the subscriber query response.' );
		$post = $result['posts'][ $post_index ];
		$this->assertSame( 'Hello Content', $post['title_rendered'], 'Subscribers should receive rendered titles.' );
		$this->assertStringContainsString( 'Body here.', $post['content_rendered'], 'Subscribers should receive rendered content.' );
		$this->assertArrayNotHasKey( 'content_raw', $post, 'Subscribers should not receive raw content without edit access.' );
	}

	/**
	 * Subscribers can fetch a published post by ID.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_subscriber_can_get_single_published_post_by_id(): void {
		$post_id = self::$post_ids['published_content'];

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => $post_id ) );

		$this->assertIsArray( $result, 'Subscribers should be able to fetch a readable published post by ID.' );
		$this->assertSame( 'Hello Content', $result['title_rendered'], 'Subscribers should receive the rendered title.' );
		$this->assertArrayNotHasKey( 'title_raw', $result, 'Subscribers should not receive raw titles without edit access.' );
		$this->assertArrayNotHasKey( 'content_raw', $result, 'Subscribers should not receive raw content without edit access.' );
		$this->assertArrayNotHasKey( 'content_rendered', $result, 'Rendered content should require an explicit field request.' );
	}

	/**
	 * Subscribers cannot request edit-context raw fields in query mode.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_subscriber_cannot_request_raw_fields_in_query_mode(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'post',
				'fields' => array( 'content_raw' ),
			)
		);

		$this->assertWPError( $result, 'Subscribers should not be able to request raw fields in query mode.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Subscriber raw-field query requests should return a permission error.' );
	}

	/**
	 * Subscribers cannot request edit-context raw fields for a single post.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_subscriber_cannot_request_raw_fields_for_single_post(): void {
		$post_id = self::$post_ids['published'];

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * @ticket 66268
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 *
	 * @param string $role The role to test.
	 */
	public function test_default_fields_omit_raw_fields_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$post_id = self::$post_ids['limited_role_content'];

		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'id' => $post_id ) );

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
	 * @ticket 66268
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 *
	 * @param string $role The role to test.
	 */
	public function test_raw_field_requests_are_denied_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$post_id = self::$post_ids['limited_role_content'];

		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_subscriber_cannot_request_draft_status(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'post',
				'status' => array( 'draft' ),
			)
		);

		$this->assertWPError( $result, 'Subscribers should not be allowed to query draft posts.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Subscriber draft queries should return a permission error.' );
	}

	/**
	 * Subscribers cannot request private posts.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_subscriber_cannot_request_private_status(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'post',
				'status' => array( 'private' ),
			)
		);

		$this->assertWPError( $result, 'Subscribers should not be allowed to query private posts.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Subscriber private queries should return a permission error.' );
	}

	/**
	 * An author can pass the draft gate but only sees their own drafts.
	 *
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'post',
				'status' => array( 'draft' ),
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
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'     => 'post',
				'status'   => array( 'draft' ),
				'per_page' => 1,
				'fields'   => array( 'id' ),
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_mode_rejects_parent_filter_for_non_hierarchical_post_type(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'post',
				'parent' => 0,
			)
		);

		$this->assertWPError( $result, 'The parent filter should be rejected for non-hierarchical post types.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'Unsupported parent filters should return a filter error.' );
	}

	/**
	 * The parent filter narrows hierarchical queries to children of the given post.
	 *
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'page',
				'parent' => $parent_id,
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertSame( array( $child_id ), $ids, 'The parent filter should return only the children of the given page.' );
	}

	/**
	 * The author_slug filter is rejected for post types without author support, mirroring REST.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_mode_rejects_author_filter_for_post_type_without_author_support(): void {
		register_post_type(
			'no_author_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title' ),
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'        => 'no_author_cpt',
				'author_slug' => get_userdata( self::$user_ids['author'] )->user_nicename,
			)
		);

		$this->assertWPError( $result, 'The author_slug filter should be rejected for post types without author support.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'Unsupported author filters should return a filter error.' );
	}

	/**
	 * The author_slug filter narrows queries to posts by the given author, and each post
	 * returns its author's slug.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_mode_filters_posts_by_author(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$author_slug = get_userdata( self::$user_ids['author'] )->user_nicename;
		$mine_id     = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'publish',
			)
		);
		$other_id    = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author_secondary'],
				'post_status' => 'publish',
			)
		);

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'        => 'post',
				'author_slug' => $author_slug,
				'per_page'    => 100,
				'fields'      => array( 'id', 'author_slug' ),
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $mine_id, $ids, 'The author_slug filter should include the author\'s posts.' );
		$this->assertNotContains( $other_id, $ids, 'The author_slug filter should exclude other authors\' posts.' );
		$this->assertSame( array( $author_slug ), array_unique( wp_list_pluck( $result['posts'], 'author_slug' ) ), 'Each post should return its author\'s slug.' );
	}

	/**
	 * Query mode returns only posts of the requested post type, also when a query filter adds
	 * post types to the blog home.
	 *
	 * WP_Query treats the ability's query as the blog home, so a `pre_get_posts` callback
	 * without an is_main_query() check also runs for it. The read check would let through
	 * posts of other exposed post types, and the edit check posts of any post type.
	 *
	 * @ticket 66268
	 * @dataProvider data_read_and_edit_fields
	 * @since 7.2.0
	 *
	 * @param list<string> $fields The fields to request.
	 */
	public function test_query_mode_skips_posts_of_other_post_types( array $fields ): void {
		register_post_type(
			'hidden_cpt',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor' ),
			)
		);

		// With several post types, an editable query keeps only the current user's posts.
		$ids = array();
		foreach ( array( 'post', 'page', 'hidden_cpt' ) as $post_type ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => $post_type,
					'post_status' => 'publish',
				)
			);
		}

		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( $query->is_home() ) {
					$query->set( 'post_type', array( 'post', 'page', 'hidden_cpt' ) );
				}
			}
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				'include' => $ids,
				'fields'  => $fields,
			)
		);

		$this->assertSame( 3, $result['total'], 'Precondition: the query filter should add the other post types.' );
		$this->assertSame( array( $ids[0] ), wp_list_pluck( $result['posts'], 'id' ), 'Query mode should return only posts of the requested type.' );
	}

	/**
	 * Raw content is available to users who can edit the post.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_raw_content_visible_to_editor(): void {
		$post_id = self::$post_ids['published_content'];

		$this->login_as( 'editor' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'content_raw' ),
			)
		);

		$this->assertSame(
			'Body here.',
			$result['content_raw'],
			'Editors should receive explicitly requested raw content.'
		);
	}

	/**
	 * Password-protected content is visible to users who can edit the post.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_password_protected_content_visible_to_editor(): void {
		$post_id = self::$post_ids['password_protected'];

		$this->login_as( 'editor' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * @ticket 66268
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 *
	 * @param string $role The role to test.
	 */
	public function test_password_protected_rendered_content_is_empty_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$post_id = self::$post_ids['password_protected'];

		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * The excerpt is generated from the content, which get_the_content() replaces with the
	 * password form unless the ability unlocks the post for its editor.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_password_protected_excerpt_visible_to_editor(): void {
		$this->login_as( 'editor' );
		$this->register_ability();

		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_content'  => 'Top secret body.',
				'post_excerpt'  => '',
			)
		);

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'excerpt_rendered', 'excerpt_protected' ),
			)
		);

		$this->assertSame(
			"<p>Top secret body.</p>\n",
			$result['excerpt_rendered'],
			'Editors should receive the real excerpt generated from a password-protected post.'
		);
		$this->assertTrue( $result['excerpt_protected'], 'The protected flag should reveal the excerpt is password-protected.' );
	}

	/**
	 * Password-protected rendered excerpts are withheld from users who cannot edit the post.
	 *
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_excerpt_rendered_applies_the_excerpt_filters(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * A title filter that returns a non-string empties the rendered title instead of failing
	 * the query.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_rendered_title_is_empty_when_a_title_filter_returns_a_non_string(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		// Runs after core's own title filters, which expect a string.
		add_filter( 'the_title', '__return_null', 20 );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => self::$post_ids['published'],
				'fields' => array( 'id', 'title_rendered' ),
			)
		);

		$this->assertIsArray( $result, 'A non-string rendered title should not fail the query.' );
		$this->assertSame( '', $result['title_rendered'], 'A non-string rendered title should be returned as an empty string.' );
	}

	/**
	 * Rendered title and excerpt filters run with the requested post as the global context,
	 * and the context that was active before the ability executed is restored.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_rendered_fields_use_and_restore_requested_post_context(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$target_id   = self::$post_ids['limited_role_content'];
		$surrounding = get_post( self::$post_ids['published'] );

		$this->assertInstanceOf( WP_Post::class, $surrounding, 'The surrounding post fixture should exist.' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Establishes a distinct context to verify the ability restores it.
		$GLOBALS['post'] = $surrounding;
		setup_postdata( $surrounding );

		$append_context_id = static function ( $text ): string {
			return (string) $text . '<!-- context:' . get_the_ID() . ' -->';
		};
		add_filter( 'the_title', $append_context_id, 20 );
		add_filter( 'the_excerpt', $append_context_id, 20 );

		$result              = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => $target_id,
				'fields' => array( 'id', 'title_rendered', 'excerpt_rendered' ),
			)
		);
		$restored_context_id = get_the_ID();

		$this->assertStringContainsString(
			'<!-- context:' . $target_id . ' -->',
			$result['title_rendered'],
			'Title filters should see the requested post as the current post.'
		);
		$this->assertStringContainsString(
			'<!-- context:' . $target_id . ' -->',
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
	 * Returns the rendered fields, whose filters run with the requested post set up as the
	 * global post.
	 *
	 * @return array<string, array{field: string}> Rendered field test cases.
	 */
	public function data_rendered_fields(): array {
		return array(
			'content_rendered' => array(
				'field' => 'content_rendered',
			),
			'excerpt_rendered' => array(
				'field' => 'excerpt_rendered',
			),
		);
	}

	/**
	 * Rendering a field unsets the loop globals that were not set before.
	 *
	 * Without a surrounding post, as in a REST request, wp_reset_postdata() cannot restore
	 * the globals that setup_postdata() populates, because the main query has no post.
	 *
	 * @ticket 66268
	 * @dataProvider data_rendered_fields
	 * @since 7.2.0
	 *
	 * @param string $field The rendered field to request.
	 */
	public function test_rendered_fields_unset_loop_globals_that_were_not_set( string $field ): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		foreach ( self::LOOP_GLOBALS as $name ) {
			unset( $GLOBALS[ $name ] );
		}

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => self::$post_ids['limited_role_content'],
				'fields' => array( $field ),
			)
		);

		$this->assertArrayHasKey( $field, $result, 'Precondition: the rendered field should be returned.' );
		$this->assertSame( array(), $this->get_loop_globals(), 'Loop globals that were not set should be unset again after rendering.' );
	}

	/**
	 * Rendering a field restores the surrounding loop globals as they were, rather than as
	 * setup_postdata() computes them for the surrounding post.
	 *
	 * @ticket 66268
	 * @dataProvider data_rendered_fields
	 * @since 7.2.0
	 *
	 * @param string $field The rendered field to request.
	 */
	public function test_rendered_fields_restore_surrounding_loop_globals( string $field ): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$surrounding = get_post( self::$post_ids['published'] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Establishes a surrounding context to verify the ability restores it.
		$GLOBALS['post'] = $surrounding;
		setup_postdata( $surrounding );

		/*
		 * A template can show the full content outside a single post view by setting `$more`,
		 * which running setup_postdata() for the surrounding post again would undo.
		 */
		$GLOBALS['more'] = 1;

		$surrounding_globals = $this->get_loop_globals();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => self::$post_ids['limited_role_content'],
				'fields' => array( $field ),
			)
		);

		$this->assertArrayHasKey( $field, $result, 'Precondition: the rendered field should be returned.' );
		$this->assertSame( $surrounding_globals, $this->get_loop_globals(), 'The surrounding loop globals should be restored as they were.' );
	}

	/**
	 * Rendering a field restores the surrounding loop globals when a calling function binds
	 * them with `global`, as load_template() and WP_Block::render() do.
	 *
	 * Such a binding makes the global a reference, so a saved copy that kept the reference
	 * would follow the global to the rendered post, and the rendered post would be restored.
	 *
	 * @ticket 66268
	 * @dataProvider data_rendered_fields
	 * @since 7.2.0
	 *
	 * @param string $field The rendered field to request.
	 */
	public function test_rendered_fields_restore_loop_globals_bound_by_the_caller( string $field ): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$surrounding = get_post( self::$post_ids['published'] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Establishes a surrounding context to verify the ability restores it.
		$GLOBALS['post'] = $surrounding;
		setup_postdata( $surrounding );

		$surrounding_globals = $this->get_loop_globals();

		$render_in_template = static function ( string $field ): array {
			global $post, $id;

			$result = wp_get_ability( 'core/content-query' )->execute(
				array(
					'id'     => self::$post_ids['limited_role_content'],
					'fields' => array( $field ),
				)
			);

			return array( $result, $post->ID, $id );
		};

		list( $result, $bound_post_id, $bound_id ) = $render_in_template( $field );

		$this->assertArrayHasKey( $field, $result, 'Precondition: the rendered field should be returned.' );
		$this->assertSame( $surrounding->ID, $bound_post_id, 'The caller should see the surrounding post again after rendering.' );
		$this->assertSame( $surrounding->ID, $bound_id, 'The caller should see the surrounding post ID again after rendering.' );
		$this->assertSame( $surrounding_globals, $this->get_loop_globals(), 'The surrounding loop globals should be restored as they were.' );
	}

	/**
	 * Rendering a field sets up a global post that was not set up before, such as the main
	 * post before the loop starts, so get_the_content() without a post still works.
	 *
	 * Rendering fires `the_post`, after which get_the_content() without a post reads the loop
	 * globals. Unsetting `$pages` again, as for a post that was never set up, made it throw a
	 * TypeError.
	 *
	 * @ticket 66268
	 * @dataProvider data_rendered_fields
	 * @since 7.2.0
	 *
	 * @param string $field The rendered field to request.
	 */
	public function test_rendered_fields_set_up_a_global_post_that_was_not_set_up( string $field ): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		foreach ( self::LOOP_GLOBALS as $name ) {
			unset( $GLOBALS[ $name ] );
		}

		// WP::register_globals() sets the main post this way, before the loop sets it up.
		$main_post = get_post( self::$post_ids['published_content'] );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Establishes a global post that was not set up.
		$GLOBALS['post'] = $main_post;

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => self::$post_ids['limited_role_content'],
				'fields' => array( $field ),
			)
		);

		$this->assertArrayHasKey( $field, $result, 'Precondition: the rendered field should be returned.' );
		$this->assertSame( $main_post->ID, get_the_ID(), 'The main post should be the global post again.' );
		$this->assertSame( 'Body here.', get_the_content(), 'get_the_content() without a post should return the main post content.' );
	}

	/**
	 * Title and permalink filters run with the requested post as the global post, also when
	 * no post was set up before, as in a REST request.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_title_and_link_filters_see_the_requested_post(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		foreach ( self::LOOP_GLOBALS as $name ) {
			unset( $GLOBALS[ $name ] );
		}

		$target_id = self::$post_ids['limited_role_content'];

		add_filter(
			'the_title',
			static function ( $title ): string {
				return (string) $title . '<!-- context:' . get_the_ID() . ' -->';
			},
			20
		);
		add_filter(
			'post_link',
			static function ( $link ): string {
				return add_query_arg( 'context', get_the_ID(), (string) $link );
			}
		);

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => $target_id,
				'fields' => array( 'title_rendered', 'link' ),
			)
		);

		$this->assertStringContainsString( '<!-- context:' . $target_id . ' -->', $result['title_rendered'], 'Title filters should see the requested post as the current post.' );
		$this->assertStringContainsString( 'context=' . $target_id, $result['link'], 'Permalink filters should see the requested post as the current post.' );
	}

	/**
	 * Returns the loop globals that are set, keyed by name.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The loop globals that are set.
	 */
	private function get_loop_globals(): array {
		$globals = array();
		foreach ( self::LOOP_GLOBALS as $name ) {
			if ( ! array_key_exists( $name, $GLOBALS ) ) {
				continue;
			}

			$globals[ $name ] = $GLOBALS[ $name ];
		}

		return $globals;
	}

	/**
	 * The password gate is suspended only for posts the current user can edit.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_allow_password_content_only_unlocks_editable_posts(): void {
		$owned_id = self::factory()->post->create(
			array(
				'post_author'   => self::$user_ids['author'],
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		$other_id = self::$post_ids['password_protected'];

		$this->login_as( 'author' );

		$ability = new WP_Abilities_Content();

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
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => $owned_id,
				'fields' => array( 'id', 'content_rendered' ),
			)
		);

		// Tear-down restores hooks, but not shortcodes.
		remove_shortcode( 'read_content_nested' );

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
	 * Query mode reports a total that matches the returned posts for an uncapped query.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_total_matches_returned_posts_when_uncapped(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'     => 'post',
				'per_page' => 100,
			)
		);

		$this->assertNotEmpty( $result['posts'], 'An uncapped query should return the readable posts.' );
		$this->assertCount( $result['total'], $result['posts'], 'An uncapped query should report a total equal to the number of returned posts.' );
		$this->assertSame( 1, $result['total_pages'], 'An uncapped query should fit on a single page.' );
	}

	/**
	 * The last page still reports the totals of the underlying query.
	 *
	 * Guards the boundary next to the out-of-range page error: the final page must not be
	 * mistaken for an overshoot.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_last_page_reports_totals(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'     => 'post',
				'include'  => $ids,
				'per_page' => 2,
				'page'     => 2,
				'fields'   => array( 'id' ),
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
	 * which is indistinguishable from an empty collection. Report it as a page that does not
	 * exist instead.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_out_of_range_page_is_rejected_rather_than_reported_as_empty(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'     => 'post',
				'include'  => $ids,
				'per_page' => 2,
				'page'     => 99,
				'fields'   => array( 'id' ),
			)
		);

		$this->assertWPError( $result, 'A page beyond the last one should fail rather than return an empty list.' );
		$this->assertSame( 'content_invalid_page_number', $result->get_error_code(), 'Out-of-range pages should report a dedicated error code.' );
		$this->assertSame( 404, $result->get_error_data()['status'], 'An out-of-range page should be reported as not found.' );
	}

	/**
	 * Returns whole numbers that the integer schema accepts but that are not PHP integers,
	 * as a page, a page size, and a page past the last one.
	 *
	 * @return array<string, array{0: float|string, 1: float|string, 2: float|string}> The values to send.
	 */
	public function data_whole_numbers_that_are_not_integers(): array {
		return array(
			'floats'               => array( 2.0, 2.0, 99.0 ),
			'decimal strings'      => array( '2.0', '2.0', '99.0' ),
			'signed strings'       => array( '+2', '+2', '+99' ),
			'strings with a space' => array( ' 2', ' 2', ' 99' ),
		);
	}

	/**
	 * A `page` and `per_page` that the integer schema accepts are honored even when they are
	 * not PHP integers, as when the MCP adapter passes the 2.0 a JSON encoder produced.
	 *
	 * Read as invalid, they would fall back to page 1 and the default page size, so a client
	 * paging with 2.0, 3.0, and so on would get page 1 every time and never reach the error for
	 * a page past the last one.
	 *
	 * @ticket 66268
	 * @dataProvider data_whole_numbers_that_are_not_integers
	 * @since 7.2.0
	 *
	 * @param float|string $page      The page to request.
	 * @param float|string $per_page  The page size to request.
	 * @param float|string $past_page A page past the last one.
	 */
	public function test_query_honors_a_page_and_per_page_that_are_not_integers( $page, $per_page, $past_page ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$query = array(
			'type'     => 'post',
			'include'  => self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) ),
			'per_page' => $per_page,
			'fields'   => array( 'id' ),
		);

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'page' => $page ) + $query );

		$this->assertIsArray( $result, 'The page should be returned.' );
		$this->assertCount( 1, $result['posts'], 'The second page of two posts each should hold the third post.' );
		$this->assertSame( 2, $result['total_pages'], 'The page count should follow the requested page size.' );

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'page' => $past_page ) + $query );

		$this->assertWPError( $result, 'A page past the last one should still fail.' );
		$this->assertSame( 'content_invalid_page_number', $result->get_error_code(), 'A page past the last one should report the out-of-range error.' );
	}

	/**
	 * Returns {@see self::IMPORTED_POST_ID} in forms that the integer schema accepts but that
	 * are not PHP integers.
	 *
	 * @return array<string, array{id: float|string}> The values to send for the post ID.
	 */
	public function data_post_ids_that_are_not_integers(): array {
		return array(
			'float'               => array(
				'id' => (float) self::IMPORTED_POST_ID,
			),
			'decimal string'      => array(
				'id' => self::IMPORTED_POST_ID . '.0',
			),
			'signed string'       => array(
				'id' => '+' . self::IMPORTED_POST_ID,
			),
			'string with a space' => array(
				'id' => ' ' . self::IMPORTED_POST_ID,
			),
		);
	}

	/**
	 * A single-post lookup honors an `id` that the integer schema accepts even when it is not
	 * a PHP integer, as when the MCP adapter passes a float that a JSON encoder produced.
	 *
	 * Read as invalid, the ID would deny a readable post as a permission error.
	 *
	 * @ticket 66268
	 * @dataProvider data_post_ids_that_are_not_integers
	 * @since 7.2.0
	 *
	 * @param float|string $id The value to send for the imported post ID.
	 */
	public function test_get_by_id_honors_an_id_that_is_not_an_integer( $id ): void {
		$post_id = self::factory()->post->create(
			array(
				'import_id'   => self::IMPORTED_POST_ID,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( self::IMPORTED_POST_ID, $post_id, 'Precondition: the post should have the requested ID.' );

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => $id,
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The post should be returned.' );
		$this->assertSame( $post_id, $result['id'], 'The lookup should resolve to the requested post.' );
	}

	/**
	 * Query mode honors a `parent` filter that the integer schema accepts even when it is not
	 * a PHP integer.
	 *
	 * Read as invalid, the filter would be rejected as an invalid filter.
	 *
	 * @ticket 66268
	 * @dataProvider data_post_ids_that_are_not_integers
	 * @since 7.2.0
	 *
	 * @param float|string $id The value to send for the imported parent post ID.
	 */
	public function test_query_honors_a_parent_that_is_not_an_integer( $id ): void {
		$parent_id = self::factory()->post->create(
			array(
				'import_id'   => self::IMPORTED_POST_ID,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$this->assertSame( self::IMPORTED_POST_ID, $parent_id, 'Precondition: the parent page should have the requested ID.' );

		$child_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_id,
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'   => 'page',
				'parent' => $id,
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The query should succeed.' );
		$this->assertSame( array( $child_id ), wp_list_pluck( $result['posts'], 'id' ), 'The query should return the children of the requested parent.' );
	}

	/**
	 * Returns fractions just above {@see self::IMPORTED_POST_ID}, which the integer schema rejects.
	 *
	 * @return array<string, array{id: float|string}> The values to send for the post ID.
	 */
	public function data_fractional_post_ids(): array {
		return array(
			'float'  => array(
				'id' => self::IMPORTED_POST_ID + 0.5,
			),
			'string' => array(
				'id' => self::IMPORTED_POST_ID . '.5',
			),
		);
	}

	/**
	 * A fractional ID is rejected rather than truncated onto the post with the whole ID.
	 *
	 * Schema validation rejects it before either callback runs through WP_Ability::execute(),
	 * but each callback still fails closed on its own.
	 *
	 * @ticket 66268
	 * @dataProvider data_fractional_post_ids
	 * @since 7.2.0
	 *
	 * @param float|string $id The value to send, a fraction just above the imported post ID.
	 */
	public function test_callbacks_reject_a_fractional_id( $id ): void {
		$post_id = self::factory()->post->create(
			array(
				'import_id'   => self::IMPORTED_POST_ID,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( self::IMPORTED_POST_ID, $post_id, 'Precondition: the post should have the ID the fraction truncates to.' );

		$this->login_as( 'administrator' );
		$content = new WP_Abilities_Content();

		$this->assertFalse( $content->check_permission( array( 'id' => $id ) ), 'The permission callback should deny a fractional ID.' );

		$result = $content->execute_content_query( array( 'id' => $id ) );

		$this->assertWPError( $result, 'The execute callback should not resolve a fractional ID.' );
		$this->assertSame( 'content_not_found', $result->get_error_code(), 'A fractional ID should fail the lookup.' );
	}

	/**
	 * A genuinely empty result set beyond the first page reports zero totals, not an error.
	 *
	 * The out-of-range guard only fires when the underlying query actually matched rows.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_empty_result_beyond_first_page_reports_zero_totals(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				'include' => array( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ),
				'page'    => 2,
				'fields'  => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'An empty result set should not be treated as an out-of-range page.' );
		$this->assertSame( array(), $result['posts'], 'No posts match the query.' );
		$this->assertSame( 0, $result['total'], 'An empty result set reports a zero total.' );
		$this->assertSame( 0, $result['total_pages'], 'An empty result set reports zero pages.' );
	}

	/**
	 * Returns page sizes that a query filter can set, with the pages they make of five posts.
	 *
	 * @return array<string, array{posts_per_page: int, total_pages: int, last_page_count: int}> Page size test cases.
	 */
	public function data_page_sizes_set_by_a_query_filter(): array {
		return array(
			'smaller page size' => array(
				'posts_per_page'  => 2,
				'total_pages'     => 3,
				'last_page_count' => 1,
			),
			'no paging'         => array(
				'posts_per_page'  => -1,
				'total_pages'     => 1,
				'last_page_count' => 5,
			),
		);
	}

	/**
	 * Query mode counts the pages with the page size the query ran with.
	 *
	 * WP_Query treats the ability's query as the blog home, so a `pre_get_posts` callback
	 * without an is_main_query() check can change its page size. Counting the pages with the
	 * requested page size would then report the wrong number of pages, and reject pages that
	 * hold posts or serve the same posts again.
	 *
	 * @ticket 66268
	 * @dataProvider data_page_sizes_set_by_a_query_filter
	 * @since 7.2.0
	 *
	 * @param int $posts_per_page  The page size the query filter sets.
	 * @param int $total_pages     The expected number of pages.
	 * @param int $last_page_count The expected number of posts on the last page.
	 */
	public function test_query_counts_pages_with_the_page_size_the_query_ran_with( int $posts_per_page, int $total_pages, int $last_page_count ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 5, array( 'post_status' => 'publish' ) );

		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ) use ( $posts_per_page ): void {
				if ( $query->is_home() ) {
					$query->set( 'posts_per_page', $posts_per_page );
				}
			}
		);

		$ability = wp_get_ability( 'core/content-query' );
		$input   = array(
			'type'     => 'post',
			'include'  => $ids,
			'per_page' => 4,
			'fields'   => array( 'id' ),
		);

		$first_page = $ability->execute( $input );

		$this->assertSame( 5, $first_page['total'], 'The total should count every matching post.' );
		$this->assertSame( $total_pages, $first_page['total_pages'], 'The page count should follow the page size the query ran with.' );

		$last_page = $ability->execute( array_merge( $input, array( 'page' => $total_pages ) ) );

		$this->assertIsArray( $last_page, 'The last page should be served.' );
		$this->assertCount( $last_page_count, $last_page['posts'], 'The last page should return the remaining posts.' );

		$past_last_page = $ability->execute( array_merge( $input, array( 'page' => $total_pages + 1 ) ) );

		$this->assertWPError( $past_last_page, 'The page after the last one should be rejected.' );
		$this->assertSame( 'content_invalid_page_number', $past_last_page->get_error_code(), 'Paging past the last page should report the page number error.' );
	}

	/**
	 * Include returns every requested post when `per_page` is omitted.
	 *
	 * Without this the default page size silently truncates a batch load: a caller asking
	 * for a known set of IDs would receive only the first `per_page` of them.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_include_returns_every_requested_post_without_per_page(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		// More than DEFAULT_PER_PAGE (10) so truncation would be visible.
		$ids = self::factory()->post->create_many( 15, array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				'include' => $ids,
				'fields'  => array( 'id' ),
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_include_honors_an_explicit_per_page(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 5, array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'     => 'post',
				'include'  => $ids,
				'per_page' => 2,
				'fields'   => array( 'id' ),
			)
		);

		$this->assertCount( 2, $result['posts'], 'An explicit per_page should paginate included posts.' );
		$this->assertSame( 5, $result['total'], 'The total should still cover every requested post.' );
		$this->assertSame( 3, $result['total_pages'], 'Page counts should follow the explicit per_page.' );
	}

	/**
	 * The include list is capped at the maximum page size.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_include_is_capped_at_the_maximum_page_size(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$schema = wp_get_ability( 'core/content-query' )->get_input_schema();
		$query  = $schema['oneOf'][2];

		$this->assertSame( $query['properties']['per_page']['maximum'], $query['properties']['include']['maxItems'], 'The include list should be capped at the maximum page size.' );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				'include' => range( 1, $query['properties']['include']['maxItems'] + 1 ),
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_rendered_fields_prime_the_post_meta_cache(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$postmeta_queries = $this->count_queries(
			'postmeta',
			static function () use ( $ids ) {
				return wp_get_ability( 'core/content-query' )->execute(
					array(
						'type'    => 'post',
						'include' => $ids,
						'fields'  => array( 'id', 'content_rendered' ),
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
	 * Requesting rendered fields primes the authors of the whole page.
	 *
	 * Rendering sets each post up with setup_postdata(), which reads the post's author, so
	 * without priming, each author on the page runs its own query.
	 *
	 * @ticket 66268
	 * @dataProvider data_rendered_fields
	 * @since 7.2.0
	 *
	 * @param string $field The rendered field to request.
	 */
	public function test_query_rendered_fields_prime_the_authors_of_the_page( string $field ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = array();
		foreach ( array( 'author', 'author_secondary', 'editor' ) as $role ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_author' => self::$user_ids[ $role ],
					'post_status' => 'publish',
				)
			);
		}

		$users_queries = $this->count_queries(
			'users',
			static function () use ( $ids, $field ) {
				return wp_get_ability( 'core/content-query' )->execute(
					array(
						'type'    => 'post',
						'include' => $ids,
						'fields'  => array( 'id', $field ),
					)
				);
			},
			$result
		);

		$this->assertCount( 3, $result['posts'], 'Precondition: the query should return the seeded posts.' );
		$this->assertSame( 1, $users_queries, 'Rendered fields should read primed authors, not query once per author.' );
	}

	/**
	 * A lean projection does not ask for post meta priming.
	 *
	 * Nothing in the default field set renders a post, so the ability leaves priming off
	 * when it builds the query.
	 *
	 * The check is on what the ability asks for, not on the queries that follow. Honoring
	 * the request belongs to whoever runs the query, and it may prime more for its own
	 * reasons, so counting queries here would describe that layer rather than this one.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_lean_projection_does_not_request_post_meta_priming(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		// A non-empty `post__in` identifies the query the ability built for this request.
		$priming = array();
		$spy     = static function ( $query ) use ( &$priming ): void {
			if ( array() === (array) $query->get( 'post__in' ) ) {
				return;
			}

			$priming[] = $query->get( 'update_post_meta_cache' );
		};

		add_action( 'pre_get_posts', $spy );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'    => 'post',
				'include' => $ids,
				'fields'  => array( 'id' ),
			)
		);

		$this->assertCount( 3, $result['posts'], 'Precondition: the query should return the seeded posts.' );
		$this->assertNotEmpty( $priming, 'Precondition: the ability should query for the included posts.' );
		$this->assertSame( array(), array_filter( $priming ), 'A lean projection should leave post meta priming off.' );
	}

	/**
	 * Returns permalink structures that read data related to each post.
	 *
	 * @return array<string, array{permalink_structure: string, table: string}> Permalink test cases.
	 */
	public function data_permalink_structures_that_read_related_data(): array {
		return array(
			'category permalinks' => array(
				'permalink_structure' => '/%category%/%postname%/',
				'table'               => 'term_relationships',
			),
			'author permalinks'   => array(
				'permalink_structure' => '/%author%/%postname%/',
				'table'               => 'users',
			),
		);
	}

	/**
	 * Requesting `link` primes the caches that permalinks read for the whole page.
	 *
	 * Permalinks read the post's terms for `%category%` and its author for `%author%`,
	 * so without priming, each returned row runs its own query.
	 *
	 * @ticket 66268
	 * @dataProvider data_permalink_structures_that_read_related_data
	 *
	 * @param string $permalink_structure The permalink structure.
	 * @param string $table               The wpdb property naming the table the permalinks read.
	 */
	public function test_query_link_primes_the_caches_permalinks_read( string $permalink_structure, string $table ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();
		$this->set_permalink_structure( $permalink_structure );

		$category_id = self::factory()->category->create();
		$ids         = array();
		foreach ( array( 'author', 'author_secondary', 'editor' ) as $role ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_author'   => self::$user_ids[ $role ],
					'post_category' => array( $category_id ),
					'post_status'   => 'publish',
				)
			);
		}

		$queries = $this->count_queries(
			$table,
			static function () use ( $ids ) {
				return wp_get_ability( 'core/content-query' )->execute(
					array(
						'type'    => 'post',
						'include' => $ids,
						'fields'  => array( 'id', 'link' ),
					)
				);
			},
			$result
		);

		$this->assertCount( 3, $result['posts'], 'Precondition: the query should return the seeded posts.' );
		$this->assertSame( 1, $queries, 'Permalinks should read primed caches, not query once per returned post.' );
	}

	/**
	 * Requesting `link` primes the parents that page permalinks read for the whole page.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_link_primes_the_parents_page_permalinks_read(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();
		$this->set_permalink_structure( '/%postname%/' );

		$ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_type'   => 'page',
					'post_parent' => self::factory()->post->create( array( 'post_type' => 'page' ) ),
				)
			);
		}

		$queries = $this->count_queries(
			'posts',
			static function () use ( $ids ) {
				return wp_get_ability( 'core/content-query' )->execute(
					array(
						'type'    => 'page',
						'include' => $ids,
						'fields'  => array( 'id', 'link' ),
					)
				);
			},
			$result
		);

		$this->assertCount( 3, $result['posts'], 'Precondition: the query should return the seeded pages.' );
		// One query finds the matching IDs, one loads those pages, and one loads their parents.
		$this->assertSame( 3, $queries, 'Page permalinks should read primed parents, not query once per returned page.' );
	}

	/**
	 * Counts the queries against a table issued while running the given callback.
	 *
	 * Counts during the call rather than checking the cache afterwards: the rendered
	 * filter chains prime meta lazily, so an after-the-fact cache check passes either way.
	 *
	 * @since 7.2.0
	 *
	 * @param string   $table    The wpdb property naming the table, such as `postmeta`.
	 * @param callable $callback Callback to run.
	 * @param mixed    $result   Set to the callback's return value.
	 * @return int Number of queries issued against the table.
	 */
	private function count_queries( string $table, callable $callback, &$result ): int {
		global $wpdb;

		$queries = 0;
		$pattern = '/(?:FROM|JOIN)\s+`?' . preg_quote( $wpdb->$table, '/' ) . '\b/i';
		$spy     = static function ( $query ) use ( &$queries, $pattern ) {
			if ( is_string( $query ) && preg_match( $pattern, $query ) ) {
				++$queries;
			}

			return $query;
		};

		wp_cache_flush();

		/*
		 * On multisite, capability checks load the current user to check whether they are a
		 * super admin. Load the user again after the flush so only the callback's own queries
		 * are counted.
		 */
		get_userdata( get_current_user_id() );

		add_filter( 'query', $spy );
		$result = $callback();
		remove_filter( 'query', $spy );

		return $queries;
	}

	/**
	 * Query rows are kept, with their ID, when none of the requested fields apply to them.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_keeps_posts_whose_requested_fields_do_not_apply(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		// `parent` never applies to the non-hierarchical `post` type.
		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'     => 'post',
				'per_page' => 100,
				'fields'   => array( 'parent' ),
			)
		);

		$this->assertNotEmpty( $result['posts'], 'Posts whose requested fields do not apply should still be returned.' );
		$this->assertCount( $result['total'], $result['posts'], 'The reported total should match the returned posts.' );

		foreach ( $result['posts'] as $post_entry ) {
			$this->assertSame( array( 'id' ), array_keys( $post_entry ), 'A post whose requested fields do not apply should return only its ID.' );
		}
	}

	/**
	 * A single post is returned with its ID when none of the requested fields apply to it.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_single_post_returns_its_id_when_requested_fields_do_not_apply(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published'];

		// `parent` never applies to the non-hierarchical `post` type.
		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'parent' ),
			)
		);

		$this->assertSame( array( 'id' => $post_id ), $result, 'A post whose requested fields do not apply should return only its ID.' );
	}

	/**
	 * Local and GMT date fields report the correct instant and offset on non-UTC sites.
	 *
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * @since 7.2.0
	 *
	 * @param int                  $post_id The post ID.
	 * @param array<string, mixed> $columns Post column values keyed by column name.
	 */
	private function replace_cached_post_date_columns( int $post_id, array $columns ): void {
		get_post( $post_id );

		$cached = wp_cache_get( $post_id, 'posts' );
		$this->assertInstanceOf( stdClass::class, $cached, 'Precondition: the raw post row should be cached.' );
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
	 * @since 7.2.0
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
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * @ticket 66268
	 * @since 7.2.0
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

		$result = wp_get_ability( 'core/content-query' )->execute(
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_execute_callback_returns_not_found_for_structural_lookup_failures(): void {
		$this->login_as( 'administrator' );

		$content = new WP_Abilities_Content();

		$missing = $content->execute_content_query( array( 'id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$this->assertWPError( $missing, 'A nonexistent post ID should fail the lookup.' );
		$this->assertSame( 'content_not_found', $missing->get_error_code(), 'Missing posts should map to the uniform not-found error.' );

		$mismatched = $content->execute_content_query(
			array(
				'id'   => self::$post_ids['published'],
				'type' => 'page',
			)
		);
		$this->assertWPError( $mismatched, 'A post type mismatch should fail the lookup.' );
		$this->assertSame( 'content_not_found', $mismatched->get_error_code(), 'Mismatched post types should map to the uniform not-found error.' );

		$missing_slug = $content->execute_content_query(
			array(
				'type' => 'post',
				'slug' => 'no-such-slug',
			)
		);
		$this->assertWPError( $missing_slug, 'An unmatched slug should fail the lookup.' );
		$this->assertSame( 'content_not_found', $missing_slug->get_error_code(), 'Unmatched slugs should map to the uniform not-found error.' );
	}

	/**
	 * Returns author slugs that do not name a user.
	 *
	 * @return array<string, array{0: mixed}> The author slug filter value.
	 */
	public function data_author_slugs_that_name_no_user(): array {
		return array(
			'unknown slug' => array( 'no-such-user' ),
			'not a string' => array( 5 ),
		);
	}

	/**
	 * An author_slug filter that does not name a user is rejected rather than silently
	 * dropped, which would widen the query to every author's posts.
	 *
	 * @ticket 66268
	 * @dataProvider data_author_slugs_that_name_no_user
	 *
	 * @param mixed $author_slug The author slug filter value.
	 */
	public function test_execute_callback_rejects_an_author_slug_that_names_no_user( $author_slug ): void {
		wp_update_user(
			array(
				'ID'            => self::$user_ids['author'],
				'user_nicename' => 'author-slug',
			)
		);

		$this->login_as( 'administrator' );
		$content = new WP_Abilities_Content();

		$result = $content->execute_content_query(
			array(
				'type'        => 'post',
				'author_slug' => $author_slug,
			)
		);

		$this->assertWPError( $result, 'An author_slug that names no user must not silently widen the query to all authors.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An unhonorable author_slug filter should fail closed as an invalid filter.' );
	}

	/**
	 * An author_slug filter is looked up as given, like the `slug` filter of the REST API users
	 * endpoint, so with the database's case-insensitive collation a slug in capitals names the
	 * author too.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_slug_filter_matches_a_slug_in_capitals(): void {
		wp_update_user(
			array(
				'ID'            => self::$user_ids['author'],
				'user_nicename' => 'author-slug',
			)
		);

		$author_post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'publish',
			)
		);
		$other_post_id  = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['editor'],
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'type'        => 'post',
				'author_slug' => 'AUTHOR-SLUG',
				'include'     => array( $author_post_id, $other_post_id ),
				'fields'      => array( 'id', 'author_slug' ),
			)
		);

		$this->assertIsArray( $result, 'The query should succeed.' );
		$this->assertSame( array( $author_post_id ), wp_list_pluck( $result['posts'], 'id' ), 'The filter should return only the author\'s posts.' );
		$this->assertSame( 'author-slug', $result['posts'][0]['author_slug'], 'Each post should return the stored slug.' );
	}

	/**
	 * A parent filter that is not a non-negative integer is rejected rather than
	 * coerced to 0 (top-level).
	 *
	 * Because 0 is a legitimate parent value (top-level posts), a non-integer value
	 * cannot be detected by a numeric bound; it must be rejected on the raw value so
	 * garbage does not silently become a top-level query.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_execute_callback_rejects_non_integer_parent_filter(): void {
		$this->login_as( 'administrator' );
		$content = new WP_Abilities_Content();

		$result = $content->execute_content_query(
			array(
				'type'   => 'page',
				'parent' => 'not-a-number',
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
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_execute_callback_rejects_include_with_no_valid_ids(): void {
		$this->login_as( 'administrator' );

		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$content = new WP_Abilities_Content();

		$result = $content->execute_content_query(
			array(
				'type'    => 'post',
				'include' => array( 0 ),
			)
		);

		$this->assertWPError( $result, 'An include filter with no valid IDs must not fall through to an unrestricted query.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An empty-after-parsing include should fail closed as an invalid filter.' );
	}

	/**
	 * An author_slug filter names only users the current user may see: a user without
	 * published posts is reported like a missing one to a subscriber, but not to an editor,
	 * who can edit their posts.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_slug_filter_only_names_users_the_current_user_may_see(): void {
		$published_id = self::factory()->post->create( array( 'post_author' => self::$user_ids['author'] ) );
		$draft_id     = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author_secondary'],
				'post_status' => 'draft',
			)
		);

		$query = static function ( array $statuses, int $user_id ) {
			return ( new WP_Abilities_Content() )->execute_content_query(
				array(
					'type'        => 'post',
					'status'      => $statuses,
					'author_slug' => get_userdata( $user_id )->user_nicename,
					'fields'      => array( 'id' ),
				)
			);
		};

		$this->login_as( 'subscriber' );
		$public = $query( array( 'publish' ), self::$user_ids['author'] );
		$this->assertIsArray( $public, 'A subscriber should filter by an author with published posts.' );
		$this->assertSame( array( $published_id ), wp_list_pluck( $public['posts'], 'id' ), 'The filter should return that author\'s posts.' );

		$hidden = $query( array( 'publish' ), self::$user_ids['author_secondary'] );
		$this->assertWPError( $hidden, 'A subscriber should not learn that an author without published posts exists.' );
		$this->assertSame( 'content_invalid_filter', $hidden->get_error_code(), 'A hidden author should be reported like a missing one.' );

		$this->login_as( 'editor' );
		$drafts = $query( array( 'draft' ), self::$user_ids['author_secondary'] );
		$this->assertIsArray( $drafts, 'An editor should filter by an author without published posts.' );
		$this->assertSame( array( $draft_id ), wp_list_pluck( $drafts['posts'], 'id' ), 'The filter should return that author\'s drafts.' );
	}

	/**
	 * On multisite, an author_slug filter does not tell an editor of one site whether a user
	 * of another site exists.
	 *
	 * @ticket 66268
	 * @group ms-required
	 * @since 7.2.0
	 */
	public function test_author_slug_filter_hides_users_of_other_sites(): void {
		$other_user_id = self::factory()->user->create( array( 'user_nicename' => 'other-site-user' ) );
		$site_id       = self::factory()->blog->create();

		switch_to_blog( $site_id );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$query = static function ( string $author_slug ) {
			return ( new WP_Abilities_Content() )->execute_content_query(
				array(
					'type'        => 'post',
					'author_slug' => $author_slug,
				)
			);
		};

		$is_member = is_user_member_of_blog( $other_user_id, $site_id );
		$other     = $query( 'other-site-user' );
		$missing   = $query( 'no-such-user' );

		restore_current_blog();

		$this->assertFalse( $is_member, 'Precondition: the other user should not be a member of the site.' );
		$this->assertWPError( $missing, 'Precondition: a slug that names no user should be rejected.' );
		$this->assertWPError( $other, 'An editor should not learn that a user of another site exists.' );
		$this->assertSame( $missing->get_error_code(), $other->get_error_code(), 'A user of another site should be reported like a missing one.' );
	}

	/**
	 * On multisite, an author_slug filter still names an author who is not a member of the
	 * site, such as a super admin, when they have published posts there.
	 *
	 * @ticket 66268
	 * @group ms-required
	 * @since 7.2.0
	 */
	public function test_author_slug_filter_names_authors_who_are_not_site_members(): void {
		$super_admin_id = self::factory()->user->create( array( 'user_nicename' => 'network-author' ) );
		grant_super_admin( $super_admin_id );
		$site_id = self::factory()->blog->create();

		switch_to_blog( $site_id );
		$post_id = self::factory()->post->create( array( 'post_author' => $super_admin_id ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$is_member = is_user_member_of_blog( $super_admin_id, $site_id );
		$result    = ( new WP_Abilities_Content() )->execute_content_query(
			array(
				'type'        => 'post',
				'author_slug' => 'network-author',
				'fields'      => array( 'id' ),
			)
		);

		restore_current_blog();
		revoke_super_admin( $super_admin_id );

		$this->assertFalse( $is_member, 'Precondition: the super admin should not be a member of the site.' );
		$this->assertIsArray( $result, 'An author with published posts on the site should be filterable without being a member.' );
		$this->assertSame( array( $post_id ), wp_list_pluck( $result['posts'], 'id' ), 'The filter should return the author\'s posts on the site.' );
	}
}
