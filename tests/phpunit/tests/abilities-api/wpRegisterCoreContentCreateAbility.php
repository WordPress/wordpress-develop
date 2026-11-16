<?php

declare( strict_types=1 );

/**
 * Tests for the core/content-create ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Content
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreContentCreateAbility extends WP_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	private const ABILITY = 'core/content-create';

	/**
	 * The content abilities, which every test unregisters before and after it runs.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	private const CONTENT_ABILITIES = array( 'core/content-query', 'core/content-create', 'core/content-update', 'core/content-delete' );

	/**
	 * A user ID that no user has.
	 *
	 * @since 7.3.0
	 *
	 * @var int
	 */
	private const MISSING_ID = 987654321;

	/**
	 * A date that stays in the future.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	private const FUTURE_GMT = '2035-06-01T12:00:00';

	/**
	 * Shared user IDs keyed by role or fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	private static $user_ids = array();

	/**
	 * Shared post IDs keyed by fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	private static $post_ids = array();

	/**
	 * Creates shared users and posts.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::$user_ids = array(
			'administrator' => $factory->user->create( array( 'role' => 'administrator' ) ),
			'editor'        => $factory->user->create( array( 'role' => 'editor' ) ),
			'author'        => $factory->user->create( array( 'role' => 'author' ) ),
			'contributor'   => $factory->user->create( array( 'role' => 'contributor' ) ),
			'subscriber'    => $factory->user->create( array( 'role' => 'subscriber' ) ),
			'page_writer'   => $factory->user->create( array( 'role' => 'subscriber' ) ),
			'publisher'     => $factory->user->create( array( 'role' => 'contributor' ) ),
		);

		// A user who may create pages but not read the drafts of other users.
		get_userdata( self::$user_ids['page_writer'] )->add_cap( 'edit_pages' );

		// A user who may publish posts but not edit published posts.
		get_userdata( self::$user_ids['publisher'] )->add_cap( 'publish_posts' );

		self::$post_ids = array(
			'published_post'    => $factory->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_status' => 'publish',
					'post_name'   => 'shared-slug',
				)
			),
			'published_page'    => $factory->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => 'page',
					'post_status' => 'publish',
				)
			),
			'other_parent_page' => $factory->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => 'page',
					'post_status' => 'publish',
				)
			),
			'editor_draft_page' => $factory->post->create(
				array(
					'post_author' => self::$user_ids['editor'],
					'post_type'   => 'page',
					'post_status' => 'draft',
				)
			),
		);

		self::$post_ids['child_page'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => self::$post_ids['published_page'],
				'post_name'   => 'child',
			)
		);

		// A page of the administrator in the Trash.
		self::$post_ids['trashed_page'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( self::$post_ids['trashed_page'] );

		$revisioned_id = $factory->post->create(
			array(
				'post_author'  => self::$user_ids['administrator'],
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'First version.',
			)
		);
		wp_update_post(
			array(
				'ID'           => $revisioned_id,
				'post_content' => 'Second version.',
			)
		);
		self::$post_ids['page_revision'] = (int) array_key_first( wp_get_post_revisions( $revisioned_id ) );
	}

	/**
	 * Unregisters the content abilities and registers the content category.
	 *
	 * @since 7.3.0
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( self::CONTENT_ABILITIES as $ability_name ) {
			if ( wp_has_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
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
	 * Unregisters the content abilities and the category, and resets the post types.
	 *
	 * @since 7.3.0
	 */
	public function tear_down(): void {
		foreach ( self::CONTENT_ABILITIES as $ability_name ) {
			if ( wp_has_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
		}

		if ( wp_has_ability_category( 'content' ) ) {
			wp_unregister_ability_category( 'content' );
		}

		/*
		 * A test may turn off `show_in_abilities` on `post` and `page`, and the next class's
		 * wpSetUpBeforeClass() runs before the next set_up() resets the post types.
		 */
		$this->reset_post_types();

		parent::tear_down();
	}

	/**
	 * Registers the content abilities inside a faked init action.
	 *
	 * @since 7.3.0
	 */
	private function register_abilities(): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		( new WP_Abilities_Content() )->register();
	}

	/**
	 * Registers the content abilities and returns the callbacks of the create ability.
	 *
	 * The callbacks call private methods, so tests that skip input validation or the
	 * permission check capture them from the registration arguments.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, callable> The permission and execute callbacks, keyed by argument name.
	 */
	private function get_ability_callbacks(): array {
		$callbacks = array();

		add_filter(
			'wp_register_ability_args',
			static function ( array $args, string $name ) use ( &$callbacks ): array {
				if ( self::ABILITY === $name ) {
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

		$this->register_abilities();

		$this->assertArrayHasKey( 'execute_callback', $callbacks, 'The core/content-create ability should be registered with callbacks.' );

		return $callbacks;
	}

	/**
	 * Runs the registered create ability with the given input.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input The ability input.
	 * @return mixed The ability result.
	 */
	private function run_ability( $input ) {
		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The core/content-create ability should be registered.' );

		return wp_get_ability( self::ABILITY )->execute( $input );
	}

	/**
	 * Logs in as the shared user with the given role or fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @param string $role The role or fixture name.
	 */
	private function login_as( string $role ): void {
		wp_set_current_user( self::$user_ids[ $role ] );
	}

	/**
	 * Takes a snapshot of the stored content: the number of posts per type and status,
	 * revisions included, and the number of meta rows and term relationships.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The snapshot.
	 */
	private function snapshot(): array {
		global $wpdb;

		return array(
			'posts' => $wpdb->get_results( "SELECT post_type, post_status, COUNT(*) AS total FROM {$wpdb->posts} GROUP BY post_type, post_status ORDER BY post_type, post_status", ARRAY_A ),
			'meta'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ),
			'terms' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships}" ),
		);
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
	private function assert_error( $result, string $code, int $status, string $context ): void {
		$this->assertWPError( $result, "{$context}: the call should fail." );
		$this->assertSame( $code, $result->get_error_code(), "{$context}: the error code should be {$code}." );

		$data = $result->get_error_data();
		$this->assertIsArray( $data, "{$context}: the error should carry data." );
		$this->assertSame( $status, $data['status'] ?? null, "{$context}: the error status should be {$status}." );
	}

	/**
	 * Asserts that a result is the invalid attribute error for the given attribute.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result    The ability result.
	 * @param string $attribute The attribute name.
	 * @param string $context   What the call was, for the assertion messages.
	 */
	private function assert_invalid_attribute( $result, string $attribute, string $context ): void {
		$this->assert_error( $result, 'content_invalid_attribute', 400, $context );
		$this->assertSame( "Invalid attribute: {$attribute}.", $result->get_error_message(), "{$context}: the message should name the attribute." );
		$this->assertSame( array( "data[{$attribute}]" ), array_keys( $result->get_error_data()['params'] ?? array() ), "{$context}: the params should hold only the input path of the attribute." );
	}

	/**
	 * Returns the newest post created by a call, or null when the call created none.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $result The ability result.
	 * @return WP_Post|null The created post.
	 */
	private function get_created_post( $result ): ?WP_Post {
		$this->assertIsArray( $result, 'The create call should succeed.' );
		$this->assertArrayHasKey( 'id', $result, 'The created post should be returned with its ID.' );

		return get_post( $result['id'] );
	}

	/**
	 * Removes the `description` keywords of a schema at every depth.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $schema The schema.
	 * @return array<string, mixed> The schema without descriptions.
	 */
	private function strip_descriptions( array $schema ): array {
		unset( $schema['description'] );
		foreach ( $schema as $key => $value ) {
			if ( is_array( $value ) ) {
				$schema[ $key ] = $this->strip_descriptions( $value );
			}
		}

		return $schema;
	}

	/**
	 * Registers a post type exposed to abilities with the given supported features.
	 *
	 * @since 7.3.0
	 *
	 * @param string       $name     The post type name.
	 * @param list<string> $supports The supported features.
	 */
	private function register_exposed_post_type( string $name, array $supports ): void {
		register_post_type(
			$name,
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => $supports,
			)
		);
	}

	/**
	 * The ability is registered in the content category with its label and annotations.
	 *
	 * @since 7.3.0
	 */
	public function test_registers_the_ability(): void {
		$this->register_abilities();

		$ability = wp_get_ability( self::ABILITY );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The core/content-create ability should be registered.' );
		$this->assertSame( 'Create Content', $ability->get_label(), 'The ability should have its label.' );
		$this->assertSame( 'content', $ability->get_category(), 'The ability should use the content category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The ability should be marked public.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertFalse( $annotations['readonly'], 'The ability should not be marked read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The ability should not be marked destructive.' );
		$this->assertFalse( $annotations['idempotent'], 'The ability should not be marked idempotent.' );
	}

	/**
	 * The ability is not registered when no post type is exposed to abilities.
	 *
	 * @since 7.3.0
	 */
	public function test_is_not_registered_without_exposed_post_types(): void {
		foreach ( array( 'post', 'page' ) as $post_type ) {
			get_post_type_object( $post_type )->show_in_abilities = false;
		}

		$this->register_abilities();

		$this->assertFalse( wp_has_ability( self::ABILITY ), 'The create ability should not be registered without an exposed post type.' );
	}

	/**
	 * The input schema takes `data`, with the post type and the allowed attributes, and `fields`.
	 *
	 * @since 7.3.0
	 */
	public function test_input_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The create ability should be registered.' );

		$schema = wp_get_ability( self::ABILITY )->get_input_schema();

		$this->assertSame( 'object', $schema['type'], 'The input should be an object.' );
		$this->assertFalse( $schema['additionalProperties'], 'The input should reject unknown keys.' );
		$this->assertSame( array( 'data' ), $schema['required'], 'The input should require data.' );
		$this->assertSame( array( 'data', 'fields' ), array_keys( $schema['properties'] ), 'The input should take data and fields.' );

		$data = $schema['properties']['data'];

		$this->assertSame( 'object', $data['type'], 'The data should be an object.' );
		$this->assertFalse( $data['additionalProperties'], 'The data should reject unknown keys.' );
		$this->assertSame( array( 'type' ), $data['required'], 'The data should require the post type.' );
		$this->assertSame( array( 'type', 'title', 'content', 'excerpt', 'status', 'slug', 'date', 'author', 'parent' ), array_keys( $data['properties'] ), 'The data should take the post type and the allowed attributes.' );

		$properties = $data['properties'];

		$this->assertSame( 'string', $properties['type']['type'], 'The post type should be a string.' );
		$this->assertSameSets( array( 'post', 'page' ), $properties['type']['enum'], 'The post type should list the exposed post types.' );
		$this->assertSame( 'string', $properties['title']['type'], 'The title should be a string.' );
		$this->assertSame( 'string', $properties['content']['type'], 'The content should be a string.' );
		$this->assertSame( 'string', $properties['excerpt']['type'], 'The excerpt should be a string.' );
		$this->assertSame( 'string', $properties['status']['type'], 'The status should be a string.' );
		$this->assertSame( array( 'publish', 'future', 'draft', 'pending', 'private' ), $properties['status']['enum'], 'The status should list the statuses that are not internal.' );
		$this->assertSame( 'string', $properties['slug']['type'], 'The slug should be a string.' );
		$this->assertSame( 1, $properties['slug']['minLength'], 'The slug should not be empty.' );
		$this->assertSame( 'integer', $properties['author']['type'], 'The author should be an integer.' );
		$this->assertSame( 1, $properties['author']['minimum'], 'The author should be at least 1.' );
		$this->assertSame( 'integer', $properties['parent']['type'], 'The parent should be an integer.' );
		$this->assertSame( 0, $properties['parent']['minimum'], 'The parent should be at least 0.' );

		$date = $properties['date'];

		$this->assertSame( 'object', $date['type'], 'The date should be an object.' );
		$this->assertFalse( $date['additionalProperties'], 'The date should reject unknown keys.' );
		$this->assertSame( 1, $date['minProperties'], 'The date should hold at least one value.' );
		$this->assertSame( array( 'site', 'gmt' ), array_keys( $date['properties'] ), 'The date should take a site and a GMT value.' );
		$this->assertSame( 'string', $date['properties']['site']['type'], 'The site date should be a string.' );
		$this->assertSame( 'date-time', $date['properties']['site']['format'], 'The site date should be a date-time.' );
		$this->assertSame( 'string', $date['properties']['gmt']['type'], 'The GMT date should be a string.' );
		$this->assertSame( 'date-time', $date['properties']['gmt']['format'], 'The GMT date should be a date-time.' );

		$this->assertTrue( wp_has_ability( 'core/content-query' ), 'The query ability should be registered next to the create ability.' );

		$query_schema = wp_get_ability( 'core/content-query' )->get_input_schema();

		$this->assertSame(
			$this->strip_descriptions( $query_schema['properties']['fields'] ),
			$this->strip_descriptions( $schema['properties']['fields'] ),
			'The fields should be the same list as the fields of the query.'
		);
	}

	/**
	 * The output is one post item, the same object as an item of the query.
	 *
	 * Covers S11.
	 *
	 * @since 7.3.0
	 */
	public function test_output_schema_is_a_query_item(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The create ability should be registered.' );

		$schema = wp_get_ability( self::ABILITY )->get_output_schema();
		$item   = wp_get_ability( 'core/content-query' )->get_output_schema()['properties']['items']['items'];

		$this->assertSame( 'object', $schema['type'], 'The output should be an object.' );
		$this->assertFalse( $schema['additionalProperties'], 'The output should not take unknown keys.' );
		$this->assertSame( array( 'id' ), $schema['required'], 'The output should always hold the ID.' );
		$this->assertSame( $this->strip_descriptions( $item ), $this->strip_descriptions( $schema ), 'The output should be the same object as a query item.' );
	}

	/**
	 * The schema enums are built from the site when the ability is registered.
	 *
	 * @since 7.3.0
	 */
	public function test_schema_enums_list_the_exposed_post_types_and_registered_statuses(): void {
		$this->register_exposed_post_type( 'cw_book', array( 'title' ) );
		register_post_type( 'cw_hidden', array( 'public' => true ) );
		register_post_status(
			'cw_archived',
			array(
				'label'  => 'Archived',
				'public' => true,
			)
		);

		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The create ability should be registered.' );

		$properties = wp_get_ability( self::ABILITY )->get_input_schema()['properties']['data']['properties'];

		$this->assertSameSets( array( 'post', 'page', 'cw_book' ), $properties['type']['enum'], 'The post type should list every exposed post type and no other.' );
		$this->assertSameSets( array_values( get_post_stati( array( 'internal' => false ) ) ), $properties['status']['enum'], 'The status should list every registered status that is not internal.' );
		$this->assertContains( 'cw_archived', $properties['status']['enum'], 'The status should list a custom status.' );
	}

	/**
	 * Example 1: a draft with the default fields.
	 *
	 * @since 7.3.0
	 */
	public function test_example_creates_a_draft_with_the_default_fields(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'post',
					'title' => 'Launch notes',
				),
			)
		);

		$post = $this->get_created_post( $result );

		$this->assertSame( array( 'id', 'type', 'status', 'date', 'slug', 'title' ), array_keys( $result ), 'The default fields should be returned.' );
		$this->assertSame( $post->ID, $result['id'], 'The ID should be the ID of the new post.' );
		$this->assertSame( 'post', $result['type'], 'The type should be post.' );
		$this->assertSame( 'draft', $result['status'], 'The post should be a draft.' );
		$this->assertSame( array( 'site', 'gmt' ), array_keys( $result['date'] ), 'The date should be given in site time and GMT.' );
		$this->assertNotFalse( rest_parse_date( $result['date']['site'] ), 'The site date should be a date-time.' );
		$this->assertNotFalse( rest_parse_date( $result['date']['gmt'] ), 'The GMT date should be a date-time.' );
		$this->assertSame( '', $result['slug'], 'A draft without a slug should have an empty slug.' );
		$this->assertSame( array( 'rendered' => 'Launch notes' ), $result['title'], 'The title should be returned rendered.' );
		$this->assertSame( 'Launch notes', $post->post_title, 'The title should be stored.' );
	}

	/**
	 * Example 2: a published child page with chosen fields.
	 *
	 * @since 7.3.0
	 */
	public function test_example_creates_a_published_child_page_with_chosen_fields(): void {
		$this->set_permalink_structure( '/%postname%/' );

		$parent_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'products',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'type'    => 'page',
					'title'   => 'Pricing',
					'content' => "<!-- wp:paragraph -->\n<p>Plans start at 9 euro per month.</p>\n<!-- /wp:paragraph -->",
					'status'  => 'publish',
					'parent'  => $parent_id,
					'slug'    => 'pricing',
				),
				'fields' => array( 'status', 'link', 'title.rendered', 'content.rendered', 'parent' ),
			)
		);

		$post = $this->get_created_post( $result );

		$this->assertSame( array( 'id', 'status', 'link', 'title', 'content', 'parent' ), array_keys( $result ), 'The chosen fields should be returned.' );
		$this->assertSame( $post->ID, $result['id'], 'The ID should be the ID of the new page.' );
		$this->assertSame( 'publish', $result['status'], 'The page should be published.' );
		$this->assertSame( home_url( '/products/pricing/' ), $result['link'], 'The link should include the parent.' );
		$this->assertSame( array( 'rendered' => 'Pricing' ), $result['title'], 'The title should be returned rendered.' );
		$this->assertSame( $parent_id, $result['parent'], 'The parent should be returned.' );

		// The rendered markup comes from the regular content filters, as for any query item.
		$this->assertSame( $this->query_item( 'page', $post->ID, array( 'content.rendered' ) )['content'], $result['content'], 'The content should be rendered as the query renders it.' );
		$this->assertStringContainsString( 'Plans start at 9 euro per month.</p>', $result['content']['rendered'], 'The rendered content should hold the paragraph.' );
	}

	/**
	 * Returns the item that core/content-query gives for one post and the given fields.
	 *
	 * @since 7.3.0
	 *
	 * @param string       $type    The post type.
	 * @param int          $post_id The post ID.
	 * @param list<string> $fields  The fields.
	 * @return array<string, mixed> The query item.
	 */
	private function query_item( string $type, int $post_id, array $fields ): array {
		$this->assertTrue( wp_has_ability( 'core/content-query' ), 'The query ability should be registered.' );

		$result = wp_get_ability( 'core/content-query' )->execute(
			array(
				'where'  => array(
					'type' => $type,
					'ids'  => array( $post_id ),
				),
				'fields' => $fields,
			)
		);

		$this->assertIsArray( $result, 'The query for the post should succeed.' );
		$this->assertCount( 1, $result['items'], 'The query should return the post.' );

		return $result['items'][0];
	}

	/**
	 * Example 3: an attribute the post type does not support.
	 *
	 * Covers S1 and S5.
	 *
	 * @since 7.3.0
	 */
	public function test_example_rejects_a_parent_for_a_post_type_that_is_not_hierarchical(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Launch notes',
					'parent' => self::$post_ids['published_post'],
				),
			)
		);

		$this->assertWPError( $result, 'A parent for a post should be rejected.' );
		$this->assertSame( 'content_invalid_attribute', $result->get_error_code(), 'The error should be the invalid attribute error.' );
		$this->assertSame( 'Invalid attribute: parent.', $result->get_error_message(), 'The message should name the parent.' );
		$this->assertSame( 400, $result->get_error_data()['status'], 'The status should be 400.' );
		$this->assertSame( array( 'data[parent]' => 'The post type is not hierarchical.' ), $result->get_error_data()['params'], 'The params should give the fixed reason for the parent.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns users that the permission callback refuses.
	 *
	 * @return array<string, array{role: string|null}> The users.
	 */
	public static function data_users_refused_by_the_permission_callback(): array {
		return array(
			'logged out' => array(
				'role' => null,
			),
			'subscriber' => array(
				'role' => 'subscriber',
			),
		);
	}

	/**
	 * A logged-out user and a user without `create_posts` are refused before the ability runs.
	 *
	 * Covers S4 and S5.
	 *
	 * @dataProvider data_users_refused_by_the_permission_callback
	 *
	 * @since 7.3.0
	 *
	 * @param string|null $role The role to log in as, or null to stay logged out.
	 */
	public function test_permission_callback_refuses_users_who_cannot_create_posts( ?string $role ): void {
		if ( null !== $role ) {
			$this->login_as( $role );
		}
		$this->register_abilities();

		$before = $this->snapshot();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'post',
					'title' => 'Refused',
				),
			)
		);

		$this->assertWPError( $result, 'The call should be refused.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'The permission callback should refuse the call.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns inputs for which the permission callback returns false, even for an administrator.
	 *
	 * @return array<string, array{input: mixed}> The inputs.
	 */
	public static function data_inputs_refused_by_the_permission_callback(): array {
		return array(
			'no input'              => array(
				'input' => null,
			),
			'no data'               => array(
				'input' => array(),
			),
			'data that is a string' => array(
				'input' => array( 'data' => 'post' ),
			),
			'no type'               => array(
				'input' => array( 'data' => array( 'title' => 'x' ) ),
			),
			'type that is a list'   => array(
				'input' => array( 'data' => array( 'type' => array( 'post' ) ) ),
			),
			'type that is a number' => array(
				'input' => array( 'data' => array( 'type' => 1 ) ),
			),
			'unexposed type'        => array(
				'input' => array( 'data' => array( 'type' => 'attachment' ) ),
			),
			'unregistered type'     => array(
				'input' => array( 'data' => array( 'type' => 'cw_missing' ) ),
			),
		);
	}

	/**
	 * The permission callback returns false for missing or malformed input.
	 *
	 * Covers S1 and S3.
	 *
	 * @dataProvider data_inputs_refused_by_the_permission_callback
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input The input.
	 */
	public function test_permission_callback_returns_false_for_malformed_input( $input ): void {
		$this->login_as( 'administrator' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertFalse( $permission( $input ), 'The permission callback should return false.' );
	}

	/**
	 * The permission callback returns true for a user who may create posts of an exposed type.
	 *
	 * @since 7.3.0
	 */
	public function test_permission_callback_returns_true_for_a_user_who_may_create_posts(): void {
		$this->login_as( 'contributor' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertTrue( $permission( array( 'data' => array( 'type' => 'post' ) ) ), 'The permission callback should return true.' );
	}

	/**
	 * Returns the outcome of creating a post per role and requested status.
	 *
	 * @return array<string, array{role: string, status: string|null, expected: string, message: string|null}> The cases.
	 */
	public static function data_create_by_role_and_status(): array {
		$publish_message = 'Sorry, you are not allowed to publish posts in this post type.';
		$private_message = 'Sorry, you are not allowed to create private posts in this post type.';

		return array(
			'administrator, no status' => array( 'administrator', null, 'draft', null ),
			'administrator, draft'     => array( 'administrator', 'draft', 'draft', null ),
			'administrator, pending'   => array( 'administrator', 'pending', 'pending', null ),
			'administrator, publish'   => array( 'administrator', 'publish', 'publish', null ),
			'administrator, future'    => array( 'administrator', 'future', 'future', null ),
			'administrator, private'   => array( 'administrator', 'private', 'private', null ),
			'editor, no status'        => array( 'editor', null, 'draft', null ),
			'editor, draft'            => array( 'editor', 'draft', 'draft', null ),
			'editor, pending'          => array( 'editor', 'pending', 'pending', null ),
			'editor, publish'          => array( 'editor', 'publish', 'publish', null ),
			'editor, future'           => array( 'editor', 'future', 'future', null ),
			'editor, private'          => array( 'editor', 'private', 'private', null ),
			'author, no status'        => array( 'author', null, 'draft', null ),
			'author, draft'            => array( 'author', 'draft', 'draft', null ),
			'author, pending'          => array( 'author', 'pending', 'pending', null ),
			'author, publish'          => array( 'author', 'publish', 'publish', null ),
			'author, future'           => array( 'author', 'future', 'future', null ),
			'author, private'          => array( 'author', 'private', 'private', null ),
			'contributor, no status'   => array( 'contributor', null, 'draft', null ),
			'contributor, draft'       => array( 'contributor', 'draft', 'draft', null ),
			'contributor, pending'     => array( 'contributor', 'pending', 'pending', null ),
			'contributor, publish'     => array( 'contributor', 'publish', 'content_cannot_publish', $publish_message ),
			'contributor, future'      => array( 'contributor', 'future', 'content_cannot_publish', $publish_message ),
			'contributor, private'     => array( 'contributor', 'private', 'content_cannot_publish', $private_message ),
		);
	}

	/**
	 * Each role may create posts in the statuses it may set, as their own author.
	 *
	 * `future`, `publish` and `private` need `publish_posts`. A scheduled post is sent with a
	 * date in the future.
	 *
	 * Covers S4, S5 and S7.
	 *
	 * @dataProvider data_create_by_role_and_status
	 *
	 * @since 7.3.0
	 *
	 * @param string      $role     The role to create as.
	 * @param string|null $status   The requested status, or null to send none.
	 * @param string      $expected The stored status, or the error code.
	 * @param string|null $message  The error message, or null on success.
	 */
	public function test_create_by_role_and_status( string $role, ?string $status, string $expected, ?string $message ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$data = array(
			'type'  => 'post',
			'title' => 'Matrix post',
		);
		if ( null !== $status ) {
			$data['status'] = $status;
		}
		if ( 'future' === $status ) {
			$data['date'] = array( 'gmt' => self::FUTURE_GMT );
		}

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data'   => $data,
				'fields' => array( 'status' ),
			)
		);

		if ( null !== $message ) {
			$this->assert_error( $result, $expected, 403, 'Creating a post in a status the role may not set' );
			$this->assertSame( $message, $result->get_error_message(), 'The message should name what the role may not do.' );
			$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
			return;
		}

		$post = $this->get_created_post( $result );

		$this->assertSame( $expected, $result['status'], 'The returned status should be the stored status.' );
		$this->assertSame( $expected, $post->post_status, 'The post should be stored in the expected status.' );
		$this->assertSame( self::$user_ids[ $role ], (int) $post->post_author, 'The current user should be the author.' );
		$this->assertSame( 'post', $post->post_type, 'The post should have the requested type.' );
	}

	/**
	 * A type whose `show_in_abilities` is turned off after registration cannot be created.
	 *
	 * Covers S3 and S5.
	 *
	 * @since 7.3.0
	 */
	public function test_type_no_longer_exposed_cannot_be_created(): void {
		$this->login_as( 'administrator' );
		$callbacks = $this->get_ability_callbacks();

		get_post_type_object( 'post' )->show_in_abilities = false;

		$before = $this->snapshot();
		$input  = array(
			'data' => array(
				'type'  => 'post',
				'title' => 'Hidden',
			),
		);

		$result = $this->run_ability( $input );

		$this->assertWPError( $result, 'The regular call should fail.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'The permission callback should refuse a type that is no longer exposed.' );

		$direct = $callbacks['execute_callback']( $input );

		$this->assert_error( $direct, 'content_not_found', 404, 'A direct call for a type that is no longer exposed' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns direct-call inputs without an exposed post type.
	 *
	 * @return array<string, array{input: mixed}> The inputs.
	 */
	public static function data_direct_inputs_without_an_exposed_type(): array {
		return array(
			'no input'              => array(
				'input' => null,
			),
			'no data'               => array(
				'input' => array(),
			),
			'data that is a string' => array(
				'input' => array( 'data' => 'post' ),
			),
			'no type'               => array(
				'input' => array( 'data' => array( 'title' => 'x' ) ),
			),
			'type that is a list'   => array(
				'input' => array(
					'data' => array(
						'type'  => array( 'post' ),
						'title' => 'x',
					),
				),
			),
			'attachment'            => array(
				'input' => array(
					'data' => array(
						'type'  => 'attachment',
						'title' => 'x',
					),
				),
			),
			'revision'              => array(
				'input' => array(
					'data' => array(
						'type'  => 'revision',
						'title' => 'x',
					),
				),
			),
			'unexposed type'        => array(
				'input' => array(
					'data' => array(
						'type'  => 'cw_hidden',
						'title' => 'x',
					),
				),
			),
		);
	}

	/**
	 * Called directly, input without an exposed post type is the not-found error.
	 *
	 * Covers S1, S3, S5 and S13.
	 *
	 * @dataProvider data_direct_inputs_without_an_exposed_type
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input The input.
	 */
	public function test_direct_call_without_an_exposed_type_is_not_found( $input ): void {
		register_post_type( 'cw_hidden', array( 'public' => true ) );

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot();
		$result = $execute( $input );

		$this->assert_error( $result, 'content_not_found', 404, 'A direct call without an exposed type' );
		$this->assertSame( 'The requested content was not found.', $result->get_error_message(), 'The message should be the not-found message.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns `data` values that are invalid in some way.
	 *
	 * @return array<string, array{data: array<string, mixed>}> The data.
	 */
	public static function data_invalid_data(): array {
		return array(
			'parent on a post'     => array(
				'data' => array(
					'type'   => 'post',
					'parent' => 1,
				),
			),
			'unregistered status'  => array(
				'data' => array(
					'type'   => 'post',
					'status' => 'cw_unregistered',
				),
			),
			'publish'              => array(
				'data' => array(
					'type'   => 'post',
					'status' => 'publish',
				),
			),
			'empty slug'           => array(
				'data' => array(
					'type'  => 'post',
					'title' => 'x',
					'slug'  => '!!!',
				),
			),
			'missing author'       => array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'x',
					'author' => self::MISSING_ID,
				),
			),
			'title that is a list' => array(
				'data' => array(
					'type'  => 'post',
					'title' => array( 'x' ),
				),
			),
			'invalid date'         => array(
				'data' => array(
					'type'  => 'post',
					'title' => 'x',
					'date'  => array( 'site' => 'not a date' ),
				),
			),
		);
	}

	/**
	 * Called directly by a user without `create_posts`, the call fails on the capability before
	 * anything about the attributes is checked.
	 *
	 * Covers S4, S5 and O2.
	 *
	 * @dataProvider data_invalid_data
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $data The data.
	 */
	public function test_direct_call_without_create_posts_fails_before_the_attributes( array $data ): void {
		$this->login_as( 'subscriber' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot();
		$result = $execute( array( 'data' => $data ) );

		$this->assert_error( $result, 'content_cannot_create', 403, 'A direct call without create_posts' );
		$this->assertSame( 'Sorry, you are not allowed to create posts as this user.', $result->get_error_message(), 'The message should be the create message.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns keys that are not part of the input.
	 *
	 * @return array<string, array{key: string, value: mixed}> The keys and values.
	 */
	public static function data_keys_outside_the_allow_list(): array {
		return array(
			'ID'                => array( 'ID', 1 ),
			'post_type'         => array( 'post_type', 'page' ),
			'post_password'     => array( 'post_password', 'secret' ),
			'meta_input'        => array( 'meta_input', array( 'cw_key' => 'value' ) ),
			'tax_input'         => array( 'tax_input', array( 'category' => array( 1 ) ) ),
			'comment_status'    => array( 'comment_status', 'closed' ),
			'post_author'       => array( 'post_author', 1 ),
			'import_id'         => array( 'import_id', 7654321 ),
			'guid'              => array( 'guid', 'https://example.org/forged' ),
			'page_template'     => array( 'page_template', 'cw-template.php' ),
			'modified'          => array( 'modified', '2001-01-01T00:00:00' ),
			'post_modified'     => array( 'post_modified', '2001-01-01 00:00:00' ),
			'post_modified_gmt' => array( 'post_modified_gmt', '2001-01-01 00:00:00' ),
			'sticky'            => array( 'sticky', true ),
			'tags_input'        => array( 'tags_input', array( 'cw-tag' ) ),
			'post_category'     => array( 'post_category', array( 2 ) ),
			'menu_order'        => array( 'menu_order', 5 ),
			'post_mime_type'    => array( 'post_mime_type', 'image/jpeg' ),
		);
	}

	/**
	 * A key outside the allow-list fails input validation.
	 *
	 * Covers S1, S5 and S6.
	 *
	 * @dataProvider data_keys_outside_the_allow_list
	 *
	 * @since 7.3.0
	 *
	 * @param string $key   The key.
	 * @param mixed  $value The value.
	 */
	public function test_key_outside_the_allow_list_fails_input_validation( string $key, $value ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'post',
					'title' => 'Extra key',
					$key    => $value,
				),
			)
		);

		$this->assertWPError( $result, 'The call should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the key.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Called directly, keys outside the allow-list are ignored and never written.
	 *
	 * Covers S6.
	 *
	 * @since 7.3.0
	 */
	public function test_direct_call_ignores_keys_outside_the_allow_list(): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$existing_before = get_post( self::$post_ids['published_post'] )->to_array();
		$category_id     = self::factory()->category->create();

		$result = $execute(
			array(
				'data'    => array(
					'type'              => 'post',
					'title'             => 'Allow-list',
					'ID'                => self::$post_ids['published_post'],
					'post_type'         => 'page',
					'post_password'     => 'secret',
					'meta_input'        => array( 'cw_key' => 'value' ),
					'tax_input'         => array( 'category' => array( 1 ) ),
					'comment_status'    => 'closed',
					'post_author'       => self::$user_ids['editor'],
					'import_id'         => 7654321,
					'guid'              => 'https://example.org/forged',
					'page_template'     => 'cw-template.php',
					'modified'          => '2001-01-01T00:00:00',
					'post_modified'     => '2001-01-01 00:00:00',
					'post_modified_gmt' => '2001-01-01 00:00:00',
					'sticky'            => true,
					'tags_input'        => array( 'cw-tag' ),
					'post_category'     => array( $category_id ),
					'menu_order'        => 5,
					'post_mime_type'    => 'image/jpeg',
				),
				'unknown' => 'ignored',
			)
		);

		$post = $this->get_created_post( $result );

		$this->assertNotSame( self::$post_ids['published_post'], $post->ID, 'A new post should be created.' );
		$this->assertNotSame( 7654321, $post->ID, 'The import ID should not be used.' );
		$this->assertSame( 'post', $post->post_type, 'The post type should come from data.type.' );
		$this->assertSame( 'Allow-list', $post->post_title, 'The title should be stored.' );
		$this->assertSame( '', $post->post_password, 'No password should be stored.' );
		$this->assertSame( array(), get_post_meta( $post->ID, 'cw_key' ), 'No meta should be stored.' );
		$this->assertSame( 'open', $post->comment_status, 'The comment status should be the default.' );
		$this->assertSame( self::$user_ids['administrator'], (int) $post->post_author, 'The author should be the current user.' );
		$this->assertStringNotContainsString( 'forged', $post->guid, 'The GUID should not be the sent one.' );
		$this->assertSame( '', get_post_meta( $post->ID, '_wp_page_template', true ), 'No page template should be stored.' );
		// A new draft without a date floats, so only the site-time modified date holds the save time.
		$this->assertNotSame( '2001-01-01 00:00:00', $post->post_modified, 'The sent site-time modified date should not be stored.' );
		$this->assertNotSame( '2001-01-01 00:00:00', $post->post_modified_gmt, 'The sent GMT modified date should not be stored.' );
		$this->assertEqualsWithDelta( time(), strtotime( get_gmt_from_date( $post->post_modified ) . ' UTC' ), 60, 'The stored site-time modified date should be the time of the save.' );
		$this->assertFalse( is_sticky( $post->ID ), 'The post should not be sticky.' );
		$this->assertSame( array(), wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) ), 'No tag should be stored.' );
		$this->assertSame( array( (int) get_option( 'default_category' ) ), wp_get_post_categories( $post->ID ), 'Only the default category should be stored.' );
		$this->assertSame( 0, $post->menu_order, 'The menu order should be 0.' );
		$this->assertSame( '', $post->post_mime_type, 'No MIME type should be stored.' );
		$this->assertSame( $existing_before, get_post( self::$post_ids['published_post'] )->to_array(), 'The post named by ID should not change.' );
	}

	/**
	 * Returns statuses that can never be set.
	 *
	 * @return array<string, array{status: string}> The statuses.
	 */
	public static function data_statuses_that_can_never_be_set(): array {
		return array(
			'trash'             => array( 'trash' ),
			'auto-draft'        => array( 'auto-draft' ),
			'inherit'           => array( 'inherit' ),
			'unregistered'      => array( 'cw_unregistered' ),
			'request-pending'   => array( 'request-pending' ),
			'request-confirmed' => array( 'request-confirmed' ),
			'upper case'        => array( 'PUBLISH' ),
			'trailing space'    => array( 'publish ' ),
			'empty string'      => array( '' ),
			'comma-joined list' => array( 'draft,publish' ),
		);
	}

	/**
	 * An internal or unregistered status fails input validation.
	 *
	 * Covers S5 and S7.
	 *
	 * @dataProvider data_statuses_that_can_never_be_set
	 *
	 * @since 7.3.0
	 *
	 * @param string $status The status.
	 */
	public function test_status_that_can_never_be_set_fails_input_validation( string $status ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Status',
					'status' => $status,
				),
			)
		);

		$this->assertWPError( $result, 'The call should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the status.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Called directly, an internal or unregistered status is an invalid status.
	 *
	 * Covers S1, S5 and S7.
	 *
	 * @dataProvider data_statuses_that_can_never_be_set
	 *
	 * @since 7.3.0
	 *
	 * @param string $status The status.
	 */
	public function test_direct_call_rejects_a_status_that_can_never_be_set( string $status ): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot();
		$result = $execute(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Status',
					'status' => $status,
				),
			)
		);

		$this->assert_invalid_attribute( $result, 'status', 'A direct call with a status that can never be set' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A status that is no longer registered when the call runs is an invalid status.
	 *
	 * Covers S1, S5 and S7.
	 *
	 * @since 7.3.0
	 */
	public function test_status_no_longer_registered_is_rejected(): void {
		register_post_status(
			'cw_temporary',
			array(
				'label'  => 'Temporary',
				'public' => true,
			)
		);

		$this->login_as( 'administrator' );
		$this->register_abilities();

		_unregister_post_status( 'cw_temporary' );

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Temporary',
					'status' => 'cw_temporary',
				),
			)
		);

		$this->assert_invalid_attribute( $result, 'status', 'A status that is no longer registered' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Registers custom statuses of every visibility, before the abilities are registered.
	 *
	 * @since 7.3.0
	 */
	private function register_custom_statuses(): void {
		register_post_status(
			'cw_public',
			array(
				'label'  => 'Public',
				'public' => true,
			)
		);
		register_post_status(
			'cw_private',
			array(
				'label'   => 'Private',
				'private' => true,
			)
		);
		register_post_status(
			'cw_viewable',
			array(
				'label'              => 'Viewable',
				'internal'           => false,
				'public'             => false,
				'publicly_queryable' => true,
			)
		);

		$this->assertTrue( is_post_status_viewable( 'cw_viewable' ), 'Precondition: the viewable status should be publicly viewable.' );
		$this->assertFalse( get_post_status_object( 'cw_viewable' )->internal, 'Precondition: the viewable status should not be internal.' );
		register_post_status(
			'cw_protected',
			array(
				'label'     => 'Protected',
				'protected' => true,
			)
		);
	}

	/**
	 * Returns custom statuses that make a post visible.
	 *
	 * @return array<string, array{status: string}> The statuses.
	 */
	public static function data_custom_statuses_that_make_a_post_visible(): array {
		return array(
			'public'            => array( 'cw_public' ),
			'private'           => array( 'cw_private' ),
			'publicly viewable' => array( 'cw_viewable' ),
		);
	}

	/**
	 * A custom status that makes a post visible needs `publish_posts`, and the refusal uses the
	 * general message, also for a custom private status (C6).
	 *
	 * Covers S5 and S7.
	 *
	 * @dataProvider data_custom_statuses_that_make_a_post_visible
	 *
	 * @since 7.3.0
	 *
	 * @param string $status The status.
	 */
	public function test_custom_visible_status_needs_publish_posts( string $status ): void {
		$this->register_custom_statuses();

		$this->login_as( 'contributor' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Custom status',
					'status' => $status,
				),
			)
		);

		$this->assert_error( $result, 'content_cannot_publish', 403, 'A custom visible status without publish_posts' );
		$this->assertSame( 'Sorry, you are not allowed to publish posts in this post type.', $result->get_error_message(), 'A custom status, also a private one, should get the general message.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A user with `publish_posts` may set a custom status that makes a post visible.
	 *
	 * Covers S7.
	 *
	 * @dataProvider data_custom_statuses_that_make_a_post_visible
	 *
	 * @since 7.3.0
	 *
	 * @param string $status The status.
	 */
	public function test_custom_visible_status_is_set_with_publish_posts( string $status ): void {
		$this->register_custom_statuses();

		$this->login_as( 'author' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Custom status',
					'status' => $status,
				),
			)
		);

		$this->assertSame( $status, $this->get_created_post( $result )->post_status, 'The custom status should be stored.' );
	}

	/**
	 * A custom status that is neither public, private nor publicly viewable needs nothing beyond
	 * `create_posts`.
	 *
	 * Covers S7.
	 *
	 * @since 7.3.0
	 */
	public function test_custom_status_that_keeps_a_post_hidden_needs_no_publish_posts(): void {
		$this->register_custom_statuses();

		$this->login_as( 'contributor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Protected status',
					'status' => 'cw_protected',
				),
			)
		);

		$this->assertSame( 'cw_protected', $this->get_created_post( $result )->post_status, 'The custom status should be stored.' );
	}

	/**
	 * Returns dates that change the stored status of a new post.
	 *
	 * @return array<string, array{status: string, gmt: string, expected: string}> The cases.
	 */
	public static function data_statuses_reported_as_stored(): array {
		return array(
			'publish with a future date' => array( 'publish', self::FUTURE_GMT, 'future' ),
			'future with a past date'    => array( 'future', '2016-12-12T19:00:00', 'publish' ),
		);
	}

	/**
	 * The regular save rules decide the stored status, and the output reports it.
	 *
	 * @dataProvider data_statuses_reported_as_stored
	 *
	 * @since 7.3.0
	 *
	 * @param string $status   The requested status.
	 * @param string $gmt      The requested GMT date.
	 * @param string $expected The stored status.
	 */
	public function test_output_reports_the_stored_status( string $status, string $gmt, string $expected ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'type'   => 'post',
					'title'  => 'Scheduled',
					'status' => $status,
					'date'   => array( 'gmt' => $gmt ),
				),
				'fields' => array( 'status' ),
			)
		);

		$this->assertSame( $expected, $this->get_created_post( $result )->post_status, 'The post should be stored in the expected status.' );
		$this->assertSame( $expected, $result['status'], 'The output should report the stored status.' );
	}

	/**
	 * Returns authors that a user without `edit_others_posts` may not name.
	 *
	 * @return array<string, array{author: string}> The authors, as fixture names.
	 */
	public static function data_authors_named_without_edit_others_posts(): array {
		return array(
			'existing user' => array( 'editor' ),
			'missing user'  => array( 'missing' ),
		);
	}

	/**
	 * A user without `edit_others_posts` gets the same error for an existing and a missing
	 * author, so the error never tells whether a user exists.
	 *
	 * Covers S5, S8 and O3.
	 *
	 * @dataProvider data_authors_named_without_edit_others_posts
	 *
	 * @since 7.3.0
	 *
	 * @param string $author The author, as a fixture name.
	 */
	public function test_naming_another_author_without_edit_others_posts_is_refused( string $author ): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Ghost written',
					'author' => self::$user_ids[ $author ] ?? self::MISSING_ID,
				),
			)
		);

		$this->assertWPError( $result, 'Naming another author should fail.' );
		$this->assertSame(
			array(
				'code'    => 'content_cannot_edit_others',
				'message' => 'Sorry, you are not allowed to create posts as this user.',
				'data'    => array( 'status' => 403 ),
			),
			array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
				'data'    => $result->get_error_data(),
			),
			'The error should be the same whether the user exists or not.'
		);
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A user without `edit_others_posts` may name themselves as the author.
	 *
	 * @since 7.3.0
	 */
	public function test_naming_oneself_as_the_author_needs_no_edit_others_posts(): void {
		$this->login_as( 'contributor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Own post',
					'author' => self::$user_ids['contributor'],
				),
			)
		);

		$this->assertSame( self::$user_ids['contributor'], (int) $this->get_created_post( $result )->post_author, 'The current user should be the author.' );
	}

	/**
	 * A user with `edit_others_posts` may name another existing user as the author.
	 *
	 * @since 7.3.0
	 */
	public function test_naming_another_author_with_edit_others_posts(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'For the author',
					'author' => self::$user_ids['author'],
				),
			)
		);

		$this->assertSame( self::$user_ids['author'], (int) $this->get_created_post( $result )->post_author, 'The named user should be the author.' );
	}

	/**
	 * A user with `edit_others_posts` who names a missing user gets the invalid author error.
	 *
	 * Covers S5 and S8.
	 *
	 * @since 7.3.0
	 */
	public function test_naming_a_missing_author_with_edit_others_posts_is_an_invalid_author(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Nobody',
					'author' => self::MISSING_ID,
				),
			)
		);

		$this->assert_invalid_attribute( $result, 'author', 'A missing author' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * On multisite, a user who is not a member of the site cannot be the author.
	 *
	 * Covers S5 and S8.
	 *
	 * @group ms-required
	 *
	 * @since 7.3.0
	 */
	public function test_naming_an_author_who_is_not_a_member_of_the_site_is_an_invalid_author(): void {
		$non_member = self::factory()->user->create( array( 'role' => 'author' ) );
		remove_user_from_blog( $non_member, get_current_blog_id() );

		$this->assertFalse( is_user_member_of_blog( $non_member ), 'Precondition: the user should not be a member of the site.' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Not a member',
					'author' => $non_member,
				),
			)
		);

		$this->assert_invalid_attribute( $result, 'author', 'An author who is not a member of the site' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * On multisite, a user without `edit_others_posts` who names a user who is not a member of the
	 * site gets the same error as for a missing user.
	 *
	 * Covers S8 and O3.
	 *
	 * @group ms-required
	 *
	 * @since 7.3.0
	 */
	public function test_naming_a_non_member_without_edit_others_posts_is_refused_like_a_missing_user(): void {
		$non_member = self::factory()->user->create( array( 'role' => 'author' ) );
		remove_user_from_blog( $non_member, get_current_blog_id() );

		$this->login_as( 'author' );
		$this->register_abilities();

		$before = $this->snapshot();
		$errors = array();
		foreach ( array(
			'non-member' => $non_member,
			'missing'    => self::MISSING_ID,
		) as $key => $author ) {
			$result = $this->run_ability(
				array(
					'data' => array(
						'type'   => 'post',
						'title'  => 'Ghost written',
						'author' => $author,
					),
				)
			);
			$this->assertWPError( $result, "Naming the {$key} user should fail." );
			$errors[ $key ] = array( $result->get_error_code(), $result->get_error_message(), $result->get_error_data() );
		}

		$this->assertSame( 'content_cannot_edit_others', $errors['missing'][0], 'The error should be the authorship error.' );
		$this->assertSame( $errors['missing'], $errors['non-member'], 'The error should be the same for a non-member and a missing user.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written for either user.' );
	}

	/**
	 * Returns parents that a page may not have.
	 *
	 * @return array<string, array{parent: string}> The parents, as fixture names.
	 */
	public static function data_parents_that_cannot_be_used(): array {
		return array(
			'missing post'               => array( 'missing' ),
			'post of another type'       => array( 'published_post' ),
			'page the user may not read' => array( 'editor_draft_page' ),
			'revision of a page'         => array( 'page_revision' ),
		);
	}

	/**
	 * A missing parent, a parent of another type and a parent the user may not read give one
	 * identical error, so the error never tells whether a post exists.
	 *
	 * Covers S5 and S9.
	 *
	 * @dataProvider data_parents_that_cannot_be_used
	 *
	 * @since 7.3.0
	 *
	 * @param string $parent_key The parent, as a fixture name.
	 */
	public function test_parent_that_cannot_be_used_gives_one_identical_error( string $parent_key ): void {
		$this->login_as( 'page_writer' );
		$this->register_abilities();

		$this->assertTrue( current_user_can( get_post_type_object( 'page' )->cap->create_posts ), 'Precondition: the user should be able to create pages.' );
		$this->assertFalse( current_user_can( 'read_post', self::$post_ids['editor_draft_page'] ), 'Precondition: the user should not be able to read the draft page of the editor.' );

		$create = function ( int $parent_id ) {
			return $this->run_ability(
				array(
					'data' => array(
						'type'   => 'page',
						'title'  => 'Child',
						'parent' => $parent_id,
					),
				)
			);
		};

		$before    = $this->snapshot();
		$reference = $create( self::MISSING_ID );
		$result    = $create( self::$post_ids[ $parent_key ] ?? self::MISSING_ID );

		$this->assert_invalid_attribute( $result, 'parent', 'A parent that cannot be used' );
		$this->assertSame( $reference->get_error_data(), $result->get_error_data(), 'The error data, with its reason, should be the same as for a missing parent.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A readable page of the same type can be the parent, and `0` makes a top-level page.
	 *
	 * @since 7.3.0
	 */
	public function test_parent_is_stored(): void {
		$this->login_as( 'page_writer' );
		$this->register_abilities();

		$child = $this->run_ability(
			array(
				'data'   => array(
					'type'   => 'page',
					'title'  => 'Child',
					'parent' => self::$post_ids['published_page'],
				),
				'fields' => array( 'parent' ),
			)
		);
		$top   = $this->run_ability(
			array(
				'data'   => array(
					'type'   => 'page',
					'title'  => 'Top',
					'parent' => 0,
				),
				'fields' => array( 'parent' ),
			)
		);

		$this->assertSame( self::$post_ids['published_page'], $this->get_created_post( $child )->post_parent, 'The parent should be stored.' );
		$this->assertSame( self::$post_ids['published_page'], $child['parent'], 'The parent should be returned.' );
		$this->assertSame( 0, $this->get_created_post( $top )->post_parent, 'A parent of 0 should make a top-level page.' );
	}

	/**
	 * Returns attributes that a post type does not support.
	 *
	 * @return array<string, array{type: string, attribute: string, value: mixed}> The cases.
	 */
	public static function data_unsupported_attributes(): array {
		return array(
			'excerpt of a page'                 => array( 'page', 'excerpt', 'An excerpt' ),
			'parent of a post'                  => array( 'post', 'parent', 1 ),
			'parent 0 of a post'                => array( 'post', 'parent', 0 ),
			'title without title support'       => array( 'cw_no_title', 'title', 'A title' ),
			'content without editor support'    => array( 'cw_title_only', 'content', 'Some content' ),
			'excerpt without excerpt support'   => array( 'cw_title_only', 'excerpt', 'An excerpt' ),
			'author without author support'     => array( 'cw_title_only', 'author', 1 ),
			'empty title without title support' => array( 'cw_no_title', 'title', '' ),
		);
	}

	/**
	 * An attribute the post type does not support is rejected, even when it would change nothing.
	 *
	 * Covers S1, S5 and O4: the first failure is returned and nothing is written.
	 *
	 * @dataProvider data_unsupported_attributes
	 *
	 * @since 7.3.0
	 *
	 * @param string $type      The post type.
	 * @param string $attribute The attribute.
	 * @param mixed  $value     The value.
	 */
	public function test_unsupported_attribute_is_rejected( string $type, string $attribute, $value ): void {
		$this->register_exposed_post_type( 'cw_no_title', array( 'editor' ) );
		$this->register_exposed_post_type( 'cw_title_only', array( 'title' ) );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$data = array(
			'type'     => $type,
			$attribute => $value,
		);
		if ( 'title' !== $attribute && 'cw_no_title' !== $type ) {
			$data['title'] = 'Unsupported';
		}
		if ( 'cw_no_title' === $type ) {
			$data['content'] = 'Unsupported';
		}
		if ( 'author' === $attribute ) {
			$data['author'] = self::$user_ids['administrator'];
		}

		$before = $this->snapshot();
		$result = $this->run_ability( array( 'data' => $data ) );

		$this->assert_invalid_attribute( $result, $attribute, 'An unsupported attribute' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns attribute values of the wrong type.
	 *
	 * @return array<string, array{type: string, attribute: string, value: mixed}> The cases.
	 */
	public static function data_values_of_the_wrong_type(): array {
		return array(
			'title that is a number'         => array( 'post', 'title', 123 ),
			'title that is a list'           => array( 'post', 'title', array( 'x' ) ),
			'title that is null'             => array( 'post', 'title', null ),
			'content that is a boolean'      => array( 'post', 'content', true ),
			'excerpt that is a number'       => array( 'post', 'excerpt', 5 ),
			'status that is a number'        => array( 'post', 'status', 1 ),
			'slug that is a list'            => array( 'post', 'slug', array( 'slug' ) ),
			'date that is a string'          => array( 'post', 'date', '2016-12-12T14:00:00' ),
			'date with a number'             => array( 'post', 'date', array( 'site' => 123 ) ),
			'date that cannot be parsed'     => array( 'post', 'date', array( 'site' => 'not a date' ) ),
			'GMT date that cannot be parsed' => array( 'post', 'date', array( 'gmt' => 'not a date' ) ),
			'author that is text'            => array( 'post', 'author', 'abc' ),
			'author 0'                       => array( 'post', 'author', 0 ),
			'author that is a fraction'      => array( 'post', 'author', 1.5 ),
			'parent that is negative'        => array( 'page', 'parent', -1 ),
			'parent that is text'            => array( 'page', 'parent', 'abc' ),
		);
	}

	/**
	 * Called directly, a value of the wrong type is an invalid attribute.
	 *
	 * Covers S1 and S5.
	 *
	 * @dataProvider data_values_of_the_wrong_type
	 *
	 * @since 7.3.0
	 *
	 * @param string $type      The post type.
	 * @param string $attribute The attribute.
	 * @param mixed  $value     The value.
	 */
	public function test_direct_call_rejects_a_value_of_the_wrong_type( string $type, string $attribute, $value ): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$data = array( 'type' => $type );
		if ( 'title' !== $attribute ) {
			$data['title'] = 'Wrong type';
		}
		$data[ $attribute ] = $value;

		$before = $this->snapshot();
		$result = $execute( array( 'data' => $data ) );

		$this->assert_invalid_attribute( $result, $attribute, 'A value of the wrong type' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * An empty title is valid next to other content.
	 *
	 * @since 7.3.0
	 */
	public function test_empty_title_is_valid(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'    => 'post',
					'title'   => '',
					'content' => 'Only content.',
				),
			)
		);

		$post = $this->get_created_post( $result );

		$this->assertSame( '', $post->post_title, 'The empty title should be stored.' );
		$this->assertSame( 'Only content.', $post->post_content, 'The content should be stored.' );
	}

	/**
	 * A save error keeps its own code and message, with status 400 when the post is empty.
	 *
	 * Covers S5.
	 *
	 * @since 7.3.0
	 */
	public function test_empty_post_is_the_save_error(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'    => 'post',
					'title'   => '',
					'content' => '',
					'excerpt' => '',
				),
			)
		);

		$this->assert_error( $result, 'empty_content', 400, 'An empty post' );
		$this->assertSame( 'Content, title, and excerpt are empty.', $result->get_error_message(), 'The message should be the message of the save error.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A database error during the save keeps its own code and message, with status 500.
	 *
	 * @since 7.3.0
	 */
	public function test_database_error_is_the_save_error(): void {
		global $wpdb;

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();

		$wpdb->suppress_errors = true;
		add_filter(
			'query',
			static function ( $query ) {
				return 0 === strpos( $query, 'INSERT' ) ? '],' : $query;
			}
		);

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'post',
					'title' => 'Database error',
				),
			)
		);

		$wpdb->suppress_errors = false;

		$this->assert_error( $result, 'db_insert_error', 500, 'A database error' );
		$this->assertSame( 'Could not insert post into the database.', $result->get_error_message(), 'The message should be the message of the save error.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written after a database error.' );
	}

	/**
	 * The after-insert hooks fire once, as in any regular save.
	 *
	 * @since 7.3.0
	 */
	public function test_after_insert_hooks_fire_once(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = did_action( 'wp_after_insert_post' );

		$this->get_created_post(
			$this->run_ability(
				array(
					'data' => array(
						'type'  => 'post',
						'title' => 'Hooks',
					),
				)
			)
		);

		$this->assertSame( 1, did_action( 'wp_after_insert_post' ) - $before, 'wp_after_insert_post should fire once.' );
	}

	/**
	 * Returns slugs and the slug they are stored as.
	 *
	 * @return array<string, array{slug: string, expected: string}> The cases.
	 */
	public static function data_slugs(): array {
		return array(
			'plain'               => array( 'launch-notes', 'launch-notes' ),
			'with capitals'       => array( 'Launch Notes!', 'launch-notes' ),
			'accented characters' => array( 'tęst-acceńted-chäræcters', 'test-accented-charaecters' ),
		);
	}

	/**
	 * A slug is sanitized and stored.
	 *
	 * @dataProvider data_slugs
	 *
	 * @since 7.3.0
	 *
	 * @param string $slug     The sent slug.
	 * @param string $expected The stored slug.
	 */
	public function test_slug_is_sanitized_and_stored( string $slug, string $expected ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'type'  => 'post',
					'title' => 'Slug',
					'slug'  => $slug,
				),
				'fields' => array( 'slug' ),
			)
		);

		$this->assertSame( $expected, $this->get_created_post( $result )->post_name, 'The sanitized slug should be stored.' );
		$this->assertSame( $expected, $result['slug'], 'The stored slug should be returned.' );
	}

	/**
	 * A slug that sanitizes to nothing is an invalid slug.
	 *
	 * Covers S1 and S5.
	 *
	 * @since 7.3.0
	 */
	public function test_slug_that_sanitizes_to_nothing_is_rejected(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'post',
					'title' => 'Slug',
					'slug'  => '!!!',
				),
			)
		);

		$this->assert_invalid_attribute( $result, 'slug', 'A slug that sanitizes to nothing' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns statuses for which the slug of a published post is made unique.
	 *
	 * @return array<string, array{status: string|null}> The statuses, or null for the default.
	 */
	public static function data_statuses_with_unique_slugs(): array {
		return array(
			'default status' => array( null ),
			'draft'          => array( 'draft' ),
			'pending'        => array( 'pending' ),
			'publish'        => array( 'publish' ),
		);
	}

	/**
	 * A new post never takes the slug of another post, also as a draft or a pending post.
	 *
	 * @dataProvider data_statuses_with_unique_slugs
	 *
	 * @since 7.3.0
	 *
	 * @param string|null $status The status, or null to send none.
	 */
	public function test_slug_of_another_post_is_made_unique( ?string $status ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$data = array(
			'type'  => 'post',
			'title' => 'Shared',
			'slug'  => 'shared-slug',
		);
		if ( null !== $status ) {
			$data['status'] = $status;
		}

		$result = $this->run_ability( array( 'data' => $data ) );

		$this->assertSame( 'shared-slug-2', $this->get_created_post( $result )->post_name, 'The slug should be made unique.' );
	}

	/**
	 * Returns parents for a draft page with the slug of an existing child page.
	 *
	 * @return array<string, array{parent: string, expected: string}> The cases.
	 */
	public static function data_draft_page_slugs_by_parent(): array {
		return array(
			'shared parent'    => array( 'published_page', 'child-2' ),
			'different parent' => array( 'other_parent_page', 'child' ),
		);
	}

	/**
	 * A draft page is made unique among the pages that share its parent only.
	 *
	 * @dataProvider data_draft_page_slugs_by_parent
	 *
	 * @since 7.3.0
	 *
	 * @param string $parent_key   The parent, as a fixture name.
	 * @param string $expected The stored slug.
	 */
	public function test_draft_page_slug_is_unique_among_its_siblings( string $parent_key, string $expected ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'page',
					'title'  => 'Child',
					'slug'   => 'child',
					'parent' => self::$post_ids[ $parent_key ],
				),
			)
		);

		$this->assertSame( $expected, $this->get_created_post( $result )->post_name, 'The slug should be unique among the siblings.' );
	}

	/**
	 * Returns sent dates and the dates they are stored as, in America/New_York.
	 *
	 * @return array<string, array{date: array<string, string>, local: string, gmt: string}> The cases.
	 */
	public static function data_dates(): array {
		return array(
			'site without offset' => array( array( 'site' => '2016-12-12T14:00:00' ), '2016-12-12 14:00:00', '2016-12-12 19:00:00' ),
			'GMT without offset'  => array( array( 'gmt' => '2016-12-12T19:00:00' ), '2016-12-12 14:00:00', '2016-12-12 19:00:00' ),
			'site with an offset' => array( array( 'site' => '2016-12-12T18:00:00-01:00' ), '2016-12-12 14:00:00', '2016-12-12 19:00:00' ),
			'GMT with an offset'  => array( array( 'gmt' => '2016-12-12T18:00:00-01:00' ), '2016-12-12 14:00:00', '2016-12-12 19:00:00' ),
			'both, the site wins' => array(
				array(
					'site' => '2016-12-12T14:00:00',
					'gmt'  => '2001-01-01T00:00:00',
				),
				'2016-12-12 14:00:00',
				'2016-12-12 19:00:00',
			),
		);
	}

	/**
	 * A sent date sets both the local and the GMT date of a new draft.
	 *
	 * @dataProvider data_dates
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, string> $date  The sent date.
	 * @param string                $local The stored local date.
	 * @param string                $gmt   The stored GMT date.
	 */
	public function test_date_is_stored_local_and_gmt( array $date, string $local, string $gmt ): void {
		update_option( 'timezone_string', 'America/New_York' );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'post',
					'title' => 'Dated',
					'date'  => $date,
				),
			)
		);

		$post = $this->get_created_post( $result );

		$this->assertSame( 'draft', $post->post_status, 'The post should be a draft.' );
		$this->assertSame( $local, $post->post_date, 'The local date should be stored.' );
		$this->assertSame( $gmt, $post->post_date_gmt, 'The GMT date should be stored.' );
	}

	/**
	 * A draft created without a date has a floating date.
	 *
	 * @since 7.3.0
	 */
	public function test_draft_without_a_date_floats(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$post = $this->get_created_post(
			$this->run_ability(
				array(
					'data' => array(
						'type'  => 'post',
						'title' => 'Floating',
					),
				)
			)
		);

		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt, 'The draft should have no GMT date.' );
	}

	/**
	 * A date that is not a date-time fails input validation.
	 *
	 * Covers S5.
	 *
	 * @since 7.3.0
	 */
	public function test_date_that_is_not_a_date_time_fails_input_validation(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'post',
					'title' => 'Invalid date',
					'date'  => array( 'site' => '2010-60-01T02:00:00Z' ),
				),
			)
		);

		$this->assertWPError( $result, 'The call should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the date.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns markup and the values it is stored and rendered as for a user without
	 * `unfiltered_html`.
	 *
	 * @return array<string, array{input: string, title_raw: string, title_rendered: string, text_raw: string, text_rendered: string}> The cases.
	 */
	public static function data_filtered_markup(): array {
		return array(
			'backslashes and non-ASCII text' => array(
				'\o/ ¯\_(ツ)_/¯',
				'\o/ ¯\_(ツ)_/¯',
				'\o/ ¯\_(ツ)_/¯',
				'\o/ ¯\_(ツ)_/¯',
				'<p>\o/ ¯\_(ツ)_/¯</p>',
			),
			'entities'                       => array(
				'\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'<p>\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;</p>',
			),
			'a script'                       => array(
				'<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'div <strong>strong</strong> ',
				'div <strong>strong</strong>',
				'<div>div</div> <strong>strong</strong> ',
				"<div>div</div>\n<p> <strong>strong</strong> </p>",
			),
			'a link with extra attributes'   => array(
				'<a href="#" target="_blank" unfiltered=true>link</a>',
				'<a href="#">link</a>',
				'<a href="#">link</a>',
				'<a href="#" target="_blank">link</a>',
				'<p><a href="#" target="_blank">link</a></p>',
			),
		);
	}

	/**
	 * Creates a post with the same markup in the title, content and excerpt, and returns the
	 * raw and rendered values.
	 *
	 * @since 7.3.0
	 *
	 * @param string $markup The markup.
	 * @return array<string, mixed> The ability result.
	 */
	private function create_with_markup( string $markup ): array {
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'type'    => 'post',
					'title'   => $markup,
					'content' => $markup,
					'excerpt' => $markup,
				),
				'fields' => array( 'title', 'content.raw', 'content.rendered', 'excerpt.raw', 'excerpt.rendered' ),
			)
		);

		$this->assertIsArray( $result, 'The post with markup should be created.' );

		return $result;
	}

	/**
	 * The markup of an author, who lacks `unfiltered_html`, is filtered as on any other save.
	 *
	 * Covers S10.
	 *
	 * @dataProvider data_filtered_markup
	 *
	 * @since 7.3.0
	 *
	 * @param string $input          The sent markup.
	 * @param string $title_raw      The stored title.
	 * @param string $title_rendered The rendered title, trimmed.
	 * @param string $text_raw       The stored content and excerpt.
	 * @param string $text_rendered  The rendered content and excerpt, trimmed.
	 */
	public function test_markup_of_an_author_is_filtered( string $input, string $title_raw, string $title_rendered, string $text_raw, string $text_rendered ): void {
		$this->login_as( 'author' );

		$this->assertFalse( current_user_can( 'unfiltered_html' ), 'Precondition: the author should not have unfiltered_html.' );

		$result = $this->create_with_markup( $input );

		$this->assertSame( $title_raw, $result['title']['raw'], 'The stored title should be filtered.' );
		$this->assertSame( $title_rendered, trim( $result['title']['rendered'] ), 'The rendered title should be filtered.' );
		$this->assertSame( $text_raw, $result['content']['raw'], 'The stored content should be filtered.' );
		$this->assertSame( $text_rendered, trim( $result['content']['rendered'] ), 'The rendered content should be filtered.' );
		$this->assertSame( $text_raw, $result['excerpt']['raw'], 'The stored excerpt should be filtered.' );
		$this->assertSame( $text_rendered, trim( $result['excerpt']['rendered'] ), 'The rendered excerpt should be filtered.' );
	}

	/**
	 * The markup of a contributor is filtered.
	 *
	 * Covers S10.
	 *
	 * @since 7.3.0
	 */
	public function test_markup_of_a_contributor_is_filtered(): void {
		$this->login_as( 'contributor' );

		$result = $this->create_with_markup( '<div>div</div> <strong>strong</strong> <script>oh noes</script>' );

		$this->assertSame( 'div <strong>strong</strong> ', $result['title']['raw'], 'The stored title should be filtered.' );
		$this->assertSame( '<div>div</div> <strong>strong</strong> ', $result['content']['raw'], 'The stored content should be filtered.' );
		$this->assertSame( '<div>div</div> <strong>strong</strong> ', $result['excerpt']['raw'], 'The stored excerpt should be filtered.' );
	}

	/**
	 * On multisite, the markup of a site administrator, who lacks `unfiltered_html`, is filtered.
	 *
	 * Covers S10.
	 *
	 * @group ms-required
	 *
	 * @since 7.3.0
	 */
	public function test_markup_of_a_site_administrator_is_filtered_on_multisite(): void {
		$this->login_as( 'administrator' );

		$this->assertFalse( current_user_can( 'unfiltered_html' ), 'Precondition: the site administrator should not have unfiltered_html.' );

		$result = $this->create_with_markup( '<div>div</div> <strong>strong</strong> <script>oh noes</script>' );

		$this->assertSame( 'div <strong>strong</strong> ', $result['title']['raw'], 'The stored title should be filtered.' );
		$this->assertSame( '<div>div</div> <strong>strong</strong> ', $result['content']['raw'], 'The stored content should be filtered.' );
	}

	/**
	 * Asserts that markup is stored unchanged.
	 *
	 * @since 7.3.0
	 */
	private function assert_markup_is_stored_unchanged(): void {
		$this->assertTrue( current_user_can( 'unfiltered_html' ), 'Precondition: the user should have unfiltered_html.' );

		$markup = '<div>div</div> <strong>strong</strong> <script>oh noes</script>';
		$result = $this->create_with_markup( $markup );

		$this->assertSame( $markup, $result['title']['raw'], 'The stored title should not be filtered.' );
		$this->assertSame( $markup, $result['content']['raw'], 'The stored content should not be filtered.' );
		$this->assertSame( $markup, $result['excerpt']['raw'], 'The stored excerpt should not be filtered.' );
	}

	/**
	 * The markup of an administrator with `unfiltered_html` is stored unchanged.
	 *
	 * Covers S10.
	 *
	 * @group ms-excluded
	 *
	 * @since 7.3.0
	 */
	public function test_markup_of_an_administrator_is_not_filtered(): void {
		$this->login_as( 'administrator' );

		$this->assert_markup_is_stored_unchanged();
	}

	/**
	 * On multisite, the markup of a super admin is stored unchanged.
	 *
	 * Covers S10.
	 *
	 * @group ms-required
	 *
	 * @since 7.3.0
	 */
	public function test_markup_of_a_super_admin_is_not_filtered(): void {
		$super_admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin );
		wp_set_current_user( $super_admin );

		$this->assert_markup_is_stored_unchanged();
	}

	/**
	 * Quotes, backslashes and non-ASCII text survive a round trip unchanged.
	 *
	 * @since 7.3.0
	 */
	public function test_quotes_backslashes_and_non_ascii_text_survive_a_round_trip(): void {
		$text = 'He said "it\'s done" \\ C:\\path\\to\\file \\\\server – café, naïve, 日本語, emoji ✓';

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'type'    => 'post',
					'title'   => $text,
					'content' => $text,
					'excerpt' => $text,
				),
				'fields' => array( 'title.raw', 'content.raw', 'excerpt.raw' ),
			)
		);

		$post = $this->get_created_post( $result );

		$this->assertSame( $text, $post->post_title, 'The stored title should be unchanged.' );
		$this->assertSame( $text, $post->post_content, 'The stored content should be unchanged.' );
		$this->assertSame( $text, $post->post_excerpt, 'The stored excerpt should be unchanged.' );
		$this->assertSame( $text, $result['title']['raw'], 'The returned title should be unchanged.' );
		$this->assertSame( $text, $result['content']['raw'], 'The returned content should be unchanged.' );
		$this->assertSame( $text, $result['excerpt']['raw'], 'The returned excerpt should be unchanged.' );
	}

	/**
	 * Raw fields are left out when the writer cannot edit the post after the write.
	 *
	 * The user may publish posts but not edit published posts.
	 *
	 * Covers S11.
	 *
	 * @since 7.3.0
	 */
	public function test_raw_fields_are_left_out_when_the_writer_cannot_edit_the_new_post(): void {
		$this->login_as( 'publisher' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'type'    => 'post',
					'title'   => 'Published once',
					'content' => 'Body.',
					'status'  => 'publish',
				),
				'fields' => array( 'title', 'content.raw', 'content.rendered' ),
			)
		);

		$post = $this->get_created_post( $result );

		$this->assertSame( 'publish', $post->post_status, 'The post should be published.' );
		$this->assertFalse( current_user_can( 'edit_post', $post->ID ), 'Precondition: the user should not be able to edit the published post.' );
		$this->assertSame( array( 'rendered' => 'Published once' ), $result['title'], 'The title should be returned without its raw value.' );
		$this->assertSame( array( 'rendered' => "<p>Body.</p>\n" ), $result['content'], 'The content should be returned without its raw value.' );
	}

	/**
	 * The current user is checked at each call, also on the same instance.
	 *
	 * Covers S4.
	 *
	 * @since 7.3.0
	 */
	public function test_each_call_checks_the_current_user(): void {
		$execute = $this->get_ability_callbacks()['execute_callback'];
		$input   = array(
			'data' => array(
				'type'  => 'post',
				'title' => 'Twice',
			),
		);

		$this->login_as( 'author' );
		$first = $execute( $input );

		$this->login_as( 'subscriber' );
		$before = $this->snapshot();
		$second = $execute( $input );

		$this->assertSame( self::$user_ids['author'], (int) $this->get_created_post( $first )->post_author, 'The first call should create a post for the author.' );
		$this->assert_error( $second, 'content_cannot_create', 403, 'The second call as a subscriber' );
		$this->assertSame( $before, $this->snapshot(), 'The second call should write nothing.' );
	}

	/**
	 * Only the requested fields are returned, and the ID always is.
	 *
	 * Covers S11.
	 *
	 * @since 7.3.0
	 */
	public function test_returns_the_requested_fields_and_the_id(): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'type'  => 'post',
					'title' => 'Fields',
				),
				'fields' => array( 'author', 'title.rendered' ),
			)
		);

		$user = wp_get_current_user();
		$post = $this->get_created_post( $result );

		$this->assertSame(
			array(
				'id'     => $post->ID,
				'title'  => array( 'rendered' => 'Fields' ),
				'author' => array(
					'id'   => $user->ID,
					'name' => $user->display_name,
					'slug' => $user->user_nicename,
				),
			),
			$result,
			'The ID and the requested fields should be returned.'
		);
	}

	/**
	 * Returns an integer in one of the forms that are read as that integer.
	 *
	 * @since 7.3.0
	 *
	 * @param int    $value The integer.
	 * @param string $form  The form: `float`, `decimal`, `plus`, `leading space`, `trailing space`,
	 *                      `leading newline` or `exponent`.
	 * @return float|string The integer in that form.
	 */
	private static function whole_number_form( int $value, string $form ) {
		$forms = array(
			'float'           => (float) $value,
			'decimal'         => "{$value}.0",
			'plus'            => "+{$value}",
			'leading space'   => " {$value}",
			'trailing space'  => "{$value} ",
			'leading newline' => "\n{$value}",
			'exponent'        => "{$value}e0",
		);

		return $forms[ $form ];
	}

	/**
	 * Returns the forms of a whole number that are read as the integer they equal.
	 *
	 * @return array<string, array{form: string}> The forms.
	 */
	public static function data_whole_number_forms(): array {
		return array(
			'whole float'                => array( 'float' ),
			'string with .0'             => array( 'decimal' ),
			'string with plus'           => array( 'plus' ),
			'string with leading space'  => array( 'leading space' ),
			'string with trailing space' => array( 'trailing space' ),
			'string with leading line'   => array( 'leading newline' ),
			'string with an exponent'    => array( 'exponent' ),
		);
	}

	/**
	 * Called directly, an author in another whole-number form is read as the integer it equals.
	 *
	 * Covers C3.
	 *
	 * @dataProvider data_whole_number_forms
	 *
	 * @since 7.3.0
	 *
	 * @param string $form The form of the author.
	 */
	public function test_direct_call_reads_an_author_in_a_whole_number_form( string $form ): void {
		$this->login_as( 'editor' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Whole number author',
					'author' => self::whole_number_form( self::$user_ids['author'], $form ),
				),
			)
		);

		$this->assertSame( self::$user_ids['author'], (int) $this->get_created_post( $result )->post_author, 'The user with that ID should be the author.' );
	}

	/**
	 * Called directly, a parent in another whole-number form is read as the integer it equals.
	 *
	 * Covers C3.
	 *
	 * @dataProvider data_whole_number_forms
	 *
	 * @since 7.3.0
	 *
	 * @param string $form The form of the parent.
	 */
	public function test_direct_call_reads_a_parent_in_a_whole_number_form( string $form ): void {
		$parent_id = self::$post_ids['published_page'];

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'data' => array(
					'type'   => 'page',
					'title'  => 'Whole number parent',
					'parent' => self::whole_number_form( $parent_id, $form ),
				),
			)
		);

		$this->assertSame( $parent_id, $this->get_created_post( $result )->post_parent, 'The page with that ID should be the parent.' );
	}

	/**
	 * Returns date values without a usable value for a direct call.
	 *
	 * @return array<string, array{date: array<string, mixed>}> The dates.
	 */
	public static function data_dates_without_a_usable_value(): array {
		return array(
			'empty date'           => array( array() ),
			'GMT that is a number' => array(
				array(
					'site' => '2016-12-12T14:00:00',
					'gmt'  => 123,
				),
			),
			'GMT that is a list'   => array(
				array(
					'site' => '2016-12-12T14:00:00',
					'gmt'  => array( '2016-12-12T19:00:00' ),
				),
			),
			'GMT that is null'     => array(
				array(
					'site' => '2016-12-12T14:00:00',
					'gmt'  => null,
				),
			),
		);
	}

	/**
	 * Called directly, an empty date or a GMT value of the wrong type is an invalid attribute.
	 *
	 * Covers S5 (C2).
	 *
	 * @dataProvider data_dates_without_a_usable_value
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $date The date.
	 */
	public function test_direct_call_rejects_a_date_without_a_usable_value( array $date ): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot();
		$result = $execute(
			array(
				'data' => array(
					'type'  => 'post',
					'title' => 'Unusable date',
					'date'  => $date,
				),
			)
		);

		$this->assert_invalid_attribute( $result, 'date', 'A direct call with a date without a usable value' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A user who may create posts but not publish them cannot create a private post.
	 *
	 * Covers S5 and S7.
	 *
	 * @since 7.3.0
	 */
	public function test_private_post_needs_publish_posts_from_a_user_who_may_create_posts(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		// Denies the capability that the role grants.
		get_userdata( $user_id )->add_cap( 'publish_posts', false );
		wp_set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'edit_posts' ), 'Precondition: the user should be able to create posts.' );
		$this->assertFalse( current_user_can( 'publish_posts' ), 'Precondition: the user should not be able to publish posts.' );

		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Private without publish_posts',
					'status' => 'private',
				),
			)
		);

		$this->assert_error( $result, 'content_cannot_publish', 403, 'A private post without publish_posts' );
		$this->assertSame( 'Sorry, you are not allowed to create private posts in this post type.', $result->get_error_message(), 'The message should be the private message.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written for the refused private post.' );
	}

	/**
	 * A pending post that a contributor creates without a date has a floating date.
	 *
	 * Covers S5.
	 *
	 * @since 7.3.0
	 */
	public function test_pending_post_of_a_contributor_without_a_date_floats(): void {
		$this->login_as( 'contributor' );
		$this->register_abilities();

		$post = $this->get_created_post(
			$this->run_ability(
				array(
					'data' => array(
						'type'   => 'post',
						'title'  => 'Pending and floating',
						'status' => 'pending',
					),
				)
			)
		);

		$this->assertSame( 'pending', $post->post_status, 'The post should be pending.' );
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt, 'The pending post should have no GMT date.' );
	}

	/**
	 * A type that gets `show_in_abilities` after the abilities were registered is not in the type
	 * enum, so a create of that type fails input validation.
	 *
	 * Covers S3 and S5.
	 *
	 * @since 7.3.0
	 */
	public function test_type_exposed_after_registration_fails_input_validation(): void {
		register_post_type(
			'cw_late',
			array(
				'public'   => true,
				'supports' => array( 'title' ),
			)
		);

		$this->login_as( 'administrator' );
		$this->register_abilities();

		get_post_type_object( 'cw_late' )->show_in_abilities = true;

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'cw_late',
					'title' => 'Exposed too late',
				),
			)
		);

		$this->assertWPError( $result, 'A create of a type exposed after registration should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject a type exposed after registration.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written for a type exposed after registration.' );
	}

	/**
	 * Registers an exposed post type whose create capability differs from its edit capability.
	 *
	 * @since 7.3.0
	 */
	private function register_type_with_its_own_create_capability(): void {
		register_post_type(
			'cw_book',
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor' ),
				'capability_type'   => 'cw_book',
				'map_meta_cap'      => true,
				'capabilities'      => array( 'create_posts' => 'create_cw_books' ),
			)
		);

		$caps = get_post_type_object( 'cw_book' )->cap;

		$this->assertSame( 'create_cw_books', $caps->create_posts, 'Precondition: the type should have its own create capability.' );
		$this->assertSame( 'edit_cw_books', $caps->edit_posts, 'Precondition: the type should have its own edit capability.' );
	}

	/**
	 * A user with only the create capability of a type may create a draft of it, but may not
	 * publish it.
	 *
	 * Covers S5 and S7.
	 *
	 * @since 7.3.0
	 */
	public function test_user_with_only_the_create_capability_creates_a_draft_and_cannot_publish(): void {
		$this->register_type_with_its_own_create_capability();

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( $user_id )->add_cap( 'create_cw_books' );
		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'edit_cw_books' ), 'Precondition: the user should not have the edit capability.' );

		$this->register_abilities();

		$draft = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'cw_book',
					'title' => 'Draft book',
				),
			)
		);

		$post = $this->get_created_post( $draft );

		$this->assertSame( 'cw_book', $post->post_type, 'The draft should be of the custom type.' );
		$this->assertSame( 'draft', $post->post_status, 'The new post should be a draft.' );
		$this->assertSame( $user_id, (int) $post->post_author, 'The user should be the author of the draft.' );

		$before    = $this->snapshot();
		$published = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'cw_book',
					'title'  => 'Published book',
					'status' => 'publish',
				),
			)
		);

		$this->assert_error( $published, 'content_cannot_publish', 403, 'Publishing with only the create capability' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written for the refused publish.' );
	}

	/**
	 * Called directly, a user with only the edit capability of a type, and not its create
	 * capability, may not create a post of it.
	 *
	 * Covers S5.
	 *
	 * @since 7.3.0
	 */
	public function test_direct_call_with_only_the_edit_capability_cannot_create(): void {
		$this->register_type_with_its_own_create_capability();

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( $user_id )->add_cap( 'edit_cw_books' );
		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'create_cw_books' ), 'Precondition: the user should not have the create capability.' );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot();
		$result = $execute(
			array(
				'data' => array(
					'type'  => 'cw_book',
					'title' => 'Book',
				),
			)
		);

		$this->assert_error( $result, 'content_cannot_create', 403, 'A direct call with only the edit capability' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * On multisite, after switching to a second site, a user who is an editor only on the first
	 * site may not create posts there.
	 *
	 * Covers S4 and S8.
	 *
	 * @group ms-required
	 *
	 * @since 7.3.0
	 */
	public function test_editor_of_the_first_site_only_is_refused_on_the_second_site(): void {
		$blog_id = self::factory()->blog->create();

		$this->assertFalse( is_user_member_of_blog( self::$user_ids['editor'], $blog_id ), 'Precondition: the editor should not be a member of the second site.' );

		$this->login_as( 'editor' );
		$first_before = $this->snapshot();

		switch_to_blog( $blog_id );
		$callbacks = $this->get_ability_callbacks();
		$input     = array(
			'data' => array(
				'type'  => 'post',
				'title' => 'Second site',
			),
		);

		$second_before = $this->snapshot();
		$regular       = $this->run_ability( $input );
		$direct        = $callbacks['execute_callback']( $input );
		$second_after  = $this->snapshot();

		restore_current_blog();

		$this->assertWPError( $regular, 'The regular call on the second site should fail.' );
		$this->assertSame( 'ability_invalid_permissions', $regular->get_error_code(), 'The permission callback should use the capabilities of the second site.' );
		$this->assert_error( $direct, 'content_cannot_create', 403, 'A direct call on the second site' );
		$this->assertSame( $second_before, $second_after, 'Nothing should be written on the second site.' );
		$this->assertSame( $first_before, $this->snapshot(), 'Nothing should be written on the first site.' );
	}

	/**
	 * On multisite, after switching to a second site, an editor of that site who names a user who
	 * is a member only of the first site gets the invalid author error.
	 *
	 * Covers S5 and S8.
	 *
	 * @group ms-required
	 *
	 * @since 7.3.0
	 */
	public function test_member_of_the_first_site_only_is_an_invalid_author_on_the_second_site(): void {
		$blog_id   = self::factory()->blog->create();
		$editor_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		add_user_to_blog( $blog_id, $editor_id, 'editor' );

		$this->assertFalse( is_user_member_of_blog( self::$user_ids['author'], $blog_id ), 'Precondition: the author should not be a member of the second site.' );

		wp_set_current_user( $editor_id );
		switch_to_blog( $blog_id );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Second site',
					'author' => self::$user_ids['author'],
				),
			)
		);
		$after  = $this->snapshot();

		restore_current_blog();

		$this->assert_invalid_attribute( $result, 'author', 'An author who is a member of the first site only' );
		$this->assertSame( $before, $after, 'Nothing should be written on the second site.' );
	}

	/**
	 * A page in the Trash that the user may read is accepted as the parent.
	 *
	 * Covers S9 (C13).
	 *
	 * @since 7.3.0
	 */
	public function test_trashed_page_the_user_may_read_is_accepted_as_the_parent(): void {
		$parent_id = self::$post_ids['trashed_page'];

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assertSame( 'trash', get_post_status( $parent_id ), 'Precondition: the parent should be in the Trash.' );
		$this->assertTrue( current_user_can( 'read_post', $parent_id ), 'Precondition: the editor should be able to read the trashed page.' );

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'page',
					'title'  => 'Child of a trashed page',
					'parent' => $parent_id,
				),
			)
		);

		$this->assertSame( $parent_id, $this->get_created_post( $result )->post_parent, 'The trashed page should be stored as the parent.' );
	}
}
