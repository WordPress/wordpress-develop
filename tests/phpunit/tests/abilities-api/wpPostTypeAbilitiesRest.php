<?php

declare( strict_types=1 );

/**
 * Tests for the post type get ability via the REST API.
 *
 * @covers WP_Post_Type_Abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpPostTypeAbilitiesRest extends WP_Test_REST_TestCase {

	/**
	 * REST API route for the post get ability.
	 */
	private const ROUTE = '/wp-abilities/v1/abilities/core/post-type/post/get/run';

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Test post IDs indexed 1-6 matching the fixture table.
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
	}

	/**
	 * Set up each test with an authenticated editor user.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::$editor_id );
	}

	/**
	 * Dispatches a GET request to the post type get ability endpoint.
	 *
	 * @param array $input Input parameters for the ability.
	 * @return WP_REST_Response The response.
	 */
	private function dispatch_get_ability( array $input = array() ): WP_REST_Response {
		$request = new WP_REST_Request( 'GET', self::ROUTE );
		if ( ! empty( $input ) ) {
			$request->set_query_params( array( 'input' => $input ) );
		}
		return rest_get_server()->dispatch( $request );
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
	 * Tests that the ability run route is registered.
	 */
	public function test_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		// The route pattern covers all ability names including this one.
		$this->assertArrayHasKey(
			'/wp-abilities/v1/abilities/(?P<name>[a-zA-Z0-9\\-\\/]+?)/run',
			$routes
		);
	}

	/**
	 * Tests that POST method is rejected for this readonly ability.
	 */
	public function test_post_method_rejected(): void {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => array() ) ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 405, $response->get_status() );
	}

	/**
	 * Tests retrieving a single post by ID.
	 */
	public function test_get_single_post_by_id(): void {
		$response = $this->dispatch_get_ability( array( 'id' => self::$post_ids[1] ) );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
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
	 */
	public function test_get_single_post_with_meta_and_taxonomies(): void {
		$response = $this->dispatch_get_ability(
			array(
				'id'      => self::$post_ids[1],
				'include' => array(
					'meta'       => true,
					'taxonomies' => true,
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		// Meta should contain the public meta keys.
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertArrayHasKey( 'footnotes', $data['meta'] );
		$this->assertArrayHasKey( 'a', $data['meta'] );
		$this->assertArrayHasKey( 'b', $data['meta'] );
		$this->assertSame( '23', $data['meta']['a'] );

		// Taxonomies should contain post_tag terms.
		$this->assertArrayHasKey( 'taxonomies', $data );
		$this->assertArrayHasKey( 'post_tag', $data['taxonomies'] );
		$tag_slugs = array_column( $data['taxonomies']['post_tag'], 'slug' );
		$this->assertContains( 't-23', $tag_slugs );
		$this->assertContains( 'c-1', $tag_slugs );
	}

	/**
	 * Tests that requesting a non-existent post returns 404.
	 */
	public function test_get_single_post_not_found(): void {
		$response = $this->dispatch_get_ability( array( 'id' => 999999 ) );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Tests that query mode returns paginated results.
	 */
	public function test_query_returns_paginated_results(): void {
		$response = $this->dispatch_get_ability(
			array(
				'per_page' => 2,
				'page'     => 1,
			)
		);

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'posts', $data );
		$this->assertCount( 2, $data['posts'] );
		$this->assertSame( 6, $data['total'] );
		$this->assertSame( 3, $data['total_pages'] );
	}

	/**
	 * Tests meta query with EXISTS operator finds only the post with footnotes.
	 */
	public function test_meta_query_exists(): void {
		$response = $this->dispatch_get_ability(
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

		$this->assertSame( 200, $response->get_status() );

		$data     = $response->get_data();
		$post_ids = $this->get_response_post_ids( $data );

		$this->assertSame( 1, $data['total'] );
		$this->assertContains( self::$post_ids[1], $post_ids );
	}

	/**
	 * Tests nested meta query: a=23 AND (b=1 OR c=1).
	 *
	 * Should match posts 1 and 2.
	 */
	public function test_meta_query_nested_and_or(): void {
		$response = $this->dispatch_get_ability(
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

		$this->assertSame( 200, $response->get_status() );

		$data     = $response->get_data();
		$post_ids = $this->get_response_post_ids( $data );

		$this->assertSame( 2, $data['total'] );
		$this->assertContains( self::$post_ids[1], $post_ids );
		$this->assertContains( self::$post_ids[2], $post_ids );
	}

	/**
	 * Tests nested tax query: tag t-23 AND (tag c-1 OR tag b-1).
	 *
	 * Should match posts 1 and 2.
	 */
	public function test_tax_query_nested_and_or(): void {
		$response = $this->dispatch_get_ability(
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

		$this->assertSame( 200, $response->get_status() );

		$data     = $response->get_data();
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
	 */
	public function test_date_query_nested_and_or(): void {
		$response = $this->dispatch_get_ability(
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

		$this->assertSame( 200, $response->get_status() );

		$data     = $response->get_data();
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
	 * Tests that unauthenticated requests are rejected.
	 */
	public function test_unauthenticated_query_rejected(): void {
		wp_set_current_user( 0 );

		$response = $this->dispatch_get_ability( array() );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	/**
	 * Tests that authenticated editor can query posts.
	 */
	public function test_authenticated_query_succeeds(): void {
		$response = $this->dispatch_get_ability( array() );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'posts', $data );
		$this->assertGreaterThan( 0, $data['total'] );
	}

	/**
	 * Tests ordering by title ascending.
	 */
	public function test_query_with_ordering(): void {
		$response = $this->dispatch_get_ability(
			array(
				'order'    => array(
					'orderby'   => 'title',
					'direction' => 'asc',
				),
				'per_page' => 100,
			)
		);

		$this->assertSame( 200, $response->get_status() );

		$data   = $response->get_data();
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
}
