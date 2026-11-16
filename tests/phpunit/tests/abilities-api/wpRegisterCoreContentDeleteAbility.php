<?php

declare( strict_types=1 );

/**
 * Tests for the core/content-delete ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Content
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreContentDeleteAbility extends WP_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	private const ABILITY = 'core/content-delete';

	/**
	 * The content abilities, which every test unregisters before and after it runs.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	private const CONTENT_ABILITIES = array( 'core/content-query', 'core/content-create', 'core/content-update', 'core/content-delete' );

	/**
	 * An ID that no post has.
	 *
	 * @since 7.3.0
	 *
	 * @var int
	 */
	private const MISSING_ID = 987654321;

	/**
	 * The roles of the matrix tests.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	private const ROLES = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

	/**
	 * The statuses of the matrix posts. `trash_<status>` is a post trashed from `<status>`.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	private const MATRIX_STATUSES = array( 'draft', 'pending', 'publish', 'future', 'private', 'trash_draft', 'trash_publish' );

	/**
	 * Shared user IDs keyed by role or fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	private static $user_ids = array();

	/**
	 * Shared post IDs keyed by fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	private static $post_ids = array();

	/**
	 * Creates shared users and posts.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		foreach ( self::ROLES as $role ) {
			self::$user_ids[ $role ] = $factory->user->create( array( 'role' => $role ) );
		}
		self::$user_ids['other'] = $factory->user->create( array( 'role' => 'editor' ) );

		// One post per owner and status, for the matrix tests.
		foreach ( array_merge( self::ROLES, array( 'other' ) ) as $owner ) {
			foreach ( self::MATRIX_STATUSES as $status ) {
				$stored_status = str_replace( 'trash_', '', $status );
				$post_id       = $factory->post->create(
					array(
						'post_author' => self::$user_ids[ $owner ],
						'post_status' => $stored_status,
						'post_date'   => 'future' === $stored_status ? '2035-01-01 00:00:00' : '',
					)
				);
				if ( $stored_status !== $status ) {
					wp_trash_post( $post_id );
				}
				self::$post_ids[ "{$owner}:{$status}" ] = $post_id;
			}
		}

		self::$post_ids['admin_post'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_status' => 'publish',
			)
		);

		foreach ( array( 'admin_page', 'front_page', 'posts_page', 'privacy_page' ) as $page ) {
			self::$post_ids[ $page ] = $factory->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => 'page',
					'post_status' => 'publish',
				)
			);
		}

		self::$post_ids['attachment'] = $factory->attachment->create_object(
			'image.jpg',
			self::$post_ids['admin_post'],
			array(
				'post_mime_type' => 'image/jpeg',
				'post_author'    => self::$user_ids['administrator'],
			)
		);

		$revisioned_id = $factory->post->create(
			array(
				'post_author'  => self::$user_ids['administrator'],
				'post_status'  => 'publish',
				'post_content' => 'First version.',
			)
		);
		wp_update_post(
			array(
				'ID'           => $revisioned_id,
				'post_content' => 'Second version.',
			)
		);
		self::$post_ids['revision'] = (int) array_key_first( wp_get_post_revisions( $revisioned_id ) );
		self::$post_ids['autosave'] = (int) _wp_put_post_revision( get_post( $revisioned_id ), true );

		// Posts of core types that are not exposed.
		foreach ( array(
			'reusable_block' => 'wp_block',
			'template'       => 'wp_template',
			'menu_item'      => 'nav_menu_item',
		) as $fixture => $post_type ) {
			self::$post_ids[ $fixture ] = $factory->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => $post_type,
					'post_status' => 'publish',
				)
			);
		}
	}

	/**
	 * Unregisters the content abilities and registers the content category.
	 *
	 * @since 7.3.0
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( self::CONTENT_ABILITIES as $ability_name ) {
			if ( wp_has_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
		}

		if ( ! wp_has_ability_category( 'content' ) ) {
			global $wp_current_filter;
			$wp_current_filter[] = 'wp_abilities_api_categories_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
			wp_register_ability_category(
				'content',
				array(
					'label'       => 'Content',
					'description' => 'Content.',
				)
			);
		}
	}

	/**
	 * Unregisters the content abilities and the category, and resets the post types.
	 *
	 * @since 7.3.0
	 */
	public function tear_down(): void {
		foreach ( self::CONTENT_ABILITIES as $ability_name ) {
			if ( wp_has_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
		}

		if ( wp_has_ability_category( 'content' ) ) {
			wp_unregister_ability_category( 'content' );
		}

		/*
		 * A test may turn off `show_in_abilities` on `post` and `page`, and the next class's
		 * wpSetUpBeforeClass() runs before the next set_up() resets the post types.
		 */
		$this->reset_post_types();

		parent::tear_down();
	}

	/**
	 * Registers the content abilities inside a faked init action.
	 *
	 * @since 7.3.0
	 */
	private function register_abilities(): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		( new WP_Abilities_Content() )->register();
	}

	/**
	 * Registers the content abilities and returns the callbacks of the delete ability.
	 *
	 * The callbacks call private methods, so tests that skip input validation or the
	 * permission check capture them from the registration arguments.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, callable> The permission and execute callbacks, keyed by argument name.
	 */
	private function get_ability_callbacks(): array {
		$callbacks = array();

		add_filter(
			'wp_register_ability_args',
			static function ( array $args, string $name ) use ( &$callbacks ): array {
				if ( self::ABILITY === $name ) {
					$callbacks = array(
						'permission_callback' => $args['permission_callback'],
						'execute_callback'    => $args['execute_callback'],
					);
				}

				return $args;
			},
			10,
			2
		);

		$this->register_abilities();

		$this->assertArrayHasKey( 'execute_callback', $callbacks, 'The core/content-delete ability should be registered with callbacks.' );

		return $callbacks;
	}

	/**
	 * Runs the registered delete ability with the given input.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input The ability input.
	 * @return mixed The ability result.
	 */
	private function run_ability( $input ) {
		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The core/content-delete ability should be registered.' );

		return wp_get_ability( self::ABILITY )->execute( $input );
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
	 * Logs in as the shared user with the given role or fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @param string $role The role or fixture name.
	 */
	private function login_as( string $role ): void {
		wp_set_current_user( self::$user_ids[ $role ] );
	}

	/**
	 * Takes a snapshot of the stored content: the number of posts per type and status,
	 * revisions included, the number of meta rows, and the row and meta of one post.
	 *
	 * @since 7.3.0
	 *
	 * @param int $post_id The post ID.
	 * @return array<string, mixed> The snapshot.
	 */
	private function snapshot( int $post_id ): array {
		global $wpdb;

		return array(
			'posts'     => $wpdb->get_results( "SELECT post_type, post_status, COUNT(*) AS total FROM {$wpdb->posts} GROUP BY post_type, post_status ORDER BY post_type, post_status", ARRAY_A ),
			'meta'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ),
			'post'      => $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A ),
			'post_meta' => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $post_id ), ARRAY_A ),
		);
	}

	/**
	 * Asserts that a result is an error with the given code and status.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $code    The expected error code.
	 * @param int    $status  The expected error status.
	 * @param string $context What the call was, for the assertion messages.
	 */
	private function assert_error( $result, string $code, int $status, string $context ): void {
		$this->assertWPError( $result, "{$context}: the call should fail." );
		$this->assertSame( $code, $result->get_error_code(), "{$context}: the error code should be {$code}." );

		$data = $result->get_error_data();
		$this->assertIsArray( $data, "{$context}: the error should carry data." );
		$this->assertSame( $status, $data['status'] ?? null, "{$context}: the error status should be {$status}." );
	}

	/**
	 * Asserts that a result is the not-found error.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	private function assert_not_found( $result, string $context ): void {
		$this->assert_error( $result, 'content_not_found', 404, $context );
		$this->assertSame( 'The requested content was not found.', $result->get_error_message(), "{$context}: the message should be the not-found message." );
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
	 * Removes the `description` keywords of a schema at every depth.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $schema The schema.
	 * @return array<string, mixed> The schema without descriptions.
	 */
	private function strip_descriptions( array $schema ): array {
		unset( $schema['description'] );
		foreach ( $schema as $key => $value ) {
			if ( is_array( $value ) ) {
				$schema[ $key ] = $this->strip_descriptions( $value );
			}
		}

		return $schema;
	}

	/**
	 * The ability is registered in the content category with its label and annotations.
	 *
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
	 * The ability is not registered when no post type is exposed to abilities.
	 *
	 * @since 7.3.0
	 */
	public function test_is_not_registered_without_exposed_post_types(): void {
		foreach ( array( 'post', 'page' ) as $post_type ) {
			get_post_type_object( $post_type )->show_in_abilities = false;
		}

		$this->register_abilities();

		$this->assertFalse( wp_has_ability( self::ABILITY ), 'The delete ability should not be registered without an exposed post type.' );
	}

	/**
	 * The input schema takes `where`, with the post type and ID, and `permanent`.
	 *
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
	}

	/**
	 * Example 1: trashing a post.
	 *
	 * Covers S12.
	 *
	 * @since 7.3.0
	 */
	public function test_example_trashes_a_post(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assert_trashed(
			$this->run_ability(
				array(
					'where' => array(
						'type' => 'post',
						'id'   => $post_id,
					),
				)
			),
			$post_id,
			'Trashing a post'
		);
	}

	/**
	 * Example 2: deleting a page permanently.
	 *
	 * Covers S12.
	 *
	 * @since 7.3.0
	 */
	public function test_example_deletes_a_page_permanently(): void {
		$post_id = self::$post_ids['admin_page'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assert_deleted(
			$this->run_ability(
				array(
					'where'     => array(
						'type' => 'page',
						'id'   => $post_id,
					),
					'permanent' => true,
				)
			),
			$post_id,
			'Deleting a page permanently'
		);
	}

	/**
	 * Example 3: trashing the post of example 1 again.
	 *
	 * Covers S5 and S12.
	 *
	 * @since 7.3.0
	 */
	public function test_example_rejects_trashing_a_trashed_post(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$input = array(
			'where' => array(
				'type' => 'post',
				'id'   => $post_id,
			),
		);

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
			'The error should be the already-trashed error of the example.'
		);
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * A logged-out user is refused before the ability runs.
	 *
	 * Covers S4 and S5.
	 *
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_a_logged_out_user(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $this->delete_input( $post_id, true ) );

		$this->assertWPError( $result, 'The call should be refused.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'The permission callback should refuse a logged-out user.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns inputs for which the permission callback returns false, even for an administrator.
	 *
	 * @return array<string, array{input: mixed}> The inputs.
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
	 * The permission callback returns false for missing or malformed input.
	 *
	 * @dataProvider data_inputs_refused_by_the_permission_callback
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input The input.
	 */
	public function test_permission_callback_returns_false_for_malformed_input( $input ): void {
		$this->login_as( 'administrator' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertFalse( $permission( $input ), 'The permission callback should return false.' );
	}

	/**
	 * The permission callback is only a coarse gate: it lets a logged-in subscriber through, and
	 * the execute callback decides on the post.
	 *
	 * @since 7.3.0
	 */
	public function test_permission_callback_returns_true_for_a_logged_in_user_and_an_exposed_type(): void {
		$this->login_as( 'subscriber' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertTrue( $permission( $this->delete_input( self::$post_ids['admin_post'] ) ), 'The permission callback should return true.' );
	}

	/**
	 * Returns ID values that are not a whole number of at least 1.
	 *
	 * @return array<string, array{id: mixed}> The IDs.
	 */
	public static function data_ids_that_are_not_whole_numbers_of_at_least_1(): array {
		return array(
			'digits and letters' => array( '123abc' ),
			'fraction'           => array( 1.5 ),
			'zero'               => array( 0 ),
			'negative'           => array( -1 ),
			'true'               => array( true ),
			'null'               => array( null ),
			'list'               => array( array( 1 ) ),
		);
	}

	/**
	 * An ID that is not a whole number of at least 1 fails input validation.
	 *
	 * Covers S2 and S5.
	 *
	 * @dataProvider data_ids_that_are_not_whole_numbers_of_at_least_1
	 *
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
	 * Returns ID values that a direct call does not read as a whole number of at least 1.
	 *
	 * @return array<string, array{id: mixed}> The IDs.
	 */
	public static function data_ids_that_are_not_whole_numbers_in_a_direct_call(): array {
		return array_merge(
			self::data_ids_that_are_not_whole_numbers_of_at_least_1(),
			array(
				'hexadecimal string'     => array( '0x1' ),
				'negative zero'          => array( -0.0 ),
				'number above 2 ** 53'   => array( 9007199254740993 ),
				'string above 2 ** 53'   => array( '9007199254740993' ),
				'exponent above 2 ** 53' => array( '1e16' ),
			)
		);
	}

	/**
	 * Called directly, an ID that is not a whole number of at least 1 is the not-found error and
	 * is never cast, even when it starts with the ID of an existing post.
	 *
	 * Covers S2, S5 and S13 (C3).
	 *
	 * @dataProvider data_ids_that_are_not_whole_numbers_in_a_direct_call
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $id The ID.
	 */
	public function test_direct_call_with_an_id_that_is_not_a_whole_number_is_not_found( $id ): void {
		$post_id = self::$post_ids['admin_post'];
		if ( '123abc' === $id ) {
			$id = "{$post_id}abc";
		} elseif ( 1.5 === $id ) {
			$id = $post_id + 0.5;
		} elseif ( '0x1' === $id ) {
			// The hexadecimal form of the ID of an existing post.
			$id = '0x' . dechex( $post_id );
		}

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );
		$result = $execute(
			array(
				'where'     => array(
					'type' => 'post',
					'id'   => $id,
				),
				'permanent' => true,
			)
		);

		$this->assert_not_found( $result, 'A direct call with an ID that is not a whole number' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Called directly, an ID made of digits is read as that number.
	 *
	 * Covers S2.
	 *
	 * @since 7.3.0
	 */
	public function test_direct_call_reads_an_id_made_of_digits(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type' => 'post',
					'id'   => (string) $post_id,
				),
			)
		);

		$this->assert_trashed( $result, $post_id, 'A direct call with an ID made of digits' );
	}

	/**
	 * Returns posts that cannot be reached as a `post`.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_posts_that_are_not_posts(): array {
		return array(
			'revision'       => array( 'revision' ),
			'attachment'     => array( 'attachment' ),
			'page'           => array( 'admin_page' ),
			'missing'        => array( 'missing' ),
			'reusable block' => array( 'reusable_block' ),
			'template'       => array( 'template' ),
			'menu item'      => array( 'menu_item' ),
			'autosave'       => array( 'autosave' ),
		);
	}

	/**
	 * A revision, an autosave, an attachment, a post of another exposed or non-exposed type and a
	 * missing post are not found as a `post`, even by an administrator and even for a permanent
	 * delete.
	 *
	 * Covers S3, S5 and S13.
	 *
	 * @dataProvider data_posts_that_are_not_posts
	 *
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
	 * Covers S3 and S13.
	 *
	 * @since 7.3.0
	 */
	public function test_direct_call_for_a_type_that_is_not_exposed_is_not_found(): void {
		register_post_type( 'cw_hidden', array( 'public' => true ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'cw_hidden' ) );

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );
		$result = $execute(
			array(
				'where'     => array(
					'type' => 'cw_hidden',
					'id'   => $post_id,
				),
				'permanent' => true,
			)
		);

		$this->assert_not_found( $result, 'A direct call for a type that is not exposed' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * A post type whose `show_in_abilities` is turned off after registration cannot be deleted.
	 *
	 * Covers S3 and S5.
	 *
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

		$this->assertWPError( $result, 'The regular call should fail.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'The permission callback should refuse a type that is no longer exposed.' );
		$this->assert_not_found( $callbacks['execute_callback']( $input ), 'A direct call for a type that is no longer exposed' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns direct-call inputs with a malformed `where`.
	 *
	 * @return array<string, array{input: mixed}> The inputs.
	 */
	public static function data_direct_inputs_with_a_malformed_where(): array {
		return array(
			'no input'               => array( null ),
			'no where'               => array( array( 'permanent' => true ) ),
			'where that is a string' => array( array( 'where' => 'post' ) ),
			'no type'                => array( array( 'where' => array( 'id' => 1 ) ) ),
			'no ID'                  => array( array( 'where' => array( 'type' => 'post' ) ) ),
		);
	}

	/**
	 * Called directly, a malformed `where` is the not-found error.
	 *
	 * Covers S1 and S13.
	 *
	 * @dataProvider data_direct_inputs_with_a_malformed_where
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input The input.
	 */
	public function test_direct_call_with_a_malformed_where_is_not_found( $input ): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( self::$post_ids['admin_post'] );

		$this->assert_not_found( $execute( $input ), 'A direct call with a malformed where' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['admin_post'] ), 'Nothing should be deleted for a malformed where.' );
	}

	/**
	 * The lookup comes before the capability: a user who may delete nothing gets the not-found
	 * error for a missing post, and the 403 error for an existing post.
	 *
	 * Covers O1, S4 and S13.
	 *
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
		$this->assert_cannot_delete( $existing, 'An existing post the user may not delete' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['admin_post'] ), 'The refused post should still exist, unchanged.' );
	}

	/**
	 * Returns the outcome of trashing each matrix post per role.
	 *
	 * @return array<string, array{role: string, owner: string, status: string, expected: string}> The cases.
	 */
	public static function data_trash_matrix(): array {
		return array(
			'administrator, own, draft'           => array( 'administrator', 'administrator', 'draft', 'trashed' ),
			'administrator, own, pending'         => array( 'administrator', 'administrator', 'pending', 'trashed' ),
			'administrator, own, publish'         => array( 'administrator', 'administrator', 'publish', 'trashed' ),
			'administrator, own, future'          => array( 'administrator', 'administrator', 'future', 'trashed' ),
			'administrator, own, private'         => array( 'administrator', 'administrator', 'private', 'trashed' ),
			'administrator, own, trash_draft'     => array( 'administrator', 'administrator', 'trash_draft', 'already_trashed' ),
			'administrator, own, trash_publish'   => array( 'administrator', 'administrator', 'trash_publish', 'already_trashed' ),
			'administrator, other, draft'         => array( 'administrator', 'other', 'draft', 'trashed' ),
			'administrator, other, pending'       => array( 'administrator', 'other', 'pending', 'trashed' ),
			'administrator, other, publish'       => array( 'administrator', 'other', 'publish', 'trashed' ),
			'administrator, other, future'        => array( 'administrator', 'other', 'future', 'trashed' ),
			'administrator, other, private'       => array( 'administrator', 'other', 'private', 'trashed' ),
			'administrator, other, trash_draft'   => array( 'administrator', 'other', 'trash_draft', 'already_trashed' ),
			'administrator, other, trash_publish' => array( 'administrator', 'other', 'trash_publish', 'already_trashed' ),
			'editor, own, draft'                  => array( 'editor', 'editor', 'draft', 'trashed' ),
			'editor, own, pending'                => array( 'editor', 'editor', 'pending', 'trashed' ),
			'editor, own, publish'                => array( 'editor', 'editor', 'publish', 'trashed' ),
			'editor, own, future'                 => array( 'editor', 'editor', 'future', 'trashed' ),
			'editor, own, private'                => array( 'editor', 'editor', 'private', 'trashed' ),
			'editor, own, trash_draft'            => array( 'editor', 'editor', 'trash_draft', 'already_trashed' ),
			'editor, own, trash_publish'          => array( 'editor', 'editor', 'trash_publish', 'already_trashed' ),
			'editor, other, draft'                => array( 'editor', 'other', 'draft', 'trashed' ),
			'editor, other, pending'              => array( 'editor', 'other', 'pending', 'trashed' ),
			'editor, other, publish'              => array( 'editor', 'other', 'publish', 'trashed' ),
			'editor, other, future'               => array( 'editor', 'other', 'future', 'trashed' ),
			'editor, other, private'              => array( 'editor', 'other', 'private', 'trashed' ),
			'editor, other, trash_draft'          => array( 'editor', 'other', 'trash_draft', 'already_trashed' ),
			'editor, other, trash_publish'        => array( 'editor', 'other', 'trash_publish', 'already_trashed' ),
			'author, own, draft'                  => array( 'author', 'author', 'draft', 'trashed' ),
			'author, own, pending'                => array( 'author', 'author', 'pending', 'trashed' ),
			'author, own, publish'                => array( 'author', 'author', 'publish', 'trashed' ),
			'author, own, future'                 => array( 'author', 'author', 'future', 'trashed' ),
			'author, own, private'                => array( 'author', 'author', 'private', 'trashed' ),
			'author, own, trash_draft'            => array( 'author', 'author', 'trash_draft', 'already_trashed' ),
			'author, own, trash_publish'          => array( 'author', 'author', 'trash_publish', 'already_trashed' ),
			'author, other, draft'                => array( 'author', 'other', 'draft', 'forbidden' ),
			'author, other, pending'              => array( 'author', 'other', 'pending', 'forbidden' ),
			'author, other, publish'              => array( 'author', 'other', 'publish', 'forbidden' ),
			'author, other, future'               => array( 'author', 'other', 'future', 'forbidden' ),
			'author, other, private'              => array( 'author', 'other', 'private', 'forbidden' ),
			'author, other, trash_draft'          => array( 'author', 'other', 'trash_draft', 'forbidden' ),
			'author, other, trash_publish'        => array( 'author', 'other', 'trash_publish', 'forbidden' ),
			'contributor, own, draft'             => array( 'contributor', 'contributor', 'draft', 'trashed' ),
			'contributor, own, pending'           => array( 'contributor', 'contributor', 'pending', 'trashed' ),
			'contributor, own, publish'           => array( 'contributor', 'contributor', 'publish', 'forbidden' ),
			'contributor, own, future'            => array( 'contributor', 'contributor', 'future', 'forbidden' ),
			'contributor, own, private'           => array( 'contributor', 'contributor', 'private', 'trashed' ),
			'contributor, own, trash_draft'       => array( 'contributor', 'contributor', 'trash_draft', 'already_trashed' ),
			'contributor, own, trash_publish'     => array( 'contributor', 'contributor', 'trash_publish', 'forbidden' ),
			'contributor, other, draft'           => array( 'contributor', 'other', 'draft', 'forbidden' ),
			'contributor, other, pending'         => array( 'contributor', 'other', 'pending', 'forbidden' ),
			'contributor, other, publish'         => array( 'contributor', 'other', 'publish', 'forbidden' ),
			'contributor, other, future'          => array( 'contributor', 'other', 'future', 'forbidden' ),
			'contributor, other, private'         => array( 'contributor', 'other', 'private', 'forbidden' ),
			'contributor, other, trash_draft'     => array( 'contributor', 'other', 'trash_draft', 'forbidden' ),
			'contributor, other, trash_publish'   => array( 'contributor', 'other', 'trash_publish', 'forbidden' ),
			'subscriber, own, draft'              => array( 'subscriber', 'subscriber', 'draft', 'forbidden' ),
			'subscriber, own, pending'            => array( 'subscriber', 'subscriber', 'pending', 'forbidden' ),
			'subscriber, own, publish'            => array( 'subscriber', 'subscriber', 'publish', 'forbidden' ),
			'subscriber, own, future'             => array( 'subscriber', 'subscriber', 'future', 'forbidden' ),
			'subscriber, own, private'            => array( 'subscriber', 'subscriber', 'private', 'forbidden' ),
			'subscriber, own, trash_draft'        => array( 'subscriber', 'subscriber', 'trash_draft', 'forbidden' ),
			'subscriber, own, trash_publish'      => array( 'subscriber', 'subscriber', 'trash_publish', 'forbidden' ),
			'subscriber, other, draft'            => array( 'subscriber', 'other', 'draft', 'forbidden' ),
			'subscriber, other, pending'          => array( 'subscriber', 'other', 'pending', 'forbidden' ),
			'subscriber, other, publish'          => array( 'subscriber', 'other', 'publish', 'forbidden' ),
			'subscriber, other, future'           => array( 'subscriber', 'other', 'future', 'forbidden' ),
			'subscriber, other, private'          => array( 'subscriber', 'other', 'private', 'forbidden' ),
			'subscriber, other, trash_draft'      => array( 'subscriber', 'other', 'trash_draft', 'forbidden' ),
			'subscriber, other, trash_publish'    => array( 'subscriber', 'other', 'trash_publish', 'forbidden' ),
		);
	}

	/**
	 * Each role may trash the posts it may delete, gets the 410 error for those already in the
	 * Trash, and the 403 error for the others, whatever their status.
	 *
	 * Covers S4, S5, S12 and S13.
	 *
	 * @dataProvider data_trash_matrix
	 *
	 * @since 7.3.0
	 *
	 * @param string $role     The role to delete as.
	 * @param string $owner    The role of the author of the post, or `other`.
	 * @param string $status   The matrix status of the post.
	 * @param string $expected `trashed`, `already_trashed` or `forbidden`.
	 */
	public function test_trash_matrix( string $role, string $owner, string $status, string $expected ): void {
		$post_id = self::$post_ids[ "{$owner}:{$status}" ];

		$this->login_as( $role );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $this->delete_input( $post_id ) );

		if ( 'trashed' === $expected ) {
			$this->assert_trashed( $result, $post_id, 'Trashing a post the role may delete' );
			return;
		}

		if ( 'already_trashed' === $expected ) {
			$this->assert_error( $result, 'content_already_trashed', 410, 'Trashing a trashed post the role may delete' );
		} else {
			$this->assert_cannot_delete( $result, 'Trashing a post the role may not delete' );
		}
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns the outcome of deleting each matrix post permanently per role.
	 *
	 * @return array<string, array{role: string, owner: string, status: string, expected: string}> The cases.
	 */
	public static function data_permanent_delete_matrix(): array {
		return array(
			'administrator, own, draft'           => array( 'administrator', 'administrator', 'draft', 'deleted' ),
			'administrator, own, pending'         => array( 'administrator', 'administrator', 'pending', 'deleted' ),
			'administrator, own, publish'         => array( 'administrator', 'administrator', 'publish', 'deleted' ),
			'administrator, own, future'          => array( 'administrator', 'administrator', 'future', 'deleted' ),
			'administrator, own, private'         => array( 'administrator', 'administrator', 'private', 'deleted' ),
			'administrator, own, trash_draft'     => array( 'administrator', 'administrator', 'trash_draft', 'deleted' ),
			'administrator, own, trash_publish'   => array( 'administrator', 'administrator', 'trash_publish', 'deleted' ),
			'administrator, other, draft'         => array( 'administrator', 'other', 'draft', 'deleted' ),
			'administrator, other, pending'       => array( 'administrator', 'other', 'pending', 'deleted' ),
			'administrator, other, publish'       => array( 'administrator', 'other', 'publish', 'deleted' ),
			'administrator, other, future'        => array( 'administrator', 'other', 'future', 'deleted' ),
			'administrator, other, private'       => array( 'administrator', 'other', 'private', 'deleted' ),
			'administrator, other, trash_draft'   => array( 'administrator', 'other', 'trash_draft', 'deleted' ),
			'administrator, other, trash_publish' => array( 'administrator', 'other', 'trash_publish', 'deleted' ),
			'editor, own, draft'                  => array( 'editor', 'editor', 'draft', 'deleted' ),
			'editor, own, pending'                => array( 'editor', 'editor', 'pending', 'deleted' ),
			'editor, own, publish'                => array( 'editor', 'editor', 'publish', 'deleted' ),
			'editor, own, future'                 => array( 'editor', 'editor', 'future', 'deleted' ),
			'editor, own, private'                => array( 'editor', 'editor', 'private', 'deleted' ),
			'editor, own, trash_draft'            => array( 'editor', 'editor', 'trash_draft', 'deleted' ),
			'editor, own, trash_publish'          => array( 'editor', 'editor', 'trash_publish', 'deleted' ),
			'editor, other, draft'                => array( 'editor', 'other', 'draft', 'deleted' ),
			'editor, other, pending'              => array( 'editor', 'other', 'pending', 'deleted' ),
			'editor, other, publish'              => array( 'editor', 'other', 'publish', 'deleted' ),
			'editor, other, future'               => array( 'editor', 'other', 'future', 'deleted' ),
			'editor, other, private'              => array( 'editor', 'other', 'private', 'deleted' ),
			'editor, other, trash_draft'          => array( 'editor', 'other', 'trash_draft', 'deleted' ),
			'editor, other, trash_publish'        => array( 'editor', 'other', 'trash_publish', 'deleted' ),
			'author, own, draft'                  => array( 'author', 'author', 'draft', 'deleted' ),
			'author, own, pending'                => array( 'author', 'author', 'pending', 'deleted' ),
			'author, own, publish'                => array( 'author', 'author', 'publish', 'deleted' ),
			'author, own, future'                 => array( 'author', 'author', 'future', 'deleted' ),
			'author, own, private'                => array( 'author', 'author', 'private', 'deleted' ),
			'author, own, trash_draft'            => array( 'author', 'author', 'trash_draft', 'deleted' ),
			'author, own, trash_publish'          => array( 'author', 'author', 'trash_publish', 'deleted' ),
			'author, other, draft'                => array( 'author', 'other', 'draft', 'forbidden' ),
			'author, other, pending'              => array( 'author', 'other', 'pending', 'forbidden' ),
			'author, other, publish'              => array( 'author', 'other', 'publish', 'forbidden' ),
			'author, other, future'               => array( 'author', 'other', 'future', 'forbidden' ),
			'author, other, private'              => array( 'author', 'other', 'private', 'forbidden' ),
			'author, other, trash_draft'          => array( 'author', 'other', 'trash_draft', 'forbidden' ),
			'author, other, trash_publish'        => array( 'author', 'other', 'trash_publish', 'forbidden' ),
			'contributor, own, draft'             => array( 'contributor', 'contributor', 'draft', 'deleted' ),
			'contributor, own, pending'           => array( 'contributor', 'contributor', 'pending', 'deleted' ),
			'contributor, own, publish'           => array( 'contributor', 'contributor', 'publish', 'forbidden' ),
			'contributor, own, future'            => array( 'contributor', 'contributor', 'future', 'forbidden' ),
			'contributor, own, private'           => array( 'contributor', 'contributor', 'private', 'deleted' ),
			'contributor, own, trash_draft'       => array( 'contributor', 'contributor', 'trash_draft', 'deleted' ),
			'contributor, own, trash_publish'     => array( 'contributor', 'contributor', 'trash_publish', 'forbidden' ),
			'contributor, other, draft'           => array( 'contributor', 'other', 'draft', 'forbidden' ),
			'contributor, other, pending'         => array( 'contributor', 'other', 'pending', 'forbidden' ),
			'contributor, other, publish'         => array( 'contributor', 'other', 'publish', 'forbidden' ),
			'contributor, other, future'          => array( 'contributor', 'other', 'future', 'forbidden' ),
			'contributor, other, private'         => array( 'contributor', 'other', 'private', 'forbidden' ),
			'contributor, other, trash_draft'     => array( 'contributor', 'other', 'trash_draft', 'forbidden' ),
			'contributor, other, trash_publish'   => array( 'contributor', 'other', 'trash_publish', 'forbidden' ),
			'subscriber, own, draft'              => array( 'subscriber', 'subscriber', 'draft', 'forbidden' ),
			'subscriber, own, pending'            => array( 'subscriber', 'subscriber', 'pending', 'forbidden' ),
			'subscriber, own, publish'            => array( 'subscriber', 'subscriber', 'publish', 'forbidden' ),
			'subscriber, own, future'             => array( 'subscriber', 'subscriber', 'future', 'forbidden' ),
			'subscriber, own, private'            => array( 'subscriber', 'subscriber', 'private', 'forbidden' ),
			'subscriber, own, trash_draft'        => array( 'subscriber', 'subscriber', 'trash_draft', 'forbidden' ),
			'subscriber, own, trash_publish'      => array( 'subscriber', 'subscriber', 'trash_publish', 'forbidden' ),
			'subscriber, other, draft'            => array( 'subscriber', 'other', 'draft', 'forbidden' ),
			'subscriber, other, pending'          => array( 'subscriber', 'other', 'pending', 'forbidden' ),
			'subscriber, other, publish'          => array( 'subscriber', 'other', 'publish', 'forbidden' ),
			'subscriber, other, future'           => array( 'subscriber', 'other', 'future', 'forbidden' ),
			'subscriber, other, private'          => array( 'subscriber', 'other', 'private', 'forbidden' ),
			'subscriber, other, trash_draft'      => array( 'subscriber', 'other', 'trash_draft', 'forbidden' ),
			'subscriber, other, trash_publish'    => array( 'subscriber', 'other', 'trash_publish', 'forbidden' ),
		);
	}

	/**
	 * Each role may delete permanently the posts it may delete, including posts in the Trash, and
	 * gets the 403 error for the others, whatever their status.
	 *
	 * Covers S4, S5, S12 and S13.
	 *
	 * @dataProvider data_permanent_delete_matrix
	 *
	 * @since 7.3.0
	 *
	 * @param string $role     The role to delete as.
	 * @param string $owner    The role of the author of the post, or `other`.
	 * @param string $status   The matrix status of the post.
	 * @param string $expected `deleted` or `forbidden`.
	 */
	public function test_permanent_delete_matrix( string $role, string $owner, string $status, string $expected ): void {
		$post_id = self::$post_ids[ "{$owner}:{$status}" ];

		$this->login_as( $role );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $this->delete_input( $post_id, true ) );

		if ( 'deleted' === $expected ) {
			$this->assert_deleted( $result, $post_id, 'Deleting a post the role may delete' );
			return;
		}

		$this->assert_cannot_delete( $result, 'Deleting a post the role may not delete' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * `permanent` set to false moves the post to the Trash.
	 *
	 * Covers S12.
	 *
	 * @since 7.3.0
	 */
	public function test_permanent_false_trashes_the_post(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assert_trashed( $this->run_ability( $this->delete_input( $post_id, false ) ), $post_id, 'Deleting with permanent false' );
	}

	/**
	 * Returns `permanent` values that are not boolean true.
	 *
	 * @return array<string, array{permanent: mixed}> The values.
	 */
	public static function data_permanent_values_that_are_not_true(): array {
		return array(
			'string true'  => array( 'true' ),
			'one'          => array( 1 ),
			'string one'   => array( '1' ),
			'string yes'   => array( 'yes' ),
			'false'        => array( false ),
			'string false' => array( 'false' ),
			'list'         => array( array( true ) ),
			'whole float'  => array( 1.0 ),
			'upper case'   => array( 'TRUE' ),
		);
	}

	/**
	 * Called directly, `permanent` counts only when it is boolean true: anything else moves the
	 * post to the Trash.
	 *
	 * Covers S12.
	 *
	 * @dataProvider data_permanent_values_that_are_not_true
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $permanent The value.
	 */
	public function test_direct_call_deletes_permanently_only_for_boolean_true( $permanent ): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where'     => array(
					'type' => 'post',
					'id'   => $post_id,
				),
				'permanent' => $permanent,
			)
		);

		$this->assert_trashed( $result, $post_id, 'A direct call with a permanent value that is not true' );
	}

	/**
	 * Called directly, `permanent` set to boolean true deletes the post permanently.
	 *
	 * Covers S12.
	 *
	 * @since 7.3.0
	 */
	public function test_direct_call_deletes_permanently_for_boolean_true(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$this->assert_deleted( $execute( $this->delete_input( $post_id, true ) ), $post_id, 'A direct call with permanent true' );
	}

	/**
	 * Returns the special pages of the site.
	 *
	 * @return array<string, array{page: string}> The pages, as fixture names.
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
	 * Covers S4, S5 and S13.
	 *
	 * @dataProvider data_special_pages
	 *
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

		$this->assert_cannot_delete( $trash, 'An editor trashing a special page' );
		$this->assert_cannot_delete( $permanent, 'An editor deleting a special page' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * On a single site, an administrator may delete the special pages.
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_special_pages
	 *
	 * @since 7.3.0
	 *
	 * @param string $page The page, as a fixture name.
	 */
	public function test_administrator_can_delete_a_special_page( string $page ): void {
		$this->set_special_pages();
		$post_id = self::$post_ids[ $page ];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assert_trashed( $this->run_ability( $this->delete_input( $post_id ) ), $post_id, 'An administrator trashing a special page' );
		$this->assert_deleted( $this->run_ability( $this->delete_input( $post_id, true ) ), $post_id, 'An administrator deleting a special page' );
	}

	/**
	 * Returns the special pages of the site and whether a site administrator may delete them on
	 * multisite.
	 *
	 * @return array<string, array{page: string, allowed: bool}> The cases.
	 */
	public static function data_special_pages_for_a_site_administrator(): array {
		return array(
			'front page'          => array( 'front_page', true ),
			'posts page'          => array( 'posts_page', true ),
			'privacy policy page' => array( 'privacy_page', false ),
		);
	}

	/**
	 * On multisite, a site administrator may delete the front page and the posts page, but not
	 * the privacy policy page.
	 *
	 * Covers S4 and S5.
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_special_pages_for_a_site_administrator
	 *
	 * @since 7.3.0
	 *
	 * @param string $page    The page, as a fixture name.
	 * @param bool   $allowed Whether the site administrator may delete it.
	 */
	public function test_site_administrator_deletes_special_pages_on_multisite( string $page, bool $allowed ): void {
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

		$this->assert_cannot_delete( $result, 'A site administrator trashing the privacy policy page' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * On multisite, a super admin may delete the special pages.
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_special_pages
	 *
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
	 * @return array<string, array{permanent: bool, filter: string, value: mixed}> The cases.
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
	 * Covers S5 and S12.
	 *
	 * @dataProvider data_operations_that_are_stopped
	 *
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

		$this->assert_error( $result, 'content_cannot_delete', 500, 'An operation that does not act' );
		$this->assertSame( 'The post cannot be deleted.', $result->get_error_message(), 'The message should be the failure message.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
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
	 * A key outside the allow-list fails input validation.
	 *
	 * Covers S1 and S5.
	 *
	 * @dataProvider data_keys_outside_the_allow_list
	 *
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
	 * Called directly, keys outside the allow-list are ignored: they neither make the delete
	 * permanent nor reach another post.
	 *
	 * Covers S6 and S12.
	 *
	 * @since 7.3.0
	 */
	public function test_direct_call_ignores_keys_outside_the_allow_list(): void {
		$post_id  = self::$post_ids['admin_post'];
		$other_id = self::$post_ids['administrator:publish'];

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$other_before = $this->snapshot( $other_id )['post'];

		$result = $execute(
			array(
				'where' => array(
					'type' => 'post',
					'id'   => $post_id,
					'ID'   => $other_id,
				),
				'force' => true,
				'ID'    => $other_id,
			)
		);

		$this->assert_trashed( $result, $post_id, 'A direct call with unknown keys' );
		$this->assertSame( $other_before, $this->snapshot( $other_id )['post'], 'The post named by ID should not change.' );
	}

	/**
	 * The current user is checked at each call, also on the same instance.
	 *
	 * Covers S4.
	 *
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

	/**
	 * Returns the forms of a whole number that are read as the integer they equal.
	 *
	 * @return array<string, array{form: string}> The forms.
	 */
	public static function data_whole_number_forms(): array {
		return array(
			'whole float'                => array( 'float' ),
			'string with .0'             => array( 'decimal' ),
			'string with plus'           => array( 'plus' ),
			'string with leading space'  => array( 'leading space' ),
			'string with trailing space' => array( 'trailing space' ),
			'string with leading line'   => array( 'leading newline' ),
			'string with an exponent'    => array( 'exponent' ),
		);
	}

	/**
	 * Called directly, an ID in another whole-number form is read as the integer it equals.
	 *
	 * Covers S2 (C3).
	 *
	 * @dataProvider data_whole_number_forms
	 *
	 * @since 7.3.0
	 *
	 * @param string $form The form of the ID.
	 */
	public function test_direct_call_reads_an_id_in_a_whole_number_form( string $form ): void {
		$post_id = self::$post_ids['admin_post'];
		$forms   = array(
			'float'           => (float) $post_id,
			'decimal'         => "{$post_id}.0",
			'plus'            => "+{$post_id}",
			'leading space'   => " {$post_id}",
			'trailing space'  => "{$post_id} ",
			'leading newline' => "\n{$post_id}",
			'exponent'        => "{$post_id}e0",
		);

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$result = $execute(
			array(
				'where' => array(
					'type' => 'post',
					'id'   => $forms[ $form ],
				),
			)
		);

		$this->assert_trashed( $result, $post_id, 'A direct call with an ID in a whole-number form' );
	}
}
