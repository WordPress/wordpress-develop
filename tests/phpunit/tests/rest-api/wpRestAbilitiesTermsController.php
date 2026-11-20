<?php

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/abilities-api/testcase-terms-ability.php';

/**
 * Tests running the term abilities through the run route.
 *
 * @covers WP_Abilities_Terms
 *
 * @group abilities-api
 * @group restapi
 */
class Tests_REST_API_WpRestAbilitiesTermsController extends WP_Abilities_Terms_UnitTestCase {

	/**
	 * The server instance for the current test.
	 *
	 * @since 7.3.0
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Sets up the server and registers the term abilities.
	 *
	 * @since 7.3.0
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$this->register_abilities();
	}

	/**
	 * Removes the server.
	 *
	 * @since 7.3.0
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Builds a run request for a term ability.
	 *
	 * A POST request carries the input in a JSON body, any other request in the query string.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $method  The HTTP method.
	 * @param string               $ability The ability name.
	 * @param array<string, mixed> $input   The ability input.
	 * @return WP_REST_Request The request.
	 */
	private function run_request( string $method, string $ability, array $input ): WP_REST_Request {
		$request = new WP_REST_Request( $method, "/wp-abilities/v1/abilities/{$ability}/run" );

		if ( 'POST' === $method ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( array( 'input' => $input ) ) );
		} else {
			$request->set_query_params( array( 'input' => $input ) );
		}

		return $request;
	}

	/**
	 * Dispatches a request that must be rejected, and asserts its status and code and that
	 * nothing stored changed.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @param int             $status  The expected status.
	 * @param string          $code    The expected error code.
	 * @param string          $context What the request was, for the assertion messages.
	 * @return array<string, mixed> The response data.
	 */
	private function dispatch_rejected( WP_REST_Request $request, int $status, string $code, string $context ): array {
		$before   = $this->snapshot();
		$response = $this->server->dispatch( $request );

		$this->assertSame( $before, $this->snapshot(), "{$context}: nothing stored should change." );
		$this->assertSame( $status, $response->get_status(), "{$context}: the status should be {$status}." );
		$this->assertSame( $code, $response->get_data()['code'] ?? null, "{$context}: the error code should be {$code}." );

		return $response->get_data();
	}

	/**
	 * Query runs with GET and the input in the query string, where numbers are strings.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_query_runs_with_get_and_the_input_in_the_query_string(): void {
		$this->login_as( 'subscriber' );

		$response = $this->server->dispatch(
			$this->run_request(
				'GET',
				'core/terms-query',
				array(
					'where'      => array(
						'taxonomy' => 'category',
						'parent'   => '0',
					),
					'fields'     => array( 'name' ),
					'pagination' => array( 'per_page' => '2' ),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The query request should succeed.' );
		$this->assertSame(
			array(
				'items'      => array(
					array(
						'id'   => self::$term_ids['empty'],
						'name' => 'Empty',
					),
					array(
						'id'   => self::$term_ids['news'],
						'name' => 'News',
					),
				),
				'pagination' => array(
					'total'       => 3,
					'total_pages' => 2,
				),
			),
			$response->get_data(),
			'The first two top-level categories should be returned.'
		);
	}

	/**
	 * A logged-out visitor cannot query.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_logged_out_query_returns_401(): void {
		$this->login_as( 'logged_out' );

		$this->dispatch_rejected( $this->run_request( 'GET', 'core/terms-query', array( 'where' => array( 'taxonomy' => 'category' ) ) ), 401, 'rest_ability_cannot_execute', 'Logged out' );
	}

	/**
	 * Create runs with POST and the input in a JSON body.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_create_runs_with_post_and_a_json_body(): void {
		$this->login_as( 'editor' );

		$response = $this->server->dispatch(
			$this->run_request(
				'POST',
				'core/term-create',
				array(
					'data'   => array(
						'taxonomy' => 'category',
						'name'     => 'Over the run route',
						'parent'   => self::$term_ids['news'],
					),
					'fields' => array( 'name', 'parent' ),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The create request should succeed.' );

		$data = $response->get_data();

		$this->assertSame( array( 'id', 'name', 'parent' ), array_keys( $data ), 'The created term should be returned in the chosen fields.' );
		$this->assertSame( 'Over the run route', get_term( $data['id'], 'category' )->name, 'The created term should be stored.' );
		$this->assertSame( self::$term_ids['news'], $data['parent'], 'The parent should be returned.' );
	}

	/**
	 * A subscriber cannot create a category over the run route.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_subscriber_create_returns_403(): void {
		$this->login_as( 'subscriber' );

		$input = array(
			'data' => array(
				'taxonomy' => 'category',
				'name'     => 'Refused',
			),
		);

		$this->dispatch_rejected( $this->run_request( 'POST', 'core/term-create', $input ), 403, 'rest_ability_cannot_execute', 'Subscriber' );
	}

	/**
	 * Update runs with POST and the input in a JSON body.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_update_runs_with_post_and_a_json_body(): void {
		$this->login_as( 'editor' );

		$response = $this->server->dispatch(
			$this->run_request(
				'POST',
				'core/term-update',
				array(
					'where'  => array(
						'taxonomy' => 'post_tag',
						'id'       => self::$term_ids['beta'],
					),
					'data'   => array( 'description' => 'Updated over the run route' ),
					'fields' => array( 'description' ),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The update request should succeed.' );
		$this->assertSame(
			array(
				'id'          => self::$term_ids['beta'],
				'description' => 'Updated over the run route',
			),
			$response->get_data(),
			'The updated term should be returned in the chosen fields.'
		);
	}

	/**
	 * A user who may not edit the term cannot update it over the run route.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_update_by_a_user_who_may_not_edit_the_term_returns_403(): void {
		$this->login_as( 'author' );

		$input = array(
			'where' => array(
				'taxonomy' => 'category',
				'id'       => self::$term_ids['news'],
			),
			'data'  => array( 'description' => 'Refused' ),
		);

		$this->dispatch_rejected( $this->run_request( 'POST', 'core/term-update', $input ), 403, 'rest_ability_cannot_execute', 'Author' );
	}

	/**
	 * Delete runs with DELETE and the input in the query string, where the ID is a string.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_delete_runs_with_delete_and_the_input_in_the_query_string(): void {
		$this->login_as( 'editor' );

		$response = $this->server->dispatch(
			$this->run_request(
				'DELETE',
				'core/term-delete',
				array(
					'where' => array(
						'taxonomy' => 'post_tag',
						'id'       => (string) self::$term_ids['beta'],
					),
				)
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The delete request should succeed.' );
		$this->assertSame( array( 'outcome' => 'deleted' ), $response->get_data(), 'The outcome should be deleted.' );
		$this->assertNull( get_term( self::$term_ids['beta'] ), 'The tag should be gone.' );
	}

	/**
	 * Returns errors of the abilities with the response they give.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{method: string, ability: string, input: array<string, mixed>, status: int, code: string, data: array<string, mixed>}> The cases.
	 */
	public static function data_ability_errors(): array {
		$flat = 'The "post_tag" taxonomy is not hierarchical.';

		return array(
			'parent filter on tags'     => array(
				'GET',
				'core/terms-query',
				array(
					'where' => array(
						'taxonomy' => 'post_tag',
						'parent'   => 0,
					),
				),
				400,
				'terms_invalid_filter',
				array( 'params' => array( 'where[parent]' => $flat ) ),
			),
			'update of a missing term'  => array(
				'POST',
				'core/term-update',
				array(
					'where' => array(
						'taxonomy' => 'category',
						'id'       => self::MISSING_ID,
					),
					'data'  => array( 'name' => 'Nobody' ),
				),
				404,
				'term_not_found',
				array(),
			),
			'create of an existing tag' => array(
				'POST',
				'core/term-create',
				array(
					'data' => array(
						'taxonomy' => 'post_tag',
						'name'     => 'alpha',
					),
				),
				400,
				'term_exists',
				array( 'term_id' => 'alpha' ),
			),
			'delete of the default'     => array(
				'DELETE',
				'core/term-delete',
				array(
					'where' => array(
						'taxonomy' => 'category',
						'id'       => 'uncategorized',
					),
				),
				400,
				'term_is_default',
				array(),
			),
		);
	}

	/**
	 * Errors of the abilities reach the response with their status and data.
	 *
	 * @ticket 66743
	 * @dataProvider data_ability_errors
	 * @since 7.3.0
	 *
	 * @param string               $method  The HTTP method.
	 * @param string               $ability The ability name.
	 * @param array<string, mixed> $input   The input, with fixture names for term IDs.
	 * @param int                  $status  The expected status.
	 * @param string               $code    The expected error code.
	 * @param array<string, mixed> $data    The expected error data next to the status.
	 */
	public function test_ability_errors_reach_the_response( string $method, string $ability, array $input, int $status, string $code, array $data ): void {
		if ( isset( $input['where']['id'] ) && is_string( $input['where']['id'] ) ) {
			$input['where']['id'] = self::$term_ids[ $input['where']['id'] ];
		}
		if ( isset( $data['term_id'] ) ) {
			$data['term_id'] = self::$term_ids[ $data['term_id'] ];
		}

		$this->login_as( 'editor' );

		$response = $this->dispatch_rejected( $this->run_request( $method, $ability, $input ), $status, $code, $code );

		$this->assertSameSetsWithIndex( array( 'status' => $status ) + $data, $response['data'] ?? null, 'The error data should reach the response.' );
	}

	/**
	 * Returns requests with a method the ability does not take.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{method: string, ability: string}> The cases.
	 */
	public static function data_wrong_methods(): array {
		return array(
			'query with POST'    => array( 'POST', 'core/terms-query' ),
			'create with GET'    => array( 'GET', 'core/term-create' ),
			'create with DELETE' => array( 'DELETE', 'core/term-create' ),
			'update with GET'    => array( 'GET', 'core/term-update' ),
			'update with DELETE' => array( 'DELETE', 'core/term-update' ),
			'delete with GET'    => array( 'GET', 'core/term-delete' ),
			'delete with POST'   => array( 'POST', 'core/term-delete' ),
		);
	}

	/**
	 * A request with a method the ability does not take is refused and stores nothing.
	 *
	 * @ticket 66743
	 * @dataProvider data_wrong_methods
	 * @since 7.3.0
	 *
	 * @param string $method  The HTTP method.
	 * @param string $ability The ability name.
	 */
	public function test_wrong_method_returns_405( string $method, string $ability ): void {
		$this->login_as( 'administrator' );

		$input = array(
			'where' => array(
				'taxonomy' => 'category',
				'id'       => self::$term_ids['empty'],
			),
			'data'  => array(
				'taxonomy' => 'category',
				'name'     => 'Wrong method',
			),
		);

		$this->dispatch_rejected( $this->run_request( $method, $ability, $input ), 405, 'rest_ability_invalid_method', "{$method} {$ability}" );
	}

	/**
	 * Input that fails the schema is refused and stores nothing.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_create_with_input_that_fails_the_schema_returns_400(): void {
		$this->login_as( 'administrator' );

		$input = array(
			'data' => array(
				'taxonomy' => 'category',
				'name'     => '',
			),
		);

		$this->dispatch_rejected( $this->run_request( 'POST', 'core/term-create', $input ), 400, 'ability_invalid_input', 'Empty name' );
	}
}
