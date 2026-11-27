<?php
/**
 * Tests for note locking.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 * @group comment
 *
 * @covers ::wp_note_action_is_locked
 * @covers ::wp_get_note_lock_actions
 * @covers ::wp_register_note_lock_meta
 * @covers WP_REST_Comments_Controller::create_item_permissions_check
 * @covers WP_REST_Comments_Controller::update_item_permissions_check
 * @covers WP_REST_Comments_Controller::delete_item_permissions_check
 */
class Tests_REST_Notes_Locking extends WP_Test_REST_TestCase {

	/**
	 * The post the notes are attached to.
	 *
	 * @var WP_Post
	 */
	protected static $post;

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * An editor, who owns the review thread.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * An author, who may edit their own post but not lock its notes.
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Arguments the `note_action_is_locked` filter was called with.
	 *
	 * @var array[]
	 */
	protected $filter_calls = array();

	/**
	 * Creates the shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$author_id = $factory->user->create( array( 'role' => 'author' ) );

		self::$post = $factory->post->create_and_get(
			array(
				'post_author' => self::$author_id,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Deletes the shared fixtures.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post->ID, true );

		self::delete_user( self::$admin_id );
		self::delete_user( self::$editor_id );
		self::delete_user( self::$author_id );
	}

	public function set_up() {
		parent::set_up();

		/*
		 * The test case unregisters every meta key on set up, discarding what
		 * `init` registered.
		 */
		wp_register_note_lock_meta();

		wp_set_current_user( self::$editor_id );
	}

	/**
	 * Locks every note action on the shared post.
	 */
	protected function lock_post() {
		update_post_meta( self::$post->ID, '_wp_notes_locked', true );
	}

	/**
	 * Dispatches a request to create a note.
	 *
	 * @param array $params Optional. Extra request parameters. Default empty array.
	 * @return WP_REST_Response The response.
	 */
	protected function create_note( $params = array() ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_param( 'post', self::$post->ID );
		$request->set_param( 'type', 'note' );
		$request->set_param( 'content', 'A note.' );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Creates a note directly, bypassing the REST gate, so that a thread can be
	 * seeded on a post that is already locked.
	 *
	 * @param array $args Optional. Extra comment arguments. Default empty array.
	 * @return int The note ID.
	 */
	protected function seed_note( $args = array() ) {
		return wp_insert_comment(
			array_merge(
				array(
					'comment_post_ID'  => self::$post->ID,
					'comment_type'     => 'note',
					'comment_content'  => 'Seeded note.',
					'comment_approved' => '0',
					'user_id'          => self::$editor_id,
				),
				$args
			)
		);
	}

	/**
	 * Dispatches a request to update a note.
	 *
	 * @param int   $note_id Note ID.
	 * @param array $params  Request parameters.
	 * @return WP_REST_Response The response.
	 */
	protected function update_note( $note_id, $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments/' . $note_id );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Dispatches a request to delete a note.
	 *
	 * @param int  $note_id Note ID.
	 * @param bool $force   Optional. Whether to bypass the trash. Default true.
	 * @return WP_REST_Response The response.
	 */
	protected function delete_note( $note_id, $force = true ) {
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/comments/' . $note_id );
		$request->set_param( 'force', $force );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Records every `note_action_is_locked` call, leaving the value untouched.
	 *
	 * @param bool            $locked  Whether the action is locked.
	 * @param string          $action  The action name.
	 * @param WP_Post         $post    The post.
	 * @param WP_Comment|null $comment The note being mutated, if there is one.
	 * @return bool The unchanged value.
	 */
	public function record_filter_call( $locked, $action, $post, $comment ) {
		$this->filter_calls[] = array( $locked, $action, $post, $comment );

		return $locked;
	}

	/**
	 * Locks deletion and nothing else.
	 *
	 * @param bool   $locked Whether the action is locked.
	 * @param string $action The action name.
	 * @return bool Whether the action is locked.
	 */
	public function lock_deletion_only( $locked, $action ) {
		return 'delete' === $action ? true : $locked;
	}

	/**
	 * Every note flow works when nothing is locked.
	 */
	public function test_unlocked_post_allows_every_note_action() {
		$created = $this->create_note();
		$this->assertSame( 201, $created->get_status(), 'The note should have been created.' );
		$note_id = $created->get_data()['id'];

		$reply = $this->create_note(
			array(
				'parent'  => $note_id,
				'content' => 'A reply.',
			)
		);
		$this->assertSame( 201, $reply->get_status(), 'The reply should have been created.' );

		$edited = $this->update_note( $note_id, array( 'content' => 'An edited note.' ) );
		$this->assertSame( 200, $edited->get_status(), 'The note should have been edited.' );

		$resolved = $this->update_note( $note_id, array( 'status' => 'approved' ) );
		$this->assertSame( 200, $resolved->get_status(), 'The note should have been resolved.' );

		$marker = $this->create_note(
			array(
				'parent'  => $note_id,
				'content' => '',
				'status'  => 'approved',
				'meta'    => array( '_wp_note_status' => 'resolved' ),
			)
		);
		$this->assertSame( 201, $marker->get_status(), 'The resolution marker note should have been created.' );

		$deleted = $this->delete_note( $note_id );
		$this->assertSame( 200, $deleted->get_status(), 'The note should have been deleted.' );
	}

	/**
	 * The per-post meta locks every mutation.
	 */
	public function test_locked_post_rejects_every_note_mutation() {
		$note_id  = $this->seed_note();
		$reply_id = $this->seed_note( array( 'comment_parent' => $note_id ) );

		$this->lock_post();

		$this->assertErrorResponse( 'rest_notes_locked', $this->create_note(), 403, 'Note creation should be locked.' );
		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->create_note( array( 'parent' => $note_id ) ),
			403,
			'Replies should be locked.'
		);
		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->create_note(
				array(
					'parent'  => $note_id,
					'content' => '',
					'status'  => 'approved',
					'meta'    => array( '_wp_note_status' => 'resolved' ),
				)
			),
			403,
			'Resolution marker notes should be locked.'
		);
		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->update_note( $note_id, array( 'content' => 'Edited.' ) ),
			403,
			'Note edits should be locked.'
		);
		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->update_note( $note_id, array( 'status' => 'approved' ) ),
			403,
			'Resolving should be locked.'
		);
		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->delete_note( $reply_id, false ),
			403,
			'Trashing a reply should be locked.'
		);
		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->delete_note( $note_id ),
			403,
			'Deleting a note should be locked.'
		);
	}

	/**
	 * Administrators are bound by the lock too.
	 */
	public function test_lock_binds_administrators() {
		$note_id = $this->seed_note();
		$this->lock_post();

		wp_set_current_user( self::$admin_id );

		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->create_note(),
			403,
			'Note creation should be locked for administrators.'
		);
		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->delete_note( $note_id ),
			403,
			'Deletion should be locked for administrators.'
		);
	}

	/**
	 * Regular comments are untouched by a lock.
	 */
	public function test_lock_does_not_affect_regular_comments() {
		$this->lock_post();
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_param( 'post', self::$post->ID );
		$request->set_param( 'content', 'A regular comment.' );
		$created = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $created->get_status(), 'The comment should have been created.' );
		$comment_id = $created->get_data()['id'];

		$edited = $this->update_note( $comment_id, array( 'content' => 'An edited comment.' ) );
		$this->assertSame( 200, $edited->get_status(), 'The comment should have been edited.' );

		$deleted = $this->delete_note( $comment_id );
		$this->assertSame( 200, $deleted->get_status(), 'The comment should have been deleted.' );
	}

	/**
	 * Reading notes stays open on a locked post.
	 */
	public function test_lock_does_not_affect_reading_notes() {
		$note_id = $this->seed_note();
		$this->lock_post();

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'post', self::$post->ID );
		$request->set_param( 'type', 'note' );
		$request->set_param( 'status', 'all' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Notes should be readable.' );
		$this->assertSame(
			array( $note_id ),
			wp_list_pluck( $response->get_data(), 'id' ),
			'The seeded note should have been listed.'
		);
	}

	/**
	 * The filter can lock notes site-wide, with no meta set.
	 */
	public function test_filter_can_lock_every_post() {
		add_filter( 'note_action_is_locked', '__return_true' );

		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->create_note(),
			403,
			'The site-wide filter should have locked note creation.'
		);
	}

	/**
	 * The filter can lock a single action, and receives the expected arguments.
	 */
	public function test_filter_can_lock_a_single_action() {
		$note_id = $this->seed_note();

		add_filter( 'note_action_is_locked', array( $this, 'record_filter_call' ), 5, 4 );
		add_filter( 'note_action_is_locked', array( $this, 'lock_deletion_only' ), 10, 2 );

		$created = $this->create_note();
		$this->assertSame( 201, $created->get_status(), 'Creation should have stayed open.' );

		$edited = $this->update_note( $note_id, array( 'content' => 'Edited.' ) );
		$this->assertSame( 200, $edited->get_status(), 'Editing should have stayed open.' );

		$resolved = $this->update_note( $note_id, array( 'status' => 'approved' ) );
		$this->assertSame( 200, $resolved->get_status(), 'Resolving should have stayed open.' );

		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->delete_note( $note_id ),
			403,
			'Deletion should have been locked.'
		);

		$this->assertSame(
			array( 'create', 'edit', 'resolve', 'delete' ),
			wp_list_pluck( $this->filter_calls, 1 ),
			'The filter should have classified each request.'
		);

		list( $locked, , $post, $comment ) = $this->filter_calls[0];
		$this->assertFalse( $locked, 'The default should have been unlocked.' );
		$this->assertSame( self::$post->ID, $post->ID, 'The filter should have received the target post.' );
		$this->assertNull( $comment, 'There should have been no comment on create.' );

		list( , , , $delete_comment ) = $this->filter_calls[3];
		$this->assertInstanceOf( WP_Comment::class, $delete_comment, 'The filter should have received the note.' );
		$this->assertSame( (string) $note_id, $delete_comment->comment_ID, 'The targeted note should have been passed.' );
	}

	/**
	 * The filter can exempt a capability from a lock.
	 */
	public function test_filter_can_exempt_a_capability() {
		$editors_note = $this->seed_note();
		$admins_note  = $this->seed_note();

		$this->lock_post();
		add_filter(
			'note_action_is_locked',
			static function ( $locked, $action ) {
				return 'delete' === $action && current_user_can( 'manage_options' ) ? false : $locked;
			},
			10,
			2
		);

		$this->assertErrorResponse(
			'rest_notes_locked',
			$this->delete_note( $editors_note ),
			403,
			'The editor should have been blocked from deleting.'
		);

		wp_set_current_user( self::$admin_id );
		$deleted = $this->delete_note( $admins_note );
		$this->assertSame( 200, $deleted->get_status(), 'The administrator should have deleted the note.' );
	}

	/**
	 * Writing the lock meta takes more than authoring the post.
	 */
	public function test_lock_meta_write_requires_edit_others_posts() {
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post->ID );
		$request->set_param( 'meta', array( '_wp_notes_locked' => true ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'The author should have been refused.' );
		$this->assertFalse(
			(bool) get_post_meta( self::$post->ID, '_wp_notes_locked', true ),
			'The post should have stayed unlocked.'
		);

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post->ID );
		$request->set_param( 'meta', array( '_wp_notes_locked' => true ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'The editor should have locked the post.' );
		$this->assertTrue(
			$response->get_data()['meta']['_wp_notes_locked'],
			'The lock should have round-tripped as a boolean.'
		);
	}

	/**
	 * The meta is only registered for post types that support notes.
	 */
	public function test_lock_meta_is_only_registered_for_note_capable_post_types() {
		register_post_type( 'wptests_no_notes', array( 'supports' => array( 'editor' ) ) );
		wp_register_note_lock_meta();

		$this->assertTrue(
			registered_meta_key_exists( 'post', '_wp_notes_locked', 'post' ),
			'The meta should be registered for a post type that supports notes.'
		);
		$this->assertFalse(
			registered_meta_key_exists( 'post', '_wp_notes_locked', 'wptests_no_notes' ),
			'The meta should not be registered for a post type without notes support.'
		);

		unregister_post_type( 'wptests_no_notes' );
	}

	/**
	 * The permission checks leave targets they cannot resolve to the controller.
	 */
	public function test_lock_leaves_unresolvable_targets_alone() {
		add_filter( 'note_action_is_locked', '__return_true' );
		wp_set_current_user( self::$admin_id );

		$missing = $this->delete_note( 999999 );
		$this->assertSame(
			'rest_comment_invalid_id',
			$missing->as_error()->get_error_code(),
			'A missing comment should have been left to the controller.'
		);

		$orphan   = $this->seed_note( array( 'comment_post_ID' => 999999 ) );
		$response = $this->delete_note( $orphan );
		$this->assertNotSame( 500, $response->get_status(), 'An orphaned note should not have been fatal.' );
		$this->assertNotSame(
			'rest_notes_locked',
			is_wp_error( $response->as_error() ) ? $response->as_error()->get_error_code() : '',
			'A note whose post is gone should have been left to the controller.'
		);
	}

	/**
	 * The editor settings advertise the locked actions.
	 *
	 * @covers ::get_block_editor_settings
	 */
	public function test_editor_settings_expose_locked_actions() {
		$context = new WP_Block_Editor_Context( array( 'post' => self::$post ) );

		$settings = get_block_editor_settings( array(), $context );
		$this->assertSame( array(), $settings['lockedNoteActions'], 'Nothing should be locked by default.' );

		$this->lock_post();
		$settings = get_block_editor_settings( array(), $context );
		$this->assertSame(
			array( 'create', 'reply', 'edit', 'resolve', 'delete' ),
			$settings['lockedNoteActions'],
			'Every action should have been locked.'
		);

		delete_post_meta( self::$post->ID, '_wp_notes_locked' );
		add_filter( 'note_action_is_locked', array( $this, 'lock_deletion_only' ), 10, 2 );

		$settings = get_block_editor_settings( array(), $context );
		$this->assertSame(
			array( 'delete' ),
			$settings['lockedNoteActions'],
			'Only the filtered action should have been locked.'
		);
	}
}
