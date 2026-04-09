<?php

declare( strict_types=1 );

/**
 * Tests for the core post-type get abilities shipped with the Abilities API.
 *
 * @covers wp_register_post_type_get_ability
 * @covers _wp_ability_format_post
 *
 * @group abilities-api
 * @group post-type-abilities
 *
 * @ticket 64606
 */
class Tests_Abilities_API_WpRegisterCorePostTypeAbilities extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static int $admin_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private static int $editor_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static int $subscriber_id;

	/**
	 * Set up before the class.
	 *
	 * @since 6.9.0
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::$editor_id     = self::factory()->user->create( array( 'role' => 'editor' ) );
		self::$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Ensure core abilities are registered for these tests.
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
	 * Resets the current user after each test.
	 */
	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// show_in_abilities post type argument.
	// -------------------------------------------------------------------------

	/**
	 * Tests that 'post' has show_in_abilities enabled by default.
	 *
	 * @ticket 64606
	 */
	public function test_builtin_post_type_post_has_show_in_abilities_enabled(): void {
		$post_type = get_post_type_object( 'post' );

		$this->assertInstanceOf( WP_Post_Type::class, $post_type );
		$this->assertTrue( $post_type->show_in_abilities );
	}

	/**
	 * Tests that 'page' has show_in_abilities enabled by default.
	 *
	 * @ticket 64606
	 */
	public function test_builtin_post_type_page_has_show_in_abilities_enabled(): void {
		$post_type = get_post_type_object( 'page' );

		$this->assertInstanceOf( WP_Post_Type::class, $post_type );
		$this->assertTrue( $post_type->show_in_abilities );
	}

	/**
	 * Tests that other built-in post types do not expose abilities by default.
	 *
	 * @ticket 64606
	 *
	 * @dataProvider data_builtin_post_types_without_abilities
	 *
	 * @param string $post_type_slug Post type slug.
	 */
	public function test_other_builtin_post_types_have_show_in_abilities_disabled( string $post_type_slug ): void {
		$post_type = get_post_type_object( $post_type_slug );

		$this->assertInstanceOf( WP_Post_Type::class, $post_type );
		$this->assertFalse( $post_type->show_in_abilities );
	}

	/**
	 * Data provider for built-in post types that should not have abilities.
	 *
	 * @return array[]
	 */
	public function data_builtin_post_types_without_abilities(): array {
		return array(
			'attachment'    => array( 'attachment' ),
			'revision'      => array( 'revision' ),
			'nav_menu_item' => array( 'nav_menu_item' ),
		);
	}

	/**
	 * Tests that custom post types default to show_in_abilities = false.
	 *
	 * @ticket 64606
	 */
	public function test_custom_post_type_defaults_show_in_abilities_to_false(): void {
		$post_type_slug = 'cpt-no-ab';
		register_post_type( $post_type_slug, array( 'public' => true ) );

		$post_type = get_post_type_object( $post_type_slug );
		$this->assertFalse( $post_type->show_in_abilities );

		unregister_post_type( $post_type_slug );
	}

	/**
	 * Tests that a custom post type can opt in to show_in_abilities.
	 *
	 * @ticket 64606
	 */
	public function test_custom_post_type_can_opt_in_to_show_in_abilities(): void {
		$post_type_slug = 'cpt-with-ab';
		register_post_type(
			$post_type_slug,
			array(
				'public'            => true,
				'show_in_abilities' => true,
			)
		);

		$post_type = get_post_type_object( $post_type_slug );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type );
		$this->assertTrue( $post_type->show_in_abilities );

		unregister_post_type( $post_type_slug );
	}

	// -------------------------------------------------------------------------
	// Ability registration.
	// -------------------------------------------------------------------------

	/**
	 * Tests that the post get ability is registered.
	 *
	 * @ticket 64606
	 */
	public function test_core_post_get_ability_is_registered(): void {
		$this->assertTrue( wp_has_ability( 'core/post-type/post/get' ) );
	}

	/**
	 * Tests that the page get ability is registered.
	 *
	 * @ticket 64606
	 */
	public function test_core_page_get_ability_is_registered(): void {
		$this->assertTrue( wp_has_ability( 'core/post-type/page/get' ) );
	}

	/**
	 * Tests that attachment does not have a get ability registered.
	 *
	 * @ticket 64606
	 */
	public function test_attachment_get_ability_is_not_registered(): void {
		$this->assertFalse( wp_has_ability( 'core/post-type/attachment/get' ) );
	}

	/**
	 * Tests the post get ability is marked as readonly in its annotations.
	 *
	 * @ticket 64606
	 */
	public function test_post_get_ability_is_readonly(): void {
		$ability     = wp_get_ability( 'core/post-type/post/get' );
		$annotations = $ability->get_meta_item( 'annotations', array() );

		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}

	/**
	 * Tests the post get ability is exposed via the REST API.
	 *
	 * @ticket 64606
	 */
	public function test_post_get_ability_is_shown_in_rest(): void {
		$ability = wp_get_ability( 'core/post-type/post/get' );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ) );
	}

	/**
	 * Tests the post get ability belongs to the content category.
	 *
	 * @ticket 64606
	 */
	public function test_post_get_ability_belongs_to_content_category(): void {
		$ability = wp_get_ability( 'core/post-type/post/get' );
		$this->assertSame( 'content', $ability->get_category() );
	}

	/**
	 * Tests that the content category is registered.
	 *
	 * @ticket 64606
	 */
	public function test_content_ability_category_is_registered(): void {
		$this->assertTrue( wp_has_ability_category( 'content' ) );
	}

	// -------------------------------------------------------------------------
	// Permission checks.
	// -------------------------------------------------------------------------

	/**
	 * Tests that the post get ability requires authentication.
	 *
	 * @ticket 64606
	 */
	public function test_post_get_ability_requires_authentication(): void {
		wp_set_current_user( 0 );

		$ability = wp_get_ability( 'core/post-type/post/get' );

		$this->assertFalse( $ability->check_permissions() );
	}

	/**
	 * Tests that a subscriber can use the post get ability.
	 *
	 * @ticket 64606
	 */
	public function test_subscriber_can_use_post_get_ability(): void {
		wp_set_current_user( self::$subscriber_id );

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$this->assertTrue( $ability->check_permissions() );
	}

	// -------------------------------------------------------------------------
	// Single post retrieval by ID.
	// -------------------------------------------------------------------------

	/**
	 * Tests that a published post can be retrieved by ID.
	 *
	 * @ticket 64606
	 */
	public function test_get_published_post_by_id(): void {
		wp_set_current_user( self::$subscriber_id );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hello World',
				'post_content' => 'Test content.',
				'post_status'  => 'publish',
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'id' => $post_id ) );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['posts'] );
		$this->assertSame( 1, $result['total'] );
		$this->assertSame( 1, $result['pages'] );

		$post = $result['posts'][0];
		$this->assertSame( $post_id, $post['id'] );
		$this->assertSame( 'Hello World', $post['title'] );
		$this->assertSame( 'Test content.', $post['content'] );
		$this->assertSame( 'publish', $post['status'] );
		$this->assertSame( 'post', $post['type'] );
		$this->assertArrayHasKey( 'date', $post );
		$this->assertArrayHasKey( 'modified', $post );
		$this->assertArrayHasKey( 'slug', $post );
		$this->assertArrayHasKey( 'link', $post );
		$this->assertArrayHasKey( 'comment_status', $post );
		$this->assertArrayHasKey( 'ping_status', $post );
		$this->assertArrayHasKey( 'parent', $post );
	}

	/**
	 * Tests that requesting a non-existent post ID returns a WP_Error.
	 *
	 * @ticket 64606
	 */
	public function test_get_non_existent_post_by_id_returns_error(): void {
		wp_set_current_user( self::$subscriber_id );

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'id' => 999999 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_post_not_found', $result->get_error_code() );
	}

	/**
	 * Tests that requesting a post of the wrong type returns a WP_Error.
	 *
	 * @ticket 64606
	 */
	public function test_get_post_with_wrong_type_returns_error(): void {
		wp_set_current_user( self::$subscriber_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'id' => $page_id ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_post_not_found', $result->get_error_code() );
	}

	/**
	 * Tests that a private post cannot be read by a subscriber.
	 *
	 * @ticket 64606
	 */
	public function test_get_private_post_by_id_denied_for_subscriber(): void {
		wp_set_current_user( self::$subscriber_id );

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'private',
				'post_author' => self::$admin_id,
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'id' => $post_id ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_cannot_read_post', $result->get_error_code() );
	}

	/**
	 * Tests that an administrator can read a private post by ID.
	 *
	 * @ticket 64606
	 */
	public function test_get_private_post_by_id_allowed_for_admin(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create( array( 'post_status' => 'private' ) );

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'id' => $post_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( $post_id, $result['posts'][0]['id'] );
	}

	// -------------------------------------------------------------------------
	// Multi-post querying.
	// -------------------------------------------------------------------------

	/**
	 * Tests that multi-post query returns published posts by default.
	 *
	 * @ticket 64606
	 */
	public function test_multi_post_query_returns_published_posts(): void {
		wp_set_current_user( self::$subscriber_id );

		$published_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_type'   => 'post',
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'pages', $result );

		$returned_ids = array_column( $result['posts'], 'id' );
		$this->assertContains( $published_id, $returned_ids );

		// Drafts should not appear in default query.
		foreach ( $result['posts'] as $post ) {
			$this->assertSame( 'publish', $post['status'] );
		}
	}

	/**
	 * Tests that per_page limits the number of returned posts.
	 *
	 * @ticket 64606
	 */
	public function test_multi_post_query_respects_per_page(): void {
		wp_set_current_user( self::$subscriber_id );

		self::factory()->post->create_many(
			5,
			array(
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'per_page' => 2 ) );

		$this->assertCount( 2, $result['posts'] );
	}

	/**
	 * Tests that per_page values above 100 are rejected by schema validation.
	 *
	 * @ticket 64606
	 */
	public function test_multi_post_query_rejects_per_page_above_100(): void {
		wp_set_current_user( self::$subscriber_id );

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'per_page' => 999 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Tests that search filter narrows results.
	 *
	 * @ticket 64606
	 */
	public function test_multi_post_query_search_filter(): void {
		wp_set_current_user( self::$subscriber_id );

		$match_id    = self::factory()->post->create(
			array(
				'post_title'  => 'Unique Ability Test Post',
				'post_status' => 'publish',
			)
		);
		$no_match_id = self::factory()->post->create(
			array(
				'post_title'  => 'Different Title',
				'post_status' => 'publish',
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'search' => 'Unique Ability Test' ) );

		$returned_ids = array_column( $result['posts'], 'id' );
		$this->assertContains( $match_id, $returned_ids );
		$this->assertNotContains( $no_match_id, $returned_ids );
	}

	/**
	 * Tests that author filter works correctly.
	 *
	 * @ticket 64606
	 */
	public function test_multi_post_query_author_filter(): void {
		wp_set_current_user( self::$subscriber_id );

		$author_post_id = self::factory()->post->create(
			array(
				'post_author' => self::$editor_id,
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$admin_id,
				'post_status' => 'publish',
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'author' => self::$editor_id ) );

		$returned_ids = array_column( $result['posts'], 'id' );
		$this->assertContains( $author_post_id, $returned_ids );

		foreach ( $result['posts'] as $post ) {
			$this->assertSame( self::$editor_id, $post['author'] );
		}
	}

	/**
	 * Tests that comment_status filter works correctly.
	 *
	 * @ticket 64606
	 */
	public function test_multi_post_query_comment_status_filter(): void {
		wp_set_current_user( self::$subscriber_id );

		$open_id   = self::factory()->post->create(
			array(
				'comment_status' => 'open',
				'post_status'    => 'publish',
			)
		);
		$closed_id = self::factory()->post->create(
			array(
				'comment_status' => 'closed',
				'post_status'    => 'publish',
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'comment_status' => 'open' ) );

		$returned_ids = array_column( $result['posts'], 'id' );
		$this->assertContains( $open_id, $returned_ids );
		$this->assertNotContains( $closed_id, $returned_ids );
	}

	/**
	 * Tests that ping_status filter works correctly.
	 *
	 * @ticket 64606
	 */
	public function test_multi_post_query_ping_status_filter(): void {
		wp_set_current_user( self::$subscriber_id );

		$open_id   = self::factory()->post->create(
			array(
				'ping_status' => 'open',
				'post_status' => 'publish',
			)
		);
		$closed_id = self::factory()->post->create(
			array(
				'ping_status' => 'closed',
				'post_status' => 'publish',
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'ping_status' => 'closed' ) );

		$returned_ids = array_column( $result['posts'], 'id' );
		$this->assertContains( $closed_id, $returned_ids );
		$this->assertNotContains( $open_id, $returned_ids );
	}

	/**
	 * Tests meta_query filter.
	 *
	 * @ticket 64606
	 */
	public function test_multi_post_query_meta_filter(): void {
		wp_set_current_user( self::$subscriber_id );

		$match_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $match_id, 'ability_test_key', 'ability_test_value' );

		$no_match_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute(
			array(
				'meta_query' => array(
					array(
						'key'     => 'ability_test_key',
						'value'   => 'ability_test_value',
						'compare' => '=',
					),
				),
			)
		);

		$returned_ids = array_column( $result['posts'], 'id' );
		$this->assertContains( $match_id, $returned_ids );
		$this->assertNotContains( $no_match_id, $returned_ids );
	}

	/**
	 * Tests date_query filter.
	 *
	 * @ticket 64606
	 */
	public function test_multi_post_query_date_filter(): void {
		wp_set_current_user( self::$subscriber_id );

		$old_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2020-01-01 00:00:00',
			)
		);
		$new_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2024-06-01 00:00:00',
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute(
			array(
				'date_query' => array(
					'after' => '2023-01-01',
				),
			)
		);

		$returned_ids = array_column( $result['posts'], 'id' );
		$this->assertContains( $new_id, $returned_ids );
		$this->assertNotContains( $old_id, $returned_ids );
	}

	/**
	 * Tests that the page ability only returns pages, not posts.
	 *
	 * @ticket 64606
	 */
	public function test_page_get_ability_only_returns_pages(): void {
		wp_set_current_user( self::$subscriber_id );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$ability = wp_get_ability( 'core/post-type/page/get' );
		$result  = $ability->execute( array() );

		$returned_ids = array_column( $result['posts'], 'id' );
		$this->assertContains( $page_id, $returned_ids );
		$this->assertNotContains( $post_id, $returned_ids );
	}

	/**
	 * Tests that pagination works correctly.
	 *
	 * @ticket 64606
	 */
	public function test_multi_post_query_pagination(): void {
		wp_set_current_user( self::$subscriber_id );

		// Create exactly 3 posts.
		$ids = self::factory()->post->create_many(
			3,
			array(
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );

		$page_1 = $ability->execute(
			array(
				'per_page' => 2,
				'page'     => 1,
			)
		);
		$page_2 = $ability->execute(
			array(
				'per_page' => 2,
				'page'     => 2,
			)
		);

		$this->assertCount( 2, $page_1['posts'] );
		$this->assertCount( 1, $page_2['posts'] );
		$this->assertGreaterThanOrEqual( 3, $page_1['total'] );
		$this->assertGreaterThanOrEqual( 2, $page_1['pages'] );
	}

	// -------------------------------------------------------------------------
	// Output format.
	// -------------------------------------------------------------------------

	/**
	 * Tests that formatted post date is in ISO 8601 / RFC 3339 format.
	 *
	 * @ticket 64606
	 */
	public function test_post_date_is_rfc3339_format(): void {
		wp_set_current_user( self::$subscriber_id );

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2024-03-15 12:00:00',
			)
		);

		$ability = wp_get_ability( 'core/post-type/post/get' );
		$result  = $ability->execute( array( 'id' => $post_id ) );

		$date = $result['posts'][0]['date'];
		// RFC 3339 format: YYYY-MM-DDTHH:MM:SS
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $date );
	}

	/**
	 * Tests that the output schema uses only valid JSON Schema keywords.
	 *
	 * @ticket 64606
	 */
	public function test_post_get_ability_schemas_use_only_valid_keywords(): void {
		$allowed_keywords   = rest_get_allowed_schema_keywords();
		$allowed_keywords[] = 'required';

		$ability_names = array( 'core/post-type/post/get', 'core/post-type/page/get' );

		$this->assertNotEmpty( $ability_names, 'Ability names list should not be empty.' );

		foreach ( $ability_names as $ability_name ) {
			$ability = wp_get_ability( $ability_name );

			$this->assertInstanceOf( WP_Ability::class, $ability, "Ability '{$ability_name}' should be registered." );

			$this->assert_schema_uses_valid_keywords(
				$ability->get_input_schema(),
				$allowed_keywords,
				$ability_name . ' input_schema'
			);
			$this->assert_schema_uses_valid_keywords(
				$ability->get_output_schema(),
				$allowed_keywords,
				$ability_name . ' output_schema'
			);
		}
	}

	/**
	 * Recursively validates that a schema only uses allowed keywords.
	 *
	 * @param array|null $schema           The schema to validate.
	 * @param string[]   $allowed_keywords List of allowed schema keywords.
	 * @param string     $context          Context for error messages.
	 */
	private function assert_schema_uses_valid_keywords( ?array $schema, array $allowed_keywords, string $context ): void {
		if ( null === $schema ) {
			return;
		}

		foreach ( $schema as $key => $value ) {
			if ( is_int( $key ) ) {
				continue;
			}

			$nesting_keywords = array( 'properties', 'items', 'additionalProperties', 'patternProperties', 'anyOf', 'oneOf' );

			if ( ! in_array( $key, $nesting_keywords, true ) && ! in_array( $key, $allowed_keywords, true ) ) {
				$this->fail( "Invalid schema keyword '{$key}' found in {$context}. Valid keywords are: " . implode( ', ', $allowed_keywords ) );
			}

			if ( 'properties' === $key && is_array( $value ) ) {
				foreach ( $value as $prop_name => $prop_schema ) {
					$this->assert_schema_uses_valid_keywords( $prop_schema, $allowed_keywords, "{$context}.properties.{$prop_name}" );
				}
			} elseif ( 'items' === $key && is_array( $value ) ) {
				$this->assert_schema_uses_valid_keywords( $value, $allowed_keywords, "{$context}.items" );
			} elseif ( ( 'anyOf' === $key || 'oneOf' === $key ) && is_array( $value ) ) {
				foreach ( $value as $index => $sub_schema ) {
					$this->assert_schema_uses_valid_keywords( $sub_schema, $allowed_keywords, "{$context}.{$key}[{$index}]" );
				}
			} elseif ( 'additionalProperties' === $key && is_array( $value ) ) {
				$this->assert_schema_uses_valid_keywords( $value, $allowed_keywords, "{$context}.additionalProperties" );
			}
		}
	}
}
