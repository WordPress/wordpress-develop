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
	 * Registers the core/content-query ability and returns the callbacks it was registered with.
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
				if ( 'core/content-query' === $name ) {
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
	 * The input schema models mutually exclusive ID, slug, and query modes as the branches of
	 * `where`, each rejecting the other modes' properties and exposing only marked types.
	 *
	 * The root takes only `where`, `fields` and `pagination`, and has no combinator. The
	 * branches of `where` have no titles.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_input_schema_models_mutually_exclusive_modes(): void {
		$this->register_ability();

		$schema = wp_get_ability( 'core/content-query' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'], 'The input schema should describe an object.' );
		$this->assertFalse( $schema['additionalProperties'], 'The input schema should reject unknown root keys.' );
		$this->assertSame( array( 'where' ), $schema['required'], 'The input schema should require where.' );
		foreach ( array( 'oneOf', 'anyOf', 'allOf' ) as $combinator ) {
			$this->assertArrayNotHasKey( $combinator, $schema, sprintf( 'The input schema should not use %s at its root.', $combinator ) );
		}
		$this->assertSame( array( 'where', 'fields', 'pagination' ), array_keys( $schema['properties'] ), 'The input schema should take where, fields and pagination, in that order.' );

		$where = $schema['properties']['where'];

		$this->assertSame( 'object', $where['type'], 'The where option should describe an object.' );
		foreach ( array( 'properties', 'additionalProperties', 'required', 'title' ) as $keyword ) {
			$this->assertArrayNotHasKey( $keyword, $where, sprintf( 'The where option should not declare %s outside its branches.', $keyword ) );
		}
		$this->assertCount( 3, $where['anyOf'], 'The input schema should expose exactly three modes.' );

		[ $by_id, $by_slug, $query ] = $where['anyOf'];

		$modes = array(
			'by-ID' => $by_id,
			'slug'  => $by_slug,
			'query' => $query,
		);
		foreach ( $modes as $mode => $branch ) {
			$this->assertArrayNotHasKey( 'title', $branch, sprintf( 'The %s mode should not have a title.', $mode ) );
			$this->assertSame( 'object', $branch['type'], sprintf( 'The %s mode should describe an object.', $mode ) );
			$this->assertSame( 'string', $branch['properties']['type']['type'], sprintf( 'The %s mode should take the post type as a string.', $mode ) );
		}

		// All modes reject properties from the other modes.
		$this->assertSame( array( 'id' ), $by_id['required'], 'The by-ID mode should require an ID.' );
		$this->assertSame( array( 'type', 'slug' ), $by_slug['required'], 'The slug mode should require post type and slug.' );
		$this->assertSame( array( 'type' ), $query['required'], 'The query mode should require a post type.' );
		$this->assertFalse( $by_id['additionalProperties'], 'The by-ID mode should reject unrelated properties.' );
		$this->assertFalse( $by_slug['additionalProperties'], 'The slug mode should reject unrelated properties.' );
		$this->assertFalse( $query['additionalProperties'], 'The query mode should reject unrelated properties.' );
		$this->assertSame( array( 'id', 'type' ), array_keys( $by_id['properties'] ), 'The by-ID mode should take an ID and a post type guard, in that order.' );
		$this->assertSame( array( 'type', 'slug' ), array_keys( $by_slug['properties'] ), 'The slug mode should take a post type and a slug, in that order.' );
		$this->assertSame( array( 'type', 'status', 'author_slug', 'parent', 'include' ), array_keys( $query['properties'] ), 'The query mode should take a post type and the query filters, in that order.' );

		// Query-only filters live only in the query mode, not the single-post modes.
		$this->assertArrayHasKey( 'include', $query['properties'], 'The query mode should support included post IDs.' );
		$this->assertArrayHasKey( 'per_page', $schema['properties']['pagination']['properties'], 'The pagination option should support a page size.' );
		$this->assertArrayNotHasKey( 'per_page', $by_id['properties'], 'The by-ID mode should not take a page size, which belongs to the pagination option.' );
		$this->assertArrayNotHasKey( 'include', $by_slug['properties'], 'The slug mode should not accept query-only included IDs.' );
		$this->assertArrayNotHasKey( 'slug', $query['properties'], 'The query mode should not accept slug; slug is a single-post mode.' );

		// Exposed post types appear in all modes that accept `type`.
		$this->assertContains( 'post', $query['properties']['type']['enum'], 'The query mode should include exposed posts.' );
		$this->assertContains( 'page', $by_id['properties']['type']['enum'], 'The by-ID guard should include exposed pages.' );
		$this->assertContains( 'page', $by_slug['properties']['type']['enum'], 'The slug mode should include exposed pages.' );
		$this->assertSameSets( array( 'post', 'page' ), $query['properties']['type']['enum'], 'Only the post types marked show_in_abilities should be exposed.' );
		$this->assertSame( $query['properties']['type']['enum'], $by_id['properties']['type']['enum'], 'The by-ID guard should expose the same post types as the query mode.' );
		$this->assertSame( $query['properties']['type']['enum'], $by_slug['properties']['type']['enum'], 'The slug mode should expose the same post types as the query mode.' );

		$this->assertSame( 'integer', $by_id['properties']['id']['type'], 'The ID should be an integer.' );
		$this->assertSame( 1, $by_id['properties']['id']['minimum'], 'The ID should be positive.' );
		$this->assertSame( 'string', $by_slug['properties']['slug']['type'], 'The slug should be a string.' );
		$this->assertSame( 1, $by_slug['properties']['slug']['minLength'], 'The slug should not be empty.' );

		$status = $query['properties']['status'];

		$this->assertSame( 'array', $status['type'], 'The status filter should take a list.' );
		$this->assertTrue( $status['uniqueItems'], 'The status filter should reject duplicate statuses.' );
		$this->assertSame( 'string', $status['items']['type'], 'The status filter should contain status names.' );
		$this->assertSameSets( array_values( get_post_stati( array( 'internal' => false ) ) ), $status['items']['enum'], 'The status filter should expose the statuses that are not internal.' );

		$this->assertSame( 'string', $query['properties']['author_slug']['type'], 'The author_slug filter should be a string.' );
		$this->assertSame( 1, $query['properties']['author_slug']['minLength'], 'The author_slug filter should not be empty.' );
		$this->assertSame( 'integer', $query['properties']['parent']['type'], 'The parent filter should be an integer.' );
		$this->assertSame( 0, $query['properties']['parent']['minimum'], 'The parent filter should accept 0 for top-level posts.' );

		$this->assertSame( 'array', $query['properties']['include']['type'], 'The include option should take a list.' );
		$this->assertSame( 1, $query['properties']['include']['minItems'], 'The include option should require at least one post ID.' );
		$this->assertSame( 100, $query['properties']['include']['maxItems'], 'The include option should accept at most 100 post IDs.' );
		$this->assertTrue( $query['properties']['include']['uniqueItems'], 'The include option should reject duplicate post IDs.' );
		$this->assertSame( 'integer', $query['properties']['include']['items']['type'], 'The include option should contain post IDs.' );
		$this->assertSame( 1, $query['properties']['include']['items']['minimum'], 'The include option should contain positive post IDs.' );

		$fields = $schema['properties']['fields'];

		$this->assertSame( 'array', $fields['type'], 'The fields option should take a list.' );
		$this->assertTrue( $fields['uniqueItems'], 'The fields option should reject duplicate field paths.' );
		$this->assertArrayNotHasKey( 'minItems', $fields, 'The fields option should accept an empty list, which selects the default fields.' );
		$this->assertSame( 'string', $fields['items']['type'], 'The fields option should contain field paths.' );
		$this->assertSame(
			array(
				'id',
				'type',
				'status',
				'date',
				'date.local',
				'date.gmt',
				'modified',
				'modified.local',
				'modified.gmt',
				'slug',
				'link',
				'title',
				'title.raw',
				'title.rendered',
				'excerpt',
				'excerpt.raw',
				'excerpt.rendered',
				'excerpt.protected',
				'content',
				'content.raw',
				'content.rendered',
				'content.protected',
				'author',
				'author.id',
				'author.name',
				'author.slug',
				'parent',
			),
			$fields['items']['enum'],
			'The fields enum should list every field, each followed by the paths of its sub-fields.'
		);

		$fields_enum = $fields['items']['enum'];
		$this->assertContains( 'type', $fields_enum, 'The fields enum should expose the post type as type.' );
		$this->assertNotContains( 'post_type', $fields_enum, 'The fields enum should not expose the post type as post_type.' );
		$this->assertContains( 'content.raw', $fields_enum, 'The fields enum should include raw content.' );
		$this->assertContains( 'content.rendered', $fields_enum, 'The fields enum should include rendered content.' );
		$this->assertContains( 'title.raw', $fields_enum, 'The fields enum should include raw titles.' );
		$this->assertContains( 'title.rendered', $fields_enum, 'The fields enum should include rendered titles.' );

		$pagination = $schema['properties']['pagination'];

		$this->assertSame( 'object', $pagination['type'], 'The pagination option should describe an object.' );
		$this->assertFalse( $pagination['additionalProperties'], 'The pagination option should reject unknown keys.' );
		$this->assertArrayNotHasKey( 'required', $pagination, 'The pagination option should not require a page or a page size.' );
		$this->assertSame( array( 'page', 'per_page' ), array_keys( $pagination['properties'] ), 'The pagination option should take a page and a page size, in that order.' );
		$this->assertSame( 'integer', $pagination['properties']['page']['type'], 'The page should be an integer.' );
		$this->assertSame( 1, $pagination['properties']['page']['minimum'], 'The page should start at 1.' );
		$this->assertSame( 'integer', $pagination['properties']['per_page']['type'], 'The page size should be an integer.' );
		$this->assertSame( 1, $pagination['properties']['per_page']['minimum'], 'The page size should be at least 1.' );
		$this->assertSame( 100, $pagination['properties']['per_page']['maximum'], 'The page size should be capped at 100.' );

		$input_properties = array(
			'root'       => $schema['properties'],
			'by-ID mode' => $by_id['properties'],
			'slug mode'  => $by_slug['properties'],
			'query mode' => $query['properties'],
			'pagination' => $pagination['properties'],
		);
		foreach ( $input_properties as $location => $properties ) {
			foreach ( $properties as $name => $property ) {
				$this->assertArrayHasKey( 'description', $property, sprintf( 'The %s property %s should have a description.', $location, $name ) );
			}
		}
	}

	/**
	 * Defaults are omitted from the whole input schema, including the branches of `where`,
	 * so the schema can compile in the client-side Abilities API validator. Runtime defaults
	 * are still applied by the ability.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_input_schema_omits_oneof_branch_defaults(): void {
		$this->register_ability();

		$schema     = wp_get_ability( 'core/content-query' )->get_input_schema();
		$query      = $schema['properties']['where']['anyOf'][2];
		$pagination = $schema['properties']['pagination'];

		$this->assertArrayNotHasKey( 'default', $query['properties']['status'], 'Status should rely on runtime defaults, not schema defaults.' );
		$this->assertArrayNotHasKey( 'default', $pagination['properties']['page'], 'Page should rely on runtime defaults, not schema defaults.' );
		$this->assertArrayNotHasKey( 'default', $pagination['properties']['per_page'], 'Per-page should rely on runtime defaults, not schema defaults.' );
		$this->assertSame( array(), $this->find_schema_keywords( $schema, array( 'default' ) ), 'The input schema should not declare a default at any depth.' );
	}

	/**
	 * Query-mode filters cannot be combined with a by-ID lookup: passing a `status` filter
	 * alongside `id` in `where` is rejected outright rather than silently ignored.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_id_mode_rejects_query_only_params(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'id'     => self::$post_ids['published'],
					'status' => array( 'publish' ),
				),
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
				'where' => array(
					'id'   => $post_id,
					'type' => 'post',
				),
			)
		);

		$this->assertIsArray( $result, 'A matching post type guard should allow the by-ID lookup.' );
		$this->assertSame( $post_id, $result['items'][0]['id'], 'The guarded by-ID lookup should return the requested post.' );
		$this->assertCount( 1, $result['items'], 'The guarded by-ID lookup should return only the requested post.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$result['pagination'],
			'The guarded by-ID lookup should report one post on one page.'
		);
	}

	/**
	 * Returns the two lookup modes.
	 *
	 * @return array<string, array{mode: string}> Lookup mode test cases.
	 */
	public function data_lookup_modes(): array {
		return array(
			'by ID'   => array(
				'mode' => 'id',
			),
			'by slug' => array(
				'mode' => 'slug',
			),
		);
	}

	/**
	 * Returns the `where` value that looks up the given post in the given mode.
	 *
	 * @since 7.2.0
	 *
	 * @param string $mode    The lookup mode, `id` or `slug`.
	 * @param int    $post_id The post to look up.
	 * @return array<string, int|string> The `where` value.
	 */
	private function get_lookup_where( string $mode, int $post_id ): array {
		if ( 'id' === $mode ) {
			return array( 'id' => $post_id );
		}

		return array(
			'type' => get_post_type( $post_id ),
			'slug' => get_post_field( 'post_name', $post_id ),
		);
	}

	/**
	 * A lookup returns its post on the first page only.
	 *
	 * A lookup is paginated like a query that matches one post, so a later page is past the
	 * last one. A page size is accepted, and makes no difference.
	 *
	 * @ticket 66268
	 * @dataProvider data_lookup_modes
	 * @since 7.2.0
	 *
	 * @param string $mode The lookup mode, `id` or `slug`.
	 */
	public function test_lookup_returns_the_post_on_the_first_page_only( string $mode ): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];
		$ability = wp_get_ability( 'core/content-query' );
		$lookup  = array(
			'where'  => $this->get_lookup_where( $mode, $post_id ),
			'fields' => array( 'id' ),
		);

		$expected = array(
			'items'      => array(
				array( 'id' => $post_id ),
			),
			'pagination' => array(
				'total'       => 1,
				'total_pages' => 1,
			),
		);

		$first_page = $ability->execute(
			$lookup + array(
				'pagination' => array(
					'page'     => 1,
					'per_page' => 5,
				),
			)
		);

		$this->assertSame( $expected, $first_page, 'The first page of a lookup should hold the requested post.' );

		$without_page = $ability->execute( $lookup + array( 'pagination' => array( 'per_page' => 5 ) ) );

		$this->assertSame( $expected, $without_page, 'A lookup without a page should return the first page.' );

		$second_page = $ability->execute( $lookup + array( 'pagination' => array( 'page' => 2 ) ) );

		$this->assertWPError( $second_page, 'The second page of a lookup should fail rather than return an empty list.' );
		$this->assertSame( 'content_invalid_page_number', $second_page->get_error_code(), 'The second page of a lookup should report a page past the last one.' );
		$this->assertSame( 404, $second_page->get_error_data()['status'], 'The second page of a lookup should be reported as not found.' );
	}

	/**
	 * A later page of a lookup of a post the current user cannot read is refused by the
	 * permission check, rather than reported as a page past the last one.
	 *
	 * @ticket 66268
	 * @dataProvider data_lookup_modes
	 * @since 7.2.0
	 *
	 * @param string $mode The lookup mode, `id` or `slug`.
	 */
	public function test_lookup_of_an_unreadable_post_is_refused_before_the_page_is_checked( string $mode ): void {
		$draft_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_name'   => 'unreadable-lookup',
				'post_status' => 'draft',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'      => $this->get_lookup_where( $mode, $draft_id ),
				'pagination' => array( 'page' => 2 ),
			)
		);

		$this->assertWPError( $result, 'The second page of a lookup of an unreadable post should fail.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'The permission check should refuse an unreadable post before the page is checked.' );
	}

	/**
	 * Returns inputs without a `where`, or whose `where` matches none of the modes.
	 *
	 * @return array<string, array{input: mixed}> Input test cases.
	 */
	public function data_inputs_without_a_valid_where(): array {
		return array(
			'no input'                   => array(
				'input' => null,
			),
			'missing where'              => array(
				'input' => array( 'fields' => array( 'id' ) ),
			),
			'empty where'                => array(
				'input' => array( 'where' => array() ),
			),
			'status without a post type' => array(
				'input' => array( 'where' => array( 'status' => array( 'draft' ) ) ),
			),
			'ID and slug'                => array(
				'input' => array(
					'where' => array(
						'id'   => self::IMPORTED_POST_ID,
						'slug' => 'x',
					),
				),
			),
			'ID and status'              => array(
				'input' => array(
					'where' => array(
						'id'     => self::IMPORTED_POST_ID,
						'status' => array( 'publish' ),
					),
				),
			),
			'ID and author_slug'         => array(
				'input' => array(
					'where' => array(
						'id'          => self::IMPORTED_POST_ID,
						'author_slug' => 'x',
					),
				),
			),
			'ID and parent'              => array(
				'input' => array(
					'where' => array(
						'id'     => self::IMPORTED_POST_ID,
						'parent' => 0,
					),
				),
			),
			'ID and include'             => array(
				'input' => array(
					'where' => array(
						'id'      => self::IMPORTED_POST_ID,
						'include' => array( self::IMPORTED_POST_ID ),
					),
				),
			),
			'slug and status'            => array(
				'input' => array(
					'where' => array(
						'type'   => 'post',
						'slug'   => 'x',
						'status' => array( 'publish' ),
					),
				),
			),
			'unknown key'                => array(
				'input' => array(
					'where' => array(
						'type'  => 'post',
						'bogus' => 1,
					),
				),
			),
		);
	}

	/**
	 * Input without a `where`, or whose `where` matches none of the modes, fails schema
	 * validation.
	 *
	 * A readable post has the ID the lookups name, so input that wrongly passed validation
	 * would return a result rather than fail.
	 *
	 * @ticket 66268
	 * @dataProvider data_inputs_without_a_valid_where
	 * @since 7.2.0
	 *
	 * @param mixed $input The input to send.
	 */
	public function test_input_without_a_valid_where_fails_schema_validation( $input ): void {
		self::factory()->post->create(
			array(
				'import_id'   => self::IMPORTED_POST_ID,
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( $input );

		$this->assertWPError( $result, 'Input without a valid where should not return a result.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Input without a valid where should fail schema validation.' );
	}

	/**
	 * Returns inputs in the old shape, with lookup keys, filters or pages at the root, and an
	 * input with an unknown pagination key.
	 *
	 * @return array<string, array{input: array<string, mixed>}> Input test cases.
	 */
	public function data_inputs_with_keys_outside_their_objects(): array {
		return array(
			'ID at the root'         => array(
				'input' => array( 'id' => self::IMPORTED_POST_ID ),
			),
			'post type at the root'  => array(
				'input' => array( 'type' => 'post' ),
			),
			'page at the root'       => array(
				'input' => array(
					'where' => array( 'type' => 'post' ),
					'page'  => 2,
				),
			),
			'page size at the root'  => array(
				'input' => array(
					'where'    => array( 'type' => 'post' ),
					'per_page' => 2,
				),
			),
			'unknown pagination key' => array(
				'input' => array(
					'where'      => array( 'type' => 'post' ),
					'pagination' => array( 'bogus' => 1 ),
				),
			),
		);
	}

	/**
	 * Input in the old shape, and an unknown pagination key, fail schema validation.
	 *
	 * The root only takes `where`, `fields` and `pagination`. A lookup key, a filter or a page
	 * given outside its object is rejected, rather than ignored in favor of another request.
	 *
	 * @ticket 66268
	 * @dataProvider data_inputs_with_keys_outside_their_objects
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $input The input to send.
	 */
	public function test_old_shape_keys_fail_schema_validation( array $input ): void {
		self::factory()->post->create(
			array(
				'import_id'   => self::IMPORTED_POST_ID,
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( $input );

		$this->assertWPError( $result, 'Old-shape or unknown keys should not return a result.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Old-shape or unknown keys should fail schema validation.' );
	}

	/**
	 * An empty `fields` list and an empty `pagination` object are valid, and select the
	 * default fields on the first page of the default page size.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_empty_fields_and_pagination_select_the_defaults(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		// More posts than the default page size of 10, each with its own date.
		for ( $day = 1; $day <= 11; $day++ ) {
			self::factory()->post->create(
				array(
					'post_status' => 'publish',
					'post_date'   => sprintf( '2026-01-%02d 10:00:00', $day ),
				)
			);
		}

		$ability = wp_get_ability( 'core/content-query' );

		$result = $ability->execute(
			array(
				'where'      => array( 'type' => 'post' ),
				'fields'     => array(),
				'pagination' => array(),
			)
		);

		$this->assertIsArray( $result, 'An empty fields list and an empty pagination object should be valid.' );
		$this->assertCount( 10, $result['items'], 'An empty pagination object should use the default page size.' );
		$this->assertSame( (int) ceil( $result['pagination']['total'] / 10 ), $result['pagination']['total_pages'], 'The page count should follow the default page size.' );
		$this->assertSame( array( 'id', 'type', 'status', 'date', 'slug', 'title' ), array_keys( $result['items'][0] ), 'An empty fields list should select the default fields.' );
		$this->assertSame( array( 'rendered' ), array_keys( $result['items'][0]['title'] ), 'The default fields should hold only the rendered title.' );
		$this->assertSame( array( 'local', 'gmt' ), array_keys( $result['items'][0]['date'] ), 'The default fields should hold both dates.' );

		$explicit = $ability->execute(
			array(
				'where'      => array( 'type' => 'post' ),
				'fields'     => array( 'id', 'type', 'status', 'date', 'slug', 'title.rendered' ),
				'pagination' => array(
					'page'     => 1,
					'per_page' => 10,
				),
			)
		);

		$this->assertSame( $explicit, $result, 'An empty fields list and an empty pagination object should return the default fields of the first page of 10 posts.' );
	}

	/**
	 * The output schema describes the one response shape that single-post lookups and queries
	 * share: the posts as `items`, and their totals as `pagination`.
	 *
	 * No part of it uses a combinator, and the fields enum lists the post properties, each
	 * followed by the paths of its sub-properties.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_output_schema_describes_single_post_and_query_responses(): void {
		$this->register_ability();

		$ability      = wp_get_ability( 'core/content-query' );
		$input_schema = $ability->get_input_schema();
		$schema       = $ability->get_output_schema();
		$post_schema  = $schema['properties']['items']['items'];
		$pagination   = $schema['properties']['pagination'];

		$this->assertSame( 'object', $schema['type'], 'The output schema should describe object responses.' );
		$this->assertFalse( $schema['additionalProperties'], 'The output schema should not allow keys besides items and pagination.' );
		$this->assertSame( array(), $this->find_schema_keywords( $schema, array( 'oneOf', 'anyOf', 'allOf' ) ), 'The output schema should describe single-post and query responses without a combinator at any depth.' );
		$this->assertSame( array( 'items', 'pagination' ), $schema['required'], 'Every response should require the items and the pagination.' );
		$this->assertSame( array( 'items', 'pagination' ), array_keys( $schema['properties'] ), 'The output schema should describe the items and the pagination, in that order.' );
		$this->assertSame( 'array', $schema['properties']['items']['type'], 'The items should be a list.' );

		$this->assertSame( 'object', $post_schema['type'], 'Each post should be described as an object.' );
		$this->assertSame( array( 'id' ), $post_schema['required'], 'Only the always-returned id should be required in a post.' );
		$this->assertFalse( $post_schema['additionalProperties'], 'Returned posts should not allow unknown properties.' );
		$this->assertArrayHasKey( 'type', $post_schema['properties'], 'The post schema should describe the post type as type.' );
		$this->assertArrayNotHasKey( 'post_type', $post_schema['properties'], 'The post schema should not expose the post type as post_type.' );
		$this->assertSame(
			array(
				'id'       => 'integer',
				'type'     => 'string',
				'status'   => 'string',
				'date'     => 'object',
				'modified' => 'object',
				'slug'     => 'string',
				'link'     => 'string',
				'title'    => 'object',
				'excerpt'  => 'object',
				'content'  => 'object',
				'author'   => 'object',
				'parent'   => 'integer',
			),
			wp_list_pluck( $post_schema['properties'], 'type' ),
			'The post schema should describe each post property with its type, in order.'
		);

		$expected_fields = array();
		foreach ( $post_schema['properties'] as $name => $property ) {
			$expected_fields[] = $name;
			foreach ( array_keys( $property['properties'] ?? array() ) as $sub_property ) {
				$expected_fields[] = $name . '.' . $sub_property;
			}
		}

		$this->assertSame(
			$expected_fields,
			$input_schema['properties']['fields']['items']['enum'],
			'The fields enum should match the post output schema properties.'
		);
		$this->assertArrayHasKey( 'raw', $post_schema['properties']['content']['properties'], 'The post schema should describe raw content.' );
		$this->assertArrayHasKey( 'rendered', $post_schema['properties']['content']['properties'], 'The post schema should describe rendered content.' );

		$nullable_string = array( 'string', 'null' );
		$object_types    = array(
			'date'     => array(
				'local' => $nullable_string,
				'gmt'   => $nullable_string,
			),
			'modified' => array(
				'local' => $nullable_string,
				'gmt'   => $nullable_string,
			),
			'title'    => array(
				'raw'      => 'string',
				'rendered' => 'string',
			),
			'excerpt'  => array(
				'raw'       => 'string',
				'rendered'  => 'string',
				'protected' => 'boolean',
			),
			'content'  => array(
				'raw'       => 'string',
				'rendered'  => 'string',
				'protected' => 'boolean',
			),
			'author'   => array(
				'id'   => 'integer',
				'name' => $nullable_string,
				'slug' => $nullable_string,
			),
		);
		foreach ( $object_types as $name => $types ) {
			$object = $post_schema['properties'][ $name ];

			$this->assertFalse( $object['additionalProperties'], sprintf( 'The %s object should not allow unknown keys.', $name ) );
			$this->assertArrayNotHasKey( 'required', $object, sprintf( 'The %s object should not require any key.', $name ) );
			$this->assertSame( $types, wp_list_pluck( $object['properties'], 'type' ), sprintf( 'The %s object should describe each key with its type, in order.', $name ) );
		}
		foreach ( array( 'date', 'modified' ) as $name ) {
			foreach ( array( 'local', 'gmt' ) as $key ) {
				$this->assertSame( 'date-time', $post_schema['properties'][ $name ]['properties'][ $key ]['format'], sprintf( 'The %s.%s value should use the date-time format.', $name, $key ) );
			}
		}

		$this->assertSame( 'object', $pagination['type'], 'The pagination should describe an object.' );
		$this->assertFalse( $pagination['additionalProperties'], 'The pagination should not allow keys besides the totals.' );
		$this->assertSame( array( 'total', 'total_pages' ), $pagination['required'], 'The pagination should require both totals.' );
		$this->assertArrayHasKey( 'total', $pagination['properties'], 'The pagination should describe the total count.' );
		$this->assertArrayHasKey( 'total_pages', $pagination['properties'], 'The pagination should describe page count.' );
		$this->assertSame(
			array(
				'total'       => 'integer',
				'total_pages' => 'integer',
			),
			wp_list_pluck( $pagination['properties'], 'type' ),
			'The pagination should describe the total and the page count as integers, in that order.'
		);
	}

	/**
	 * Returns the paths of the given keywords anywhere in a schema.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $schema   The schema to search.
	 * @param list<string> $keywords The keywords to look for.
	 * @param string       $path     Optional. The path of the schema in its root schema. Default empty string.
	 * @return list<string> The paths of the keywords, such as `properties.where.anyOf`.
	 */
	private function find_schema_keywords( array $schema, array $keywords, string $path = '' ): array {
		$found = array();

		foreach ( $schema as $key => $value ) {
			$key_path = '' === $path ? (string) $key : $path . '.' . $key;

			if ( in_array( $key, $keywords, true ) ) {
				$found[] = $key_path;
			}

			if ( is_array( $value ) ) {
				$found = array_merge( $found, $this->find_schema_keywords( $value, $keywords, $key_path ) );
			}
		}

		return $found;
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

		// Query mode is the third `where` branch; its `type` enum lists exposed types.
		$enum = wp_get_ability( 'core/content-query' )->get_input_schema()['properties']['where']['anyOf'][2]['properties']['type']['enum'];
		$this->assertContains( 'content_cpt', $enum, 'Custom post types marked show_in_abilities should appear in the query enum.' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'content_cpt',
				'post_status' => 'publish',
			)
		);

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'type' => 'content_cpt' ) ) );
		$ids    = wp_list_pluck( $result['items'], 'id' );

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

		// Query mode is the third `where` branch; its `type` enum lists exposed types.
		$enum = wp_get_ability( 'core/content-query' )->get_input_schema()['properties']['where']['anyOf'][2]['properties']['type']['enum'];
		$this->assertNotContains( 'content_cpt', $enum, 'The post type should not appear in the query enum.' );

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => $post_id ) ) );
		$this->assertWPError( $result, 'A post of the post type should not be readable by ID.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'The by-ID lookup should be denied.' );
	}

	/**
	 * A schema filter can expose a post type that is registered after the ability, by adding it
	 * to the `type` enum of every `where` branch.
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

			foreach ( $args['input_schema']['properties']['where']['anyOf'] as $index => $mode ) {
				$args['input_schema']['properties']['where']['anyOf'][ $index ]['properties']['type']['enum'][] = 'late_cpt';
			}

			return $args;
		};
		add_filter( 'wp_register_ability_args', $amend_schema, 10, 2 );
		$this->register_ability();

		$enum = wp_get_ability( 'core/content-query' )->get_input_schema()['properties']['where']['anyOf'][2]['properties']['type']['enum'];
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
				'where'  => array( 'type' => 'late_cpt' ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['items'], 'id' ), 'The late post type should be queryable after it becomes exposed.' );
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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => $post_id ) ) );

		$this->assertIsArray( $result, 'The by-ID lookup should return a post array.' );
		$this->assertSame( $post_id, $result['items'][0]['id'], 'The by-ID lookup should return the requested post.' );
		$this->assertSame( 'Hello Content', $result['items'][0]['title']['rendered'], 'Rendered titles should be returned by default.' );
		$this->assertSame(
			array( 'id', 'type', 'status', 'date', 'slug', 'title' ),
			array_keys( $result['items'][0] ),
			'Omitted fields should return the lean default field set.'
		);
		$this->assertSame( array( 'rendered' ), array_keys( $result['items'][0]['title'] ), 'The default title should hold only the rendered title.' );
		$this->assertSame( array( 'local', 'gmt' ), array_keys( $result['items'][0]['date'] ), 'The default date should hold the local and the GMT date.' );
		$this->assertCount( 1, $result['items'], 'The by-ID lookup should return only the requested post.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$result['pagination'],
			'The by-ID lookup should report one post on one page.'
		);
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
			'where'  => array( 'id' => $post_id ),
			'fields' => array( 'id', 'title.rendered' ),
		);

		$this->assertTrue( $ability->validate_input( $input ), 'Object input should pass the registered schema.' );

		$result = $ability->execute( $input );

		$this->assertIsArray( $result, 'Object input should execute the by-ID lookup.' );
		$this->assertSame( $post_id, $result['items'][0]['id'], 'Object input should preserve the requested post ID.' );
		$this->assertSame( 'Hello Content', $result['items'][0]['title']['rendered'], 'Object input should preserve the requested fields.' );
		$this->assertSame( array( 'id', 'title' ), array_keys( $result['items'][0] ), 'Object input should use the requested field projection.' );
		$this->assertSame( array( 'rendered' ), array_keys( $result['items'][0]['title'] ), 'Object input should project the title to the requested path.' );
	}

	/**
	 * Object `where` and `pagination` values behave like their array forms.
	 *
	 * Schema validation accepts a `stdClass` for every object in the input, so neither value
	 * may be discarded, whether the input itself is an array or an object.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_object_where_and_pagination_behave_like_arrays(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );
		$post_id = self::$post_ids['published_content'];

		$lookup = array(
			'where'  => (object) array( 'id' => $post_id ),
			'fields' => array( 'id' ),
		);

		$expected_lookup = array(
			'items'      => array(
				array( 'id' => $post_id ),
			),
			'pagination' => array(
				'total'       => 1,
				'total_pages' => 1,
			),
		);

		$this->assertSame( $expected_lookup, $ability->execute( $lookup ), 'An object where in array input should run the lookup.' );
		$this->assertSame( $expected_lookup, $ability->execute( (object) $lookup ), 'An object where in object input should run the lookup.' );

		$older_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$newer_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-02-01 10:00:00',
			)
		);

		// Posts are ordered newest first, so the second page of one post holds the older post.
		$query = array(
			'where'      => (object) array(
				'type'    => 'post',
				'include' => array( $newer_id, $older_id ),
			),
			'fields'     => array( 'id' ),
			'pagination' => (object) array(
				'page'     => 2,
				'per_page' => 1,
			),
		);

		$expected_query = array(
			'items'      => array(
				array( 'id' => $older_id ),
			),
			'pagination' => array(
				'total'       => 2,
				'total_pages' => 2,
			),
		);

		$this->assertSame( $expected_query, $ability->execute( $query ), 'Object where and pagination values in array input should filter and page the query.' );
		$this->assertSame( $expected_query, $ability->execute( (object) $query ), 'Object where and pagination values in object input should filter and page the query.' );
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', 'type', 'content.rendered', 'content.raw' ),
			)
		);

		$this->assertSame( $post_id, $result['items'][0]['id'], 'The by-ID lookup should return the requested post.' );
		$this->assertSame( 'post', $result['items'][0]['type'], 'The by-ID lookup should return the post type as type.' );
		$this->assertStringContainsString( 'Body here.', $result['items'][0]['content']['rendered'], 'Explicit content fields should include rendered content.' );
		$this->assertSame( 'Body here.', $result['items'][0]['content']['raw'], 'Explicit content fields should include raw content.' );
		$this->assertCount( 1, $result['items'], 'The by-ID lookup should return only the requested post.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$result['pagination'],
			'The by-ID lookup should report one post on one page.'
		);
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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) ) );

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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => 2 ** 64 + $aliased_id ) ) );

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
				'where' => array(
					'id'   => $post_id,
					'type' => 'page',
				),
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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => $post_id ) ) );

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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => $post_id ) ) );

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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => $post_id ) ) );

		$this->assertIsArray( $result, 'Editors should be able to access posts they can edit even when the status is not publicly viewable.' );
		$this->assertSame( $post_id, $result['items'][0]['id'], 'The editable post should be returned.' );
		$this->assertSame( 'Hidden public status', $result['items'][0]['title']['rendered'], 'The editable post should include normal default fields.' );
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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => $child_id ) ) );

		$this->assertIsArray( $result, 'Inherited posts should be readable when their parent is readable.' );
		$this->assertSame( $child_id, $result['items'][0]['id'], 'The inherited child should be returned.' );
		$this->assertSame( 'Inherited child', $result['items'][0]['title']['rendered'], 'The inherited child should include normal default fields.' );
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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => $post_id ) ) );

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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'type' => 'post' ) ) );
		$ids    = wp_list_pluck( $result['items'], 'id' );

		$this->assertContains( $published, $ids, 'Published posts should be returned by default.' );
		$this->assertNotContains( $draft, $ids, 'Draft posts should not be returned by default.' );
	}

	/**
	 * Query results are ordered by post date, newest first, whatever order `where.include`
	 * uses.
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
				'where'  => array(
					'type'    => 'post',
					// Deliberately neither date order nor ID order.
					'include' => array( $middle, $newest, $oldest ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame(
			array( $newest, $middle, $oldest ),
			wp_list_pluck( $result['items'], 'id' ),
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
				'where'  => array(
					'type'    => 'post',
					'include' => array( $page_id, $post_id ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['items'], 'id' ), 'Include should not leak posts from other post types.' );
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
				'where'  => array(
					'type'    => 'post',
					'include' => $post_id,
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['items'], 'id' ), 'A single included post ID should limit the query to that post.' );
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
				'where'  => array(
					'type'    => 'post',
					'status'  => array( 'draft' ),
					'include' => array( $draft_a, $draft_b ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $draft_b ), wp_list_pluck( $result['items'], 'id' ), 'Include should not bypass row-level draft permissions.' );
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
				'where'  => array(
					'type'    => 'post',
					'status'  => array( 'draft' ),
					'include' => array( $draft ),
				),
				'fields' => array( 'id', 'type', 'status', 'content.rendered', 'content.raw' ),
			)
		);

		$this->assertSame( array( $draft ), wp_list_pluck( $result['items'], 'id' ), 'The draft query should return only the included draft.' );
		$this->assertSame( 'post', $result['items'][0]['type'], 'Query responses should return the post type as type.' );
		$this->assertSame( 'draft', $result['items'][0]['status'], 'The draft query should expose the requested draft status.' );
		$this->assertStringContainsString( 'Draft body for content fields.', $result['items'][0]['content']['rendered'], 'Draft query results should include rendered content when requested.' );
		$this->assertSame( 'Draft body for content fields.', $result['items'][0]['content']['raw'], 'Draft query results should include raw content when requested.' );
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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'slug' => 'whatever' ) ) );

		$this->assertWPError( $result, 'Slug queries without a post type should fail validation.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Invalid slug queries should return an input error.' );
	}

	/**
	 * Slug mode returns a single post when paired with a post type.
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
				'where' => array(
					'type' => 'post',
					'slug' => 'content-slug-mode',
				),
			)
		);

		$this->assertIsArray( $result, 'The slug lookup should return a post array.' );
		$this->assertSame( $post_id, $result['items'][0]['id'], 'The slug lookup should return the requested post.' );
		$this->assertSame( 'content-slug-mode', $result['items'][0]['slug'], 'The slug lookup should return the matching slug.' );
		$this->assertCount( 1, $result['items'], 'The slug lookup should return only the requested post.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$result['pagination'],
			'The slug lookup should report one post on one page.'
		);
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
				'where'  => array(
					'type' => 'post',
					'slug' => 'content-slug-fields',
				),
				'fields' => array( 'id', 'type', 'slug', 'content.rendered', 'content.raw' ),
			)
		);

		$this->assertSame( $post_id, $result['items'][0]['id'], 'The slug lookup should return the requested post.' );
		$this->assertSame( 'post', $result['items'][0]['type'], 'The slug lookup should return the post type as type.' );
		$this->assertSame( 'content-slug-fields', $result['items'][0]['slug'], 'The slug lookup should return the matching slug.' );
		$this->assertStringContainsString( 'Slug body for content fields.', $result['items'][0]['content']['rendered'], 'Slug lookups should include rendered content when requested.' );
		$this->assertSame( 'Slug body for content fields.', $result['items'][0]['content']['raw'], 'Slug lookups should include raw content when requested.' );
		$this->assertCount( 1, $result['items'], 'The slug lookup should return only the requested post.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$result['pagination'],
			'The slug lookup should report one post on one page.'
		);
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
				'where'  => array(
					'type' => 'post',
					'slug' => 'slug-not-bounded',
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The published post should resolve even though the drafts fill more than a page.' );
		$this->assertSame( $published, $result['items'][0]['id'], 'The readable published post should still resolve.' );
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
				'where' => array(
					'type' => 'post',
					'slug' => '0',
				),
			)
		);

		$this->assertIsArray( $result, 'The slug lookup should return a post array.' );
		$this->assertSame( $post_id, $result['items'][0]['id'], 'Slug mode should resolve the literal "0" slug to the post.' );
		$this->assertCount( 1, $result['items'], 'A "0" slug should not fall through to a query of every post.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$result['pagination'],
			'A "0" slug lookup should report one post on one page.'
		);
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
				'where' => array(
					'type'   => 'post',
					'slug'   => 'content-slug-mode',
					'parent' => 0,
				),
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
				'where' => array(
					'type' => 'post',
					'slug' => 'shadowed-slug',
				),
			)
		);

		$this->assertIsArray( $result, 'The slug lookup should succeed.' );
		$this->assertSame( $published_id, $result['items'][0]['id'], 'The slug lookup should resolve to the published post.' );
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
				'where' => array(
					'type' => 'post',
					'slug' => 'draft-only-slug',
				),
			)
		);

		$this->assertIsArray( $result, 'The draft author should resolve their own draft by slug.' );
		$this->assertSame( $draft_id, $result['items'][0]['id'], 'The draft author should receive their own draft.' );

		$this->login_as( 'subscriber' );

		$denied = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type' => 'post',
					'slug' => 'draft-only-slug',
				),
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
				'fields' => array( 'id', 'content.raw' ),
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
	 * @param list<string> $fields The field paths to request.
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
				'where'  => array(
					'type' => 'post',
					'slug' => 'shared-slug',
				),
				'fields' => $fields,
			)
		);

		$this->assertIsArray( $result, 'The slug lookup should succeed.' );
		$this->assertSame( $post_id, $result['items'][0]['id'], 'The slug lookup should resolve to the post of the requested type.' );
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
				'where' => array(
					'id'      => self::$post_ids['published'],
					'include' => array( self::$post_ids['published'] ),
				),
			)
		);
		$by_slug = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type'    => 'post',
					'slug'    => 'whatever',
					'include' => array( self::$post_ids['published'] ),
				),
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', 'title.rendered' ),
			)
		);

		$this->assertSame(
			array( 'id', 'title' ),
			array_keys( $result['items'][0] ),
			'The fields filter should limit the response to exactly the requested keys.'
		);
		$this->assertSame( array( 'rendered' ), array_keys( $result['items'][0]['title'] ), 'The fields filter should limit the title to the requested path.' );
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'title.rendered' ),
			)
		);

		$this->assertSame( array( 'id', 'title' ), array_keys( $result['items'][0] ), 'The ID should be returned along with the requested fields.' );
		$this->assertSame( array( 'rendered' ), array_keys( $result['items'][0]['title'] ), 'The title should hold only the requested path.' );
		$this->assertSame( $post_id, $result['items'][0]['id'], 'The returned ID should be the requested post.' );
	}

	/**
	 * An unknown requested field path fails schema validation.
	 *
	 * Unlike fields a post type does not support, which are omitted per post, a field path
	 * that is not part of the supported set is rejected before the ability executes.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_unknown_requested_field_fails_schema_validation(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => self::$post_ids['published_content'] ),
				'fields' => array( 'id', 'bogus_field' ),
			)
		);

		$this->assertWPError( $result, 'An unknown requested field should fail the request.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Unknown fields should use the invalid input error.' );
	}

	/**
	 * Returns field paths that each name one sub-field.
	 *
	 * @return array<string, array{field: string}> Field path test cases.
	 */
	public function data_sub_field_paths(): array {
		return array(
			'title.rendered'    => array(
				'field' => 'title.rendered',
			),
			'date.gmt'          => array(
				'field' => 'date.gmt',
			),
			'modified.local'    => array(
				'field' => 'modified.local',
			),
			'author.name'       => array(
				'field' => 'author.name',
			),
			'content.protected' => array(
				'field' => 'content.protected',
			),
		);
	}

	/**
	 * A sub-field path returns only that sub-field, inside its object.
	 *
	 * @ticket 66268
	 * @dataProvider data_sub_field_paths
	 * @since 7.2.0
	 *
	 * @param string $field The field path to request, such as `title.rendered`.
	 */
	public function test_sub_field_path_returns_only_that_sub_field( string $field ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		list( $object, $key ) = explode( '.', $field );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => self::$post_ids['limited_role_content'] ),
				'fields' => array( $field ),
			)
		);

		$this->assertIsArray( $result, 'The lookup should succeed.' );
		$this->assertSame( array( 'id', $object ), array_keys( $result['items'][0] ), 'Only the ID and the object that holds the sub-field should be returned.' );
		$this->assertSame( array( $key ), array_keys( $result['items'][0][ $object ] ), 'The object should hold only the requested sub-field.' );
	}

	/**
	 * Returns the fields that are objects, with the keys each one holds.
	 *
	 * @return array<string, array{field: string, keys: list<string>}> Object field test cases.
	 */
	public function data_object_fields(): array {
		return array(
			'title'    => array(
				'field' => 'title',
				'keys'  => array( 'raw', 'rendered' ),
			),
			'excerpt'  => array(
				'field' => 'excerpt',
				'keys'  => array( 'raw', 'rendered', 'protected' ),
			),
			'content'  => array(
				'field' => 'content',
				'keys'  => array( 'raw', 'rendered', 'protected' ),
			),
			'date'     => array(
				'field' => 'date',
				'keys'  => array( 'local', 'gmt' ),
			),
			'modified' => array(
				'field' => 'modified',
				'keys'  => array( 'local', 'gmt' ),
			),
			'author'   => array(
				'field' => 'author',
				'keys'  => array( 'id', 'name', 'slug' ),
			),
		);
	}

	/**
	 * A whole object returns every key it holds to a user who can edit the post.
	 *
	 * @ticket 66268
	 * @dataProvider data_object_fields
	 * @since 7.2.0
	 *
	 * @param string       $field The object field to request.
	 * @param list<string> $keys  The keys the object should hold, in order.
	 */
	public function test_whole_object_returns_every_key_to_a_user_who_can_edit_the_post( string $field, array $keys ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => self::$post_ids['limited_role_content'] ),
				'fields' => array( $field ),
			)
		);

		$this->assertIsArray( $result, 'The lookup should succeed.' );
		$this->assertSame( array( 'id', $field ), array_keys( $result['items'][0] ), 'Only the ID and the requested object should be returned.' );
		$this->assertSame( $keys, array_keys( $result['items'][0][ $field ] ), 'The object should hold every key, in order.' );
	}

	/**
	 * Whole title, excerpt and content objects leave out the raw values for users who cannot
	 * edit the post, rather than failing the request.
	 *
	 * Only explicit raw paths ask for edit access, so the objects return every sub-field the
	 * user may see.
	 *
	 * @ticket 66268
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 * @since 7.2.0
	 *
	 * @param string $role The role to test.
	 */
	public function test_whole_objects_omit_raw_values_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => self::$post_ids['limited_role_content'] ),
				'fields' => array( 'title', 'excerpt', 'content' ),
			)
		);

		$this->assertIsArray( $result, 'Whole objects should not require edit access.' );
		$this->assertSame( array( 'rendered' ), array_keys( $result['items'][0]['title'] ), 'The title should hold only the rendered title.' );
		$this->assertSame( array( 'rendered', 'protected' ), array_keys( $result['items'][0]['excerpt'] ), 'The excerpt should hold the rendered excerpt and the protected flag.' );
		$this->assertSame( array( 'rendered', 'protected' ), array_keys( $result['items'][0]['content'] ), 'The content should hold the rendered content and the protected flag.' );
	}

	/**
	 * The whole content of a password-protected post withholds the content from users who
	 * cannot edit the post, and returns it to users who can.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_whole_content_of_a_password_protected_post_follows_edit_access(): void {
		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );
		$input   = array(
			'where'  => array( 'id' => self::$post_ids['password_protected'] ),
			'fields' => array( 'content' ),
		);

		$this->login_as( 'subscriber' );
		$withheld = $ability->execute( $input );

		$this->assertIsArray( $withheld, 'A subscriber should read the protected post.' );
		$this->assertSame(
			array(
				'rendered'  => '',
				'protected' => true,
			),
			$withheld['items'][0]['content'],
			'A subscriber should receive an empty rendered content and the protected flag.'
		);

		$this->login_as( 'editor' );
		$unlocked = $ability->execute( $input );

		$this->assertIsArray( $unlocked, 'An editor should read the protected post.' );

		$content = $unlocked['items'][0]['content'];

		$this->assertSame( array( 'raw', 'rendered', 'protected' ), array_keys( $content ), 'An editor should receive every content key.' );
		$this->assertSame( 'Top secret body.', $content['raw'], 'An editor should receive the raw content.' );
		$this->assertStringContainsString( 'Top secret body.', $content['rendered'], 'An editor should receive the real rendered content.' );
		$this->assertTrue( $content['protected'], 'The protected flag should still be set for an editor.' );
	}

	/**
	 * A query for the whole content is not narrowed to the posts the current user can edit,
	 * and holds the raw content only on those posts.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_for_whole_content_is_not_narrowed_to_editable_posts(): void {
		$own_id   = self::factory()->post->create(
			array(
				'post_author'  => self::$user_ids['author'],
				'post_content' => 'Own body.',
				'post_status'  => 'publish',
			)
		);
		$other_id = self::factory()->post->create(
			array(
				'post_author'  => self::$user_ids['author_secondary'],
				'post_content' => 'Other body.',
				'post_status'  => 'publish',
			)
		);

		$this->login_as( 'author' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type'    => 'post',
					'include' => array( $own_id, $other_id ),
				),
				'fields' => array( 'content' ),
			)
		);

		$this->assertIsArray( $result, 'An author should query the whole content.' );

		$posts = array_column( $result['items'], null, 'id' );

		$this->assertSameSets( array( $own_id, $other_id ), array_keys( $posts ), 'The query should return the posts of other users as well as the author\'s own.' );
		$this->assertSame( 'Own body.', $posts[ $own_id ]['content']['raw'], 'The author\'s own post should hold the raw content.' );
		$this->assertArrayNotHasKey( 'raw', $posts[ $other_id ]['content'], 'Another user\'s post should not hold the raw content.' );
		$this->assertStringContainsString( 'Other body.', $posts[ $other_id ]['content']['rendered'], 'Another user\'s post should hold the rendered content.' );
	}

	/**
	 * A query for an explicit raw path is narrowed to the posts the current user can edit.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_for_an_explicit_raw_path_is_narrowed_to_editable_posts(): void {
		$own_id   = self::factory()->post->create(
			array(
				'post_author'  => self::$user_ids['author'],
				'post_content' => 'Own body.',
				'post_status'  => 'publish',
			)
		);
		$other_id = self::factory()->post->create(
			array(
				'post_author'  => self::$user_ids['author_secondary'],
				'post_content' => 'Other body.',
				'post_status'  => 'publish',
			)
		);

		$this->login_as( 'author' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type'    => 'post',
					'include' => array( $own_id, $other_id ),
				),
				'fields' => array( 'content.raw' ),
			)
		);

		$this->assertIsArray( $result, 'An author, who can edit posts, should query an explicit raw path.' );
		$this->assertSame( array( $own_id ), wp_list_pluck( $result['items'], 'id' ), 'The query should return only the author\'s own post, not another user\'s post.' );
		$this->assertSame( 'Own body.', $result['items'][0]['content']['raw'], 'The author\'s own post should hold the requested raw content.' );
	}

	/**
	 * An explicit raw path asks for edit access even next to its whole object, and a rendered
	 * path next to its whole object returns the whole object.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_whole_object_with_one_of_its_paths_follows_the_path_rules(): void {
		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );
		$post_id = self::$post_ids['limited_role_content'];

		$this->login_as( 'subscriber' );
		$denied = $ability->execute(
			array(
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'title', 'title.raw' ),
			)
		);

		$this->assertWPError( $denied, 'A raw title path next to the whole title should still ask for edit access.' );
		$this->assertSame( 'ability_invalid_permissions', $denied->get_error_code(), 'A subscriber should be refused the raw title path.' );

		$this->login_as( 'editor' );
		$result = $ability->execute(
			array(
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'title', 'title.rendered' ),
			)
		);

		$this->assertIsArray( $result, 'An editor should read the whole title.' );
		$this->assertSame(
			array(
				'raw'      => 'Readable title',
				'rendered' => 'Readable title',
			),
			$result['items'][0]['title'],
			'A rendered title path next to the whole title should return the whole title.'
		);
	}

	/**
	 * The author object holds the author's ID, display name and slug, and keeps the stored ID
	 * with a null name and slug when the user no longer exists.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_object_describes_the_author(): void {
		$author_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'display_name'  => 'Content Author',
				'user_nicename' => 'content-author',
			)
		);
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => $author_id,
				'post_status' => 'publish',
			)
		);

		// No user has this ID, as when the author was deleted after writing the post.
		$orphan_id = self::factory()->post->create(
			array(
				'post_author' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
				'post_status' => 'publish',
			)
		);

		$this->assertFalse( get_userdata( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ), 'Precondition: no user should have the stored author ID.' );

		$this->login_as( 'administrator' );
		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );

		$result = $ability->execute(
			array(
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'author' ),
			)
		);
		$orphan = $ability->execute(
			array(
				'where'  => array( 'id' => $orphan_id ),
				'fields' => array( 'author' ),
			)
		);

		$this->assertSame(
			array(
				'id'   => $author_id,
				'name' => 'Content Author',
				'slug' => 'content-author',
			),
			$result['items'][0]['author'],
			'The author object should hold the author\'s ID, display name and slug.'
		);
		$this->assertSame(
			array(
				'id'   => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
				'name' => null,
				'slug' => null,
			),
			$orphan['items'][0]['author'],
			'The author object should keep the stored ID, with a null name and slug, when the user no longer exists.'
		);
	}

	/**
	 * Requested objects that do not apply to the post type are left out, and the post keeps its
	 * ID.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_objects_that_do_not_apply_are_left_out(): void {
		register_post_type(
			'untitled_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'editor' ),
			)
		);

		$this->assertFalse( post_type_supports( 'untitled_cpt', 'title' ), 'Precondition: the post type should not support titles.' );
		$this->assertFalse( post_type_supports( 'untitled_cpt', 'author' ), 'Precondition: the post type should not support authors.' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'untitled_cpt',
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'title', 'author' ),
			)
		);

		$this->assertIsArray( $result, 'The lookup should succeed.' );
		$this->assertSame( array( 'id' => $post_id ), $result['items'][0], 'Objects that do not apply should be left out, and the post should keep its ID.' );
	}

	/**
	 * Returned keys follow the order of the fields enum, whatever order the fields are
	 * requested in, both at the top level and inside each object.
	 *
	 * The second lookup requests every top-level field in reverse order, on a hierarchical
	 * post type that supports every object, so that every top-level field applies.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_returned_keys_follow_the_fields_order(): void {
		register_post_type(
			'every_field_cpt',
			array(
				'public'            => true,
				'hierarchical'      => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor', 'author', 'excerpt' ),
			)
		);

		$parent_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'every_field_cpt',
				'post_status' => 'publish',
			)
		);
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'every_field_cpt',
				'post_status' => 'publish',
				'post_parent' => $parent_id,
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => self::$post_ids['limited_role_content'] ),
				'fields' => array( 'title', 'id', 'date.gmt', 'content.protected', 'content.raw' ),
			)
		);

		$this->assertIsArray( $result, 'The lookup should succeed.' );
		$this->assertSame( array( 'id', 'date', 'title', 'content' ), array_keys( $result['items'][0] ), 'Top-level keys should follow the fields order.' );
		$this->assertSame( array( 'raw', 'protected' ), array_keys( $result['items'][0]['content'] ), 'Keys inside an object should follow the fields order.' );

		// Every top-level field of the fields enum, in its order.
		$top_level_fields = array( 'id', 'type', 'status', 'date', 'modified', 'slug', 'link', 'title', 'excerpt', 'content', 'author', 'parent' );

		$every_field = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => $post_id ),
				'fields' => array_reverse( $top_level_fields ),
			)
		);

		$this->assertIsArray( $every_field, 'The lookup of every top-level field should succeed.' );
		$this->assertSame( $top_level_fields, array_keys( $every_field['items'][0] ), 'Every top-level key should be returned in the fields order, whatever order the fields are requested in.' );
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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'type' => 'post' ) ) );

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
				'where'  => array( 'type' => 'post' ),
				'fields' => array( 'id', 'title.rendered', 'content.rendered' ),
			)
		);
		$ids    = wp_list_pluck( $result['items'], 'id' );

		$this->assertContains( $post_id, $ids, 'Subscribers should be able to query readable published posts.' );
		$post_index = array_search( $post_id, $ids, true );
		$this->assertIsInt( $post_index, 'The published post should be present in the subscriber query response.' );
		$post = $result['items'][ $post_index ];
		$this->assertSame( 'Hello Content', $post['title']['rendered'], 'Subscribers should receive rendered titles.' );
		$this->assertStringContainsString( 'Body here.', $post['content']['rendered'], 'Subscribers should receive rendered content.' );
		$this->assertArrayNotHasKey( 'raw', $post['content'], 'Subscribers should not receive raw content without edit access.' );
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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => $post_id ) ) );

		$this->assertIsArray( $result, 'Subscribers should be able to fetch a readable published post by ID.' );
		$this->assertSame( 'Hello Content', $result['items'][0]['title']['rendered'], 'Subscribers should receive the rendered title.' );
		$this->assertArrayNotHasKey( 'raw', $result['items'][0]['title'], 'Subscribers should not receive raw titles without edit access.' );
		$this->assertArrayNotHasKey( 'content', $result['items'][0], 'Subscribers should not receive raw content without edit access.' );
		$this->assertSame(
			array( 'id', 'type', 'status', 'date', 'slug', 'title' ),
			array_keys( $result['items'][0] ),
			'Rendered content should require an explicit field request.'
		);
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
				'where'  => array( 'type' => 'post' ),
				'fields' => array( 'content.raw' ),
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'content.raw' ),
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

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => array( 'id' => $post_id ) ) );

		$this->assertIsArray( $result, 'The readable published post should be returned.' );
		$this->assertSame( 'Readable title', $result['items'][0]['title']['rendered'], 'Rendered title should remain visible.' );
		$this->assertArrayNotHasKey( 'raw', $result['items'][0]['title'], 'Raw title should be omitted.' );
		$this->assertArrayNotHasKey( 'excerpt', $result['items'][0], 'Raw excerpt should be omitted.' );
		$this->assertArrayNotHasKey( 'content', $result['items'][0], 'Raw content should be omitted.' );
		$this->assertSame(
			array( 'id', 'type', 'status', 'date', 'slug', 'title' ),
			array_keys( $result['items'][0] ),
			'Rendered content should be omitted from the lean default field set.'
		);
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'content.raw' ),
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
				'where' => array(
					'type'   => 'post',
					'status' => array( 'draft' ),
				),
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
				'where' => array(
					'type'   => 'post',
					'status' => array( 'private' ),
				),
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
				'where' => array(
					'type'   => 'post',
					'status' => array( 'draft' ),
				),
			)
		);
		$ids    = wp_list_pluck( $result['items'], 'id' );

		$this->assertContains( $draft_b, $ids, 'Authors should see their own drafts.' );
		$this->assertNotContains( $draft_a, $ids, 'Authors should not see another author\'s drafts.' );
	}

	/**
	 * Query totals mirror WP_Query even when row-level permissions withhold rows.
	 *
	 * This matches the REST posts controller: `items` only contains rows the current user can
	 * read, while `pagination.total` and `pagination.total_pages` describe the underlying query.
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
				'where'      => array(
					'type'   => 'post',
					'status' => array( 'draft' ),
				),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 1 ),
			)
		);

		$this->assertSame( array( $draft_b ), wp_list_pluck( $result['items'], 'id' ), 'Authors should receive only drafts they can read.' );
		$this->assertNotContains( $draft_a, wp_list_pluck( $result['items'], 'id' ), 'Rows withheld by row-level permissions should not be returned.' );
		$this->assertGreaterThan( count( $result['items'] ), $result['pagination']['total'], 'Totals may include rows withheld by row-level permission checks.' );
		$this->assertSame( 2, $result['pagination']['total_pages'], 'Page counts should be based on the underlying query total, matching REST behavior.' );
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
				'where' => array(
					'type'   => 'post',
					'parent' => 0,
				),
			)
		);

		$this->assertWPError( $result, 'The parent filter should be rejected for non-hierarchical post types.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'Unsupported parent filters should return a filter error.' );
		$this->assertSame(
			array( 'where[parent]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the unsupported parent filter to the error message.'
		);
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
				'where' => array(
					'type'   => 'page',
					'parent' => $parent_id,
				),
			)
		);
		$ids    = wp_list_pluck( $result['items'], 'id' );

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
				'where' => array(
					'type'        => 'no_author_cpt',
					'author_slug' => get_userdata( self::$user_ids['author'] )->user_nicename,
				),
			)
		);

		$this->assertWPError( $result, 'The author_slug filter should be rejected for post types without author support.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'Unsupported author filters should return a filter error.' );
		$this->assertSame(
			array( 'where[author_slug]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the unsupported author_slug filter to the error message.'
		);
	}

	/**
	 * The author_slug filter narrows queries to posts by the given author, and each post
	 * returns its author's slug as `author.slug`.
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
				'where'      => array(
					'type'        => 'post',
					'author_slug' => $author_slug,
				),
				'fields'     => array( 'id', 'author.slug' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);
		$ids    = wp_list_pluck( $result['items'], 'id' );

		$this->assertContains( $mine_id, $ids, 'The author_slug filter should include the author\'s posts.' );
		$this->assertNotContains( $other_id, $ids, 'The author_slug filter should exclude other authors\' posts.' );
		$this->assertSame( array( $author_slug ), array_unique( wp_list_pluck( wp_list_pluck( $result['items'], 'author' ), 'slug' ) ), 'Each post should return its author\'s slug.' );
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
	 * @param list<string> $fields The field paths to request.
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
				'where'  => array(
					'type'    => 'post',
					'include' => $ids,
				),
				'fields' => $fields,
			)
		);

		$this->assertSame( 3, $result['pagination']['total'], 'Precondition: the query filter should add the other post types.' );
		$this->assertSame( array( $ids[0] ), wp_list_pluck( $result['items'], 'id' ), 'Query mode should return only posts of the requested type.' );
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', 'content.raw' ),
			)
		);

		$this->assertSame(
			'Body here.',
			$result['items'][0]['content']['raw'],
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', 'content.raw', 'content.rendered' ),
			)
		);

		$this->assertSame(
			'Top secret body.',
			$result['items'][0]['content']['raw'],
			'Editors should receive raw password-protected content.'
		);
		$this->assertStringContainsString(
			'Top secret body.',
			$result['items'][0]['content']['rendered'],
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', 'content.rendered', 'content.protected' ),
			)
		);

		$this->assertSame( '', $result['items'][0]['content']['rendered'], 'Password-protected rendered content should be withheld.' );
		$this->assertTrue( $result['items'][0]['content']['protected'], 'The protected flag should reveal the field is password-protected.' );
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', 'excerpt.rendered', 'excerpt.protected' ),
			)
		);

		$this->assertSame(
			"<p>Top secret body.</p>\n",
			$result['items'][0]['excerpt']['rendered'],
			'Editors should receive the real excerpt generated from a password-protected post.'
		);
		$this->assertTrue( $result['items'][0]['excerpt']['protected'], 'The protected flag should reveal the excerpt is password-protected.' );
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', 'excerpt.rendered', 'excerpt.protected' ),
			)
		);

		$this->assertSame( '', $result['items'][0]['excerpt']['rendered'], 'Password-protected rendered excerpts should be withheld.' );
		$this->assertTrue( $result['items'][0]['excerpt']['protected'], 'The protected flag should reveal the excerpt is password-protected.' );
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
				'where'  => array( 'id' => self::$post_ids['limited_role_content'] ),
				'fields' => array( 'id', 'excerpt.rendered' ),
			)
		);

		$this->assertSame(
			"<p>Readable excerpt.</p>\n",
			$result['items'][0]['excerpt']['rendered'],
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
				'where'  => array( 'id' => self::$post_ids['published'] ),
				'fields' => array( 'id', 'title.rendered' ),
			)
		);

		$this->assertIsArray( $result, 'A non-string rendered title should not fail the query.' );
		$this->assertSame( '', $result['items'][0]['title']['rendered'], 'A non-string rendered title should be returned as an empty string.' );
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
				'where'  => array( 'id' => $target_id ),
				'fields' => array( 'id', 'title.rendered', 'excerpt.rendered' ),
			)
		);
		$restored_context_id = get_the_ID();

		$this->assertStringContainsString(
			'<!-- context:' . $target_id . ' -->',
			$result['items'][0]['title']['rendered'],
			'Title filters should see the requested post as the current post.'
		);
		$this->assertStringContainsString(
			'<!-- context:' . $target_id . ' -->',
			$result['items'][0]['excerpt']['rendered'],
			'Excerpt filters should see the requested post as the current post.'
		);
		$this->assertSame(
			$surrounding->ID,
			$restored_context_id,
			'The surrounding post context should be restored after rendering.'
		);
	}

	/**
	 * Returns the rendered field paths, whose filters run with the requested post set up as the
	 * global post.
	 *
	 * @return array<string, array{field: string}> Rendered field test cases.
	 */
	public function data_rendered_fields(): array {
		return array(
			'content.rendered' => array(
				'field' => 'content.rendered',
			),
			'excerpt.rendered' => array(
				'field' => 'excerpt.rendered',
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
	 * @param string $field The rendered field path to request.
	 */
	public function test_rendered_fields_unset_loop_globals_that_were_not_set( string $field ): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		foreach ( self::LOOP_GLOBALS as $name ) {
			unset( $GLOBALS[ $name ] );
		}

		list( $object, $key ) = explode( '.', $field );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => self::$post_ids['limited_role_content'] ),
				'fields' => array( $field ),
			)
		);

		$this->assertArrayHasKey( $key, $result['items'][0][ $object ], 'Precondition: the rendered field should be returned.' );
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
	 * @param string $field The rendered field path to request.
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

		list( $object, $key ) = explode( '.', $field );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => self::$post_ids['limited_role_content'] ),
				'fields' => array( $field ),
			)
		);

		$this->assertArrayHasKey( $key, $result['items'][0][ $object ], 'Precondition: the rendered field should be returned.' );
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
	 * @param string $field The rendered field path to request.
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
					'where'  => array( 'id' => self::$post_ids['limited_role_content'] ),
					'fields' => array( $field ),
				)
			);

			return array( $result, $post->ID, $id );
		};

		list( $result, $bound_post_id, $bound_id ) = $render_in_template( $field );
		list( $object, $key )                      = explode( '.', $field );

		$this->assertArrayHasKey( $key, $result['items'][0][ $object ], 'Precondition: the rendered field should be returned.' );
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
	 * @param string $field The rendered field path to request.
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

		list( $object, $key ) = explode( '.', $field );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => self::$post_ids['limited_role_content'] ),
				'fields' => array( $field ),
			)
		);

		$this->assertArrayHasKey( $key, $result['items'][0][ $object ], 'Precondition: the rendered field should be returned.' );
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
				'where'  => array( 'id' => $target_id ),
				'fields' => array( 'title.rendered', 'link' ),
			)
		);

		$this->assertStringContainsString( '<!-- context:' . $target_id . ' -->', $result['items'][0]['title']['rendered'], 'Title filters should see the requested post as the current post.' );
		$this->assertStringContainsString( 'context=' . $target_id, $result['items'][0]['link'], 'Permalink filters should see the requested post as the current post.' );
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

		$this->login_as( 'author' );

		// Read the password gate of each post while the editable protected post renders.
		$gates = array();
		add_filter(
			'the_content',
			static function ( $content ) use ( &$gates, $owned_id ) {
				$gates = array(
					'owned'  => post_password_required( $owned_id ),
					'other'  => post_password_required( self::$post_ids['password_protected'] ),
					'public' => post_password_required( self::$post_ids['published'] ),
				);

				return $content;
			}
		);

		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => $owned_id ),
				'fields' => array( 'id', 'content.rendered' ),
			)
		);

		$this->assertIsArray( $result, 'Precondition: the author should read their own protected post.' );
		$this->assertFalse( $gates['owned'], 'The filter should unlock a protected post the current user can edit.' );
		$this->assertTrue( $gates['other'], 'The filter should keep the gate on a protected post the current user cannot edit.' );
		$this->assertFalse( $gates['public'], 'The filter should leave posts that do not require a password ungated.' );
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
				'where'  => array( 'id' => $owned_id ),
				'fields' => array( 'id', 'content.rendered' ),
			)
		);

		// Tear-down restores hooks, but not shortcodes.
		remove_shortcode( 'read_content_nested' );

		$this->assertStringNotContainsString(
			'NESTED_SECRET_MARKER',
			$result['items'][0]['content']['rendered'],
			'Rendering an editable protected post must not unlock another protected post it embeds.'
		);
		$this->assertStringContainsString(
			'GATED',
			$result['items'][0]['content']['rendered'],
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
				'where'      => array( 'type' => 'post' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertNotEmpty( $result['items'], 'An uncapped query should return the readable posts.' );
		$this->assertCount( $result['pagination']['total'], $result['items'], 'An uncapped query should report a total equal to the number of returned posts.' );
		$this->assertSame( 1, $result['pagination']['total_pages'], 'An uncapped query should fit on a single page.' );
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
				'where'      => array(
					'type'    => 'post',
					'include' => $ids,
				),
				'fields'     => array( 'id' ),
				'pagination' => array(
					'page'     => 2,
					'per_page' => 2,
				),
			)
		);

		$this->assertCount( 1, $result['items'], 'The last page should return the remaining post.' );
		$this->assertSame( 3, $result['pagination']['total'], 'The last page should report the full total.' );
		$this->assertSame( 2, $result['pagination']['total_pages'], 'The last page should report the full page count.' );
	}

	/**
	 * Paging past the last page reports an error rather than an empty collection.
	 *
	 * `WP_Query::set_found_posts()` skips the count when a page yields no rows, so without
	 * recovering the total an out-of-range page would report a `pagination` of
	 * `total: 0, total_pages: 0`, which is indistinguishable from an empty collection. Report
	 * it as a page that does not exist instead.
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
				'where'      => array(
					'type'    => 'post',
					'include' => $ids,
				),
				'fields'     => array( 'id' ),
				'pagination' => array(
					'page'     => 99,
					'per_page' => 2,
				),
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
	 * A `pagination.page` and `pagination.per_page` that the integer schema accepts are honored
	 * even when they are not PHP integers, as when the MCP adapter passes the 2.0 a JSON encoder
	 * produced.
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
			'where'      => array(
				'type'    => 'post',
				'include' => self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) ),
			),
			'fields'     => array( 'id' ),
			'pagination' => array( 'per_page' => $per_page ),
		);

		$result = wp_get_ability( 'core/content-query' )->execute( array_replace_recursive( $query, array( 'pagination' => array( 'page' => $page ) ) ) );

		$this->assertIsArray( $result, 'The page should be returned.' );
		$this->assertCount( 1, $result['items'], 'The second page of two posts each should hold the third post.' );
		$this->assertSame( 2, $result['pagination']['total_pages'], 'The page count should follow the requested page size.' );

		$result = wp_get_ability( 'core/content-query' )->execute( array_replace_recursive( $query, array( 'pagination' => array( 'page' => $past_page ) ) ) );

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
	 * A single-post lookup honors a `where.id` that the integer schema accepts even when it is
	 * not a PHP integer, as when the MCP adapter passes a float that a JSON encoder produced.
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
				'where'  => array( 'id' => $id ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The post should be returned.' );
		$this->assertSame( $post_id, $result['items'][0]['id'], 'The lookup should resolve to the requested post.' );
	}

	/**
	 * Query mode honors a `where.parent` filter that the integer schema accepts even when it is
	 * not a PHP integer.
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
				'where'  => array(
					'type'   => 'page',
					'parent' => $id,
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The query should succeed.' );
		$this->assertSame( array( $child_id ), wp_list_pluck( $result['items'], 'id' ), 'The query should return the children of the requested parent.' );
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
		$callbacks = $this->get_ability_callbacks();
		$input     = array( 'where' => array( 'id' => $id ) );

		$this->assertFalse( $callbacks['permission_callback']( $input ), 'The permission callback should deny a fractional ID.' );

		$result = $callbacks['execute_callback']( $input );

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
				'where'      => array(
					'type'    => 'post',
					'include' => array( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ),
				),
				'fields'     => array( 'id' ),
				'pagination' => array( 'page' => 2 ),
			)
		);

		$this->assertIsArray( $result, 'An empty result set should not be treated as an out-of-range page.' );
		$this->assertSame( array(), $result['items'], 'No posts match the query.' );
		$this->assertSame( 0, $result['pagination']['total'], 'An empty result set reports a zero total.' );
		$this->assertSame( 0, $result['pagination']['total_pages'], 'An empty result set reports zero pages.' );
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
			'where'      => array(
				'type'    => 'post',
				'include' => $ids,
			),
			'fields'     => array( 'id' ),
			'pagination' => array( 'per_page' => 4 ),
		);

		$first_page = $ability->execute( $input );

		$this->assertSame( 5, $first_page['pagination']['total'], 'The total should count every matching post.' );
		$this->assertSame( $total_pages, $first_page['pagination']['total_pages'], 'The page count should follow the page size the query ran with.' );

		$last_page = $ability->execute( array_replace_recursive( $input, array( 'pagination' => array( 'page' => $total_pages ) ) ) );

		$this->assertIsArray( $last_page, 'The last page should be served.' );
		$this->assertCount( $last_page_count, $last_page['items'], 'The last page should return the remaining posts.' );

		$past_last_page = $ability->execute( array_replace_recursive( $input, array( 'pagination' => array( 'page' => $total_pages + 1 ) ) ) );

		$this->assertWPError( $past_last_page, 'The page after the last one should be rejected.' );
		$this->assertSame( 'content_invalid_page_number', $past_last_page->get_error_code(), 'Paging past the last page should report the page number error.' );
	}

	/**
	 * Include returns every requested post when `pagination.per_page` is omitted.
	 *
	 * Without this the default page size silently truncates a batch load: a caller asking
	 * for a known set of IDs would receive only the first page of them.
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
				'where'  => array(
					'type'    => 'post',
					'include' => $ids,
				),
				'fields' => array( 'id' ),
			)
		);

		$returned = wp_list_pluck( $result['items'], 'id' );
		sort( $returned );
		sort( $ids );

		$this->assertSame( $ids, $returned, 'Every requested post ID should be returned on a single page.' );
		$this->assertSame( 15, $result['pagination']['total'], 'The total should cover every requested post.' );
		$this->assertSame( 1, $result['pagination']['total_pages'], 'Included posts should fit on a single page by default.' );
	}

	/**
	 * An explicit `pagination.per_page` still paginates an include request.
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
				'where'      => array(
					'type'    => 'post',
					'include' => $ids,
				),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 2 ),
			)
		);

		$this->assertCount( 2, $result['items'], 'An explicit per_page should paginate included posts.' );
		$this->assertSame( 5, $result['pagination']['total'], 'The total should still cover every requested post.' );
		$this->assertSame( 3, $result['pagination']['total_pages'], 'Page counts should follow the explicit per_page.' );
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

		$schema     = wp_get_ability( 'core/content-query' )->get_input_schema();
		$query      = $schema['properties']['where']['anyOf'][2];
		$pagination = $schema['properties']['pagination'];

		$this->assertSame( $pagination['properties']['per_page']['maximum'], $query['properties']['include']['maxItems'], 'The include list should be capped at the maximum page size.' );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type'    => 'post',
					'include' => range( 1, $query['properties']['include']['maxItems'] + 1 ),
				),
			)
		);

		$this->assertWPError( $result, 'An include list beyond the cap should be rejected as invalid input.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The cap should be enforced by schema validation.' );
	}

	/**
	 * Returns the field paths that request a rendered excerpt or content, either directly or
	 * through the whole object.
	 *
	 * @return array<string, array{field: string}> Post meta priming test cases.
	 */
	public function data_fields_that_prime_post_meta(): array {
		return array(
			'content.rendered' => array(
				'field' => 'content.rendered',
			),
			'excerpt.rendered' => array(
				'field' => 'excerpt.rendered',
			),
			'whole content'    => array(
				'field' => 'content',
			),
			'whole excerpt'    => array(
				'field' => 'excerpt',
			),
		);
	}

	/**
	 * Requesting rendered fields primes the post meta cache for the whole page.
	 *
	 * The rendered filter chains may read post meta, so priming avoids one lazy meta
	 * query per returned row. Requesting the whole excerpt or content counts as requesting
	 * its rendered value.
	 *
	 * @ticket 66268
	 * @dataProvider data_fields_that_prime_post_meta
	 * @since 7.2.0
	 *
	 * @param string $field The field path to request.
	 */
	public function test_query_rendered_fields_prime_the_post_meta_cache( string $field ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$ids = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$postmeta_queries = $this->count_queries(
			'postmeta',
			static function () use ( $ids, $field ) {
				return wp_get_ability( 'core/content-query' )->execute(
					array(
						'where'  => array(
							'type'    => 'post',
							'include' => $ids,
						),
						'fields' => array( 'id', $field ),
					)
				);
			},
			$result
		);

		$this->assertCount( 3, $result['items'], 'Precondition: the query should return the seeded posts.' );

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
	 * @param string $field The rendered field path to request.
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
						'where'  => array(
							'type'    => 'post',
							'include' => $ids,
						),
						'fields' => array( 'id', $field ),
					)
				);
			},
			$result
		);

		$this->assertCount( 3, $result['items'], 'Precondition: the query should return the seeded posts.' );
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
			if ( empty( $query->get( 'post__in' ) ) ) {
				return;
			}

			$priming[] = $query->get( 'update_post_meta_cache' );
		};

		add_action( 'pre_get_posts', $spy );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type'    => 'post',
					'include' => $ids,
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertCount( 3, $result['items'], 'Precondition: the query should return the seeded posts.' );
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
						'where'  => array(
							'type'    => 'post',
							'include' => $ids,
						),
						'fields' => array( 'id', 'link' ),
					)
				);
			},
			$result
		);

		$this->assertCount( 3, $result['items'], 'Precondition: the query should return the seeded posts.' );
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
						'where'  => array(
							'type'    => 'page',
							'include' => $ids,
						),
						'fields' => array( 'id', 'link' ),
					)
				);
			},
			$result
		);

		$this->assertCount( 3, $result['items'], 'Precondition: the query should return the seeded pages.' );
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
				'where'      => array( 'type' => 'post' ),
				'fields'     => array( 'parent' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertNotEmpty( $result['items'], 'Posts whose requested fields do not apply should still be returned.' );
		$this->assertCount( $result['pagination']['total'], $result['items'], 'The reported total should match the returned posts.' );

		foreach ( $result['items'] as $post_entry ) {
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'parent' ),
			)
		);

		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $post_id ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$result,
			'A post whose requested fields do not apply should return only its ID.'
		);
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', 'date.local', 'date.gmt', 'modified.local', 'modified.gmt' ),
			)
		);

		$this->assertSame( '2026-01-15T10:00:00-05:00', $result['items'][0]['date']['local'], 'The local date should carry the site timezone offset.' );
		$this->assertSame( '2026-01-15T15:00:00+00:00', $result['items'][0]['date']['gmt'], 'The GMT date should be the UTC instant with a UTC offset.' );
		$this->assertSame( '2026-01-16T11:00:00-05:00', $result['items'][0]['modified']['local'], 'The local modified date should carry the site timezone offset.' );
		$this->assertSame( '2026-01-16T16:00:00+00:00', $result['items'][0]['modified']['gmt'], 'The GMT modified date should be the UTC instant with a UTC offset.' );
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
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', 'date.gmt' ),
			)
		);

		$this->assertSame(
			'2026-01-15T15:00:00+00:00',
			$result['items'][0]['date']['gmt'],
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
	 * Data provider for GMT date field paths.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, array{field: string, gmt_column: string, local_column: string, local_date: string, expected: string}>
	 */
	public function data_gmt_date_fields(): array {
		return array(
			'date.gmt'     => array(
				'field'        => 'date.gmt',
				'gmt_column'   => 'post_date_gmt',
				'local_column' => 'post_date',
				'local_date'   => '2026-01-15 10:00:00',
				'expected'     => '2026-01-15T15:00:00+00:00',
			),
			'modified.gmt' => array(
				'field'        => 'modified.gmt',
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
	 * @param string $field        The field path to request, such as `date.gmt`.
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

		list( $object, $key ) = explode( '.', $field );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', $field ),
			)
		);

		$this->assertSame(
			$expected,
			$result['items'][0][ $object ][ $key ],
			'A null stored GMT date should be derived from the local date, not resolved to the current time.'
		);
	}

	/**
	 * A post with no usable date at all reports null.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 *
	 * @dataProvider data_gmt_date_fields
	 *
	 * @param string $field        The field path to request, such as `date.gmt`.
	 * @param string $gmt_column   The cached GMT post column to null out.
	 * @param string $local_column The cached local post column to null out.
	 * @param string $local_date   Unused. Present to match the shared data provider shape.
	 * @param string $expected     Unused. Present to match the shared data provider shape.
	 */
	public function test_gmt_date_is_null_when_no_usable_date_exists( string $field, string $gmt_column, string $local_column, string $local_date, string $expected ): void {
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

		list( $object, $key ) = explode( '.', $field );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', $field ),
			)
		);

		$this->assertIsArray( $result, 'An unresolvable GMT date should pass output validation.' );
		$this->assertArrayHasKey( $key, $result['items'][0][ $object ], 'An unresolvable GMT date should still be returned.' );
		$this->assertNull( $result['items'][0][ $object ][ $key ], 'An unresolvable GMT date should be null.' );
	}

	/**
	 * Data provider for date field paths.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, array{field: string, column: string}>
	 */
	public function data_date_fields(): array {
		return array(
			'date.local'     => array(
				'field'  => 'date.local',
				'column' => 'post_date',
			),
			'date.gmt'       => array(
				'field'  => 'date.gmt',
				'column' => 'post_date_gmt',
			),
			'modified.local' => array(
				'field'  => 'modified.local',
				'column' => 'post_modified',
			),
			'modified.gmt'   => array(
				'field'  => 'modified.gmt',
				'column' => 'post_modified_gmt',
			),
		);
	}

	/**
	 * A malformed stored date reports null instead of failing output validation.
	 *
	 * A zero month still parses, but formats with a negative year, which the `date-time`
	 * format of the output schema rejects.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 *
	 * @dataProvider data_date_fields
	 *
	 * @param string $field  The field path to request, such as `date.local`.
	 * @param string $column The cached post column to corrupt.
	 */
	public function test_malformed_date_is_null( string $field, string $column ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->replace_cached_post_date_columns( $post_id, array( $column => '0000-00-01 00:00:00' ) );

		list( $object, $key ) = explode( '.', $field );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array( 'id' => $post_id ),
				'fields' => array( 'id', $field ),
			)
		);

		$this->assertIsArray( $result, 'A malformed date should pass output validation.' );
		$this->assertArrayHasKey( $key, $result['items'][0][ $object ], 'A malformed date should still be returned.' );
		$this->assertNull( $result['items'][0][ $object ][ $key ], 'A malformed date should be null.' );
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

		$execute = $this->get_ability_callbacks()['execute_callback'];

		$missing = $execute( array( 'where' => array( 'id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) ) );
		$this->assertWPError( $missing, 'A nonexistent post ID should fail the lookup.' );
		$this->assertSame( 'content_not_found', $missing->get_error_code(), 'Missing posts should map to the uniform not-found error.' );

		$mismatched = $execute(
			array(
				'where' => array(
					'id'   => self::$post_ids['published'],
					'type' => 'page',
				),
			)
		);
		$this->assertWPError( $mismatched, 'A post type mismatch should fail the lookup.' );
		$this->assertSame( 'content_not_found', $mismatched->get_error_code(), 'Mismatched post types should map to the uniform not-found error.' );

		$missing_slug = $execute(
			array(
				'where' => array(
					'type' => 'post',
					'slug' => 'no-such-slug',
				),
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
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type'        => 'post',
					'author_slug' => $author_slug,
				),
			)
		);

		$this->assertWPError( $result, 'An author_slug that names no user must not silently widen the query to all authors.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An unhonorable author_slug filter should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where[author_slug]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the unmatched author_slug filter to the error message.'
		);
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
				'where'  => array(
					'type'        => 'post',
					'author_slug' => 'AUTHOR-SLUG',
					'include'     => array( $author_post_id, $other_post_id ),
				),
				'fields' => array( 'id', 'author.slug' ),
			)
		);

		$this->assertIsArray( $result, 'The query should succeed.' );
		$this->assertSame( array( $author_post_id ), wp_list_pluck( $result['items'], 'id' ), 'The filter should return only the author\'s posts.' );
		$this->assertSame( 'author-slug', $result['items'][0]['author']['slug'], 'Each post should return the stored slug.' );
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
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type'   => 'page',
					'parent' => 'not-a-number',
				),
			)
		);

		$this->assertWPError( $result, 'A non-integer parent filter must not silently coerce to a top-level (0) query.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An unhonorable parent filter should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where[parent]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the non-integer parent filter to the error message.'
		);
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
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type'    => 'post',
					'include' => array( 0 ),
				),
			)
		);

		$this->assertWPError( $result, 'An include filter with no valid IDs must not fall through to an unrestricted query.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An empty-after-parsing include should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where[include]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the include filter to the error message.'
		);
	}

	/**
	 * The execute callback rejects a `where` that is not an object, instead of running a query
	 * without filters.
	 *
	 * Gated transports validate the input first, so such a value never reaches the callback
	 * through them. The permission callback lets it through so that the execute callback can
	 * report it.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_execute_callback_rejects_a_where_that_is_not_an_object(): void {
		$this->login_as( 'administrator' );

		$callbacks = $this->get_ability_callbacks();

		foreach ( array( 'id=5', 5, true, null ) as $where ) {
			$input = array( 'where' => $where );
			$label = wp_json_encode( $where );

			$this->assertTrue( $callbacks['permission_callback']( $input ), sprintf( 'The permission callback should let the %s where through.', $label ) );

			$result = $callbacks['execute_callback']( $input );

			$this->assertWPError( $result, sprintf( 'The %s where must not fall through to a query without filters.', $label ) );
			$this->assertSame( 'content_invalid_filter', $result->get_error_code(), sprintf( 'The %s where should fail closed as an invalid filter.', $label ) );
			$this->assertSame(
				array( 'where' => $result->get_error_message() ),
				$result->get_error_data()['params'],
				sprintf( 'The error data should map the %s where to the error message.', $label )
			);
			$this->assertSame( 400, $result->get_error_data()['status'], sprintf( 'The %s where should be reported as a bad request.', $label ) );
		}
	}

	/**
	 * Returns inputs whose `where` is missing, empty, or holds neither a lookup key nor a post
	 * type.
	 *
	 * @return array<string, array{input: array<string, mixed>}> Input test cases.
	 */
	public function data_inputs_that_read_as_a_query_without_a_post_type(): array {
		return array(
			'missing where'      => array(
				'input' => array( 'fields' => array( 'id' ) ),
			),
			'empty where'        => array(
				'input' => array( 'where' => array() ),
			),
			'empty string where' => array(
				'input' => array( 'where' => '' ),
			),
			'filters only'       => array(
				'input' => array( 'where' => array( 'status' => array( 'publish' ) ) ),
			),
		);
	}

	/**
	 * Called directly, a `where` that is missing, empty, or holds neither a lookup key nor a
	 * post type reads as a query without a post type, which both callbacks refuse.
	 *
	 * An empty string counts as an empty object, as in schema validation.
	 *
	 * @ticket 66268
	 * @dataProvider data_inputs_that_read_as_a_query_without_a_post_type
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $input The input to send.
	 */
	public function test_callbacks_refuse_a_query_without_a_post_type( array $input ): void {
		$this->login_as( 'administrator' );

		$callbacks = $this->get_ability_callbacks();

		$this->assertFalse( $callbacks['permission_callback']( $input ), 'The permission callback should refuse a query without a post type.' );

		$result = $callbacks['execute_callback']( $input );

		$this->assertWPError( $result, 'The execute callback should not run a query without a post type.' );
		$this->assertSame( 'content_not_found', $result->get_error_code(), 'A query without a post type should be reported as not found.' );
	}

	/**
	 * Called directly, a `pagination` that is not an object falls back to the first page of
	 * the default page size.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_execute_callback_defaults_a_pagination_that_is_not_an_object(): void {
		$this->login_as( 'administrator' );

		// More posts than the default page size of 10, each with its own date.
		for ( $day = 1; $day <= 11; $day++ ) {
			self::factory()->post->create(
				array(
					'post_status' => 'publish',
					'post_date'   => sprintf( '2026-01-%02d 10:00:00', $day ),
				)
			);
		}

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$query   = array(
			'where'  => array( 'type' => 'post' ),
			'fields' => array( 'id' ),
		);

		$expected = $execute( $query );

		$this->assertIsArray( $expected, 'Precondition: the query without pagination should succeed.' );
		$this->assertCount( 10, $expected['items'], 'Precondition: the query without pagination should hold a page of the default size.' );

		foreach ( array( 'page=2', 5, true, null ) as $pagination ) {
			$this->assertSame(
				$expected,
				$execute( $query + array( 'pagination' => $pagination ) ),
				sprintf( 'The %s pagination should fall back to the first page of the default page size.', wp_json_encode( $pagination ) )
			);
		}
	}

	/**
	 * Called directly, a `where` that holds an ID next to a slug and a query filter resolves
	 * the post by ID, and both callbacks ignore the other keys.
	 *
	 * Schema validation rejects such a `where`, so this only matters for callers that skip it.
	 * The slug names a draft the subscriber cannot read, and the subscriber may not query
	 * drafts, so a callback that honored either key would refuse the request.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_callbacks_resolve_the_id_and_ignore_other_where_keys(): void {
		$post_id  = self::$post_ids['published_content'];
		$draft_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_name'   => 'ignored-lookup-slug',
				'post_status' => 'draft',
			)
		);

		$this->login_as( 'subscriber' );

		$callbacks = $this->get_ability_callbacks();
		$input     = array(
			'where'  => array(
				'id'      => $post_id,
				'type'    => 'post',
				'slug'    => 'ignored-lookup-slug',
				'status'  => array( 'draft' ),
				'include' => array( $draft_id ),
			),
			'fields' => array( 'id' ),
		);

		$this->assertTrue( $callbacks['permission_callback']( $input ), 'The permission callback should resolve the readable post by ID.' );
		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $post_id ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$callbacks['execute_callback']( $input ),
			'The execute callback should resolve the post by ID and ignore the slug and the query filters.'
		);
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

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$query   = static function ( array $statuses, int $user_id ) use ( $execute ) {
			return $execute(
				array(
					'where'  => array(
						'type'        => 'post',
						'status'      => $statuses,
						'author_slug' => get_userdata( $user_id )->user_nicename,
					),
					'fields' => array( 'id' ),
				)
			);
		};

		$this->login_as( 'subscriber' );
		$public = $query( array( 'publish' ), self::$user_ids['author'] );
		$this->assertIsArray( $public, 'A subscriber should filter by an author with published posts.' );
		$this->assertSame( array( $published_id ), wp_list_pluck( $public['items'], 'id' ), 'The filter should return that author\'s posts.' );

		$hidden = $query( array( 'publish' ), self::$user_ids['author_secondary'] );
		$this->assertWPError( $hidden, 'A subscriber should not learn that an author without published posts exists.' );
		$this->assertSame( 'content_invalid_filter', $hidden->get_error_code(), 'A hidden author should be reported like a missing one.' );
		$this->assertSame(
			array( 'where[author_slug]' => $hidden->get_error_message() ),
			$hidden->get_error_data()['params'],
			'The error data should map the hidden author_slug filter to the error message.'
		);

		$this->login_as( 'editor' );
		$drafts = $query( array( 'draft' ), self::$user_ids['author_secondary'] );
		$this->assertIsArray( $drafts, 'An editor should filter by an author without published posts.' );
		$this->assertSame( array( $draft_id ), wp_list_pluck( $drafts['items'], 'id' ), 'The filter should return that author\'s drafts.' );
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

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$query   = static function ( string $author_slug ) use ( $execute ) {
			return $execute(
				array(
					'where' => array(
						'type'        => 'post',
						'author_slug' => $author_slug,
					),
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
		$execute = $this->get_ability_callbacks()['execute_callback'];

		switch_to_blog( $site_id );
		$post_id = self::factory()->post->create( array( 'post_author' => $super_admin_id ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$is_member = is_user_member_of_blog( $super_admin_id, $site_id );
		$result    = $execute(
			array(
				'where'  => array(
					'type'        => 'post',
					'author_slug' => 'network-author',
				),
				'fields' => array( 'id' ),
			)
		);

		restore_current_blog();
		revoke_super_admin( $super_admin_id );

		$this->assertFalse( $is_member, 'Precondition: the super admin should not be a member of the site.' );
		$this->assertIsArray( $result, 'An author with published posts on the site should be filterable without being a member.' );
		$this->assertSame( array( $post_id ), wp_list_pluck( $result['items'], 'id' ), 'The filter should return the author\'s posts on the site.' );
	}
}
