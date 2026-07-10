<?php

declare( strict_types=1 );

/**
 * Tests dispatching the core/read-content ability through the Abilities REST run endpoint.
 *
 * @covers WP_Content_Abilities
 *
 * @group abilities-api
 * @group rest-api
 */
class Tests_REST_API_WpRestAbilitiesContentController extends WP_UnitTestCase {

	/**
	 * The REST server instance for the current test.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * The run route for the core/read-content ability.
	 *
	 * @var string
	 */
	const RUN_ROUTE = '/wp-abilities/v1/abilities/core/read-content/run';

	/**
	 * Sets up users and registers the core abilities.
	 *
	 * @since 7.1.0
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

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
	 * Cleans up registered abilities and categories.
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

		parent::tear_down_after_class();
	}

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		wp_set_current_user( self::$admin_id );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Builds a GET run request with the given ability input.
	 *
	 * @param array<string, mixed> $input The ability input.
	 * @return WP_REST_Request The request.
	 */
	private function run_request( array $input ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', self::RUN_ROUTE );
		$request->set_query_params( array( 'input' => $input ) );
		return $request;
	}

	public function test_logged_out_user_receives_401(): void {
		wp_set_current_user( 0 );

		$response = $this->server->dispatch( $this->run_request( array( 'post_type' => 'post' ) ) );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_subscriber_requesting_drafts_receives_403(): void {
		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'post_type' => 'post',
					'status'    => array( 'draft' ),
				)
			)
		);

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_subscriber_requesting_published_posts_receives_readable_fields(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Published for subscriber via REST',
				'post_content' => 'Subscriber REST body.',
				'post_status'  => 'publish',
			)
		);

		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'post_type' => 'post',
					'fields'    => array( 'id', 'title_rendered', 'content_rendered' ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( $post_id, wp_list_pluck( $data['posts'], 'id' ) );

		$post_index = array_search( $post_id, wp_list_pluck( $data['posts'], 'id' ), true );
		$this->assertIsInt( $post_index );

		$post = $data['posts'][ $post_index ];
		$this->assertSame( 'Published for subscriber via REST', $post['title_rendered'] );
		$this->assertStringContainsString( 'Subscriber REST body.', $post['content_rendered'] );
		$this->assertArrayNotHasKey( 'content_raw', $post );
	}

	public function test_subscriber_requesting_raw_fields_receives_403(): void {
		wp_set_current_user( self::$subscriber_id );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'post_type' => 'post',
					'fields'    => array( 'content_raw' ),
				)
			)
		);

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_admin_query_returns_published_posts(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Published via REST',
				'post_status' => 'publish',
			)
		);

		$response = $this->server->dispatch( $this->run_request( array( 'post_type' => 'post' ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'posts', $data );
		$this->assertContains( $post_id, wp_list_pluck( $data['posts'], 'id' ) );
	}

	public function test_admin_query_include_limits_results(): void {
		$first  = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$second = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-02-01 10:00:00',
			)
		);
		$third  = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-03-01 10:00:00',
			)
		);

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'post_type' => 'post',
					// Deliberately pass IDs in the opposite of the expected date order.
					'include'   => array( $first, $third ),
					'fields'    => array( 'id' ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $third, $first ), wp_list_pluck( $data['posts'], 'id' ) );
		$this->assertNotContains( $second, wp_list_pluck( $data['posts'], 'id' ) );
	}

	public function test_get_single_post_by_id(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->server->dispatch( $this->run_request( array( 'id' => $post_id ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $post_id, $data['id'] );
		$this->assertArrayNotHasKey( 'posts', $data );
		$this->assertArrayNotHasKey( 'total', $data );
	}

	public function test_get_single_post_by_slug(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_name'   => 'rest-content-slug',
				'post_status' => 'publish',
			)
		);

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'post_type' => 'post',
					'slug'      => 'rest-content-slug',
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $post_id, $data['id'] );
		$this->assertSame( 'rest-content-slug', $data['slug'] );
		$this->assertArrayNotHasKey( 'posts', $data );
	}

	public function test_wrong_http_method_returns_405(): void {
		$request = new WP_REST_Request( 'POST', self::RUN_ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => array( 'post_type' => 'post' ) ) ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 405, $response->get_status() );
		$this->assertSame( 'rest_ability_invalid_method', $response->get_data()['code'] );
	}

	public function test_pagination_returns_totals_in_body(): void {
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'post_type' => 'post',
					'per_page'  => 2,
					'page'      => 1,
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $data['posts'] );
		$this->assertGreaterThanOrEqual( 3, $data['total'] );
		$this->assertSame( (int) ceil( $data['total'] / 2 ), $data['total_pages'] );
	}

	public function test_out_of_range_page_returns_400(): void {
		self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->server->dispatch(
			$this->run_request(
				array(
					'post_type' => 'post',
					'per_page'  => 1,
					'page'      => 999,
				)
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'content_invalid_page_number', $response->get_data()['code'] );
	}
}
