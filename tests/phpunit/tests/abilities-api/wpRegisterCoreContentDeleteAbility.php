<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-content-write-ability.php';

/**
 * Tests for the core/content-delete ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Content
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreContentDeleteAbility extends WP_Abilities_Content_Write_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/content-delete';

	/**
	 * Creates shared users and posts.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::create_shared_fixtures( $factory );

		foreach ( array( 'front_page', 'posts_page', 'privacy_page' ) as $page ) {
			self::$post_ids[ $page ] = $factory->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => 'page',
					'post_status' => 'publish',
				)
			);
		}
	}

	/**
	 * Builds the input that deletes a post.
	 *
	 * @since 7.3.0
	 *
	 * @param int       $post_id   The post ID.
	 * @param bool|null $permanent The `permanent` value, or null to send none.
	 * @return array<string, mixed> The input.
	 */
	private function delete_input( int $post_id, ?bool $permanent = null ): array {
		$input = array(
			'where' => array(
				'type' => get_post_type( $post_id ),
				'id'   => $post_id,
			),
		);
		if ( null !== $permanent ) {
			$input['permanent'] = $permanent;
		}

		return $input;
	}

	/**
	 * Asserts that a result is the 403 delete error.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	private function assert_cannot_delete( $result, string $context ): void {
		$this->assert_error( $result, 'content_cannot_delete', 403, $context );
		$this->assertSame( 'Sorry, you are not allowed to delete this post.', $result->get_error_message(), "{$context}: the message should be the delete message." );
	}

	/**
	 * Asserts that a post was moved to the Trash and the result says so.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param int    $post_id The post ID.
	 * @param string $context What the call was, for the assertion messages.
	 */
	private function assert_trashed( $result, int $post_id, string $context ): void {
		$this->assertSame( array( 'outcome' => 'trashed' ), $result, "{$context}: the outcome should be trashed." );

		clean_post_cache( $post_id );

		$this->assertSame( 'trash', get_post_status( $post_id ), "{$context}: the post should be in the Trash." );
	}

	/**
	 * Asserts that a post was deleted permanently and the result says so.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param int    $post_id The post ID.
	 * @param string $context What the call was, for the assertion messages.
	 */
	private function assert_deleted( $result, int $post_id, string $context ): void {
		$this->assertSame( array( 'outcome' => 'deleted' ), $result, "{$context}: the outcome should be deleted." );

		clean_post_cache( $post_id );

		$this->assertNull( get_post( $post_id ), "{$context}: the post should no longer exist." );
	}

	/**
	 * The ability is registered in the content category with its label and annotations.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_registers_the_ability(): void {
		$this->register_abilities();

		$ability = wp_get_ability( self::ABILITY );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The core/content-delete ability should be registered.' );
		$this->assertSame( 'Delete Content', $ability->get_label(), 'The ability should have its label.' );
		$this->assertSame( 'content', $ability->get_category(), 'The ability should use the content category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The ability should be marked public.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertFalse( $annotations['readonly'], 'The ability should not be marked read-only.' );
		$this->assertTrue( $annotations['destructive'], 'The ability should be marked destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'The ability should be marked idempotent.' );
	}

	/**
	 * The input schema takes `where`, with the post type and ID, and `permanent`.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_input_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The delete ability should be registered.' );

		$schema = wp_get_ability( self::ABILITY )->get_input_schema();

		$this->assertSame( 'object', $schema['type'], 'The input should be an object.' );
		$this->assertFalse( $schema['additionalProperties'], 'The input should reject unknown keys.' );
		$this->assertSame( array( 'where' ), $schema['required'], 'The input should require where.' );
		$this->assertSame( array( 'where', 'permanent' ), array_keys( $schema['properties'] ), 'The input should take where and permanent.' );
		$this->assertSame( 'boolean', $schema['properties']['permanent']['type'], 'Permanent should be a boolean.' );

		$update_where = wp_get_ability( 'core/content-update' )->get_input_schema()['properties']['where'];

		$this->assertSame( $this->strip_descriptions( $update_where ), $this->strip_descriptions( $schema['properties']['where'] ), 'The where should be the same as on update.' );
		$this->assertSame( array( 'type', 'id' ), $schema['properties']['where']['required'], 'The where should require the type and the ID.' );
		$this->assertSame( 1, $schema['properties']['where']['properties']['id']['minimum'], 'The ID should be at least 1.' );
	}

	/**
	 * The output holds the outcome only.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_output_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The delete ability should be registered.' );

		$schema = wp_get_ability( self::ABILITY )->get_output_schema();

		$this->assertSame( 'object', $schema['type'], 'The output should be an object.' );
		$this->assertFalse( $schema['additionalProperties'], 'The output should not take unknown keys.' );
		$this->assertSame( array( 'outcome' ), $schema['required'], 'The output should require the outcome.' );
		$this->assertSame( array( 'outcome' ), array_keys( $schema['properties'] ), 'The output should hold the outcome only.' );
		$this->assertSame( 'string', $schema['properties']['outcome']['type'], 'The outcome should be a string.' );
		$this->assertSame( array( 'trashed', 'deleted' ), $schema['properties']['outcome']['enum'], 'The outcome should be one of trashed and deleted.' );
	}

	/**
	 * A page is deleted permanently with `permanent`.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_deletes_a_page_permanently(): void {
		$post_id = self::$post_ids['admin_page'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assert_deleted( $this->run_ability( $this->delete_input( $post_id, true ) ), $post_id, 'Deleting a page permanently' );
	}

	/**
	 * Trashing a post that is already in the Trash is the already-trashed error.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_rejects_trashing_a_trashed_post(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$input = $this->delete_input( $post_id );

		$this->assert_trashed( $this->run_ability( $input ), $post_id, 'The first call' );

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $input );

		$this->assertWPError( $result, 'Trashing the post again should fail.' );
		$this->assertSame(
			array(
				'code'    => 'content_already_trashed',
				'message' => 'The post has already been trashed.',
				'data'    => array( 'status' => 410 ),
			),
			array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
				'data'    => $result->get_error_data(),
			),
			'The error should be the already-trashed error.'
		);
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * A logged-out user is refused before the ability runs.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_a_logged_out_user(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $this->delete_input( $post_id, true ) );

		$this->assert_refused( $result, 'A logged-out user' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns inputs for which the permission callback returns false, even for an administrator.
	 *
	 * @return array<string, array{0: mixed}> The inputs.
	 */
	public static function data_inputs_refused_by_the_permission_callback(): array {
		return array(
			'no input'               => array( null ),
			'no where'               => array( array( 'permanent' => true ) ),
			'where that is a string' => array( array( 'where' => 'post' ) ),
			'type that is a number'  => array(
				array(
					'where' => array(
						'type' => 1,
						'id'   => 1,
					),
				),
			),
			'unexposed type'         => array(
				array(
					'where' => array(
						'type' => 'revision',
						'id'   => 1,
					),
				),
			),
		);
	}

	/**
	 * The permission callback returns false for a post the user cannot delete, in every form the
	 * post can be named in input that was not prepared.
	 *
	 * @ticket 66706
	 * @dataProvider data_unprepared_where_forms
	 * @since 7.3.0
	 *
	 * @param string $id_type   The type to send the ID as.
	 * @param bool   $as_object Whether to send `where` as an object.
	 */
	public function test_permission_callback_returns_false_for_a_post_the_user_cannot_delete( string $id_type, bool $as_object ): void {
		$this->login_as( 'contributor' );

		$permission = $this->get_ability_callbacks()['permission_callback'];
		$where      = $this->build_unprepared_where( self::$post_ids['admin_post'], $id_type, $as_object );

		$this->assertFalse( $permission( array( 'where' => $where ) ), 'The permission callback should return false.' );
	}

	/**
	 * The permission callback returns true for a post the user can delete.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_permission_callback_returns_true_for_a_post_the_user_can_delete(): void {
		$this->login_as( 'author' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertTrue( $permission( $this->delete_input( self::$post_ids['author:draft'] ) ), 'The permission callback should return true.' );
	}

	/**
	 * The permission callback lets a post that does not exist through, also for a user who may
	 * delete nothing.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_permission_callback_lets_a_missing_post_through(): void {
		$this->login_as( 'subscriber' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertTrue(
			$permission(
				array(
					'where' => array(
						'type' => 'post',
						'id'   => self::MISSING_ID,
					),
				)
			),
			'The permission callback should return true.'
		);
	}

	/**
	 * A permission result that a filter turned into a grant does not skip the capability check
	 * on the post.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_granted_permission_result_does_not_skip_the_capability_check(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'contributor' );
		$this->register_abilities();
		add_filter( 'wp_ability_permission_result', '__return_true' );

		$before    = $this->snapshot( $post_id );
		$trash     = $this->run_ability( $this->delete_input( $post_id ) );
		$permanent = $this->run_ability( $this->delete_input( $post_id, true ) );

		$this->assert_cannot_delete( $trash, 'A trash granted by a filter' );
		$this->assert_cannot_delete( $permanent, 'A permanent delete granted by a filter' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * An ID that is not a whole number of at least 1 fails input validation.
	 *
	 * @ticket 66706
	 * @dataProvider data_ids_that_are_not_whole_numbers_of_at_least_1
	 * @since 7.3.0
	 *
	 * @param mixed $id The ID.
	 */
	public function test_id_that_is_not_a_whole_number_fails_input_validation( $id ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( self::$post_ids['admin_post'] );
		$result = $this->run_ability(
			array(
				'where'     => array(
					'type' => 'post',
					'id'   => $id,
				),
				'permanent' => true,
			)
		);

		$this->assertWPError( $result, 'The call should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the ID.' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['admin_post'] ), 'Nothing should be written.' );
	}

	/**
	 * A revision, an autosave, an attachment, a post of another exposed or non-exposed type and a
	 * missing post are not found as a `post`, even by an administrator and even for a permanent
	 * delete.
	 *
	 * @ticket 66706
	 * @dataProvider data_posts_that_are_not_posts
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_post_of_another_type_is_not_found( string $post ): void {
		$post_id = self::$post_ids[ $post ] ?? self::MISSING_ID;

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability(
			array(
				'where'     => array(
					'type' => 'post',
					'id'   => $post_id,
				),
				'permanent' => true,
			)
		);

		$this->assert_not_found( $result, 'A post that is not a post' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Called directly, a post of a type that is not exposed is not found, whatever its ID.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_direct_call_for_a_type_that_is_not_exposed_is_not_found(): void {
		register_post_type( 'cw_hidden', array( 'public' => true ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'cw_hidden' ) );

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );
		$result = $execute( $this->delete_input( $post_id, true ) );

		$this->assert_not_found( $result, 'A direct call for a type that is not exposed' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * A post type whose `show_in_abilities` is turned off after registration cannot be deleted.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_type_no_longer_exposed_cannot_be_deleted(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'administrator' );
		$callbacks = $this->get_ability_callbacks();

		get_post_type_object( 'post' )->show_in_abilities = false;

		$input  = $this->delete_input( $post_id, true );
		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $input );

		$this->assert_refused( $result, 'A regular call for a type that is no longer exposed' );
		$this->assert_not_found( $callbacks['execute_callback']( $input ), 'A direct call for a type that is no longer exposed' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns direct-call inputs with a malformed `where`.
	 *
	 * @return array<string, array{0: array<string, mixed>}> The inputs.
	 */
	public static function data_direct_inputs_with_a_malformed_where(): array {
		return array(
			'no where' => array( array( 'permanent' => true ) ),
			'no type'  => array( array( 'where' => array( 'id' => 1 ) ) ),
			'no ID'    => array( array( 'where' => array( 'type' => 'post' ) ) ),
		);
	}

	/**
	 * Called directly, a malformed `where` is the not-found error.
	 *
	 * @ticket 66706
	 * @dataProvider data_direct_inputs_with_a_malformed_where
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $input The input.
	 */
	public function test_direct_call_with_a_malformed_where_is_not_found( array $input ): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( self::$post_ids['admin_post'] );

		$this->assert_not_found( $execute( $input ), 'A direct call with a malformed where' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['admin_post'] ), 'Nothing should be deleted for a malformed where.' );
	}

	/**
	 * The lookup comes before the capability: a user who may delete nothing gets the not-found
	 * error for a missing post, and is refused for an existing post.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_lookup_comes_before_the_capability(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$before   = $this->snapshot( self::$post_ids['admin_post'] );
		$missing  = $this->run_ability(
			array(
				'where' => array(
					'type' => 'post',
					'id'   => self::MISSING_ID,
				),
			)
		);
		$existing = $this->run_ability( $this->delete_input( self::$post_ids['admin_post'] ) );

		$this->assert_not_found( $missing, 'A missing post' );
		$this->assert_refused( $existing, 'An existing post the user may not delete' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['admin_post'] ), 'The refused post should still exist, unchanged.' );
	}

	/**
	 * Returns whether each role may delete each matrix post.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: bool}> The cases.
	 */
	public static function data_delete_matrix(): array {
		return array(
			'administrator, own, draft'           => array( 'administrator', 'administrator', 'draft', true ),
			'administrator, own, pending'         => array( 'administrator', 'administrator', 'pending', true ),
			'administrator, own, publish'         => array( 'administrator', 'administrator', 'publish', true ),
			'administrator, own, future'          => array( 'administrator', 'administrator', 'future', true ),
			'administrator, own, private'         => array( 'administrator', 'administrator', 'private', true ),
			'administrator, own, trash_draft'     => array( 'administrator', 'administrator', 'trash_draft', true ),
			'administrator, own, trash_publish'   => array( 'administrator', 'administrator', 'trash_publish', true ),
			'administrator, other, draft'         => array( 'administrator', 'other', 'draft', true ),
			'administrator, other, pending'       => array( 'administrator', 'other', 'pending', true ),
			'administrator, other, publish'       => array( 'administrator', 'other', 'publish', true ),
			'administrator, other, future'        => array( 'administrator', 'other', 'future', true ),
			'administrator, other, private'       => array( 'administrator', 'other', 'private', true ),
			'administrator, other, trash_draft'   => array( 'administrator', 'other', 'trash_draft', true ),
			'administrator, other, trash_publish' => array( 'administrator', 'other', 'trash_publish', true ),
			'editor, own, draft'                  => array( 'editor', 'editor', 'draft', true ),
			'editor, own, pending'                => array( 'editor', 'editor', 'pending', true ),
			'editor, own, publish'                => array( 'editor', 'editor', 'publish', true ),
			'editor, own, future'                 => array( 'editor', 'editor', 'future', true ),
			'editor, own, private'                => array( 'editor', 'editor', 'private', true ),
			'editor, own, trash_draft'            => array( 'editor', 'editor', 'trash_draft', true ),
			'editor, own, trash_publish'          => array( 'editor', 'editor', 'trash_publish', true ),
			'editor, other, draft'                => array( 'editor', 'other', 'draft', true ),
			'editor, other, pending'              => array( 'editor', 'other', 'pending', true ),
			'editor, other, publish'              => array( 'editor', 'other', 'publish', true ),
			'editor, other, future'               => array( 'editor', 'other', 'future', true ),
			'editor, other, private'              => array( 'editor', 'other', 'private', true ),
			'editor, other, trash_draft'          => array( 'editor', 'other', 'trash_draft', true ),
			'editor, other, trash_publish'        => array( 'editor', 'other', 'trash_publish', true ),
			'author, own, draft'                  => array( 'author', 'author', 'draft', true ),
			'author, own, pending'                => array( 'author', 'author', 'pending', true ),
			'author, own, publish'                => array( 'author', 'author', 'publish', true ),
			'author, own, future'                 => array( 'author', 'author', 'future', true ),
			'author, own, private'                => array( 'author', 'author', 'private', true ),
			'author, own, trash_draft'            => array( 'author', 'author', 'trash_draft', true ),
			'author, own, trash_publish'          => array( 'author', 'author', 'trash_publish', true ),
			'author, other, draft'                => array( 'author', 'other', 'draft', false ),
			'author, other, pending'              => array( 'author', 'other', 'pending', false ),
			'author, other, publish'              => array( 'author', 'other', 'publish', false ),
			'author, other, future'               => array( 'author', 'other', 'future', false ),
			'author, other, private'              => array( 'author', 'other', 'private', false ),
			'author, other, trash_draft'          => array( 'author', 'other', 'trash_draft', false ),
			'author, other, trash_publish'        => array( 'author', 'other', 'trash_publish', false ),
			'contributor, own, draft'             => array( 'contributor', 'contributor', 'draft', true ),
			'contributor, own, pending'           => array( 'contributor', 'contributor', 'pending', true ),
			'contributor, own, publish'           => array( 'contributor', 'contributor', 'publish', false ),
			'contributor, own, future'            => array( 'contributor', 'contributor', 'future', false ),
			'contributor, own, private'           => array( 'contributor', 'contributor', 'private', true ),
			'contributor, own, trash_draft'       => array( 'contributor', 'contributor', 'trash_draft', true ),
			'contributor, own, trash_publish'     => array( 'contributor', 'contributor', 'trash_publish', false ),
			'contributor, other, draft'           => array( 'contributor', 'other', 'draft', false ),
			'contributor, other, pending'         => array( 'contributor', 'other', 'pending', false ),
			'contributor, other, publish'         => array( 'contributor', 'other', 'publish', false ),
			'contributor, other, future'          => array( 'contributor', 'other', 'future', false ),
			'contributor, other, private'         => array( 'contributor', 'other', 'private', false ),
			'contributor, other, trash_draft'     => array( 'contributor', 'other', 'trash_draft', false ),
			'contributor, other, trash_publish'   => array( 'contributor', 'other', 'trash_publish', false ),
			'subscriber, own, draft'              => array( 'subscriber', 'subscriber', 'draft', false ),
			'subscriber, own, pending'            => array( 'subscriber', 'subscriber', 'pending', false ),
			'subscriber, own, publish'            => array( 'subscriber', 'subscriber', 'publish', false ),
			'subscriber, own, future'             => array( 'subscriber', 'subscriber', 'future', false ),
			'subscriber, own, private'            => array( 'subscriber', 'subscriber', 'private', false ),
			'subscriber, own, trash_draft'        => array( 'subscriber', 'subscriber', 'trash_draft', false ),
			'subscriber, own, trash_publish'      => array( 'subscriber', 'subscriber', 'trash_publish', false ),
			'subscriber, other, draft'            => array( 'subscriber', 'other', 'draft', false ),
			'subscriber, other, pending'          => array( 'subscriber', 'other', 'pending', false ),
			'subscriber, other, publish'          => array( 'subscriber', 'other', 'publish', false ),
			'subscriber, other, future'           => array( 'subscriber', 'other', 'future', false ),
			'subscriber, other, private'          => array( 'subscriber', 'other', 'private', false ),
			'subscriber, other, trash_draft'      => array( 'subscriber', 'other', 'trash_draft', false ),
			'subscriber, other, trash_publish'    => array( 'subscriber', 'other', 'trash_publish', false ),
		);
	}

	/**
	 * Returns the cases of the delete matrix, plus one that sends `permanent` set to false.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: bool, 4?: bool}> The cases.
	 */
	public static function data_trash_matrix(): array {
		return array_merge(
			self::data_delete_matrix(),
			array(
				'administrator, own, publish, permanent false' => array( 'administrator', 'administrator', 'publish', true, false ),
			)
		);
	}

	/**
	 * Each role may trash the posts it may delete, gets the 410 error for those already in the
	 * Trash, and is refused for the others, whatever their status. `permanent` set to false also
	 * moves the post to the Trash.
	 *
	 * @ticket 66706
	 * @dataProvider data_trash_matrix
	 * @since 7.3.0
	 *
	 * @param string    $role       The role to delete as.
	 * @param string    $owner      The role of the author of the post, or `other`.
	 * @param string    $status     The matrix status of the post.
	 * @param bool      $may_delete Whether the role may delete the post.
	 * @param bool|null $permanent  Optional. The `permanent` value, or null to send none. Default null.
	 */
	public function test_trash_matrix( string $role, string $owner, string $status, bool $may_delete, ?bool $permanent = null ): void {
		$post_id = self::$post_ids[ "{$owner}:{$status}" ];

		$this->login_as( $role );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $this->delete_input( $post_id, $permanent ) );

		if ( $may_delete && ! str_starts_with( $status, 'trash_' ) ) {
			$this->assert_trashed( $result, $post_id, 'Trashing a post the role may delete' );
			return;
		}

		if ( $may_delete ) {
			$this->assert_error( $result, 'content_already_trashed', 410, 'Trashing a trashed post the role may delete' );
		} else {
			$this->assert_refused( $result, 'Trashing a post the role may not delete' );
		}
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Each role may delete permanently the posts it may delete, including posts in the Trash, and
	 * is refused for the others, whatever their status.
	 *
	 * @ticket 66706
	 * @dataProvider data_delete_matrix
	 * @since 7.3.0
	 *
	 * @param string $role       The role to delete as.
	 * @param string $owner      The role of the author of the post, or `other`.
	 * @param string $status     The matrix status of the post.
	 * @param bool   $may_delete Whether the role may delete the post.
	 */
	public function test_permanent_delete_matrix( string $role, string $owner, string $status, bool $may_delete ): void {
		$post_id = self::$post_ids[ "{$owner}:{$status}" ];

		$this->login_as( $role );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $this->delete_input( $post_id, true ) );

		if ( $may_delete ) {
			$this->assert_deleted( $result, $post_id, 'Deleting a post the role may delete' );
			return;
		}

		$this->assert_refused( $result, 'Deleting a post the role may not delete' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * A post that cannot be moved to the Trash is not deleted in its place: the call fails
	 * and asks for `permanent`, which then deletes the post.
	 *
	 * Attachments only support the Trash when `MEDIA_TRASH` is on, so the test exposes them.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_post_without_trash_support_needs_permanent(): void {
		$post_id = self::$post_ids['attachment'];

		get_post_type_object( 'attachment' )->show_in_abilities = true;

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assertFalse( MEDIA_TRASH, 'Precondition: attachments should not support the Trash.' );

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $this->delete_input( $post_id ) );

		$this->assert_error( $result, 'content_trash_not_supported', 501, 'Trashing a post without Trash support' );
		$this->assertSame( 'The post does not support trashing. Set `permanent` to true to delete it.', $result->get_error_message(), 'The message should ask for a permanent delete.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'A post without Trash support should be left as it was.' );

		$this->assert_deleted( $this->run_ability( $this->delete_input( $post_id, true ) ), $post_id, 'Deleting a post without Trash support permanently' );
	}

	/**
	 * Returns the special pages of the site.
	 *
	 * @return array<string, array{0: string}> The pages, as fixture names.
	 */
	public static function data_special_pages(): array {
		return array(
			'front page'          => array( 'front_page' ),
			'posts page'          => array( 'posts_page' ),
			'privacy policy page' => array( 'privacy_page' ),
		);
	}

	/**
	 * Sets the special pages of the site.
	 *
	 * @since 7.3.0
	 */
	private function set_special_pages(): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$post_ids['front_page'] );
		update_option( 'page_for_posts', self::$post_ids['posts_page'] );
		update_option( 'wp_page_for_privacy_policy', self::$post_ids['privacy_page'] );
	}

	/**
	 * An editor may delete neither the front page, the posts page nor the privacy policy page.
	 *
	 * @ticket 66706
	 * @dataProvider data_special_pages
	 * @since 7.3.0
	 *
	 * @param string $page The page, as a fixture name.
	 */
	public function test_editor_cannot_delete_a_special_page( string $page ): void {
		$this->set_special_pages();
		$post_id = self::$post_ids[ $page ];

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before    = $this->snapshot( $post_id );
		$trash     = $this->run_ability( $this->delete_input( $post_id ) );
		$permanent = $this->run_ability( $this->delete_input( $post_id, true ) );

		$this->assert_refused( $trash, 'An editor trashing a special page' );
		$this->assert_refused( $permanent, 'An editor deleting a special page' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns the special pages of the site, each with a trash and a permanent delete.
	 *
	 * @return array<string, array{0: string, 1: bool}> The cases.
	 */
	public static function data_special_page_deletes(): array {
		return array(
			'front page, trash'              => array( 'front_page', false ),
			'front page, permanent'          => array( 'front_page', true ),
			'posts page, trash'              => array( 'posts_page', false ),
			'posts page, permanent'          => array( 'posts_page', true ),
			'privacy policy page, trash'     => array( 'privacy_page', false ),
			'privacy policy page, permanent' => array( 'privacy_page', true ),
		);
	}

	/**
	 * On a single site, an administrator may trash the special pages and delete them permanently.
	 *
	 * The page is not trashed before the permanent delete: trashing the front page or the posts
	 * page clears the option that makes it special.
	 *
	 * @group ms-excluded
	 *
	 * @ticket 66706
	 * @dataProvider data_special_page_deletes
	 * @since 7.3.0
	 *
	 * @param string $page      The page, as a fixture name.
	 * @param bool   $permanent Whether the delete is permanent.
	 */
	public function test_administrator_can_delete_a_special_page( string $page, bool $permanent ): void {
		$this->set_special_pages();
		$post_id = self::$post_ids[ $page ];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability( $this->delete_input( $post_id, $permanent ) );

		if ( $permanent ) {
			$this->assert_deleted( $result, $post_id, 'An administrator deleting a special page' );
		} else {
			$this->assert_trashed( $result, $post_id, 'An administrator trashing a special page' );
		}
	}

	/**
	 * Returns the special pages of the site and whether a site administrator may trash them on
	 * multisite.
	 *
	 * @return array<string, array{0: string, 1: bool}> The cases.
	 */
	public static function data_special_pages_for_a_site_administrator(): array {
		return array(
			'front page'          => array( 'front_page', true ),
			'posts page'          => array( 'posts_page', true ),
			'privacy policy page' => array( 'privacy_page', false ),
		);
	}

	/**
	 * On multisite, a site administrator may trash the front page and the posts page, but not
	 * the privacy policy page.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
	 * @dataProvider data_special_pages_for_a_site_administrator
	 * @since 7.3.0
	 *
	 * @param string $page    The page, as a fixture name.
	 * @param bool   $allowed Whether the site administrator may trash it.
	 */
	public function test_site_administrator_trashes_special_pages_on_multisite( string $page, bool $allowed ): void {
		$this->set_special_pages();
		$post_id = self::$post_ids[ $page ];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $this->delete_input( $post_id ) );

		if ( $allowed ) {
			$this->assert_trashed( $result, $post_id, 'A site administrator trashing a special page' );
			return;
		}

		$this->assert_refused( $result, 'A site administrator trashing the privacy policy page' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * On multisite, a super admin may delete the special pages.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
	 * @dataProvider data_special_pages
	 * @since 7.3.0
	 *
	 * @param string $page The page, as a fixture name.
	 */
	public function test_super_admin_can_delete_a_special_page_on_multisite( string $page ): void {
		$this->set_special_pages();
		$post_id = self::$post_ids[ $page ];

		$super_admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin );
		wp_set_current_user( $super_admin );

		$this->register_abilities();

		$this->assert_deleted( $this->run_ability( $this->delete_input( $post_id, true ) ), $post_id, 'A super admin deleting a special page' );
	}

	/**
	 * Returns values for the pre-trash and pre-delete filters that stop the operation.
	 *
	 * @return array<string, array{0: bool, 1: string, 2: mixed}> The cases.
	 */
	public static function data_operations_that_are_stopped(): array {
		return array(
			'trash, false'                    => array( false, 'pre_trash_post', false ),
			'trash, truthy without trashing'  => array( false, 'pre_trash_post', true ),
			'delete, false'                   => array( true, 'pre_delete_post', false ),
			'delete, truthy without deleting' => array( true, 'pre_delete_post', true ),
		);
	}

	/**
	 * When the trash or delete call fails or does not act, the result is the 500 error and the
	 * post is left as it was.
	 *
	 * @ticket 66706
	 * @dataProvider data_operations_that_are_stopped
	 * @since 7.3.0
	 *
	 * @param bool   $permanent Whether the delete is permanent.
	 * @param string $filter    The filter that stops the operation.
	 * @param mixed  $value     The value the filter returns.
	 */
	public function test_operation_that_does_not_act_is_an_error( bool $permanent, string $filter, $value ): void {
		$post_id = self::$post_ids['admin_post'];

		add_filter(
			$filter,
			static function () use ( $value ) {
				return $value;
			}
		);

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $this->delete_input( $post_id, $permanent ) );

		$this->assert_error( $result, 'content_delete_failed', 500, 'An operation that does not act' );
		$this->assertSame( 'The post could not be deleted.', $result->get_error_message(), 'The message should be the failure message.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns keys that are not part of the input, and where they are sent.
	 *
	 * @return array<string, array{0: string|null, 1: string, 2: mixed}> The cases.
	 */
	public static function data_keys_outside_the_allow_list(): array {
		return array(
			'force'        => array( null, 'force', true ),
			'ID'           => array( null, 'ID', 1 ),
			'where status' => array( 'where', 'status', 'trash' ),
			'where ID'     => array( 'where', 'ID', 1 ),
		);
	}

	/**
	 * A key outside the allow-list fails input validation.
	 *
	 * @ticket 66706
	 * @dataProvider data_keys_outside_the_allow_list
	 * @since 7.3.0
	 *
	 * @param string|null $location Where the key is sent, or null for the top level.
	 * @param string      $key      The key.
	 * @param mixed       $value    The value.
	 */
	public function test_key_outside_the_allow_list_fails_input_validation( ?string $location, string $key, $value ): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$input = $this->delete_input( $post_id );
		if ( null === $location ) {
			$input[ $key ] = $value;
		} else {
			$input[ $location ][ $key ] = $value;
		}

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $input );

		$this->assertWPError( $result, 'The call should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the key.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * The current user is checked at each call, also on the same instance.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_each_call_checks_the_current_user(): void {
		$own_id   = self::$post_ids['author:draft'];
		$other_id = self::$post_ids['author:pending'];
		$execute  = $this->get_ability_callbacks()['execute_callback'];

		$this->login_as( 'author' );
		$first = $execute( $this->delete_input( $own_id ) );

		$this->login_as( 'contributor' );
		$before = $this->snapshot( $other_id );
		$second = $execute( $this->delete_input( $other_id ) );

		$this->assert_trashed( $first, $own_id, 'The first call as the author' );
		$this->assert_cannot_delete( $second, 'The second call as a contributor' );
		$this->assertSame( $before, $this->snapshot( $other_id ), 'The second call should write nothing.' );
	}
}
