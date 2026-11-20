<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-user-write-ability.php';

/**
 * Tests for the core/user-delete ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Users
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreUserDeleteAbility extends WP_Abilities_User_Write_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/user-delete';

	/**
	 * Builds the input that deletes a user.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $user_id  The user ID.
	 * @param mixed $reassign The reassign value, or null to send none.
	 * @return array<string, mixed> The input.
	 */
	private function delete_input( $user_id, $reassign ): array {
		$input = array( 'where' => array( 'id' => $user_id ) );
		if ( null !== $reassign ) {
			$input['reassign'] = $reassign;
		}

		return $input;
	}

	/**
	 * Creates a user who authored a published post and owns a link.
	 *
	 * @since 7.3.0
	 *
	 * @param string $login The username.
	 * @return array{user: int, post: int, link: int} The IDs of the user, the post and the link.
	 */
	private function create_target_with_content( string $login ): array {
		$user_id = $this->create_target( $login );

		return array(
			'user' => $user_id,
			'post' => self::factory()->post->create(
				array(
					'post_author' => $user_id,
					'post_status' => 'publish',
				)
			),
			'link' => (int) wp_insert_link(
				array(
					'link_url'   => 'https://example.test/',
					'link_name'  => 'UWT link',
					'link_owner' => $user_id,
				)
			),
		);
	}

	/**
	 * Returns the author and status of a post, read from the database.
	 *
	 * @since 7.3.0
	 *
	 * @param int $post_id The post ID.
	 * @return array{author: int, status: string}|null The author and status, or null when the post is gone.
	 */
	private function get_stored_post( int $post_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT post_author, post_status FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A );

		return null === $row ? null : array(
			'author' => (int) $row['post_author'],
			'status' => $row['post_status'],
		);
	}

	/**
	 * Returns the owner of a link, read from the database.
	 *
	 * @since 7.3.0
	 *
	 * @param int $link_id The link ID.
	 * @return int|null The owner, or null when the link is gone.
	 */
	private function get_stored_link_owner( int $link_id ): ?int {
		global $wpdb;

		$owner = $wpdb->get_var( $wpdb->prepare( "SELECT link_owner FROM {$wpdb->links} WHERE link_id = %d", $link_id ) );

		return null === $owner ? null : (int) $owner;
	}

	/**
	 * Returns whether a user row exists, read from the database.
	 *
	 * @since 7.3.0
	 *
	 * @param int $user_id The user ID.
	 * @return bool Whether the user exists.
	 */
	private function user_row_exists( int $user_id ): bool {
		global $wpdb;

		return '1' === (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID = %d", $user_id ) );
	}

	/**
	 * Returns the outcome of a successful call on this kind of install.
	 *
	 * @since 7.3.0
	 *
	 * @return array{outcome: string} The output.
	 */
	private function get_success_output(): array {
		return array( 'outcome' => is_multisite() ? 'removed' : 'deleted' );
	}

	/**
	 * Asserts that a user was deleted, or on multisite removed from the current site only.
	 *
	 * @since 7.3.0
	 *
	 * @param int    $user_id The user ID.
	 * @param string $context What the call was, for the assertion messages.
	 */
	private function assert_user_gone( int $user_id, string $context ): void {
		if ( is_multisite() ) {
			$this->assertFalse( is_user_member_of_blog( $user_id, get_current_blog_id() ), "{$context}: the user should no longer be a member of the site." );
			$this->assertTrue( $this->user_row_exists( $user_id ), "{$context}: the account should stay." );
		} else {
			$this->assertFalse( $this->user_row_exists( $user_id ), "{$context}: the user should be deleted." );
		}
	}

	/**
	 * The ability is registered in the user category with its label and annotations.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_registers_the_ability(): void {
		$this->assert_registration( 'Delete User', true, false );
	}

	/**
	 * The input schema takes `where` with the ID and a required `reassign`.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_input_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The delete ability should be registered.' );

		$expected = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where', 'reassign' ),
			'properties'           => array(
				'where'    => $this->get_where_schema(),
				'reassign' => array(
					'type'    => array( 'integer', 'boolean' ),
					'minimum' => 1,
				),
			),
		);

		$this->assertSame( $this->normalize_schema( $expected ), $this->normalize_schema( wp_get_ability( self::ABILITY )->get_input_schema() ) );
	}

	/**
	 * The output schema is an object with the outcome only.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_output_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The delete ability should be registered.' );

		$expected = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'outcome' ),
			'properties'           => array(
				'outcome' => array( 'type' => 'string' ),
			),
		);

		$this->assertSame( $this->normalize_schema( $expected ), $this->normalize_schema( wp_get_ability( self::ABILITY )->get_output_schema() ) );
	}

	/**
	 * The user is deleted, or on multisite removed from the current site, and a second
	 * call finds no user.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_deletes_the_user_once(): void {
		$user_id = $this->create_target( 'uwtgone' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assertSame( $this->get_success_output(), $this->run_ability( $this->delete_input( $user_id, false ) ), 'The output should give the outcome.' );
		$this->assert_user_gone( $user_id, 'Deleting a user' );
		$this->assertSame( array(), $this->get_mails(), 'No mail should be sent.' );

		$this->assert_core_error( $this->run_ability( $this->delete_input( $user_id, false ) ), 'user_not_found', 'Deleting the same user again' );
	}

	/**
	 * Without reassignment the posts of a deleted user go to the trash and their links
	 * are deleted; on multisite, where the user is only removed, they keep their author.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_no_reassignment
	 *
	 * @param mixed $reassign The reassign value.
	 */
	public function test_deletes_without_reassigning( $reassign ): void {
		$target = $this->create_target_with_content( 'uwtnoreassign' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assertSame( $this->get_success_output(), $this->run_ability( $this->delete_input( $target['user'], $reassign ) ), 'The user should be deleted.' );
		$this->assert_user_gone( $target['user'], 'Deleting without reassigning' );

		if ( is_multisite() ) {
			$this->assertSame(
				array(
					'author' => $target['user'],
					'status' => 'publish',
				),
				$this->get_stored_post( $target['post'] ),
				'The post should keep its author.'
			);
			$this->assertSame( $target['user'], $this->get_stored_link_owner( $target['link'] ), 'The link should keep its owner.' );
		} else {
			$this->assertSame( 'trash', $this->get_stored_post( $target['post'] )['status'] ?? null, 'The post should go to the trash.' );
			$this->assertNull( $this->get_stored_link_owner( $target['link'] ), 'The link should be deleted.' );
		}
	}

	/**
	 * Returns the values that reassign nothing.
	 *
	 * @return array<string, array{reassign: mixed}> The reassign values.
	 */
	public static function data_no_reassignment(): array {
		return array(
			'false'         => array( false ),
			'false as text' => array( 'false' ),
		);
	}

	/**
	 * Without reassignment, on a single site, a page goes to the trash, a post that is
	 * already in the trash and an attachment are deleted permanently, and a pattern stays
	 * with its author.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_content_deleted_with_its_author
	 *
	 * @param string      $post_type   The post type.
	 * @param string      $post_status The post status.
	 * @param string|null $status      The status afterwards, or null when the post is
	 *                                 deleted permanently.
	 */
	public function test_deletes_content_by_its_kind_without_reassigning( string $post_type, string $post_status, ?string $status ): void {
		$user_id = $this->create_target( 'uwtcontent' );
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_type'   => $post_type,
				'post_status' => $post_status,
			)
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assertSame(
			array(
				'author' => $user_id,
				'status' => $post_status,
			),
			$this->get_stored_post( $post_id ),
			'The content should be stored for the user before the call.'
		);
		$this->assertSame( array( 'outcome' => 'deleted' ), $this->run_ability( $this->delete_input( $user_id, false ) ), 'The user should be deleted.' );
		$this->assertFalse( $this->user_row_exists( $user_id ), 'The user row should be gone.' );

		$stored = $this->get_stored_post( $post_id );

		if ( null === $status ) {
			$this->assertNull( $stored, 'The content should be deleted permanently.' );
		} elseif ( 'trash' === $status ) {
			$this->assertSame( 'trash', $stored['status'] ?? null, 'The content should go to the trash.' );
		} else {
			$this->assertSame(
				array(
					'author' => $user_id,
					'status' => $status,
				),
				$stored,
				'The content should stay as it is, with its author.'
			);
		}
	}

	/**
	 * Returns kinds of content, and what becomes of each when its author is deleted.
	 *
	 * @return array<string, array{post_type: string, post_status: string, status: string|null}>
	 *         The post type, the post status, and the status afterwards.
	 */
	public static function data_content_deleted_with_its_author(): array {
		return array(
			'page'              => array( 'page', 'publish', 'trash' ),
			'post in the trash' => array( 'post', 'trash', null ),
			'attachment'        => array( 'attachment', 'inherit', null ),
			'pattern'           => array( 'wp_block', 'publish', 'publish' ),
		);
	}

	/**
	 * The posts and links of a deleted user go to the user given in `reassign`, given as
	 * a number or as text; on multisite, the posts of the current site.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_reassign_ids
	 *
	 * @param string $heir    The user to reassign to, as a fixture name, or `first` for user 1.
	 * @param bool   $as_text Whether to send the ID as text.
	 */
	public function test_reassigns_posts_and_links( string $heir, bool $as_text ): void {
		$heir_id = 'first' === $heir ? 1 : self::$user_ids[ $heir ];
		$target  = $this->create_target_with_content( 'uwtreassign' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assertSame( $this->get_success_output(), $this->run_ability( $this->delete_input( $target['user'], $as_text ? (string) $heir_id : $heir_id ) ), 'The user should be deleted.' );
		$this->assert_user_gone( $target['user'], 'Deleting with reassignment' );
		$this->assertSame(
			array(
				'author' => $heir_id,
				'status' => 'publish',
			),
			$this->get_stored_post( $target['post'] ),
			'The post should belong to the user it was reassigned to.'
		);
		$this->assertSame( $heir_id, $this->get_stored_link_owner( $target['link'] ), 'The link should belong to the user it was reassigned to.' );
	}

	/**
	 * Returns the users to reassign to.
	 *
	 * @return array<string, array{heir: string, as_text: bool}> The user, and whether to
	 *                                                           send the ID as text.
	 */
	public static function data_reassign_ids(): array {
		return array(
			'editor'               => array( 'editor', false ),
			'editor, ID as text'   => array( 'editor', true ),
			'user one'             => array( 'first', false ),
			'user one, ID as text' => array( 'first', true ),
		);
	}

	/**
	 * Every value that names no usable user to reassign to gives the same error, and
	 * nothing changes.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_unusable_reassign_values
	 *
	 * @param mixed $reassign        The reassign value; `missing`, `target` and `outsider`
	 *                               stand for an ID of no user, the target and a user of
	 *                               another site, and null sends none.
	 * @param bool  $skip_validation Whether to skip schema validation.
	 */
	public function test_refuses_an_unusable_reassign_value( $reassign, bool $skip_validation ): void {
		$target = $this->create_target_with_content( 'uwtbadreassign' );

		$placeholders = array(
			'missing'  => self::MISSING_ID,
			'target'   => $target['user'],
			'outsider' => self::$user_ids['outsider'] ?? 0,
		);
		if ( is_string( $reassign ) && isset( $placeholders[ $reassign ] ) ) {
			$reassign = $placeholders[ $reassign ];
		}

		$input = $this->delete_input( $target['user'], $reassign );
		if ( null === $reassign && $skip_validation ) {
			$input['reassign'] = null;
		}
		if ( $skip_validation ) {
			$this->skip_input_validation();
		}

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability( $input );

		$message = self::ERRORS['user_invalid_reassign'][1];
		$this->assert_value_error( $result, 'user_invalid_reassign', 'reassign', 'Deleting with an unusable reassign value', $message );
		$this->assertSame(
			array(
				'status' => 400,
				'params' => array( 'reassign' => $message ),
			),
			$result->get_error_data(),
			'The error data should be the same for every unusable value.'
		);
		$this->assert_unchanged( $before, 'Deleting with an unusable reassign value' );
	}

	/**
	 * Returns reassign values that name no usable user.
	 *
	 * @return array<string, array{reassign: mixed, skip_validation: bool}> The value, and
	 *                                                                      whether to skip validation.
	 */
	public static function data_unusable_reassign_values(): array {
		$rows = array(
			'true'                         => array( true, false ),
			'true as text'                 => array( 'true', false ),
			'ID of no user'                => array( 'missing', false ),
			'the user being deleted'       => array( 'target', false ),
			'null, validation skipped'     => array( null, true ),
			'zero, validation skipped'     => array( 0, true ),
			'negative, validation skipped' => array( -1, true ),
		);

		if ( is_multisite() ) {
			$rows['user of another site'] = array( 'outsider', false );
		}

		return $rows;
	}

	/**
	 * With validation skipped, input without `reassign` gives the reassign error.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_refuses_a_missing_reassign_when_validation_is_skipped(): void {
		$user_id = $this->create_target( 'uwtnoreassignkey' );

		$this->skip_input_validation();
		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_value_error( $this->run_ability( $this->delete_input( $user_id, null ) ), 'user_invalid_reassign', 'reassign', 'Deleting without reassign', self::ERRORS['user_invalid_reassign'][1] );
		$this->assert_unchanged( $before, 'Deleting without reassign' );
	}

	/**
	 * Input the schema refuses changes nothing.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_inputs_the_schema_refuses
	 *
	 * @param array<string, mixed> $input The input, where the ID `target` stands for the user to delete.
	 */
	public function test_refuses_input_the_schema_refuses( array $input ): void {
		$user_id = $this->create_target( 'uwtschema' );
		if ( isset( $input['where']['id'] ) && 'target' === $input['where']['id'] ) {
			$input['where']['id'] = $user_id;
		}

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_invalid_input( $this->run_ability( $input ), 'Deleting with input the schema refuses' );
		$this->assert_unchanged( $before, 'Deleting with input the schema refuses' );
	}

	/**
	 * Returns inputs the schema refuses; the ID `target` stands for the user to delete.
	 *
	 * @return array<string, array{input: array<string, mixed>}> The inputs.
	 */
	public static function data_inputs_the_schema_refuses(): array {
		$where = array( 'id' => 'target' );
		$rows  = array(
			'no where'    => array( array( 'reassign' => false ) ),
			'no reassign' => array( array( 'where' => $where ) ),
			'ID of zero'  => array(
				array(
					'where'    => array( 'id' => 0 ),
					'reassign' => false,
				),
			),
			'unknown key' => array(
				array(
					'where'    => $where,
					'reassign' => false,
					'force'    => true,
				),
			),
		);

		$values = array(
			'zero'         => 0,
			'zero as text' => '0',
			'empty text'   => '',
			'negative'     => -1,
			'fraction'     => 7.5,
			'text'         => 'abc',
			'null'         => null,
			'list'         => array( 1 ),
		);
		foreach ( $values as $label => $value ) {
			$rows[ "reassign {$label}" ] = array(
				array(
					'where'    => $where,
					'reassign' => $value,
				),
			);
		}

		return $rows;
	}

	/**
	 * A caller without the capability to delete users is refused, and nothing changes.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_callers_without_the_capability
	 *
	 * @param string $caller The caller, as a fixture name, or an empty string to log out.
	 */
	public function test_refuses_callers_without_the_capability( string $caller ): void {
		$user_id = $this->create_target( 'uwtgate' );

		$this->login_as( $caller );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_refused( $this->run_ability( $this->delete_input( $user_id, false ) ), 'Deleting without the capability' );
		$this->assert_unchanged( $before, 'Deleting without the capability' );
	}

	/**
	 * Returns callers who may not delete users.
	 *
	 * @return array<string, array{caller: string}> The caller.
	 */
	public static function data_callers_without_the_capability(): array {
		return array(
			'editor'      => array( 'editor' ),
			'author'      => array( 'author' ),
			'contributor' => array( 'contributor' ),
			'subscriber'  => array( 'subscriber' ),
			'promoter'    => array( 'promoter' ),
			'logged out'  => array( '' ),
		);
	}

	/**
	 * The callback checks the capability again, with a status that tells a logged-out
	 * caller apart.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_callers_behind_the_gate
	 *
	 * @param string $caller The caller, as a fixture name, or an empty string to log out.
	 */
	public function test_checks_the_capability_again( string $caller ): void {
		$user_id   = $this->create_target( 'uwtgateagain' );
		$callbacks = $this->get_ability_callbacks();
		$this->login_as( $caller );

		$before = $this->snapshot();

		$this->assert_core_error( $callbacks['execute_callback']( $this->delete_input( $user_id, false ) ), 'user_cannot_delete', 'Deleting behind the gate' );
		$this->assert_unchanged( $before, 'Deleting behind the gate' );
	}

	/**
	 * Returns callers who reach the callback without the capability.
	 *
	 * @return array<string, array{caller: string}> The caller.
	 */
	public static function data_callers_behind_the_gate(): array {
		return array(
			'subscriber' => array( 'subscriber' ),
			'logged out' => array( '' ),
		);
	}

	/**
	 * The callback checks the capability to delete users apart from the capability on
	 * the target: a caller granted only the capability on the target is refused.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_checks_the_capability_apart_from_the_one_on_the_user(): void {
		$target    = $this->create_target_with_content( 'uwtgrantedtarget' );
		$user_id   = $target['user'];
		$callbacks = $this->get_ability_callbacks();

		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap, int $caller_id, array $args ) use ( $user_id ): array {
				if ( ( is_multisite() ? 'remove_user' : 'delete_user' ) === $cap && (int) ( $args[0] ?? 0 ) === $user_id ) {
					return array( 'exist' );
				}

				return $caps;
			},
			10,
			4
		);

		$this->login_as( 'subscriber' );

		$this->assertTrue( current_user_can( is_multisite() ? 'remove_user' : 'delete_user', $user_id ), 'The subscriber should hold the capability on the target.' );
		$this->assertFalse( current_user_can( is_multisite() ? 'remove_users' : 'delete_users' ), 'The subscriber should not hold the capability to delete users.' );

		$before = $this->snapshot();

		$this->assert_core_error( $callbacks['execute_callback']( $this->delete_input( $user_id, false ) ), 'user_cannot_delete', 'Deleting with the capability on the target only' );
		$this->assert_unchanged( $before, 'Deleting with the capability on the target only' );
		$this->assertTrue( is_multisite() ? is_user_member_of_blog( $user_id, get_current_blog_id() ) : $this->user_row_exists( $user_id ), 'The target should stay.' );
		$this->assertSame( 'publish', $this->get_stored_post( $target['post'] )['status'] ?? null, 'The post should not be trashed.' );
	}

	/**
	 * An ID that no user has is the not-found error.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @dataProvider data_ids_of_no_user
	 *
	 * @param mixed $id              The ID.
	 * @param bool  $skip_validation Whether to skip schema validation.
	 */
	public function test_reports_a_missing_user( $id, bool $skip_validation ): void {
		if ( $skip_validation ) {
			$this->skip_input_validation();
		}

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_core_error( $this->run_ability( $this->delete_input( $id, false ) ), 'user_not_found', 'Deleting a user that does not exist' );
		$this->assert_unchanged( $before, 'Deleting a user that does not exist' );
	}

	/**
	 * Returns IDs that name no user.
	 *
	 * @return array<string, array{id: mixed, skip_validation: bool}> The ID, and whether
	 *                                                                 to skip validation.
	 */
	public static function data_ids_of_no_user(): array {
		return array(
			'missing ID'                   => array( self::MISSING_ID, false ),
			'zero, validation skipped'     => array( 0, true ),
			'negative, validation skipped' => array( -1, true ),
		);
	}

	/**
	 * A capability filter that denies deleting the target is respected.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_respects_a_denied_capability_for_the_user(): void {
		$user_id = $this->create_target( 'uwtdenied' );

		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap, int $caller_id, array $args ) use ( $user_id ): array {
				if ( in_array( $cap, array( 'delete_user', 'remove_user' ), true ) && (int) ( $args[0] ?? 0 ) === $user_id ) {
					return array( 'do_not_allow' );
				}

				return $caps;
			},
			10,
			4
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_core_error( $this->run_ability( $this->delete_input( $user_id, false ) ), 'user_cannot_delete', 'Deleting a user the capability filter protects' );
		$this->assert_unchanged( $before, 'Deleting a user the capability filter protects' );
	}

	/**
	 * On a single site the last administrator may delete themself.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-excluded
	 */
	public function test_last_administrator_deletes_themself(): void {
		( new WP_User( 1 ) )->set_role( 'editor' );
		( new WP_User( self::$user_ids['administrator'] ) )->set_role( 'editor' );

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assertSame( array( 'outcome' => 'deleted' ), $this->run_ability( $this->delete_input( self::$user_ids['manager'], false ) ), 'The administrator should delete themself.' );
		$this->assertFalse( $this->user_row_exists( self::$user_ids['manager'] ), 'The administrator should be deleted.' );
	}

	/**
	 * On multisite only a super admin may remove themself from the site.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_callers_removing_themselves
	 *
	 * @param string $caller  The caller, as a fixture name.
	 * @param bool   $allowed Whether the caller may remove themself.
	 */
	public function test_removing_yourself_on_multisite( string $caller, bool $allowed ): void {
		$user_id = self::$user_ids[ $caller ];

		$this->login_as( $caller );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability( $this->delete_input( $user_id, false ) );

		if ( $allowed ) {
			$this->assertSame( array( 'outcome' => 'removed' ), $result, 'The super admin should remove themself.' );
			$this->assert_user_gone( $user_id, 'A super admin removing themself' );
		} else {
			$this->assert_core_error( $result, 'user_cannot_delete', 'A site administrator removing themself' );
			$this->assert_unchanged( $before, 'A site administrator removing themself' );
		}
	}

	/**
	 * Returns callers who remove themselves.
	 *
	 * @return array<string, array{caller: string, allowed: bool}> The caller, and whether
	 *                                                             they may.
	 */
	public static function data_callers_removing_themselves(): array {
		return array(
			'site administrator' => array( 'administrator', false ),
			'super admin'        => array( 'manager', true ),
		);
	}

	/**
	 * On multisite a site administrator removes a super admin from the site only: the
	 * account, the super admin status and the other memberships stay.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 */
	public function test_site_administrator_removes_a_super_admin(): void {
		$user_id = $this->create_target( 'uwtsuper', array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		add_user_to_blog( self::$site_id, $user_id, 'editor' );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assertSame( array( 'outcome' => 'removed' ), $this->run_ability( $this->delete_input( $user_id, false ) ), 'The super admin should be removed from the site.' );
		$this->assert_user_gone( $user_id, 'Removing a super admin' );
		$this->assertTrue( is_super_admin( $user_id ), 'The user should stay a super admin.' );
		$this->assertTrue( is_user_member_of_blog( $user_id, self::$site_id ), 'The user should stay a member of the other site.' );

		revoke_super_admin( $user_id );
	}

	/**
	 * On multisite a user who is not a member of the current site is not found, for
	 * every caller.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_callers_and_users_of_another_site
	 *
	 * @param string $caller The caller, as a fixture name.
	 * @param string $target The target, as a fixture name.
	 */
	public function test_reports_a_user_of_another_site( string $caller, string $target ): void {
		$this->login_as( $caller );
		$this->register_abilities();

		$before = $this->snapshot();

		$this->assert_core_error( $this->run_ability( $this->delete_input( self::$user_ids[ $target ], false ) ), 'user_not_found', 'Deleting a user of another site' );
		$this->assert_unchanged( $before, 'Deleting a user of another site' );
	}

	/**
	 * Returns callers and users who are not members of the current site.
	 *
	 * @return array<string, array{caller: string, target: string}> The caller and the target.
	 */
	public static function data_callers_and_users_of_another_site(): array {
		return array(
			'super admin'                  => array( 'manager', 'outsider' ),
			'super admin removing oneself' => array( 'outside_super_admin', 'outside_super_admin' ),
			'site administrator'           => array( 'administrator', 'outsider' ),
		);
	}

	/**
	 * A user that is still stored after the deletion is the delete error.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-excluded
	 */
	public function test_reports_a_user_row_that_was_not_deleted(): void {
		global $wpdb;

		$user_id = $this->create_target( 'uwtstuck' );
		$users   = $wpdb->users;

		add_filter(
			'query',
			static function ( string $query ) use ( $users, $user_id ): string {
				if ( preg_match( '/^DELETE FROM `?' . preg_quote( $users, '/' ) . '`? WHERE `?ID`? = \'?' . $user_id . '\'?$/', trim( $query ) ) ) {
					return 'SELECT 1';
				}

				return $query;
			}
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assert_core_error( $this->run_ability( $this->delete_input( $user_id, false ) ), 'user_delete_failed', 'Deleting a user whose row stays' );
		$this->assertTrue( $this->user_row_exists( $user_id ), 'The user should still exist.' );
	}

	/**
	 * On multisite a user who is still a member after the removal is the delete error.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 *
	 * @group ms-required
	 */
	public function test_reports_a_membership_that_was_not_removed(): void {
		global $wpdb;

		$user_id = $this->create_target( 'uwtstuckmember' );
		$meta_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT umeta_id FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $wpdb->get_blog_prefix() . 'capabilities' ) );
		$blocked = "DELETE FROM {$wpdb->usermeta} WHERE umeta_id IN( {$meta_id} )";

		$this->assertGreaterThan( 0, $meta_id, 'The user should have capabilities on the site.' );

		add_filter(
			'query',
			static function ( string $query ) use ( $blocked ): string {
				return trim( $query ) === $blocked ? 'SELECT 1' : $query;
			}
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assert_core_error( $this->run_ability( $this->delete_input( $user_id, false ) ), 'user_delete_failed', 'Removing a user whose membership stays' );
		$this->assertTrue( is_user_member_of_blog( $user_id, get_current_blog_id() ), 'The user should still be a member.' );
	}

	/**
	 * Core's user hook fires once, as the caller, and the hook of the other ways of
	 * deleting users does not.
	 *
	 * @ticket 66720
	 * @since 7.3.0
	 */
	public function test_fires_core_hooks_only(): void {
		$user_id = $this->create_target( 'uwthooks' );

		$other = 0;
		add_action(
			'rest_delete_user',
			static function () use ( &$other ) {
				++$other;
			}
		);

		$current_users = array();
		add_action(
			is_multisite() ? 'remove_user_from_blog' : 'delete_user',
			static function () use ( &$current_users ) {
				$current_users[] = get_current_user_id();
			}
		);

		$this->login_as( 'manager' );
		$this->register_abilities();

		$this->assertSame( $this->get_success_output(), $this->run_ability( $this->delete_input( $user_id, false ) ), 'The user should be deleted.' );
		$this->assertSame( array( self::$user_ids['manager'] ), $current_users, 'The core action should fire once, as the caller.' );
		$this->assertSame( 0, $other, 'The hook of the other ways of deleting users should not fire.' );
	}
}
