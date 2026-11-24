<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-content-existing-post-ability.php';

/**
 * Tests for the core/content-delete ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Content
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreContentDeleteAbility extends WP_Abilities_Content_Existing_Post_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/content-delete';

	/**
	 * The label of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const LABEL = 'Delete Content';

	/**
	 * The annotations of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, bool>
	 */
	protected const ANNOTATIONS = array(
		'readonly'    => false,
		'destructive' => true,
		'idempotent'  => true,
	);

	/**
	 * The input that the shared tests send next to `where`.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, mixed>
	 */
	protected const OTHER_INPUT = array( 'permanent' => true );

	/**
	 * Creates shared users and posts.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::create_shared_fixtures( $factory );
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
		$input = array( 'where' => $this->build_where( $post_id ) );
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
	protected function assert_cannot_write( $result, string $context ): void {
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
	 * Returns the cases of the capability matrix, plus one that sends `permanent` set to false.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: bool, 4?: bool}> The cases.
	 */
	public static function data_trash_matrix(): array {
		return array_merge(
			self::data_capability_matrix(),
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
			$this->assertSame( 'The post has already been trashed.', $result->get_error_message(), 'The message should be the already-trashed message.' );
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
	 * @dataProvider data_capability_matrix
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
	 * On multisite, a site administrator may trash the front page and the posts page, but not
	 * the privacy policy page.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
	 * @dataProvider data_special_pages
	 * @since 7.3.0
	 *
	 * @param string $page The page, as a fixture name.
	 */
	public function test_site_administrator_trashes_special_pages_on_multisite( string $page ): void {
		$this->set_special_pages();
		$post_id = self::$post_ids[ $page ];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $this->delete_input( $post_id ) );

		if ( 'privacy_page' !== $page ) {
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
	 * @return array<string, array{0: bool, 1: mixed}> The cases.
	 */
	public static function data_operations_that_are_stopped(): array {
		return array(
			'trash, false'                    => array( false, false ),
			'trash, truthy without trashing'  => array( false, true ),
			'delete, false'                   => array( true, false ),
			'delete, truthy without deleting' => array( true, true ),
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
	 * @param bool  $permanent Whether the delete is permanent.
	 * @param mixed $value     The value that the pre-trash or pre-delete filter returns.
	 */
	public function test_operation_that_does_not_act_is_an_error( bool $permanent, $value ): void {
		$post_id = self::$post_ids['admin_post'];

		add_filter(
			$permanent ? 'pre_delete_post' : 'pre_trash_post',
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
}
