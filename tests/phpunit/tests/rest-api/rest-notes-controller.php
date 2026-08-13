<?php
/**
 * Unit tests covering WP_REST_Notes_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 * @group notes
 *
 * @coversDefaultClass WP_REST_Notes_Controller
 */
class WP_Test_REST_Notes_Controller extends WP_Test_REST_Controller_Testcase {

	/**
	 * The REST route the controller registers.
	 */
	const ROUTE = '/wp/v2/notes';

	/**
	 * Editor user ID. Can edit the test post, so can read its notes.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * A second editor, used to prove notes are shared across everyone who can
	 * edit the post rather than scoped to their author.
	 *
	 * @var int
	 */
	protected static $other_editor_id;

	/**
	 * Subscriber user ID. Cannot edit the test post.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Post the notes hang off.
	 *
	 * @var int
	 */
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id       = $factory->user->create( array( 'role' => 'editor' ) );
		self::$other_editor_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$subscriber_id   = $factory->user->create( array( 'role' => 'subscriber' ) );

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$editor_id,
				'post_status' => 'publish',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		/*
		 * The test case unregisters every meta key between tests, and
		 * `_wp_note_status` is registered on `init`, which has already fired.
		 */
		wp_create_initial_comment_meta();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$editor_id );
		self::delete_user( self::$other_editor_id );
		self::delete_user( self::$subscriber_id );

		wp_delete_post( self::$post_id, true );
	}

	/**
	 * Creates a note.
	 *
	 * @param array $args Optional. Overrides for the comment fields.
	 * @return int Comment ID.
	 */
	protected function create_note( $args = array() ) {
		return self::factory()->comment->create(
			array_merge(
				array(
					'comment_post_ID'  => self::$post_id,
					'comment_type'     => 'note',
					'comment_approved' => '0',
					'user_id'          => self::$editor_id,
					'comment_content'  => 'A note.',
				),
				$args
			)
		);
	}

	/**
	 * Dispatches a GET request to the notes collection.
	 *
	 * @param array $params Optional. Query parameters.
	 * @return WP_REST_Response Response object.
	 */
	protected function get_notes( $params = array() ) {
		$request = new WP_REST_Request( 'GET', self::ROUTE );
		$request->set_query_params( array_merge( array( 'post' => self::$post_id ), $params ) );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * The routes are registered.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( self::ROUTE, $routes );
		$this->assertArrayHasKey( self::ROUTE . '/(?P<id>[\d]+)', $routes );
	}

	/**
	 * The collection reads in edit context by default; a single note does not.
	 *
	 * @covers ::get_collection_params
	 */
	public function test_context_param() {
		$note = $this->create_note();

		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'edit', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', self::ROUTE . '/' . $note );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * The collection returns top-level notes only, with replies nested.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items() {
		$thread    = $this->create_note();
		$reply_one = $this->create_note(
			array(
				'comment_parent'   => $thread,
				'comment_content'  => 'First reply.',
				'comment_date_gmt' => '2026-01-01 00:00:00',
				'comment_date'     => '2026-01-01 00:00:00',
			)
		);
		$reply_two = $this->create_note(
			array(
				'comment_parent'   => $thread,
				'comment_content'  => 'Second reply.',
				'comment_date_gmt' => '2026-01-02 00:00:00',
				'comment_date'     => '2026-01-02 00:00:00',
			)
		);

		wp_set_current_user( self::$editor_id );

		$response = $this->get_notes();
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data, 'Replies should not appear as their own records.' );
		$this->assertSame( $thread, $data[0]['id'] );

		$reply_ids = wp_list_pluck( $data[0]['replies'], 'id' );
		$this->assertSame(
			array( $reply_one, $reply_two ),
			$reply_ids,
			'Replies should be nested under the thread, oldest first.'
		);
		$this->assertSame( 2, $data[0]['reply_count'] );
	}

	/**
	 * A single note is returned with its replies.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$thread = $this->create_note();
		$reply  = $this->create_note( array( 'comment_parent' => $thread ) );

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'GET', self::ROUTE . '/' . $thread );
		$request->set_param( 'context', 'edit' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $thread, $data['id'] );
		$this->assertSame( array( $reply ), wp_list_pluck( $data['replies'], 'id' ) );
	}

	/**
	 * Creating a note does not require the client to name the comment type.
	 *
	 * @covers ::create_item
	 */
	public function test_create_item() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params(
			array(
				'post'    => self::$post_id,
				'content' => 'Created through the notes route.',
				'status'  => 'hold',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'note', $data['type'] );
		$this->assertSame( 'hold', $data['status'] );
		$this->assertSame( 'note', get_comment( $data['id'] )->comment_type );
	}

	/**
	 * Resolving a note is a status update on the note route.
	 *
	 * @covers ::update_item
	 */
	public function test_update_item() {
		$note = $this->create_note();

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', self::ROUTE . '/' . $note );
		$request->set_body_params( array( 'status' => 'approved' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'approved', $response->get_data()['status'] );
		$this->assertSame( 'note', get_comment( $note )->comment_type );
	}

	/**
	 * A note can be deleted through its own route.
	 *
	 * @covers ::delete_item
	 */
	public function test_delete_item() {
		$note = $this->create_note();

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'DELETE', self::ROUTE . '/' . $note );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['deleted'] );
		$this->assertNull( get_comment( $note ) );
	}

	/**
	 * A prepared note carries every schema property and nothing else.
	 *
	 * @covers ::prepare_item_for_response
	 */
	public function test_prepare_item() {
		$note = $this->create_note();

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'GET', self::ROUTE . '/' . $note );
		$request->set_param( 'context', 'edit' );

		$data       = rest_get_server()->dispatch( $request )->get_data();
		$properties = ( new WP_REST_Notes_Controller() )->get_item_schema()['properties'];

		foreach ( array_keys( $properties ) as $property ) {
			$this->assertArrayHasKey( $property, $data, "The `$property` property should be present." );
		}

		$this->assertSame( self::$post_id, $data['post'] );
		$this->assertSame( 'note', $data['type'] );
	}

	/**
	 * Fields that only make sense for anonymous commenters are not exposed.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$properties = ( new WP_REST_Notes_Controller() )->get_item_schema()['properties'];

		foreach ( array( 'author_email', 'author_ip', 'author_url', 'author_user_agent', 'link' ) as $removed ) {
			$this->assertArrayNotHasKey( $removed, $properties, "The `$removed` property should not be exposed." );
		}

		$this->assertArrayHasKey( 'replies', $properties );
		$this->assertArrayHasKey( 'reply_count', $properties );
	}

	/**
	 * Replies are prepared in the same context as their thread.
	 *
	 * This is the behaviour `_embed` on the comments collection cannot provide:
	 * embedded children are always prepared in `view` context, so `content.raw`,
	 * the value the editor writes back, never reaches the client.
	 *
	 * @covers ::get_items
	 */
	public function test_replies_are_prepared_in_edit_context() {
		$thread = $this->create_note();
		$this->create_note(
			array(
				'comment_parent'  => $thread,
				'comment_content' => 'Raw reply body.',
			)
		);

		wp_set_current_user( self::$editor_id );

		$data = $this->get_notes( array( 'context' => 'edit' ) )->get_data();

		$this->assertArrayHasKey( 'raw', $data[0]['content'] );
		$this->assertArrayHasKey( 'raw', $data[0]['replies'][0]['content'] );
		$this->assertSame( 'Raw reply body.', $data[0]['replies'][0]['content']['raw'] );
	}

	/**
	 * Both open and resolved notes come back without asking for a status.
	 *
	 * @covers ::get_collection_params
	 */
	public function test_get_items_defaults_to_all_statuses() {
		$open     = $this->create_note( array( 'comment_approved' => '0' ) );
		$resolved = $this->create_note( array( 'comment_approved' => '1' ) );

		wp_set_current_user( self::$editor_id );

		$data     = $this->get_notes()->get_data();
		$statuses = array();

		foreach ( $data as $note ) {
			$statuses[ $note['id'] ] = $note['status'];
		}

		$this->assertSame( 'hold', $statuses[ $open ] );
		$this->assertSame( 'approved', $statuses[ $resolved ] );
	}

	/**
	 * Pagination counts and cuts between threads, never inside one.
	 *
	 * @covers ::get_items
	 */
	public function test_pagination_counts_threads_not_replies() {
		$first  = $this->create_note( array( 'comment_date_gmt' => '2026-01-01 00:00:00' ) );
		$second = $this->create_note( array( 'comment_date_gmt' => '2026-01-02 00:00:00' ) );

		$this->create_note( array( 'comment_parent' => $first ) );
		$this->create_note( array( 'comment_parent' => $second ) );

		wp_set_current_user( self::$editor_id );

		$response = $this->get_notes( array( 'per_page' => 1 ) );
		$data     = $response->get_data();
		$headers  = $response->get_headers();

		$this->assertSame( '2', (string) $headers['X-WP-Total'], 'Only threads should be counted.' );
		$this->assertSame( '2', (string) $headers['X-WP-TotalPages'] );
		$this->assertCount( 1, $data );
		$this->assertCount( 1, $data[0]['replies'], 'A page break must not strip a thread of its replies.' );
	}

	/**
	 * `_fields` can trim the response down to a per-post tally.
	 *
	 * @covers ::attach_replies
	 */
	public function test_reply_count_is_available_without_the_replies() {
		$thread = $this->create_note();
		$this->create_note( array( 'comment_parent' => $thread ) );

		wp_set_current_user( self::$editor_id );

		$data = $this->get_notes( array( '_fields' => 'id,post,reply_count' ) )->get_data();

		$this->assertSame(
			array( 'id', 'post', 'reply_count' ),
			array_keys( $data[0] )
		);
		$this->assertSame( 1, $data[0]['reply_count'] );
	}

	/**
	 * The collection is scoped to a post.
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_requires_a_post() {
		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Anyone who can edit the post can read its notes, not just their author.
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_notes_are_readable_by_every_editor_of_the_post() {
		$this->create_note( array( 'user_id' => self::$editor_id ) );

		wp_set_current_user( self::$other_editor_id );

		$response = $this->get_notes();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
	}

	/**
	 * Users who cannot edit the post cannot read its notes.
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_denied_without_edit_post() {
		$this->create_note();

		wp_set_current_user( self::$subscriber_id );

		$this->assertErrorResponse( 'rest_cannot_read_notes', $this->get_notes(), 403 );
	}

	/**
	 * Logged-out requests are rejected outright.
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_denied_when_logged_out() {
		$this->create_note();

		wp_set_current_user( 0 );

		$this->assertErrorResponse( 'rest_notes_not_logged_in', $this->get_notes(), 401 );
	}

	/**
	 * Post types that do not opt into notes have no notes to read.
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_denied_for_post_type_without_notes_support() {
		register_post_type( 'no_notes', array( 'supports' => array( 'editor' ) ) );

		$unsupported_id = self::factory()->post->create(
			array(
				'post_type'   => 'no_notes',
				'post_author' => self::$editor_id,
			)
		);

		wp_set_current_user( self::$editor_id );

		$response = $this->get_notes( array( 'post' => $unsupported_id ) );

		unregister_post_type( 'no_notes' );

		$this->assertErrorResponse( 'rest_note_not_supported_post_type', $response, 403 );
	}

	/**
	 * The single-note routes do not expose ordinary comments.
	 *
	 * @covers ::get_comment
	 */
	public function test_single_route_rejects_a_regular_comment() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => '1',
			)
		);

		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'GET', self::ROUTE . '/' . $comment_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_note_invalid_id', $response, 404 );
	}

	/**
	 * A reply created through the route shows up nested in the thread.
	 *
	 * @covers ::create_item
	 */
	public function test_created_reply_is_nested_in_its_thread() {
		$thread = $this->create_note();

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params(
			array(
				'post'    => self::$post_id,
				'parent'  => $thread,
				'content' => 'A reply.',
				'status'  => 'hold',
			)
		);

		$created = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $created->get_status() );

		$data = $this->get_notes()->get_data();

		$this->assertCount( 1, $data, 'The reply should not surface as its own thread.' );
		$this->assertSame(
			array( $created->get_data()['id'] ),
			wp_list_pluck( $data[0]['replies'], 'id' )
		);
	}

	/**
	 * A draft is exactly the kind of post that gets annotated.
	 *
	 * @covers ::create_item_permissions_check
	 */
	public function test_create_item_on_a_draft_post() {
		$draft_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => self::$editor_id,
			)
		);

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params(
			array(
				'post'    => $draft_id,
				'content' => 'A note on a draft.',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'note', get_comment( $response->get_data()['id'] )->comment_type );
	}

	/**
	 * A closed discussion does not close the editorial one.
	 *
	 * @covers ::create_item_permissions_check
	 */
	public function test_create_item_when_comments_are_closed() {
		$closed_id = self::factory()->post->create(
			array(
				'post_author'    => self::$editor_id,
				'comment_status' => 'closed',
			)
		);

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params(
			array(
				'post'    => $closed_id,
				'content' => 'Comments are closed, notes are not.',
			)
		);

		$this->assertSame( 201, rest_get_server()->dispatch( $request )->get_status() );
	}

	/**
	 * Notes cannot be created anonymously, whatever the discussion settings say.
	 *
	 * @covers ::create_item_permissions_check
	 */
	public function test_create_item_requires_login() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params(
			array(
				'post'    => self::$post_id,
				'content' => 'Anonymous note.',
			)
		);

		$this->assertErrorResponse( 'rest_notes_not_logged_in', rest_get_server()->dispatch( $request ), 401 );
	}

	/**
	 * Users who cannot edit the post cannot annotate it.
	 *
	 * @covers ::create_item_permissions_check
	 */
	public function test_create_item_denied_without_edit_post() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params(
			array(
				'post'    => self::$post_id,
				'content' => 'Not mine to annotate.',
			)
		);

		$this->assertErrorResponse( 'rest_cannot_create_note', rest_get_server()->dispatch( $request ), 403 );
	}

	/**
	 * Post types that do not opt into notes cannot be annotated either.
	 *
	 * @covers ::create_item_permissions_check
	 */
	public function test_create_item_denied_for_post_type_without_notes_support() {
		register_post_type( 'no_notes', array( 'supports' => array( 'editor' ) ) );

		$unsupported_id = self::factory()->post->create(
			array(
				'post_type'   => 'no_notes',
				'post_author' => self::$editor_id,
			)
		);

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params(
			array(
				'post'    => $unsupported_id,
				'content' => 'Unsupported.',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		unregister_post_type( 'no_notes' );

		$this->assertErrorResponse( 'rest_note_not_supported_post_type', $response, 403 );
	}

	/**
	 * Resolving a note posts no text of its own.
	 *
	 * @dataProvider data_resolution_statuses
	 * @covers ::check_is_comment_content_allowed
	 *
	 * @param string $status Resolution status stored in `_wp_note_status`.
	 */
	public function test_create_empty_note_with_resolution_meta( $status ) {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'post'    => self::$post_id,
					'content' => '',
					'meta'    => array( '_wp_note_status' => $status ),
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( $status, $response->get_data()['meta']['_wp_note_status'] );
	}

	/**
	 * Data provider for resolution statuses.
	 *
	 * @return array[]
	 */
	public function data_resolution_statuses() {
		return array(
			'resolved' => array( 'resolved' ),
			'reopen'   => array( 'reopen' ),
		);
	}

	/**
	 * An empty note with nothing to record is still an empty note.
	 *
	 * @dataProvider data_disallowed_empty_note_meta
	 * @covers ::check_is_comment_content_allowed
	 *
	 * @param array $meta Meta sent with the note.
	 */
	public function test_cannot_create_empty_note( $meta ) {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array_merge(
					array(
						'post'    => self::$post_id,
						'content' => '',
					),
					$meta
				)
			)
		);

		$this->assertErrorResponse( 'rest_comment_content_invalid', rest_get_server()->dispatch( $request ), 400 );
	}

	/**
	 * Data provider for empty notes that must be rejected.
	 *
	 * @return array[]
	 */
	public function data_disallowed_empty_note_meta() {
		return array(
			'no meta at all' => array( array() ),
			'invalid status' => array( array( 'meta' => array( '_wp_note_status' => 'invalid' ) ) ),
		);
	}

	/**
	 * Two people can raise the same point without one being swallowed.
	 *
	 * @covers ::determine_comment_approval
	 */
	public function test_duplicate_notes_are_both_created() {
		wp_set_current_user( self::$editor_id );

		for ( $i = 0; $i < 2; $i++ ) {
			$request = new WP_REST_Request( 'POST', self::ROUTE );
			$request->set_body_params(
				array(
					'post'    => self::$post_id,
					'content' => 'The same point, twice.',
				)
			);

			$this->assertSame( 201, rest_get_server()->dispatch( $request )->get_status() );
		}

		$this->assertCount( 2, $this->get_notes()->get_data() );
	}

	/**
	 * Reading a note follows edit access to its post, by role.
	 *
	 * @dataProvider data_note_read_permissions
	 * @covers ::get_items_permissions_check
	 *
	 * @param string $role             Role of the reading user.
	 * @param string $post_author_role Role of the post author.
	 * @param bool   $can_read         Whether the reader should see the notes.
	 */
	public function test_note_read_permissions_by_role( $role, $post_author_role, $can_read ) {
		$reader = self::factory()->user->create( array( 'role' => $role ) );
		$author = 'contributor' === $post_author_role
			? self::factory()->user->create( array( 'role' => 'contributor' ) )
			: self::factory()->user->create( array( 'role' => $post_author_role ) );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $author,
				'post_status' => 'contributor' === $post_author_role ? 'draft' : 'publish',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => '0',
				'user_id'          => $author,
			)
		);

		wp_set_current_user( $reader );

		$response = $this->get_notes( array( 'post' => $post_id ) );

		if ( $can_read ) {
			$this->assertSame( 200, $response->get_status() );
			$this->assertCount( 1, $response->get_data() );
		} else {
			$this->assertErrorResponse( 'rest_cannot_read_notes', $response, 403 );
		}

		wp_delete_post( $post_id, true );
		self::delete_user( $reader );
		self::delete_user( $author );
	}

	/**
	 * Data provider for note read permissions.
	 *
	 * @return array[]
	 */
	public function data_note_read_permissions() {
		return array(
			'Administrator can see notes on other posts'  => array( 'administrator', 'author', true ),
			'Editor can see notes on other posts'         => array( 'editor', 'contributor', true ),
			'Author cannot see notes on other posts'      => array( 'author', 'editor', false ),
			'Contributor cannot see notes on other posts' => array( 'contributor', 'author', false ),
			'Subscriber cannot see notes'                 => array( 'subscriber', 'author', false ),
		);
	}

	/**
	 * The thread list does not build a `children` link per note.
	 *
	 * @covers ::prepare_links
	 */
	public function test_links_omit_children() {
		$thread = $this->create_note();
		$this->create_note( array( 'comment_parent' => $thread ) );

		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'GET', self::ROUTE . '/' . $thread );
		$response = rest_get_server()->dispatch( $request );

		$this->assertArrayNotHasKey( 'children', $response->get_links() );
	}
}
