<?php
/**
 * Class for displaying, modifying, & sanitizing application passwords.
 *
 * @since ?.?.0
 *
 * @package Two_Factor
 */
class WP_Application_Passwords {

	/**
	 * The user meta application password key.
	 * @type string
	 */
	const USERMETA_KEY_APPLICATION_PASSWORDS = '_application_passwords';

	/**
	 * The length of generated application passwords.
	 *
	 * @type integer
	 */
	const PW_LENGTH = 24;

	/**
	 * Generate a new application password.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param int    $user_id User ID.
	 * @param string $name    Password name.
	 * @return array          The first key in the array is the new password, the second is its row in the table.
	 */
	public static function create_new_application_password( $user_id, $name ) {
		$new_password    = wp_generate_password( self::PW_LENGTH, false );
		$hashed_password = wp_hash_password( $new_password );

		$new_item = array(
			'name'      => $name,
			'password'  => $hashed_password,
			'created'   => time(),
			'last_used' => null,
			'last_ip'   => null,
		);

		$passwords = self::get_user_application_passwords( $user_id );
		if ( ! $passwords ) {
			$passwords = array();
		}

		$passwords[] = $new_item;
		self::set_user_application_passwords( $user_id, $passwords );

		return array( $new_password, $new_item );
	}

	/**
	 * Delete a specified application password.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @see WP_Application_Passwords::password_unique_slug()
	 *
	 * @param int    $user_id User ID.
	 * @param string $slug The generated slug of the password in question.
	 * @return bool Whether the password was successfully found and deleted.
	 */
	public static function delete_application_password( $user_id, $slug ) {
		$passwords = self::get_user_application_passwords( $user_id );

		foreach ( $passwords as $key => $item ) {
			if ( self::password_unique_slug( $item ) === $slug ) {
				unset( $passwords[ $key ] );
				self::set_user_application_passwords( $user_id, $passwords );
				return true;
			}
		}

		// Specified Application Password not found!
		return false;
	}

	/**
	 * Deletes all application passwords for the given user.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param int    $user_id User ID.
	 * @return int   The number of passwords that were deleted.
	 */
	public static function delete_all_application_passwords( $user_id ) {
		$passwords = self::get_user_application_passwords( $user_id );

		if ( is_array( $passwords ) ) {
			self::set_user_application_passwords( $user_id, array() );
			return sizeof( $passwords );
		}

		return 0;
	}

	/**
	 * Generate a unique repeatable slug from the hashed password, name, and when it was created.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param array $item The current item.
	 * @return string
	 */
	public static function password_unique_slug( $item ) {
		$concat = $item['name'] . '|' . $item['password'] . '|' . $item['created'];
		$hash   = md5( $concat );
		return substr( $hash, 0, 12 );
	}

	/**
	 * Sanitize and then split a password into smaller chunks.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param string $raw_password Users raw password.
	 * @return string
	 */
	public static function chunk_password( $raw_password ) {
		$raw_password = preg_replace( '/[^a-z\d]/i', '', $raw_password );
		return trim( chunk_split( $raw_password, 4, ' ' ) );
	}

	/**
	 * Get a users application passwords.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_user_application_passwords( $user_id ) {
		$passwords = get_user_meta( $user_id, self::USERMETA_KEY_APPLICATION_PASSWORDS, true );
		if ( ! is_array( $passwords ) ) {
			return array();
		}
		return $passwords;
	}

	/**
	 * Gets a user's application password with the given slug.
	 *
	 * @since ?.?.0
	 *
	 * @param int    $user_id The user id.
	 * @param string $slug    The slug.
	 * @return array|null
	 */
	public static function get_user_application_password( $user_id, $slug ) {
		$passwords = self::get_user_application_passwords( $user_id );

		foreach ( $passwords as $password ) {
			if ( self::password_unique_slug( $password ) === $slug ) {
				return $password;
			}
		}

		return null;
	}

	/**
	 * Set a users application passwords.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param int   $user_id User ID.
	 * @param array $passwords Application passwords.
	 *
	 * @return bool
	 */
	public static function set_user_application_passwords( $user_id, $passwords ) {
		return update_user_meta( $user_id, self::USERMETA_KEY_APPLICATION_PASSWORDS, $passwords );
	}
}
