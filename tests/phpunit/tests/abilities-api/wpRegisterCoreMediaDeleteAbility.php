<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-media-ability.php';

/**
 * Tests for the core/media-delete ability.
 *
 * @covers WP_Abilities_Media
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreMediaDeleteAbility extends WP_Abilities_Media_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/media-delete';

	/**
	 * The message of a call that cannot trash.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	private const TRASH_NOT_SUPPORTED = 'The attachment does not support trashing. Set "permanent" to delete it.';

	/**
	 * The message of a deletion Core did not carry out.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	private const DELETE_FAILED = 'The attachment could not be deleted.';

	/**
	 * Creates the shared fixtures.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::create_shared_fixtures( $factory );
	}

	/**
	 * Returns a delete input for a shared attachment.
	 *
	 * @since 7.3.0
	 *
	 * @param string    $attachment The attachment, as a fixture name.
	 * @param bool|null $permanent  Optional. The `permanent` value, or null to leave it out. Default null.
	 * @return array<string, mixed> The input.
	 */
	private function delete_input( string $attachment, ?bool $permanent = null ): array {
		$input = array( 'where' => array( 'id' => self::$post_ids[ $attachment ] ?? self::MISSING_ID ) );

		if ( null !== $permanent ) {
			$input['permanent'] = $permanent;
		}

		return $input;
	}

	/**
	 * Skips the test unless the site can move attachments to the Trash.
	 *
	 * @since 7.3.0
	 */
	private function require_trash(): void {
		if ( ! self::site_can_trash_attachments() ) {
			$this->markTestSkipped( 'The site cannot move attachments to the Trash.' );
		}
	}

	/**
	 * Returns the `permanent` values that ask for the Trash.
	 *
	 * @return array<string, array{permanent: bool|null}> The values, null for a missing key.
	 */
	public static function data_trash_requests(): array {
		return array(
			'false'   => array( false ),
			'missing' => array( null ),
		);
	}

	/**
	 * The ability is registered with its label, category, meta and annotations.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_registers_the_delete_ability(): void {
		$this->assert_registration( 'Delete Media', false, true, true );
	}

	/**
	 * The input and output schemas are the published ones.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_schemas(): void {
		$this->register_abilities();
		$ability = wp_get_ability( self::ABILITY );

		$expected_input  = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where' ),
			'properties'           => array(
				'where'     => self::where_id_schema(),
				'permanent' => array( 'type' => 'boolean' ),
			),
		);
		$expected_output = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'outcome' ),
			'properties'           => array(
				'outcome' => array( 'type' => 'string' ),
			),
		);

		$this->assertSame( $this->normalize_schema( $expected_input ), $this->normalize_schema( $ability->get_input_schema() ), 'The input schema should match.' );
		$this->assertSame( $this->normalize_schema( $expected_output ), $this->normalize_schema( $ability->get_output_schema() ), 'The output schema should match.' );
	}

	/**
	 * Returns callers with whether the gate lets them delete an attachment of the author, or
	 * their own attachment.
	 *
	 * @return array<string, array{role: string, expected: bool, own?: bool}> The cases.
	 */
	public static function data_gate_roles(): array {
		return array(
			'logged out'                      => array( 'logged_out', false ),
			'subscriber'                      => array( 'subscriber', false ),
			'contributor'                     => array( 'contributor', false ),
			'no caps'                         => array( 'no_caps', false ),
			'read and upload'                 => array( 'uploader', false ),
			'read and upload, own attachment' => array( 'uploader', false, true ),
			'own author'                      => array( 'author', true ),
			'editor'                          => array( 'editor', true ),
			'administrator'                   => array( 'administrator', true ),
		);
	}

	/**
	 * The permission callback needs `delete_post` on the target.
	 *
	 * @ticket 66719
	 * @dataProvider data_gate_roles
	 * @since 7.3.0
	 *
	 * @param string $role     The role.
	 * @param bool   $expected Whether the gate passes.
	 * @param bool   $own      Optional. Whether the target is the caller's own attachment. Default false.
	 */
	public function test_permission_callback_needs_delete_post( string $role, bool $expected, bool $own = false ): void {
		$id = $own ? $this->create_attachment_of( $role ) : self::$post_ids['unattached'];

		$this->login_as( $role );
		$this->register_abilities();

		$input = array(
			'where'     => array( 'id' => $id ),
			'permanent' => true,
		);

		$this->assertSame( $expected, wp_get_ability( self::ABILITY )->check_permissions( $input ), 'The gate should follow delete_post on the target.' );
	}

	/**
	 * The permission callback refuses an administrator when the attachment type has
	 * `show_in_rest` off, and nothing is deleted.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_when_attachments_are_hidden(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		get_post_type_object( 'attachment' )->show_in_rest = false;

		$input = $this->delete_input( 'unattached', true );
		$this->assertFalse( wp_get_ability( self::ABILITY )->check_permissions( $input ), 'The administrator should be refused.' );
		$this->assert_refused( $this->run_ability( $input ), 'A deletion while attachments are hidden' );
		$this->assertInstanceOf( WP_Post::class, get_post( self::$post_ids['unattached'] ), 'The attachment should still exist.' );
	}

	/**
	 * A refused caller gets the generic error and nothing is deleted.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_refused_caller_gets_the_generic_error(): void {
		$this->login_as( 'contributor' );
		$this->register_abilities();

		$this->assert_refused( $this->run_ability( $this->delete_input( 'unattached', true ) ), 'A deletion by a contributor' );
		$this->assertInstanceOf( WP_Post::class, get_post( self::$post_ids['unattached'] ), 'The attachment should still exist.' );
	}

	/**
	 * The permission callback passes when the ID names no attachment, so that the call
	 * answers that nothing was found, and nothing is deleted.
	 *
	 * @ticket 66719
	 * @dataProvider data_ids_that_name_no_attachment
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_id_that_names_no_attachment_is_not_found( string $post ): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$input  = $this->delete_input( $post, true );
		$before = $this->snapshot();

		$this->assertTrue( wp_get_ability( self::ABILITY )->check_permissions( $input ), 'The gate should pass.' );
		$this->assert_not_found( $this->run_ability( $input ), 'A deletion of something that is not an attachment' );
		$this->assertSame( $before['posts'], $this->snapshot()['posts'], 'Nothing should be deleted.' );
	}

	/**
	 * The permission callback returns false for input without a usable ID.
	 *
	 * @ticket 66719
	 * @dataProvider data_inputs_without_a_usable_id
	 * @since 7.3.0
	 *
	 * @param mixed $input The input.
	 */
	public function test_permission_callback_refuses_input_without_a_usable_id( $input ): void {
		$this->login_as( 'administrator' );
		$callbacks = $this->get_ability_callbacks();

		$this->assertFalse( call_user_func( $callbacks['permission_callback'], $input ), 'The gate should refuse input without a usable ID.' );
	}

	/**
	 * The permission callback returns false for the ID of an attachment the caller may delete
	 * followed by letters.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_an_id_followed_by_letters(): void {
		$this->login_as( 'administrator' );
		$callbacks = $this->get_ability_callbacks();

		$input = array(
			'where'     => array( 'id' => self::$post_ids['unattached'] . 'abc' ),
			'permanent' => true,
		);

		$this->assertFalse( call_user_func( $callbacks['permission_callback'], $input ), 'The gate should refuse an ID followed by letters.' );
	}

	/**
	 * The permission callback accepts the ID in any form the integer type accepts.
	 *
	 * @ticket 66719
	 * @dataProvider data_accepted_id_forms
	 * @since 7.3.0
	 *
	 * @param string $form The form of the ID.
	 */
	public function test_permission_callback_accepts_id_forms( string $form ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$input = array(
			'where'     => array( 'id' => $this->id_in_form( self::$post_ids['unattached'], $form ) ),
			'permanent' => true,
		);

		$this->assertTrue( wp_get_ability( self::ABILITY )->check_permissions( $input ), 'The gate should accept the ID form.' );
	}

	/**
	 * Returns IDs that are missing or below 1.
	 *
	 * @return array<string, array{where: array<string, int>}> The `where` values.
	 */
	public static function data_unusable_ids(): array {
		return array(
			'missing'  => array( array() ),
			'zero'     => array( array( 'id' => 0 ) ),
			'negative' => array( array( 'id' => -1 ) ),
		);
	}

	/**
	 * With the gate and the validation skipped, an ID that is missing or below 1 is not found.
	 *
	 * @ticket 66719
	 * @dataProvider data_unusable_ids
	 * @since 7.3.0
	 *
	 * @param array<string, int> $where The `where` value.
	 */
	public function test_unusable_id_is_not_found( array $where ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();
		$this->skip_input_validation();
		$this->force_permission();

		$this->assert_not_found(
			$this->run_ability(
				array(
					'where'     => $where,
					'permanent' => true,
				)
			),
			'A deletion with an unusable ID'
		);
	}

	/**
	 * A permanent deletion with an ID of 0 is not found, also when the global post is an
	 * attachment the caller may delete, and that attachment is kept unchanged.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_lookup_never_uses_the_global_post(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();
		$this->skip_input_validation();
		$this->force_permission();

		$GLOBALS['post'] = get_post( self::$post_ids['unattached'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The lookup should not fall back to it.

		$before = $this->snapshot( self::$post_ids['unattached'] );
		$result = $this->run_ability(
			array(
				'where'     => array( 'id' => 0 ),
				'permanent' => true,
			)
		);
		$after  = $this->snapshot( self::$post_ids['unattached'] );

		$this->assert_not_found( $result, 'A permanent deletion with an ID of 0' );
		$this->assertSame( $before['post'], $after['post'], 'The attachment row should not change.' );
		$this->assertSame( $before['post_meta'], $after['post_meta'], 'The attachment meta should not change.' );
		$this->assertSame( $before['posts'], $after['posts'], 'No post should be removed.' );
	}

	/**
	 * Returns the callers the gate refuses, with the status they get.
	 *
	 * @return array<string, array{role: string, status: int, own: bool}> The cases.
	 */
	public static function data_callers_without_delete_post(): array {
		$cases = array();

		foreach ( self::data_gate_roles() as $name => $row ) {
			if ( ! $row[1] ) {
				$cases[ $name ] = array( $row[0], 'logged_out' === $row[0] ? 401 : 403, $row[2] ?? false );
			}
		}

		return $cases;
	}

	/**
	 * Without `delete_post` the callback refuses the call and nothing is deleted.
	 *
	 * @ticket 66719
	 * @dataProvider data_callers_without_delete_post
	 * @since 7.3.0
	 *
	 * @param string $role   The role.
	 * @param int    $status The expected status.
	 * @param bool   $own    Whether the target is the caller's own attachment.
	 */
	public function test_callback_refuses_without_delete_post( string $role, int $status, bool $own ): void {
		$id = $own ? $this->create_attachment_of( $role ) : self::$post_ids['unattached'];

		$this->login_as( $role );
		$this->register_abilities();
		$this->force_permission();

		$before = $this->snapshot( $id );
		$result = $this->run_ability(
			array(
				'where'     => array( 'id' => $id ),
				'permanent' => true,
			)
		);

		$this->assert_error( $result, 'media_cannot_delete', $status, 'A deletion without delete_post' );
		$this->assert_error_message( $result, 'Sorry, you are not allowed to delete this post.', 'A deletion without delete_post' );
		$this->assertSame( $before, $this->snapshot( $id ), 'Nothing should change.' );
	}

	/**
	 * Returns keys that are not part of the input, and where they are sent.
	 *
	 * @return array<string, array{location: string|null, key: string, value: mixed}> The cases.
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
	 * A key outside the allow-list fails input validation, and nothing is deleted.
	 *
	 * @ticket 66719
	 * @dataProvider data_keys_outside_the_allow_list
	 * @since 7.3.0
	 *
	 * @param string|null $location Where the key is sent, or null for the top level.
	 * @param string      $key      The key.
	 * @param mixed       $value    The value.
	 */
	public function test_key_outside_the_allow_list_fails_input_validation( ?string $location, string $key, $value ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$input = $this->delete_input( 'unattached', true );
		if ( null === $location ) {
			$input[ $key ] = $value;
		} else {
			$input[ $location ][ $key ] = $value;
		}

		$before = $this->snapshot( self::$post_ids['unattached'] );
		$result = $this->run_ability( $input );

		$this->assertWPError( $result, 'The call should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the key.' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['unattached'] ), 'Nothing should be written.' );
	}

	/**
	 * The lookup and the capability come before the Trash checks.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_lookup_and_capability_come_first(): void {
		$this->login_as( 'contributor' );
		$this->register_abilities();
		$this->force_permission();

		$this->assert_not_found( $this->run_ability( $this->delete_input( 'missing' ) ), 'A trash request for a missing attachment' );
		$this->assert_error( $this->run_ability( $this->delete_input( 'trashed' ) ), 'media_cannot_delete', 403, 'A trash request for a trashed attachment without delete_post' );
	}

	/**
	 * Returns attachments of every status.
	 *
	 * @return array<string, array{attachment: string}> The attachments, as fixture names.
	 */
	public static function data_attachments_of_every_status(): array {
		return array(
			'inherit' => array( 'unattached' ),
			'private' => array( 'private' ),
			'trashed' => array( 'trashed' ),
		);
	}

	/**
	 * A permanent deletion removes the attachment whatever its status.
	 *
	 * @ticket 66719
	 * @dataProvider data_attachments_of_every_status
	 * @since 7.3.0
	 *
	 * @param string $attachment The attachment, as a fixture name.
	 */
	public function test_permanent_deletion( string $attachment ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assertSame( array( 'outcome' => 'deleted' ), $this->run_ability( $this->delete_input( $attachment, true ) ), 'The outcome should be deleted.' );
		$this->assertNull( get_post( self::$post_ids[ $attachment ] ), 'The attachment should no longer exist.' );
	}

	/**
	 * A permanent deletion removes the stored file and its sub-sizes.
	 *
	 * @ticket 66719
	 * @requires extension gd
	 * @since 7.3.0
	 */
	public function test_permanent_deletion_removes_the_files(): void {
		$id    = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$file  = get_attached_file( $id );
		$sizes = wp_get_attachment_metadata( $id )['sizes'] ?? array();

		$this->assertFileExists( $file, 'The stored file should exist before the deletion.' );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assertSame(
			array( 'outcome' => 'deleted' ),
			$this->run_ability(
				array(
					'where'     => array( 'id' => $id ),
					'permanent' => true,
				)
			),
			'The outcome should be deleted.'
		);
		$this->assertNull( get_post( $id ), 'The attachment should no longer exist.' );
		$this->assertFileDoesNotExist( $file, 'The stored file should be removed.' );

		foreach ( $sizes as $name => $size ) {
			$this->assertFileDoesNotExist( dirname( $file ) . '/' . $size['file'], "The {$name} sub-size {$size['file']} should be removed." );
		}
	}

	/**
	 * When the site cannot trash attachments, a trash request is refused and nothing is
	 * deleted.
	 *
	 * @ticket 66719
	 * @dataProvider data_trash_requests
	 * @since 7.3.0
	 *
	 * @param bool|null $permanent The `permanent` value, null for a missing key.
	 */
	public function test_trash_not_supported( ?bool $permanent ): void {
		if ( self::site_can_trash_attachments() ) {
			$this->markTestSkipped( 'The site can move attachments to the Trash.' );
		}

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( self::$post_ids['unattached'] );
		$result = $this->run_ability( $this->delete_input( 'unattached', $permanent ) );

		$this->assert_error( $result, 'media_trash_not_supported', 501, 'A trash request' );
		$this->assert_error_message( $result, self::TRASH_NOT_SUPPORTED, 'A trash request' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['unattached'] ), 'Nothing should change.' );
	}

	/**
	 * When the site cannot trash attachments, a trash request for a trashed attachment is
	 * refused as unsupported, before the Trash state is read.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_trash_not_supported_comes_before_already_trashed(): void {
		if ( self::site_can_trash_attachments() ) {
			$this->markTestSkipped( 'The site can move attachments to the Trash.' );
		}

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assert_error( $this->run_ability( $this->delete_input( 'trashed' ) ), 'media_trash_not_supported', 501, 'A trash request for a trashed attachment' );
		$this->assertSame( 'trash', get_post( self::$post_ids['trashed'] )->post_status, 'The attachment should stay in the Trash.' );
	}

	/**
	 * When the site can trash attachments, a trash request moves the attachment to the Trash.
	 *
	 * @ticket 66719
	 * @dataProvider data_trash_requests
	 * @since 7.3.0
	 *
	 * @param bool|null $permanent The `permanent` value, null for a missing key.
	 */
	public function test_trash( ?bool $permanent ): void {
		$this->require_trash();

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assertSame( array( 'outcome' => 'trashed' ), $this->run_ability( $this->delete_input( 'unattached', $permanent ) ), 'The outcome should be trashed.' );
		$this->assertSame( 'trash', get_post( self::$post_ids['unattached'] )->post_status, 'The attachment should be in the Trash.' );
	}

	/**
	 * When the site can trash attachments, a trash request for a trashed attachment is
	 * refused and nothing changes.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_already_trashed(): void {
		$this->require_trash();

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( self::$post_ids['trashed'] );
		$result = $this->run_ability( $this->delete_input( 'trashed' ) );

		$this->assert_error( $result, 'media_already_trashed', 410, 'A trash request for a trashed attachment' );
		$this->assert_error_message( $result, 'The attachment has already been trashed.', 'A trash request for a trashed attachment' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['trashed'] ), 'Nothing should change.' );
	}

	/**
	 * When a filter makes Core skip the permanent deletion, the call fails and the attachment
	 * stays.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_skipped_deletion_fails(): void {
		add_filter( 'pre_delete_attachment', '__return_false' );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability( $this->delete_input( 'unattached', true ) );

		$this->assert_error( $result, 'media_delete_failed', 500, 'A skipped deletion' );
		$this->assert_error_message( $result, self::DELETE_FAILED, 'A skipped deletion' );
		$this->assertInstanceOf( WP_Post::class, get_post( self::$post_ids['unattached'] ), 'The attachment should still exist.' );
	}

	/**
	 * When a filter makes Core skip the move to the Trash, the call fails and the attachment
	 * keeps its status.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_skipped_trash_fails(): void {
		$this->require_trash();

		add_filter( 'pre_trash_post', '__return_false' );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability( $this->delete_input( 'unattached', false ) );

		$this->assert_error( $result, 'media_delete_failed', 500, 'A skipped move to the Trash' );
		$this->assert_error_message( $result, self::DELETE_FAILED, 'A skipped move to the Trash' );
		$this->assertSame( 'inherit', get_post( self::$post_ids['unattached'] )->post_status, 'The attachment should keep its status.' );
	}

	/**
	 * The own author deletes an own attachment.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_own_author_deletes_an_own_attachment(): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$this->assertSame( array( 'outcome' => 'deleted' ), $this->run_ability( $this->delete_input( 'pdf', true ) ), 'The outcome should be deleted.' );
		$this->assertNull( get_post( self::$post_ids['pdf'] ), 'The attachment should no longer exist.' );
	}

	/**
	 * A listener on the permanent deletion sees the caller as the current user.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permanent_deletion_runs_as_the_caller(): void {
		$deleted_by = array();
		add_action(
			'delete_attachment',
			static function () use ( &$deleted_by ): void {
				$deleted_by[] = get_current_user_id();
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assertSame( array( 'outcome' => 'deleted' ), $this->run_ability( $this->delete_input( 'editor_image', true ) ), 'The outcome of the deletion by the editor should be deleted.' );
		$this->assertSame( array( self::$user_ids['editor'] ), $deleted_by, 'The listener should see the caller as the current user.' );
	}
}
