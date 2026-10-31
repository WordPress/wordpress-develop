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
	 * A post ID that tests create a post with through `import_id`, so that an ID beyond the
	 * integer range can alias it.
	 *
	 * Floats near 2^64 are 4096 apart, so 2^64 + N is exact for a multiple of 4096 and casts
	 * to N.
	 *
	 * @since 7.2.0
	 *
	 * @var int
	 */
	private const ALIASED_POST_ID = 4096 * 1024;

	/**
	 * The result of a query that matches no post, on any page.
	 *
	 * @since 7.2.0
	 *
	 * @var array<string, array<mixed>>
	 */
	private const EMPTY_RESULT = array(
		'items'      => array(),
		'pagination' => array(
			'total'       => 0,
			'total_pages' => 0,
		),
	);

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

		if ( ! wp_has_ability_category( 'content' ) ) {
			global $wp_current_filter;
			$wp_current_filter[] = 'wp_abilities_api_categories_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
			wp_register_ability_category(
				'content',
				array(
					'label'       => 'Content',
					'description' => 'Content.',
				)
			);
		}
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
	 * Logs in as the shared user with the given role.
	 *
	 * @since 7.2.0
	 *
	 * @param string $role The role to log in as.
	 */
	private function login_as( string $role ): void {
		wp_set_current_user( self::$user_ids[ $role ] );
	}

	/**
	 * Returns roles that can read public posts but cannot edit another user's post.
	 *
	 * @return array<string, array{role: string}> Role test cases.
	 */
	public static function data_roles_without_edit_access_to_other_users_posts(): array {
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
	 * The input schema models `where` as one object that requires the post type and takes the
	 * filters next to it, exposing only marked types.
	 *
	 * The root takes only `where`, `fields` and `pagination`. No part of the input schema uses
	 * a combinator, and `where` has no title.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_input_schema_models_a_flat_where(): void {
		$this->register_ability();

		$schema = wp_get_ability( 'core/content-query' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'], 'The input schema should describe an object.' );
		$this->assertFalse( $schema['additionalProperties'], 'The input schema should reject unknown root keys.' );
		$this->assertSame( array( 'where' ), $schema['required'], 'The input schema should require where.' );
		$this->assertSame( array(), $this->find_schema_keywords( $schema, array( 'oneOf', 'anyOf', 'allOf' ) ), 'The input schema should not use a combinator at any depth.' );
		$this->assertSame( array( 'where', 'fields', 'pagination' ), array_keys( $schema['properties'] ), 'The input schema should take where, fields and pagination, in that order.' );

		$where = $schema['properties']['where'];

		$this->assertSame( 'object', $where['type'], 'The where option should describe an object.' );
		$this->assertArrayNotHasKey( 'title', $where, 'The where option should not have a title.' );
		$this->assertSame( array( 'type' ), $where['required'], 'The where option should require a post type.' );
		$this->assertFalse( $where['additionalProperties'], 'The where option should reject unknown keys.' );
		$this->assertSame( array( 'type', 'ids', 'slugs', 'status', 'author', 'parent' ), array_keys( $where['properties'] ), 'The where option should take a post type and the filters, in that order.' );
		$this->assertSame( 'string', $where['properties']['type']['type'], 'The where option should take the post type as a string.' );
		$this->assertSameSets( array( 'post', 'page' ), $where['properties']['type']['enum'], 'Only the post types marked show_in_abilities should be exposed.' );

		$ids = $where['properties']['ids'];

		$this->assertSame( 'array', $ids['type'], 'The ids filter should take a list.' );
		$this->assertSame( 1, $ids['minItems'], 'The ids filter should require at least one post ID.' );
		$this->assertSame( 100, $ids['maxItems'], 'The ids filter should accept at most 100 post IDs.' );
		$this->assertTrue( $ids['uniqueItems'], 'The ids filter should reject duplicate post IDs.' );
		$this->assertSame( 'integer', $ids['items']['type'], 'The ids filter should contain post IDs.' );
		$this->assertSame( 1, $ids['items']['minimum'], 'The ids filter should contain positive post IDs.' );

		$slugs = $where['properties']['slugs'];

		$this->assertSame( 'array', $slugs['type'], 'The slugs filter should take a list.' );
		$this->assertSame( 1, $slugs['minItems'], 'The slugs filter should require at least one slug.' );
		$this->assertSame( 100, $slugs['maxItems'], 'The slugs filter should accept at most 100 slugs.' );
		$this->assertTrue( $slugs['uniqueItems'], 'The slugs filter should reject duplicate slugs.' );
		$this->assertSame( 'string', $slugs['items']['type'], 'The slugs filter should contain slugs as strings.' );
		$this->assertSame( 1, $slugs['items']['minLength'], 'The slugs filter should not contain an empty slug.' );

		$status = $where['properties']['status'];

		$this->assertSame( 'array', $status['type'], 'The status filter should take a list.' );
		$this->assertTrue( $status['uniqueItems'], 'The status filter should reject duplicate statuses.' );
		$this->assertSame( 1, $status['minItems'], 'The status filter should require at least one status.' );
		$this->assertSame( 'string', $status['items']['type'], 'The status filter should contain status names.' );
		$this->assertSameSets( array_values( get_post_stati( array( 'internal' => false ) ) ), $status['items']['enum'], 'The status filter should expose the statuses that are not internal.' );

		$author = $where['properties']['author'];

		$this->assertSame( 'object', $author['type'], 'The author filter should describe an object.' );
		$this->assertFalse( $author['additionalProperties'], 'The author filter should reject unknown keys.' );
		$this->assertSame( 1, $author['minProperties'], 'The author filter should require an ID or a slug.' );
		$this->assertArrayNotHasKey( 'required', $author, 'The author filter should not require a particular key.' );
		$this->assertSame( array( 'id', 'slug' ), array_keys( $author['properties'] ), 'The author filter should take an ID and a slug, in that order.' );
		$this->assertSame( 'integer', $author['properties']['id']['type'], 'The author ID should be an integer.' );
		$this->assertSame( 1, $author['properties']['id']['minimum'], 'The author ID should be positive.' );
		$this->assertSame( 'string', $author['properties']['slug']['type'], 'The author slug should be a string.' );
		$this->assertSame( 1, $author['properties']['slug']['minLength'], 'The author slug should not be empty.' );
		$this->assertSame( 'integer', $where['properties']['parent']['type'], 'The parent filter should be an integer.' );
		$this->assertSame( 0, $where['properties']['parent']['minimum'], 'The parent filter should accept 0 for top-level posts.' );

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
				'date.site',
				'date.gmt',
				'modified',
				'modified.site',
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
			'where'      => $where['properties'],
			'author'     => $author['properties'],
			'pagination' => $pagination['properties'],
		);
		foreach ( $input_properties as $location => $properties ) {
			foreach ( $properties as $name => $property ) {
				$this->assertArrayHasKey( 'description', $property, sprintf( 'The %s property %s should have a description.', $location, $name ) );
			}
		}
	}

	/**
	 * Defaults are omitted from the whole input schema, so the schema can compile in the
	 * client-side Abilities API validator. Runtime defaults are still applied by the ability.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_input_schema_omits_defaults(): void {
		$this->register_ability();

		$schema = wp_get_ability( 'core/content-query' )->get_input_schema();

		$this->assertSame( array(), $this->find_schema_keywords( $schema, array( 'default' ) ), 'The input schema should not declare a default at any depth.' );
	}

	/**
	 * `where.ids` combines with a `status` filter: a post must have one of the IDs and one of the
	 * statuses.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_ids_combine_with_a_status_filter(): void {
		$draft_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'ids'    => array( self::$post_ids['published'], $draft_id ),
					'status' => array( 'publish' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The ids filter next to a status filter should pass validation.' );
		$this->assertSame( array( self::$post_ids['published'] ), wp_list_pluck( $result['items'], 'id' ), 'The ids filter and the status filter should both apply.' );
	}

	/**
	 * Returns inputs whose `where` is missing, empty, or has no exposed post type.
	 *
	 * An empty string counts as an empty object, as in schema validation.
	 *
	 * @return array<string, array{input: array<string, mixed>}> Input test cases.
	 */
	public static function data_inputs_without_a_post_type(): array {
		return array(
			'missing where'              => array(
				'input' => array( 'fields' => array( 'id' ) ),
			),
			'empty where'                => array(
				'input' => array( 'where' => array() ),
			),
			'empty string where'         => array(
				'input' => array( 'where' => '' ),
			),
			'IDs without a post type'    => array(
				'input' => array( 'where' => array( 'ids' => array( self::IMPORTED_POST_ID ) ) ),
			),
			'slugs without a post type'  => array(
				'input' => array( 'where' => array( 'slugs' => array( 'x' ) ) ),
			),
			'status without a post type' => array(
				'input' => array( 'where' => array( 'status' => array( 'draft' ) ) ),
			),
			'post type outside the enum' => array(
				'input' => array( 'where' => array( 'type' => 'attachment' ) ),
			),
		);
	}

	/**
	 * Returns no input, and inputs that hold a key their object does not take or a value its
	 * schema rejects.
	 *
	 * @return array<string, array{input: mixed}> Input test cases.
	 */
	public static function data_invalid_inputs(): array {
		return array(
			'no input'               => array(
				'input' => null,
			),
			'unknown key'            => array(
				'input' => array(
					'where' => array(
						'type'  => 'post',
						'bogus' => 1,
					),
				),
			),
			'unknown pagination key' => array(
				'input' => array(
					'where'      => array( 'type' => 'post' ),
					'pagination' => array( 'bogus' => 1 ),
				),
			),
			'empty IDs'              => array(
				'input' => array(
					'where' => array(
						'type' => 'post',
						'ids'  => array(),
					),
				),
			),
			'ID of 0'                => array(
				'input' => array(
					'where' => array(
						'type' => 'post',
						'ids'  => array( 0 ),
					),
				),
			),
			'duplicate IDs'          => array(
				'input' => array(
					'where' => array(
						'type' => 'post',
						'ids'  => array( self::IMPORTED_POST_ID, self::IMPORTED_POST_ID ),
					),
				),
			),
			'empty slugs'            => array(
				'input' => array(
					'where' => array(
						'type'  => 'post',
						'slugs' => array(),
					),
				),
			),
			'empty slug'             => array(
				'input' => array(
					'where' => array(
						'type'  => 'post',
						'slugs' => array( '' ),
					),
				),
			),
			'duplicate slugs'        => array(
				'input' => array(
					'where' => array(
						'type'  => 'post',
						'slugs' => array( 'x', 'x' ),
					),
				),
			),
			'empty author'           => array(
				'input' => array(
					'where' => array(
						'type'   => 'post',
						'author' => array(),
					),
				),
			),
			'unknown author key'     => array(
				'input' => array(
					'where' => array(
						'type'   => 'post',
						'author' => array( 'name' => 'x' ),
					),
				),
			),
			'author ID of 0'         => array(
				'input' => array(
					'where' => array(
						'type'   => 'post',
						'author' => array( 'id' => 0 ),
					),
				),
			),
			'empty author slug'      => array(
				'input' => array(
					'where' => array(
						'type'   => 'post',
						'author' => array( 'slug' => '' ),
					),
				),
			),
		);
	}

	/**
	 * Input without a post type, with a key that its object does not take, or with a value its
	 * schema rejects, fails schema validation.
	 *
	 * @ticket 66268
	 * @dataProvider data_inputs_without_a_post_type
	 * @dataProvider data_invalid_inputs
	 * @since 7.2.0
	 *
	 * @param mixed $input The input to send.
	 */
	public function test_invalid_input_fails_schema_validation( $input ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( $input );

		$this->assertWPError( $result, 'Invalid input should not return a result.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'Invalid input should fail schema validation.' );
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
		$this->assertSameSets( array( 'id', 'type', 'status', 'date', 'slug', 'title' ), array_keys( $result['items'][0] ), 'An empty fields list should select the default fields.' );
		$this->assertSame( array( 'rendered' ), array_keys( $result['items'][0]['title'] ), 'The default fields should hold only the rendered title.' );
		$this->assertSame( array( 'site', 'gmt' ), array_keys( $result['items'][0]['date'] ), 'The default fields should hold both the site and the GMT date, in that order.' );

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
	 * The output schema describes the one response shape that every query shares: the posts as
	 * `items`, and their totals as `pagination`, without a combinator at any depth.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_output_schema_describes_query_responses(): void {
		$this->register_ability();

		$schema      = wp_get_ability( 'core/content-query' )->get_output_schema();
		$post_schema = $schema['properties']['items']['items'];
		$pagination  = $schema['properties']['pagination'];

		$this->assertSame( 'object', $schema['type'], 'The output schema should describe object responses.' );
		$this->assertFalse( $schema['additionalProperties'], 'The output schema should not allow keys besides items and pagination.' );
		$this->assertSame( array(), $this->find_schema_keywords( $schema, array( 'oneOf', 'anyOf', 'allOf' ) ), 'The output schema should describe query responses without a combinator at any depth.' );
		$this->assertSame( array( 'items', 'pagination' ), $schema['required'], 'Every response should require the items and the pagination.' );
		$this->assertSame( array( 'items', 'pagination' ), array_keys( $schema['properties'] ), 'The output schema should describe the items and the pagination, in that order.' );
		$this->assertSame( 'array', $schema['properties']['items']['type'], 'The items should be a list.' );

		$this->assertSame( 'object', $post_schema['type'], 'Each post should be described as an object.' );
		$this->assertSame( array( 'id' ), $post_schema['required'], 'Only the always-returned id should be required in a post.' );
		$this->assertFalse( $post_schema['additionalProperties'], 'Returned posts should not allow unknown properties.' );
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

		$nullable_string = array( 'string', 'null' );
		$object_types    = array(
			'date'     => array(
				'site' => $nullable_string,
				'gmt'  => $nullable_string,
			),
			'modified' => array(
				'site' => $nullable_string,
				'gmt'  => $nullable_string,
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
			foreach ( array( 'site', 'gmt' ) as $key ) {
				$this->assertSame( 'date-time', $post_schema['properties'][ $name ]['properties'][ $key ]['format'], sprintf( 'The %s.%s value should use the date-time format.', $name, $key ) );
			}
			$this->assertStringContainsString( "in ISO 8601 format using the site's timezone.", $post_schema['properties'][ $name ]['properties']['site']['description'], sprintf( 'The %s.site description should say the date uses the site timezone.', $name ) );
		}

		$this->assertSame( 'object', $pagination['type'], 'The pagination should describe an object.' );
		$this->assertFalse( $pagination['additionalProperties'], 'The pagination should not allow keys besides the totals.' );
		$this->assertSame( array( 'total', 'total_pages' ), $pagination['required'], 'The pagination should require both totals.' );
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
	 * @return list<string> The paths of the keywords, such as `properties.where.properties.ids.minItems`.
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

		// The `type` enum of `where` lists the exposed post types.
		$enum = wp_get_ability( 'core/content-query' )->get_input_schema()['properties']['where']['properties']['type']['enum'];
		$this->assertContains( 'content_cpt', $enum, 'Custom post types marked show_in_abilities should appear in the post type enum.' );

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
	public static function data_show_in_abilities_values_other_than_true(): array {
		return array(
			'false'               => array(
				'value' => false,
			),
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

		// The `type` enum of `where` lists the exposed post types.
		$enum = wp_get_ability( 'core/content-query' )->get_input_schema()['properties']['where']['properties']['type']['enum'];
		$this->assertNotContains( 'content_cpt', $enum, 'The post type should not appear in the post type enum.' );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type' => 'content_cpt',
					'ids'  => array( $post_id ),
				),
			)
		);
		$this->assertWPError( $result, 'A post of the post type should not be readable by ID.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'A query of the post type should fail validation.' );
	}

	/**
	 * A schema filter can expose a post type that is registered after the ability, by adding it
	 * to the `type` enum of `where`.
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

			$args['input_schema']['properties']['where']['properties']['type']['enum'][] = 'late_cpt';

			return $args;
		};
		add_filter( 'wp_register_ability_args', $amend_schema, 10, 2 );
		$this->register_ability();

		$enum = wp_get_ability( 'core/content-query' )->get_input_schema()['properties']['where']['properties']['type']['enum'];
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
	public function test_query_by_id_returns_the_published_post(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
			)
		);

		$this->assertIsArray( $result, 'The query by ID should return a post array.' );
		$this->assertSame( array( $post_id ), wp_list_pluck( $result['items'], 'id' ), 'The query by ID should return only the requested post.' );
		$this->assertSame( 'Hello Content', $result['items'][0]['title']['rendered'], 'Rendered titles should be returned by default.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$result['pagination'],
			'The query by ID should report one post on one page.'
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
	public function test_query_accepts_object_input(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];
		$ability = wp_get_ability( 'core/content-query' );
		$input   = (object) array(
			'where'  => array(
				'type' => 'post',
				'ids'  => array( $post_id ),
			),
			'fields' => array( 'id', 'title.rendered' ),
		);

		$result = $ability->execute( $input );

		$this->assertIsArray( $result, 'Object input should execute the query by ID.' );
		$this->assertSame( $post_id, $result['items'][0]['id'], 'Object input should preserve the requested post ID.' );
		$this->assertSame( 'Hello Content', $result['items'][0]['title']['rendered'], 'Object input should preserve the requested fields.' );
		$this->assertSameSets( array( 'id', 'title' ), array_keys( $result['items'][0] ), 'Object input should use the requested field projection.' );
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
				'type' => 'post',
				'ids'  => array( $newer_id, $older_id ),
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
	 * A post queried by ID can return explicitly requested rendered and raw content.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_by_id_can_return_content_fields(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published_content'];

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'id', 'type', 'content.rendered', 'content.raw' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['items'], 'id' ), 'The query by ID should return only the requested post.' );
		$this->assertSameSets( array( 'id', 'type', 'content' ), array_keys( $result['items'][0] ), 'The query by ID should return only the requested fields.' );
		$this->assertSameSets( array( 'raw', 'rendered' ), array_keys( $result['items'][0]['content'] ), 'The content should hold only the requested sub-fields.' );
		$this->assertSame( 'post', $result['items'][0]['type'], 'The query by ID should return the post type as type.' );
		$this->assertStringContainsString( 'Body here.', $result['items'][0]['content']['rendered'], 'Explicit content fields should include rendered content.' );
		$this->assertSame( 'Body here.', $result['items'][0]['content']['raw'], 'Explicit content fields should include raw content.' );
		$this->assertSame(
			array(
				'total'       => 1,
				'total_pages' => 1,
			),
			$result['pagination'],
			'The query by ID should report one post on one page.'
		);
	}

	/**
	 * Returns `where` values whose IDs or slugs match no post of the post type: a missing ID
	 * and a slug that no post has.
	 *
	 * @return array<string, array{where: array<string, mixed>}> The `where` values.
	 */
	public static function data_where_values_that_match_no_post(): array {
		return array(
			'missing ID'            => array(
				'where' => array(
					'type' => 'post',
					'ids'  => array( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ),
				),
			),
			'slug that no post has' => array(
				'where' => array(
					'type'  => 'post',
					'slugs' => array( 'no-such-slug' ),
				),
			),
		);
	}

	/**
	 * A query whose IDs or slugs match no post of the post type returns the empty result,
	 * rather than an error.
	 *
	 * @ticket 66268
	 * @dataProvider data_where_values_that_match_no_post
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $where The `where` value to send.
	 */
	public function test_ids_or_slugs_that_match_no_post_return_the_empty_result( array $where ): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute( array( 'where' => $where ) );

		$this->assertSame( self::EMPTY_RESULT, $result, 'A query whose IDs or slugs match no post should return the empty result.' );
	}

	/**
	 * A post in a status that is public but not viewable is returned only to users who can edit
	 * it.
	 *
	 * The default statuses of a subscriber leave the status out of the query. Those of a
	 * contributor and an author cover it, and the post is withheld from them because they
	 * cannot edit it.
	 *
	 * @ticket 66268
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 * @dataProvider data_roles_with_edit_access_to_other_users_posts
	 * @since 7.2.0
	 *
	 * @param string $role The role to test.
	 */
	public function test_public_non_viewable_status_is_returned_only_with_edit_access( string $role ): void {
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
				'post_author' => self::$user_ids['administrator'],
				'post_status' => 'public_hidden',
			)
		);

		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame(
			current_user_can( 'edit_post', $post_id ) ? array( $post_id ) : array(),
			wp_list_pluck( $result['items'], 'id' ),
			'A post in a public status that is not viewable should be returned only to users who can edit it.'
		);
	}

	/**
	 * Returns `where` filters that name a post in the publicly viewable `public_archive` status.
	 *
	 * @return array<string, array{where: array<string, mixed>}> Filter test cases.
	 */
	public static function data_filters_naming_a_post_in_a_viewable_custom_status(): array {
		return array(
			'ids'    => array(
				'where' => array(
					'type' => 'post',
					'ids'  => array( self::IMPORTED_POST_ID ),
				),
			),
			'slugs'  => array(
				'where' => array(
					'type'  => 'post',
					'slugs' => array( 'public-archive-post' ),
				),
			),
			'status' => array(
				'where' => array(
					'type'   => 'post',
					'status' => array( 'public_archive' ),
				),
			),
		);
	}

	/**
	 * A post in a publicly viewable custom status is returned to a subscriber, who cannot edit
	 * it, whether the query names it by ID, by slug or by status.
	 *
	 * @ticket 66268
	 * @dataProvider data_filters_naming_a_post_in_a_viewable_custom_status
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $where The `where` filters.
	 */
	public function test_viewable_custom_status_is_readable_by_subscribers( array $where ): void {
		register_post_status(
			'public_archive',
			array(
				'label'  => 'Public archive',
				'public' => true,
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'import_id'   => self::IMPORTED_POST_ID,
				'post_author' => self::$user_ids['administrator'],
				'post_name'   => 'public-archive-post',
				'post_status' => 'public_archive',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => $where,
				'fields' => array( 'id' ),
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
			'A subscriber should get the post in a publicly viewable custom status.'
		);
	}

	/**
	 * Registers the exposed `inherit_cpt` post type, and adds the `inherit` status to the
	 * ability's queries for it.
	 *
	 * `inherit` is an internal status, so neither `where.status` nor the default statuses cover
	 * it, and only a query filter can add posts in it. The ability's query runs
	 * `pre_get_posts`, so a callback without an is_main_query() check can.
	 *
	 * @since 7.2.0
	 */
	private function register_inherit_post_type(): void {
		register_post_type(
			'inherit_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor' ),
			)
		);

		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( in_array( 'inherit_cpt', (array) $query->get( 'post_type' ), true ) ) {
					$query->set( 'post_status', array( 'publish', 'inherit' ) );
				}
			}
		);
	}

	/**
	 * A post that inherits its status from a readable parent is readable.
	 *
	 * A query filter adds the `inherit` status, which no input can request, so the post reaches
	 * the read check.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_inherited_post_is_readable_when_parent_is_readable(): void {
		$this->register_inherit_post_type();

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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type' => 'inherit_cpt',
					'ids'  => array( $child_id ),
				),
			)
		);

		$this->assertIsArray( $result, 'Inherited posts should be readable when their parent is readable.' );
		$this->assertSame( $child_id, $result['items'][0]['id'], 'The inherited child should be returned.' );
		$this->assertSame( 'Inherited child', $result['items'][0]['title']['rendered'], 'The inherited child should include normal default fields.' );
	}

	/**
	 * A post with an inherited status but no readable parent is denied.
	 *
	 * A query filter adds the `inherit` status, which no input can request, so the post reaches
	 * the read check.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_inherited_post_without_parent_is_denied_for_read_only_users(): void {
		$this->register_inherit_post_type();

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'inherit_cpt',
				'post_status' => 'inherit',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type' => 'inherit_cpt',
					'ids'  => array( $post_id ),
				),
			)
		);

		$this->assertIsArray( $result, 'An inherited post without a readable parent should not fail the query.' );
		$this->assertSame( 1, $result['pagination']['total'], 'Precondition: the query filter should add the inherited post.' );
		$this->assertSame( array(), $result['items'], 'Inherited posts without a readable parent should be denied.' );
	}

	/**
	 * A query without IDs or slugs returns only published posts by default.
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
	 * Query results are ordered by post date, newest first, whatever order `where.ids` uses.
	 *
	 * Pins both halves of what the schema advertises. The ability leaves `orderby` at the
	 * WP_Query default, matching the REST posts controller, and `ids` only filters the query,
	 * so a caller that passes IDs in a chosen order must not expect them back in it. The posts
	 * are created out of date order, so that ordering by ID would fail.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_orders_posts_newest_first_regardless_of_ids_order(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$newest = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-03-01 10:00:00',
			)
		);
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					// Deliberately neither date order nor ID order.
					'ids'  => array( $oldest, $newest, $middle ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame(
			array( $newest, $middle, $oldest ),
			wp_list_pluck( $result['items'], 'id' ),
			'Results should be ordered by post date, newest first, not by the order of the ids list.'
		);
	}

	/**
	 * Query IDs still respect the requested post type.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_ids_respect_requested_post_type(): void {
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
					'type' => 'post',
					'ids'  => array( $page_id, $post_id ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['items'], 'id' ), 'IDs should not leak posts from other post types.' );
	}

	/**
	 * Query IDs accept a single post ID, as the input schema does.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_ids_accept_a_single_post_id(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::$post_ids['published'];

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => $post_id,
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['items'], 'id' ), 'A single post ID should limit the query to that post.' );
	}

	/**
	 * Query IDs still respect row-level permissions.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_ids_respect_row_level_permissions(): void {
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
					'type'   => 'post',
					'status' => array( 'draft' ),
					'ids'    => array( $draft_a, $draft_b ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $draft_b ), wp_list_pluck( $result['items'], 'id' ), 'IDs should not bypass row-level draft permissions.' );
	}

	/**
	 * A query can return drafts named by ID with explicitly requested rendered and raw content.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_draft_ids_can_return_content_fields(): void {
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
					'type'   => 'post',
					'status' => array( 'draft' ),
					'ids'    => array( $draft ),
				),
				'fields' => array( 'id', 'type', 'status', 'content.rendered', 'content.raw' ),
			)
		);

		$this->assertSame( array( $draft ), wp_list_pluck( $result['items'], 'id' ), 'The draft query should return only the requested draft.' );
		$this->assertSameSets( array( 'id', 'type', 'status', 'content' ), array_keys( $result['items'][0] ), 'The draft query should return only the requested fields.' );
		$this->assertSameSets( array( 'raw', 'rendered' ), array_keys( $result['items'][0]['content'] ), 'The draft content should hold only the requested sub-fields.' );
		$this->assertSame( 'post', $result['items'][0]['type'], 'Query responses should return the post type as type.' );
		$this->assertSame( 'draft', $result['items'][0]['status'], 'The draft query should expose the requested draft status.' );
		$this->assertStringContainsString( 'Draft body for content fields.', $result['items'][0]['content']['rendered'], 'Draft query results should include rendered content when requested.' );
		$this->assertSame( 'Draft body for content fields.', $result['items'][0]['content']['raw'], 'Draft query results should include raw content when requested.' );
	}

	/**
	 * A query by several slugs returns every listed post, newest first, on one page.
	 *
	 * Each slug is normalized like a post slug, so a slug in capitals matches its lowercase
	 * slug, and the slug "0" matches too. With slugs, the page size defaults to the maximum of
	 * 100 instead of 10, so the posts of more than 10 slugs still fit on the first page.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_slugs_return_every_listed_post(): void {
		/*
		 * Core regenerates an "empty" post_name from the title, so a post titled "0"
		 * ends up with the literal slug "0". It is the newest post, and is created first
		 * so that ID order is not date order.
		 */
		$zero_id = self::factory()->post->create(
			array(
				'post_title'  => '0',
				'post_name'   => '0',
				'post_status' => 'publish',
				'post_date'   => '2026-01-12 10:00:00',
			)
		);

		$this->assertSame( '0', get_post( $zero_id )->post_name, 'Precondition: the post slug should be the literal string "0".' );

		$slugs    = array();
		$post_ids = array();
		for ( $day = 1; $day <= 11; $day++ ) {
			$slugs[]    = "listed-slug-{$day}";
			$post_ids[] = self::factory()->post->create(
				array(
					'post_name'   => "listed-slug-{$day}",
					'post_status' => 'publish',
					'post_date'   => sprintf( '2026-01-%02d 10:00:00', $day ),
				)
			);
		}

		$slugs[]    = '0';
		$post_ids[] = $zero_id;

		// Request one slug in capitals, and the slugs in neither date order nor slug order.
		$slugs[2]  = 'LISTED-SLUG-3';
		$requested = array_merge( array_slice( $slugs, 6 ), array_reverse( array_slice( $slugs, 0, 6 ) ) );

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type'  => 'post',
					'slugs' => $requested,
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The query by several slugs should succeed.' );

		$ids = wp_list_pluck( $result['items'], 'id' );

		$this->assertContains( $post_ids[2], $ids, 'A slug in capitals should match its lowercase slug.' );
		$this->assertContains( $zero_id, $ids, 'The slug "0" should match its post.' );
		$this->assertSame( array_reverse( $post_ids ), $ids, 'Every listed post should be returned, newest first.' );
		$this->assertSame(
			array(
				'total'       => 12,
				'total_pages' => 1,
			),
			$result['pagination'],
			'With slugs, the page size should default to 100, so every listed post should fit on one page.'
		);
	}

	/**
	 * A slug that several posts share returns every one of them, newest first, and the
	 * `parent` filter tells them apart, with a parent of 0 for the top-level page.
	 *
	 * Pages under the same parent cannot share a slug, but a top-level page and a child page
	 * can. With slugs, the page size defaults to the maximum of 100, so without a page size
	 * both pages come back on the first page, while an explicit page size of one splits them
	 * over two pages.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_slugs_return_pages_with_the_same_slug_under_different_parents(): void {
		$parent_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_ids  = array();
		foreach ( array( 0, $parent_id ) as $index => $page_parent ) {
			$page_ids[] = self::factory()->post->create(
				array(
					'post_type'   => 'page',
					'post_parent' => $page_parent,
					'post_name'   => 'shared-slug',
					'post_status' => 'publish',
					'post_date'   => sprintf( '2026-%02d-01 10:00:00', $index + 1 ),
				)
			);
		}

		$this->assertSame( array( 'shared-slug', 'shared-slug' ), wp_list_pluck( array_map( 'get_post', $page_ids ), 'post_name' ), 'Precondition: a top-level page and a child page should share the slug.' );

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );
		$where   = array(
			'type'  => 'page',
			'slugs' => array( 'shared-slug' ),
		);

		$both = $ability->execute(
			array(
				'where'  => $where,
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $both, 'The query by a shared slug should succeed.' );
		$this->assertSame( array_reverse( $page_ids ), wp_list_pluck( $both['items'], 'id' ), 'Both pages sharing the slug should be returned, newest first.' );
		$this->assertSame(
			array(
				'total'       => 2,
				'total_pages' => 1,
			),
			$both['pagination'],
			'Without a page size, both pages sharing the slug should fit on the first page.'
		);

		$first_page = $ability->execute(
			array(
				'where'      => $where,
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 1 ),
			)
		);

		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $page_ids[1] ),
				),
				'pagination' => array(
					'total'       => 2,
					'total_pages' => 2,
				),
			),
			$first_page,
			'An explicit page size of one should return the newest page first, and the totals should count both pages.'
		);

		$by_parent = $ability->execute(
			array(
				'where'  => $where + array( 'parent' => $parent_id ),
				'fields' => array( 'id' ),
			)
		);
		$top_level = $ability->execute(
			array(
				'where'  => $where + array( 'parent' => 0 ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $page_ids[1] ), wp_list_pluck( $by_parent['items'], 'id' ), 'The parent filter should return only the child page.' );
		$this->assertSame( array( $page_ids[0] ), wp_list_pluck( $top_level['items'], 'id' ), 'A parent of 0 should return only the top-level page.' );
	}

	/**
	 * A slug that normalizes to an empty string cannot match a post, so it is dropped, while
	 * the other slugs still apply.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_slugs_that_normalize_to_nothing_are_dropped(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_name'   => 'real-slug',
				'post_status' => 'publish',
			)
		);

		// A draft has no slug yet, so an empty slug would match it.
		self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type'  => 'post',
					'slugs' => array( '!!!', 'real-slug' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'A slug that normalizes to nothing should not fail the query while another slug is left.' );
		$this->assertSame( array( $post_id ), wp_list_pluck( $result['items'], 'id' ), 'The post with the real slug should be returned.' );
	}

	/**
	 * `where.ids` and `where.slugs` combine like every other filter: a post must be in both
	 * lists.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_ids_and_slugs_together_return_only_posts_in_both_lists(): void {
		$post_ids = array();
		foreach ( array( 'a', 'b', 'c' ) as $name ) {
			$post_ids[ $name ] = self::factory()->post->create(
				array(
					'post_name'   => "both-lists-{$name}",
					'post_status' => 'publish',
				)
			);
		}

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type'  => 'post',
					'ids'   => array( $post_ids['a'], $post_ids['b'] ),
					'slugs' => array( 'both-lists-b', 'both-lists-c' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $post_ids['b'] ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$result,
			'Only the post in both lists should be returned.'
		);
	}

	/**
	 * Returns a role that cannot read another user's draft and a role that can, with the posts
	 * that share a slug that each role gets, newest first.
	 *
	 * @return array<string, array{role: string, expected: list<string>}> Role test cases.
	 */
	public static function data_roles_with_and_without_access_to_others_drafts(): array {
		return array(
			'subscriber' => array(
				'role'     => 'subscriber',
				'expected' => array( 'published' ),
			),
			'editor'     => array(
				'role'     => 'editor',
				'expected' => array( 'draft', 'published' ),
			),
		);
	}

	/**
	 * A newer draft that shares a published post's slug comes back next to the published post
	 * for a role that can read the draft, and is withheld from a role that cannot.
	 *
	 * Every post that matches a slug is returned. A subscriber's default statuses do not cover
	 * drafts, while an editor's do.
	 *
	 * @ticket 66268
	 * @dataProvider data_roles_with_and_without_access_to_others_drafts
	 * @since 7.2.0
	 *
	 * @param string       $role     The role to query the slug as.
	 * @param list<string> $expected The posts the role should get, newest first.
	 */
	public function test_slugs_return_a_newer_draft_sharing_the_slug_only_to_roles_that_can_read_it( string $role, array $expected ): void {
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

		$posts        = array(
			'published' => $published_id,
			'draft'     => $draft_id,
		);
		$expected_ids = array_map(
			static function ( string $name ) use ( $posts ): int {
				return $posts[ $name ];
			},
			$expected
		);

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type'  => 'post',
					'slugs' => array( 'shadowed-slug' ),
				),
			)
		);

		$this->assertIsArray( $result, 'The query by slug should succeed.' );
		$this->assertSame( $expected_ids, wp_list_pluck( $result['items'], 'id' ), 'The query by slug should return every post with the slug that the role may read, newest first.' );
		$this->assertCount( $result['pagination']['total'], $expected, 'The total should count every post with the slug in the statuses the role may query.' );
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['published_content'] ),
				),
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
	public static function data_sub_field_paths(): array {
		return array(
			'title.rendered'    => array(
				'field' => 'title.rendered',
			),
			'content.raw'       => array(
				'field' => 'content.raw',
			),
			'content.rendered'  => array(
				'field' => 'content.rendered',
			),
			'date.site'         => array(
				'field' => 'date.site',
			),
			'date.gmt'          => array(
				'field' => 'date.gmt',
			),
			'modified.site'     => array(
				'field' => 'modified.site',
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
				'fields' => array( $field ),
			)
		);

		$this->assertIsArray( $result, 'The query should succeed.' );
		$this->assertSameSets( array( 'id', $object ), array_keys( $result['items'][0] ), 'Only the ID and the object that holds the sub-field should be returned.' );
		$this->assertSame( array( $key ), array_keys( $result['items'][0][ $object ] ), 'The object should hold only the requested sub-field.' );
	}

	/**
	 * Returns the fields that are objects, with the keys each one holds.
	 *
	 * @return array<string, array{field: string, keys: list<string>}> Object field test cases.
	 */
	public static function data_object_fields(): array {
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
				'keys'  => array( 'site', 'gmt' ),
			),
			'modified' => array(
				'field' => 'modified',
				'keys'  => array( 'site', 'gmt' ),
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
				'fields' => array( $field ),
			)
		);

		$this->assertIsArray( $result, 'The query should succeed.' );
		$this->assertSame( array( 'id', $field ), array_keys( $result['items'][0] ), 'Only the ID and the requested object should be returned.' );
		$this->assertSame( $keys, array_keys( $result['items'][0][ $field ] ), 'The object should hold every key, in order.' );
	}

	/**
	 * Whole title, excerpt and content objects leave out the raw values for users who cannot
	 * edit the post, rather than failing the request.
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
				'fields' => array( 'title', 'excerpt', 'content' ),
			)
		);

		$this->assertIsArray( $result, 'Whole objects should not require edit access.' );
		$this->assertSameSets( array( 'id', 'title', 'excerpt', 'content' ), array_keys( $result['items'][0] ), 'Only the ID and the requested objects should be returned.' );
		$this->assertSame( array( 'rendered' ), array_keys( $result['items'][0]['title'] ), 'The title should hold only the rendered title.' );
		$this->assertSameSets( array( 'rendered', 'protected' ), array_keys( $result['items'][0]['excerpt'] ), 'The excerpt should hold the rendered excerpt and the protected flag.' );
		$this->assertSameSets( array( 'rendered', 'protected' ), array_keys( $result['items'][0]['content'] ), 'The content should hold the rendered content and the protected flag.' );
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
					'type' => 'post',
					'ids'  => array( $own_id, $other_id ),
				),
				'fields' => array( 'content' ),
			)
		);

		$this->assertIsArray( $result, 'An author should query the whole content.' );

		$posts = array_column( $result['items'], null, 'id' );

		$this->assertSameSets( array( $own_id, $other_id ), array_keys( $posts ), 'The query should return the posts of other users as well as the author\'s own.' );
		$this->assertSameSets( array( 'raw', 'rendered', 'protected' ), array_keys( $posts[ $own_id ]['content'] ), 'The author\'s own post should hold every content key.' );
		$this->assertSame( 'Own body.', $posts[ $own_id ]['content']['raw'], 'The author\'s own post should hold the raw content.' );
		$this->assertSameSets( array( 'rendered', 'protected' ), array_keys( $posts[ $other_id ]['content'] ), 'Another user\'s post should not hold the raw content.' );
		$this->assertStringContainsString( 'Other body.', $posts[ $other_id ]['content']['rendered'], 'Another user\'s post should hold the rendered content.' );
	}

	/**
	 * A query for an explicit raw path is not narrowed to the posts the current user can edit
	 * either, and leaves the raw content out of the other posts.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_for_an_explicit_raw_path_is_not_narrowed_to_editable_posts(): void {
		$own_id   = self::factory()->post->create(
			array(
				'post_author'  => self::$user_ids['author'],
				'post_content' => 'Own body.',
				'post_status'  => 'publish',
				'post_date'    => '2026-01-01 10:00:00',
			)
		);
		$other_id = self::factory()->post->create(
			array(
				'post_author'  => self::$user_ids['author_secondary'],
				'post_content' => 'Other body.',
				'post_status'  => 'publish',
				'post_date'    => '2026-02-01 10:00:00',
			)
		);

		$this->login_as( 'author' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $own_id, $other_id ),
				),
				'fields' => array( 'content.raw' ),
			)
		);

		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $other_id ),
					array(
						'id'      => $own_id,
						'content' => array( 'raw' => 'Own body.' ),
					),
				),
				'pagination' => array(
					'total'       => 2,
					'total_pages' => 1,
				),
			),
			$result,
			'The query should return another user\'s post without the raw content, next to the author\'s own post with it.'
		);
	}

	/**
	 * A path next to its whole object returns the whole object, without the sub-fields the
	 * current user cannot see.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_whole_object_with_one_of_its_paths_follows_the_path_rules(): void {
		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );
		$post_id = self::$post_ids['limited_role_content'];

		$this->login_as( 'subscriber' );
		$without_raw = $ability->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'title', 'title.raw' ),
			)
		);

		$this->assertSame(
			array(
				'items'      => array(
					array(
						'id'    => $post_id,
						'title' => array( 'rendered' => 'Readable title' ),
					),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$without_raw,
			'A raw title path next to the whole title should return the whole title without the raw title to a subscriber.'
		);

		$this->login_as( 'editor' );
		$result = $ability->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'title', 'title.rendered' ),
			)
		);

		$this->assertIsArray( $result, 'An editor should read the whole title.' );
		$this->assertSameSets( array( 'id', 'title' ), array_keys( $result['items'][0] ), 'Only the ID and the title should be returned.' );
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'author' ),
			)
		);
		$orphan = $ability->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $orphan_id ),
				),
				'fields' => array( 'author' ),
			)
		);

		$this->assertSameSets( array( 'id', 'author' ), array_keys( $result['items'][0] ), 'Only the ID and the author should be returned.' );
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
	 * The author's name and slug are returned to the author, to a user who can list users, and,
	 * once the author has a published post, to any user who can read the post. Other users get
	 * the ID with a null name and slug. The `author` slug filter follows the same rule: it
	 * matches the post exactly when the slug is returned, while the ID filter always does.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 *
	 * @dataProvider data_author_visibility
	 *
	 * @param string $role              The role that reads the post, or `self` for the author.
	 * @param string $post_status       The status of the author's only post.
	 * @param bool   $expected_visible  Whether the name and slug should be returned.
	 */
	public function test_author_name_and_slug_are_returned_to_users_who_may_see_the_author( string $role, string $post_status, bool $expected_visible ): void {
		$author_id = self::factory()->user->create(
			array(
				'role'          => 'contributor',
				'display_name'  => 'Hidden Contributor',
				'user_nicename' => 'hidden-contributor',
			)
		);
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => $author_id,
				'post_status' => $post_status,
			)
		);

		if ( 'self' === $role ) {
			wp_set_current_user( $author_id );
		} else {
			$this->login_as( $role );
		}
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'author' ),
			)
		);

		$this->assertCount( 1, $result['items'], 'Precondition: the current user should read the post.' );
		$this->assertSame(
			array(
				'id'   => $author_id,
				'name' => $expected_visible ? 'Hidden Contributor' : null,
				'slug' => $expected_visible ? 'hidden-contributor' : null,
			),
			$result['items'][0]['author'],
			$expected_visible ? 'The author object should name an author the current user may see.' : 'The author object should keep the ID and null the name and slug of an author the current user may not see.'
		);

		$filter = static function ( array $author ) use ( $post_id ) {
			return wp_get_ability( 'core/content-query' )->execute(
				array(
					'where'  => array(
						'type'   => 'post',
						'ids'    => array( $post_id ),
						'author' => $author,
					),
					'fields' => array( 'id' ),
				)
			);
		};

		$by_slug = $filter( array( 'slug' => 'hidden-contributor' ) );
		if ( $expected_visible ) {
			$this->assertSame( array( $post_id ), wp_list_pluck( $by_slug['items'], 'id' ), 'The slug filter should match the post of an author whose slug is returned.' );
		} else {
			$this->assertSame( self::EMPTY_RESULT, $by_slug, 'The slug filter should match no post for an author whose slug is withheld.' );
		}

		$by_id = $filter( array( 'id' => $author_id ) );
		$this->assertSame( array( $post_id ), wp_list_pluck( $by_id['items'], 'id' ), 'The ID filter should match the post whichever author the current user may see.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{role: string, post_status: string, expected_visible: bool}>
	 */
	public static function data_author_visibility(): array {
		return array(
			'editor, pending post'         => array(
				'role'             => 'editor',
				'post_status'      => 'pending',
				'expected_visible' => false,
			),
			'editor, published post'       => array(
				'role'             => 'editor',
				'post_status'      => 'publish',
				'expected_visible' => true,
			),
			'subscriber, published post'   => array(
				'role'             => 'subscriber',
				'post_status'      => 'publish',
				'expected_visible' => true,
			),
			'administrator, pending post'  => array(
				'role'             => 'administrator',
				'post_status'      => 'pending',
				'expected_visible' => true,
			),
			'the author, own pending post' => array(
				'role'             => 'self',
				'post_status'      => 'pending',
				'expected_visible' => true,
			),
		);
	}

	/**
	 * Whether an author has posts the current user may read is looked up once per author and
	 * query, not once per post, and only when the name or slug is requested.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_visibility_is_looked_up_once_per_author(): void {
		$post_ids = array();
		foreach ( array( 'contributor', 'contributor', 'author_secondary' ) as $role ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_author' => self::$user_ids[ $role ],
					'post_status' => 'pending',
				)
			);
		}

		$this->login_as( 'editor' );
		$this->register_ability();

		$looked_up = array();
		add_filter(
			'get_usernumposts',
			static function ( $count, $user_id ) use ( &$looked_up ) {
				$looked_up[] = $user_id;
				return $count;
			},
			10,
			2
		);

		$ability = wp_get_ability( 'core/content-query' );
		$query   = static function ( array $fields ) use ( $ability, $post_ids ) {
			return $ability->execute(
				array(
					'where'  => array(
						'type'   => 'post',
						'status' => array( 'pending' ),
						'ids'    => $post_ids,
					),
					'fields' => $fields,
				)
			);
		};

		$ids_only = $query( array( 'author.id' ) );
		$this->assertCount( 3, $ids_only['items'], 'Precondition: the editor should read the pending posts.' );
		$this->assertSame( array(), $looked_up, 'A query for the author ID alone should not count any author\'s posts.' );

		$query( array( 'author.name' ) );
		$this->assertSameSets( array( self::$user_ids['contributor'], self::$user_ids['author_secondary'] ), $looked_up, 'A query for the author name should count each author\'s posts once.' );

		$looked_up = array();
		$ability->execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'status' => array( 'pending' ),
					'ids'    => $post_ids,
					'author' => array( 'slug' => get_userdata( self::$user_ids['contributor'] )->user_nicename ),
				),
				'fields' => array( 'author.name' ),
			)
		);
		$this->assertSame( array( self::$user_ids['contributor'] ), $looked_up, 'A slug filter and the author name should share one count of the author\'s posts.' );
	}

	/**
	 * An author is visible once they have a published post in a viewable post type exposed to
	 * abilities, whether or not that post type is exposed to the REST API, and not for a
	 * published post in a post type exposed to the REST API only.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_visibility_follows_the_post_types_exposed_to_abilities(): void {
		register_post_type(
			'rest_only_cpt',
			array(
				'public'            => false,
				'show_in_rest'      => true,
				'show_in_abilities' => false,
				'supports'          => array( 'title', 'author' ),
			)
		);
		register_post_type(
			'abilities_cpt',
			array(
				'public'             => false,
				'publicly_queryable' => true,
				'show_in_rest'       => false,
				'show_in_abilities'  => true,
				'supports'           => array( 'title', 'author' ),
			)
		);

		$authors  = array();
		$post_ids = array();
		foreach ( array( 'rest_only_cpt', 'abilities_cpt' ) as $post_type ) {
			$authors[ $post_type ] = self::factory()->user->create(
				array(
					'role'          => 'contributor',
					'user_nicename' => "$post_type-author",
				)
			);
			self::factory()->post->create(
				array(
					'post_type'   => $post_type,
					'post_author' => $authors[ $post_type ],
					'post_status' => 'publish',
				)
			);
			$post_ids[ $post_type ] = self::factory()->post->create(
				array(
					'post_author' => $authors[ $post_type ],
					'post_status' => 'pending',
				)
			);
		}

		$this->login_as( 'editor' );
		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );
		$result  = $ability->execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'status' => array( 'pending' ),
					'ids'    => array_values( $post_ids ),
				),
				'fields' => array( 'id', 'author' ),
			)
		);
		$slugs   = array();
		foreach ( $result['items'] as $item ) {
			$slugs[ $item['id'] ] = $item['author']['slug'];
		}
		$this->assertSameSetsWithIndex(
			array(
				$post_ids['rest_only_cpt'] => null,
				$post_ids['abilities_cpt'] => 'abilities_cpt-author',
			),
			$slugs,
			'Only the author with a published post in a viewable post type exposed to abilities should be named.'
		);

		$filter = static function ( string $slug ) use ( $ability, $post_ids ) {
			return $ability->execute(
				array(
					'where'  => array(
						'type'   => 'post',
						'status' => array( 'pending' ),
						'ids'    => array_values( $post_ids ),
						'author' => array( 'slug' => $slug ),
					),
					'fields' => array( 'id' ),
				)
			);
		};
		$this->assertSame( self::EMPTY_RESULT, $filter( 'rest_only_cpt-author' ), 'The slug of an author published only in a post type exposed to the REST API should match no post.' );
		$this->assertSame( array( $post_ids['abilities_cpt'] ), wp_list_pluck( $filter( 'abilities_cpt-author' )['items'], 'id' ), 'The slug of an author published in a post type exposed to abilities should match their post.' );
	}

	/**
	 * Published posts in a non-viewable post type must not make an author's slug visible.
	 *
	 * @since 7.2.0
	 */
	public function test_author_slug_is_not_exposed_by_posts_in_a_non_viewable_post_type(): void {
		register_post_type(
			'hidden_author_cpt',
			array(
				'public'             => true,
				'publicly_queryable' => false,
				'show_in_abilities'  => true,
				'supports'           => array( 'title', 'author' ),
			)
		);
		$author = self::factory()->user->create_and_get( array( 'role' => 'author' ) );
		self::factory()->post->create(
			array(
				'post_type'   => 'hidden_author_cpt',
				'post_author' => $author->ID,
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_ability();
		$ability = wp_get_ability( 'core/content-query' );

		foreach ( array( $author->user_nicename, 'no-such-user' ) as $slug ) {
			$result = $ability->execute(
				array(
					'where' => array(
						'type'   => 'hidden_author_cpt',
						'author' => array( 'slug' => $slug ),
					),
				)
			);
			$this->assertSame( self::EMPTY_RESULT, $result, 'A hidden author slug should return zero totals, just like an unknown slug.' );
		}
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
				'where'  => array(
					'type' => 'untitled_cpt',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'title', 'author' ),
			)
		);

		$this->assertIsArray( $result, 'The query should succeed.' );
		$this->assertSame( array( 'id' => $post_id ), $result['items'][0], 'Objects that do not apply should be left out, and the post should keep its ID.' );
	}

	/**
	 * Returned keys follow the order of the fields enum, whatever order the fields are
	 * requested in, both at the top level and inside each object.
	 *
	 * The second query requests every top-level field in reverse order, on a hierarchical
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

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'every_field_cpt',
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
				'fields' => array( 'title', 'id', 'date.gmt', 'content.protected', 'content.raw' ),
			)
		);

		$this->assertIsArray( $result, 'The query should succeed.' );
		$this->assertSame( array( 'id', 'date', 'title', 'content' ), array_keys( $result['items'][0] ), 'Top-level keys should follow the fields order.' );
		$this->assertSame( array( 'raw', 'protected' ), array_keys( $result['items'][0]['content'] ), 'Keys inside an object should follow the fields order.' );

		// Every top-level field of the fields enum, in its order.
		$top_level_fields = array( 'id', 'type', 'status', 'date', 'modified', 'slug', 'link', 'title', 'excerpt', 'content', 'author', 'parent' );

		$every_field = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'every_field_cpt',
					'ids'  => array( $post_id ),
				),
				'fields' => array_reverse( $top_level_fields ),
			)
		);

		$this->assertIsArray( $every_field, 'The query for every top-level field should succeed.' );
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
		$post = $result['items'][ array_search( $post_id, $ids, true ) ];
		$this->assertSame( 'Hello Content', $post['title']['rendered'], 'Subscribers should receive rendered titles.' );
		$this->assertStringContainsString( 'Body here.', $post['content']['rendered'], 'Subscribers should receive rendered content.' );
		$this->assertSame( array( 'rendered' ), array_keys( $post['content'] ), 'Subscribers should receive only the requested rendered content.' );
	}

	/**
	 * Users who cannot edit another user's post get it without the raw fields they explicitly
	 * request, rather than an error or no post.
	 *
	 * @ticket 66268
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 * @since 7.2.0
	 *
	 * @param string $role The role to test.
	 */
	public function test_raw_field_requests_omit_raw_values_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$post_id = self::$post_ids['limited_role_content'];

		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'title.raw', 'excerpt.raw', 'content.raw' ),
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
			'Raw field requests should return the readable post without the raw values.'
		);
	}

	/**
	 * Returns statuses that are neither publicly viewable nor `private`: a draft, a public status
	 * that is not viewable, and a custom status flagged private, which WP_Query does not narrow
	 * to the current user's own posts as it does `private`.
	 *
	 * @return array<string, array{status: string}> Status test cases.
	 */
	public static function data_statuses_that_require_edit_access(): array {
		return array(
			'draft'                         => array(
				'status' => 'draft',
			),
			'public status, not viewable'   => array(
				'status' => 'public_hidden',
			),
			'custom status flagged private' => array(
				'status' => 'members_only',
			),
		);
	}

	/**
	 * Subscribers cannot request posts in a status that requires edit access.
	 *
	 * @ticket 66268
	 * @dataProvider data_statuses_that_require_edit_access
	 * @since 7.2.0
	 *
	 * @param string $status The status to request.
	 */
	public function test_subscriber_cannot_request_statuses_that_require_edit_access( string $status ): void {
		register_post_status(
			'public_hidden',
			array(
				'label'              => 'Public hidden',
				'public'             => true,
				'publicly_queryable' => false,
			)
		);
		register_post_status(
			'members_only',
			array(
				'label'   => 'Members only',
				'private' => true,
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type'   => 'post',
					'status' => array( $status ),
				),
			)
		);

		$this->assertWPError( $result, 'Subscribers should not be allowed to query posts in a status that requires edit access.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'A subscriber query for a status that requires edit access should return a permission error.' );
	}

	/**
	 * Subscribers can query private posts, and get only their own.
	 *
	 * Another user's private post is neither returned nor counted, because a subscriber lacks
	 * `read_private_posts`.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_subscriber_gets_only_their_own_private_posts(): void {
		$own_id   = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['subscriber'],
				'post_status' => 'private',
			)
		);
		$other_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'private',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_ability();

		$ability  = wp_get_ability( 'core/content-query' );
		$expected = array(
			'items'      => array(
				array( 'id' => $own_id ),
			),
			'pagination' => array(
				'total'       => 1,
				'total_pages' => 1,
			),
		);

		$by_status = $ability->execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'status' => array( 'private' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( $expected, $by_status, 'A query for private posts should return only the subscriber\'s own private post.' );

		$by_ids = $ability->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $own_id, $other_id ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( $expected, $by_ids, 'A query by ID without a status should return only the subscriber\'s own private post.' );
	}

	/**
	 * Registers a post type with its own capabilities, such as `edit_books`, instead of those
	 * of posts.
	 *
	 * No role has these capabilities, so tests grant them with
	 * create_subscriber_with_capabilities().
	 *
	 * @since 7.2.0
	 */
	private function register_post_type_with_own_capabilities(): void {
		register_post_type(
			'book',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'capability_type'   => array( 'book', 'books' ),
				'map_meta_cap'      => true,
				'supports'          => array( 'title', 'editor', 'author' ),
			)
		);
	}

	/**
	 * Creates a subscriber who also has the given capabilities.
	 *
	 * @since 7.2.0
	 *
	 * @param list<string> $capabilities The capabilities to grant.
	 * @return int The user ID.
	 */
	private function create_subscriber_with_capabilities( array $capabilities ): int {
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		foreach ( $capabilities as $capability ) {
			$user->add_cap( $capability );
		}

		return $user->ID;
	}

	/**
	 * Querying the drafts of a post type with its own capabilities requires the edit capability
	 * of that post type, not that of posts.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_drafts_of_a_post_type_with_own_capabilities_require_its_edit_capability(): void {
		$this->register_post_type_with_own_capabilities();

		$draft_id = self::factory()->post->create(
			array(
				'post_type'   => 'book',
				'post_author' => self::$user_ids['author'],
				'post_status' => 'draft',
			)
		);

		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );
		$input   = array(
			'where'  => array(
				'type'   => 'book',
				'status' => array( 'draft' ),
			),
			'fields' => array( 'id' ),
		);

		$this->login_as( 'editor' );
		$denied = $ability->execute( $input );

		$this->assertWPError( $denied, 'An editor, who can edit posts but not books, should not query draft books.' );
		$this->assertSame( 'ability_invalid_permissions', $denied->get_error_code(), 'A draft query without the edit capability of the post type should return a permission error.' );

		wp_set_current_user( $this->create_subscriber_with_capabilities( array( 'edit_books', 'edit_others_books' ) ) );

		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $draft_id ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$ability->execute( $input ),
			'A user who can edit others\' books, but not posts, should get another user\'s draft book.'
		);
	}

	/**
	 * Another user's private post of a post type with its own capabilities is only returned and
	 * counted with the `read_private` capability of that post type, not that of posts.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_private_posts_of_a_post_type_with_own_capabilities_require_its_read_private_capability(): void {
		$this->register_post_type_with_own_capabilities();

		$private_id = self::factory()->post->create(
			array(
				'post_type'   => 'book',
				'post_author' => self::$user_ids['author'],
				'post_status' => 'private',
			)
		);

		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );
		$input   = array(
			'where'  => array(
				'type'   => 'book',
				'status' => array( 'private' ),
			),
			'fields' => array( 'id' ),
		);

		$this->login_as( 'editor' );

		$this->assertSame( self::EMPTY_RESULT, $ability->execute( $input ), 'An editor, who can read private posts but not private books, should neither get nor count another user\'s private book.' );

		wp_set_current_user( $this->create_subscriber_with_capabilities( array( 'read_private_books' ) ) );

		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $private_id ),
				),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$ability->execute( $input ),
			'A user who can read private books, but not private posts, should get another user\'s private book.'
		);
	}

	/**
	 * A user who can read private posts but cannot edit posts gets another user's private
	 * posts, but cannot query drafts, so a query by ID without a status covers the private post
	 * but not the draft.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_user_who_can_read_private_posts_but_not_edit_posts_gets_private_posts_but_not_drafts(): void {
		$private_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'private',
			)
		);
		$draft_id   = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( $this->create_subscriber_with_capabilities( array( 'read_private_posts' ) ) );
		$this->register_ability();

		$ability  = wp_get_ability( 'core/content-query' );
		$expected = array(
			'items'      => array(
				array( 'id' => $private_id ),
			),
			'pagination' => array(
				'total'       => 1,
				'total_pages' => 1,
			),
		);

		$by_status = $ability->execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'status' => array( 'private' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( $expected, $by_status, 'A query for private posts should return another user\'s private post.' );

		$drafts = $ability->execute(
			array(
				'where' => array(
					'type'   => 'post',
					'status' => array( 'draft' ),
				),
			)
		);

		$this->assertWPError( $drafts, 'A user who cannot edit posts should not query drafts.' );
		$this->assertSame( 'ability_invalid_permissions', $drafts->get_error_code(), 'A draft query without edit access should return a permission error.' );

		$by_ids = $ability->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $private_id, $draft_id ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( $expected, $by_ids, 'A query by ID without a status should cover the private post but not the draft.' );
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

		self::factory()->post->create(
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
				'where'  => array(
					'type'   => 'post',
					'status' => array( 'draft' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $draft_b ), wp_list_pluck( $result['items'], 'id' ), 'Authors should receive only drafts they can read.' );
		$this->assertSame( 2, $result['pagination']['total'], 'Totals should include rows withheld by row-level permission checks.' );
		$this->assertSame( 1, $result['pagination']['total_pages'], 'Page counts should be based on the underlying query total, matching REST behavior.' );
	}

	/**
	 * Returns roles with the posts that a query by ID without a status returns to them, and the
	 * total they get.
	 *
	 * The posts are named by status and by owner: the `own_` posts belong to the `author` role,
	 * and the published post and the `other_` posts to another author.
	 *
	 * The total counts the posts that the query covers, including withheld ones. Another user's
	 * private post is only covered with `read_private_posts`, so the subscriber's and the
	 * author's totals leave it out while the editor's counts it.
	 *
	 * @return array<string, array{role: string, expected: list<string>, total: int}> Role test cases.
	 */
	public static function data_default_statuses_with_ids_by_role(): array {
		return array(
			'subscriber' => array(
				'role'     => 'subscriber',
				'expected' => array( 'publish' ),
				'total'    => 1,
			),
			'author'     => array(
				'role'     => 'author',
				'expected' => array( 'publish', 'own_draft', 'own_pending', 'own_future', 'own_private' ),
				'total'    => 8,
			),
			'editor'     => array(
				'role'     => 'editor',
				'expected' => array( 'publish', 'own_draft', 'own_pending', 'own_future', 'own_private', 'other_draft', 'other_pending', 'other_future', 'other_private' ),
				'total'    => 9,
			),
		);
	}

	/**
	 * A query by ID without a status covers every status that the current user may query, and
	 * returns the posts in them that the user may read.
	 *
	 * A subscriber may query published and private posts. An author may query every status and
	 * reads their own posts in any of them, while another user's drafts, pending and scheduled
	 * posts are withheld, though counted. Another user's private post is neither returned nor
	 * counted, because the subscriber and the author lack `read_private_posts`. An editor reads
	 * posts in every status.
	 *
	 * @ticket 66268
	 * @dataProvider data_default_statuses_with_ids_by_role
	 * @since 7.2.0
	 *
	 * @param string       $role     The role to query as.
	 * @param list<string> $expected The posts the role should get.
	 * @param int          $total    The total the role should get.
	 */
	public function test_ids_without_a_status_cover_the_statuses_each_role_may_query( string $role, array $expected, int $total ): void {
		$owners = array(
			'own'   => self::$user_ids['author'],
			'other' => self::$user_ids['author_secondary'],
		);
		$posts  = array(
			'publish' => self::factory()->post->create(
				array(
					'post_author' => $owners['other'],
					'post_status' => 'publish',
				)
			),
		);
		foreach ( $owners as $owner => $author_id ) {
			foreach ( array( 'draft', 'pending', 'future', 'private' ) as $status ) {
				$args = array(
					'post_author' => $author_id,
					'post_status' => $status,
				);
				if ( 'future' === $status ) {
					// A scheduled post needs a date in the future.
					$args['post_date'] = '2099-01-01 10:00:00';
				}

				$posts[ "{$owner}_{$status}" ] = self::factory()->post->create( $args );
			}
		}

		$this->assertSame( 'future', get_post_status( $posts['own_future'] ), 'Precondition: the scheduled post should keep its status.' );

		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array_values( $posts ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The query by ID should succeed.' );
		$this->assertSameSets(
			array_values( array_intersect_key( $posts, array_flip( $expected ) ) ),
			wp_list_pluck( $result['items'], 'id' ),
			'The query should return the posts the role may read, in the statuses the role may query.'
		);
		$this->assertSame( $total, $result['pagination']['total'], 'The total should count every post the query covers, including withheld ones.' );
	}

	/**
	 * An author gets their own draft from a query by ID or by slug that gives no status, but
	 * not from a query without IDs or slugs, whose default status stays `publish`, nor from a
	 * query whose explicit status leaves drafts out.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_gets_their_own_draft_only_by_ids_or_slugs_without_a_status(): void {
		$draft_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_name'   => 'own-draft',
				'post_status' => 'draft',
			)
		);

		$this->login_as( 'author' );
		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );

		$by_id = $ability->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $draft_id ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $draft_id ), wp_list_pluck( $by_id['items'], 'id' ), 'A query by ID without a status should return the author\'s own draft.' );

		$by_slug = $ability->execute(
			array(
				'where'  => array(
					'type'  => 'post',
					'slugs' => array( 'own-draft' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( $draft_id ), wp_list_pluck( $by_slug['items'], 'id' ), 'A query by slug without a status should return the author\'s own draft.' );

		$without_ids = $ability->execute(
			array(
				'where'      => array( 'type' => 'post' ),
				'fields'     => array( 'id' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertNotContains( $draft_id, wp_list_pluck( $without_ids['items'], 'id' ), 'A query without IDs or slugs should keep the default status of publish.' );

		$published_only = $ability->execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'ids'    => array( $draft_id ),
					'status' => array( 'publish' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( self::EMPTY_RESULT, $published_only, 'An explicit status should apply next to the IDs, so the draft should not be returned.' );
	}

	/**
	 * Returns roles that can edit and read another user's post in any status.
	 *
	 * @return array<string, array{role: string}> Role test cases.
	 */
	public static function data_roles_with_edit_access_to_other_users_posts(): array {
		return array(
			'administrator' => array(
				'role' => 'administrator',
			),
			'editor'        => array(
				'role' => 'editor',
			),
		);
	}

	/**
	 * A query by ID without a status does not cover the internal `trash` status, even for a
	 * role that can edit the trashed post.
	 *
	 * The default statuses with IDs are the statuses that are not internal, so the query
	 * neither returns nor counts the trashed post. The post is named by ID, since trashing a
	 * post renames its slug.
	 *
	 * @ticket 66268
	 * @dataProvider data_roles_with_edit_access_to_other_users_posts
	 * @since 7.2.0
	 *
	 * @param string $role The role to query as.
	 */
	public function test_ids_without_a_status_do_not_cover_trashed_posts( string $role ): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $post_id );

		$this->assertSame( 'trash', get_post_status( $post_id ), 'Precondition: the post should be in the trash.' );

		$this->login_as( $role );
		$this->register_ability();

		$this->assertTrue( current_user_can( 'edit_post', $post_id ), 'Precondition: the role should be able to edit the trashed post.' );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( self::EMPTY_RESULT, $result, 'A query by ID without a status should neither return nor count a trashed post.' );
	}

	/**
	 * The parent filter is rejected for non-hierarchical post types, mirroring REST.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_rejects_parent_filter_for_non_hierarchical_post_type(): void {
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
	public function test_query_filters_pages_by_parent(): void {
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
	 * The author filter is rejected for post types without author support.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_rejects_author_filter_for_post_type_without_author_support(): void {
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
					'type'   => 'no_author_cpt',
					'author' => array( 'slug' => get_userdata( self::$user_ids['author'] )->user_nicename ),
				),
			)
		);

		$this->assertWPError( $result, 'The author filter should be rejected for post types without author support.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'Unsupported author filters should return a filter error.' );
		$this->assertSame(
			array( 'where[author]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the unsupported author filter to the error message.'
		);
		$this->assertSame( 400, $result->get_error_data()['status'], 'The unsupported author filter should be reported as a bad request.' );
	}

	/**
	 * For a post type without author support, the author filter is rejected before its value
	 * is checked, so a malformed value reports the missing author support.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_execute_callback_rejects_author_filter_for_post_type_without_author_support_before_its_value(): void {
		register_post_type(
			'no_author_cpt',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title' ),
			)
		);

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type'   => 'no_author_cpt',
					'author' => 'not-an-object',
				),
			)
		);

		$this->assertWPError( $result, 'The author filter should be rejected for post types without author support whatever its value.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'The unsupported author filter should fail as an invalid filter before its value is checked.' );
		$this->assertSame( 'The author filter is only supported for post types that support authors.', $result->get_error_message(), 'The error should report the missing author support, not the malformed value.' );
		$this->assertSame(
			array( 'where[author]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the unsupported author filter, not its value, to the error message.'
		);
	}

	/**
	 * The author filter narrows queries to posts by the author with the given slug, and each
	 * post returns that slug as `author.slug`.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_filters_posts_by_author(): void {
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
					'type'   => 'post',
					'author' => array( 'slug' => $author_slug ),
				),
				'fields'     => array( 'id', 'author.slug' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'An author slug should pass validation.' );

		$ids = wp_list_pluck( $result['items'], 'id' );

		$this->assertContains( $mine_id, $ids, 'The author slug should include the author\'s posts.' );
		$this->assertNotContains( $other_id, $ids, 'The author slug should exclude other authors\' posts.' );
		$this->assertSame( array( $author_slug ), array_unique( wp_list_pluck( wp_list_pluck( $result['items'], 'author' ), 'slug' ) ), 'Each post should return the author slug the filter named.' );
	}

	/**
	 * The author filter narrows queries to posts by the author with the given ID, and each
	 * post returns that ID as `author.id`.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_filters_posts_by_author_id(): void {
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

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'      => array(
					'type'   => 'post',
					'author' => array( 'id' => self::$user_ids['author'] ),
				),
				'fields'     => array( 'id', 'author.id' ),
				'pagination' => array( 'per_page' => 100 ),
			)
		);

		$this->assertIsArray( $result, 'An author ID should pass validation.' );

		$ids = wp_list_pluck( $result['items'], 'id' );

		$this->assertContains( $mine_id, $ids, 'The author ID should include the author\'s posts.' );
		$this->assertNotContains( $other_id, $ids, 'The author ID should exclude other authors\' posts.' );
		$this->assertSame( array( self::$user_ids['author'] ), array_unique( wp_list_pluck( wp_list_pluck( $result['items'], 'author' ), 'id' ) ), 'Each post should return the author ID the filter named.' );
	}

	/**
	 * An author filter given as an object behaves like its array form.
	 *
	 * Schema validation accepts a `stdClass` for every object in the input, so the filter
	 * must read the ID and the slug from either form.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_filter_given_as_an_object_behaves_like_an_array(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'publish',
			)
		);
		$ability = wp_get_ability( 'core/content-query' );
		$query   = static function ( $author ) use ( $ability ) {
			return $ability->execute(
				array(
					'where'  => array(
						'type'   => 'post',
						'author' => $author,
					),
					'fields' => array( 'id' ),
				)
			);
		};

		$expected = array(
			'items'      => array(
				array( 'id' => $post_id ),
			),
			'pagination' => array(
				'total'       => 1,
				'total_pages' => 1,
			),
		);

		$this->assertSame( $expected, $query( array( 'id' => self::$user_ids['author'] ) ), 'Precondition: the array form should return the author\'s post.' );
		$this->assertSame( $expected, $query( (object) array( 'id' => self::$user_ids['author'] ) ), 'An object author filter with an ID should return the author\'s post.' );
		$this->assertSame( $expected, $query( (object) array( 'slug' => get_userdata( self::$user_ids['author'] )->user_nicename ) ), 'An object author filter with a slug should return the author\'s post.' );
	}

	/**
	 * An author filter with both an ID and a slug matches a post only when both name its
	 * author, since a post must match every given filter.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_filter_with_an_id_and_a_slug_matches_posts_only_when_both_name_the_author(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		$post_id     = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'publish',
			)
		);
		$author_id   = self::$user_ids['author'];
		$author_slug = get_userdata( $author_id )->user_nicename;
		$other_slug  = get_userdata( self::$user_ids['author_secondary'] )->user_nicename;
		$ability     = wp_get_ability( 'core/content-query' );
		$query       = static function ( array $author ) use ( $ability ) {
			return $ability->execute(
				array(
					'where'  => array(
						'type'   => 'post',
						'author' => $author,
					),
					'fields' => array( 'id' ),
				)
			);
		};

		$same_user = $query(
			array(
				'id'   => $author_id,
				'slug' => $author_slug,
			)
		);

		$this->assertIsArray( $same_user, 'An ID and a slug that name the same user should pass validation.' );
		$this->assertSame( array( $post_id ), wp_list_pluck( $same_user['items'], 'id' ), 'An ID and a slug that name the same user should return that user\'s posts.' );
		$this->assertSame(
			self::EMPTY_RESULT,
			$query(
				array(
					'id'   => $author_id,
					'slug' => $other_slug,
				)
			),
			'An ID and a slug that name different users should match no post.'
		);
		$this->assertSame(
			self::EMPTY_RESULT,
			$query(
				array(
					'id'   => $author_id,
					'slug' => 'no-such-user',
				)
			),
			'An ID next to a slug that names no user should match no post.'
		);
		$this->assertSame(
			self::EMPTY_RESULT,
			$query(
				array(
					'id'   => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
					'slug' => $author_slug,
				)
			),
			'A slug next to an ID of another user should match no post.'
		);
	}

	/**
	 * A query returns only posts of the requested post type, also when a query filter adds
	 * post types to the blog home.
	 *
	 * WP_Query treats the ability's query as the blog home, so a `pre_get_posts` callback
	 * without an is_main_query() check also runs for it. The read check would let through
	 * posts of other exposed post types.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_skips_posts_of_other_post_types(): void {
		register_post_type(
			'hidden_cpt',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor' ),
			)
		);

		$ids = array();
		foreach ( array( 'post', 'page', 'hidden_cpt' ) as $post_type ) {
			$ids[] = self::factory()->post->create(
				array(
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
					'type' => 'post',
					'ids'  => $ids,
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame(
			array(
				'items'      => array(
					array( 'id' => $ids[0] ),
				),
				'pagination' => array(
					'total'       => 3,
					'total_pages' => 1,
				),
			),
			$result,
			'The query by ID should return only the post of the requested type, and count the posts that the query filter adds.'
		);
	}

	/**
	 * A query by ID or by status returns another user's private post to a role that can read
	 * it, also when a query filter adds post types to the blog home.
	 *
	 * With several post types, WP_Query checks private posts against
	 * `read_private_multiple_post_types`, which no role has, so a query for the readable posts
	 * only would leave the post out.
	 *
	 * @ticket 66268
	 * @dataProvider data_roles_with_edit_access_to_other_users_posts
	 * @since 7.2.0
	 *
	 * @param string $role The role to query as.
	 */
	public function test_another_users_private_post_is_returned_when_a_query_filter_adds_post_types( string $role ): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'private',
			)
		);

		add_action(
			'pre_get_posts',
			static function ( WP_Query $query ): void {
				if ( $query->is_home() ) {
					$query->set( 'post_type', array( 'post', 'page' ) );
				}
			}
		);

		$this->login_as( $role );
		$this->register_ability();

		$ability  = wp_get_ability( 'core/content-query' );
		$expected = array(
			'items'      => array(
				array( 'id' => $post_id ),
			),
			'pagination' => array(
				'total'       => 1,
				'total_pages' => 1,
			),
		);

		$by_ids = $ability->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( $expected, $by_ids, 'A query by ID should return another user\'s private post to a role that can read it.' );

		$by_status = $ability->execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'status' => array( 'private' ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( $expected, $by_status, 'A query for private posts should return another user\'s private post to a role that can read it.' );
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
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
	 * Password-protected rendered content and excerpts are withheld from users who cannot edit
	 * the post.
	 *
	 * @ticket 66268
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 * @since 7.2.0
	 *
	 * @param string $role The role to test.
	 */
	public function test_password_protected_rendered_content_is_empty_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$post_id = self::$post_ids['password_protected'];

		$this->login_as( $role );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'id', 'content.rendered', 'content.protected', 'excerpt.rendered', 'excerpt.protected' ),
			)
		);

		$this->assertSame( '', $result['items'][0]['content']['rendered'], 'Password-protected rendered content should be withheld.' );
		$this->assertTrue( $result['items'][0]['content']['protected'], 'The protected flag should reveal the content is password-protected.' );
		$this->assertSame( '', $result['items'][0]['excerpt']['rendered'], 'Password-protected rendered excerpts should be withheld.' );
		$this->assertTrue( $result['items'][0]['excerpt']['protected'], 'The protected flag should reveal the excerpt is password-protected.' );
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['published'] ),
				),
				'fields' => array( 'id', 'title.rendered' ),
			)
		);

		$this->assertIsArray( $result, 'A non-string rendered title should not fail the query.' );
		$this->assertSame( '', $result['items'][0]['title']['rendered'], 'A non-string rendered title should be returned as an empty string.' );
	}

	/**
	 * Title, excerpt and permalink filters run with the requested post as the global context,
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
		add_filter(
			'post_link',
			static function ( $link ): string {
				return add_query_arg( 'context', get_the_ID(), (string) $link );
			}
		);

		$result              = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $target_id ),
				),
				'fields' => array( 'id', 'link', 'title.rendered', 'excerpt.rendered' ),
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
		$this->assertStringContainsString( 'context=' . $target_id, $result['items'][0]['link'], 'Permalink filters should see the requested post as the current post.' );
		$this->assertSame(
			$surrounding->ID,
			$restored_context_id,
			'The surrounding post context should be restored after rendering.'
		);
	}

	/**
	 * Rendering a field unsets the loop globals that were not set before.
	 *
	 * Without a surrounding post, as in a REST request, wp_reset_postdata() cannot restore
	 * the globals that setup_postdata() populates, because the main query has no post.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_rendered_fields_unset_loop_globals_that_were_not_set(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		foreach ( self::LOOP_GLOBALS as $name ) {
			unset( $GLOBALS[ $name ] );
		}

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
				'fields' => array( 'content.rendered' ),
			)
		);

		$this->assertArrayHasKey( 'rendered', $result['items'][0]['content'], 'Precondition: the rendered content should be returned.' );
		$this->assertSame( array(), $this->get_loop_globals(), 'Loop globals that were not set should be unset again after rendering.' );
	}

	/**
	 * Rendering a field restores the surrounding loop globals as they were, rather than as
	 * setup_postdata() computes them for the surrounding post.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_rendered_fields_restore_surrounding_loop_globals(): void {
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
				'fields' => array( 'content.rendered' ),
			)
		);

		$this->assertArrayHasKey( 'rendered', $result['items'][0]['content'], 'Precondition: the rendered content should be returned.' );
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
	 * @since 7.2.0
	 */
	public function test_rendered_fields_restore_loop_globals_bound_by_the_caller(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$surrounding = get_post( self::$post_ids['published'] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Establishes a surrounding context to verify the ability restores it.
		$GLOBALS['post'] = $surrounding;
		setup_postdata( $surrounding );

		$surrounding_globals = $this->get_loop_globals();

		$render_in_template = static function (): array {
			global $post, $id;

			$result = wp_get_ability( 'core/content-query' )->execute(
				array(
					'where'  => array(
						'type' => 'post',
						'ids'  => array( self::$post_ids['limited_role_content'] ),
					),
					'fields' => array( 'content.rendered' ),
				)
			);

			return array( $result, $post->ID, $id );
		};

		list( $result, $bound_post_id, $bound_id ) = $render_in_template();

		$this->assertArrayHasKey( 'rendered', $result['items'][0]['content'], 'Precondition: the rendered content should be returned.' );
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
	 * @since 7.2.0
	 */
	public function test_rendered_fields_set_up_a_global_post_that_was_not_set_up(): void {
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
				'fields' => array( 'content.rendered' ),
			)
		);

		$this->assertArrayHasKey( 'rendered', $result['items'][0]['content'], 'Precondition: the rendered content should be returned.' );
		$this->assertSame( $main_post->ID, get_the_ID(), 'The main post should be the global post again.' );
		$this->assertSame( 'Body here.', get_the_content(), 'get_the_content() without a post should return the main post content.' );
	}

	/**
	 * Rendering a field with no global post, such as on `init`, does not start the loop, so
	 * get_the_content() without a post still works for a global post set afterwards.
	 *
	 * Rendering fires `the_post`, after which get_the_content() without a post reads the loop
	 * globals. They are unset again when there was no global post, so the main post that
	 * WP::register_globals() sets afterwards made it throw a TypeError. The ability resets
	 * the count when it was the first thing to fire the action and no global post was set.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_rendered_fields_without_a_global_post_do_not_start_the_loop(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		foreach ( self::LOOP_GLOBALS as $name ) {
			unset( $GLOBALS[ $name ] );
		}

		// No loop has started yet, as on `init`.
		unset( $GLOBALS['wp_actions']['the_post'] );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
				'fields' => array( 'content.rendered' ),
			)
		);

		$this->assertArrayHasKey( 'rendered', $result['items'][0]['content'], 'Precondition: the rendered content should be returned.' );
		$this->assertSame( 0, did_action( 'the_post' ), 'Rendering a field with no global post should not count as starting the loop.' );

		// WP::register_globals() sets the main post this way, before the loop sets it up.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Establishes a global post that was not set up.
		$GLOBALS['post'] = get_post( self::$post_ids['published_content'] );

		$this->assertSame( 'Body here.', get_the_content(), 'get_the_content() without a post should return the content of a global post set afterwards.' );
	}

	/**
	 * Rendering a field with no global post keeps the `the_post` count of a loop that had
	 * already started, since the ability resets the count only when it was the first thing
	 * to fire the action.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_rendered_fields_without_a_global_post_keep_an_earlier_the_post_count(): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		// Another loop has already set up a post, and the global post was cleared afterwards.
		setup_postdata( get_post( self::$post_ids['published'] ) );
		foreach ( self::LOOP_GLOBALS as $name ) {
			unset( $GLOBALS[ $name ] );
		}

		$the_post_count = did_action( 'the_post' );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
				'fields' => array( 'content.rendered' ),
			)
		);

		$this->assertArrayHasKey( 'rendered', $result['items'][0]['content'], 'Precondition: the rendered content should be returned.' );
		$this->assertSame( $the_post_count + 1, did_action( 'the_post' ), 'The ability should keep the `the_post` count of a loop that had already started.' );
	}

	/**
	 * Returns the posts whose `the_post` callbacks throw: the requested post, as it is set
	 * up, and the surrounding post, as it is set up again afterwards.
	 *
	 * @return array<string, array{throwing_post: string}> Throwing post test cases.
	 */
	public static function data_posts_whose_the_post_callbacks_throw(): array {
		return array(
			'requested post'   => array(
				'throwing_post' => 'limited_role_content',
			),
			'surrounding post' => array(
				'throwing_post' => 'published',
			),
		);
	}

	/**
	 * A `the_post` callback that throws fails the query without leaving the requested post
	 * in the loop globals.
	 *
	 * @ticket 66268
	 * @dataProvider data_posts_whose_the_post_callbacks_throw
	 * @since 7.2.0
	 *
	 * @param string $throwing_post The fixture name of the post whose `the_post` callback throws.
	 */
	public function test_post_context_is_restored_when_a_the_post_callback_throws( string $throwing_post ): void {
		$this->login_as( 'subscriber' );
		$this->register_ability();

		$surrounding = get_post( self::$post_ids['published'] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Establishes a surrounding context to verify the ability restores it.
		$GLOBALS['post'] = $surrounding;
		setup_postdata( $surrounding );

		/*
		 * Setting up the surrounding post again undoes a `$more` that a template set, so only
		 * putting back the saved values restores it.
		 */
		$GLOBALS['more'] = 1;

		$surrounding_globals = $this->get_loop_globals();

		$throwing_post_id = self::$post_ids[ $throwing_post ];
		add_action(
			'the_post',
			static function ( WP_Post $post ) use ( $throwing_post_id ): void {
				if ( $throwing_post_id === $post->ID ) {
					throw new RuntimeException( 'A the_post callback failed.' );
				}
			}
		);

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::$post_ids['limited_role_content'] ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertWPError( $result, 'A throwing the_post callback should fail the query.' );
		$this->assertSame( 'ability_callback_exception', $result->get_error_code(), 'A throwing the_post callback should return the callback exception error.' );
		$this->assertSame( $surrounding_globals, $this->get_loop_globals(), 'The surrounding loop globals should be restored as they were.' );
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $owned_id ),
				),
				'fields' => array( 'id', 'content.rendered' ),
			)
		);

		$this->assertSame( array( $owned_id ), wp_list_pluck( $result['items'], 'id' ), 'Precondition: the author should read their own protected post.' );
		$this->assertFalse( $gates['owned'], 'The filter should unlock a protected post the current user can edit.' );
		$this->assertTrue( $gates['other'], 'The filter should keep the gate on a protected post the current user cannot edit.' );
		$this->assertFalse( $gates['public'], 'The filter should leave posts that do not require a password ungated.' );
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
					'type' => 'post',
					'ids'  => $ids,
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
					'type' => 'post',
					'ids'  => $ids,
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
	public static function data_whole_numbers_that_are_not_integers(): array {
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
				'type' => 'post',
				'ids'  => self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) ),
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
	public static function data_post_ids_that_are_not_integers(): array {
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
	 * A query honors an ID in `where.ids` that the integer schema accepts even when it is not a
	 * PHP integer, as when the MCP adapter passes a float that a JSON encoder produced.
	 *
	 * Read as invalid, the ID would be dropped, and the query would fail as an invalid filter.
	 *
	 * @ticket 66268
	 * @dataProvider data_post_ids_that_are_not_integers
	 * @since 7.2.0
	 *
	 * @param float|string $id The value to send for the imported post ID.
	 */
	public function test_query_honors_an_id_that_is_not_an_integer( $id ): void {
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $id ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The post should be returned.' );
		$this->assertSame( array( $post_id ), wp_list_pluck( $result['items'], 'id' ), 'The query should resolve to the requested post.' );
	}

	/**
	 * A query honors a `where.parent` filter that the integer schema accepts even when it is
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
					'type' => 'post',
					'ids'  => array( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ),
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
	public static function data_page_sizes_set_by_a_query_filter(): array {
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
	 * A query counts the pages with the page size it ran with.
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
				'type' => 'post',
				'ids'  => $ids,
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
	 * IDs return every requested post when `pagination.per_page` is omitted.
	 *
	 * Without this the default page size silently truncates a batch load: a caller asking
	 * for a known set of IDs would receive only the first page of them.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_ids_return_every_requested_post_without_per_page(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();

		// More than DEFAULT_PER_PAGE (10) so truncation would be visible.
		$ids = self::factory()->post->create_many( 15, array( 'post_status' => 'publish' ) );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => $ids,
				),
				'fields' => array( 'id' ),
			)
		);

		$returned = wp_list_pluck( $result['items'], 'id' );
		sort( $returned );
		sort( $ids );

		$this->assertSame( $ids, $returned, 'Every requested post ID should be returned on a single page.' );
		$this->assertSame( 15, $result['pagination']['total'], 'The total should cover every requested post.' );
		$this->assertSame( 1, $result['pagination']['total_pages'], 'The requested posts should fit on a single page by default.' );
	}

	/**
	 * Returns the field paths that request a rendered excerpt or content, either directly or
	 * through the whole object.
	 *
	 * @return array<string, array{field: string}> Post meta priming test cases.
	 */
	public static function data_fields_that_prime_post_meta(): array {
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
							'type' => 'post',
							'ids'  => $ids,
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
	 * A query primes the authors of the whole page.
	 *
	 * Every returned post is set up with setup_postdata(), which reads the post's author, so
	 * without priming, each author on the page runs its own query, whatever fields are
	 * requested.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_primes_the_authors_of_the_page(): void {
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
			static function () use ( $ids ) {
				return wp_get_ability( 'core/content-query' )->execute(
					array(
						'where'  => array(
							'type' => 'post',
							'ids'  => $ids,
						),
						'fields' => array( 'id' ),
					)
				);
			},
			$result
		);

		$this->assertCount( 3, $result['items'], 'Precondition: the query should return the seeded posts.' );
		$this->assertSame( 1, $users_queries, 'Setting up the returned posts should read primed authors, not query once per author.' );
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
					'type' => 'post',
					'ids'  => $ids,
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertCount( 3, $result['items'], 'Precondition: the query should return the seeded posts.' );
		$this->assertNotEmpty( $priming, 'Precondition: the ability should query for the requested posts.' );
		$this->assertSame( array(), array_filter( $priming ), 'A lean projection should leave post meta priming off.' );
	}

	/**
	 * Requesting `link` primes the terms that `%category%` permalinks read for the whole page.
	 *
	 * Without priming, each returned row runs its own query.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_query_link_primes_the_terms_category_permalinks_read(): void {
		$this->login_as( 'administrator' );
		$this->register_ability();
		$this->set_permalink_structure( '/%category%/%postname%/' );

		$ids = self::factory()->post->create_many(
			3,
			array(
				'post_category' => array( self::factory()->category->create() ),
				'post_status'   => 'publish',
			)
		);

		$queries = $this->count_queries(
			'term_relationships',
			static function () use ( $ids ) {
				return wp_get_ability( 'core/content-query' )->execute(
					array(
						'where'  => array(
							'type' => 'post',
							'ids'  => $ids,
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
							'type' => 'page',
							'ids'  => $ids,
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
		$this->assertSame( 1, $result['pagination']['total_pages'], 'The returned posts should fit on a single page.' );

		foreach ( $result['items'] as $post_entry ) {
			$this->assertSame( array( 'id' ), array_keys( $post_entry ), 'A post whose requested fields do not apply should return only its ID.' );
		}
	}

	/**
	 * Site and GMT date fields report the correct instant and offset on non-UTC sites.
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'id', 'date.site', 'date.gmt', 'modified.site', 'modified.gmt' ),
			)
		);

		$this->assertSame( '2026-01-15T10:00:00-05:00', $result['items'][0]['date']['site'], 'The site date should carry the site timezone offset.' );
		$this->assertSame( '2026-01-15T15:00:00+00:00', $result['items'][0]['date']['gmt'], 'The GMT date should be the UTC instant with a UTC offset.' );
		$this->assertSame( '2026-01-16T11:00:00-05:00', $result['items'][0]['modified']['site'], 'The site modified date should carry the site timezone offset.' );
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
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
	public static function data_gmt_date_fields(): array {
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
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
	public static function data_date_fields(): array {
		return array(
			'date.site'     => array(
				'field'  => 'date.site',
				'column' => 'post_date',
			),
			'date.gmt'      => array(
				'field'  => 'date.gmt',
				'column' => 'post_date_gmt',
			),
			'modified.site' => array(
				'field'  => 'modified.site',
				'column' => 'post_modified',
			),
			'modified.gmt'  => array(
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
	 * @param string $field  The field path to request, such as `date.site`.
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
				'where'  => array(
					'type' => 'post',
					'ids'  => array( $post_id ),
				),
				'fields' => array( 'id', $field ),
			)
		);

		$this->assertIsArray( $result, 'A malformed date should pass output validation.' );
		$this->assertArrayHasKey( $key, $result['items'][0][ $object ], 'A malformed date should still be returned.' );
		$this->assertNull( $result['items'][0][ $object ][ $key ], 'A malformed date should be null.' );
	}

	/**
	 * Returns `where.author` values that are not an object holding an ID or a slug: values
	 * that are not objects, empty values, and objects with an unknown property.
	 *
	 * Schema validation rejects them all, so the test calls the execute callback directly.
	 *
	 * @return array<string, array{author: mixed}> The `where.author` values.
	 */
	public static function data_author_filters_without_an_id_or_a_slug(): array {
		return array(
			'string'                          => array(
				'author' => 'author-slug',
			),
			'integer'                         => array(
				'author' => 5,
			),
			'boolean'                         => array(
				'author' => true,
			),
			'list'                            => array(
				'author' => array( 'author-slug' ),
			),
			'null'                            => array(
				'author' => null,
			),
			'empty string'                    => array(
				'author' => '',
			),
			'empty array'                     => array(
				'author' => array(),
			),
			'empty object'                    => array(
				'author' => (object) array(),
			),
			'unknown property'                => array(
				'author' => array( 'name' => 'x' ),
			),
			'unknown property next to a slug' => array(
				'author' => array(
					'slug' => 'author-slug',
					'name' => 'x',
				),
			),
		);
	}

	/**
	 * Called directly, an author filter that is not an object holding an ID or a slug is
	 * rejected rather than dropped, which would widen the query to every author's posts.
	 *
	 * @ticket 66268
	 * @dataProvider data_author_filters_without_an_id_or_a_slug
	 * @since 7.2.0
	 *
	 * @param mixed $author The `where.author` value to send.
	 */
	public function test_execute_callback_rejects_an_author_filter_without_an_id_or_a_slug( $author ): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type'   => 'post',
					'author' => $author,
				),
			)
		);

		$this->assertWPError( $result, 'An author filter without an ID or a slug must not silently widen the query to all authors.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An author filter without an ID or a slug should fail as an invalid filter.' );
		$this->assertSame(
			array( 'where[author]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the malformed author filter to the error message.'
		);
		$this->assertSame( 400, $result->get_error_data()['status'], 'The malformed author filter should be reported as a bad request.' );
	}

	/**
	 * Returns `where.author.id` values that are not a positive integer.
	 *
	 * Schema validation rejects them all, so the test calls the execute callback directly.
	 *
	 * @return array<string, array{id: mixed}> The `where.author.id` values.
	 */
	public static function data_author_ids_that_are_not_positive_integers(): array {
		return array(
			'zero'             => array(
				'id' => 0,
			),
			'negative number'  => array(
				'id' => -5,
			),
			'fraction'         => array(
				'id' => 2.5,
			),
			'non-digit string' => array(
				'id' => 'abc',
			),
			'true'             => array(
				'id' => true,
			),
			'false'            => array(
				'id' => false,
			),
			'null'             => array(
				'id' => null,
			),
		);
	}

	/**
	 * Called directly, an author ID that is not a positive integer is rejected rather than
	 * coerced, which could match another user's posts or every author's posts.
	 *
	 * @ticket 66268
	 * @dataProvider data_author_ids_that_are_not_positive_integers
	 * @since 7.2.0
	 *
	 * @param mixed $id The `where.author.id` value to send.
	 */
	public function test_execute_callback_rejects_an_author_id_that_is_not_a_positive_integer( $id ): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type'   => 'post',
					'author' => array( 'id' => $id ),
				),
			)
		);

		$this->assertWPError( $result, 'An author ID that is not a positive integer must not be coerced into a query.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An author ID that is not a positive integer should fail as an invalid filter.' );
		$this->assertSame(
			array( 'where[author][id]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the malformed author ID to the error message.'
		);
		$this->assertSame( 400, $result->get_error_data()['status'], 'The malformed author ID should be reported as a bad request.' );
	}

	/**
	 * Returns the scalar types, other than integer, that carry a whole number.
	 *
	 * @return array<string, array{type: string}> The type names, as `settype()` takes them.
	 */
	public static function data_author_id_whole_number_types(): array {
		return array(
			'string of digits' => array(
				'type' => 'string',
			),
			'whole float'      => array(
				'type' => 'float',
			),
		);
	}

	/**
	 * Called directly, an author ID given as a string of digits or a whole float is accepted
	 * like a `parent` ID, and names the user with that ID.
	 *
	 * @ticket 66268
	 * @dataProvider data_author_id_whole_number_types
	 * @since 7.2.0
	 *
	 * @param string $type The type to give the ID as.
	 */
	public function test_execute_callback_accepts_an_author_id_given_as_a_whole_number( string $type ): void {
		$mine_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author_secondary'],
				'post_status' => 'publish',
			)
		);

		$author_id = self::$user_ids['author'];
		settype( $author_id, $type );

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'author' => array( 'id' => $author_id ),
				),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, "An author ID given as a {$type} should be accepted." );
		$this->assertSame( array( $mine_id ), wp_list_pluck( $result['items'], 'id' ), "An author ID given as a {$type} should return only that author's posts." );
	}

	/**
	 * Returns author filters with more than one problem, with the parameter the error
	 * should name: the object's shape is checked before its ID, and the ID before its slug.
	 *
	 * @return array<string, array{author: array<string, mixed>, param: string}> The filters and parameters.
	 */
	public static function data_author_filters_with_several_problems(): array {
		return array(
			'unknown property next to a bad id' => array(
				'author' => array(
					'id'   => 0,
					'name' => 'x',
				),
				'param'  => 'where[author]',
			),
			'bad id and bad slug'               => array(
				'author' => array(
					'id'   => 0,
					'slug' => 5,
				),
				'param'  => 'where[author][id]',
			),
			'bad id and empty slug'             => array(
				'author' => array(
					'id'   => 0,
					'slug' => '',
				),
				'param'  => 'where[author][id]',
			),
			'good id and bad slug'              => array(
				'author' => array(
					'id'   => 1,
					'slug' => 5,
				),
				'param'  => 'where[author][slug]',
			),
		);
	}

	/**
	 * Called directly, an author filter with several problems reports the first one found:
	 * the object's shape, then the ID, then the slug.
	 *
	 * @ticket 66268
	 * @dataProvider data_author_filters_with_several_problems
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $author The `where.author` value to send.
	 * @param string               $param  The parameter the error should name.
	 */
	public function test_execute_callback_reports_the_first_problem_of_an_author_filter( array $author, string $param ): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type'   => 'post',
					'author' => $author,
				),
			)
		);

		$this->assertWPError( $result, 'An author filter with several problems should be rejected.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An author filter with several problems should fail as an invalid filter.' );
		$this->assertSame(
			array( $param => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should name the first problem found in the author filter.'
		);
	}

	/**
	 * Returns `where.author.slug` values that are not a non-empty string.
	 *
	 * Schema validation rejects them all, so the test calls the execute callback directly.
	 *
	 * @return array<string, array{slug: mixed}> The `where.author.slug` values.
	 */
	public static function data_author_slugs_that_are_not_non_empty_strings(): array {
		return array(
			'integer'      => array(
				'slug' => 5,
			),
			'boolean'      => array(
				'slug' => true,
			),
			'list'         => array(
				'slug' => array( 'author-slug' ),
			),
			'object'       => array(
				'slug' => (object) array( 'slug' => 'author-slug' ),
			),
			'null'         => array(
				'slug' => null,
			),
			'empty string' => array(
				'slug' => '',
			),
		);
	}

	/**
	 * Called directly, an author slug that is not a string, or is empty, is rejected rather
	 * than silently dropped, which would widen the query to every author's posts.
	 *
	 * @ticket 66268
	 * @dataProvider data_author_slugs_that_are_not_non_empty_strings
	 * @since 7.2.0
	 *
	 * @param mixed $slug The `where.author.slug` value to send.
	 */
	public function test_execute_callback_rejects_an_author_slug_that_is_not_a_non_empty_string( $slug ): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type'   => 'post',
					'author' => array( 'slug' => $slug ),
				),
			)
		);

		$this->assertWPError( $result, 'An author slug that is not a non-empty string must not silently widen the query to all authors.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An author slug that is not a non-empty string should fail as an invalid filter.' );
		$this->assertSame(
			array( 'where[author][slug]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the malformed author slug to the error message.'
		);
		$this->assertSame( 400, $result->get_error_data()['status'], 'The malformed author slug should be reported as a bad request.' );
	}

	/**
	 * An author slug that names no user matches no post, rather than being dropped, which
	 * would widen the query to every author's posts.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_filter_with_a_slug_that_names_no_user_matches_no_post(): void {
		self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type'   => 'post',
					'author' => array( 'slug' => 'no-such-user' ),
				),
			)
		);

		$this->assertSame( self::EMPTY_RESULT, $result, 'An author slug that names no user should match no post.' );
	}

	/**
	 * An author ID matches the author ID stored on the post without looking the user up, so
	 * the posts of a deleted user are still found by the `author.id` their output returns.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_filter_by_id_matches_the_posts_of_a_deleted_user(): void {
		// No user has this ID, as when the author was deleted after writing the post.
		$orphan_id = self::factory()->post->create(
			array(
				'post_author' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author'],
				'post_status' => 'publish',
			)
		);

		$this->assertFalse( get_userdata( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ), 'Precondition: no user should have the stored author ID.' );

		$this->login_as( 'administrator' );
		$this->register_ability();

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'author' => array( 'id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ),
				),
				'fields' => array( 'id', 'author' ),
			)
		);

		$this->assertIsArray( $result, 'The stored author ID of a deleted user should be accepted as a filter.' );
		$this->assertSame( array( $orphan_id ), wp_list_pluck( $result['items'], 'id' ), 'The filter should return the posts that store the deleted user\'s ID.' );
		$this->assertSame( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $result['items'][0]['author']['id'], 'The returned author ID should be the one the filter named.' );
	}

	/**
	 * An author slug is looked up as given, like the `slug` filter of the REST API users
	 * endpoint, so with the database's case-insensitive collation a slug in capitals names the
	 * author too.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_filter_matches_a_slug_in_capitals(): void {
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
					'type'   => 'post',
					'author' => array( 'slug' => 'AUTHOR-SLUG' ),
					'ids'    => array( $author_post_id, $other_post_id ),
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
	 * Returns `where.ids` values that hold no valid ID: no item is a whole number of at least
	 * 1 within the integer range.
	 *
	 * @return array<string, array{ids: mixed}> The `where.ids` values.
	 */
	public static function data_ids_with_no_valid_ids(): array {
		return array(
			'zero'                     => array(
				'ids' => array( 0 ),
			),
			'fraction'                 => array(
				'ids' => array( 2.5 ),
			),
			'negative number'          => array(
				'ids' => array( -5 ),
			),
			'non-numeric string'       => array(
				'ids' => 'abc',
			),
			'beyond the integer range' => array(
				'ids' => array( 2 ** 64 + self::ALIASED_POST_ID ),
			),
		);
	}

	/**
	 * An ids filter that parses to no valid IDs is rejected rather than returning an
	 * unrestricted result set.
	 *
	 * WP_Query ignores an empty `post__in`, so an ids list with no valid IDs would otherwise
	 * return every post of the type — the opposite of the caller's intent. The filter must
	 * fail closed instead, and an ID beyond the integer range must not wrap around onto the
	 * published post whose ID it aliases.
	 *
	 * @ticket 66268
	 * @dataProvider data_ids_with_no_valid_ids
	 * @since 7.2.0
	 *
	 * @param mixed $ids The `where.ids` value to send.
	 */
	public function test_execute_callback_rejects_ids_with_no_valid_ids( $ids ): void {
		$this->login_as( 'administrator' );

		self::factory()->post->create(
			array(
				'import_id'   => self::ALIASED_POST_ID,
				'post_status' => 'publish',
			)
		);
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type' => 'post',
					'ids'  => $ids,
				),
			)
		);

		$this->assertWPError( $result, 'An ids filter with no valid IDs must not fall through to an unrestricted query.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'An empty-after-parsing ids filter should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where[ids]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the ids filter to the error message.'
		);
	}

	/**
	 * Called directly, an item of `where.ids` that is not a valid ID is dropped, while the
	 * other IDs still apply.
	 *
	 * The fraction is just above a real post's ID, so an ID truncated onto that post would
	 * return it as well.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_execute_callback_drops_an_invalid_id_next_to_a_valid_one(): void {
		$truncated_id = self::factory()->post->create(
			array(
				'import_id'   => self::IMPORTED_POST_ID,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( self::IMPORTED_POST_ID, $truncated_id, 'Precondition: the post should have the ID the fraction truncates to.' );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where'  => array(
					'type' => 'post',
					'ids'  => array( self::IMPORTED_POST_ID + 0.5, $post_id ),
				),
				'fields' => array( 'id' ),
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
			'Only the post with the valid ID should be returned.'
		);
	}

	/**
	 * Returns `where.slugs` values in which no slug is left after normalization.
	 *
	 * Schema validation rejects the empty ones, so the test calls the execute callback
	 * directly.
	 *
	 * @return array<string, array{slugs: mixed}> The `where.slugs` values.
	 */
	public static function data_slugs_with_no_slug_left(): array {
		return array(
			'empty slug'         => array(
				'slugs' => array( '' ),
			),
			'empty string'       => array(
				'slugs' => '',
			),
			'punctuation string' => array(
				'slugs' => '!!!',
			),
		);
	}

	/**
	 * Called directly, a slugs filter with no slug left after normalization is rejected rather
	 * than widened to every post.
	 *
	 * @ticket 66268
	 * @dataProvider data_slugs_with_no_slug_left
	 * @since 7.2.0
	 *
	 * @param mixed $slugs The `where.slugs` value to send.
	 */
	public function test_execute_callback_rejects_slugs_with_no_slug_left( $slugs ): void {
		$this->login_as( 'administrator' );

		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type'  => 'post',
					'slugs' => $slugs,
				),
			)
		);

		$this->assertWPError( $result, 'A slugs filter with no slug left must not fall through to an unrestricted query.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'A slugs filter with no slug left should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where[slugs]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the slugs filter to the error message.'
		);
	}

	/**
	 * Returns `where` filters with a status that is not registered, or is internal, which reach
	 * the execute callback when it is called directly, or when the input schema lists a status
	 * that is not registered when the query runs.
	 *
	 * @return array<string, array{where: array<string, mixed>}> The `where` filters.
	 */
	public static function data_where_filters_with_statuses_that_cannot_be_queried(): array {
		return array(
			'empty status list'                   => array(
				'where' => array(
					'type'   => 'post',
					'status' => array(),
				),
			),
			'status list without a status name'   => array(
				'where' => array(
					'type'   => 'post',
					'status' => array( 123 ),
				),
			),
			'unregistered status'                 => array(
				'where' => array(
					'type'   => 'post',
					'status' => array( 'unregistered_status' ),
				),
			),
			'unregistered status next to publish' => array(
				'where' => array(
					'type'   => 'post',
					'status' => array( 'publish', 'unregistered_status' ),
				),
			),
			'unregistered status next to ids'     => array(
				'where' => array(
					'type'   => 'post',
					'ids'    => array( self::IMPORTED_POST_ID ),
					'status' => array( 'unregistered_status' ),
				),
			),
			'trash'                               => array(
				'where' => array(
					'type'   => 'post',
					'status' => array( 'trash' ),
				),
			),
			'inherit'                             => array(
				'where' => array(
					'type'   => 'post',
					'status' => array( 'inherit' ),
				),
			),
		);
	}

	/**
	 * Called directly, a status filter with a status that is not registered, or is internal, or
	 * with no status left in it, is rejected rather than queried.
	 *
	 * WP_Query ignores a status that is not registered, and with no status left it covers every
	 * status, including `trash`. A list that is given but empty would fall through to the default
	 * statuses instead, which with `ids` or `slugs` cover every status the user may query.
	 *
	 * @ticket 66268
	 * @dataProvider data_where_filters_with_statuses_that_cannot_be_queried
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $where The `where` filters to send.
	 */
	public function test_execute_callback_rejects_statuses_that_cannot_be_queried( array $where ): void {
		$this->login_as( 'administrator' );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute( array( 'where' => $where ) );

		$this->assertWPError( $result, 'A status filter with a status that cannot be queried must not run the query.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'A status that cannot be queried should fail as an invalid filter.' );
		$this->assertSame(
			array( 'where[status]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the status filter to the error message.'
		);
	}

	/**
	 * A status that the input schema still lists, but that is no longer registered when the
	 * query runs, is rejected rather than queried.
	 *
	 * The input schema lists the statuses registered when the ability is registered, so such a
	 * status passes input validation.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_status_no_longer_registered_is_rejected(): void {
		register_post_status(
			'temporary',
			array(
				'label'  => 'Temporary',
				'public' => true,
			)
		);

		$this->login_as( 'editor' );
		$this->register_ability();

		_unregister_post_status( 'temporary' );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where' => array(
					'type'   => 'post',
					'status' => array( 'temporary' ),
				),
			)
		);

		$this->assertWPError( $result, 'A status that is no longer registered must not run the query.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'A status that is no longer registered should fail as an invalid filter.' );
		$this->assertSame(
			array( 'where[status]' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the status that is no longer registered to the error message.'
		);
	}

	/**
	 * `where.ids` and `where.slugs` also take a comma-separated string, and a single ID or
	 * slug, even a slug sent as a number, as schema validation does.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_ids_and_slugs_accept_comma_separated_strings_and_single_values(): void {
		$older_id = self::factory()->post->create(
			array(
				'post_name'   => 'string-list-older',
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$newer_id = self::factory()->post->create(
			array(
				'post_name'   => 'string-list-newer',
				'post_status' => 'publish',
				'post_date'   => '2026-02-01 10:00:00',
			)
		);
		$year_id  = self::factory()->post->create(
			array(
				'post_name'   => '2024',
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_ability();

		$ability = wp_get_ability( 'core/content-query' );
		$query   = static function ( array $filter ) use ( $ability ) {
			return $ability->execute(
				array(
					'where'  => array( 'type' => 'post' ) + $filter,
					'fields' => array( 'id' ),
				)
			);
		};
		$both    = array(
			'items'      => array(
				array( 'id' => $newer_id ),
				array( 'id' => $older_id ),
			),
			'pagination' => array(
				'total'       => 2,
				'total_pages' => 1,
			),
		);
		$older   = array(
			'items'      => array(
				array( 'id' => $older_id ),
			),
			'pagination' => array(
				'total'       => 1,
				'total_pages' => 1,
			),
		);
		$year    = array(
			'items'      => array(
				array( 'id' => $year_id ),
			),
			'pagination' => array(
				'total'       => 1,
				'total_pages' => 1,
			),
		);

		$this->assertSame( $both, $query( array( 'ids' => $older_id . ',' . $newer_id ) ), 'A comma-separated ids string should return every listed post.' );
		$this->assertSame( $both, $query( array( 'slugs' => 'string-list-older,string-list-newer' ) ), 'A comma-separated slugs string should return every listed post.' );
		$this->assertSame( $older, $query( array( 'ids' => $older_id ) ), 'A single ID should return its post.' );
		$this->assertSame( $older, $query( array( 'slugs' => 'string-list-older' ) ), 'A single slug string should return its post.' );
		$this->assertSame( $year, $query( array( 'slugs' => 2024 ) ), 'A single slug sent as a number should return its post.' );
	}

	/**
	 * Returns values that are not objects, which schema validation rejects for `where` and
	 * `pagination`.
	 *
	 * @return array<string, array{value: mixed}> The values to send.
	 */
	public static function data_values_that_are_not_objects(): array {
		return array(
			'query string' => array(
				'value' => 'id=5&page=2',
			),
			'integer'      => array(
				'value' => 5,
			),
			'boolean'      => array(
				'value' => true,
			),
			'null'         => array(
				'value' => null,
			),
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
	 * @dataProvider data_values_that_are_not_objects
	 * @since 7.2.0
	 *
	 * @param mixed $value The value to send as `where`.
	 */
	public function test_execute_callback_rejects_a_where_that_is_not_an_object( $value ): void {
		$this->login_as( 'administrator' );

		$callbacks = $this->get_ability_callbacks();
		$input     = array( 'where' => $value );

		$this->assertTrue( $callbacks['permission_callback']( $input ), 'The permission callback should let the where through.' );

		$result = $callbacks['execute_callback']( $input );

		$this->assertWPError( $result, 'The where must not fall through to a query without filters.' );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code(), 'The where should fail closed as an invalid filter.' );
		$this->assertSame(
			array( 'where' => $result->get_error_message() ),
			$result->get_error_data()['params'],
			'The error data should map the where to the error message.'
		);
		$this->assertSame( 400, $result->get_error_data()['status'], 'The where should be reported as a bad request.' );
	}

	/**
	 * Called directly, a `where` that is missing, empty, or has no exposed post type reads as
	 * a query without a post type, which both callbacks refuse.
	 *
	 * IDs and slugs do not stand in for the post type.
	 *
	 * @ticket 66268
	 * @dataProvider data_inputs_without_a_post_type
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
	 * @dataProvider data_values_that_are_not_objects
	 * @since 7.2.0
	 *
	 * @param mixed $value The value to send as `pagination`.
	 */
	public function test_execute_callback_defaults_a_pagination_that_is_not_an_object( $value ): void {
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
		$this->assertSame( $expected, $execute( $query + array( 'pagination' => $value ) ), 'The pagination should fall back to the first page of the default page size.' );
	}

	/**
	 * An author filter matches only the posts the current user may read, so a user without
	 * such posts gives the same empty result as an ID or a slug that names no user. A slug
	 * further names only a user the current user may see, as the `author` field reports them,
	 * while an ID matches the author stored on the post.
	 *
	 * @ticket 66268
	 * @since 7.2.0
	 */
	public function test_author_filter_matches_no_post_for_a_user_without_readable_posts(): void {
		$published_id = self::factory()->post->create( array( 'post_author' => self::$user_ids['author'] ) );
		$draft_id     = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['author_secondary'],
				'post_status' => 'draft',
			)
		);

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$query   = static function ( array $statuses, array $author ) use ( $execute ) {
			return $execute(
				array(
					'where'  => array(
						'type'   => 'post',
						'status' => $statuses,
						'author' => $author,
					),
					'fields' => array( 'id' ),
				)
			);
		};

		$author_slug           = array( 'slug' => get_userdata( self::$user_ids['author'] )->user_nicename );
		$author_secondary_id   = array( 'id' => self::$user_ids['author_secondary'] );
		$author_secondary_slug = array( 'slug' => get_userdata( self::$user_ids['author_secondary'] )->user_nicename );
		$subscriber_slug       = array( 'slug' => get_userdata( self::$user_ids['subscriber'] )->user_nicename );
		$missing_id            = array( 'id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$missing_slug          = array( 'slug' => 'no-such-user' );

		$this->login_as( 'subscriber' );
		$public = $query( array( 'publish' ), $author_slug );
		$this->assertIsArray( $public, 'A subscriber should filter by an author with published posts.' );
		$this->assertSame( array( $published_id ), wp_list_pluck( $public['items'], 'id' ), 'The filter should return that author\'s posts.' );
		$this->assertSame( self::EMPTY_RESULT, $query( array( 'publish' ), $author_secondary_slug ), 'A subscriber should get the empty result for the slug of an author without published posts.' );
		$this->assertSame( self::EMPTY_RESULT, $query( array( 'publish' ), $author_secondary_id ), 'A subscriber should get the empty result for the ID of an author without published posts.' );
		$this->assertSame( self::EMPTY_RESULT, $query( array( 'publish' ), $missing_slug ), 'A subscriber should get the empty result for a slug that names no user.' );
		$this->assertSame( self::EMPTY_RESULT, $query( array( 'publish' ), $missing_id ), 'A subscriber should get the empty result for an ID no post stores.' );

		$this->login_as( 'editor' );
		$this->assertSame( self::EMPTY_RESULT, $query( array( 'publish' ), $subscriber_slug ), 'An editor should get the empty result for a user without posts.' );
		$this->assertSame( self::EMPTY_RESULT, $query( array( 'publish' ), $missing_slug ), 'An editor should get the empty result for a slug that names no user.' );
		$this->assertSame( self::EMPTY_RESULT, $query( array( 'publish' ), $missing_id ), 'An editor should get the empty result for an ID no post stores.' );

		$this->assertSame( self::EMPTY_RESULT, $query( array( 'draft' ), $author_secondary_slug ), 'An editor should get the empty result for the slug of an author without published posts, whom they may not see, even for drafts they may read.' );

		$drafts_by_id = $query( array( 'draft' ), $author_secondary_id );
		$this->assertIsArray( $drafts_by_id, 'An editor should filter by the ID of an author without published posts.' );
		$this->assertSame( array( $draft_id ), wp_list_pluck( $drafts_by_id['items'], 'id' ), 'The ID should return that author\'s drafts.' );
	}

	/**
	 * On multisite, an author filter that names a user of another site, by ID or by slug,
	 * matches no post, like an ID or a slug that names no user.
	 *
	 * @ticket 66268
	 * @group ms-required
	 * @since 7.2.0
	 */
	public function test_author_filter_matches_no_post_for_a_user_of_another_site(): void {
		$other_user_id = self::factory()->user->create( array( 'user_nicename' => 'other-site-user' ) );
		$site_id       = self::factory()->blog->create();

		switch_to_blog( $site_id );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$query   = static function ( array $author ) use ( $execute ) {
			return $execute(
				array(
					'where' => array(
						'type'   => 'post',
						'author' => $author,
					),
				)
			);
		};

		$is_member     = is_user_member_of_blog( $other_user_id, $site_id );
		$other_by_slug = $query( array( 'slug' => 'other-site-user' ) );
		$other_by_id   = $query( array( 'id' => $other_user_id ) );
		$missing_slug  = $query( array( 'slug' => 'no-such-user' ) );
		$missing_id    = $query( array( 'id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );

		restore_current_blog();

		$this->assertFalse( $is_member, 'Precondition: the other user should not be a member of the site.' );
		$this->assertSame( self::EMPTY_RESULT, $missing_slug, 'Precondition: a slug that names no user should match no post.' );
		$this->assertSame( self::EMPTY_RESULT, $missing_id, 'Precondition: an ID no post stores should match no post.' );
		$this->assertSame( self::EMPTY_RESULT, $other_by_slug, 'The slug of a user of another site should match no post of the site.' );
		$this->assertSame( self::EMPTY_RESULT, $other_by_id, 'The ID of a user of another site should match no post of the site.' );
	}

	/**
	 * On multisite, an author filter still names an author who is not a member of the site,
	 * such as a super admin, when they have published posts there.
	 *
	 * @ticket 66268
	 * @group ms-required
	 * @since 7.2.0
	 */
	public function test_author_filter_names_authors_who_are_not_site_members(): void {
		$super_admin_id = self::factory()->user->create( array( 'user_nicename' => 'network-author' ) );
		grant_super_admin( $super_admin_id );
		$site_id = self::factory()->blog->create();
		$execute = $this->get_ability_callbacks()['execute_callback'];

		switch_to_blog( $site_id );
		$post_id = self::factory()->post->create( array( 'post_author' => $super_admin_id ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$is_member = is_user_member_of_blog( $super_admin_id, $site_id );
		$by_slug   = $execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'author' => array( 'slug' => 'network-author' ),
				),
				'fields' => array( 'id' ),
			)
		);
		$by_id     = $execute(
			array(
				'where'  => array(
					'type'   => 'post',
					'author' => array( 'id' => $super_admin_id ),
				),
				'fields' => array( 'id' ),
			)
		);

		restore_current_blog();
		revoke_super_admin( $super_admin_id );

		$this->assertFalse( $is_member, 'Precondition: the super admin should not be a member of the site.' );
		$this->assertIsArray( $by_slug, 'An author with published posts on the site should be filterable by slug without being a member.' );
		$this->assertSame( array( $post_id ), wp_list_pluck( $by_slug['items'], 'id' ), 'The slug should return the author\'s posts on the site.' );
		$this->assertIsArray( $by_id, 'An author with published posts on the site should be filterable by ID without being a member.' );
		$this->assertSame( array( $post_id ), wp_list_pluck( $by_id['items'], 'id' ), 'The ID should return the author\'s posts on the site.' );
	}
}
