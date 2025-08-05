<?php

/**
 * Unit test factory for users.
 *
 * Note: The below @method notations are defined solely for the benefit of IDEs,
 * as a way to indicate expected return values from the given factory methods.
 *
 * @method int|WP_Error     create( $args = array(), $generation_definitions = null )
 * @method WP_User|WP_Error create_and_get( $args = array(), $generation_definitions = null )
 * @method (int|WP_Error)[] create_many( $count, $args = array(), $generation_definitions = null )
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
	 *
	 * @param array $args The user data to insert.
	 *
	 * @return int|WP_Error The user ID on success, WP_Error object on failure.
	 */
	public function create_object( $args ) {
		return wp_insert_user( $args );
	}

	/**
	 * Updates the user data.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param int   $user_id ID of the user to update.
	 * @param array $fields  The user data to update.
	 *
	 * @return int|WP_Error The user ID on success, WP_Error object on failure.
	 */
	public function update_object( $user_id, $fields ) {
		$fields['ID'] = $user_id;
		return wp_update_user( $fields );
	}

	/**
	 * Retrieves the user for a given ID.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param int $user_id ID of the user ID to retrieve.
	 *
	 * @return WP_User The user object.
	 */
	public function get_object_by_id( $user_id ) {
		return new WP_User( $user_id );
	}
}
