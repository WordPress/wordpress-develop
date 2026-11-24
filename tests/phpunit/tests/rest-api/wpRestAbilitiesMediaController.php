<?php

declare( strict_types=1 );

/**
 * Tests running the media abilities through the Abilities API run route.
 *
 * @covers WP_Abilities_Media
 *
 * @group abilities-api
 * @group restapi
 */
class Tests_REST_API_WpRestAbilitiesMediaController extends WP_UnitTestCase {

	/**
	 * The server instance for the current test.
	 *
	 * @since 7.3.0
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Shared user IDs keyed by role.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	protected static $user_ids = array();

	/**
	 * Shared attachment IDs keyed by fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	protected static $attachment_ids = array();

	/**
	 * Creates the shared users and attachments and registers the core abilities.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		foreach ( array( 'administrator', 'editor', 'author', 'subscriber' ) as $role ) {
			self::$user_ids[ $role ] = $factory->user->create( array( 'role' => $role ) );
		}

		foreach ( array( 'query', 'update', 'delete', 'trash' ) as $name ) {
			self::$attachment_ids[ $name ] = $factory->attachment->create_object(
				array(
					'file'           => "2026/01/mdt-route-{$name}.jpg",
					'post_author'    => self::$user_ids['author'],
					'post_parent'    => 0,
					'post_status'    => 'inherit',
					'post_mime_type' => 'image/jpeg',
					'post_title'     => "Route {$name}",
				)
			);
		}

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

		/*
		 * Restore the hooks right away instead of after the class. The first test of a run
		 * snapshots the hooks and every test resets them to that snapshot, so changes left
		 * here would leak into every later test whenever this class runs first.
		 */
		remove_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		remove_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );
	}

	/**
	 * Unregisters the abilities and categories and forgets the shared fixtures.
	 *
	 * @since 7.3.0
	 */
	public static function wpTearDownAfterClass(): void {
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		self::$user_ids       = array();
		self::$attachment_ids = array();
	}

	/**
	 * Sets up the server.
	 *
	 * @since 7.3.0
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	/**
	 * Removes the server and the files a test added.
	 *
	 * @since 7.3.0
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Returns the run route of a media ability.
	 *
	 * @since 7.3.0
	 *
	 * @param string $operation The operation: query, upload, update or delete.
	 * @return string The route.
	 */
	private static function route( string $operation ): string {
		return "/wp-abilities/v1/abilities/core/media-{$operation}/run";
	}

	/**
	 * Dispatches a run request with the input in the query string.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $method    The HTTP method.
	 * @param string               $operation The operation.
	 * @param array<string, mixed> $input     The input, with string values as a query string has them.
	 * @return WP_REST_Response The response.
	 */
	private function dispatch_query( string $method, string $operation, array $input ): WP_REST_Response {
		$request = new WP_REST_Request( $method, self::route( $operation ) );
		$request->set_query_params( array( 'input' => $input ) );

		return $this->server->dispatch( $request );
	}

	/**
	 * Dispatches a run request with the input in a JSON body.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $method    The HTTP method.
	 * @param string               $operation The operation.
	 * @param array<string, mixed> $input     The input.
	 * @return WP_REST_Response The response.
	 */
	private function dispatch_json( string $method, string $operation, array $input ): WP_REST_Response {
		$request = new WP_REST_Request( $method, self::route( $operation ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'input' => $input ) ) );

		return $this->server->dispatch( $request );
	}

	/**
	 * Asserts the status and error code of a response.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_REST_Response $response The response.
	 * @param int              $status   The expected status.
	 * @param string           $code     The expected error code.
	 * @param string           $context  What the request was, for the assertion messages.
	 */
	private function assert_error_response( WP_REST_Response $response, int $status, string $code, string $context ): void {
		$this->assertSame( $status, $response->get_status(), "{$context}: the status should be {$status}." );
		$this->assertSame( $code, $response->get_data()['code'] ?? null, "{$context}: the error code should be {$code}." );
	}

	/**
	 * Dispatches a request that should be refused and asserts the error and that a target
	 * attachment still exists.
	 *
	 * GET and DELETE requests carry the input in the query string, POST requests in a JSON body.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $operation  The operation.
	 * @param string               $method     The HTTP method.
	 * @param array<string, mixed> $input      The input, without the target attachment.
	 * @param string|null          $attachment The target attachment as a fixture name, or null for none.
	 * @param int                  $status     The expected status.
	 * @param string               $code       The expected error code.
	 * @param string               $context    What the request is, for the assertion messages.
	 */
	private function assert_refused_request( string $operation, string $method, array $input, ?string $attachment, int $status, string $code, string $context ): void {
		$in_query_string = 'POST' !== $method;
		$attachment_id   = null === $attachment ? 0 : self::$attachment_ids[ $attachment ];
		if ( $attachment_id ) {
			$input['where'] = array( 'id' => $in_query_string ? (string) $attachment_id : $attachment_id );
		}

		$response = $in_query_string
			? $this->dispatch_query( $method, $operation, $input )
			: $this->dispatch_json( $method, $operation, $input );

		$this->assert_error_response( $response, $status, $code, $context );
		if ( $attachment_id ) {
			$this->assertInstanceOf( WP_Post::class, get_post( $attachment_id ), "{$context}: the attachment should still exist." );
		}
	}

	/**
	 * A query runs with GET and the input in the query string.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_query_runs_with_get(): void {
		wp_set_current_user( self::$user_ids['editor'] );

		$response = $this->dispatch_query(
			'GET',
			'query',
			array(
				'where'  => array( 'ids' => array( (string) self::$attachment_ids['query'] ) ),
				'fields' => array( 'id', 'title.raw' ),
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'A query should succeed.' );
		$this->assertSame(
			array(
				array(
					'id'    => self::$attachment_ids['query'],
					'title' => array( 'raw' => 'Route query' ),
				),
			),
			$data['items'],
			'The query should list the attachment with the selected fields.'
		);
	}

	/**
	 * A query with input the schema refuses is a bad request.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_query_with_invalid_input_is_a_bad_request(): void {
		wp_set_current_user( self::$user_ids['editor'] );

		$response = $this->dispatch_query( 'GET', 'query', array( 'where' => array( 'status' => array( 'mdt-bogus' ) ) ) );

		$this->assert_error_response( $response, 400, 'ability_invalid_input', 'A query with an unknown status' );
	}

	/**
	 * An upload runs with POST and the input in a JSON body.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_upload_runs_with_post(): void {
		wp_set_current_user( self::$user_ids['author'] );

		$response = $this->dispatch_json(
			'POST',
			'upload',
			array(
				'data'   => array(
					'file'  => array(
						'base64'   => base64_encode( file_get_contents( DIR_TESTDATA . '/images/codeispoetry.png' ) ),
						'filename' => 'mdt-route-upload.png',
					),
					'title' => 'MDT route upload',
				),
				'fields' => array( 'title.raw', 'mime_type' ),
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'An upload should succeed.' );
		$this->assertSame( 'attachment', get_post_type( $data['id'] ), 'The upload should create an attachment.' );
		$this->assertSame( 'MDT route upload', $data['title']['raw'], 'The title should be the one sent.' );
		$this->assertSame( 'image/png', $data['mime_type'], 'The MIME type should be the stored one.' );
	}

	/**
	 * An update runs with POST and the input in a JSON body.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_update_runs_with_post(): void {
		wp_set_current_user( self::$user_ids['editor'] );

		$response = $this->dispatch_json(
			'POST',
			'update',
			array(
				'where'  => array( 'id' => self::$attachment_ids['update'] ),
				'data'   => array( 'alt_text' => 'MDT route alt' ),
				'fields' => array( 'alt_text' ),
			)
		);

		$this->assertSame( 200, $response->get_status(), 'An update should succeed.' );
		$this->assertSame(
			array(
				'id'       => self::$attachment_ids['update'],
				'alt_text' => 'MDT route alt',
			),
			$response->get_data(),
			'The update should return the selected fields.'
		);
	}

	/**
	 * An update of an ID that names no attachment is not found.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_update_of_a_missing_attachment_is_not_found(): void {
		wp_set_current_user( self::$user_ids['editor'] );

		$response = $this->dispatch_json(
			'POST',
			'update',
			array(
				'where' => array( 'id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ),
				'data'  => array( 'title' => 'MDT missing' ),
			)
		);

		$this->assert_error_response( $response, 404, 'media_not_found', 'An update of a missing attachment' );
	}

	/**
	 * A permanent deletion runs with DELETE and the input in the query string.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permanent_deletion_runs_with_delete(): void {
		wp_set_current_user( self::$user_ids['editor'] );

		$response = $this->dispatch_query(
			'DELETE',
			'delete',
			array(
				'where'     => array( 'id' => (string) self::$attachment_ids['delete'] ),
				'permanent' => 'true',
			)
		);

		$this->assertSame( 200, $response->get_status(), 'A permanent deletion should succeed.' );
		$this->assertSame( array( 'outcome' => 'deleted' ), $response->get_data(), 'The outcome should be deleted.' );
		$this->assertNull( get_post( self::$attachment_ids['delete'] ), 'The attachment should no longer exist.' );
	}

	/**
	 * A deletion with `permanent` false in the query string asks for the Trash.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_deletion_with_permanent_false_asks_for_the_trash(): void {
		wp_set_current_user( self::$user_ids['editor'] );

		$response = $this->dispatch_query(
			'DELETE',
			'delete',
			array(
				'where'     => array( 'id' => (string) self::$attachment_ids['trash'] ),
				'permanent' => 'false',
			)
		);

		if ( EMPTY_TRASH_DAYS >= 1 && MEDIA_TRASH ) {
			$this->assertSame( 200, $response->get_status(), 'A trash request should succeed.' );
			$this->assertSame( array( 'outcome' => 'trashed' ), $response->get_data(), 'The outcome should be trashed.' );
			$this->assertSame( 'trash', get_post( self::$attachment_ids['trash'] )->post_status, 'The attachment should be in the Trash.' );
		} else {
			$this->assert_error_response( $response, 501, 'media_trash_not_supported', 'A trash request on a site that cannot trash' );
			$this->assertSame( 'inherit', get_post( self::$attachment_ids['trash'] )->post_status, 'The attachment should keep its status.' );
		}
	}

	/**
	 * Returns requests sent with a method their ability does not accept.
	 *
	 * @return array<string, array{operation: string, method: string, role: string, input: array<string, mixed>, attachment: string|null, context: string}> The cases.
	 */
	public static function data_wrong_methods(): array {
		return array(
			'query with POST'    => array( 'query', 'POST', 'editor', array(), null, 'A query with POST' ),
			'upload with GET'    => array( 'upload', 'GET', 'author', array(), null, 'An upload with GET' ),
			'deletion with POST' => array( 'delete', 'POST', 'editor', array( 'permanent' => true ), 'delete', 'A deletion with POST' ),
		);
	}

	/**
	 * A request with a method its ability does not accept is refused as a wrong method.
	 *
	 * @ticket 66719
	 * @dataProvider data_wrong_methods
	 * @since 7.3.0
	 *
	 * @param string               $operation  The operation.
	 * @param string               $method     The HTTP method.
	 * @param string               $role       The role of the caller.
	 * @param array<string, mixed> $input      The input, without the target attachment.
	 * @param string|null          $attachment The target attachment as a fixture name, or null for none.
	 * @param string               $context    What the request is, for the assertion messages.
	 */
	public function test_wrong_method_is_refused( string $operation, string $method, string $role, array $input, ?string $attachment, string $context ): void {
		wp_set_current_user( self::$user_ids[ $role ] );

		$this->assert_refused_request( $operation, $method, $input, $attachment, 405, 'rest_ability_invalid_method', $context );
	}

	/**
	 * Returns requests the caller may not make.
	 *
	 * @return array<string, array{operation: string, method: string, role: string|null, input: array<string, mixed>, attachment: string|null, status: int, code: string, context: string}> The cases, a null role for a logged-out caller.
	 */
	public static function data_refused_requests(): array {
		$upload_input = array(
			'data' => array(
				'file' => array(
					'base64'   => base64_encode( file_get_contents( DIR_TESTDATA . '/images/codeispoetry.png' ) ),
					'filename' => 'mdt-route-forbidden.png',
				),
			),
		);

		return array(
			'logged-out query'                   => array( 'query', 'GET', null, array(), null, 401, 'rest_ability_cannot_execute', 'A logged-out query' ),
			'query of the Trash by a subscriber' => array( 'query', 'GET', 'subscriber', array( 'where' => array( 'status' => array( 'trash' ) ) ), null, 403, 'media_cannot_filter_by_status', 'A query of the Trash by a subscriber' ),
			'upload by a subscriber'             => array( 'upload', 'POST', 'subscriber', $upload_input, null, 403, 'rest_ability_cannot_execute', 'An upload by a subscriber' ),
			'logged-out deletion'                => array( 'delete', 'DELETE', null, array( 'permanent' => 'true' ), 'delete', 401, 'rest_ability_cannot_execute', 'A logged-out deletion' ),
		);
	}

	/**
	 * A request the caller may not make is refused with its status and error code.
	 *
	 * @ticket 66719
	 * @dataProvider data_refused_requests
	 * @since 7.3.0
	 *
	 * @param string               $operation  The operation.
	 * @param string               $method     The HTTP method.
	 * @param string|null          $role       The role of the caller, or null for a logged-out caller.
	 * @param array<string, mixed> $input      The input, without the target attachment.
	 * @param string|null          $attachment The target attachment as a fixture name, or null for none.
	 * @param int                  $status     The expected status.
	 * @param string               $code       The expected error code.
	 * @param string               $context    What the request is, for the assertion messages.
	 */
	public function test_refused_request_returns_the_error( string $operation, string $method, ?string $role, array $input, ?string $attachment, int $status, string $code, string $context ): void {
		wp_set_current_user( null === $role ? 0 : self::$user_ids[ $role ] );

		$this->assert_refused_request( $operation, $method, $input, $attachment, $status, $code, $context );
	}
}
