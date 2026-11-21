<?php

/**
 * Unit test factory for users.
 *
 * Note: The below @method notation is defined solely for the benefit of IDEs,
 * as a way to indicate the expected return value from the given factory method.
 *
 * @method WP_User create_and_get( $args = array(), $generation_definitions = null )
 */
class WP_UnitTest_Factory_For_User extends WP_UnitTest_Factory_For_Thing {

	public function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'user_login' => new WP_UnitTest_Generator_Sequence( 'User %s' ),
			'user_pass'  => 'password',
			'user_email' => new WP_UnitTest_Generator_Sequence( 'user_%s@example.org' ),
		);
	}

	/**
	 * Inserts an user.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array<string, mixed> $args The user data to insert.
	 * @return positive-int The user ID.
	 * @throws WP_UnitTest_Factory_Exception When the user could not be created.
	 */
	public function create_object( $args ) {
		$user_id = wp_insert_user( $args );

		$this->assert_valid_object_id( $user_id, 'Unable to create the user' );

		return $user_id;
	}

	/**
	 * Updates the user data.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param int                  $user_id ID of the user to update.
	 * @param array<string, mixed> $fields  The user data to update.
	 * @return positive-int The user ID.
	 * @throws WP_UnitTest_Factory_Exception When the user could not be updated.
	 */
	public function update_object( $user_id, $fields ) {
		$fields['ID'] = $user_id;

		$updated_id = wp_update_user( $fields );

		$this->assert_valid_object_id( $updated_id, 'Unable to update the user' );

		return $updated_id;
	}

	/**
	 * Retrieves the user for a given ID.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param int $user_id ID of the user ID to retrieve.
	 * @return WP_User The user object.
	 */
	public function get_object_by_id( $user_id ) {
		// Unlike the other factories, this cannot fail: WP_User is returned even for an unknown ID.
		return new WP_User( $user_id );
	}
}
