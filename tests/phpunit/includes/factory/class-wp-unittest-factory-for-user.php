<?php

/**
 * Unit test factory for users.
 *
 * Note: The below @method notations are defined solely for the benefit of IDEs,
 * as a way to indicate expected return values from the given factory methods.
 *
 * @method int create( $args = array(), $generation_definitions = null )
 * @method WP_User create_and_get( $args = array(), $generation_definitions = null )
 * @method int[] create_many( $count, $args = array(), $generation_definitions = null )
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
	 * @param array $args The user data to insert.
	 *
	 * @return int|WP_Error
	 */
	public function create_object( $args ) {
		return wp_insert_user( $args );
	}

	/**
	 * Updates the user data.
	 *
	 * @param int   $user_id The user id to update.
	 * @param array $fields  The user data to update.
	 *
	 * @return int|WP_Error User id on success. WP_Error on failure.
	 */
	public function update_object( $user_id, $fields ) {
		$fields['ID'] = $user_id;
		return wp_update_user( $fields );
	}

	/**
	 * Retrieves the user for given user id.
	 *
	 * @param int $user_id The user id to get.
	 *
	 * @return WP_User The user.
	 */
	public function get_object_by_id( $user_id ) {
		return new WP_User( $user_id );
	}
}
