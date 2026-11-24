<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-media-ability.php';

/**
 * Tests for the core/media-query ability.
 *
 * @covers WP_Abilities_Media
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreMediaAbility extends WP_Abilities_Media_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/media-query';

	/**
	 * The result of a query that matches nothing, on any page.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, array<mixed>>
	 */
	private const EMPTY_RESULT = array(
		'items'      => array(),
		'pagination' => array(
			'total'       => 0,
			'total_pages' => 0,
		),
	);

	/**
	 * Creates the shared fixtures, and the attachments of the order and paging tests.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::create_shared_fixtures( $factory );

		$order_parent = $factory->post->create( array( 'post_author' => self::$user_ids['editor'] ) );
		$dates        = array(
			'order_a' => '2022-02-02 10:00:00',
			'order_b' => '2022-02-02 10:00:00',
			'order_c' => '2022-03-03 10:00:00',
			'order_d' => '2022-01-01 10:00:00',
		);

		self::$post_ids['order_parent'] = $order_parent;
		foreach ( $dates as $name => $date ) {
			self::$post_ids[ $name ] = $factory->attachment->create_object(
				array(
					'file'           => "2026/01/mdt-{$name}.jpg",
					'post_parent'    => $order_parent,
					'post_mime_type' => 'image/jpeg',
					'post_author'    => self::$user_ids['editor'],
					'post_date'      => $date,
				)
			);
		}

		$paging_parent                   = $factory->post->create( array( 'post_author' => self::$user_ids['editor'] ) );
		self::$post_ids['paging_parent'] = $paging_parent;
		for ( $i = 1; $i <= 11; $i++ ) {
			self::$post_ids[ "paging_{$i}" ] = $factory->attachment->create_object(
				array(
					'file'           => "2026/01/mdt-paging-{$i}.jpg",
					'post_parent'    => $paging_parent,
					'post_mime_type' => 'image/jpeg',
					'post_author'    => self::$user_ids['editor'],
				)
			);
		}

		self::$post_ids['no_posts_author_image'] = $factory->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-no-posts-author.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => self::$user_ids['author_without_posts'],
			)
		);
	}

	/**
	 * Runs the query and returns the IDs of the items.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $input The input.
	 * @return list<int> The item IDs.
	 */
	private function query_ids( array $input ): array {
		$input['fields'] = array( 'id' );
		$result          = $this->run_ability( $input );

		$this->assertIsArray( $result, 'The query should succeed.' );

		return wp_list_pluck( $result['items'], 'id' );
	}

	/**
	 * Returns the item of one attachment, with the given fields.
	 *
	 * @since 7.3.0
	 *
	 * @param int               $id     The attachment ID.
	 * @param list<string>|null $fields The fields, or null for the defaults.
	 * @param list<string>      $status Optional. The statuses to query. Default `inherit`.
	 * @return array<string, mixed> The item.
	 */
	private function query_item( int $id, ?array $fields, array $status = array( 'inherit' ) ): array {
		$input = array(
			'where' => array(
				'ids'    => array( $id ),
				'status' => $status,
			),
		);

		if ( null !== $fields ) {
			$input['fields'] = $fields;
		}

		$result = $this->run_ability( $input );

		$this->assertIsArray( $result, 'The query should succeed.' );
		$this->assertCount( 1, $result['items'], 'The query should return the attachment.' );

		return $result['items'][0];
	}

	/**
	 * The ability is registered with its label, category, meta and annotations.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_registers_the_query_ability(): void {
		$this->assert_registration( 'Query Media', true, false, true );
	}

	/**
	 * The input schema is the published one.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_input_schema(): void {
		$this->register_abilities();

		$list = static function ( array $items, ?int $max_items = null ): array {
			$schema = array(
				'type'        => 'array',
				'uniqueItems' => true,
				'minItems'    => 1,
				'items'       => $items,
			);

			if ( null !== $max_items ) {
				$schema['maxItems'] = $max_items;
			}

			return $schema;
		};

		$expected = array(
			'type'                 => 'object',
			'default'              => array(),
			'additionalProperties' => false,
			'properties'           => array(
				'where'      => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'ids'        => $list(
							array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							100
						),
						'slugs'      => $list(
							array(
								'type'      => 'string',
								'minLength' => 1,
							),
							100
						),
						'status'     => $list(
							array(
								'type' => 'string',
								'enum' => array( 'inherit', 'private', 'trash' ),
							)
						),
						'media_type' => $list(
							array(
								'type' => 'string',
								'enum' => array( 'application', 'audio', 'image', 'text', 'video' ),
							)
						),
						'mime_type'  => $list(
							array(
								'type'      => 'string',
								'minLength' => 1,
							)
						),
						'post'       => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'author'     => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'minProperties'        => 1,
							'properties'           => array(
								'id'   => array(
									'type'    => 'integer',
									'minimum' => 1,
								),
								'slug' => array(
									'type'      => 'string',
									'minLength' => 1,
								),
							),
						),
					),
				),
				'fields'     => self::fields_schema(),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
			),
		);

		$this->assertSame(
			$this->normalize_schema( $expected ),
			$this->normalize_schema( wp_get_ability( self::ABILITY )->get_input_schema() ),
			'The input schema should match.'
		);
	}

	/**
	 * The output schema is the published one.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_output_schema(): void {
		$this->register_abilities();

		$expected = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'items', 'pagination' ),
			'properties'           => array(
				'items'      => array(
					'type'  => 'array',
					'items' => self::item_schema(),
				),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'total', 'total_pages' ),
					'properties'           => array(
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
					),
				),
			),
		);

		$this->assertSame(
			$this->normalize_schema( $expected ),
			$this->normalize_schema( wp_get_ability( self::ABILITY )->get_output_schema() ),
			'The output schema should match.'
		);
	}

	/**
	 * Returns logged-in roles.
	 *
	 * @return array<string, array{role: string}> The roles.
	 */
	public static function data_logged_in_roles(): array {
		$roles = self::data_all_roles();
		unset( $roles['logged out'] );
		$roles['role without capabilities'] = array( 'no_caps' );

		return $roles;
	}

	/**
	 * The permission callback passes for every logged-in caller, whatever the status filter.
	 *
	 * @ticket 66719
	 * @dataProvider data_logged_in_roles
	 * @since 7.3.0
	 *
	 * @param string $role The role.
	 */
	public function test_permission_callback_passes_for_a_logged_in_caller( string $role ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$ability = wp_get_ability( self::ABILITY );

		$this->assertTrue( $ability->check_permissions( array() ), 'The permission callback should pass without input.' );
		$this->assertTrue( $ability->check_permissions( array( 'where' => array( 'status' => array( 'trash' ) ) ) ), 'The permission callback should not look at the status filter.' );
	}

	/**
	 * The permission callback refuses a logged-out caller, and a call when the attachment type
	 * has `show_in_abilities` off.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_logged_out_callers_and_hidden_attachments(): void {
		$this->register_abilities();
		$ability = wp_get_ability( self::ABILITY );

		$this->login_as( 'logged_out' );
		$this->assertFalse( $ability->check_permissions( array() ), 'A logged-out caller should be refused.' );

		$this->login_as( 'administrator' );
		get_post_type_object( 'attachment' )->show_in_abilities = false;
		$this->assertFalse( $ability->check_permissions( array() ), 'An administrator should be refused when the attachment type has show_in_abilities off.' );
	}

	/**
	 * A logged-out call is refused with the generic error, and input the schema refuses is
	 * answered before the permission callback.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_generic_errors(): void {
		$this->login_as( 'logged_out' );
		$this->register_abilities();

		$this->assert_refused( $this->run_ability( array() ), 'A logged-out query' );

		$invalid = $this->run_ability( array( 'pagination' => array( 'per_page' => 0 ) ) );
		$this->assertWPError( $invalid, 'An invalid query should fail.' );
		$this->assertSame( 'ability_invalid_input', $invalid->get_error_code(), 'Invalid input should be answered before the permission callback.' );
	}

	/**
	 * Without a status filter only attachments in the `inherit` status are listed.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_default_status_is_inherit(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$ids = $this->query_ids(
			array(
				'where' => array(
					'ids' => array( self::$post_ids['unattached'], self::$post_ids['private'], self::$post_ids['trashed'] ),
				),
			)
		);

		$this->assertSame( array( self::$post_ids['unattached'] ), $ids, 'Only the attachment in the inherit status should be listed.' );
	}

	/**
	 * Returns status filters with the roles that may and may not use them.
	 *
	 * @return array<string, array{role: string, status: list<string>, allowed: bool}> The cases.
	 */
	public static function data_status_filters(): array {
		return array(
			'subscriber, inherit'          => array( 'subscriber', array( 'inherit' ), true ),
			'subscriber, private'          => array( 'subscriber', array( 'private' ), false ),
			'subscriber, trash'            => array( 'subscriber', array( 'trash' ), false ),
			'subscriber, inherit, private' => array( 'subscriber', array( 'inherit', 'private' ), false ),
			'read and upload, private'     => array( 'uploader', array( 'private' ), false ),
			'read and upload, trash'       => array( 'uploader', array( 'trash' ), false ),
			'contributor, private'         => array( 'contributor', array( 'private' ), true ),
			'contributor, trash'           => array( 'contributor', array( 'trash' ), true ),
			'author, inherit, private'     => array( 'author', array( 'inherit', 'private' ), true ),
			'editor, all statuses'         => array( 'editor', array( 'inherit', 'private', 'trash' ), true ),
			'administrator, trash'         => array( 'administrator', array( 'trash' ), true ),
		);
	}

	/**
	 * A status filter needs `read_private_posts` or `edit_posts` for `private` and
	 * `edit_posts` for `trash`; one status the caller may not use refuses the call, and a
	 * caller who may use a status gets the attachments in that status.
	 *
	 * @ticket 66719
	 * @dataProvider data_status_filters
	 * @since 7.3.0
	 *
	 * @param string       $role    The role.
	 * @param list<string> $status  The status filter.
	 * @param bool         $allowed Whether the caller may use it.
	 */
	public function test_status_filter_capabilities( string $role, array $status, bool $allowed ): void {
		$own = array();
		if ( $allowed ) {
			foreach ( $status as $own_status ) {
				$own[] = self::factory()->attachment->create_object(
					array(
						'file'           => "2026/01/mdt-own-{$own_status}.jpg",
						'post_status'    => $own_status,
						'post_mime_type' => 'image/jpeg',
						'post_author'    => self::$user_ids[ $role ],
					)
				);
			}
		}

		$this->login_as( $role );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where' => array(
					'status' => $status,
					'ids'    => array() === $own ? array( self::$post_ids['unattached'] ) : $own,
				),
			)
		);

		if ( $allowed ) {
			$this->assertIsArray( $result, 'The caller should be allowed to filter by these statuses.' );
			$this->assertSameSets( $own, wp_list_pluck( $result['items'], 'id' ), 'The attachments in the allowed statuses should be listed.' );
			return;
		}

		$this->assert_error( $result, 'media_cannot_filter_by_status', 403, 'A forbidden status filter' );
		$this->assert_error_message( $result, 'Sorry, you are not allowed to filter media by this status.', 'A forbidden status filter' );
	}

	/**
	 * The status filter lists the attachments in the requested statuses.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_status_filter_selects_statuses(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$ids = array( self::$post_ids['unattached'], self::$post_ids['private'], self::$post_ids['trashed'] );

		$this->assertSame(
			array( self::$post_ids['trashed'] ),
			$this->query_ids(
				array(
					'where' => array(
						'ids'    => $ids,
						'status' => array( 'trash' ),
					),
				)
			),
			'The trash filter should list only the trashed attachment.'
		);
		$this->assertSameSets(
			array( self::$post_ids['unattached'], self::$post_ids['private'] ),
			$this->query_ids(
				array(
					'where' => array(
						'ids'    => $ids,
						'status' => array( 'inherit', 'private' ),
					),
				)
			),
			'The inherit and private filter should list both attachments.'
		);
	}

	/**
	 * Returns status lists that hold a value outside the status set.
	 *
	 * @return array<string, array{role: string, status: list<string>}> The cases.
	 */
	public static function data_statuses_outside_the_set(): array {
		return array(
			'subscriber, publish'         => array( 'subscriber', array( 'publish' ) ),
			'subscriber, trash and bogus' => array( 'subscriber', array( 'trash', 'mdt_bogus' ) ),
			'editor, bogus'               => array( 'editor', array( 'mdt_bogus' ) ),
			'editor, inherit and draft'   => array( 'editor', array( 'inherit', 'draft' ) ),
		);
	}

	/**
	 * A status outside the status set is invalid input for every caller.
	 *
	 * @ticket 66719
	 * @dataProvider data_statuses_outside_the_set
	 * @since 7.3.0
	 *
	 * @param string       $role   The role.
	 * @param list<string> $status The status filter.
	 */
	public function test_status_outside_the_set_is_invalid_input( string $role, array $status ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => array( 'status' => $status ) ) );

		$this->assertWPError( $result, 'A status outside the set should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject a status outside the set.' );
	}

	/**
	 * The capability to filter by a status is checked before any other filter.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_status_is_checked_before_other_filters(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$forbidden = $this->run_ability(
			array(
				'where' => array(
					'status'    => array( 'trash' ),
					'mime_type' => array( 'mdt/not-allowed' ),
				),
			)
		);
		$this->assert_error( $forbidden, 'media_cannot_filter_by_status', 403, 'A forbidden status next to an invalid MIME type' );
	}

	/**
	 * IDs select attachments; an ID of another post type or of nothing matches nothing.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_ids_select_attachments_only(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$ids = $this->query_ids(
			array(
				'where' => array(
					'ids' => array( self::$post_ids['unattached'], self::$post_ids['pdf'], self::$post_ids['post'], self::$post_ids['page'], self::MISSING_ID ),
				),
			)
		);

		$this->assertSameSets( array( self::$post_ids['unattached'], self::$post_ids['pdf'] ), $ids, 'Only the attachments should be listed.' );
	}

	/**
	 * Slugs are sanitized as post slugs; a slug of nothing matches nothing.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_slugs_are_sanitized_as_post_slugs(): void {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-team-photo.jpg',
				'post_name'      => 'mdt-team-photo',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$this->assertSame( array( $id ), $this->query_ids( array( 'where' => array( 'slugs' => array( 'MDT-Team-Photo', 'mdt-no-such-slug' ) ) ) ), 'The slug should match after sanitizing.' );
		$this->assertSame( self::EMPTY_RESULT, $this->run_ability( array( 'where' => array( 'slugs' => array( 'mdt-no-such-slug' ) ) ) ), 'A slug of nothing should match nothing.' );
	}

	/**
	 * A media type selects the allowed types of its group, and several groups add up.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_media_type_selects_groups(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$ids = array( self::$post_ids['unattached'], self::$post_ids['pdf'], self::$post_ids['audio'] );

		$this->assertSame(
			array( self::$post_ids['pdf'] ),
			$this->query_ids(
				array(
					'where' => array(
						'ids'        => $ids,
						'media_type' => array( 'application' ),
					),
				)
			),
			'The application group should select the PDF.'
		);
		$this->assertSameSets(
			array( self::$post_ids['unattached'], self::$post_ids['audio'] ),
			$this->query_ids(
				array(
					'where' => array(
						'ids'        => $ids,
						'media_type' => array( 'image', 'audio' ),
					),
				)
			),
			'The image and audio groups should select the image and the audio file.'
		);
	}

	/**
	 * A media type group without any allowed type is an invalid filter.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_media_type_without_allowed_types_is_an_invalid_filter(): void {
		add_filter(
			'upload_mimes',
			static function ( array $mimes ): array {
				return array_filter(
					$mimes,
					static function ( string $mime ): bool {
						return ! str_starts_with( $mime, 'video/' );
					}
				);
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => array( 'media_type' => array( 'image', 'video' ) ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_filter', 'where[media_type]', 'A media type without allowed types' );
	}

	/**
	 * A MIME type selects attachments of exactly that type.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_mime_type_selects_exact_types(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$ids = $this->query_ids(
			array(
				'where' => array(
					'ids'       => array( self::$post_ids['unattached'], self::$post_ids['pdf'], self::$post_ids['audio'] ),
					'mime_type' => array( 'application/pdf', 'audio/mpeg' ),
				),
			)
		);

		$this->assertSameSets( array( self::$post_ids['pdf'], self::$post_ids['audio'] ), $ids, 'The MIME types should select the PDF and the audio file.' );
	}

	/**
	 * Returns MIME types that are not exactly one of the allowed types.
	 *
	 * @return array<string, array{mime_type: string}> The MIME types.
	 */
	public static function data_mime_types_that_are_not_allowed(): array {
		return array(
			'alias'       => array( 'image/jpg' ),
			'group only'  => array( 'image' ),
			'wildcard'    => array( 'image/*' ),
			'not allowed' => array( 'application/x-php' ),
			'upper case'  => array( 'IMAGE/JPEG' ),
		);
	}

	/**
	 * A MIME type that is not exactly one of the allowed types is an invalid filter.
	 *
	 * @ticket 66719
	 * @dataProvider data_mime_types_that_are_not_allowed
	 * @since 7.3.0
	 *
	 * @param string $mime_type The MIME type.
	 */
	public function test_mime_type_that_is_not_allowed_is_an_invalid_filter( string $mime_type ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => array( 'mime_type' => array( 'image/jpeg', $mime_type ) ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_filter', 'where[mime_type]', 'A MIME type that is not allowed' );
	}

	/**
	 * A media type and a MIME type together select the attachments that match either.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_media_type_and_mime_type_together_are_a_union(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$ids = $this->query_ids(
			array(
				'where' => array(
					'ids'        => array( self::$post_ids['unattached'], self::$post_ids['pdf'], self::$post_ids['audio'] ),
					'media_type' => array( 'audio' ),
					'mime_type'  => array( 'application/pdf' ),
				),
			)
		);

		$this->assertSameSets( array( self::$post_ids['pdf'], self::$post_ids['audio'] ), $ids, 'Either filter should select an attachment.' );
	}

	/**
	 * A post filter selects the attachments of that post, and 0 selects unattached ones.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_post_filter_selects_the_attachments_of_a_post(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$this->assertSame( array( self::$post_ids['attached'] ), $this->query_ids( array( 'where' => array( 'post' => self::$post_ids['post'] ) ) ), 'The post filter should select the attachment of the post.' );

		$unattached = $this->query_ids(
			array(
				'where' => array(
					'post' => 0,
					'ids'  => array( self::$post_ids['unattached'], self::$post_ids['attached'] ),
				),
			)
		);
		$this->assertSame( array( self::$post_ids['unattached'] ), $unattached, 'A post filter of 0 should select the unattached attachment.' );
	}

	/**
	 * Returns post filters that list nothing, with the totals they give.
	 *
	 * @return array<string, array{post: string, pagination: array{total: int, total_pages: int}}> The posts, as fixture names, and the totals.
	 */
	public static function data_post_filters_that_select_nothing(): array {
		return array(
			'missing post'             => array(
				'missing',
				array(
					'total'       => 0,
					'total_pages' => 0,
				),
			),
			'post without attachments' => array(
				'page',
				array(
					'total'       => 0,
					'total_pages' => 0,
				),
			),
			'private post'             => array(
				'private_post',
				array(
					'total'       => 1,
					'total_pages' => 1,
				),
			),
			'attachment'               => array(
				'unattached',
				array(
					'total'       => 0,
					'total_pages' => 0,
				),
			),
		);
	}

	/**
	 * A post that does not exist, has no attachments or whose attachments the caller cannot
	 * read lists no attachment and no error, with the given totals.
	 *
	 * @ticket 66719
	 * @dataProvider data_post_filters_that_select_nothing
	 * @since 7.3.0
	 *
	 * @param string                              $post       The post, as a fixture name.
	 * @param array{total: int, total_pages: int} $pagination The expected totals.
	 */
	public function test_post_filter_that_selects_nothing( string $post, array $pagination ): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where'  => array( 'post' => self::$post_ids[ $post ] ?? self::MISSING_ID ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertIsArray( $result, 'The query should succeed.' );
		$this->assertSame( array(), $result['items'], 'No attachment should be listed.' );
		$this->assertSame( $pagination['total'], $result['pagination']['total'] ?? null, "The total should be {$pagination['total']}." );
		$this->assertSame( $pagination['total_pages'], $result['pagination']['total_pages'] ?? null, "The total pages should be {$pagination['total_pages']}." );
	}

	/**
	 * An author filter with an ID matches the stored author, without a lookup of the user.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_author_id_filter(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$ids = array( self::$post_ids['unattached'], self::$post_ids['editor_image'] );

		$this->assertSame(
			array( self::$post_ids['editor_image'] ),
			$this->query_ids(
				array(
					'where' => array(
						'ids'    => $ids,
						'author' => array( 'id' => self::$user_ids['editor'] ),
					),
				)
			),
			'The author ID should select the attachment of that user.'
		);
		$this->assertSame(
			self::EMPTY_RESULT,
			$this->run_ability( array( 'where' => array( 'author' => array( 'id' => self::MISSING_ID ) ) ) ),
			'The ID of no user should match nothing.'
		);
	}

	/**
	 * An author filter with a slug matches the attachments of a user the caller may see.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_author_slug_filter_for_a_visible_user(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$ids = $this->query_ids(
			array(
				'where' => array(
					'ids'    => array( self::$post_ids['unattached'], self::$post_ids['editor_image'] ),
					'author' => array( 'slug' => get_userdata( self::$user_ids['editor'] )->user_nicename ),
				),
			)
		);

		$this->assertSame( array( self::$post_ids['editor_image'] ), $ids, 'The slug of a user with published posts should select that user\'s attachment.' );
	}

	/**
	 * Returns author filters that match nothing for a subscriber.
	 *
	 * @return array<string, array{author: string}> The filters, as cases.
	 */
	public static function data_author_filters_that_match_nothing(): array {
		return array(
			'slug of no user'                       => array( 'slug_of_nobody' ),
			'slug of a user the caller may not see' => array( 'slug_of_invisible_user' ),
			'ID and slug of different users'        => array( 'id_and_other_slug' ),
		);
	}

	/**
	 * A slug of no user or of a user the caller may not see, and an ID and a slug of
	 * different users, match nothing and give zeros.
	 *
	 * @ticket 66719
	 * @dataProvider data_author_filters_that_match_nothing
	 * @since 7.3.0
	 *
	 * @param string $filter The filter, as a case name.
	 */
	public function test_author_filter_that_matches_nothing( string $filter ): void {
		$filters = array(
			'slug_of_nobody'         => array( 'slug' => 'mdt-nobody' ),
			'slug_of_invisible_user' => array( 'slug' => get_userdata( self::$user_ids['author_without_posts'] )->user_nicename ),
			'id_and_other_slug'      => array(
				'id'   => self::$user_ids['editor'],
				'slug' => get_userdata( self::$user_ids['author'] )->user_nicename,
			),
		);

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$this->assertSame( self::EMPTY_RESULT, $this->run_ability( array( 'where' => array( 'author' => $filters[ $filter ] ) ) ), 'The author filter should match nothing.' );
	}

	/**
	 * Filters combine with AND.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_filters_combine_with_and(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$ids = $this->query_ids(
			array(
				'where' => array(
					'ids'        => array( self::$post_ids['unattached'], self::$post_ids['pdf'], self::$post_ids['editor_image'] ),
					'media_type' => array( 'image' ),
					'author'     => array( 'id' => self::$user_ids['author'] ),
				),
			)
		);

		$this->assertSame( array( self::$post_ids['unattached'] ), $ids, 'Only the attachment that matches every filter should be listed.' );
	}

	/**
	 * Items are ordered by date, newest first, then by ID, highest first, also across pages
	 * and when the query holds more than one status.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_order_is_date_then_id_descending(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$expected = array( self::$post_ids['order_c'], self::$post_ids['order_b'], self::$post_ids['order_a'], self::$post_ids['order_d'] );
		$where    = array( 'post' => self::$post_ids['order_parent'] );

		$this->assertSame( $expected, $this->query_ids( array( 'where' => $where ) ), 'The items should be ordered by date, then ID.' );

		$page_1 = $this->query_ids(
			array(
				'where'      => $where,
				'pagination' => array(
					'per_page' => 2,
					'page'     => 1,
				),
			)
		);
		$page_2 = $this->query_ids(
			array(
				'where'      => $where,
				'pagination' => array(
					'per_page' => 2,
					'page'     => 2,
				),
			)
		);

		$this->assertSame( array_slice( $expected, 0, 2 ), $page_1, 'The first page should hold the first two items.' );
		$this->assertSame( array_slice( $expected, 2, 2 ), $page_2, 'The second page should hold the next two items.' );

		$this->login_as( 'editor' );
		$ids = $this->query_ids(
			array(
				'where' => array(
					'status' => array( 'inherit', 'private' ),
					'post'   => self::$post_ids['order_parent'],
				),
			)
		);

		$this->assertSame( $expected, $ids, 'Attachments of the same date should be ordered by ID, highest first, also with more than one status.' );
	}

	/**
	 * Rows of another post type that another callback adds to the rows of the query are
	 * never listed.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_rows_of_another_post_type_are_never_listed(): void {
		$page = get_post( self::$post_ids['page'] );
		add_filter(
			'the_posts',
			static function ( array $posts ) use ( $page ): array {
				$posts[] = $page;
				return $posts;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$ids = $this->query_ids( array( 'where' => array( 'post' => self::$post_ids['order_parent'] ) ) );

		$this->assertNotContains( self::$post_ids['page'], $ids, 'The added page should not be listed.' );
		$this->assertSame( array( 'attachment' ), array_values( array_unique( array_map( 'get_post_type', $ids ) ) ), 'Only attachments should be listed.' );
	}

	/**
	 * A page holds 10 items by default, and 100 when IDs or slugs are sent.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_per_page_defaults(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$all = array();
		for ( $i = 1; $i <= 11; $i++ ) {
			$all[] = self::$post_ids[ "paging_{$i}" ];
		}

		$result = $this->run_ability(
			array(
				'where'  => array( 'post' => self::$post_ids['paging_parent'] ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertCount( 10, $result['items'], 'A page should hold 10 items by default.' );
		$this->assertSame(
			array(
				'total'       => 11,
				'total_pages' => 2,
			),
			$result['pagination'],
			'The pagination should count every match.'
		);

		$this->assertCount( 11, $this->query_ids( array( 'where' => array( 'ids' => $all ) ) ), 'A page should hold up to 100 items when IDs are sent.' );

		$slugs = array_map(
			static function ( int $id ): string {
				return get_post_field( 'post_name', $id );
			},
			$all
		);
		$this->assertCount( 11, $this->query_ids( array( 'where' => array( 'slugs' => $slugs ) ) ), 'A page should hold up to 100 items when slugs are sent.' );
	}

	/**
	 * Returns attachments with the roles that may and may not read them.
	 *
	 * @return array<string, array{attachment: string, role: string, readable: bool}> The cases.
	 */
	public static function data_row_check(): array {
		return array(
			'unattached, subscriber'                   => array( 'unattached', 'subscriber', true ),
			'attached to a published post, subscriber' => array( 'attached', 'subscriber', true ),
			'attached to a published post, no capabilities' => array( 'attached', 'no_caps', true ),
			'attached to a private post, subscriber'   => array( 'private_parent', 'subscriber', false ),
			'attached to a private post, contributor'  => array( 'private_parent', 'contributor', false ),
			'attached to a private post, editing role' => array( 'private_parent', 'other_editor', false ),
			'attached to a private post, editor'       => array( 'private_parent', 'editor', true ),
		);
	}

	/**
	 * The row check lists an attachment only when the caller may read it.
	 *
	 * @ticket 66719
	 * @dataProvider data_row_check
	 * @since 7.3.0
	 *
	 * @param string $attachment The attachment, as a fixture name.
	 * @param string $role       The role.
	 * @param bool   $readable   Whether the caller may read it.
	 */
	public function test_row_check( string $attachment, string $role, bool $readable ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$ids = $this->query_ids( array( 'where' => array( 'ids' => array( self::$post_ids[ $attachment ] ) ) ) );

		$this->assertSame( $readable ? array( self::$post_ids[ $attachment ] ) : array(), $ids, 'The attachment should be listed only when the caller may read it.' );
	}

	/**
	 * A private attachment is listed for its author and for a caller with
	 * `read_private_posts`, and not for another contributor.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_private_attachments_are_listed_for_callers_who_may_read_them(): void {
		$own = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-own-private.jpg',
				'post_status'    => 'private',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => self::$user_ids['contributor'],
			)
		);

		$this->register_abilities();
		$input = array(
			'where'  => array(
				'ids'    => array( $own, self::$post_ids['private'] ),
				'status' => array( 'private' ),
			),
			'fields' => array( 'id' ),
		);

		$this->login_as( 'contributor' );
		$contributor = $this->run_ability( $input );

		$this->assertSame( array( $own ), wp_list_pluck( $contributor['items'], 'id' ), 'The contributor should see only their own private attachment.' );
		$this->assertSame( 1, $contributor['pagination']['total'], 'The total should leave out the private attachment of another user.' );

		$this->login_as( 'editor' );
		$editor = $this->run_ability( $input );

		$this->assertSameSets( array( $own, self::$post_ids['private'] ), wp_list_pluck( $editor['items'], 'id' ), 'The editor should see both private attachments.' );
		$this->assertSame( 2, $editor['pagination']['total'], 'The total should count both private attachments for the editor.' );
	}

	/**
	 * An attachment whose parent no longer exists counts as unattached and is listed.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_attachment_whose_parent_no_longer_exists_is_listed(): void {
		global $wpdb;

		$id = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-orphan.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$wpdb->update( $wpdb->posts, array( 'post_parent' => self::MISSING_ID ), array( 'ID' => $id ) );
		clean_post_cache( $id );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$this->assertSame( array( $id ), $this->query_ids( array( 'where' => array( 'ids' => array( $id ) ) ) ), 'An attachment of a missing parent should be listed.' );
	}

	/**
	 * An attachment whose parent has a public status other than `publish` is listed for a
	 * caller without any capability.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_attachment_of_a_parent_in_a_public_custom_status_is_listed(): void {
		register_post_status(
			'mdt_public_archive',
			array(
				'label'  => 'Public archive',
				'public' => true,
			)
		);

		$parent = self::factory()->post->create(
			array(
				'post_status' => 'mdt_public_archive',
				'post_author' => self::$user_ids['editor'],
			)
		);
		$id     = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-public-archive.jpg',
				'post_parent'    => $parent,
				'post_mime_type' => 'image/jpeg',
				'post_author'    => self::$user_ids['editor'],
			)
		);

		$this->login_as( 'no_caps' );
		$this->register_abilities();

		$ids = $this->query_ids( array( 'where' => array( 'ids' => array( $id ) ) ) );

		$this->assertSame( array( $id ), $ids, 'An attachment of a parent in a public status should be listed.' );
	}

	/**
	 * An attachment of a published post is listed for a caller without any capability only
	 * while the parent's type has `show_in_abilities` on.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_attachment_of_a_published_post_needs_the_parent_type_shown(): void {
		$this->login_as( 'no_caps' );
		$this->register_abilities();

		$input = array( 'where' => array( 'ids' => array( self::$post_ids['attached'] ) ) );

		$this->assertSame( array( self::$post_ids['attached'] ), $this->query_ids( $input ), 'The attachment should be listed while the post type has show_in_abilities on.' );

		get_post_type_object( 'post' )->show_in_abilities = false;

		$this->assertSame( array(), $this->query_ids( $input ), 'The attachment should not be listed while the post type has show_in_abilities off.' );
	}

	/**
	 * Two attachments that are each other's parent end the query without an error and are
	 * not listed.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_attachments_in_a_parent_loop_are_not_listed(): void {
		global $wpdb;

		$first  = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-loop-1.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$second = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-loop-2.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$wpdb->update( $wpdb->posts, array( 'post_parent' => $second ), array( 'ID' => $first ) );
		$wpdb->update( $wpdb->posts, array( 'post_parent' => $first ), array( 'ID' => $second ) );
		clean_post_cache( $first );
		clean_post_cache( $second );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where'  => array( 'ids' => array( $first, $second ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertNotWPError( $result, 'A parent loop should not make the query fail.' );
		$this->assertSame( array(), $result['items'], 'No attachment of the loop should be listed.' );
	}

	/**
	 * An attachment that is its own parent counts as unattached and is listed.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_attachment_that_is_its_own_parent_is_listed(): void {
		global $wpdb;

		$id = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-self-parent.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$wpdb->update( $wpdb->posts, array( 'post_parent' => $id ), array( 'ID' => $id ) );
		clean_post_cache( $id );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$this->assertSame( array( $id ), $this->query_ids( array( 'where' => array( 'ids' => array( $id ) ) ) ), 'An attachment that is its own parent should be listed.' );
	}

	/**
	 * The total counts an attachment the row check withholds, so a page can hold fewer items
	 * than the total.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_total_counts_a_withheld_attachment(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'where'  => array( 'ids' => array( self::$post_ids['private_parent'], self::$post_ids['unattached'] ) ),
				'fields' => array( 'id' ),
			)
		);

		$this->assertSame( array( self::$post_ids['unattached'] ), wp_list_pluck( $result['items'], 'id' ), 'Only the readable attachment should be listed.' );
		$this->assertSame(
			array(
				'total'       => 2,
				'total_pages' => 1,
			),
			$result['pagination'],
			'The total should count the withheld attachment.'
		);
	}

	/**
	 * When nothing matches every page is empty; a page past the last one is an error when
	 * something matches.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_pages_past_the_end(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$this->assertSame(
			self::EMPTY_RESULT,
			$this->run_ability(
				array(
					'where'      => array( 'ids' => array( self::MISSING_ID ) ),
					'pagination' => array( 'page' => 3 ),
				)
			),
			'Every page of a query that matches nothing should be empty.'
		);

		$result = $this->run_ability(
			array(
				'where'      => array( 'ids' => array( self::$post_ids['unattached'] ) ),
				'pagination' => array( 'page' => 2 ),
			)
		);

		$this->assert_error( $result, 'media_invalid_page_number', 404, 'A page past the last one' );
		$this->assert_error_message( $result, 'The page number requested is larger than the number of pages available.', 'A page past the last one' );
	}

	/**
	 * Returns `where` values that a callback receives only when validation is skipped, with
	 * the path of the error.
	 *
	 * @return array<string, array{where: array<string, mixed>, path: string}> The cases.
	 */
	public static function data_unusable_filter_values(): array {
		return array(
			'empty slug list'      => array( array( 'slugs' => array() ), 'where[slugs]' ),
			'slug list of nothing' => array( array( 'slugs' => array( '' ) ), 'where[slugs]' ),
		);
	}

	/**
	 * Returns `where` values outside the input schema.
	 *
	 * @return array<string, array{where: array<string, mixed>}> The cases.
	 */
	public static function data_filter_values_outside_the_schema(): array {
		return array(
			'empty ID list'         => array( array( 'ids' => array() ) ),
			'ID list of zero'       => array( array( 'ids' => array( 0 ) ) ),
			'empty status list'     => array( array( 'status' => array() ) ),
			'empty media type list' => array( array( 'media_type' => array() ) ),
			'empty MIME type list'  => array( array( 'mime_type' => array() ) ),
			'post below zero'       => array( array( 'post' => -1 ) ),
			'empty author'          => array( array( 'author' => array() ) ),
			'author ID of zero'     => array( array( 'author' => array( 'id' => 0 ) ) ),
			'empty author slug'     => array( array( 'author' => array( 'slug' => '' ) ) ),
		);
	}

	/**
	 * A filter value outside the input schema is invalid input.
	 *
	 * @ticket 66719
	 * @dataProvider data_filter_values_outside_the_schema
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $where The filters.
	 */
	public function test_filter_value_outside_the_schema_is_invalid_input( array $where ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( array( 'where' => $where ) );

		$this->assertWPError( $result, 'A filter value outside the schema should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the filter value.' );
	}

	/**
	 * With validation skipped, a filter value that cannot be applied is an invalid filter.
	 *
	 * @ticket 66719
	 * @dataProvider data_unusable_filter_values
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $where The filters.
	 * @param string               $path  The path of the error.
	 */
	public function test_unusable_filter_value_is_an_invalid_filter( array $where, string $path ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->skip_input_validation();

		$this->assert_error_with_params( $this->run_ability( array( 'where' => $where ) ), 'media_invalid_filter', $path, 'An unusable filter value' );
	}

	/**
	 * Returns `fields` values that select the default fields.
	 *
	 * @return array<string, array{fields: list<string>|null}> The values.
	 */
	public static function data_default_field_inputs(): array {
		return array(
			'no fields'    => array( null ),
			'empty fields' => array( array() ),
		);
	}

	/**
	 * Without fields, an item holds the default fields, with only the rendered title; an
	 * empty field list counts as not sent.
	 *
	 * @ticket 66719
	 * @dataProvider data_default_field_inputs
	 * @since 7.3.0
	 *
	 * @param list<string>|null $fields The fields, or null to send none.
	 */
	public function test_default_fields( ?array $fields ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$id   = self::$post_ids['unattached'];
		$item = $this->query_item( $id, $fields );

		$this->assertSameSets( self::DEFAULT_KEYS, array_keys( $item ), 'The item should hold the default fields.' );
		$this->assertSame( array( 'rendered' ), array_keys( $item['title'] ), 'The title should hold only the rendered value.' );
		$this->assertSame( $id, $item['id'], 'The ID should be the attachment ID.' );
		$this->assertSame( 'inherit', $item['status'], 'The status should be the stored one.' );
		$this->assertSame( 'image', $item['media_type'], 'The media type should be image.' );
		$this->assertSame( 'image/jpeg', $item['mime_type'], 'The MIME type should be the stored one.' );
		$this->assertSame( wp_get_attachment_url( $id ), $item['source_url'], 'The source URL should be the attachment URL.' );
	}

	/**
	 * Returns field lists with the keys and sub-keys they select for an editor.
	 *
	 * @return array<string, array{fields: list<string>, expected: array<string, list<string>|null>}> The cases.
	 */
	public static function data_field_selections(): array {
		return array(
			'ID only'             => array( array( 'id' ), array( 'id' => null ) ),
			'title'               => array(
				array( 'title' ),
				array(
					'id'    => null,
					'title' => array( 'raw', 'rendered' ),
				),
			),
			'raw title'           => array(
				array( 'title.raw' ),
				array(
					'id'    => null,
					'title' => array( 'raw' ),
				),
			),
			'dates'               => array(
				array( 'date.gmt', 'modified' ),
				array(
					'id'       => null,
					'date'     => array( 'gmt' ),
					'modified' => array( 'site', 'gmt' ),
				),
			),
			'author slug'         => array(
				array( 'author.slug', 'post' ),
				array(
					'id'     => null,
					'author' => array( 'slug' ),
					'post'   => null,
				),
			),
			'every top-level key' => array(
				array( 'link', 'caption', 'description', 'alt_text', 'filename', 'author' ),
				array(
					'id'          => null,
					'link'        => null,
					'caption'     => array( 'raw', 'rendered' ),
					'description' => array( 'raw', 'rendered' ),
					'alt_text'    => null,
					'filename'    => null,
					'author'      => array( 'id', 'name', 'slug' ),
				),
			),
		);
	}

	/**
	 * `fields` selects exactly the keys and sub-keys asked for, next to the ID.
	 *
	 * @ticket 66719
	 * @dataProvider data_field_selections
	 * @since 7.3.0
	 *
	 * @param list<string>                     $fields   The fields.
	 * @param array<string, list<string>|null> $expected The expected keys, with their sub-keys.
	 */
	public function test_fields_select_exact_keys( array $fields, array $expected ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$item = $this->query_item( self::$post_ids['unattached'], $fields );

		$this->assertSameSets( array_keys( $expected ), array_keys( $item ), 'The item should hold exactly the selected keys.' );
		foreach ( $expected as $key => $sub_keys ) {
			if ( null !== $sub_keys ) {
				$this->assertSameSets( $sub_keys, array_keys( $item[ $key ] ), "The {$key} key should hold exactly the selected sub-keys." );
			}
		}
	}

	/**
	 * Raw values need `edit_post` on the item; an object left without any sub-field is left
	 * out.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_raw_values_need_edit_post(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$id = self::$post_ids['editor_image'];

		$item = $this->query_item( $id, array( 'title', 'caption', 'description' ) );
		$this->assertSame( array( 'rendered' ), array_keys( $item['title'] ), 'The title should hold only the rendered value.' );
		$this->assertSame( array( 'rendered' ), array_keys( $item['caption'] ), 'The caption should hold only the rendered value.' );
		$this->assertSame( array( 'rendered' ), array_keys( $item['description'] ), 'The description should hold only the rendered value.' );

		$this->assertSame( array( 'id' ), array_keys( $this->query_item( $id, array( 'title.raw' ) ) ), 'A title left without a sub-field should be left out.' );
	}

	/**
	 * Rendered values: the title without a status prefix, an empty caption as an excerpt of
	 * the description, and the description through the content filters, with the global post
	 * the same after the call.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_rendered_values(): void {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-rendered.jpg',
				'post_status'    => 'private',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => self::$user_ids['editor'],
				'post_title'     => 'Secret title',
				'post_excerpt'   => '',
				'post_content'   => 'Description words.',
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$GLOBALS['post'] = get_post( self::$post_ids['post'] );
		$global_before   = $GLOBALS['post'];

		$item = $this->query_item( $id, array( 'title.rendered', 'caption.rendered', 'description.rendered' ), array( 'private' ) );

		$this->assertSame( $global_before, $GLOBALS['post'], 'The global post should be the same after the call.' );
		$this->assertSame( 'Secret title', $item['title']['rendered'], 'The rendered title should have no status prefix.' );
		$this->assertStringContainsString( 'Description words.', $item['caption']['rendered'], 'An empty caption should render an excerpt of the description.' );

		$rendered = $item['description']['rendered'];
		$this->assertStringContainsString( '<p class="attachment">', $rendered, 'The rendered description should hold the attachment markup.' );
		$this->assertLessThan( strpos( $rendered, 'Description words.' ), strpos( $rendered, '<p class="attachment">' ), 'The attachment markup should come before the text.' );
	}

	/**
	 * Core's title prefixes work as before after a call that returns rendered titles.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_title_prefixes_work_after_the_call(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$private_before   = has_filter( 'private_title_format' );
		$protected_before = has_filter( 'protected_title_format' );

		$this->query_item( self::$post_ids['private'], array( 'title.rendered' ), array( 'private' ) );

		$this->assertSame( $private_before, has_filter( 'private_title_format' ), 'The private title format filter should have the callbacks it had before.' );
		$this->assertSame( $protected_before, has_filter( 'protected_title_format' ), 'The protected title format filter should have the callbacks it had before.' );
		$this->assertStringStartsWith( 'Private: ', get_the_title( self::$post_ids['private_post'] ), 'The title of a private post should still have the prefix.' );
	}

	/**
	 * Stored values are returned as stored: status, slug, MIME type, link, alt text, parent
	 * and the media type.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_stored_values(): void {
		update_post_meta( self::$post_ids['attached'], '_wp_attachment_image_alt', 'Stored alt' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$fields   = array( 'status', 'slug', 'mime_type', 'link', 'alt_text', 'post', 'media_type' );
		$attached = $this->query_item( self::$post_ids['attached'], $fields );
		$pdf      = $this->query_item( self::$post_ids['pdf'], $fields );

		$this->assertSame( 'inherit', $attached['status'], 'The status should be the stored one.' );
		$this->assertSame( get_post_field( 'post_name', self::$post_ids['attached'] ), $attached['slug'], 'The slug should be the stored one.' );
		$this->assertSame( get_permalink( self::$post_ids['attached'] ), $attached['link'], 'The link should be the permalink.' );
		$this->assertSame( 'Stored alt', $attached['alt_text'], 'The alt text should be the stored one.' );
		$this->assertSame( self::$post_ids['post'], $attached['post'], 'The post should be the parent ID.' );
		$this->assertSame( 'image', $attached['media_type'], 'An image should have the media type image.' );
		$this->assertSame( 'application/pdf', $pdf['mime_type'], 'The MIME type should be the stored one.' );
		$this->assertSame( '', $pdf['alt_text'], 'A missing alt text should be an empty string.' );
		$this->assertSame( 0, $pdf['post'], 'An unattached attachment should have the post 0.' );
		$this->assertSame( 'file', $pdf['media_type'], 'A PDF should have the media type file.' );
	}

	/**
	 * The file name and file size are left out when the attachment has no file, and the
	 * source URL is then the attachment's GUID.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_file_keys_are_left_out_without_a_file(): void {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => '',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$item = $this->query_item( $id, array( 'source_url', 'filename', 'filesize' ) );

		$this->assertSameSets( array( 'id', 'source_url' ), array_keys( $item ), 'The file name and file size should be left out.' );
		$this->assertNotEmpty( get_the_guid( $id ), 'The attachment should have a GUID.' );
		$this->assertSame( get_the_guid( $id ), $item['source_url'], 'The source URL should be the GUID.' );
		$this->assertSame( wp_get_attachment_url( $id ), $item['source_url'], 'The source URL should be the attachment URL.' );
	}

	/**
	 * The source URL is left out when the attachment URL is false.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_source_url_is_left_out_when_the_attachment_url_is_false(): void {
		add_filter( 'wp_get_attachment_url', '__return_empty_string' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assertFalse( wp_get_attachment_url( self::$post_ids['unattached'] ), 'The attachment URL should be false.' );

		$item = $this->query_item( self::$post_ids['unattached'], array( 'source_url' ) );

		$this->assertSame( array( 'id' ), array_keys( $item ), 'The source URL should be left out.' );
	}

	/**
	 * The media details hold only the width, height, length and sizes, as integers, with a
	 * full size next to the sub-sizes; a size that lacks a value is left out.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_media_details_from_the_stored_metadata(): void {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-meta.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		wp_update_attachment_metadata(
			$id,
			array(
				'width'      => '640',
				'height'     => 480,
				'length'     => '12',
				'file'       => '2026/01/mdt-meta.jpg',
				'filesize'   => 1234,
				'sizes'      => array(
					'thumbnail' => array(
						'file'      => 'mdt-meta-150x150.jpg',
						'width'     => 150,
						'height'    => 150,
						'mime-type' => 'image/jpeg',
					),
					'broken'    => array(
						'file'      => 'mdt-meta-broken.jpg',
						'width'     => 10,
						'mime-type' => 'image/jpeg',
					),
				),
				'image_meta' => array( 'title' => 'Not returned' ),
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$details = $this->query_item( $id, array( 'media_details' ) )['media_details'];

		$this->assertSameSets( array( 'width', 'height', 'length', 'sizes' ), array_keys( $details ), 'The media details should hold only the width, height, length and sizes.' );
		$this->assertSame( 640, $details['width'], 'The width should be an integer.' );
		$this->assertSame( 480, $details['height'], 'The height should be an integer.' );
		$this->assertSame( 12, $details['length'], 'The length should be an integer.' );
		$this->assertSameSets( array( 'thumbnail', 'full' ), array_keys( (array) $details['sizes'] ), 'The sizes should hold the complete sub-size and the full size.' );

		$sizes = (array) $details['sizes'];
		$this->assertSame(
			array(
				'width'      => 150,
				'height'     => 150,
				'mime_type'  => 'image/jpeg',
				'source_url' => dirname( wp_get_attachment_url( $id ) ) . '/mdt-meta-150x150.jpg',
			),
			(array) $sizes['thumbnail'],
			'The sub-size should hold its width, height, MIME type and URL.'
		);
		$this->assertSame(
			array(
				'width'      => 640,
				'height'     => 480,
				'mime_type'  => 'image/jpeg',
				'source_url' => wp_get_attachment_url( $id ),
			),
			(array) $sizes['full'],
			'The full size should hold the width, height, MIME type and URL of the file.'
		);
	}

	/**
	 * A stored sub-size without a file or a height is left out, and the full size is still
	 * added when it was the only sub-size, without a PHP warning or notice.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_incomplete_sub_size_is_left_out(): void {
		$no_file = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-meta-no-file.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		wp_update_attachment_metadata(
			$no_file,
			array(
				'width'  => 640,
				'height' => 480,
				'file'   => '2026/01/mdt-meta-no-file.jpg',
				'sizes'  => array(
					'thumbnail' => array(
						'file'      => 'mdt-meta-no-file-150x150.jpg',
						'width'     => 150,
						'height'    => 150,
						'mime-type' => 'image/jpeg',
					),
					'medium'    => array(
						'width'     => 300,
						'height'    => 225,
						'mime-type' => 'image/jpeg',
					),
				),
			)
		);
		$only_broken = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-meta-only-broken.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		wp_update_attachment_metadata(
			$only_broken,
			array(
				'width'  => 640,
				'height' => 480,
				'file'   => '2026/01/mdt-meta-only-broken.jpg',
				'sizes'  => array(
					'thumbnail' => array(
						'file'      => 'mdt-meta-only-broken-150x150.jpg',
						'width'     => 150,
						'mime-type' => 'image/jpeg',
					),
				),
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$no_file_details     = $this->query_item( $no_file, array( 'media_details' ) )['media_details'];
		$only_broken_details = $this->query_item( $only_broken, array( 'media_details' ) )['media_details'];

		$this->assertSameSets( array( 'thumbnail', 'full' ), array_keys( (array) $no_file_details['sizes'] ), 'The sub-size without a file should be left out.' );
		$this->assertSame( array( 'full' ), array_keys( (array) $only_broken_details['sizes'] ), 'The sizes should hold exactly the full size.' );
	}

	/**
	 * For a file that is not an image, every size is left out when the stored full size
	 * lacks its height, and the call succeeds without a PHP warning or notice.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_sizes_of_a_file_whose_full_size_lacks_a_height_are_left_out(): void {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-meta-document.pdf',
				'post_mime_type' => 'application/pdf',
			)
		);
		wp_update_attachment_metadata(
			$id,
			array(
				'filesize' => 7175,
				'sizes'    => array(
					'full'      => array(
						'file'      => 'mdt-meta-document-pdf.jpg',
						'width'     => 1088,
						'mime-type' => 'image/jpeg',
					),
					'thumbnail' => array(
						'file'      => 'mdt-meta-document-pdf-116x150.jpg',
						'width'     => 116,
						'height'    => 150,
						'mime-type' => 'image/jpeg',
					),
				),
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$details = $this->query_item( $id, array( 'media_details' ) )['media_details'];

		$this->assertArrayHasKey( 'sizes', (array) $details, 'The media details should hold the sizes.' );
		$this->assert_empty_object( $details['sizes'], 'Every size should be left out.' );
	}

	/**
	 * The sizes are an empty object without sub-sizes, and the media details are an empty
	 * object without metadata.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_media_details_without_sizes_or_metadata(): void {
		$with_meta = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-no-sizes.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		wp_update_attachment_metadata(
			$with_meta,
			array(
				'width'  => 10,
				'height' => 10,
				'file'   => '2026/01/mdt-no-sizes.jpg',
				'sizes'  => array(),
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$details = $this->query_item( $with_meta, array( 'media_details' ) )['media_details'];
		$this->assert_empty_object( $details['sizes'], 'The sizes should be an empty object without sub-sizes.' );

		$without = $this->query_item( self::$post_ids['unattached'], array( 'media_details' ) );
		$this->assert_empty_object( $without['media_details'], 'The media details should be an empty object without metadata.' );
	}

	/**
	 * Without metadata, the media details asked for only through a sub-path are left out.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_media_details_asked_for_through_a_sub_path_are_left_out_without_metadata(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$item = $this->query_item( self::$post_ids['unattached'], array( 'media_details.sizes' ) );

		$this->assertSame( array( 'id' ), array_keys( $item ), 'The item should hold only the ID.' );
	}

	/**
	 * Dates are returned as a site date and a GMT date.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_dates(): void {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-dates.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_date'      => '2021-03-04 05:06:07',
				'post_date_gmt'  => '2021-03-04 10:06:07',
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$item = $this->query_item( $id, array( 'date' ) );

		$this->assertStringStartsWith( '2021-03-04T05:06:07', $item['date']['site'], 'The site date should be the stored date.' );
		$this->assertStringStartsWith( '2021-03-04T10:06:07', $item['date']['gmt'], 'The GMT date should be the stored GMT date.' );
	}

	/**
	 * Returns the date fields with their columns.
	 *
	 * @return array<string, array{field: string, column: string, gmt_column: string}> The cases.
	 */
	public static function data_date_columns(): array {
		return array(
			'date'     => array( 'date', 'post_date', 'post_date_gmt' ),
			'modified' => array( 'modified', 'post_modified', 'post_modified_gmt' ),
		);
	}

	/**
	 * Without a stored GMT date, the GMT date is derived from the site date.
	 *
	 * @ticket 66719
	 * @dataProvider data_date_columns
	 * @since 7.3.0
	 *
	 * @param string $field      The output field.
	 * @param string $column     The site date column.
	 * @param string $gmt_column The GMT date column.
	 */
	public function test_gmt_date_is_derived_from_the_site_date( string $field, string $column, string $gmt_column ): void {
		global $wpdb;

		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', 2 );

		$id = self::factory()->attachment->create_object(
			array(
				'file'           => "2026/01/mdt-derived-{$field}.jpg",
				'post_mime_type' => 'image/jpeg',
			)
		);
		$wpdb->update(
			$wpdb->posts,
			array(
				$column     => '2021-03-04 05:06:07',
				$gmt_column => '0000-00-00 00:00:00',
			),
			array( 'ID' => $id )
		);
		clean_post_cache( $id );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$item = $this->query_item( $id, array( $field ) );

		$this->assertStringStartsWith( '2021-03-04T05:06:07', $item[ $field ]['site'], 'The site date should be the stored site date.' );
		$this->assertStringStartsWith( '2021-03-04T03:06:07', $item[ $field ]['gmt'], 'The GMT date should be derived from the site date.' );
	}

	/**
	 * Without either stored column, the site and GMT dates are null.
	 *
	 * @ticket 66719
	 * @dataProvider data_date_columns
	 * @since 7.3.0
	 *
	 * @param string $field      The output field.
	 * @param string $column     The site date column.
	 * @param string $gmt_column The GMT date column.
	 */
	public function test_dates_are_null_without_a_stored_date( string $field, string $column, string $gmt_column ): void {
		global $wpdb;

		$id = self::factory()->attachment->create_object(
			array(
				'file'           => "2026/01/mdt-no-{$field}.jpg",
				'post_mime_type' => 'image/jpeg',
			)
		);
		$wpdb->update(
			$wpdb->posts,
			array(
				$column     => '0000-00-00 00:00:00',
				$gmt_column => '0000-00-00 00:00:00',
			),
			array( 'ID' => $id )
		);
		clean_post_cache( $id );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$item = $this->query_item( $id, array( $field ) );

		$this->assertSame(
			array(
				'site' => null,
				'gmt'  => null,
			),
			$item[ $field ],
			'The site and GMT dates should be null without a stored date.'
		);
	}

	/**
	 * Returns viewers and authors, with whether the viewer may see the author.
	 *
	 * @return array<string, array{viewer: string, attachment: string, author: string, visible: bool}> The cases.
	 */
	public static function data_author_visibility(): array {
		return array(
			'subscriber, user with published posts' => array( 'subscriber', 'editor_image', 'editor', true ),
			'subscriber, user without posts'        => array( 'subscriber', 'no_posts_author_image', 'author_without_posts', false ),
			'administrator, user without posts'     => array( 'administrator', 'no_posts_author_image', 'author_without_posts', true ),
			'the user themself'                     => array( 'author_without_posts', 'no_posts_author_image', 'author_without_posts', true ),
		);
	}

	/**
	 * The author's ID is always returned; the name and slug only when the caller may see the
	 * user.
	 *
	 * @ticket 66719
	 * @dataProvider data_author_visibility
	 * @since 7.3.0
	 *
	 * @param string $viewer     The role of the caller.
	 * @param string $attachment The attachment, as a fixture name.
	 * @param string $author     The author, as a fixture name.
	 * @param bool   $visible    Whether the caller may see the author.
	 */
	public function test_author_visibility( string $viewer, string $attachment, string $author, bool $visible ): void {
		$this->login_as( $viewer );
		$this->register_abilities();

		$user     = get_userdata( self::$user_ids[ $author ] );
		$expected = array(
			'id'   => $user->ID,
			'name' => $visible ? $user->display_name : null,
			'slug' => $visible ? $user->user_nicename : null,
		);

		$item = $this->query_item( self::$post_ids[ $attachment ], array( 'author' ) );

		$this->assertSame( $expected, $item['author'], 'The author should be named only when the caller may see the user.' );
	}

	/**
	 * Returns post types the only published post of an author can be in without making the
	 * author visible.
	 *
	 * @return array<string, array{args: array<string, bool>}> The post type arguments.
	 */
	public static function data_post_types_that_do_not_show_the_author(): array {
		return array(
			'public, not exposed to abilities'   => array(
				array(
					'public'            => true,
					'show_in_abilities' => false,
				),
			),
			'exposed to abilities, not viewable' => array(
				array(
					'public'            => false,
					'show_in_abilities' => true,
				),
			),
		);
	}

	/**
	 * A published post in a post type that is not exposed to abilities or not viewable does
	 * not make its author visible.
	 *
	 * @ticket 66719
	 * @dataProvider data_post_types_that_do_not_show_the_author
	 * @since 7.3.0
	 *
	 * @param array<string, bool> $args The post type arguments.
	 */
	public function test_author_of_a_post_type_that_does_not_show_the_author( array $args ): void {
		register_post_type( 'mdt_author_type', $args );

		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		self::factory()->post->create(
			array(
				'post_type'   => 'mdt_author_type',
				'post_status' => 'publish',
				'post_author' => $author,
			)
		);
		$attachment = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-author-type.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => $author,
			)
		);

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$item = $this->query_item( $attachment, array( 'author' ) );

		$this->assertSame(
			array(
				'id'   => $author,
				'name' => null,
				'slug' => null,
			),
			$item['author'],
			'The author should not be named.'
		);
	}

	/**
	 * Whether the author is visible is worked out for each caller, also in the same request.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_author_visibility_is_worked_out_for_each_caller(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		self::factory()->post->create(
			array(
				'post_status' => 'private',
				'post_author' => $author,
			)
		);
		$attachment = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-private-post-author.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => $author,
			)
		);
		$user       = get_userdata( $author );

		$input = array(
			'where'  => array( 'ids' => array( $attachment ) ),
			'fields' => array( 'author' ),
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$editor_result = $this->run_ability( $input );

		$this->login_as( 'subscriber' );
		$subscriber_result = $this->run_ability( $input );

		$this->assertIsArray( $editor_result, 'The query of the editor should succeed.' );
		$this->assertIsArray( $subscriber_result, 'The query of the subscriber should succeed.' );
		$this->assertSame(
			array(
				'id'   => $author,
				'name' => $user->display_name,
				'slug' => $user->user_nicename,
			),
			$editor_result['items'][0]['author'] ?? null,
			'An editor should see the author of a private post.'
		);
		$this->assertSame(
			array(
				'id'   => $author,
				'name' => null,
				'slug' => null,
			),
			$subscriber_result['items'][0]['author'] ?? null,
			'A subscriber should not see the author of a private post only.'
		);
	}

	/**
	 * Returns every role of the matrix tests.
	 *
	 * @return array<string, array{role: string}> The roles.
	 */
	public static function data_all_roles(): array {
		return array(
			'logged out'      => array( 'logged_out' ),
			'subscriber'      => array( 'subscriber' ),
			'contributor'     => array( 'contributor' ),
			'author'          => array( 'author' ),
			'editor'          => array( 'editor' ),
			'administrator'   => array( 'administrator' ),
			'read and upload' => array( 'uploader' ),
		);
	}
}
