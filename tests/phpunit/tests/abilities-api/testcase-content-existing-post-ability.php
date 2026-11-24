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
	 * Asserts that a result is the 403 error of the capability check in the execute callback.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	abstract protected function assert_cannot_write( $result, string $context ): void;

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
	 * Builds the input that names a post by its type and ID.
	 *
	 * @since 7.3.0
	 *
	 * @param int         $post_id The post ID.
	 * @param string|null $type    Optional. The post type, or null for the type of the post. Default null.
	 * @return array<string, mixed> The input.
	 */
	protected function build_post_input( int $post_id, ?string $type = null ): array {
		return $this->build_input(
			array(
				'type' => $type ?? get_post_type( $post_id ),
				'id'   => $post_id,
			)
		);
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
		$input      = $this->build_post_input( self::MISSING_ID, 'post' );

		$this->assertTrue( $permission( $input ), 'The permission callback should return true.' );
		$this->assert_not_found( $this->run_ability( $input ), 'A missing post' );
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

		$before = $this->snapshot( $post_id );

		$this->assert_cannot_write( $this->run_ability( $this->build_post_input( $post_id ) ), 'A call granted by a filter' );
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
			$this->build_input(
				array(
					'type' => 'post',
					'id'   => $id,
				)
			)
		);

		$this->assertWPError( $result, 'The call should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the ID.' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['admin_post'] ), 'Nothing should be written.' );
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
		$post_id = self::$post_ids[ $post ] ?? self::MISSING_ID;

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
		$execute  = $this->get_ability_callbacks()['execute_callback'];

		$this->login_as( 'author' );
		$own_before = $this->snapshot( $own_id );
		$first      = $execute( $this->build_post_input( $own_id ) );

		$this->login_as( 'contributor' );
		$other_before = $this->snapshot( $other_id );
		$second       = $execute( $this->build_post_input( $other_id ) );

		$this->assertIsArray( $first, 'The first call as the author should succeed.' );
		$this->assertNotSame( $own_before, $this->snapshot( $own_id ), 'The first call should write the post.' );
		$this->assert_cannot_write( $second, 'The second call as a contributor' );
		$this->assertSame( $other_before, $this->snapshot( $other_id ), 'The second call should write nothing.' );
	}
}
