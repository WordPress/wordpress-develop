<?php

declare( strict_types=1 );

/**
 * Tests for the post type get ability.
 *
 * @ticket 64606
 *
 * @covers WP_Post_Type_Abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpPostTypeAbilities extends WP_UnitTestCase {

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Test post IDs indexed 1-7 matching the fixture table.
	 *
	 * @var int[]
	 */
	protected static $post_ids = array();

	/**
	 * Tag term IDs keyed by slug.
	 *
	 * @var int[]
	 */
	protected static $tag_ids = array();

	/**
	 * Set up fixtures shared across all tests in this class.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		// Unregister any existing abilities and categories to start fresh.
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $category ) {
			wp_unregister_ability_category( $category->get_slug() );
		}

		// Ensure core abilities are registered.
		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );
		add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );

		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );

		// Create tags.
		foreach ( array( 't-23', 'c-1', 'b-1', 'other-tag' ) as $slug ) {
			self::$tag_ids[ $slug ] = $factory->term->create(
				array(
					'taxonomy' => 'post_tag',
					'slug'     => $slug,
					'name'     => $slug,
				)
			);
		}

		// Post 1: has footnotes meta, a=23, b=1, tags t-23 & c-1, date 2025-11-26.
		self::$post_ids[1] = $factory->post->create(
			array(
				'post_title'  => 'Post with footnotes',
				'post_status' => 'publish',
				'post_date'   => '2025-11-26 10:00:00',
			)
		);
		update_post_meta( self::$post_ids[1], 'footnotes', '[{}]' );
		update_post_meta( self::$post_ids[1], 'a', '23' );
		update_post_meta( self::$post_ids[1], 'b', '1' );
		update_post_meta( self::$post_ids[1], 'My.Key', 'special' );
		wp_set_object_terms( self::$post_ids[1], array( 't-23', 'c-1' ), 'post_tag' );

		// Post 2: a=23, c=1, tags t-23 & b-1, date 2025-11-15.
		self::$post_ids[2] = $factory->post->create(
			array(
				'post_title'  => 'Post with meta a and c',
				'post_status' => 'publish',
				'post_date'   => '2025-11-15 10:00:00',
			)
		);
		update_post_meta( self::$post_ids[2], 'a', '23' );
		update_post_meta( self::$post_ids[2], 'c', '1' );
		wp_set_object_terms( self::$post_ids[2], array( 't-23', 'b-1' ), 'post_tag' );

		// Post 3: b=1, tag b-1, date 2025-06-26.
		self::$post_ids[3] = $factory->post->create(
			array(
				'post_title'  => 'Post with meta b only',
				'post_status' => 'publish',
				'post_date'   => '2025-06-26 10:00:00',
			)
		);
		update_post_meta( self::$post_ids[3], 'b', '1' );
		wp_set_object_terms( self::$post_ids[3], array( 'b-1' ), 'post_tag' );

		// Post 4: x=99, tag other-tag, date 2024-03-10.
		self::$post_ids[4] = $factory->post->create(
			array(
				'post_title'  => 'Post with unrelated meta',
				'post_status' => 'publish',
				'post_date'   => '2024-03-10 10:00:00',
			)
		);
		update_post_meta( self::$post_ids[4], 'x', '99' );
		wp_set_object_terms( self::$post_ids[4], array( 'other-tag' ), 'post_tag' );

		// Post 5: no meta, no tags, date 2025-11-01.
		self::$post_ids[5] = $factory->post->create(
			array(
				'post_title'  => 'Post for date nov',
				'post_status' => 'publish',
				'post_date'   => '2025-11-01 10:00:00',
			)
		);

		// Post 6: no meta, no tags, date 2025-06-26.
		self::$post_ids[6] = $factory->post->create(
			array(
				'post_title'  => 'Post for date day26',
				'post_status' => 'publish',
				'post_date'   => '2025-06-26 10:00:00',
			)
		);

		// Post 7: private post, used for permission checks.
		self::$post_ids[7] = $factory->post->create(
			array(
				'post_title'  => 'Private post',
				'post_status' => 'private',
				'post_author' => self::$editor_id,
				'post_date'   => '2025-12-01 10:00:00',
			)
		);
	}

	/**
	 * Clean up after all tests.
	 */
	public static function wpTearDownAfterClass(): void {
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $category ) {
			wp_unregister_ability_category( $category->get_slug() );
		}

		// Unregister test meta keys.
		unregister_meta_key( 'post', 'footnotes' );
		unregister_meta_key( 'post', 'a' );
		unregister_meta_key( 'post', 'b' );
		unregister_meta_key( 'post', 'c' );
		unregister_meta_key( 'post', 'My.Key' );
		unregister_meta_key( 'post', 'structured_object' );
		unregister_meta_key( 'post', 'schema_constrained' );
	}

	/**
	 * Set up each test with an authenticated editor user.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::$editor_id );

		// Register meta keys with show_in_abilities enabled.
		// This must be done in set_up() because the global $wp_meta_keys is reset between tests.
		register_meta(
			'post',
			'footnotes',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_abilities' => true,
			)
		);
		register_meta(
			'post',
			'a',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_abilities' => true,
			)
		);
		register_meta(
			'post',
			'b',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_abilities' => true,
			)
		);
		register_meta(
			'post',
			'c',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_abilities' => true,
			)
		);
		register_meta(
			'post',
			'My.Key',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_abilities' => true,
			)
		);
		register_meta(
			'post',
			'structured_object',
			array(
				'type'              => 'object',
				'single'            => true,
				'show_in_abilities' => true,
			)
		);
		register_meta(
			'post',
			'schema_constrained',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_abilities' => array(
					'schema' => array(
						'type' => 'string',
						'enum' => array( 'allowed' ),
					),
				),
			)
		);

		$this->reregister_post_type_abilities();
	}

	/**
	 * Simulates the `wp_abilities_api_init` action.
	 *
	 * This makes `doing_action('wp_abilities_api_init')` return true without
	 * firing all hooks registered on that action.
	 */
	private function simulate_doing_wp_abilities_init_action(): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init';
	}

	/**
	 * Ends the simulated `wp_abilities_api_init` action.
	 */
	private function end_simulated_wp_abilities_init_action(): void {
		global $wp_current_filter;
		array_pop( $wp_current_filter );
	}

	/**
	 * Re-registers post type abilities so input schemas include the current meta key registry.
	 */
	private function reregister_post_type_abilities(): void {
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}

		remove_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		$this->simulate_doing_wp_abilities_init_action();
		WP_Post_Type_Abilities::register();
		$this->end_simulated_wp_abilities_init_action();
	}

	/**
	 * Executes the post type get ability.
	 *
	 * @param array $input Input parameters for the ability.
	 * @return mixed The ability output on success, or WP_Error on failure.
	 */
	private function execute_get_ability( array $input = array() ) {
		$ability = wp_get_ability( 'core/post-type/post/get' );
		$this->assertInstanceOf( WP_Ability::class, $ability );

		if ( empty( $input ) ) {
			return $ability->execute();
		}

		return $ability->execute( $input );
	}

	/**
	 * Extracts post IDs from a query response's posts array.
	 *
	 * @param array $data Response data containing 'posts' key.
	 * @return int[] Array of post IDs.
	 */
	private function get_response_post_ids( array $data ): array {
		return array_map(
			static function ( $post ) {
				return $post['id'];
			},
			$data['posts']
		);
	}

	/**
	 * Tests that the get ability is registered with expected metadata.
	 *
	 * @ticket 64606
	 */
	public function test_get_ability_is_registered_with_expected_metadata(): void {
		$ability = wp_get_ability( 'core/post-type/post/get' );

		$this->assertInstanceOf( WP_Ability::class, $ability );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ) );
		$this->assertTrue( $annotations['readonly'] ?? false );
		$this->assertFalse( $annotations['destructive'] ?? true );
		$this->assertTrue( $annotations['idempotent'] ?? false );
	}

	/**
	 * Tests retrieving a single post by ID.
	 *
	 * @ticket 64606
	 */
	public function test_get_single_post_by_id(): void {
		$data = $this->execute_get_ability( array( 'id' => self::$post_ids[1] ) );

		$this->assertIsArray( $data );
		$this->assertSame( self::$post_ids[1], $data['id'] );
		$this->assertSame( 'post', $data['type'] );
		$this->assertSame( 'publish', $data['status'] );
		$this->assertSame( 'Post with footnotes', $data['title'] );
		$this->assertArrayHasKey( 'slug', $data );
		$this->assertArrayHasKey( 'link', $data );
		$this->assertArrayHasKey( 'date', $data );
		$this->assertArrayHasKey( 'modified', $data );
	}

	/**
	 * Tests retrieving a single post with meta and taxonomies included.
	 *
	 * @ticket 64606
	 */
	public function test_get_single_post_with_meta_and_taxonomies(): void {
		$data = $this->execute_get_ability(
			array(
				'id'      => self::$post_ids[1],
				'include' => array(
					'meta'       => true,
					'taxonomies' => true,
				),
			)
		);

		$this->assertIsArray( $data );

		// Meta should contain the public meta keys.
		$this->assertArrayHasKey( 'meta', $data );
		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'footnotes', $meta );
		$this->assertArrayHasKey( 'a', $meta );
		$this->assertArrayHasKey( 'b', $meta );
		$this->assertSame( '23', $meta['a'] );

		// Taxonomies should contain post_tag terms.
		$this->assertArrayHasKey( 'taxonomies', $data );
		$this->assertArrayHasKey( 'post_tag', $data['taxonomies'] );
		$tag_slugs = array_column( $data['taxonomies']['post_tag'], 'slug' );
		$this->assertContains( 't-23', $tag_slugs );
		$this->assertContains( 'c-1', $tag_slugs );
	}

	/**
	 * Tests that requesting a non-existent post returns a not found error.
	 *
	 * @ticket 64606
	 */
	public function test_get_single_post_not_found(): void {
		$result = $this->execute_get_ability( array( 'id' => 999999 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'post_not_found', $result->get_error_code() );
	}

	/**
	 * Tests that query mode returns paginated results.
	 *
	 * @ticket 64606
	 */
	public function test_query_returns_paginated_results(): void {
		$data = $this->execute_get_ability(
			array(
				'per_page' => 2,
				'page'     => 1,
			)
		);

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'posts', $data );
		$this->assertCount( 2, $data['posts'] );
		$this->assertSame( 6, $data['total'] );
		$this->assertSame( 3, $data['total_pages'] );
	}

	/**
	 * Tests meta query with EXISTS operator finds only the post with footnotes.
	 *
	 * @ticket 64606
	 */
	public function test_meta_query_exists(): void {
		$data = $this->execute_get_ability(
			array(
				'include' => array( 'meta' => true ),
				'query'   => array(
					'meta' => array(
						'queries' => array(
							array(
								'key'     => 'footnotes',
								'compare' => 'EXISTS',
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $data );
		$post_ids = $this->get_response_post_ids( $data );

		$this->assertSame( 1, $data['total'] );
		$this->assertContains( self::$post_ids[1], $post_ids );
	}

	/**
	 * Tests nested meta query: a=23 AND (b=1 OR c=1).
	 *
	 * Should match posts 1 and 2.
	 *
	 * @ticket 64606
	 */
	public function test_meta_query_nested_and_or(): void {
		$data = $this->execute_get_ability(
			array(
				'query' => array(
					'meta' => array(
						'relation' => 'AND',
						'queries'  => array(
							array(
								'key'     => 'a',
								'compare' => '=',
								'value'   => '23',
							),
							array(
								'relation' => 'OR',
								'queries'  => array(
									array(
										'key'     => 'b',
										'compare' => '=',
										'value'   => '1',
									),
									array(
										'key'     => 'c',
										'compare' => '=',
										'value'   => '1',
									),
								),
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $data );
		$post_ids = $this->get_response_post_ids( $data );

		$this->assertSame( 2, $data['total'] );
		$this->assertContains( self::$post_ids[1], $post_ids );
		$this->assertContains( self::$post_ids[2], $post_ids );
	}

	/**
	 * Tests nested tax query: tag t-23 AND (tag c-1 OR tag b-1).
	 *
	 * Should match posts 1 and 2.
	 *
	 * @ticket 64606
	 */
	public function test_tax_query_nested_and_or(): void {
		$data = $this->execute_get_ability(
			array(
				'query' => array(
					'tax' => array(
						'relation' => 'AND',
						'queries'  => array(
							array(
								'taxonomy' => 'post_tag',
								'field'    => 'slug',
								'terms'    => array( 't-23' ),
							),
							array(
								'relation' => 'OR',
								'queries'  => array(
									array(
										'taxonomy' => 'post_tag',
										'field'    => 'slug',
										'terms'    => array( 'c-1' ),
									),
									array(
										'taxonomy' => 'post_tag',
										'field'    => 'slug',
										'terms'    => array( 'b-1' ),
									),
								),
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $data );
		$post_ids = $this->get_response_post_ids( $data );

		$this->assertSame( 2, $data['total'] );
		$this->assertContains( self::$post_ids[1], $post_ids );
		$this->assertContains( self::$post_ids[2], $post_ids );
	}

	/**
	 * Tests nested date query: year=2025 AND (day=26 OR month=11).
	 *
	 * Should match posts 1, 2, 3, 5, 6 (all 2025 posts that have day=26 or month=11).
	 * Post 4 excluded because it is from 2024.
	 *
	 * @ticket 64606
	 */
	public function test_date_query_nested_and_or(): void {
		$data = $this->execute_get_ability(
			array(
				'per_page' => 100,
				'query'    => array(
					'date' => array(
						'relation' => 'AND',
						'queries'  => array(
							array( 'year' => 2025 ),
							array(
								'relation' => 'OR',
								'queries'  => array(
									array( 'day' => 26 ),
									array( 'month' => 11 ),
								),
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $data );
		$post_ids = $this->get_response_post_ids( $data );

		$this->assertSame( 5, $data['total'] );
		$this->assertContains( self::$post_ids[1], $post_ids );
		$this->assertContains( self::$post_ids[2], $post_ids );
		$this->assertContains( self::$post_ids[3], $post_ids );
		$this->assertContains( self::$post_ids[5], $post_ids );
		$this->assertContains( self::$post_ids[6], $post_ids );
		$this->assertNotContains( self::$post_ids[4], $post_ids );
	}

	/**
	 * Tests that unauthenticated requests cannot query published posts.
	 *
	 * @ticket 64606
	 */
	public function test_unauthenticated_query_published_posts_rejected(): void {
		wp_set_current_user( 0 );

		$result = $this->execute_get_ability( array() );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Tests that unauthenticated requests cannot query private posts.
	 *
	 * @ticket 64606
	 */
	public function test_unauthenticated_query_private_status_rejected(): void {
		wp_set_current_user( 0 );

		$result = $this->execute_get_ability(
			array(
				'status' => array( 'private' ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Tests that unauthenticated requests cannot read a published post by ID.
	 *
	 * @ticket 64606
	 */
	public function test_unauthenticated_get_single_published_post_rejected(): void {
		wp_set_current_user( 0 );

		$result = $this->execute_get_ability( array( 'id' => self::$post_ids[1] ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Tests that unauthenticated requests cannot read a private post by ID.
	 *
	 * @ticket 64606
	 */
	public function test_unauthenticated_get_single_private_post_rejected(): void {
		wp_set_current_user( 0 );

		$result = $this->execute_get_ability( array( 'id' => self::$post_ids[7] ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Tests that authenticated editor can query posts.
	 *
	 * @ticket 64606
	 */
	public function test_authenticated_query_succeeds(): void {
		$data = $this->execute_get_ability( array() );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'posts', $data );
		$this->assertGreaterThan( 0, $data['total'] );
	}

	/**
	 * Tests ordering by title ascending.
	 *
	 * @ticket 64606
	 */
	public function test_query_with_ordering(): void {
		$data = $this->execute_get_ability(
			array(
				'order'    => array(
					'orderby'   => 'title',
					'direction' => 'asc',
				),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $data );
		$titles = array_map(
			static function ( $post ) {
				return $post['title'];
			},
			$data['posts']
		);

		$sorted = $titles;
		sort( $sorted, SORT_STRING );
		$this->assertSame( $sorted, $titles );
	}

	/**
	 * Tests that only meta keys registered with show_in_abilities are included.
	 *
	 * @ticket 64606
	 */
	public function test_meta_only_includes_show_in_abilities_registered_keys(): void {
		// Post 4 has meta key 'x' which is NOT registered with show_in_abilities.
		$data = $this->execute_get_ability(
			array(
				'id'      => self::$post_ids[4],
				'include' => array( 'meta' => true ),
			)
		);

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'meta', $data );

		// The 'x' meta key should NOT be present since it's not registered with show_in_abilities.
		$this->assertArrayNotHasKey( 'x', (array) $data['meta'] );
	}

	/**
	 * Tests that a schema-based `show_in_abilities` registration is accepted for meta queries.
	 *
	 * @ticket 64606
	 */
	public function test_meta_query_with_schema_based_registration_succeeds(): void {
		update_post_meta( self::$post_ids[1], 'schema_constrained', 'allowed' );

		$data = $this->execute_get_ability(
			array(
				'query'    => array(
					'meta' => array(
						'queries' => array(
							array(
								'key'     => 'schema_constrained',
								'compare' => '=',
								'value'   => 'allowed',
							),
						),
					),
				),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $data );
		$post_ids = $this->get_response_post_ids( $data );

		$this->assertContains( self::$post_ids[1], $post_ids );
	}

	/**
	 * Tests that `show_in_abilities.schema` is enforced when meta is included in output.
	 *
	 * @ticket 64606
	 */
	public function test_meta_include_with_schema_invalid_value_fails_output_validation(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Post with invalid schema-constrained meta',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'schema_constrained', 'blocked' );

		$result = $this->execute_get_ability(
			array(
				'id'      => $post_id,
				'include' => array( 'meta' => true ),
			)
		);

		wp_delete_post( $post_id, true );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_output', $result->get_error_code() );
		$this->assertStringContainsString( 'schema_constrained', $result->get_error_message() );
		$this->assertStringContainsString( 'output', $result->get_error_message() );
	}

	/**
	 * Tests that object-like meta values are allowed by the output schema.
	 *
	 * @ticket 64606
	 */
	public function test_meta_include_supports_object_values(): void {
		$filter = static function ( $check, $object_id, $meta_key, $single, $meta_type ) {
			if ( 'post' !== $meta_type || self::$post_ids[1] !== $object_id || '' !== $meta_key || $single ) {
				return $check;
			}

			return array(
				'structured_object' => array(
					(object) array(
						'foo'    => 'bar',
						'nested' => array(
							'baz' => 'qux',
						),
					),
				),
			);
		};

		add_filter( 'get_post_metadata', $filter, 10, 5 );

		$data = $this->execute_get_ability(
			array(
				'id'      => self::$post_ids[1],
				'include' => array( 'meta' => true ),
			)
		);
		remove_filter( 'get_post_metadata', $filter, 10 );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'structured_object', $meta );

		$structured_object = (array) $meta['structured_object'];
		$this->assertSame( 'bar', $structured_object['foo'] );
		$this->assertSame( 'qux', $structured_object['nested']['baz'] );
	}

	/**
	 * Tests that meta query with unregistered meta key returns an error.
	 *
	 * @ticket 64606
	 */
	public function test_meta_query_with_invalid_key_returns_error(): void {
		$result = $this->execute_get_ability(
			array(
				'query' => array(
					'meta' => array(
						'queries' => array(
							array(
								'key'     => 'invalid_meta_key',
								'compare' => 'EXISTS',
							),
						),
					),
				),
			)
		);

		$this->assertWPError( $result );
		// Schema validation catches invalid meta keys.
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Tests that meta query with valid registered meta key succeeds.
	 *
	 * @ticket 64606
	 */
	public function test_meta_query_with_valid_key_succeeds(): void {
		$data = $this->execute_get_ability(
			array(
				'query' => array(
					'meta' => array(
						'queries' => array(
							array(
								'key'     => 'a',
								'compare' => '=',
								'value'   => '23',
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'posts', $data );
	}

	/**
	 * Tests that meta query works with a registered non-slug meta key.
	 *
	 * @ticket 64606
	 */
	public function test_meta_query_with_non_slug_registered_key_succeeds(): void {
		$data = $this->execute_get_ability(
			array(
				'query'    => array(
					'meta' => array(
						'queries' => array(
							array(
								'key'     => 'My.Key',
								'compare' => 'EXISTS',
							),
						),
					),
				),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $data );
		$post_ids = $this->get_response_post_ids( $data );

		$this->assertSame( 1, $data['total'] );
		$this->assertContains( self::$post_ids[1], $post_ids );
	}

	/**
	 * Tests that post-type-specific show_in_abilities overrides global registration.
	 *
	 * @ticket 64606
	 */
	public function test_meta_query_respects_post_type_specific_show_in_abilities_override(): void {
		register_meta(
			'post',
			'override_key',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_abilities' => false,
			)
		);

		register_meta(
			'post',
			'override_key',
			array(
				'object_subtype'    => 'post',
				'type'              => 'string',
				'single'            => true,
				'show_in_abilities' => true,
			)
		);

		update_post_meta( self::$post_ids[1], 'override_key', '1' );
		$this->reregister_post_type_abilities();

		$data = $this->execute_get_ability(
			array(
				'query'    => array(
					'meta' => array(
						'queries' => array(
							array(
								'key'     => 'override_key',
								'compare' => 'EXISTS',
							),
						),
					),
				),
				'per_page' => 100,
			)
		);

		$this->assertIsArray( $data );
		$post_ids = $this->get_response_post_ids( $data );

		$this->assertSame( 1, $data['total'] );
		$this->assertContains( self::$post_ids[1], $post_ids );
	}

	/**
	 * Tests that invalid clauses in deeply nested meta queries fail schema validation.
	 *
	 * @ticket 64606
	 */
	public function test_meta_query_deep_nested_invalid_clause_rejected(): void {
		$result = $this->execute_get_ability(
			array(
				'query' => array(
					'meta' => array(
						'queries' => array(
							array(
								'relation' => 'AND',
								'queries'  => array(
									array(
										'relation' => 'OR',
										'queries'  => array(
											array(
												'foo' => 'bar',
											),
										),
									),
								),
							),
						),
					),
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Tests that top-level meta query requires a queries list.
	 *
	 * @ticket 64606
	 */
	public function test_meta_query_missing_queries_rejected(): void {
		$result = $this->execute_get_ability(
			array(
				'query' => array(
					'meta' => array(
						'relation' => 'AND',
					),
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Tests that tax query with non-public taxonomy returns an error.
	 *
	 * @ticket 64606
	 */
	public function test_tax_query_with_non_public_taxonomy_returns_error(): void {
		// Register a non-public taxonomy for testing.
		register_taxonomy(
			'private_tax',
			'post',
			array(
				'public' => false,
			)
		);

		$result = $this->execute_get_ability(
			array(
				'query' => array(
					'tax' => array(
						'queries' => array(
							array(
								'taxonomy' => 'private_tax',
								'terms'    => array( 'test-term' ),
							),
						),
					),
				),
			)
		);

		// Schema validation catches non-public taxonomies.
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// Clean up.
		unregister_taxonomy( 'private_tax' );
	}

	/**
	 * Tests that tax query with public taxonomy succeeds.
	 *
	 * @ticket 64606
	 */
	public function test_tax_query_with_public_taxonomy_succeeds(): void {
		$data = $this->execute_get_ability(
			array(
				'query' => array(
					'tax' => array(
						'queries' => array(
							array(
								'taxonomy' => 'post_tag',
								'terms'    => array( 't-23' ),
								'field'    => 'slug',
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'posts', $data );
	}
}
