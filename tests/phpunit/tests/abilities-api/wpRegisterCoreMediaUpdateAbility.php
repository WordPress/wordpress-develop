<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-media-ability.php';

/**
 * Tests for the core/media-update ability.
 *
 * @covers WP_Abilities_Media
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreMediaUpdateAbility extends WP_Abilities_Media_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/media-update';

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
	 * Returns an update input for a shared attachment.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $attachment The attachment, as a fixture name.
	 * @param array<string, mixed> $data       The attributes.
	 * @param string[]|null        $fields     Optional. The fields. Default null.
	 * @return array<string, mixed> The input.
	 */
	private function update_input( string $attachment, array $data, ?array $fields = null ): array {
		$input = array(
			'where' => array( 'id' => self::$post_ids[ $attachment ] ?? self::MISSING_ID ),
			'data'  => $data,
		);

		if ( null !== $fields ) {
			$input['fields'] = $fields;
		}

		return $input;
	}

	/**
	 * Asserts that a call succeeded and returned the item of the attachment.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result     The ability result.
	 * @param string $attachment The attachment, as a fixture name.
	 * @param string $context    What the call was, for the assertion messages.
	 */
	private function assert_updated( $result, string $attachment, string $context ): void {
		$this->assertNotWPError( $result, "{$context}: the update should succeed." );
		$this->assertIsArray( $result, "{$context}: the update should return the item." );
		$this->assertSame( self::$post_ids[ $attachment ], $result['id'], "{$context}: the item should be the attachment." );
	}

	/**
	 * Asserts that the row and meta of an attachment are the same as in a snapshot.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $before  The snapshot before the call.
	 * @param string               $context What the call was, for the assertion messages.
	 */
	private function assert_unchanged( array $before, string $context ): void {
		$after = $this->snapshot( (int) $before['post']['ID'] );

		$this->assertSame( $before['post'], $after['post'], "{$context}: the attachment row should not change." );
		$this->assertSame( $before['post_meta'], $after['post_meta'], "{$context}: the attachment meta should not change." );
		$this->assertSame( $before['posts'], $after['posts'], "{$context}: no post should be added or removed." );
	}

	/**
	 * Sets a column of a shared attachment directly.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $attachment The attachment, as a fixture name.
	 * @param array<string, mixed> $columns    The columns and their values.
	 */
	private function set_columns( string $attachment, array $columns ): void {
		global $wpdb;

		$wpdb->update( $wpdb->posts, $columns, array( 'ID' => self::$post_ids[ $attachment ] ) );
		clean_post_cache( self::$post_ids[ $attachment ] );
	}

	/**
	 * The ability is registered with its label, category, meta and annotations.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_registers_the_update_ability(): void {
		$this->assert_registration( 'Update Media', false, false, true );
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

		$expected_input = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where', 'data' ),
			'properties'           => array(
				'where'  => self::where_id_schema(),
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'minProperties'        => 1,
					'properties'           => self::attribute_schemas( 0 ),
				),
				'fields' => self::fields_schema(),
			),
		);

		$this->assertSame( $this->normalize_schema( $expected_input ), $this->normalize_schema( $ability->get_input_schema() ), 'The input schema should match.' );
		$this->assertSame( $this->normalize_schema( self::item_schema() ), $this->normalize_schema( $ability->get_output_schema() ), 'The output schema should match.' );
	}

	/**
	 * Returns callers with whether the gate lets them update an attachment of the author, or
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
	 * The permission callback needs `edit_post` on the target.
	 *
	 * @ticket 66719
	 * @dataProvider data_gate_roles
	 * @since 7.3.0
	 *
	 * @param string $role     The role.
	 * @param bool   $expected Whether the gate passes.
	 * @param bool   $own      Optional. Whether the target is the caller's own attachment. Default false.
	 */
	public function test_permission_callback_needs_edit_post( string $role, bool $expected, bool $own = false ): void {
		$id = $own ? $this->create_attachment_of( $role ) : self::$post_ids['unattached'];

		$this->login_as( $role );
		$this->register_abilities();

		$input = array(
			'where' => array( 'id' => $id ),
			'data'  => array( 'title' => 'MDT' ),
		);

		$this->assertSame( $expected, wp_get_ability( self::ABILITY )->check_permissions( $input ), 'The gate should follow edit_post on the target.' );
	}

	/**
	 * The permission callback refuses an administrator when the attachment type has
	 * `show_in_rest` off, and the call is refused with the generic error.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_when_attachments_are_hidden(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		get_post_type_object( 'attachment' )->show_in_rest = false;

		$input = $this->update_input( 'unattached', array( 'title' => 'MDT hidden' ) );
		$this->assertFalse( wp_get_ability( self::ABILITY )->check_permissions( $input ), 'The administrator should be refused.' );
		$this->assert_refused( $this->run_ability( $input ), 'An update while attachments are hidden' );
	}

	/**
	 * A refused caller gets the generic error and nothing changes.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_refused_caller_gets_the_generic_error(): void {
		$this->login_as( 'contributor' );
		$this->register_abilities();

		$before = $this->snapshot( self::$post_ids['unattached'] );
		$this->assert_refused( $this->run_ability( $this->update_input( 'unattached', array( 'title' => 'MDT refused' ) ) ), 'An update by a contributor' );
		$this->assert_unchanged( $before, 'An update by a contributor' );
	}

	/**
	 * The permission callback passes when the ID names no attachment, so that the call
	 * answers that nothing was found.
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

		$input = $this->update_input( $post, array( 'title' => 'MDT not found' ) );

		$this->assertTrue( wp_get_ability( self::ABILITY )->check_permissions( $input ), 'The gate should pass.' );
		$this->assert_not_found( $this->run_ability( $input ), 'An update of something that is not an attachment' );
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
	 * The permission callback returns false for the ID of an attachment the caller may edit
	 * followed by letters.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_an_id_followed_by_letters(): void {
		$this->login_as( 'administrator' );
		$callbacks = $this->get_ability_callbacks();

		$input = array(
			'where' => array( 'id' => self::$post_ids['unattached'] . 'abc' ),
			'data'  => array( 'title' => 'MDT letters' ),
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
			'where' => array( 'id' => $this->id_in_form( self::$post_ids['unattached'], $form ) ),
			'data'  => array( 'title' => 'MDT form' ),
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
					'where' => $where,
					'data'  => array( 'title' => 'MDT unusable' ),
				)
			),
			'An update with an unusable ID'
		);
	}

	/**
	 * Returns update inputs without a usable ID.
	 *
	 * @return array<string, array{input: array<string, mixed>}> The inputs.
	 */
	public static function data_updates_without_a_usable_id(): array {
		return array(
			'ID of zero' => array(
				array(
					'where' => array( 'id' => 0 ),
					'data'  => array( 'title' => 'MDT global post' ),
				),
			),
			'no where'   => array(
				array( 'data' => array( 'title' => 'MDT global post' ) ),
			),
		);
	}

	/**
	 * Without a usable ID the update is not found, also when the global post is an attachment
	 * the caller may edit, and that attachment does not change.
	 *
	 * @ticket 66719
	 * @dataProvider data_updates_without_a_usable_id
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $input The input.
	 */
	public function test_lookup_never_uses_the_global_post( array $input ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();
		$this->skip_input_validation();
		$this->force_permission();

		$GLOBALS['post'] = get_post( self::$post_ids['unattached'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The lookup should not fall back to it.

		$before = $this->snapshot( self::$post_ids['unattached'] );

		$this->assert_not_found( $this->run_ability( $input ), 'An update without a usable ID' );
		$this->assert_unchanged( $before, 'An update without a usable ID' );
	}

	/**
	 * Returns the callers the gate refuses, with the status they get.
	 *
	 * @return array<string, array{role: string, status: int, own: bool}> The cases.
	 */
	public static function data_callers_without_edit_post(): array {
		$cases = array();

		foreach ( self::data_gate_roles() as $name => $row ) {
			if ( ! $row[1] ) {
				$cases[ $name ] = array( $row[0], 'logged_out' === $row[0] ? 401 : 403, $row[2] ?? false );
			}
		}

		return $cases;
	}

	/**
	 * Without `edit_post` the callback refuses the call before any attribute is checked, and
	 * nothing changes.
	 *
	 * @ticket 66719
	 * @dataProvider data_callers_without_edit_post
	 * @since 7.3.0
	 *
	 * @param string $role   The role.
	 * @param int    $status The expected status.
	 * @param bool   $own    Whether the target is the caller's own attachment.
	 */
	public function test_callback_refuses_without_edit_post( string $role, int $status, bool $own ): void {
		$id = $own ? $this->create_attachment_of( $role ) : self::$post_ids['unattached'];

		$this->login_as( $role );
		$this->register_abilities();
		$this->force_permission();
		$this->skip_input_validation();

		$before = $this->snapshot( $id );
		$result = $this->run_ability(
			array(
				'where' => array( 'id' => $id ),
				'data'  => array(
					'author' => 0,
					'title'  => 'MDT cannot edit',
				),
			)
		);

		$this->assert_error( $result, 'media_cannot_edit', $status, 'An update without edit_post' );
		$this->assert_error_message( $result, 'Sorry, you are not allowed to edit this post.', 'An update without edit_post' );
		$this->assert_unchanged( $before, 'An update without edit_post' );
	}

	/**
	 * The lookup comes before everything else.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_lookup_comes_first(): void {
		$this->login_as( 'contributor' );
		$this->register_abilities();
		$this->force_permission();
		$this->skip_input_validation();

		$this->assert_not_found( $this->run_ability( $this->update_input( 'missing', array( 'author' => 0 ) ) ), 'An update of a missing attachment with an invalid author' );
	}

	/**
	 * Data without any attribute is an invalid attribute.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_data_without_attributes(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->skip_input_validation();

		$before = $this->snapshot( self::$post_ids['unattached'] );

		$this->assert_error_with_params( $this->run_ability( $this->update_input( 'unattached', array() ) ), 'media_invalid_attribute', 'data', 'Empty data' );
		$this->assert_error_with_params( $this->run_ability( $this->update_input( 'unattached', array( 'status' => 'private' ) ) ), 'media_invalid_attribute', 'data', 'Data with only a key outside the attributes' );
		$this->assert_unchanged( $before, 'Data without attributes' );
	}

	/**
	 * The attributes are checked in the order author, post, date, slug.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_attribute_checks_run_in_order(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->skip_input_validation();

		$data = array(
			'author' => 0,
			'post'   => -1,
			'date'   => array(),
			'slug'   => '!!!',
		);

		$this->assert_error_with_params( $this->run_ability( $this->update_input( 'unattached', $data ) ), 'media_invalid_attribute', 'data[author]', 'Every attribute invalid' );

		unset( $data['author'] );
		$this->assert_error_with_params( $this->run_ability( $this->update_input( 'unattached', $data ) ), 'media_invalid_attribute', 'data[post]', 'Post, date and slug invalid' );

		unset( $data['post'] );
		$this->assert_error_with_params( $this->run_ability( $this->update_input( 'unattached', $data ) ), 'media_invalid_attribute', 'data[date]', 'Date and slug invalid' );

		unset( $data['date'] );
		$this->assert_error_with_params( $this->run_ability( $this->update_input( 'unattached', $data ) ), 'media_invalid_attribute', 'data[slug]', 'Slug invalid' );
	}

	/**
	 * The caller's own ID is allowed as the author.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_own_author_id_is_allowed(): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$this->assert_updated( $this->run_ability( $this->update_input( 'unattached', array( 'author' => self::$user_ids['author'] ) ) ), 'unattached', 'An update with the own author' );
	}

	/**
	 * The stored author sent again is not checked, also when that user no longer exists.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_stored_author_sent_again_is_not_checked(): void {
		$this->set_columns( 'editor_image', array( 'post_author' => self::MISSING_ID ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated(
			$this->run_ability(
				$this->update_input(
					'editor_image',
					array(
						'author' => self::MISSING_ID,
						'title'  => 'MDT stored author',
					)
				)
			),
			'editor_image',
			'An update with the stored author'
		);
		$this->assertSame( self::MISSING_ID, (int) get_post_field( 'post_author', self::$post_ids['editor_image'] ), 'The author should stay.' );
	}

	/**
	 * Returns authors the author may not set.
	 *
	 * @return array<string, array{author: string}> The authors, as fixture names.
	 */
	public static function data_other_authors(): array {
		return array(
			'another user' => array( 'editor' ),
			'missing user' => array( 'missing' ),
		);
	}

	/**
	 * Another author needs `edit_others_posts`, checked before the user is looked up.
	 *
	 * @ticket 66719
	 * @dataProvider data_other_authors
	 * @since 7.3.0
	 *
	 * @param string $author The author, as a fixture name.
	 */
	public function test_other_author_needs_edit_others_posts( string $author ): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$before = $this->snapshot( self::$post_ids['unattached'] );
		$result = $this->run_ability( $this->update_input( 'unattached', array( 'author' => self::$user_ids[ $author ] ?? self::MISSING_ID ) ) );

		$this->assert_error( $result, 'media_cannot_edit_others', 403, 'An update to another author' );
		$this->assert_error_message( $result, 'Sorry, you are not allowed to update posts as this user.', 'An update to another author' );
		$this->assert_unchanged( $before, 'An update to another author' );
	}

	/**
	 * An author that names no user is an invalid author for a caller who may set authors;
	 * another existing user is stored.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_author_for_a_caller_who_may_set_authors(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( 'editor_image', array( 'author' => self::MISSING_ID ) ) );
		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[author]', 'A missing author' );
		$this->assert_error_message( $result, 'Invalid author ID.', 'A missing author' );

		$this->assert_updated( $this->run_ability( $this->update_input( 'editor_image', array( 'author' => self::$user_ids['author'] ) ) ), 'editor_image', 'An update to another author' );
		$this->assertSame( self::$user_ids['author'], (int) get_post_field( 'post_author', self::$post_ids['editor_image'] ), 'The new author should be stored.' );
	}

	/**
	 * A parent of 0 detaches the attachment without any capability on the old parent.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_parent_of_zero_detaches(): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( 'attached', array( 'post' => 0 ), array( 'post' ) ) );

		$this->assert_updated( $result, 'attached', 'A detach' );
		$this->assertSame( 0, $result['post'], 'The output should show the attachment detached.' );
		$this->assertSame( 0, get_post( self::$post_ids['attached'] )->post_parent, 'The attachment should be detached.' );
	}

	/**
	 * The current parent sent again needs no capability, also when that post no longer exists.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_current_parent_sent_again_needs_no_capability(): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$this->assert_updated(
			$this->run_ability(
				$this->update_input(
					'attached',
					array(
						'post'  => self::$post_ids['post'],
						'title' => 'MDT same parent',
					)
				)
			),
			'attached',
			'An update with the current parent'
		);

		$this->set_columns( 'unattached', array( 'post_parent' => self::MISSING_ID ) );

		$this->assert_updated(
			$this->run_ability(
				$this->update_input(
					'unattached',
					array(
						'post'  => self::MISSING_ID,
						'title' => 'MDT missing parent',
					)
				)
			),
			'unattached',
			'An update with a current parent that no longer exists'
		);
		$this->assertSame( self::MISSING_ID, get_post( self::$post_ids['unattached'] )->post_parent, 'The parent should stay.' );
	}

	/**
	 * Returns parents the author may not attach to.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_parents_the_author_may_not_use(): array {
		return array(
			'page of another user' => array( 'page' ),
			'missing post'         => array( 'missing' ),
		);
	}

	/**
	 * A new parent needs `edit_post` on it, checked before it is looked up.
	 *
	 * @ticket 66719
	 * @dataProvider data_parents_the_author_may_not_use
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_new_parent_needs_edit_post( string $post ): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$before = $this->snapshot( self::$post_ids['unattached'] );
		$result = $this->run_ability( $this->update_input( 'unattached', array( 'post' => self::$post_ids[ $post ] ?? self::MISSING_ID ) ) );

		$this->assert_error( $result, 'media_cannot_attach', 403, 'An update to a forbidden parent' );
		$this->assert_error_message( $result, 'Sorry, you are not allowed to attach media to this post.', 'An update to a forbidden parent' );
		$this->assert_unchanged( $before, 'An update to a forbidden parent' );
	}

	/**
	 * Returns parents of a type that cannot hold attachments.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_parents_of_an_invalid_type(): array {
		return array(
			'revision'   => array( 'revision' ),
			'attachment' => array( 'editor_image' ),
		);
	}

	/**
	 * A revision or an attachment cannot be the parent, also when it is the current parent.
	 *
	 * @ticket 66719
	 * @dataProvider data_parents_of_an_invalid_type
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_parent_of_an_invalid_type( string $post ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$data   = array( 'post' => self::$post_ids[ $post ] );
		$result = $this->run_ability( $this->update_input( 'unattached', $data ) );

		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[post]', 'A new parent of an invalid type' );
		$this->assert_error_message( $result, 'Invalid parent type.', 'A new parent of an invalid type' );

		$this->set_columns( 'unattached', array( 'post_parent' => self::$post_ids[ $post ] ) );

		$this->assert_error_with_params( $this->run_ability( $this->update_input( 'unattached', $data ) ), 'media_invalid_attribute', 'data[post]', 'A current parent of an invalid type' );
	}

	/**
	 * Returns authors below 1.
	 *
	 * @return array<string, array{author: int}> The authors.
	 */
	public static function data_authors_below_one(): array {
		return array(
			'zero'     => array( 0 ),
			'negative' => array( -1 ),
		);
	}

	/**
	 * An author below 1 is invalid before the capability to set another author is checked.
	 *
	 * @ticket 66719
	 * @dataProvider data_authors_below_one
	 * @since 7.3.0
	 *
	 * @param int $author The author.
	 */
	public function test_author_below_one_is_invalid_before_the_capability_check( int $author ): void {
		$this->login_as( 'author' );
		$this->register_abilities();
		$this->skip_input_validation();

		$before = $this->snapshot( self::$post_ids['unattached'] );
		$result = $this->run_ability( $this->update_input( 'unattached', array( 'author' => $author ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[author]', 'An author below 1' );
		$this->assert_unchanged( $before, 'An author below 1' );
	}

	/**
	 * Returns posts of another user of a type that cannot hold attachments.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_other_users_parents_of_an_invalid_type(): array {
		return array(
			'revision of a post of another user' => array( 'revision' ),
			'attachment of another user'         => array( 'editor_image' ),
		);
	}

	/**
	 * A new parent of an invalid type that the caller may not edit is refused as a missing
	 * post is, before its type is checked.
	 *
	 * @ticket 66719
	 * @dataProvider data_other_users_parents_of_an_invalid_type
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_parent_capability_is_checked_before_the_type( string $post ): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$before  = $this->snapshot( self::$post_ids['unattached'] );
		$missing = $this->run_ability( $this->update_input( 'unattached', array( 'post' => self::MISSING_ID ) ) );
		$result  = $this->run_ability( $this->update_input( 'unattached', array( 'post' => self::$post_ids[ $post ] ) ) );

		$this->assert_error( $missing, 'media_cannot_attach', 403, 'An update to a missing post' );
		$this->assert_error( $result, 'media_cannot_attach', 403, 'An update to a forbidden parent of an invalid type' );
		$this->assert_error_message( $result, 'Sorry, you are not allowed to attach media to this post.', 'An update to a forbidden parent of an invalid type' );
		$this->assertSame( $missing->get_error_message(), $result->get_error_message(), 'The message should be the one of a missing post.' );
		$this->assertSame( $missing->get_error_data(), $result->get_error_data(), 'The error data should be the one of a missing post.' );
		$this->assert_unchanged( $before, 'An update to a forbidden parent of an invalid type' );
	}

	/**
	 * A post the caller may edit becomes the parent.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_new_parent_is_stored(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated( $this->run_ability( $this->update_input( 'unattached', array( 'post' => self::$post_ids['author_post'] ) ) ), 'unattached', 'An update to a new parent' );
		$this->assertSame( self::$post_ids['author_post'], get_post( self::$post_ids['unattached'] )->post_parent, 'The new parent should be stored.' );
	}

	/**
	 * Returns dates, with the stored local and GMT dates in New York in winter.
	 *
	 * @return array<string, array{date: array<string, string>, local: string, gmt: string}> The cases.
	 */
	public static function data_dates(): array {
		return array(
			'site date' => array( array( 'site' => '2099-01-15T10:00:00' ), '2099-01-15 10:00:00', '2099-01-15 15:00:00' ),
			'GMT date'  => array( array( 'gmt' => '2099-01-15T15:00:00' ), '2099-01-15 10:00:00', '2099-01-15 15:00:00' ),
			'both'      => array(
				array(
					'site' => '2099-01-15T10:00:00',
					'gmt'  => '2099-01-20T00:00:00',
				),
				'2099-01-15 10:00:00',
				'2099-01-15 15:00:00',
			),
		);
	}

	/**
	 * The site date is read in the site's time zone and the GMT date in UTC; the site date
	 * wins when both are sent.
	 *
	 * @ticket 66719
	 * @dataProvider data_dates
	 * @since 7.3.0
	 *
	 * @param array<string, string> $date  The date input.
	 * @param string                $local The expected stored local date.
	 * @param string                $gmt   The expected stored GMT date.
	 */
	public function test_date( array $date, string $local, string $gmt ): void {
		update_option( 'timezone_string', 'America/New_York' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated( $this->run_ability( $this->update_input( 'unattached', array( 'date' => $date ) ) ), 'unattached', 'An update with a date' );

		$this->assertSame( $local, get_post_field( 'post_date', self::$post_ids['unattached'] ), 'The local date should be stored.' );
		$this->assertSame( $gmt, get_post_field( 'post_date_gmt', self::$post_ids['unattached'] ), 'The GMT date should be stored.' );
	}

	/**
	 * Returns dates equal to a stored one.
	 *
	 * @return array<string, array{date: array<string, string>}> The cases.
	 */
	public static function data_dates_equal_to_the_stored_one(): array {
		return array(
			'site date equal to the stored site date' => array( array( 'site' => '2099-01-15T10:00:00' ) ),
			'GMT date equal to the stored GMT date'   => array( array( 'gmt' => '2099-01-20T00:00:00' ) ),
		);
	}

	/**
	 * A site date equal to the stored site date, or a GMT date alone equal to the stored GMT
	 * date, counts as not sent: the call succeeds and neither stored date changes.
	 *
	 * @ticket 66719
	 * @dataProvider data_dates_equal_to_the_stored_one
	 * @since 7.3.0
	 *
	 * @param array<string, string> $date The date input.
	 */
	public function test_date_equal_to_the_stored_one_counts_as_not_sent( array $date ): void {
		update_option( 'timezone_string', 'America/New_York' );

		/*
		 * The stored columns disagree, so a date that is applied would change the other
		 * column.
		 */
		$this->set_columns(
			'unattached',
			array(
				'post_date'     => '2099-01-15 10:00:00',
				'post_date_gmt' => '2099-01-20 00:00:00',
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated( $this->run_ability( $this->update_input( 'unattached', array( 'date' => $date ) ) ), 'unattached', 'An update with a date equal to the stored one' );

		$post = get_post( self::$post_ids['unattached'] );

		$this->assertSame( '2099-01-15 10:00:00', $post->post_date, 'The stored site date should stay.' );
		$this->assertSame( '2099-01-20 00:00:00', $post->post_date_gmt, 'The stored GMT date should stay.' );
	}

	/**
	 * A listener on the update sees the caller as the current user, and a callback on the
	 * attachment data runs during the update and stays registered.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_update_runs_as_the_caller(): void {
		$updated_by = array();
		$data_calls = 0;
		$listener   = static function () use ( &$updated_by ): void {
			$updated_by[] = get_current_user_id();
		};
		$callback   = static function ( array $data ) use ( &$data_calls ): array {
			++$data_calls;
			return $data;
		};
		add_action( 'attachment_updated', $listener );
		add_filter( 'wp_insert_attachment_data', $callback );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated( $this->run_ability( $this->update_input( 'editor_image', array( 'title' => 'MDT caller update' ) ) ), 'editor_image', 'An update by the editor' );

		$this->assertSame( array( self::$user_ids['editor'] ), array_unique( $updated_by ), 'The listener should see the caller as the current user.' );
		$this->assertGreaterThan( 0, $data_calls, 'The attachment data callback should run during the update.' );
		$this->assertNotFalse( has_filter( 'wp_insert_attachment_data', $callback ), 'The attachment data callback should stay registered.' );
	}

	/**
	 * A date Core cannot parse is an invalid date.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_unparsable_date_is_invalid(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->skip_input_validation();

		$this->assert_error_with_params(
			$this->run_ability( $this->update_input( 'unattached', array( 'date' => array( 'gmt' => 'not a date' ) ) ) ),
			'media_invalid_attribute',
			'data[date]',
			'An unparsable date'
		);
	}

	/**
	 * A slug with nothing left after sanitizing is invalid; a taken slug is made unique.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_slug(): void {
		self::factory()->post->create( array( 'post_name' => 'mdt-taken-update-slug' ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_error_with_params( $this->run_ability( $this->update_input( 'unattached', array( 'slug' => '!!!' ) ) ), 'media_invalid_attribute', 'data[slug]', 'A slug with nothing left' );

		$this->assert_updated( $this->run_ability( $this->update_input( 'unattached', array( 'slug' => 'MDT-Taken-Update-Slug' ) ) ), 'unattached', 'An update with a taken slug' );
		$slug = get_post_field( 'post_name', self::$post_ids['unattached'] );

		$this->assertNotSame( 'mdt-taken-update-slug', $slug, 'The taken slug should be made unique.' );
		$this->assertStringStartsWith( 'mdt-taken-update-slug', $slug, 'The unique slug should start with the sanitized slug.' );
	}

	/**
	 * Only the attributes that were sent change, and every update changes the modified date.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_only_sent_attributes_change(): void {
		$this->set_columns(
			'unattached',
			array(
				'post_modified'     => '2020-01-01 00:00:00',
				'post_modified_gmt' => '2020-01-01 00:00:00',
				'post_excerpt'      => 'MDT old caption',
				'post_content'      => 'MDT old description',
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( 'unattached', array( 'alt_text' => 'MDT new alt' ), array( 'alt_text', 'title.raw', 'caption.raw', 'description.raw' ) ) );
		$post   = get_post( self::$post_ids['unattached'] );

		$this->assert_updated( $result, 'unattached', 'An update of the alt text' );
		$this->assertSame( 'MDT new alt', $result['alt_text'], 'The alt text should be the new one.' );
		$this->assertSame( 'Fixture unattached', $result['title']['raw'], 'The title should not change.' );
		$this->assertSame( 'MDT old caption', $result['caption']['raw'], 'The caption should not change.' );
		$this->assertSame( 'MDT old description', $result['description']['raw'], 'The description should not change.' );
		$this->assertNotSame( '2020-01-01 00:00:00', $post->post_modified_gmt, 'The modified date should change.' );
	}

	/**
	 * A value equal to the stored one is no error.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_value_equal_to_the_stored_one(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$post = get_post( self::$post_ids['unattached'] );

		$this->assert_updated(
			$this->run_ability(
				$this->update_input(
					'unattached',
					array(
						'title'  => $post->post_title,
						'slug'   => $post->post_name,
						'author' => (int) $post->post_author,
					)
				)
			),
			'unattached',
			'An update with the stored values'
		);
		$this->assertSame( $post->post_name, get_post_field( 'post_name', self::$post_ids['unattached'] ), 'The slug should stay.' );
	}

	/**
	 * A listener after the save sees the new alt text.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_listener_after_the_save_sees_the_alt_text(): void {
		$target = self::$post_ids['unattached'];
		$seen   = array();
		add_action(
			'wp_after_insert_post',
			static function ( int $post_id ) use ( &$seen, $target ): void {
				if ( $target === $post_id ) {
					$seen[] = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
				}
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated( $this->run_ability( $this->update_input( 'unattached', array( 'alt_text' => 'MDT listener alt' ) ) ), 'unattached', 'An update of the alt text' );

		$this->assertCount( 1, $seen, 'The listener should run once for the attachment.' );
		$this->assertSame( 'MDT listener alt', end( $seen ), 'The last call of the listener should see the new alt text.' );
	}

	/**
	 * Returns attributes, each sent alone, with the column that stores it, the value sent
	 * and the value stored. For `author` and `post` the value is a fixture name, and the
	 * stored value is its ID.
	 *
	 * @return array<string, array{attribute: string, column: string, value: mixed, stored: string|null}> The cases.
	 */
	public static function data_attributes_sent_alone(): array {
		return array(
			'title'       => array( 'title', 'post_title', 'MDT listener title', 'MDT listener title' ),
			'caption'     => array( 'caption', 'post_excerpt', 'MDT listener caption', 'MDT listener caption' ),
			'description' => array( 'description', 'post_content', 'MDT listener description', 'MDT listener description' ),
			'slug'        => array( 'slug', 'post_name', 'mdt-listener-slug', 'mdt-listener-slug' ),
			'date'        => array( 'date', 'post_date', array( 'site' => '2020-01-02T03:04:05' ), '2020-01-02 03:04:05' ),
			'author'      => array( 'author', 'post_author', 'editor', null ),
			'post'        => array( 'post', 'post_parent', 'post', null ),
		);
	}

	/**
	 * A listener after the save runs once for the attachment, as an update, after the sent
	 * value is stored, and gets the attachment as it was before the call.
	 *
	 * @ticket 66719
	 * @dataProvider data_attributes_sent_alone
	 * @since 7.3.0
	 *
	 * @param string      $attribute The attribute.
	 * @param string      $column    The column that stores it.
	 * @param mixed       $value     The value sent, or a fixture name for `author` and `post`.
	 * @param string|null $stored    The value stored, or null for the ID of the fixture.
	 */
	public function test_listener_after_the_save_runs_once_after_the_value_is_stored( string $attribute, string $column, $value, ?string $stored ): void {
		global $wpdb;

		if ( 'author' === $attribute ) {
			$value = self::$user_ids[ $value ];
		} elseif ( 'post' === $attribute ) {
			$value = self::$post_ids[ $value ];
		}

		$stored ??= (string) $value;
		$target   = self::$post_ids['unattached'];
		$previous = get_post( $target );
		$calls    = array();

		$this->assertNotSame( $stored, (string) $previous->$column, 'The value sent should differ from the stored one.' );

		add_action(
			'wp_after_insert_post',
			static function ( int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before ) use ( &$calls, $target, $wpdb ): void {
				if ( $target === $post_id ) {
					$calls[] = array(
						'update' => $update,
						'before' => $post_before,
						'row'    => $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A ),
					);
				}
			},
			10,
			4
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated( $this->run_ability( $this->update_input( 'unattached', array( $attribute => $value ) ) ), 'unattached', "An update of the {$attribute}" );

		$this->assertCount( 1, $calls, 'The listener should run once for the attachment.' );
		$this->assertTrue( $calls[0]['update'], 'The listener should be told that this is an update.' );
		$this->assertSame( $stored, $calls[0]['row'][ $column ], "The {$attribute} should be stored when the listener runs." );
		$this->assertInstanceOf( WP_Post::class, $calls[0]['before'], 'The listener should get the attachment as it was before.' );
		$this->assertSame( $previous->$column, $calls[0]['before']->$column, "The attachment the listener gets should hold the {$attribute} from before the call." );
	}

	/**
	 * Returns save failures with the status of their error.
	 *
	 * @return array<string, array{database_error: bool, status: int}> The cases.
	 */
	public static function data_save_failures(): array {
		return array(
			'empty post'     => array( false, 400 ),
			'database error' => array( true, 500 ),
		);
	}

	/**
	 * When the save fails nothing changes, the alt text included.
	 *
	 * @ticket 66719
	 * @dataProvider data_save_failures
	 * @since 7.3.0
	 *
	 * @param bool $database_error Whether the save fails with a database error.
	 * @param int  $status         The expected status.
	 */
	public function test_failed_save_changes_nothing( bool $database_error, int $status ): void {
		update_post_meta( self::$post_ids['unattached'], '_wp_attachment_image_alt', 'MDT old alt' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot( self::$post_ids['unattached'] );
		$this->make_saves_fail( $database_error );

		$result = $this->run_ability(
			$this->update_input(
				'unattached',
				array(
					'title'    => 'MDT failed title',
					'alt_text' => 'MDT failed alt',
				)
			)
		);

		$this->assert_error( $result, 'media_save_failed', $status, 'A failed save' );
		$this->assert_unchanged( $before, 'A failed save' );
	}

	/**
	 * Returns attachments of a status other than `inherit`.
	 *
	 * @return array<string, array{attachment: string, status: string}> The cases.
	 */
	public static function data_attachments_with_a_status(): array {
		return array(
			'private' => array( 'private', 'private' ),
			'trashed' => array( 'trashed', 'trash' ),
		);
	}

	/**
	 * A private or trashed attachment is updated and keeps its status.
	 *
	 * @ticket 66719
	 * @dataProvider data_attachments_with_a_status
	 * @since 7.3.0
	 *
	 * @param string $attachment The attachment, as a fixture name.
	 * @param string $status     The stored status.
	 */
	public function test_status_never_changes( string $attachment, string $status ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated( $this->run_ability( $this->update_input( $attachment, array( 'title' => 'MDT kept status' ) ) ), $attachment, 'An update of an attachment with a status' );

		$post = get_post( self::$post_ids[ $attachment ] );
		$this->assertSame( $status, $post->post_status, 'The status should stay.' );
		$this->assertSame( 'MDT kept status', $post->post_title, 'The title should change.' );
	}

	/**
	 * Returns keys that are not part of the input, and where they are sent.
	 *
	 * @return array<string, array{location: string, key: string, value: mixed}> The cases.
	 */
	public static function data_keys_outside_the_allow_list(): array {
		return array(
			'data ID'             => array( 'data', 'ID', 1 ),
			'data import_id'      => array( 'data', 'import_id', 7654321 ),
			'data status'         => array( 'data', 'status', 'private' ),
			'data post_status'    => array( 'data', 'post_status', 'private' ),
			'data post_type'      => array( 'data', 'post_type', 'post' ),
			'data mime_type'      => array( 'data', 'mime_type', 'text/html' ),
			'data post_mime_type' => array( 'data', 'post_mime_type', 'text/html' ),
			'data guid'           => array( 'data', 'guid', 'https://example.org/forged' ),
			'data password'       => array( 'data', 'password', 'secret' ),
			'data post_password'  => array( 'data', 'post_password', 'secret' ),
			'data comment_status' => array( 'data', 'comment_status', 'closed' ),
			'data ping_status'    => array( 'data', 'ping_status', 'closed' ),
			'data menu_order'     => array( 'data', 'menu_order', 5 ),
			'data meta'           => array( 'data', 'meta', array( 'mdt_key' => 'value' ) ),
			'data meta_input'     => array( 'data', 'meta_input', array( 'mdt_key' => 'value' ) ),
			'data tax_input'      => array( 'data', 'tax_input', array( 'category' => array( 1 ) ) ),
			'data post_author'    => array( 'data', 'post_author', 1 ),
			'data post_parent'    => array( 'data', 'post_parent', 1 ),
			'data modified'       => array( 'data', 'modified', '2001-01-01T00:00:00' ),
			'where status'        => array( 'where', 'status', 'inherit' ),
			'where ID'            => array( 'where', 'ID', 1 ),
		);
	}

	/**
	 * A key outside the allow-list fails input validation, and nothing is written.
	 *
	 * @ticket 66719
	 * @dataProvider data_keys_outside_the_allow_list
	 * @since 7.3.0
	 *
	 * @param string $location Where the key is sent.
	 * @param string $key      The key.
	 * @param mixed  $value    The value.
	 */
	public function test_key_outside_the_allow_list_fails_input_validation( string $location, string $key, $value ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$input = $this->update_input( 'unattached', array( 'title' => 'MDT extra key' ) );

		$input[ $location ][ $key ] = $value;

		$before = $this->snapshot( self::$post_ids['unattached'] );
		$result = $this->run_ability( $input );

		$this->assertWPError( $result, 'The call should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the key.' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['unattached'] ), 'Nothing should be written.' );
	}

	/**
	 * The same call twice gives the same stored values and no error.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_same_call_twice(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$input = $this->update_input(
			'unattached',
			array(
				'title'    => 'MDT twice',
				'slug'     => 'mdt-twice',
				'post'     => self::$post_ids['author_post'],
				'alt_text' => 'MDT twice alt',
			),
			array( 'title.raw', 'slug', 'post', 'alt_text' )
		);

		$first  = $this->run_ability( $input );
		$second = $this->run_ability( $input );

		$this->assert_updated( $first, 'unattached', 'The first call' );
		$this->assert_updated( $second, 'unattached', 'The second call' );
		$this->assertSame( $first, $second, 'The stored values should be the same.' );
	}

	/**
	 * The output holds the default fields when none are sent, and the selected ones otherwise.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_output_fields(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$default = $this->run_ability( $this->update_input( 'unattached', array( 'title' => 'MDT default' ) ) );
		$this->assert_updated( $default, 'unattached', 'An update without fields' );
		$this->assertSameSets( self::DEFAULT_KEYS, array_keys( $default ), 'The item should hold the default fields.' );
		$this->assertSame( 'MDT default', $default['title']['rendered'], 'The rendered title should be the new one.' );

		$selected = $this->run_ability(
			$this->update_input(
				'unattached',
				array(
					'caption'  => 'October 2026',
					'alt_text' => 'The team on the office roof',
				),
				array( 'caption', 'alt_text' )
			)
		);
		$this->assert_updated( $selected, 'unattached', 'An update with fields' );
		$this->assertSameSets( array( 'id', 'caption', 'alt_text' ), array_keys( $selected ), 'The item should hold the selected fields.' );
		$this->assertSame( 'October 2026', $selected['caption']['raw'], 'The raw caption should be the new one.' );
		$this->assertSame( 'The team on the office roof', $selected['alt_text'], 'The alt text should be the new one.' );
	}

	/**
	 * The alt text is sanitized as a text field, and an empty one is saved as an empty text.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_alt_text(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated( $this->run_ability( $this->update_input( 'unattached', array( 'alt_text' => "<b>MDT</b>\n alt " ) ) ), 'unattached', 'An update with markup in the alt text' );
		$this->assertSame( sanitize_text_field( "<b>MDT</b>\n alt " ), get_post_meta( self::$post_ids['unattached'], '_wp_attachment_image_alt', true ), 'The alt text should be sanitized as a text field.' );

		$result = $this->run_ability( $this->update_input( 'unattached', array( 'alt_text' => '' ), array( 'alt_text' ) ) );
		$this->assert_updated( $result, 'unattached', 'An update with an empty alt text' );
		$this->assertSame( '', $result['alt_text'], 'The output alt text should be empty.' );
		$this->assertSame( '', get_post_meta( self::$post_ids['unattached'], '_wp_attachment_image_alt', true ), 'The stored alt text should be empty.' );
	}

	/**
	 * Returns callers who may edit an attachment of the author, with whether their markup is
	 * kept. On single site the editor has `unfiltered_html`; on multisite only a super admin
	 * has it.
	 *
	 * @return array<string, array{role: string, kept: bool, super_admin?: bool}> The cases.
	 */
	public static function data_markup_roles(): array {
		if ( is_multisite() ) {
			return array(
				'editor'      => array( 'editor', false ),
				'author'      => array( 'author', false ),
				'super admin' => array( 'administrator', true, true ),
			);
		}

		return array(
			'editor' => array( 'editor', true ),
			'author' => array( 'author', false ),
		);
	}

	/**
	 * Markup in the title, caption and description is kept for a caller with
	 * `unfiltered_html` and filtered for one without it.
	 *
	 * @ticket 66719
	 * @dataProvider data_markup_roles
	 * @since 7.3.0
	 *
	 * @param string $role        The role.
	 * @param bool   $kept        Whether the markup is kept.
	 * @param bool   $super_admin Optional. Whether the caller is a super admin. Default false.
	 */
	public function test_markup_follows_unfiltered_html( string $role, bool $kept, bool $super_admin = false ): void {
		if ( $super_admin ) {
			grant_super_admin( self::$user_ids[ $role ] );
		}

		$this->login_as( $role );
		$this->register_abilities();

		$markup = 'MDT <script>alert(1)</script>';
		$this->assert_updated(
			$this->run_ability(
				$this->update_input(
					'unattached',
					array(
						'title'       => $markup,
						'caption'     => $markup,
						'description' => $markup,
					)
				)
			),
			'unattached',
			'An update with markup'
		);

		if ( $super_admin ) {
			revoke_super_admin( self::$user_ids[ $role ] );
		}

		$post = get_post( self::$post_ids['unattached'] );

		foreach ( array( 'post_title', 'post_excerpt', 'post_content' ) as $field ) {
			$this->assertSame( $kept, str_contains( $post->$field, '<script>' ), $kept ? "The markup in {$field} should be kept." : "The markup in {$field} should be filtered." );
		}
	}

	/**
	 * Returns texts that hold backslashes.
	 *
	 * @return array<string, array{text: string}> The texts.
	 */
	public static function data_texts_with_backslashes(): array {
		return array(
			'single backslashes' => array( 'C:\\mdt\\x' ),
			'double backslash'   => array( 'a\\\\b' ),
		);
	}

	/**
	 * Backslashes in the title, caption, description and alt text are stored as they were
	 * sent.
	 *
	 * @ticket 66719
	 * @dataProvider data_texts_with_backslashes
	 * @since 7.3.0
	 *
	 * @param string $text The text.
	 */
	public function test_backslashes_are_stored_as_sent( string $text ): void {
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap ): array {
				return 'unfiltered_html' === $cap ? array( 'exist' ) : $caps;
			},
			10,
			2
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated(
			$this->run_ability(
				$this->update_input(
					'unattached',
					array(
						'title'       => $text,
						'caption'     => $text,
						'description' => $text,
						'alt_text'    => $text,
					)
				)
			),
			'unattached',
			'An update with backslashes'
		);

		$id = self::$post_ids['unattached'];
		$this->assertSame( $text, get_post_field( 'post_title', $id, 'raw' ), 'The title should be stored as sent.' );
		$this->assertSame( $text, get_post_field( 'post_excerpt', $id, 'raw' ), 'The caption should be stored as sent.' );
		$this->assertSame( $text, get_post_field( 'post_content', $id, 'raw' ), 'The description should be stored as sent.' );
		$this->assertSame( $text, get_post_meta( $id, '_wp_attachment_image_alt', true ), 'The alt text should be stored as sent.' );
	}

	/**
	 * When the caller cannot read the attachment after the update, the update succeeds and
	 * returns only the ID.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_unreadable_attachment_returns_only_the_id(): void {
		$this->login_as( 'other_editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( 'private_parent', array( 'title' => 'MDT unreadable' ), array( 'title', 'status' ) ) );

		$this->assertSame( array( 'id' => self::$post_ids['private_parent'] ), $result, 'The output should hold only the ID.' );
		$this->assertSame( 'MDT unreadable', get_post_field( 'post_title', self::$post_ids['private_parent'] ), 'The update should be saved.' );
	}

	/**
	 * On multisite an author who is not a member of the site is an invalid author.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_author_of_another_site_is_invalid(): void {
		$outsider = self::factory()->user->create();
		remove_user_from_blog( $outsider, get_current_blog_id() );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( 'editor_image', array( 'author' => $outsider ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[author]', 'An author of another site' );
		$this->assert_error_message( $result, 'Invalid author ID.', 'An author of another site' );
	}

	/**
	 * On multisite a super admin who is not a member of the site may send their own ID as
	 * the author.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_super_admin_outside_the_site_may_send_the_own_id(): void {
		$super_admin = self::factory()->user->create();
		remove_user_from_blog( $super_admin, get_current_blog_id() );
		grant_super_admin( $super_admin );

		wp_set_current_user( $super_admin );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( 'unattached', array( 'author' => $super_admin ) ) );
		revoke_super_admin( $super_admin );

		$this->assert_updated( $result, 'unattached', 'An update by a super admin outside the site' );
		$this->assertSame( (string) $super_admin, get_post_field( 'post_author', self::$post_ids['unattached'] ), 'The stored author should be the super admin.' );
	}
}
