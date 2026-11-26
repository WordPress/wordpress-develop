<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-content-write-ability.php';
require_once __DIR__ . '/trait-content-data-write-tests.php';

/**
 * Tests for the core/content-create ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Content
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreContentCreateAbility extends WP_Abilities_Content_Write_UnitTestCase {

	use WP_Abilities_Content_Data_Write_Tests;

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/content-create';

	/**
	 * The label of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const LABEL = 'Create Content';

	/**
	 * The annotations of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, bool>
	 */
	protected const ANNOTATIONS = array(
		'readonly'    => false,
		'destructive' => false,
		'idempotent'  => false,
	);

	/**
	 * Creates shared users and posts.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		foreach ( self::ROLES as $role ) {
			self::$user_ids[ $role ] = $factory->user->create( array( 'role' => $role ) );
		}

		// A user who may create pages but not read the drafts of other users.
		self::$user_ids['page_writer'] = $factory->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( self::$user_ids['page_writer'] )->add_cap( 'edit_pages' );

		// A user who may publish posts but not edit published posts.
		self::$user_ids['publisher'] = $factory->user->create( array( 'role' => 'contributor' ) );
		get_userdata( self::$user_ids['publisher'] )->add_cap( 'publish_posts' );

		self::$post_ids['published_post'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_status' => 'publish',
				'post_name'   => 'shared-slug',
			)
		);

		foreach ( array( 'published_page', 'other_parent_page', 'trashed_page' ) as $key ) {
			self::$post_ids[ $key ] = $factory->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => 'page',
					'post_status' => 'publish',
				)
			);
		}
		wp_trash_post( self::$post_ids['trashed_page'] );

		self::$post_ids['editor_draft_page'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['editor'],
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);

		// A child page whose `child` slug data_draft_page_slugs_by_parent() reads.
		$factory->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => self::$post_ids['published_page'],
				'post_name'   => 'child',
			)
		);

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
	 * Returns the post a create call returned.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context Optional. What the call was, for the assertion messages. Default 'The create call'.
	 * @return WP_Post The post with the returned ID.
	 */
	private function get_created_post( $result, string $context = 'The create call' ): WP_Post {
		$this->assertIsArray( $result, "{$context} should succeed." );
		$this->assertArrayHasKey( 'id', $result, "{$context} should return the created post with its ID." );
		$this->assertIsInt( $result['id'], "{$context} should return the ID as an integer." );

		$post = get_post( $result['id'] );
		$this->assertInstanceOf( WP_Post::class, $post, "{$context} should return the ID of a stored post." );

		return $post;
	}

	/**
	 * Returns null: the shared tests create a post.
	 *
	 * @since 7.3.0
	 *
	 * @param string $role The role, as a fixture name.
	 * @return null No post ID.
	 */
	protected function data_write_post_id( string $role ): ?int {
		return null;
	}

	/**
	 * Builds the input that creates a post of type `post` with the given data.
	 *
	 * @since 7.3.0
	 *
	 * @param int|null             $post_id Unused: the call creates a post.
	 * @param array<string, mixed> $data    The data, without the type.
	 * @param list<string>|null    $fields  Optional. The fields, or null for the default. Default null.
	 * @return array<string, mixed> The input.
	 */
	protected function data_write_input( ?int $post_id, array $data, ?array $fields = null ): array {
		$input = array( 'data' => array_merge( array( 'type' => 'post' ), $data ) );
		if ( null !== $fields ) {
			$input['fields'] = $fields;
		}

		return $input;
	}

	/**
	 * Returns the post a create call returned.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed    $result  The ability result.
	 * @param int|null $post_id Unused: the call created a post.
	 * @return WP_Post The created post.
	 */
	protected function assert_data_written( $result, ?int $post_id ): WP_Post {
		return $this->get_created_post( $result );
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
	 * The input schema takes `data`, with the post type and the allowed attributes, and `fields`.
	 *
	 * @ticket 66706
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
		$this->assertArrayNotHasKey( 'minLength', $properties['slug'], 'The slug should take an empty string, so the empty slug of a draft can be sent back.' );
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
	 * @ticket 66706
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
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_schema_enums_list_the_exposed_post_types_and_registered_statuses(): void {
		$this->register_exposed_post_type( 'cw_book', array( 'title' ) );
		register_post_type( 'cw_unexposed', array( 'public' => true ) );
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
		$this->assertContains( 'cw_archived', $properties['status']['enum'], 'The status should list a custom status.' );
	}

	/**
	 * A post created with only a type and a title is a draft, returned in the default fields.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_creates_a_draft_with_the_default_fields(): void {
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

		$this->assertSameSets( array( 'id', 'type', 'status', 'date', 'slug', 'title' ), array_keys( $result ), 'The default fields should be returned.' );
		$this->assertSame( 'post', $result['type'], 'The type should be post.' );
		$this->assertSame( 'draft', $result['status'], 'The post should be a draft.' );
		$this->assertSameSets( array( 'site', 'gmt' ), array_keys( $result['date'] ), 'The date should be given in site time and GMT.' );
		$this->assertNotFalse( rest_parse_date( $result['date']['site'] ), 'The site date should be a date-time.' );
		$this->assertNotFalse( rest_parse_date( $result['date']['gmt'] ), 'The GMT date should be a date-time.' );
		$this->assertSame( '', $result['slug'], 'A draft without a slug should have an empty slug.' );
		$this->assertSame( array( 'rendered' => 'Launch notes' ), $result['title'], 'The title should be returned rendered.' );
		$this->assertSame( 'Launch notes', $post->post_title, 'The title should be stored.' );
	}

	/**
	 * A published child page is returned in the chosen fields.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_creates_a_published_child_page_with_chosen_fields(): void {
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

		$this->assertSameSets( array( 'id', 'status', 'link', 'title', 'content', 'parent' ), array_keys( $result ), 'The chosen fields should be returned.' );
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
	 * Returns users that the permission callback refuses.
	 *
	 * @return array<string, array{0: string|null}> The role, or null to stay logged out.
	 */
	public static function data_users_refused_by_the_permission_callback(): array {
		return array(
			'logged out' => array( null ),
			'subscriber' => array( 'subscriber' ),
		);
	}

	/**
	 * A logged-out user and a user without `create_posts` are refused before the ability runs.
	 *
	 * @ticket 66706
	 * @dataProvider data_users_refused_by_the_permission_callback
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

		$this->assert_refused( $result, 'A user who cannot create posts' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns inputs for which the permission callback returns false, even for an administrator.
	 *
	 * @return array<string, array{0: mixed}> The inputs.
	 */
	public static function data_inputs_refused_by_the_permission_callback(): array {
		return array(
			'no input'              => array( null ),
			'no data'               => array( array() ),
			'data that is a string' => array( array( 'data' => 'post' ) ),
			'no type'               => array( array( 'data' => array( 'title' => 'x' ) ) ),
			'type that is a list'   => array( array( 'data' => array( 'type' => array( 'post' ) ) ) ),
			'type that is a number' => array( array( 'data' => array( 'type' => 1 ) ) ),
			'unexposed type'        => array( array( 'data' => array( 'type' => 'attachment' ) ) ),
			'unregistered type'     => array( array( 'data' => array( 'type' => 'cw_missing' ) ) ),
			'status that is a list' => array(
				array(
					'data' => array(
						'type'   => 'post',
						'status' => array( 'draft' ),
					),
				),
			),
			'author that is a word' => array(
				array(
					'data' => array(
						'type'   => 'post',
						'author' => 'admin',
					),
				),
			),
		);
	}

	/**
	 * The permission callback returns true for a user who may create posts of an exposed type.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_permission_callback_returns_true_for_a_user_who_may_create_posts(): void {
		$this->login_as( 'contributor' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertTrue( $permission( array( 'data' => array( 'type' => 'post' ) ) ), 'The permission callback should return true.' );
	}

	/**
	 * Returns the outcome of creating a post per role and requested status: the role, the
	 * requested status or null to send none, and the stored status or null when the permission
	 * callback refuses the call.
	 *
	 * @return array<string, array{0: string, 1: string|null, 2: string|null}> The cases.
	 */
	public static function data_create_by_role_and_status(): array {
		$cases = array();
		foreach ( array( 'administrator', 'editor', 'author', 'contributor' ) as $role ) {
			$cases[ "{$role}, no status" ] = array( $role, null, 'draft' );
			foreach ( array( 'draft', 'pending', 'publish', 'future', 'private' ) as $status ) {
				// A contributor may only create drafts and pending posts.
				if ( 'contributor' === $role && ! in_array( $status, array( 'draft', 'pending' ), true ) ) {
					$cases[ "{$role}, {$status}" ] = array( $role, $status, null );
				} else {
					$cases[ "{$role}, {$status}" ] = array( $role, $status, $status );
				}
			}
		}

		return $cases + array(
			// A custom status that makes a post visible needs `publish_posts`.
			'author, cw_public'        => array( 'author', 'cw_public', 'cw_public' ),
			'author, cw_private'       => array( 'author', 'cw_private', 'cw_private' ),
			'author, cw_viewable'      => array( 'author', 'cw_viewable', 'cw_viewable' ),
			'contributor, cw_public'   => array( 'contributor', 'cw_public', null ),
			'contributor, cw_private'  => array( 'contributor', 'cw_private', null ),
			'contributor, cw_viewable' => array( 'contributor', 'cw_viewable', null ),
			// A custom status that is neither public, private nor publicly viewable needs nothing beyond `create_posts`.
			'contributor, cw_hidden'   => array( 'contributor', 'cw_hidden', 'cw_hidden' ),
		);
	}

	/**
	 * Each role may create posts in the statuses it may set, as their own author.
	 *
	 * `future`, `publish`, `private` and custom statuses that make a post visible need
	 * `publish_posts`. A scheduled post is sent with a date in the future, and a draft or a
	 * pending post sent without a date has a floating date.
	 *
	 * @ticket 66706
	 * @dataProvider data_create_by_role_and_status
	 * @since 7.3.0
	 *
	 * @param string      $role     The role to create as.
	 * @param string|null $status   The requested status, or null to send none.
	 * @param string|null $expected The stored status, or null when the call is refused.
	 */
	public function test_create_by_role_and_status( string $role, ?string $status, ?string $expected ): void {
		$this->register_custom_statuses();

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

		if ( null === $expected ) {
			$this->assert_refused( $result, 'Creating a post in a status the role may not set' );
			$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
			return;
		}

		$post = $this->get_created_post( $result );

		$this->assertSame( $expected, $result['status'], 'The returned status should be the stored status.' );
		$this->assertSame( $expected, $post->post_status, 'The post should be stored in the expected status.' );
		$this->assertSame( self::$user_ids[ $role ], (int) $post->post_author, 'The current user should be the author.' );
		$this->assertSame( 'post', $post->post_type, 'The post should have the requested type.' );
		if ( in_array( $expected, array( 'draft', 'pending' ), true ) ) {
			$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt, 'A draft or a pending post sent without a date should have no GMT date.' );
		}
	}

	/**
	 * A type whose `show_in_abilities` is turned off after registration cannot be created.
	 *
	 * @ticket 66706
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

		$this->assert_refused( $result, 'The regular call for a type that is no longer exposed' );

		$direct = $callbacks['execute_callback']( $input );

		$this->assert_not_found( $direct, 'A direct call for a type that is no longer exposed' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns direct-call inputs without an exposed post type.
	 *
	 * @return array<string, array{0: array<string, mixed>}> The inputs.
	 */
	public static function data_direct_inputs_without_an_exposed_type(): array {
		return array(
			'no data'        => array( array() ),
			'no type'        => array( array( 'data' => array( 'title' => 'x' ) ) ),
			'attachment'     => array(
				array(
					'data' => array(
						'type'  => 'attachment',
						'title' => 'x',
					),
				),
			),
			'revision'       => array(
				array(
					'data' => array(
						'type'  => 'revision',
						'title' => 'x',
					),
				),
			),
			'unexposed type' => array(
				array(
					'data' => array(
						'type'  => 'cw_unexposed',
						'title' => 'x',
					),
				),
			),
		);
	}

	/**
	 * Called directly, input without an exposed post type is the not-found error.
	 *
	 * @ticket 66706
	 * @dataProvider data_direct_inputs_without_an_exposed_type
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $input The input.
	 */
	public function test_direct_call_without_an_exposed_type_is_not_found( array $input ): void {
		register_post_type( 'cw_unexposed', array( 'public' => true ) );

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot();
		$result = $execute( $input );

		$this->assert_not_found( $result, 'A direct call without an exposed type' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns `data` values that the input schema rejects: keys outside the allow-list, statuses
	 * that can never be set and values of the wrong type.
	 *
	 * @return array<string, array{0: array<string, mixed>}> The data.
	 */
	public static function data_data_that_fails_input_validation(): array {
		$cases = array();

		foreach ( array(
			'ID'                => 1,
			'post_type'         => 'page',
			'post_password'     => 'secret',
			'meta_input'        => array( 'cw_key' => 'value' ),
			'tax_input'         => array( 'category' => array( 1 ) ),
			'comment_status'    => 'closed',
			'post_author'       => 1,
			'import_id'         => 7654321,
			'guid'              => 'https://example.org/forged',
			'page_template'     => 'cw-template.php',
			'modified'          => '2001-01-01T00:00:00',
			'post_modified'     => '2001-01-01 00:00:00',
			'post_modified_gmt' => '2001-01-01 00:00:00',
			'sticky'            => true,
			'tags_input'        => array( 'cw-tag' ),
			'post_category'     => array( 2 ),
			'menu_order'        => 5,
			'post_mime_type'    => 'image/jpeg',
		) as $key => $value ) {
			$cases[ "key outside the allow-list: {$key}" ] = array(
				array(
					'type'  => 'post',
					'title' => 'Invalid',
					$key    => $value,
				),
			);
		}

		foreach ( self::data_statuses_that_can_never_be_set() as $label => list( $status ) ) {
			$cases[ "status that can never be set: {$label}" ] = array(
				array(
					'type'   => 'post',
					'title'  => 'Invalid',
					'status' => $status,
				),
			);
		}

		foreach ( array(
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
			'date that is not a date-time'   => array( 'post', 'date', array( 'site' => '2010-60-01T02:00:00Z' ) ),
			'author that is text'            => array( 'post', 'author', 'abc' ),
			'author 0'                       => array( 'post', 'author', 0 ),
			'author that is a fraction'      => array( 'post', 'author', 1.5 ),
			'parent that is negative'        => array( 'page', 'parent', -1 ),
			'parent that is text'            => array( 'page', 'parent', 'abc' ),
		) as $label => list( $type, $attribute, $value ) ) {
			$data = array( 'type' => $type );
			if ( 'title' !== $attribute ) {
				$data['title'] = 'Invalid';
			}
			$data[ $attribute ] = $value;

			$cases[ "value of the wrong type: {$label}" ] = array( $data );
		}

		return $cases;
	}

	/**
	 * A key outside the allow-list, a status that can never be set and a value of the wrong type
	 * fail input validation.
	 *
	 * @ticket 66706
	 * @dataProvider data_data_that_fails_input_validation
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $data The data.
	 */
	public function test_data_fails_input_validation( array $data ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability( array( 'data' => $data ) );

		$this->assertWPError( $result, 'The call should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the data.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A status that is no longer registered when the call runs is an invalid status.
	 *
	 * @ticket 66706
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

		$this->assert_invalid_attribute( $result, 'status', 'A status that is no longer registered', 'The status must be a registered post status that is not internal.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * The permission callback refuses a user without `edit_others_posts` who names another
	 * author, existing or not, so the refusal never tells whether a user exists.
	 *
	 * @ticket 66706
	 * @dataProvider data_authors_named_without_edit_others_posts
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
					'author' => self::$user_ids[ $author ] ?? REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
				),
			)
		);

		$this->assert_refused( $result, 'Naming another author' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * Returns authors that a role may name: the role and the author, as fixture names.
	 *
	 * @return array<string, array{0: string, 1: string}> The cases.
	 */
	public static function data_authors_a_role_may_name(): array {
		return array(
			'oneself without edit_others_posts'   => array( 'contributor', 'contributor' ),
			'another user with edit_others_posts' => array( 'editor', 'author' ),
		);
	}

	/**
	 * A user without `edit_others_posts` may name themselves as the author, and a user with
	 * `edit_others_posts` may name another existing user.
	 *
	 * @ticket 66706
	 * @dataProvider data_authors_a_role_may_name
	 * @since 7.3.0
	 *
	 * @param string $role   The role to create as.
	 * @param string $author The author, as a fixture name.
	 */
	public function test_naming_an_author_the_role_may_name( string $role, string $author ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data' => array(
					'type'   => 'post',
					'title'  => 'Named author',
					'author' => self::$user_ids[ $author ],
				),
			)
		);

		$this->assertSame( self::$user_ids[ $author ], (int) $this->get_created_post( $result )->post_author, 'The named user should be the author.' );
	}

	/**
	 * Returns attributes that are invalid for the user or after sanitization: the role, the data,
	 * the invalid attribute and the expected error message.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>, 2: string, 3: string}> The cases.
	 */
	public static function data_invalid_attributes(): array {
		return array(
			'missing author named with edit_others_posts'  => array(
				'editor',
				array(
					'type'   => 'post',
					'title'  => 'Nobody',
					'author' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
				),
				'author',
				'The author must be an existing user of this site.',
			),
			'slug that sanitizes to nothing'               => array(
				'administrator',
				array(
					'type'  => 'post',
					'title' => 'Slug',
					'slug'  => '!!!',
				),
				'slug',
				'The slug must contain characters that are valid in a slug.',
			),
			'empty slug'                                   => array(
				'administrator',
				array(
					'type'  => 'post',
					'title' => 'Slug',
					'slug'  => '',
				),
				'slug',
				'The slug must contain characters that are valid in a slug.',
			),
			// Intentionally strict: the save would drop the slug, so it is an error instead of being dropped without notice.
			'slug of a pending post without publish_posts' => array(
				'contributor',
				array(
					'type'   => 'post',
					'title'  => 'Pending slug',
					'status' => 'pending',
					'slug'   => 'pending-slug',
				),
				'slug',
				'The slug of a pending post can only be set by a user who can publish the post.',
			),
		);
	}

	/**
	 * A missing author named by a user with `edit_others_posts`, a slug that sanitizes to nothing
	 * and the slug of a pending post set by a user who may not publish posts are invalid attributes.
	 *
	 * @ticket 66706
	 * @dataProvider data_invalid_attributes
	 * @since 7.3.0
	 *
	 * @param string               $role      The role to create as.
	 * @param array<string, mixed> $data      The data.
	 * @param string               $attribute The invalid attribute.
	 * @param string               $message   The expected error message.
	 */
	public function test_invalid_attribute_is_rejected( string $role, array $data, string $attribute, string $message ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability( array( 'data' => $data ) );

		$this->assert_invalid_attribute( $result, $attribute, "An invalid {$attribute}", $message );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * On multisite, a user who is not a member of the site cannot be the author.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
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

		$this->assert_invalid_attribute( $result, 'author', 'An author who is not a member of the site', 'The author must be an existing user of this site.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * On multisite, a user without `edit_others_posts` who names a user who is not a member of the
	 * site gets the same error as for a missing user.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
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
			'missing'    => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
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

		$this->assertSame( 'ability_invalid_permissions', $errors['missing'][0], 'The permission callback should refuse the call.' );
		$this->assertSame( $errors['missing'], $errors['non-member'], 'The error should be the same for a non-member and a missing user.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written for either user.' );
	}

	/**
	 * Returns parents that a page may not have.
	 *
	 * @return array<string, array{0: string}> The parents, as fixture names.
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
	 * A missing parent, a parent of another type, a parent the user may not read and a revision
	 * give one identical error, so the error never tells whether a post exists.
	 *
	 * @ticket 66706
	 * @dataProvider data_parents_that_cannot_be_used
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

		// The reference is another missing ID, so the `missing post` row is compared with a different input too.
		$before    = $this->snapshot();
		$reference = $create( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER + 1 );
		$result    = $create( self::$post_ids[ $parent_key ] ?? REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );

		$this->assert_invalid_attribute( $result, 'parent', 'A parent that cannot be used' );
		$this->assertWPError( $reference, 'Another missing parent should fail.' );
		$this->assertSame( $reference->get_error_data(), $result->get_error_data(), 'The error data, with its reason, should be the same as for another missing parent.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * A readable page of the same type can be the parent, and a parent of `0` is accepted.
	 *
	 * A new page is top level without a parent too, so for `0` only the acceptance and the
	 * returned value are checked. Moving a page to the top level is an update.
	 *
	 * @ticket 66706
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

		$this->assertSame( self::$post_ids['published_page'], $this->get_created_post( $child, 'The create call of the child page' )->post_parent, 'The parent should be stored.' );
		$this->assertSame( self::$post_ids['published_page'], $child['parent'], 'The parent should be returned.' );
		$this->get_created_post( $top, 'The create call with a parent of 0' );
		$this->assertSame( 0, $top['parent'], 'A parent of 0 should be accepted and returned as 0.' );
	}

	/**
	 * Returns attributes that a post type does not support: the post type, the attribute, the
	 * value and, optionally, the expected error message. An author value is a user and a string
	 * parent value is a post, as fixture names.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed, 3?: string}> The cases.
	 */
	public static function data_unsupported_attributes(): array {
		return array(
			'excerpt of a page'                 => array( 'page', 'excerpt', 'An excerpt' ),
			'parent of a post'                  => array( 'post', 'parent', 1 ),
			'existing parent of a post'         => array( 'post', 'parent', 'published_post', 'The parent attribute is only supported for hierarchical post types.' ),
			'parent 0 of a post'                => array( 'post', 'parent', 0 ),
			'title without title support'       => array( 'cw_no_title', 'title', 'A title' ),
			'content without editor support'    => array( 'cw_title_only', 'content', 'Some content' ),
			'excerpt without excerpt support'   => array( 'cw_title_only', 'excerpt', 'An excerpt' ),
			'author without author support'     => array( 'cw_title_only', 'author', 'administrator' ),
			'empty title without title support' => array( 'cw_no_title', 'title', '' ),
		);
	}

	/**
	 * An attribute the post type does not support is rejected, even when it would change nothing.
	 *
	 * @ticket 66706
	 * @dataProvider data_unsupported_attributes
	 * @since 7.3.0
	 *
	 * @param string      $type      The post type.
	 * @param string      $attribute The attribute.
	 * @param mixed       $value     The value.
	 * @param string|null $message   Optional. The expected error message. Default null.
	 */
	public function test_unsupported_attribute_is_rejected( string $type, string $attribute, $value, ?string $message = null ): void {
		$this->register_exposed_post_type( 'cw_no_title', array( 'editor' ) );
		$this->register_exposed_post_type( 'cw_title_only', array( 'title' ) );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		// The fixtures do not exist yet when the provider runs.
		if ( 'author' === $attribute ) {
			$value = self::$user_ids[ $value ];
		} elseif ( 'parent' === $attribute && is_string( $value ) ) {
			$value = self::$post_ids[ $value ];
		}

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

		$before = $this->snapshot();
		$result = $this->run_ability( array( 'data' => $data ) );

		$this->assert_invalid_attribute( $result, $attribute, 'An unsupported attribute', $message );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * An empty title is valid next to other content.
	 *
	 * @ticket 66706
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
	 * A database error during the save keeps its own code and message, with status 500.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_database_error_is_the_save_error(): void {
		global $wpdb;

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot();

		$suppress = $wpdb->suppress_errors();
		add_filter(
			'query',
			static function ( $query ) {
				return str_starts_with( $query, 'INSERT' ) ? '],' : $query;
			}
		);

		try {
			$result = $this->run_ability(
				array(
					'data' => array(
						'type'  => 'post',
						'title' => 'Database error',
					),
				)
			);
		} finally {
			$wpdb->suppress_errors( $suppress );
		}

		$this->assert_error( $result, 'db_insert_error', 500, 'A database error' );
		$this->assertSame( 'Could not insert post into the database.', $result->get_error_message(), 'The message should be the message of the save error.' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written after a database error.' );
	}

	/**
	 * The after-insert hooks fire once, as in any regular save.
	 *
	 * @ticket 66706
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
	 * @return array<string, array{0: string, 1: string}> The sent and the stored slugs.
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
	 * @ticket 66706
	 * @dataProvider data_slugs
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
	 * A user who may publish posts sets the slug of a pending post.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_slug_of_a_pending_post_is_stored_with_publish_posts(): void {
		$this->login_as( 'publisher' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'type'   => 'post',
					'title'  => 'Pending slug',
					'status' => 'pending',
					'slug'   => 'pending-slug',
				),
				'fields' => array( 'status', 'slug' ),
			)
		);

		$post = $this->get_created_post( $result );

		$this->assertSame( 'pending', $post->post_status, 'The post should be pending.' );
		$this->assertSame( 'pending-slug', $post->post_name, 'The slug of the pending post should be stored.' );
		$this->assertSame( 'pending-slug', $result['slug'], 'The slug of the pending post should be returned.' );
	}

	/**
	 * Returns statuses for which the slug of a published post is made unique.
	 *
	 * @return array<string, array{0: string|null}> The statuses, or null for the default.
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
	 * @ticket 66706
	 * @dataProvider data_statuses_with_unique_slugs
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
	 * Returns parents for a draft page with the slug of an existing child page, and the slug the
	 * page is stored with.
	 *
	 * @return array<string, array{0: string, 1: string}> The parents, as fixture names, and the slugs.
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
	 * @ticket 66706
	 * @dataProvider data_draft_page_slugs_by_parent
	 * @since 7.3.0
	 *
	 * @param string $parent_key The parent, as a fixture name.
	 * @param string $expected   The stored slug.
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
	 * Returns sent dates and the local and GMT dates they are stored as, in America/New_York.
	 *
	 * @return array<string, array{0: array<string, string>, 1: string, 2: string}> The cases.
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
	 * @ticket 66706
	 * @dataProvider data_dates
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
	 * Returns markup and the values it is stored and rendered as for a user without
	 * `unfiltered_html`: the role, the sent markup, the stored and the rendered title, and the
	 * stored and the rendered content and excerpt.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}> The cases.
	 */
	public static function data_filtered_markup(): array {
		$script = array(
			'<div>div</div> <strong>strong</strong> <script>oh noes</script>',
			'div <strong>strong</strong> ',
			'div <strong>strong</strong>',
			'<div>div</div> <strong>strong</strong> ',
			"<div>div</div>\n<p> <strong>strong</strong> </p>",
		);

		return array(
			'backslashes and non-ASCII text' => array(
				'author',
				'\o/ ¯\_(ツ)_/¯',
				'\o/ ¯\_(ツ)_/¯',
				'\o/ ¯\_(ツ)_/¯',
				'\o/ ¯\_(ツ)_/¯',
				'<p>\o/ ¯\_(ツ)_/¯</p>',
			),
			'entities'                       => array(
				'author',
				'\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'<p>\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;</p>',
			),
			'a script'                       => array_merge( array( 'author' ), $script ),
			'a script of a contributor'      => array_merge( array( 'contributor' ), $script ),
			'a link with extra attributes'   => array(
				'author',
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
	 * The markup of an author or a contributor, who lack `unfiltered_html`, is filtered as on any
	 * other save.
	 *
	 * @ticket 66706
	 * @dataProvider data_filtered_markup
	 * @since 7.3.0
	 *
	 * @param string $role           The role to create as.
	 * @param string $input          The sent markup.
	 * @param string $title_raw      The stored title.
	 * @param string $title_rendered The rendered title, trimmed.
	 * @param string $text_raw       The stored content and excerpt.
	 * @param string $text_rendered  The rendered content and excerpt, trimmed.
	 */
	public function test_markup_of_a_user_without_unfiltered_html_is_filtered( string $role, string $input, string $title_raw, string $title_rendered, string $text_raw, string $text_rendered ): void {
		$this->login_as( $role );

		$this->assertFalse( current_user_can( 'unfiltered_html' ), 'Precondition: the user should not have unfiltered_html.' );

		$result = $this->create_with_markup( $input );

		$this->assertSame( $title_raw, $result['title']['raw'], 'The stored title should be filtered.' );
		$this->assertSame( $title_rendered, trim( $result['title']['rendered'] ), 'The rendered title should be filtered.' );
		$this->assertSame( $text_raw, $result['content']['raw'], 'The stored content should be filtered.' );
		$this->assertSame( $text_rendered, trim( $result['content']['rendered'] ), 'The rendered content should be filtered.' );
		$this->assertSame( $text_raw, $result['excerpt']['raw'], 'The stored excerpt should be filtered.' );
		$this->assertSame( $text_rendered, trim( $result['excerpt']['rendered'] ), 'The rendered excerpt should be filtered.' );
	}

	/**
	 * On multisite, the markup of a site administrator, who lacks `unfiltered_html`, is filtered.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
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
	 * @group ms-excluded
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_markup_of_an_administrator_is_not_filtered(): void {
		$this->login_as( 'administrator' );

		$this->assert_markup_is_stored_unchanged();
	}

	/**
	 * On multisite, the markup of a super admin is stored unchanged.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_markup_of_a_super_admin_is_not_filtered(): void {
		$super_admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin );
		wp_set_current_user( $super_admin );

		$this->assert_markup_is_stored_unchanged();
	}

	/**
	 * Raw fields are left out when the writer cannot edit the post after the write.
	 *
	 * The user may publish posts but not edit published posts.
	 *
	 * @ticket 66706
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
		$this->assertFalse( current_user_can( 'edit_post', $post->ID ), 'The user should not be able to edit the published post.' );
		$this->assertSame( array( 'rendered' => 'Published once' ), $result['title'], 'The title should be returned without its raw value.' );
		$this->assertSame( array( 'rendered' => "<p>Body.</p>\n" ), $result['content'], 'The content should be returned without its raw value.' );
	}

	/**
	 * The current user is checked at each call, also on the same instance.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_each_call_checks_the_current_user(): void {
		$this->register_abilities();
		$input = array(
			'data' => array(
				'type'  => 'post',
				'title' => 'Twice',
			),
		);

		$this->login_as( 'author' );
		$first = $this->run_ability( $input );

		$this->login_as( 'subscriber' );
		$before = $this->snapshot();
		$second = $this->run_ability( $input );

		$this->assertSame( self::$user_ids['author'], (int) $this->get_created_post( $first )->post_author, 'The first call should create a post for the author.' );
		$this->assert_refused( $second, 'The second call as a subscriber' );
		$this->assertSame( $before, $this->snapshot(), 'The second call should write nothing.' );
	}

	/**
	 * Only the requested fields are returned, and the ID always is.
	 *
	 * @ticket 66706
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
	 * A type that gets `show_in_abilities` after the abilities were registered is not in the type
	 * enum, so a create of that type fails input validation.
	 *
	 * @ticket 66706
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
	 * @ticket 66706
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

		$this->assert_refused( $published, 'Publishing with only the create capability' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written for the refused publish.' );
	}

	/**
	 * A user with only the edit capability of a type, and not its create capability, may not
	 * create a post of it.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_user_with_only_the_edit_capability_cannot_create(): void {
		$this->register_type_with_its_own_create_capability();

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( $user_id )->add_cap( 'edit_cw_books' );
		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'create_cw_books' ), 'Precondition: the user should not have the create capability.' );

		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'cw_book',
					'title' => 'Book',
				),
			)
		);

		$this->assert_refused( $result, 'A user with only the edit capability' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should be written.' );
	}

	/**
	 * On multisite, after switching to a second site, a user who is an editor only on the first
	 * site may not create posts there.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_editor_of_the_first_site_only_is_refused_on_the_second_site(): void {
		$blog_id = self::factory()->blog->create();

		$this->assertFalse( is_user_member_of_blog( self::$user_ids['editor'], $blog_id ), 'Precondition: the editor should not be a member of the second site.' );

		$this->login_as( 'editor' );
		$first_before = $this->snapshot();

		switch_to_blog( $blog_id );
		$this->register_abilities();

		$second_before = $this->snapshot();
		$result        = $this->run_ability(
			array(
				'data' => array(
					'type'  => 'post',
					'title' => 'Second site',
				),
			)
		);
		$second_after  = $this->snapshot();

		restore_current_blog();

		$this->assert_refused( $result, 'A call on the second site' );
		$this->assertSame( $second_before, $second_after, 'Nothing should be written on the second site.' );
		$this->assertSame( $first_before, $this->snapshot(), 'Nothing should be written on the first site.' );
	}

	/**
	 * On multisite, after switching to a second site, an editor of that site who names a user who
	 * is a member only of the first site gets the invalid author error.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
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
	 * @ticket 66706
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
