<?php

declare( strict_types=1 );

/**
 * Tests for the core/read-content ability shipped with the Abilities API.
 *
 * @covers wp_register_core_abilities
 * @covers WP_Content_Abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreContentAbility extends WP_UnitTestCase {

	/**
	 * An exposed custom post type used to verify post-type-agnostic behavior.
	 *
	 * @var string
	 */
	const EXPOSED_CPT = 'content_ability_cpt';

	/**
	 * A custom post type that is NOT exposed to abilities.
	 *
	 * @var string
	 */
	const HIDDEN_CPT = 'content_hidden_cpt';

	/**
	 * Registers post types and the core abilities once, before the schema is built.
	 *
	 * The ability builds its `post_type`/`status`/`fields` schema at registration time,
	 * so any custom post type must be registered before the abilities are registered.
	 *
	 * @since 7.1.0
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		register_post_type(
			self::EXPOSED_CPT,
			array(
				'public'            => true,
				'show_in_abilities' => true,
				'supports'          => array( 'title', 'editor', 'excerpt', 'author' ),
			)
		);

		register_post_type(
			self::HIDDEN_CPT,
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor' ),
			)
		);

		// Temporarily remove the unhook functions so we can register core abilities.
		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
	}

	/**
	 * Cleans up registered abilities, categories, and post types.
	 *
	 * @since 7.1.0
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

		unregister_post_type( self::EXPOSED_CPT );
		unregister_post_type( self::HIDDEN_CPT );

		parent::tear_down_after_class();
	}

	/**
	 * Logs in as a user with the given role and returns the user ID.
	 *
	 * @param string $role The role to create the user with.
	 * @return int The new user ID.
	 */
	private function login_as( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
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
	 * Convenience accessor for the ability.
	 *
	 * @return WP_Ability The core/read-content ability.
	 */
	private function ability(): WP_Ability {
		return wp_get_ability( 'core/read-content' );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Registration & schema
	 * -------------------------------------------------------------------------
	 */

	public function test_ability_is_registered_readonly_in_content_category(): void {
		$ability = $this->ability();

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( 'content', $ability->get_category() );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ) );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}

	public function test_input_schema_models_mutually_exclusive_modes(): void {
		$schema = $this->ability()->get_input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertCount( 3, $schema['oneOf'] );

		[ $by_id, $by_slug, $query ] = $schema['oneOf'];

		$this->assertSame( array( 'id' ), $by_id['required'] );
		$this->assertSame( array( 'post_type', 'slug' ), $by_slug['required'] );
		$this->assertSame( array( 'post_type' ), $query['required'] );
		$this->assertFalse( $by_id['additionalProperties'] );
		$this->assertFalse( $by_slug['additionalProperties'] );
		$this->assertFalse( $query['additionalProperties'] );

		// Query-only filters live only in the query mode, not the single-post modes.
		$this->assertArrayHasKey( 'include', $query['properties'] );
		$this->assertArrayHasKey( 'per_page', $query['properties'] );
		$this->assertArrayNotHasKey( 'per_page', $by_id['properties'] );
		$this->assertArrayNotHasKey( 'include', $by_slug['properties'] );
		$this->assertArrayNotHasKey( 'slug', $query['properties'] );

		$this->assertContains( 'post', $query['properties']['post_type']['enum'] );
		$this->assertContains( 'page', $by_id['properties']['post_type']['enum'] );
		$this->assertContains( 'page', $by_slug['properties']['post_type']['enum'] );

		$this->assertSame( 1, $query['properties']['include']['minItems'] );
		$this->assertTrue( $query['properties']['include']['uniqueItems'] );
		$this->assertSame( 'integer', $query['properties']['include']['items']['type'] );
		$this->assertSame( 1, $query['properties']['include']['items']['minimum'] );
	}

	public function test_id_mode_rejects_query_only_params(): void {
		$this->login_as( 'administrator' );

		$result = $this->ability()->execute(
			array(
				'id'       => 1,
				'per_page' => 10,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_id_mode_accepts_post_type_guard(): void {
		$this->login_as( 'administrator' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$result = $this->ability()->execute(
			array(
				'id'        => $post_id,
				'post_type' => 'post',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( $post_id, $result['id'] );
		$this->assertArrayNotHasKey( 'posts', $result );
	}

	public function test_input_schema_post_type_enum_only_includes_exposed_types(): void {
		$enum = $this->ability()->get_input_schema()['oneOf'][2]['properties']['post_type']['enum'];

		$this->assertContains( 'post', $enum );
		$this->assertContains( 'page', $enum );
		$this->assertContains( self::EXPOSED_CPT, $enum );
		$this->assertNotContains( self::HIDDEN_CPT, $enum );
		$this->assertNotContains( 'revision', $enum );
	}

	public function test_query_exposed_custom_post_type(): void {
		$this->login_as( 'administrator' );

		if ( ! post_type_exists( self::EXPOSED_CPT ) ) {
			register_post_type(
				self::EXPOSED_CPT,
				array(
					'public'            => true,
					'show_in_abilities' => true,
					'supports'          => array( 'title', 'editor', 'excerpt', 'author' ),
				)
			);
		}

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => self::EXPOSED_CPT,
				'post_status' => 'publish',
			)
		);

		$result = $this->ability()->execute( array( 'post_type' => self::EXPOSED_CPT ) );
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $post_id, $ids );
	}

	public function test_input_schema_status_and_fields_enums(): void {
		$properties = $this->ability()->get_input_schema()['oneOf'][2]['properties'];

		$status_enum = $properties['status']['items']['enum'];
		$this->assertContains( 'publish', $status_enum );
		$this->assertContains( 'draft', $status_enum );
		$this->assertContains( 'private', $status_enum );
		$this->assertNotContains( 'trash', $status_enum );
		$this->assertNotContains( 'auto-draft', $status_enum );

		$fields_enum = $properties['fields']['items']['enum'];
		$this->assertContains( 'content_raw', $fields_enum );
		$this->assertContains( 'content_rendered', $fields_enum );
		$this->assertContains( 'title_raw', $fields_enum );
		$this->assertContains( 'title_rendered', $fields_enum );
		$this->assertContains( 'author', $fields_enum );
	}

	public function test_input_schema_omits_oneof_branch_defaults(): void {
		$properties = $this->ability()->get_input_schema()['oneOf'][2]['properties'];

		$this->assertArrayNotHasKey( 'default', $properties['status'] );
		$this->assertArrayNotHasKey( 'default', $properties['page'] );
		$this->assertArrayNotHasKey( 'default', $properties['per_page'] );
	}

	public function test_output_schema_describes_single_post_and_query_responses(): void {
		$schema       = $this->ability()->get_output_schema();
		$post_schema  = $schema['oneOf'][0];
		$query_schema = $schema['oneOf'][1];

		$this->assertSame( 'object', $schema['type'] );
		$this->assertCount( 2, $schema['oneOf'] );
		$this->assertSame( 'object', $post_schema['type'] );
		$this->assertArrayNotHasKey( 'required', $post_schema );
		$this->assertFalse( $post_schema['additionalProperties'] );
		$this->assertArrayHasKey( 'content_raw', $post_schema['properties'] );
		$this->assertArrayHasKey( 'content_rendered', $post_schema['properties'] );
		$this->assertSame( array( 'posts', 'total', 'total_pages' ), $query_schema['required'] );
		$this->assertArrayHasKey( 'total', $query_schema['properties'] );
		$this->assertArrayHasKey( 'total_pages', $query_schema['properties'] );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Single-post retrieval
	 * -------------------------------------------------------------------------
	 */

	public function test_get_single_published_post_by_id(): void {
		$this->login_as( 'administrator' );
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hello Content',
				'post_content' => 'Body here.',
				'post_status'  => 'publish',
			)
		);

		$result = $this->ability()->execute( array( 'id' => $post_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( $post_id, $result['id'] );
		$this->assertSame( 'Hello Content', $result['title_rendered'] );
		$this->assertSame(
			array( 'id', 'type', 'status', 'date', 'slug', 'title_rendered' ),
			array_keys( $result )
		);
		$this->assertArrayNotHasKey( 'posts', $result );
	}

	public function test_get_by_id_with_mismatched_post_type_is_denied(): void {
		$this->login_as( 'administrator' );
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$result = $this->ability()->execute(
			array(
				'id'        => $post_id,
				'post_type' => 'page',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_get_by_missing_id_is_denied(): void {
		$this->login_as( 'administrator' );

		$result = $this->ability()->execute( array( 'id' => 999999 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_get_by_id_for_unexposed_post_type_is_denied(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => self::HIDDEN_CPT,
				'post_status' => 'publish',
			)
		);

		$this->login_as( 'administrator' );

		$result = $this->ability()->execute( array( 'id' => $post_id ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Query mode
	 * -------------------------------------------------------------------------
	 */

	public function test_query_returns_only_published_by_default(): void {
		$this->login_as( 'administrator' );
		$published = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$result = $this->ability()->execute( array( 'post_type' => 'post' ) );
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $published, $ids );
		$this->assertNotContains( $draft, $ids );
	}

	public function test_query_include_limits_results_and_preserves_order(): void {
		$this->login_as( 'administrator' );

		$first  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$second = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$third  = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'include'   => array( $third, $first ),
				'fields'    => array( 'id' ),
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertSame( array( $third, $first ), $ids );
		$this->assertNotContains( $second, $ids );
	}

	public function test_query_include_respects_requested_post_type(): void {
		$this->login_as( 'administrator' );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'include'   => array( $page_id, $post_id ),
				'fields'    => array( 'id' ),
			)
		);

		$this->assertSame( array( $post_id ), wp_list_pluck( $result['posts'], 'id' ) );
	}

	public function test_query_include_respects_row_level_permissions(): void {
		$author_a = self::factory()->user->create( array( 'role' => 'author' ) );
		$author_b = self::factory()->user->create( array( 'role' => 'author' ) );

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
		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'draft' ),
				'include'   => array( $draft_a, $draft_b ),
				'fields'    => array( 'id' ),
			)
		);

		$this->assertSame( array( $draft_b ), wp_list_pluck( $result['posts'], 'id' ) );
	}

	public function test_slug_mode_requires_post_type(): void {
		$this->login_as( 'administrator' );

		$result = $this->ability()->execute( array( 'slug' => 'whatever' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_get_single_published_post_by_slug(): void {
		$this->login_as( 'administrator' );
		$post_id = self::factory()->post->create(
			array(
				'post_name'   => 'content-slug-mode',
				'post_title'  => 'Content Slug Mode',
				'post_status' => 'publish',
			)
		);

		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'content-slug-mode',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( $post_id, $result['id'] );
		$this->assertSame( 'content-slug-mode', $result['slug'] );
		$this->assertArrayNotHasKey( 'posts', $result );
		$this->assertArrayNotHasKey( 'total', $result );
	}

	public function test_slug_mode_rejects_query_only_params(): void {
		$this->login_as( 'administrator' );

		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'whatever',
				'per_page'  => 10,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_include_cannot_be_combined_with_single_post_modes(): void {
		$this->login_as( 'administrator' );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$by_id = $this->ability()->execute(
			array(
				'id'      => $post_id,
				'include' => array( $post_id ),
			)
		);
		$by_slug = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'whatever',
				'include'   => array( $post_id ),
			)
		);

		$this->assertWPError( $by_id );
		$this->assertSame( 'ability_invalid_input', $by_id->get_error_code() );
		$this->assertWPError( $by_slug );
		$this->assertSame( 'ability_invalid_input', $by_slug->get_error_code() );
	}

	public function test_query_filters_by_author(): void {
		$author_a = self::factory()->user->create( array( 'role' => 'author' ) );
		$author_b = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_a   = self::factory()->post->create(
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
		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'author'    => $author_a,
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertSame( array( $post_a ), $ids );
	}

	public function test_query_filters_by_parent_for_hierarchical_types(): void {
		$this->login_as( 'administrator' );
		$parent = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$child  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent,
				'post_status' => 'publish',
			)
		);

		$result = $this->ability()->execute(
			array(
				'post_type' => 'page',
				'parent'    => $parent,
				'fields'    => array( 'id', 'parent' ),
			)
		);

		$this->assertCount( 1, $result['posts'] );
		$this->assertSame( $child, $result['posts'][0]['id'] );
		$this->assertSame( $parent, $result['posts'][0]['parent'] );
	}

	/*
	 * -------------------------------------------------------------------------
	 * fields filter
	 * -------------------------------------------------------------------------
	 */

	public function test_fields_filter_limits_returned_keys(): void {
		$this->login_as( 'administrator' );
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$result = $this->ability()->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'title_rendered' ),
			)
		);

		$this->assertSame( array( 'id', 'title_rendered' ), array_keys( $result ) );
	}

	public function test_unsupported_fields_are_omitted_for_post_type(): void {
		$this->login_as( 'administrator' );
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$result = $this->ability()->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'parent' ),
			)
		);

		// `post` is not hierarchical, so `parent` must be absent even when requested.
		$this->assertArrayNotHasKey( 'parent', $result );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Permissions & visibility (security)
	 * -------------------------------------------------------------------------
	 */

	public function test_logged_out_user_is_denied(): void {
		wp_set_current_user( 0 );

		$result = $this->ability()->execute( array( 'post_type' => 'post' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_subscriber_can_request_published_content(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Visible to subscribers',
				'post_content' => 'Rendered body for subscribers.',
				'post_status'  => 'publish',
			)
		);
		$this->login_as( 'subscriber' );

		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'fields'    => array( 'id', 'title_rendered', 'content_rendered' ),
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $post_id, $ids );
		$post_index = array_search( $post_id, $ids, true );
		$this->assertIsInt( $post_index );
		$post = $result['posts'][ $post_index ];
		$this->assertSame( 'Visible to subscribers', $post['title_rendered'] );
		$this->assertStringContainsString( 'Rendered body for subscribers.', $post['content_rendered'] );
		$this->assertArrayNotHasKey( 'content_raw', $post );
	}

	public function test_subscriber_can_get_single_published_post_by_id(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Readable single',
				'post_content' => 'Readable single body.',
				'post_status'  => 'publish',
			)
		);
		$this->login_as( 'subscriber' );

		$result = $this->ability()->execute( array( 'id' => $post_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( 'Readable single', $result['title_rendered'] );
		$this->assertArrayNotHasKey( 'title_raw', $result );
		$this->assertArrayNotHasKey( 'content_raw', $result );
		$this->assertArrayNotHasKey( 'content_rendered', $result );
	}

	public function test_subscriber_cannot_request_raw_fields_in_query_mode(): void {
		$this->login_as( 'subscriber' );

		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'fields'    => array( 'content_raw' ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_subscriber_cannot_request_raw_fields_for_single_post(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->login_as( 'subscriber' );

		$result = $this->ability()->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'content_raw' ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Users who cannot edit another user's post do not receive raw fields by default.
	 *
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 *
	 * @param string $role The role to test.
	 */
	public function test_default_fields_omit_raw_fields_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$post_owner_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id       = self::factory()->post->create(
			array(
				'post_author'  => $post_owner_id,
				'post_title'   => 'Readable title',
				'post_content' => 'Readable body for limited role.',
				'post_excerpt' => 'Readable excerpt.',
				'post_status'  => 'publish',
			)
		);

		$this->login_as( $role );

		$result = $this->ability()->execute( array( 'id' => $post_id ) );

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
		$post_owner_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id       = self::factory()->post->create(
			array(
				'post_author' => $post_owner_id,
				'post_status' => 'publish',
			)
		);

		$this->login_as( $role );

		$result = $this->ability()->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'content_raw' ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Raw field requests should require edit access to the post.' );
	}

	public function test_subscriber_cannot_request_draft_status(): void {
		$this->login_as( 'subscriber' );

		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'draft' ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_subscriber_cannot_request_private_status(): void {
		$this->login_as( 'subscriber' );

		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'private' ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_author_cannot_see_other_authors_drafts(): void {
		$author_a = self::factory()->user->create( array( 'role' => 'author' ) );
		$author_b = self::factory()->user->create( array( 'role' => 'author' ) );

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

		// Author B can pass the status gate (has edit_posts) but only sees their own draft.
		wp_set_current_user( $author_b );
		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'draft' ),
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $draft_b, $ids );
		$this->assertNotContains( $draft_a, $ids );
	}

	public function test_administrator_can_access_private_posts(): void {
		$private = self::factory()->post->create( array( 'post_status' => 'private' ) );
		$this->login_as( 'administrator' );

		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'status'    => array( 'private' ),
			)
		);
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $private, $ids );
	}

	public function test_unexposed_post_type_is_rejected_by_input_schema(): void {
		$this->login_as( 'administrator' );

		$result = $this->ability()->execute( array( 'post_type' => self::HIDDEN_CPT ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Password-protected posts
	 * -------------------------------------------------------------------------
	 */

	public function test_raw_content_visible_to_editor(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'Public body with raw block markup.',
			)
		);

		$this->login_as( 'editor' );
		$result = $this->ability()->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'content_raw' ),
			)
		);

		$this->assertSame( 'Public body with raw block markup.', $result['content_raw'] );
	}

	public function test_password_protected_content_visible_to_editor(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_content'  => 'Top secret body.',
			)
		);

		$this->login_as( 'editor' );
		$result = $this->ability()->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'content_raw', 'content_rendered' ),
			)
		);

		$this->assertSame( 'Top secret body.', $result['content_raw'] );
		$this->assertStringContainsString( 'Top secret body.', $result['content_rendered'] );
	}

	/**
	 * Password-protected rendered content is withheld from users who cannot edit the post.
	 *
	 * @dataProvider data_roles_without_edit_access_to_other_users_posts
	 *
	 * @param string $role The role to test.
	 */
	public function test_password_protected_rendered_content_is_empty_for_roles_without_edit_access_to_other_users_posts( string $role ): void {
		$post_owner_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id       = self::factory()->post->create(
			array(
				'post_author'   => $post_owner_id,
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_content'  => 'Hidden rendered body.',
			)
		);

		$this->login_as( $role );
		$result = $this->ability()->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'content_rendered', 'content_protected' ),
			)
		);

		$this->assertSame( '', $result['content_rendered'], 'Password-protected rendered content should be withheld.' );
		$this->assertTrue( $result['content_protected'], 'The protected flag should reveal the field is password-protected.' );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Pagination
	 * -------------------------------------------------------------------------
	 */

	public function test_query_paginates_and_reports_totals(): void {
		$this->login_as( 'administrator' );
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$page1 = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'per_page'  => 2,
				'page'      => 1,
			)
		);

		$this->assertCount( 2, $page1['posts'] );
		$this->assertGreaterThanOrEqual( 3, $page1['total'] );
		$this->assertSame( (int) ceil( $page1['total'] / 2 ), $page1['total_pages'] );

		$page2 = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'per_page'  => 2,
				'page'      => 2,
			)
		);

		$this->assertNotEmpty( $page2['posts'] );
		$this->assertSame( $page1['total'], $page2['total'] );
	}

	public function test_per_page_is_capped(): void {
		$this->login_as( 'administrator' );

		$schema = $this->ability()->get_input_schema()['oneOf'][2];

		$this->assertSame( 100, $schema['properties']['per_page']['maximum'] );
	}

	public function test_single_post_does_not_return_query_totals(): void {
		$this->login_as( 'administrator' );
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$result = $this->ability()->execute( array( 'id' => $post_id ) );

		$this->assertArrayNotHasKey( 'posts', $result );
		$this->assertArrayNotHasKey( 'total', $result );
		$this->assertArrayNotHasKey( 'total_pages', $result );
	}

	public function test_ability_opts_into_pagination(): void {
		$this->assertTrue( (bool) $this->ability()->get_meta_item( 'pagination', false ) );
	}
}
