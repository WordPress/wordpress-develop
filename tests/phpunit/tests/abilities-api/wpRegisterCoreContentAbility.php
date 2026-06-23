<?php

declare( strict_types=1 );

/**
 * Tests for the core/content ability shipped with the Abilities API.
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
	const HIDDEN_CPT = 'content_ability_hidden_cpt';

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
	 * Convenience accessor for the ability.
	 *
	 * @return WP_Ability The core/content ability.
	 */
	private function ability(): WP_Ability {
		return wp_get_ability( 'core/content' );
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

	public function test_input_schema_requires_id_or_post_type(): void {
		$schema = $this->ability()->get_input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertSame(
			array(
				array( 'required' => array( 'id' ) ),
				array( 'required' => array( 'post_type' ) ),
			),
			$schema['anyOf']
		);
		$this->assertFalse( $schema['additionalProperties'] );
	}

	public function test_input_schema_post_type_enum_only_includes_exposed_types(): void {
		$enum = $this->ability()->get_input_schema()['properties']['post_type']['enum'];

		$this->assertContains( 'post', $enum );
		$this->assertContains( 'page', $enum );
		$this->assertContains( self::EXPOSED_CPT, $enum );
		$this->assertNotContains( self::HIDDEN_CPT, $enum );
		$this->assertNotContains( 'revision', $enum );
	}

	public function test_input_schema_status_and_fields_enums(): void {
		$properties = $this->ability()->get_input_schema()['properties'];

		$status_enum = $properties['status']['items']['enum'];
		$this->assertContains( 'publish', $status_enum );
		$this->assertContains( 'draft', $status_enum );
		$this->assertContains( 'private', $status_enum );
		$this->assertNotContains( 'trash', $status_enum );
		$this->assertNotContains( 'auto-draft', $status_enum );
		$this->assertSame( array( 'publish' ), $properties['status']['default'] );

		$fields_enum = $properties['fields']['items']['enum'];
		$this->assertContains( 'raw_content', $fields_enum );
		$this->assertContains( 'title', $fields_enum );
		$this->assertContains( 'author', $fields_enum );
	}

	public function test_output_schema_has_no_required_fields(): void {
		$schema    = $this->ability()->get_output_schema();
		$post_item = $schema['properties']['posts']['items'];

		$this->assertArrayNotHasKey( 'required', $post_item );
		$this->assertFalse( $post_item['additionalProperties'] );
		$this->assertArrayHasKey( 'raw_content', $post_item['properties'] );
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
		$this->assertCount( 1, $result['posts'] );
		$this->assertSame( $post_id, $result['posts'][0]['id'] );
		$this->assertSame( 'Hello Content', $result['posts'][0]['title'] );
		$this->assertSame( 'Body here.', $result['posts'][0]['raw_content'] );
		$this->assertSame( 'post', $result['posts'][0]['type'] );
	}

	public function test_get_by_id_with_mismatched_post_type_returns_not_found(): void {
		$this->login_as( 'administrator' );
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$result = $this->ability()->execute(
			array(
				'id'        => $post_id,
				'post_type' => 'page',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'content_not_found', $result->get_error_code() );
	}

	public function test_get_by_missing_id_returns_generic_not_found(): void {
		$this->login_as( 'administrator' );

		$result = $this->ability()->execute( array( 'id' => 999999 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'content_not_found', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
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

	public function test_query_by_slug_requires_post_type(): void {
		$this->login_as( 'administrator' );

		$result = $this->ability()->execute( array( 'slug' => 'whatever' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_query_by_slug_within_post_type(): void {
		$this->login_as( 'administrator' );
		$post_id = self::factory()->post->create(
			array(
				'post_name'   => 'find-me',
				'post_status' => 'publish',
			)
		);

		$result = $this->ability()->execute(
			array(
				'post_type' => 'post',
				'slug'      => 'find-me',
			)
		);

		$this->assertCount( 1, $result['posts'] );
		$this->assertSame( $post_id, $result['posts'][0]['id'] );
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
				'fields' => array( 'id', 'title' ),
			)
		);

		$this->assertSame( array( 'id', 'title' ), array_keys( $result['posts'][0] ) );
	}

	public function test_unsupported_fields_are_omitted_for_post_type(): void {
		$this->login_as( 'administrator' );
		// Pages do not support excerpt by default in this CPT, but `post` does; use the
		// exposed CPT which does not support `comments`/`parent` to confirm omission.
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$result = $this->ability()->execute( array( 'id' => $post_id ) );

		// `post` is not hierarchical, so `parent` must be absent even though requested implicitly.
		$this->assertArrayNotHasKey( 'parent', $result['posts'][0] );
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

	public function test_subscriber_can_read_published_posts(): void {
		$published = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->login_as( 'subscriber' );

		$result = $this->ability()->execute( array( 'post_type' => 'post' ) );
		$ids    = wp_list_pluck( $result['posts'], 'id' );

		$this->assertContains( $published, $ids );
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

	public function test_administrator_can_read_private_posts(): void {
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

	public function test_password_protected_content_withheld_from_non_editor(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_content'  => 'Top secret body.',
				'post_excerpt'  => 'Secret excerpt.',
			)
		);

		$this->login_as( 'subscriber' );
		$result = $this->ability()->execute(
			array(
				'id'     => $post_id,
				'fields' => array( 'id', 'raw_content', 'excerpt' ),
			)
		);

		$this->assertSame( '', $result['posts'][0]['raw_content'] );
		$this->assertSame( '', $result['posts'][0]['excerpt'] );
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
				'fields' => array( 'id', 'raw_content' ),
			)
		);

		$this->assertSame( 'Top secret body.', $result['posts'][0]['raw_content'] );
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

		$schema = $this->ability()->get_input_schema();

		$this->assertSame( 100, $schema['properties']['per_page']['maximum'] );
		$this->assertSame( 10, $schema['properties']['per_page']['default'] );
	}

	public function test_single_post_reports_totals(): void {
		$this->login_as( 'administrator' );
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$result = $this->ability()->execute( array( 'id' => $post_id ) );

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( 1, $result['total_pages'] );
	}

	public function test_ability_opts_into_pagination(): void {
		$this->assertTrue( (bool) $this->ability()->get_meta_item( 'pagination', false ) );
	}
}
