<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-content-write-ability.php';

/**
 * Base test case for the abilities that write an existing post named by `where`:
 * core/content-update and core/content-delete.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.3.0
 */
abstract class WP_Abilities_Content_Existing_Post_UnitTestCase extends WP_Abilities_Content_Write_UnitTestCase {

	/**
	 * The input that each test class sends next to `where`.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, mixed>
	 */
	protected const OTHER_INPUT = array();

	/**
	 * The statuses of the matrix posts. `trash_<status>` is a post trashed from `<status>`.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const MATRIX_STATUSES = array( 'draft', 'pending', 'publish', 'future', 'private', 'trash_draft', 'trash_publish' );

	/**
	 * Creates the users and posts shared by the tests that write an existing post: a user per
	 * role and an `other` editor, one post per owner and matrix status, the posts that
	 * data_posts_that_are_not_posts() names, and the pages that data_special_pages() names.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	protected static function create_shared_fixtures( WP_UnitTest_Factory $factory ): void {
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
						'post_author'   => self::$user_ids[ $owner ],
						'post_status'   => $stored_status,
						'post_title'    => "Matrix {$owner} {$status}",
						'post_date_gmt' => 'future' === $stored_status ? str_replace( 'T', ' ', self::FUTURE_GMT ) : '',
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
				'post_title'  => 'Launch notes',
				'post_name'   => 'shared-slug',
			)
		);

		// A plain page, and the pages that set_special_pages() makes special.
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
	 * Builds the input that names a post with the given `where`.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $where The `where`.
	 * @return array<string, mixed> The input.
	 */
	protected function build_input( $where ): array {
		return array_merge( array( 'where' => $where ), static::OTHER_INPUT );
	}

	/**
	 * Builds the `where` that names a post by its type and ID.
	 *
	 * @since 7.3.0
	 *
	 * @param int         $post_id The post ID.
	 * @param string|null $type    Optional. The post type, or null for the type of the post. Default null.
	 * @return array{type: string|false, id: int} The `where`.
	 */
	protected function build_where( int $post_id, ?string $type = null ): array {
		return array(
			'type' => $type ?? get_post_type( $post_id ),
			'id'   => $post_id,
		);
	}

	/**
	 * Builds the input that names a post by its type and ID.
	 *
	 * @since 7.3.0
	 *
	 * @param int         $post_id The post ID.
	 * @param string|null $type    Optional. The post type, or null for the type of the post. Default null.
	 * @return array<string, mixed> The input.
	 */
	protected function build_post_input( int $post_id, ?string $type = null ): array {
		return $this->build_input( $this->build_where( $post_id, $type ) );
	}

	/**
	 * Asserts that input validation rejects an input of an administrator and that nothing is
	 * written to the given post.
	 *
	 * Registers the content abilities unless the test already did.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $input   The ability input.
	 * @param int    $post_id The ID of the post that should be left as it was.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_invalid_input( $input, int $post_id, string $context ): void {
		$this->login_as( 'administrator' );
		if ( ! wp_has_ability( static::ABILITY ) ) {
			$this->register_abilities();
		}

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability( $input );

		$this->assertWPError( $result, "{$context}: the call should fail." );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), "{$context}: the schema should reject the input." );
		$this->assertSame( $before, $this->snapshot( $post_id ), "{$context}: nothing should be written." );
	}

	/**
	 * Returns whether each role may edit and delete each matrix post.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: bool}> The cases.
	 */
	public static function data_capability_matrix(): array {
		$denied = array(
			'author:other'      => true,
			'contributor:other' => true,
			'subscriber:own'    => true,
			'subscriber:other'  => true,
		);

		// A contributor may only write their own posts that were never published.
		$denied_to_own_contributor = array( 'publish', 'future', 'trash_publish' );

		$cases = array();
		foreach ( self::ROLES as $role ) {
			foreach ( array( 'own', 'other' ) as $owner ) {
				foreach ( self::MATRIX_STATUSES as $status ) {
					$allowed = ! isset( $denied[ "{$role}:{$owner}" ] )
						&& ! ( 'contributor' === $role && 'own' === $owner && in_array( $status, $denied_to_own_contributor, true ) );

					$cases[ "{$role}, {$owner}, {$status}" ] = array( $role, 'own' === $owner ? $role : 'other', $status, $allowed );
				}
			}
		}

		return $cases;
	}

	/**
	 * Returns inputs for which the permission callback returns false, even for an administrator.
	 *
	 * Each input but the missing one holds the OTHER_INPUT of the test class.
	 *
	 * @return array<string, array{0: mixed}> The inputs.
	 */
	public static function data_inputs_refused_by_the_permission_callback(): array {
		$cases = array(
			'no input' => array( null ),
			'no where' => array( static::OTHER_INPUT ),
		);

		$wheres = array(
			'where that is a string' => 'post',
			'type that is a list'    => array(
				'type' => array( 'post' ),
				'id'   => 1,
			),
			'unexposed type'         => array(
				'type' => 'attachment',
				'id'   => 1,
			),
		);
		foreach ( $wheres as $label => $where ) {
			$cases[ $label ] = array( array_merge( array( 'where' => $where ), static::OTHER_INPUT ) );
		}

		return $cases;
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
	 * Makes the shared pages the front page, the posts page and the privacy policy page of the site.
	 *
	 * @since 7.3.0
	 */
	protected function set_special_pages(): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$post_ids['front_page'] );
		update_option( 'page_for_posts', self::$post_ids['posts_page'] );
		update_option( 'wp_page_for_privacy_policy', self::$post_ids['privacy_page'] );
	}

	/**
	 * Returns the forms a `where` can take in input that was not prepared.
	 *
	 * @return array<string, array{0: string, 1: bool}> The type of the ID, and whether `where` is
	 *                                                  an object.
	 */
	public static function data_unprepared_where_forms(): array {
		return array(
			'integer ID'              => array( 'integer', false ),
			'integer string ID'       => array( 'string', false ),
			'float ID'                => array( 'float', false ),
			'where that is an object' => array( 'integer', true ),
		);
	}

	/**
	 * Builds a `where` for a post in one of the forms of unprepared input.
	 *
	 * @since 7.3.0
	 *
	 * @param int    $post_id   The post ID.
	 * @param string $id_type   The type to send the ID as.
	 * @param bool   $as_object Whether to send `where` as an object.
	 * @return array<string, mixed>|stdClass The `where`.
	 */
	protected function build_unprepared_where( int $post_id, string $id_type, bool $as_object ) {
		$id = $post_id;
		settype( $id, $id_type );

		$where = array(
			'type' => get_post_type( $post_id ),
			'id'   => $id,
		);

		return $as_object ? (object) $where : $where;
	}

	/**
	 * Returns posts that cannot be reached as a `post`.
	 *
	 * @return array<string, array{0: string}> The posts, as fixture names.
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
	 * Returns ID values that are not a whole number of at least 1.
	 *
	 * @return array<string, array{0: mixed}> The IDs.
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
			'empty string'       => array( '' ),
		);
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

		$this->assert_refused( $this->run_ability( $this->build_post_input( $post_id ) ), 'A logged-out user' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * The permission callback returns false for a post the user cannot write, in every form the
	 * post can be named in input that was not prepared.
	 *
	 * @ticket 66706
	 * @dataProvider data_unprepared_where_forms
	 * @since 7.3.0
	 *
	 * @param string $id_type   The type to send the ID as.
	 * @param bool   $as_object Whether to send `where` as an object.
	 */
	public function test_permission_callback_returns_false_for_a_post_the_user_cannot_write( string $id_type, bool $as_object ): void {
		$this->login_as( 'contributor' );

		$permission = $this->get_ability_callbacks()['permission_callback'];
		$where      = $this->build_unprepared_where( self::$post_ids['admin_post'], $id_type, $as_object );

		$this->assertFalse( $permission( $this->build_input( $where ) ), 'The permission callback should return false.' );
	}

	/**
	 * The permission callback returns true for a post the user can write.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_permission_callback_returns_true_for_a_post_the_user_can_write(): void {
		$this->login_as( 'author' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertTrue( $permission( $this->build_post_input( self::$post_ids['author:draft'] ) ), 'The permission callback should return true.' );
	}

	/**
	 * The permission callback lets a post that does not exist through, so that the call returns
	 * the not-found error, also for a user who may write nothing.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_permission_callback_lets_a_missing_post_through(): void {
		$this->login_as( 'subscriber' );

		$permission = $this->get_ability_callbacks()['permission_callback'];
		$input      = $this->build_post_input( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, 'post' );

		$this->assertTrue( $permission( $input ), 'The permission callback should return true.' );
		$this->assert_not_found( $this->run_ability( $input ), 'A missing post' );
	}

	/**
	 * A permission result that a filter turns into a grant is the final permission decision:
	 * the call runs without a second capability check on the post.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_permission_result_granted_by_a_filter_is_final(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'contributor' );
		$this->register_abilities();

		$input = $this->build_post_input( $post_id );
		$this->assert_refused( $this->run_ability( $input ), 'Precondition: a call without the filter' );

		add_filter( 'wp_ability_permission_result', '__return_true' );

		$before = $this->snapshot( $post_id );

		$this->assertIsArray( $this->run_ability( $input ), 'A call granted by a filter should succeed.' );
		$this->assertNotSame( $before, $this->snapshot( $post_id ), 'A call granted by a filter should write the post.' );
	}

	/**
	 * An ID that is not a whole number of at least 1 fails input validation, and called directly,
	 * it is the not-found error.
	 *
	 * @ticket 66706
	 * @dataProvider data_ids_that_are_not_whole_numbers_of_at_least_1
	 * @since 7.3.0
	 *
	 * @param mixed $id The ID.
	 */
	public function test_id_that_is_not_a_whole_number_fails_input_validation( $id ): void {
		$post_id = self::$post_ids['admin_post'];
		$input   = $this->build_input(
			array(
				'type' => 'post',
				'id'   => $id,
			)
		);

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );

		$this->assert_not_found( $execute( $input ), 'A direct call with an ID that is not a whole number' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'The direct call should write nothing.' );

		$this->assert_invalid_input( $input, $post_id, 'An ID that is not a whole number' );
	}

	/**
	 * Returns keys that are not part of the input, and where they are sent.
	 *
	 * @return array<string, array{0: string|null, 1: string, 2: mixed}> Where the key is sent, or
	 *                                                                  null for the top level, the
	 *                                                                  key, and the value.
	 */
	abstract public static function data_keys_outside_the_allow_list(): array;

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

		$input = $this->build_post_input( $post_id );
		if ( null === $location ) {
			$input[ $key ] = $value;
		} else {
			$input[ $location ][ $key ] = $value;
		}

		$this->assert_invalid_input( $input, $post_id, 'A key outside the allow-list' );
	}

	/**
	 * A revision, an autosave, an attachment, a post of another exposed or non-exposed type and a
	 * missing post are not found as a `post`, even by an administrator.
	 *
	 * @ticket 66706
	 * @dataProvider data_posts_that_are_not_posts
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_post_of_another_type_is_not_found( string $post ): void {
		$post_id = self::$post_ids[ $post ] ?? REST_TESTS_IMPOSSIBLY_HIGH_NUMBER;

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );

		$this->assert_not_found( $this->run_ability( $this->build_post_input( $post_id, 'post' ) ), 'A post that is not a post' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Called directly, a post of a type that is not exposed is not found.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_direct_call_for_a_type_that_is_not_exposed_is_not_found(): void {
		register_post_type( 'cw_unexposed', array( 'public' => true ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'cw_unexposed' ) );

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );

		$this->assert_not_found( $execute( $this->build_post_input( $post_id ) ), 'A direct call for a type that is not exposed' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * A post type whose `show_in_abilities` is turned off after registration cannot be written.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_type_no_longer_exposed_cannot_be_written(): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'administrator' );
		$callbacks = $this->get_ability_callbacks();

		get_post_type_object( 'post' )->show_in_abilities = false;

		$input  = $this->build_post_input( $post_id );
		$before = $this->snapshot( $post_id );

		$this->assert_refused( $this->run_ability( $input ), 'A regular call for a type that is no longer exposed' );
		$this->assert_not_found( $callbacks['execute_callback']( $input ), 'A direct call for a type that is no longer exposed' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns malformed `where` values for a direct call, or null to send none.
	 *
	 * @return array<string, array{0: array<string, mixed>|null}> The values.
	 */
	public static function data_malformed_where_values(): array {
		return array(
			'no where' => array( null ),
			'no type'  => array( array( 'id' => 1 ) ),
			'no ID'    => array( array( 'type' => 'post' ) ),
		);
	}

	/**
	 * Called directly, a malformed `where` is the not-found error.
	 *
	 * @ticket 66706
	 * @dataProvider data_malformed_where_values
	 * @since 7.3.0
	 *
	 * @param array<string, mixed>|null $where The `where`, or null to send none.
	 */
	public function test_direct_call_with_a_malformed_where_is_not_found( ?array $where ): void {
		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$input = $this->build_input( $where );
		if ( null === $where ) {
			unset( $input['where'] );
		}

		$before = $this->snapshot( self::$post_ids['admin_post'] );

		$this->assert_not_found( $execute( $input ), 'A direct call with a malformed where' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['admin_post'] ), 'Nothing should be written.' );
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
		$this->register_abilities();

		$this->login_as( 'author' );
		$own_before = $this->snapshot( $own_id );
		$first      = $this->run_ability( $this->build_post_input( $own_id ) );

		$this->login_as( 'contributor' );
		$other_before = $this->snapshot( $other_id );
		$second       = $this->run_ability( $this->build_post_input( $other_id ) );

		$this->assertIsArray( $first, 'The first call as the author should succeed.' );
		$this->assertNotSame( $own_before, $this->snapshot( $own_id ), 'The first call should write the post.' );
		$this->assert_refused( $second, 'The second call as a contributor' );
		$this->assertSame( $other_before, $this->snapshot( $other_id ), 'The second call should write nothing.' );
	}
}
