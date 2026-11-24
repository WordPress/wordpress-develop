<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-media-item-ability.php';

/**
 * Tests for the core/media-delete ability.
 *
 * @covers WP_Abilities_Media
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreMediaDeleteAbility extends WP_Abilities_Media_Item_UnitTestCase {

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
		$input = array( 'where' => array( 'id' => self::$post_ids[ $attachment ] ?? REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );

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
	 * Returns whether the site can move attachments to the Trash.
	 *
	 * @since 7.3.0
	 *
	 * @return bool Whether attachments can be trashed.
	 */
	private static function site_can_trash_attachments(): bool {
		return EMPTY_TRASH_DAYS >= 1 && MEDIA_TRASH;
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
	 * Returns an input for the given `where` value.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $where The `where` value.
	 * @return array<string, mixed> The input.
	 */
	protected function build_input( array $where ): array {
		return array(
			'where'     => $where,
			'permanent' => true,
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
	 * The input takes the attachment ID and the permanent flag, and the output is the outcome.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_schemas(): void {
		$this->register_abilities();
		$ability = wp_get_ability( self::ABILITY );
		$input   = $ability->get_input_schema();
		$output  = $ability->get_output_schema();

		$this->assert_object_schema( $input, array( 'where' ), array( 'where', 'permanent' ), 'The input schema' );
		$this->assert_where_id_schema( $input['properties']['where'] );
		$this->assert_object_schema( $output, array( 'outcome' ), array( 'outcome' ), 'The output schema' );
		$this->assertSame( array( 'deleted', 'trashed' ), $output['properties']['outcome']['enum'] ?? null, 'The outcome should be deleted or trashed.' );
	}

	/**
	 * Without `delete_post` the callback refuses the call and nothing is deleted.
	 *
	 * @ticket 66719
	 * @dataProvider data_callers_without_the_capability
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
			'where status' => array( 'where', 'status', 'trash' ),
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

		$this->assert_invalid_input( $this->run_ability( $input ), 'A key outside the allow-list' );
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
	 * The attachment deletion hook fires once for the attachment on a permanent deletion.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permanent_deletion_runs_the_attachment_hook(): void {
		$deleted = array();
		add_action(
			'delete_attachment',
			static function ( int $post_id ) use ( &$deleted ): void {
				$deleted[] = $post_id;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assertSame( array( 'outcome' => 'deleted' ), $this->run_ability( $this->delete_input( 'editor_image', true ) ), 'The outcome of the deletion by the editor should be deleted.' );
		$this->assertSame( array( self::$post_ids['editor_image'] ), $deleted, 'The attachment deletion hook should fire once for the attachment.' );
	}
}
