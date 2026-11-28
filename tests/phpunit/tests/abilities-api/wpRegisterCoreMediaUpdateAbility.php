<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-media-item-ability.php';
require_once __DIR__ . '/trait-media-attribute-tests.php';

/**
 * Tests for the core/media-update ability.
 *
 * @covers WP_Abilities_Media
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreMediaUpdateAbility extends WP_Abilities_Media_Item_UnitTestCase {

	use WP_Abilities_Media_Attribute_Tests;

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/media-update';

	/**
	 * The message of a write as another author.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const OTHER_AUTHOR_MESSAGE = 'Sorry, you are not allowed to update posts as this user.';

	/**
	 * The message of a write to a parent the caller may not edit.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ATTACH_MESSAGE = 'Sorry, you are not allowed to attach media to this post.';

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
			'where' => array( 'id' => self::$post_ids[ $attachment ] ?? REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ),
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
	protected function assert_unchanged( array $before, string $context ): void {
		$after = $this->snapshot( (int) $before['post']['ID'] );

		$this->assertSame( $before['post'], $after['post'], "{$context}: the attachment row should not change." );
		$this->assertSame( $before['post_meta'], $after['post_meta'], "{$context}: the attachment meta should not change." );
		$this->assertSame( $before['posts'], $after['posts'], "{$context}: no post should be added or removed." );
	}

	/**
	 * Updates the attachment of the author with the given attributes.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $data   The attributes.
	 * @param string[]|null        $fields Optional. The fields. Default null.
	 * @return mixed The ability result.
	 */
	protected function write( array $data, ?array $fields = null ) {
		return $this->run_ability( $this->update_input( 'unattached', $data, $fields ) );
	}

	/**
	 * Asserts that an update of the attachment of the author succeeded and returns its ID.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 * @return int The attachment ID.
	 */
	protected function assert_written( $result, string $context ): int {
		$this->assert_updated( $result, 'unattached', $context );

		return self::$post_ids['unattached'];
	}

	/**
	 * Takes a snapshot of the attachment of the author.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The snapshot.
	 */
	protected function snapshot_write(): array {
		return $this->snapshot( self::$post_ids['unattached'] );
	}

	/**
	 * Sets columns of a shared attachment directly.
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
	 * Returns an input for the given `where` value.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $where The `where` value.
	 * @return array<string, mixed> The input.
	 */
	protected function build_input( array $where ): array {
		return array(
			'where' => $where,
			'data'  => array( 'title' => 'MDT shared gate' ),
		);
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
	 * The input takes the attachment ID, the attributes and the fields, and the output is
	 * one attachment.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_schemas(): void {
		$this->register_abilities();
		$ability = wp_get_ability( self::ABILITY );
		$input   = $ability->get_input_schema();

		$this->assert_object_schema( $input, array( 'where', 'data' ), array( 'where', 'data', 'fields' ), 'The input schema' );
		$this->assert_where_id_schema( $input['properties']['where'] );
		$this->assert_attributes_schema( $input['properties']['data'], array() );
		$this->assertSame( 1, $input['properties']['data']['minProperties'] ?? null, 'The data should change at least one attribute.' );
		$this->assert_fields_schema( $input['properties']['fields'] );
		$this->assert_item_schema( $ability->get_output_schema() );
	}

	/**
	 * Without `edit_post` the callback refuses the call before any attribute is checked, and
	 * nothing changes.
	 *
	 * @ticket 66719
	 * @dataProvider data_callers_without_the_capability
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
	 * Data without any attribute is invalid input.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_data_without_attributes(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_write();

		$this->assert_invalid_input( $this->write( array() ), 'Empty data' );
		$this->assert_unchanged( $before, 'Empty data' );
	}

	/**
	 * The stored author sent again is not checked, also when that user no longer exists.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_stored_author_sent_again_is_not_checked(): void {
		$this->set_columns( 'editor_image', array( 'post_author' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_updated(
			$this->run_ability(
				$this->update_input(
					'editor_image',
					array(
						'author' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
						'title'  => 'MDT stored author',
					)
				)
			),
			'editor_image',
			'An update with the stored author'
		);
		$this->assertSame( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, (int) get_post_field( 'post_author', self::$post_ids['editor_image'] ), 'The author should stay.' );
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

		$this->set_columns( 'unattached', array( 'post_parent' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );

		$this->assert_updated(
			$this->run_ability(
				$this->update_input(
					'unattached',
					array(
						'post'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
						'title' => 'MDT missing parent',
					)
				)
			),
			'unattached',
			'An update with a current parent that no longer exists'
		);
		$this->assertSame( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, get_post( self::$post_ids['unattached'] )->post_parent, 'The parent should stay.' );
	}

	/**
	 * A current parent of an invalid type sent again is still invalid.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_current_parent_of_an_invalid_type(): void {
		$this->set_columns( 'unattached', array( 'post_parent' => self::$post_ids['revision'] ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_error_with_params( $this->write( array( 'post' => self::$post_ids['revision'] ) ), 'media_invalid_attribute', 'data[post]', 'A current parent of an invalid type' );
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
	 * The attachment update hook fires for the attachment, and a callback on the attachment
	 * data runs during the update and stays registered.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_update_runs_the_attachment_hooks(): void {
		$updated    = array();
		$data_calls = 0;
		$listener   = static function ( int $post_id ) use ( &$updated ): void {
			$updated[] = $post_id;
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

		$this->assertSame( array( self::$post_ids['editor_image'] ), array_values( array_unique( $updated ) ), 'The attachment update hook should fire for the attachment only.' );
		$this->assertGreaterThan( 0, $data_calls, 'The attachment data callback should run during the update.' );
		$this->assertNotFalse( has_filter( 'wp_insert_attachment_data', $callback ), 'The attachment data callback should stay registered.' );
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
		$this->assertSame( $first, $second, 'The second call should return the same item.' );
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
}
