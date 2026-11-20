<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-terms-ability.php';

/**
 * Tests for the core/terms-query ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Terms
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreTermsAbility extends WP_Abilities_Terms_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/terms-query';

	/**
	 * Returns the IDs of the items of a query result.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 * @return list<int> The IDs, in their order.
	 */
	private function item_ids( $result, string $context ): array {
		$this->assertIsArray( $result, "{$context}: the query should succeed." );
		$this->assertArrayHasKey( 'items', $result, "{$context}: the result should hold items." );

		return array_map(
			static function ( array $item ): int {
				return $item['id'];
			},
			$result['items']
		);
	}

	/**
	 * Creates tags and returns their IDs and slugs.
	 *
	 * @since 7.3.0
	 *
	 * @param int $count The number of tags.
	 * @return array{ids: list<int>, slugs: list<string>} The IDs and slugs.
	 */
	private function create_tags( int $count ): array {
		$ids   = array();
		$slugs = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$ids[]   = self::factory()->term->create(
				array(
					'taxonomy' => 'post_tag',
					'name'     => sprintf( 'extra %02d', $i ),
				)
			);
			$slugs[] = sprintf( 'extra-%02d', $i );
		}

		return array(
			'ids'   => $ids,
			'slugs' => $slugs,
		);
	}

	/**
	 * The ability is registered in the content category with its label and annotations.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_registers_the_ability(): void {
		$this->register_abilities();

		$ability = wp_get_ability( self::ABILITY );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The core/terms-query ability should be registered.' );
		$this->assertSame( 'Query Terms', $ability->get_label(), 'The ability should have its label.' );
		$this->assertSame( 'content', $ability->get_category(), 'The ability should use the content category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The ability should be marked public.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'], 'The ability should be marked read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The ability should not be marked destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'The ability should be marked idempotent.' );
	}

	/**
	 * None of the term abilities is registered when no taxonomy is exposed to abilities.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_no_term_ability_is_registered_without_an_exposed_taxonomy(): void {
		foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
			$taxonomy->show_in_abilities = false;
		}

		$this->register_abilities();

		foreach ( self::TERM_ABILITIES as $ability_name ) {
			$this->assertFalse( wp_has_ability( $ability_name ), "The {$ability_name} ability should not be registered without an exposed taxonomy." );
		}
	}

	/**
	 * The taxonomy enums of the four abilities list the exposed taxonomies and no other.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_taxonomy_enums_list_only_the_exposed_taxonomies(): void {
		$this->register_taxonomy_with_own_capabilities();
		register_taxonomy(
			'tt_hidden',
			'post',
			array(
				'public'            => true,
				'show_in_rest'      => true,
				'show_in_abilities' => false,
			)
		);
		register_taxonomy(
			'tt_truthy',
			'post',
			array(
				'public'            => true,
				'show_in_abilities' => 1,
			)
		);

		$this->register_abilities();

		$expected = array( 'category', 'post_tag', 'tt_genre' );
		$paths    = array(
			'core/terms-query' => array( 'where', 'taxonomy' ),
			'core/term-create' => array( 'data', 'taxonomy' ),
			'core/term-update' => array( 'where', 'taxonomy' ),
			'core/term-delete' => array( 'where', 'taxonomy' ),
		);

		foreach ( $paths as $ability_name => $path ) {
			$this->assertTrue( wp_has_ability( $ability_name ), "The {$ability_name} ability should be registered." );

			$schema = wp_get_ability( $ability_name )->get_input_schema();
			$enum   = $schema['properties'][ $path[0] ]['properties'][ $path[1] ]['enum'];

			$this->assertSameSets( $expected, $enum, "The taxonomy enum of {$ability_name} should list every exposed taxonomy and no other." );
		}
	}

	/**
	 * The input schema is the filter object, the field list and the pagination.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_input_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The query ability should be registered.' );

		$list = static function ( array $items ): array {
			return array(
				'type'        => 'array',
				'uniqueItems' => true,
				'minItems'    => 1,
				'maxItems'    => 100,
				'items'       => $items,
			);
		};

		$expected = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where' ),
			'properties'           => array(
				'where'      => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'taxonomy' ),
					'properties'           => array(
						'taxonomy'   => $this->taxonomy_schema(),
						'ids'        => $list(
							array(
								'type'    => 'integer',
								'minimum' => 1,
							)
						),
						'slugs'      => $list(
							array(
								'type'      => 'string',
								'minLength' => 1,
							)
						),
						'parent'     => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'post'       => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'hide_empty' => array( 'type' => 'boolean' ),
					),
				),
				'fields'     => self::FIELDS_SCHEMA,
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
			),
		);

		$this->assert_schema( $expected, wp_get_ability( self::ABILITY )->get_input_schema(), 'The input schema should be exactly the documented one.' );
	}

	/**
	 * The output schema is the list of items and the pagination totals.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_output_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The query ability should be registered.' );

		$expected = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'items', 'pagination' ),
			'properties'           => array(
				'items'      => array(
					'type'  => 'array',
					'items' => self::ITEM_SCHEMA,
				),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'total', 'total_pages' ),
					'properties'           => array(
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
					),
				),
			),
		);

		$this->assert_schema( $expected, wp_get_ability( self::ABILITY )->get_output_schema(), 'The output schema should be exactly the documented one.' );
	}

	/**
	 * Returns raw input that the permission callback refuses even for an administrator.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{input: mixed}> The inputs.
	 */
	public static function data_inputs_refused_by_the_permission_callback(): array {
		return array(
			'input that is a string'              => array( 'category' ),
			'input that is null'                  => array( null ),
			'missing where'                       => array( array() ),
			'where that is a string'              => array( array( 'where' => 'category' ) ),
			'where that is an integer'            => array( array( 'where' => 1 ) ),
			'missing taxonomy'                    => array( array( 'where' => array( 'parent' => 0 ) ) ),
			'taxonomy that is not a string'       => array( array( 'where' => array( 'taxonomy' => array( 'category' ) ) ) ),
			'taxonomy that is not exposed'        => array( array( 'where' => array( 'taxonomy' => 'nav_menu' ) ) ),
			'taxonomy that does not exist'        => array( array( 'where' => array( 'taxonomy' => 'tt_missing' ) ) ),
			'object where with a hidden taxonomy' => array( array( 'where' => (object) array( 'taxonomy' => 'nav_menu' ) ) ),
			'object input without a where'        => array( (object) array( 'fields' => array( 'id' ) ) ),
		);
	}

	/**
	 * The permission callback refuses malformed input and taxonomies that are not exposed.
	 *
	 * @ticket 66743
	 * @dataProvider data_inputs_refused_by_the_permission_callback
	 * @since 7.3.0
	 *
	 * @param mixed $input The input.
	 */
	public function test_permission_callback_refuses_malformed_input( $input ): void {
		$this->login_as( 'administrator' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertFalse( $permission( $input ), 'The permission callback should refuse the input.' );
	}

	/**
	 * Returns input forms that count as objects.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{input: mixed}> The inputs.
	 */
	public static function data_object_input_forms(): array {
		$where = array( 'taxonomy' => 'post_tag' );

		return array(
			'arrays'                  => array( array( 'where' => $where ) ),
			'input that is an object' => array( (object) array( 'where' => $where ) ),
			'where that is an object' => array( array( 'where' => (object) $where ) ),
			'both that are objects'   => array( (object) array( 'where' => (object) $where ) ),
		);
	}

	/**
	 * The permission callback accepts the input and `where` as arrays or objects.
	 *
	 * @ticket 66743
	 * @dataProvider data_object_input_forms
	 * @since 7.3.0
	 *
	 * @param mixed $input The input.
	 */
	public function test_permission_callback_accepts_arrays_and_objects( $input ): void {
		$this->login_as( 'subscriber' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertTrue( $permission( $input ), 'The permission callback should accept the input.' );
	}

	/**
	 * A logged-out visitor cannot query, also for a public taxonomy.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_logged_out_visitor_is_refused(): void {
		$this->login_as( 'logged_out' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertFalse( $permission( array( 'where' => array( 'taxonomy' => 'category' ) ) ), 'The permission callback should refuse a logged-out visitor.' );

		$result = $this->run_rejected( array( 'where' => array( 'taxonomy' => 'category' ) ), 'Logged out' );

		$this->assert_refused( $result, 'Logged out' );
	}

	/**
	 * Returns every logged-in role, a user with no role on the site included.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{role: string}> The roles.
	 */
	public static function data_logged_in_roles(): array {
		return array(
			'administrator'     => array( 'administrator' ),
			'editor'            => array( 'editor' ),
			'author'            => array( 'author' ),
			'contributor'       => array( 'contributor' ),
			'subscriber'        => array( 'subscriber' ),
			'user with no role' => array( 'no_role' ),
		);
	}

	/**
	 * Any logged-in user can query terms, without a capability.
	 *
	 * @ticket 66743
	 * @dataProvider data_logged_in_roles
	 * @since 7.3.0
	 *
	 * @param string $role The role.
	 */
	public function test_any_logged_in_user_can_query( string $role ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => array( 'taxonomy' => 'post_tag' ) ) );

		$this->assertSame( array( self::$term_ids['alpha'], self::$term_ids['beta'] ), $this->item_ids( $result, $role ), 'Every tag should be listed by name.' );
	}

	/**
	 * A super admin who has no role on the site can query.
	 *
	 * @ticket 66743
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_super_admin_can_query(): void {
		$this->login_as( 'super_admin' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => array( 'taxonomy' => 'post_tag' ) ) );

		$this->assertSame( array( self::$term_ids['alpha'], self::$term_ids['beta'] ), $this->item_ids( $result, 'Super admin' ), 'Every tag should be listed by name.' );
	}

	/**
	 * Returns taxonomies that the execute callback does not find.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{taxonomy: string}> The taxonomies.
	 */
	public static function data_taxonomies_that_are_not_found(): array {
		return array(
			'taxonomy turned off after registration' => array( 'category' ),
			'taxonomy that is not exposed'           => array( 'nav_menu' ),
			'taxonomy that does not exist'           => array( 'tt_missing' ),
		);
	}

	/**
	 * The execute callback checks itself that the taxonomy is exposed.
	 *
	 * @ticket 66743
	 * @dataProvider data_taxonomies_that_are_not_found
	 * @since 7.3.0
	 *
	 * @param string $taxonomy The taxonomy.
	 */
	public function test_execute_refuses_a_taxonomy_that_is_not_exposed( string $taxonomy ): void {
		$this->login_as( 'administrator' );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		get_taxonomy( 'category' )->show_in_abilities = false;

		$result = $this->call_rejected( $execute, array( 'where' => array( 'taxonomy' => $taxonomy ) ), $taxonomy );

		$this->assert_taxonomy_not_found( $result, $taxonomy );
	}

	/**
	 * Returns parent filters on a flat taxonomy.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{where: array<string, mixed>}> The filters.
	 */
	public static function data_parent_filters_on_a_flat_taxonomy(): array {
		return array(
			'parent of zero'                => array( array( 'parent' => 0 ) ),
			'parent of a term'              => array( array( 'parent' => 5 ) ),
			'parent with the later filters' => array(
				array(
					'parent'     => 0,
					'hide_empty' => true,
					'post'       => self::MISSING_ID,
				),
			),
		);
	}

	/**
	 * A parent filter on a flat taxonomy is an error, also for zero, before the other filters.
	 *
	 * @ticket 66743
	 * @dataProvider data_parent_filters_on_a_flat_taxonomy
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $where The filters next to the taxonomy.
	 */
	public function test_parent_filter_on_a_flat_taxonomy_is_an_error( array $where ): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result  = $this->run_rejected( array( 'where' => array( 'taxonomy' => 'post_tag' ) + $where ), 'Parent on tags' );
		$message = 'The "post_tag" taxonomy is not hierarchical.';

		$this->assert_error( $result, 'terms_invalid_filter', 400, $message, 'Parent on tags', array( 'params' => array( 'where[parent]' => $message ) ) );
	}

	/**
	 * Hiding empty terms cannot be combined with the post filter, and that is checked before the post.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_hide_empty_with_the_post_filter_is_an_error(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$message = 'The hide_empty filter cannot be combined with the post filter.';
		$extra   = array( 'params' => array( 'where[hide_empty]' => $message ) );

		foreach ( array( self::$post_ids['published'], self::MISSING_ID ) as $post_id ) {
			$context = "Hide empty with post {$post_id}";
			$result  = $this->run_rejected(
				array(
					'where' => array(
						'taxonomy'   => 'post_tag',
						'post'       => $post_id,
						'hide_empty' => true,
					),
				),
				$context
			);

			$this->assert_error( $result, 'terms_invalid_filter', 400, $message, $context, $extra );
		}

		$result = $this->run_ability(
			array(
				'where' => array(
					'taxonomy'   => 'post_tag',
					'post'       => self::$post_ids['published'],
					'hide_empty' => false,
				),
			)
		);

		$this->assertSame( array( self::$term_ids['beta'] ), $this->item_ids( $result, 'Hide empty false' ), 'Hiding no terms should be allowed with the post filter.' );
	}

	/**
	 * Returns posts that the post filter refuses, with the user who asks.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{post: string, role: string}> The cases.
	 */
	public static function data_posts_refused_by_the_post_filter(): array {
		return array(
			'post that does not exist'             => array( 'missing', 'administrator' ),
			'post type without the taxonomy'       => array( 'page', 'administrator' ),
			'private post the user cannot read'    => array( 'private', 'subscriber' ),
			'private post for a user with no role' => array( 'private', 'no_role' ),
		);
	}

	/**
	 * A post that is missing, of a type without the taxonomy or not readable gives one identical error.
	 *
	 * @ticket 66743
	 * @dataProvider data_posts_refused_by_the_post_filter
	 * @since 7.3.0
	 *
	 * @param string $post The post fixture name, or `missing`.
	 * @param string $role The role.
	 */
	public function test_post_filter_refuses_a_post_that_cannot_be_used( string $post, string $role ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$post_id = 'missing' === $post ? self::MISSING_ID : self::$post_ids[ $post ];
		$result  = $this->run_rejected(
			array(
				'where' => array(
					'taxonomy' => 'category',
					'post'     => $post_id,
				),
			),
			$post
		);

		$this->assert_error( $result, 'terms_invalid_filter', 400, 'Invalid post ID.', $post, array( 'params' => array( 'where[post]' => 'Invalid post ID.' ) ) );
	}

	/**
	 * A published post that is not publicly viewable is refused for a user who cannot read it,
	 * with the same error, and listed for a user who can.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_post_filter_refuses_a_published_post_that_is_not_publicly_viewable(): void {
		register_post_type(
			'tt_internal',
			array(
				'public'       => false,
				'map_meta_cap' => true,
				'taxonomies'   => array( 'post_tag' ),
			)
		);
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'tt_internal',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $post_id, array( self::$term_ids['alpha'] ), 'post_tag' );

		$this->assertSame( 'publish', get_post_status( $post_id ), 'The post should be published.' );
		$this->assertFalse( is_post_publicly_viewable( $post_id ), 'The post should not be publicly viewable.' );

		$input = array(
			'where' => array(
				'taxonomy' => 'post_tag',
				'post'     => $post_id,
			),
		);

		$this->login_as( 'no_role' );
		$this->register_abilities();

		$result = $this->run_rejected( $input, 'No role' );

		$this->assert_error( $result, 'terms_invalid_filter', 400, 'Invalid post ID.', 'No role', array( 'params' => array( 'where[post]' => 'Invalid post ID.' ) ) );

		$this->login_as( 'subscriber' );

		$this->assertSame( array( self::$term_ids['alpha'] ), $this->item_ids( $this->run_ability( $input ), 'Subscriber' ), 'A user who can read the post should get its tags.' );
	}

	/**
	 * Returns post IDs the schema refuses, with whether a global post is set.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{post: int, with_global: bool}> The cases.
	 */
	public static function data_post_ids_below_one(): array {
		return array(
			'post zero'                         => array( 0, false ),
			'post minus one'                    => array( -1, false ),
			'post zero with a global post'      => array( 0, true ),
			'post minus one with a global post' => array( -1, true ),
		);
	}

	/**
	 * A post ID below 1 that reaches the execute callback gives the error of the other posts
	 * the filter refuses, also when a global post with tags is set.
	 *
	 * @ticket 66743
	 * @dataProvider data_post_ids_below_one
	 * @since 7.3.0
	 *
	 * @param int  $post        The post ID the schema refuses.
	 * @param bool $with_global Whether to set the global post to a post with tags.
	 */
	public function test_post_filter_refuses_a_post_id_below_one( int $post, bool $with_global ): void {
		if ( $with_global ) {
			$GLOBALS['post'] = get_post( self::$post_ids['published'] );
			$this->assertNotEmpty( get_the_terms( $GLOBALS['post'], 'post_tag' ), 'The global post should have tags.' );
		}

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_past_validation(
			array(
				'where' => array(
					'taxonomy' => 'post_tag',
					'post'     => $post,
				),
			),
			"Post {$post}"
		);

		$this->assert_error( $result, 'terms_invalid_filter', 400, 'Invalid post ID.', "Post {$post}", array( 'params' => array( 'where[post]' => 'Invalid post ID.' ) ) );
	}

	/**
	 * The terms of a published post are listed with the default fields.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_lists_the_tags_of_a_post(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where' => array(
					'taxonomy' => 'post_tag',
					'post'     => self::$post_ids['published'],
				),
			)
		);

		$this->assertSame(
			array(
				'items'      => array( $this->expected_item( self::$term_ids['beta'], self::DEFAULT_KEYS ) ),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$result,
			'The tags of the post should be listed with the default fields.'
		);
	}

	/**
	 * The terms of a private post are listed for a user who can read it.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_lists_the_tags_of_a_private_post_the_user_can_read(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where' => array(
					'taxonomy' => 'post_tag',
					'post'     => self::$post_ids['private'],
				),
			)
		);

		$this->assertSame( array( self::$term_ids['alpha'] ), $this->item_ids( $result, 'Private post' ), 'The tags of the private post should be listed.' );
	}

	/**
	 * The post filter works for a post type that is not exposed to abilities.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_post_filter_accepts_a_post_type_that_is_not_exposed(): void {
		register_post_type(
			'tt_book',
			array(
				'public'     => true,
				'taxonomies' => array( 'post_tag' ),
			)
		);
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'tt_book',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $post_id, array( self::$term_ids['alpha'] ), 'post_tag' );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where' => array(
					'taxonomy' => 'post_tag',
					'post'     => $post_id,
				),
			)
		);

		$this->assertSame( array( self::$term_ids['alpha'] ), $this->item_ids( $result, 'Book' ), 'The tags of the book should be listed.' );
	}

	/**
	 * The terms of a post are paged by the ability.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_pages_the_terms_of_a_post(): void {
		$tags = $this->create_tags( 2 );
		wp_set_object_terms( self::$post_ids['published'], $tags['ids'], 'post_tag', true );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where'      => array(
					'taxonomy' => 'post_tag',
					'post'     => self::$post_ids['published'],
				),
				'fields'     => array( 'name' ),
				'pagination' => array(
					'page'     => 2,
					'per_page' => 2,
				),
			)
		);

		$this->assertSame(
			array(
				'items'      => array(
					array(
						'id'   => $tags['ids'][1],
						'name' => 'extra 02',
					),
				),
				'pagination' => array(
					'total'       => 3,
					'total_pages' => 2,
				),
			),
			$result,
			'The second page should hold the last tag of the post by name.'
		);
	}

	/**
	 * The top-level categories are listed by name with the chosen fields, two per page.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_lists_top_level_categories_with_chosen_fields(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$input  = array(
			'where'      => array(
				'taxonomy' => 'category',
				'parent'   => 0,
			),
			'fields'     => array( 'name', 'slug', 'count' ),
			'pagination' => array( 'per_page' => 2 ),
		);
		$keys   = array( 'name', 'slug', 'count' );
		$result = $this->run_ability( $input );

		$this->assertSame(
			array(
				'items'      => array(
					$this->expected_item( self::$term_ids['empty'], $keys ),
					$this->expected_item( self::$term_ids['news'], $keys ),
				),
				'pagination' => array(
					'total'       => 3,
					'total_pages' => 2,
				),
			),
			$result,
			'The first page should hold the first two top-level categories by name.'
		);
		$this->assertSame( array( 'id', 'name', 'slug', 'count' ), array_keys( $result['items'][0] ), 'The item should hold the ID and the chosen fields.' );
		$this->assertSame( 1, $result['items'][1]['count'], 'The count should be an integer.' );

		$input['pagination']['page'] = 2;

		$this->assertSame( array( self::$term_ids['uncategorized'] ), $this->item_ids( $this->run_ability( $input ), 'Page 2' ), 'The second page should hold the last top-level category.' );
	}

	/**
	 * Returns filters with the categories they match, by name.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{where: array<string, mixed>, expected: list<string>}> The cases.
	 */
	public static function data_category_filters(): array {
		return array(
			'no filter'                    => array( array(), array( 'empty', 'news', 'product_updates', 'roadmap', 'uncategorized' ) ),
			'children of a category'       => array( array( 'parent' => 'news' ), array( 'product_updates' ) ),
			'hide empty'                   => array( array( 'hide_empty' => true ), array( 'news' ) ),
			'hide empty set to false'      => array( array( 'hide_empty' => false ), array( 'empty', 'news', 'product_updates', 'roadmap', 'uncategorized' ) ),
			'IDs with unknown and tag IDs' => array( array( 'ids' => array( 'roadmap', 'news', 'missing', 'alpha' ) ), array( 'news', 'roadmap' ) ),
			'slugs with unknown and tags'  => array( array( 'slugs' => array( 'roadmap', 'news', 'nothing', 'beta' ) ), array( 'news', 'roadmap' ) ),
			'IDs and slugs together'       => array(
				array(
					'ids'   => array( 'news', 'roadmap' ),
					'slugs' => array( 'news', 'empty' ),
				),
				array( 'news' ),
			),
		);
	}

	/**
	 * The filters select the categories they name, ordered by name.
	 *
	 * @ticket 66743
	 * @dataProvider data_category_filters
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $where    The filters, with fixture names for IDs.
	 * @param list<string>         $expected The fixture names of the expected categories.
	 */
	public function test_filters_select_the_categories( array $where, array $expected ): void {
		$ids = static function ( array $names ): array {
			return array_map(
				static function ( string $name ): int {
					return 'missing' === $name ? self::MISSING_ID : self::$term_ids[ $name ];
				},
				$names
			);
		};

		if ( isset( $where['parent'] ) ) {
			$where['parent'] = self::$term_ids[ $where['parent'] ];
		}
		if ( isset( $where['ids'] ) ) {
			$where['ids'] = $ids( $where['ids'] );
		}
		if ( isset( $where['slugs'] ) ) {
			$where['slugs'] = array_map(
				static function ( string $name ): string {
					return isset( self::$term_ids[ $name ] ) ? get_term( self::$term_ids[ $name ] )->slug : $name;
				},
				$where['slugs']
			);
		}

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => array( 'taxonomy' => 'category' ) + $where ) );

		$this->assertSame( $ids( $expected ), $this->item_ids( $result, 'Filters' ), 'The matching categories should be listed by name.' );
		$this->assertSame( count( $expected ), $result['pagination']['total'], 'The total should count the matching categories.' );
		$this->assertSame( 1, $result['pagination']['total_pages'], 'The categories should fit on one page.' );
	}

	/**
	 * The registered query arguments of a taxonomy apply, but the paging stays the ability's own.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_taxonomy_query_arguments_apply_with_the_ability_paging(): void {
		register_taxonomy(
			'tt_ordered',
			'post',
			array(
				'show_in_abilities' => true,
				'args'              => array(
					'orderby' => 'slug',
					'order'   => 'DESC',
					'number'  => 1,
					'offset'  => 5,
				),
			)
		);

		$ids = array();
		foreach ( array(
			'Apple'  => 'a-slug',
			'Banana' => 'c-slug',
			'Cherry' => 'b-slug',
		) as $name => $slug ) {
			$ids[ $slug ] = self::factory()->term->create(
				array(
					'taxonomy' => 'tt_ordered',
					'name'     => $name,
					'slug'     => $slug,
				)
			);
		}

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$input = array(
			'where'      => array( 'taxonomy' => 'tt_ordered' ),
			'pagination' => array( 'per_page' => 2 ),
		);

		$this->assertSame( array( $ids['c-slug'], $ids['b-slug'] ), $this->item_ids( $this->run_ability( $input ), 'Page 1' ), 'The first page should hold two terms in the order the taxonomy sets.' );

		$input['pagination']['page'] = 2;
		$result                      = $this->run_ability( $input );

		$this->assertSame( array( $ids['a-slug'] ), $this->item_ids( $result, 'Page 2' ), 'The second page should hold the last term in the order the taxonomy sets.' );
		$this->assertSame(
			array(
				'total'       => 3,
				'total_pages' => 2,
			),
			$result['pagination'],
			'The totals should count every term of the taxonomy.'
		);
	}

	/**
	 * The registered query arguments of a taxonomy apply to the terms of a post as Core applies
	 * them, paging included, and the totals count every term of the post.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_taxonomy_query_arguments_apply_to_the_terms_of_a_post_as_core_applies_them(): void {
		register_taxonomy(
			'tt_limited',
			'post',
			array(
				'show_in_abilities' => true,
				'args'              => array( 'number' => 1 ),
			)
		);

		$post_id = self::$post_ids['published'];
		$names   = array( 'Apple', 'Banana', 'Cherry' );
		foreach ( $names as $name ) {
			self::factory()->term->create(
				array(
					'taxonomy' => 'tt_limited',
					'name'     => $name,
				)
			);
		}
		wp_set_object_terms( $post_id, $names, 'tt_limited' );

		$per_page = 2;
		$expected = wp_list_pluck(
			wp_get_object_terms(
				$post_id,
				'tt_limited',
				array(
					'number' => $per_page,
					'offset' => 0,
				)
			),
			'term_id'
		);

		$this->assertLessThan( $per_page, count( $expected ), 'Precondition: Core should list fewer terms of the post than the page size.' );
		$this->assertNotEmpty( $expected, 'Precondition: Core should list a term of the post.' );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where'      => array(
					'taxonomy' => 'tt_limited',
					'post'     => $post_id,
				),
				'pagination' => array( 'per_page' => $per_page ),
			)
		);

		$this->assertSame( array_map( 'intval', $expected ), $this->item_ids( $result, 'Terms of the post' ), 'The page should hold what Core lists for the post.' );
		$this->assertSame(
			array(
				'total'       => 3,
				'total_pages' => 2,
			),
			$result['pagination'],
			'The totals should count every term of the post.'
		);
	}

	/**
	 * A registered query argument of a taxonomy replaces the ability's own argument of the
	 * same key.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_taxonomy_query_argument_replaces_the_ability_argument(): void {
		register_taxonomy(
			'tt_filled',
			'post',
			array(
				'show_in_abilities' => true,
				'args'              => array( 'hide_empty' => true ),
			)
		);
		$filled = self::factory()->term->create(
			array(
				'taxonomy' => 'tt_filled',
				'name'     => 'Filled',
			)
		);
		self::factory()->term->create(
			array(
				'taxonomy' => 'tt_filled',
				'name'     => 'Unused',
			)
		);
		wp_set_object_terms( self::$post_ids['published'], array( $filled ), 'tt_filled' );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		foreach ( array(
			'absent' => array(),
			'false'  => array( 'hide_empty' => false ),
		) as $case => $where ) {
			$result = $this->run_ability( array( 'where' => array( 'taxonomy' => 'tt_filled' ) + $where ) );

			$this->assertSame( array( $filled ), $this->item_ids( $result, "hide_empty {$case}" ), "With hide_empty {$case}, the taxonomy's own value should apply." );
			$this->assertSame(
				array(
					'total'       => 1,
					'total_pages' => 1,
				),
				$result['pagination'],
				"With hide_empty {$case}, the totals should count only the terms in use."
			);
		}
	}

	/**
	 * Returns how many terms are listed per page by default.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{filter: string|null, per_page: int}> The cases.
	 */
	public static function data_default_page_sizes(): array {
		return array(
			'without IDs or slugs' => array( null, 10 ),
			'with IDs'             => array( 'ids', 100 ),
			'with slugs'           => array( 'slugs', 100 ),
		);
	}

	/**
	 * Ten terms are listed per page by default, a hundred when IDs or slugs are given.
	 *
	 * @ticket 66743
	 * @dataProvider data_default_page_sizes
	 * @since 7.3.0
	 *
	 * @param string|null $filter   The list filter, if any.
	 * @param int         $per_page The expected page size.
	 */
	public function test_default_page_size( ?string $filter, int $per_page ): void {
		$tags  = $this->create_tags( 11 );
		$where = array( 'taxonomy' => 'post_tag' );
		$total = 13;
		if ( null !== $filter ) {
			$where[ $filter ] = $tags[ $filter ];
			$total            = 11;
		}

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => $where ) );

		$this->assertCount( min( $per_page, $total ), $this->item_ids( $result, 'Default page' ), 'The first page should hold the default number of tags.' );
		$this->assertSame(
			array(
				'total'       => $total,
				'total_pages' => (int) ceil( $total / $per_page ),
			),
			$result['pagination'],
			'The totals should follow the default page size.'
		);
	}

	/**
	 * Returns page sizes the schema refuses, with the page size they read as.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{per_page: int, filter: string|null, extra: int, size: int}> The cases.
	 */
	public static function data_page_sizes_out_of_bounds(): array {
		return array(
			'zero without IDs or slugs'      => array( 0, null, 11, 10 ),
			'minus one without IDs or slugs' => array( -1, null, 11, 10 ),
			'zero with IDs'                  => array( 0, 'ids', 11, 100 ),
			'minus one with slugs'           => array( -1, 'slugs', 11, 100 ),
			'above the maximum'              => array( 500, null, 101, 100 ),
		);
	}

	/**
	 * A page size below 1 that reaches the execute callback reads as the default page size,
	 * and one above 100 reads as 100.
	 *
	 * @ticket 66743
	 * @dataProvider data_page_sizes_out_of_bounds
	 * @since 7.3.0
	 *
	 * @param int         $per_page The page size the schema refuses.
	 * @param string|null $filter   The list filter, if any.
	 * @param int         $extra    How many tags to add to the two fixture tags.
	 * @param int         $size     The page size it should read as.
	 */
	public function test_page_size_out_of_bounds_is_bounded( int $per_page, ?string $filter, int $extra, int $size ): void {
		$tags  = $this->create_tags( $extra );
		$where = array( 'taxonomy' => 'post_tag' );
		$total = $extra + 2;
		if ( null !== $filter ) {
			$where[ $filter ] = $tags[ $filter ];
			$total            = $extra;
		}

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_past_validation(
			array(
				'where'      => $where,
				'pagination' => array( 'per_page' => $per_page ),
			),
			"Page size {$per_page}"
		);

		$this->assertCount( min( $size, $total ), $this->item_ids( $result, "Page size {$per_page}" ), "The first page should hold {$size} tags at most." );
		$this->assertSame(
			array(
				'total'       => $total,
				'total_pages' => (int) ceil( $total / $size ),
			),
			$result['pagination'],
			"The totals should follow a page size of {$size}."
		);
	}

	/**
	 * Returns page numbers the schema refuses.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{page: int}> The cases.
	 */
	public static function data_pages_below_one(): array {
		return array(
			'page zero'      => array( 0 ),
			'page minus one' => array( -1 ),
		);
	}

	/**
	 * A page number below 1 that reaches the execute callback reads as page 1.
	 *
	 * @ticket 66743
	 * @dataProvider data_pages_below_one
	 * @since 7.3.0
	 *
	 * @param int $page The page number the schema refuses.
	 */
	public function test_page_below_one_reads_as_the_first_page( int $page ): void {
		$this->create_tags( 11 );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$input = array(
			'where'      => array( 'taxonomy' => 'post_tag' ),
			'pagination' => array( 'per_page' => 5 ),
		);
		$first = $this->run_ability( $input );

		$input['pagination']['page'] = $page;

		$result = $this->run_past_validation( $input, "Page {$page}" );

		$this->assertCount( 5, $this->item_ids( $first, 'Page 1' ), 'The first page should hold five tags.' );
		$this->assertSame( $first, $result, "Page {$page} should give the first page." );
	}

	/**
	 * A page beyond the last one is an error, also with an explicit page size.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_page_beyond_the_last_is_an_error(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_rejected(
			array(
				'where'      => array( 'taxonomy' => 'category' ),
				'pagination' => array(
					'page'     => 4,
					'per_page' => 2,
				),
			),
			'Page 4'
		);

		$this->assert_error( $result, 'terms_invalid_page_number', 404, 'The page number requested is larger than the number of pages available.', 'Page 4' );

		$result = $this->run_ability(
			array(
				'where'      => array( 'taxonomy' => 'category' ),
				'pagination' => array(
					'page'     => 3,
					'per_page' => 2,
				),
			)
		);

		$this->assertSame( array( self::$term_ids['uncategorized'] ), $this->item_ids( $result, 'Page 3' ), 'The last page should hold the last category.' );
	}

	/**
	 * A page that holds terms is returned even when it is beyond the counted pages, which
	 * happens when empty ancestors of used categories are listed but not counted.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_page_that_holds_terms_beyond_the_counted_pages_is_returned(): void {
		self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_category' => array( self::$term_ids['roadmap'] ),
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$input = array(
			'where'      => array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
			),
			'pagination' => array(
				'page'     => 3,
				'per_page' => 1,
			),
		);

		$result = $this->run_ability( $input );

		$this->assertSame( array( self::$term_ids['roadmap'] ), $this->item_ids( $result, 'Page 3' ), 'The third page should hold the used child category.' );
		$this->assertSame(
			array(
				'total'       => 2,
				'total_pages' => 2,
			),
			$result['pagination'],
			'The totals should count only the used categories.'
		);

		$input['pagination']['page'] = 4;
		$result                      = $this->run_rejected( $input, 'Page 4' );

		$this->assert_error( $result, 'terms_invalid_page_number', 404, 'The page number requested is larger than the number of pages available.', 'Page 4' );
	}

	/**
	 * Filters that match nothing give an empty list and zero totals, on any page.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_no_match_gives_an_empty_list(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$empty = array(
			'items'      => array(),
			'pagination' => array(
				'total'       => 0,
				'total_pages' => 0,
			),
		);

		foreach ( array( 1, 3 ) as $page ) {
			$result = $this->run_ability(
				array(
					'where'      => array(
						'taxonomy' => 'category',
						'ids'      => array( self::MISSING_ID, self::$term_ids['alpha'] ),
					),
					'pagination' => array( 'page' => $page ),
				)
			);

			$this->assertSame( $empty, $result, "Page {$page} of no match should be an empty list with zero totals." );
		}
	}

	/**
	 * A total that is not a number counts as zero, and the page of terms is still returned.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_total_that_is_not_a_number_counts_as_zero(): void {
		add_filter(
			'terms_pre_query',
			static function ( $terms, WP_Term_Query $query ) {
				return 'count' === $query->query_vars['fields'] ? 'not a number' : $terms;
			},
			10,
			2
		);

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => array( 'taxonomy' => 'post_tag' ) ) );

		$this->assertSame( array( self::$term_ids['alpha'], self::$term_ids['beta'] ), $this->item_ids( $result, 'Not a number' ), 'The tags should still be listed.' );
		$this->assertSame(
			array(
				'total'       => 0,
				'total_pages' => 0,
			),
			$result['pagination'],
			'The totals should be zero.'
		);
	}

	/**
	 * Returns the terms Core lists for the tags, or for the tags of the published post.
	 *
	 * @since 7.3.0
	 *
	 * @param bool $with_post Whether to list the tags of the published post.
	 * @return array<int, mixed> The result of Core's call.
	 */
	private function core_tag_result( bool $with_post ): array {
		if ( $with_post ) {
			return wp_get_object_terms( self::$post_ids['published'], 'post_tag' );
		}

		return get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
			)
		);
	}

	/**
	 * Returns the filters through which a result that is not a term can reach the ability.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{filter: string, with_post: bool}> The cases.
	 */
	public static function data_result_filters(): array {
		return array(
			'terms of the taxonomy' => array( 'get_terms', false ),
			'terms of a post'       => array( 'wp_get_object_terms', true ),
		);
	}

	/**
	 * Results that are not terms are skipped.
	 *
	 * @ticket 66743
	 * @dataProvider data_result_filters
	 * @since 7.3.0
	 *
	 * @param string $filter    The filter that adds the results.
	 * @param bool   $with_post Whether to filter by the post.
	 */
	public function test_results_that_are_not_terms_are_skipped( string $filter, bool $with_post ): void {
		add_filter(
			$filter,
			static function ( $terms ) {
				if ( ! is_array( $terms ) ) {
					return $terms;
				}

				$terms[] = 'not a term';
				$terms[] = 5;
				$terms[] = (object) array( 'term_id' => self::$term_ids['alpha'] );

				return $terms;
			}
		);

		$core = $this->core_tag_result( $with_post );

		$this->assertContains( 'not a term', $core, 'Precondition: the filter should add the entries on this path.' );

		$where = array( 'taxonomy' => 'post_tag' );
		if ( $with_post ) {
			$where['post'] = self::$post_ids['published'];
		}

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => $where ) );

		$expected = $with_post ? array( self::$term_ids['beta'] ) : array( self::$term_ids['alpha'], self::$term_ids['beta'] );

		$this->assertSame( $expected, $this->item_ids( $result, $filter ), 'Only the terms should be listed.' );
	}

	/**
	 * A term of another taxonomy that a filter adds to the result is skipped.
	 *
	 * @ticket 66743
	 * @dataProvider data_result_filters
	 * @since 7.3.0
	 *
	 * @param string $filter    The filter that adds the term.
	 * @param bool   $with_post Whether to filter by the post.
	 */
	public function test_terms_of_another_taxonomy_are_skipped( string $filter, bool $with_post ): void {
		add_filter(
			$filter,
			static function ( $terms ) {
				if ( ! is_array( $terms ) ) {
					return $terms;
				}

				$terms[] = get_term( self::$term_ids['news'], 'category' );

				return $terms;
			}
		);

		$foreign = array_filter(
			$this->core_tag_result( $with_post ),
			static function ( $term ): bool {
				return $term instanceof WP_Term && 'category' === $term->taxonomy;
			}
		);

		$this->assertCount( 1, $foreign, 'Precondition: the filter should add the category on this path.' );

		$where = array( 'taxonomy' => 'post_tag' );
		if ( $with_post ) {
			$where['post'] = self::$post_ids['published'];
		}

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => $where ) );

		$expected = $with_post ? array( self::$term_ids['beta'] ) : array( self::$term_ids['alpha'], self::$term_ids['beta'] );

		$this->assertSame( $expected, $this->item_ids( $result, $filter ), 'Only the tags should be listed.' );
	}

	/**
	 * Returns ID list entries below 1.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{entry: int|string}> The entries, with `-` and a fixture name for a negative ID.
	 */
	public static function data_id_entries_below_one(): array {
		return array(
			'ID zero'     => array( 0 ),
			'negative ID' => array( '-news' ),
		);
	}

	/**
	 * An entry of the ID list below 1 that reaches the execute callback is left out, and the
	 * other IDs are queried.
	 *
	 * @ticket 66743
	 * @dataProvider data_id_entries_below_one
	 * @since 7.3.0
	 *
	 * @param int|string $entry The entry, with `-` and a fixture name for a negative ID.
	 */
	public function test_id_entry_below_one_is_left_out( $entry ): void {
		if ( is_string( $entry ) ) {
			$entry = -self::$term_ids[ substr( $entry, 1 ) ];
		}

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_past_validation(
			array(
				'where' => array(
					'taxonomy' => 'category',
					'ids'      => array( self::$term_ids['roadmap'], $entry ),
				),
			),
			"Entry {$entry}"
		);

		$this->assertSame(
			array(
				'items'      => array( $this->expected_item( self::$term_ids['roadmap'], self::DEFAULT_KEYS ) ),
				'pagination' => array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			$result,
			'Only the valid ID should be queried.'
		);
	}

	/**
	 * Returns taxonomies with the error a parent filter below 0 gives on them.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{taxonomy: string, message: string}> The cases.
	 */
	public static function data_parent_filters_below_zero(): array {
		return array(
			'hierarchical taxonomy' => array( 'category', 'The parent filter must be a non-negative integer.' ),
			'flat taxonomy'         => array( 'post_tag', 'The "post_tag" taxonomy is not hierarchical.' ),
		);
	}

	/**
	 * A parent filter below 0 that reaches the execute callback is refused; on a flat taxonomy
	 * the error for a parent on that taxonomy comes first.
	 *
	 * @ticket 66743
	 * @dataProvider data_parent_filters_below_zero
	 * @since 7.3.0
	 *
	 * @param string $taxonomy The taxonomy.
	 * @param string $message  The expected message.
	 */
	public function test_parent_filter_below_zero_is_an_error( string $taxonomy, string $message ): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_past_validation(
			array(
				'where' => array(
					'taxonomy' => $taxonomy,
					'parent'   => -1,
				),
			),
			"Parent -1 on {$taxonomy}"
		);

		$this->assert_error( $result, 'terms_invalid_filter', 400, $message, "Parent -1 on {$taxonomy}", array( 'params' => array( 'where[parent]' => $message ) ) );
	}

	/**
	 * Returns ID and slug lists the schema refuses, with the parameter they are reported as.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{filter: string, values: list<int|string>, message: string}> The cases.
	 */
	public static function data_lists_without_a_valid_entry(): array {
		$ids   = 'The ids filter must list one or more valid term IDs.';
		$slugs = 'The slugs filter must list one or more valid term slugs.';

		return array(
			'only the ID zero'       => array( 'ids', array( 0 ), $ids ),
			'only a negative ID'     => array( 'ids', array( '-news' ), $ids ),
			'zero and a negative ID' => array( 'ids', array( 0, '-news' ), $ids ),
			'no ID'                  => array( 'ids', array(), $ids ),
			'no slug'                => array( 'slugs', array(), $slugs ),
		);
	}

	/**
	 * An ID list with no entry of at least 1, and a slug list with no entry, that reach the
	 * execute callback are refused.
	 *
	 * @ticket 66743
	 * @dataProvider data_lists_without_a_valid_entry
	 * @since 7.3.0
	 *
	 * @param string           $filter  The list filter.
	 * @param list<int|string> $values  The entries, with fixture names for IDs and `-` for a negative ID.
	 * @param string           $message The expected message.
	 */
	public function test_list_without_a_valid_entry_is_an_error( string $filter, array $values, string $message ): void {
		$values = array_map(
			static function ( $value ) {
				if ( ! is_string( $value ) ) {
					return $value;
				}

				return '-' === $value[0] ? -self::$term_ids[ substr( $value, 1 ) ] : self::$term_ids[ $value ];
			},
			$values
		);

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_past_validation(
			array(
				'where' => array(
					'taxonomy' => 'category',
					$filter    => $values,
				),
			),
			$filter
		);

		$this->assert_error( $result, 'terms_invalid_filter', 400, $message, $filter, array( 'params' => array( "where[{$filter}]" => $message ) ) );
	}

	/**
	 * Returns each field with the taxonomy it is asked for.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{fields: list<string>, taxonomy: string, keys: list<string>}> The cases.
	 */
	public static function data_field_lists(): array {
		$cases = array();
		foreach ( self::ITEM_KEYS as $key ) {
			$cases[ "{$key} of a category" ] = array( array( $key ), 'category', array_values( array_unique( array( 'id', $key ) ) ) );
		}

		return $cases + array(
			'parent of a tag'         => array( array( 'parent' ), 'post_tag', array( 'id' ) ),
			'every field of a tag'    => array( self::ITEM_KEYS, 'post_tag', array( 'id', 'taxonomy', 'name', 'slug', 'description', 'count', 'link' ) ),
			'every field reversed'    => array( array_reverse( self::ITEM_KEYS ), 'category', self::ITEM_KEYS ),
			'empty list'              => array( array(), 'category', self::DEFAULT_KEYS ),
			'fields in another order' => array( array( 'link', 'name', 'id' ), 'category', array( 'id', 'name', 'link' ) ),
		);
	}

	/**
	 * The item holds the ID and exactly the requested keys, in their fixed order, and the parent
	 * only for a hierarchical taxonomy.
	 *
	 * @ticket 66743
	 * @dataProvider data_field_lists
	 * @since 7.3.0
	 *
	 * @param list<string> $fields   The requested fields.
	 * @param string       $taxonomy The taxonomy.
	 * @param list<string> $keys     The expected keys, in their order.
	 */
	public function test_fields_select_the_keys( array $fields, string $taxonomy, array $keys ): void {
		$term_id = 'category' === $taxonomy ? self::$term_ids['product_updates'] : self::$term_ids['beta'];

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where'  => array(
					'taxonomy' => $taxonomy,
					'ids'      => array( $term_id ),
				),
				'fields' => $fields,
			)
		);

		$this->assertSame( array( $term_id ), $this->item_ids( $result, 'Fields' ), 'The term should be listed.' );
		$this->assertSame( $keys, array_keys( $result['items'][0] ), 'The item should hold exactly the expected keys in their order.' );
		$this->assertSame( $this->expected_item( $term_id, $keys ), $result['items'][0], 'The item should hold the values of the term.' );
	}

	/**
	 * Every value of an item has its type, and the name and description are the stored values.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_item_holds_the_stored_values(): void {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy'    => 'category',
				'name'        => 'Tips & <em>tricks</em>',
				'description' => '<strong>Bold</strong> &amp; plain',
				'parent'      => self::$term_ids['news'],
			)
		);
		$stored  = get_term( $term_id );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where'  => array(
					'taxonomy' => 'category',
					'ids'      => array( $term_id ),
				),
				'fields' => self::ITEM_KEYS,
			)
		);

		$this->assertSame(
			array(
				array(
					'id'          => $term_id,
					'taxonomy'    => 'category',
					'name'        => $stored->name,
					'slug'        => $stored->slug,
					'description' => $stored->description,
					'parent'      => self::$term_ids['news'],
					'count'       => 0,
					'link'        => get_term_link( $stored ),
				),
			),
			$result['items'] ?? null,
			'The item should hold the stored values with their types.'
		);
		$this->assertSame( 'Tips &amp; tricks', $result['items'][0]['name'], 'The name should be the stored name, not decoded.' );
		$this->assertSame( '<strong>Bold</strong> &amp; plain', $result['items'][0]['description'], 'The description should be the stored description, not rendered.' );
	}

	/**
	 * The link is empty when the term link is not a string.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_link_is_empty_when_the_term_link_is_an_error(): void {
		add_filter(
			'term_link',
			static function () {
				return new WP_Error( 'tt_no_link', 'No link.' );
			}
		);

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where'  => array(
					'taxonomy' => 'post_tag',
					'ids'      => array( self::$term_ids['beta'] ),
				),
				'fields' => array( 'link' ),
			)
		);

		$this->assertSame(
			array(
				array(
					'id'   => self::$term_ids['beta'],
					'link' => '',
				),
			),
			$result['items'] ?? null,
			'The link should be empty.'
		);
	}

	/**
	 * An exposed taxonomy that is not public can be queried.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_exposed_taxonomy_that_is_not_public_can_be_queried(): void {
		register_taxonomy(
			'tt_private',
			'post',
			array(
				'public'            => false,
				'show_in_abilities' => true,
			)
		);
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'tt_private',
				'name'     => 'Internal',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => array( 'taxonomy' => 'tt_private' ) ) );

		$this->assertSame( array( $this->expected_item( $term_id, self::DEFAULT_KEYS ) ), $result['items'] ?? null, 'The term should be listed with the default fields.' );
	}
}
