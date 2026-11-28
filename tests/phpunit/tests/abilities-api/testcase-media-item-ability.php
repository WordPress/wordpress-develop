<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-media-ability.php';

/**
 * Base test case for the media abilities that act on one existing attachment:
 * core/media-update and core/media-delete. It holds the tests of the shared gate.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.3.0
 */
abstract class WP_Abilities_Media_Item_UnitTestCase extends WP_Abilities_Media_UnitTestCase {

	/**
	 * Returns an input of the ability under test for the given `where` value.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $where The `where` value.
	 * @return array<string, mixed> The input.
	 */
	abstract protected function build_input( array $where ): array;

	/**
	 * Creates an attachment row without a file whose author is the shared user with the
	 * given role or fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @param string $role The role or fixture name.
	 * @return int The attachment ID.
	 */
	protected function create_attachment_of( string $role ): int {
		return self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-own-' . $role . '.jpg',
				'post_author'    => self::$user_ids[ $role ],
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Own ' . $role,
			)
		);
	}

	/**
	 * Returns posts that are not attachments, and an ID that names nothing.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_ids_that_name_no_attachment(): array {
		return array(
			'post'     => array( 'post' ),
			'page'     => array( 'page' ),
			'revision' => array( 'revision' ),
			'missing'  => array( 'missing' ),
		);
	}

	/**
	 * Returns `where.id` values in forms the integer type accepts, as names for id_in_form().
	 *
	 * @return array<string, array{form: string}> The forms.
	 */
	public static function data_accepted_id_forms(): array {
		return array(
			'integer'              => array( 'integer' ),
			'integer string'       => array( 'string' ),
			'zero-padded string'   => array( 'padded' ),
			'float without a part' => array( 'float' ),
		);
	}

	/**
	 * Returns an ID in one of the forms of data_accepted_id_forms().
	 *
	 * @since 7.3.0
	 *
	 * @param int    $id   The ID.
	 * @param string $form The form.
	 * @return int|string|float The ID in that form.
	 */
	protected function id_in_form( int $id, string $form ) {
		switch ( $form ) {
			case 'string':
				return (string) $id;
			case 'padded':
				return '00' . $id;
			case 'float':
				return (float) $id;
			default:
				return $id;
		}
	}

	/**
	 * Returns `where` values the update and delete gates return false for.
	 *
	 * @return array<string, array{input: mixed}> The inputs.
	 */
	public static function data_inputs_without_a_usable_id(): array {
		return array(
			'no input'                 => array( null ),
			'no where'                 => array( array() ),
			'where string'             => array( array( 'where' => 'x' ) ),
			'no ID'                    => array( array( 'where' => array() ) ),
			'true'                     => array( array( 'where' => array( 'id' => true ) ) ),
			'letters'                  => array( array( 'where' => array( 'id' => 'abc' ) ) ),
			'fraction'                 => array( array( 'where' => array( 'id' => 1.5 ) ) ),
			'fraction string'          => array( array( 'where' => array( 'id' => '1.5' ) ) ),
			'list'                     => array( array( 'where' => array( 'id' => array( 1 ) ) ) ),
			'zero'                     => array( array( 'where' => array( 'id' => 0 ) ) ),
			'zero string'              => array( array( 'where' => array( 'id' => '0' ) ) ),
			'zero-padded zero string'  => array( array( 'where' => array( 'id' => '000' ) ) ),
			'zero float'               => array( array( 'where' => array( 'id' => 0.0 ) ) ),
			'negative'                 => array( array( 'where' => array( 'id' => -1 ) ) ),
			'negative float'           => array( array( 'where' => array( 'id' => -1.0 ) ) ),
			'float above 2 to the 53'  => array( array( 'where' => array( 'id' => 9007199254740994.0 ) ) ),
			'float in exponent format' => array( array( 'where' => array( 'id' => 1e20 ) ) ),
		);
	}

	/**
	 * Returns callers with whether the gate lets them act on an attachment of the author, or
	 * on their own attachment.
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
	 * Returns the callers the gate refuses, with the status they get.
	 *
	 * @return array<string, array{role: string, status: int, own: bool}> The cases.
	 */
	public static function data_callers_without_the_capability(): array {
		$cases = array();

		foreach ( self::data_gate_roles() as $name => $row ) {
			if ( ! $row[1] ) {
				$cases[ $name ] = array( $row[0], 'logged_out' === $row[0] ? 401 : 403, $row[2] ?? false );
			}
		}

		return $cases;
	}

	/**
	 * The permission callback needs the capability of the ability on the target: `edit_post`
	 * for an update and `delete_post` for a deletion.
	 *
	 * @ticket 66719
	 * @dataProvider data_gate_roles
	 * @since 7.3.0
	 *
	 * @param string $role     The role.
	 * @param bool   $expected Whether the gate passes.
	 * @param bool   $own      Optional. Whether the target is the caller's own attachment. Default false.
	 */
	public function test_permission_callback_needs_the_capability( string $role, bool $expected, bool $own = false ): void {
		$id = $own ? $this->create_attachment_of( $role ) : self::$post_ids['unattached'];

		$this->login_as( $role );
		$this->register_abilities();

		$this->assertSame( $expected, wp_get_ability( static::ABILITY )->check_permissions( $this->build_input( array( 'id' => $id ) ) ), 'The gate should follow the capability on the target.' );
	}

	/**
	 * The permission callback refuses an administrator when the attachment type has
	 * `show_in_abilities` off, and the call is refused with the generic error.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_when_attachments_are_hidden(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		get_post_type_object( 'attachment' )->show_in_abilities = false;

		$before = $this->snapshot( self::$post_ids['unattached'] );
		$input  = $this->build_input( array( 'id' => self::$post_ids['unattached'] ) );

		$this->assertFalse( wp_get_ability( static::ABILITY )->check_permissions( $input ), 'The administrator should be refused.' );
		$this->assert_refused( $this->run_ability( $input ), 'A call while attachments are hidden' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['unattached'] ), 'Nothing should change.' );
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

		$this->assert_refused( $this->run_ability( $this->build_input( array( 'id' => self::$post_ids['unattached'] ) ) ), 'A call by a contributor' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['unattached'] ), 'Nothing should change.' );
	}

	/**
	 * The permission callback passes when the ID names no attachment, so that the call
	 * answers that nothing was found, and nothing changes.
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

		$input  = $this->build_input( array( 'id' => self::$post_ids[ $post ] ?? REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$before = $this->snapshot();

		$this->assertTrue( wp_get_ability( static::ABILITY )->check_permissions( $input ), 'The gate should pass.' );
		$this->assert_not_found( $this->run_ability( $input ), 'A call for something that is not an attachment' );
		$this->assertSame( $before, $this->snapshot(), 'Nothing should change.' );
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
		$permission = $this->get_permission_callback();

		$this->assertFalse( call_user_func( $permission, $input ), 'The gate should refuse input without a usable ID.' );
	}

	/**
	 * The permission callback returns false for the ID of an attachment the caller may act on
	 * followed by letters.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_an_id_followed_by_letters(): void {
		$this->login_as( 'administrator' );
		$permission = $this->get_permission_callback();

		$input = $this->build_input( array( 'id' => self::$post_ids['unattached'] . 'abc' ) );

		$this->assertFalse( call_user_func( $permission, $input ), 'The gate should refuse an ID followed by letters.' );
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

		$input = $this->build_input( array( 'id' => $this->id_in_form( self::$post_ids['unattached'], $form ) ) );

		$this->assertTrue( wp_get_ability( static::ABILITY )->check_permissions( $input ), 'The gate should accept the ID form.' );
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
	 * With the gate and the validation skipped, an ID that is missing or below 1 is not found,
	 * also when the global post is an attachment the caller may change, and that attachment
	 * does not change.
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

		$GLOBALS['post'] = get_post( self::$post_ids['unattached'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The lookup should not fall back to it.

		$before = $this->snapshot( self::$post_ids['unattached'] );

		$this->assert_not_found( $this->run_ability( $this->build_input( $where ) ), 'A call with an unusable ID' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['unattached'] ), 'The global post should not change.' );
	}
}
