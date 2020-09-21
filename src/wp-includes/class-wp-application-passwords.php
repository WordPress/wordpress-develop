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
	 *
	 * @since ?.?.0
	 *
	 * @type string
	 */
	const USERMETA_KEY_APPLICATION_PASSWORDS = '_application_passwords';

	/**
	 * The length of generated application passwords.
	 *
	 * @since ?.?.0
	 *
	 * @type int
	 */
	const PW_LENGTH = 24;

	/**
	 * Generate a new application password.
	 *
	 * @since ?.?.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $name    Application name.
	 * @return array          The first key in the array is the new password, the second is its detailed information.
	 */
	public static function create_new_application_password( $user_id, $name ) {
		$new_password    = wp_generate_password( self::PW_LENGTH, false );
		$hashed_password = wp_hash_password( $new_password );

		$new_item = array(
			'uuid'      => wp_generate_uuid4(),
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
	 * @param int    $user_id User ID.
	 * @param string $uuid    The password's uuid.
	 * @return bool Whether the password was successfully found and deleted.
	 */
	public static function delete_application_password( $user_id, $uuid ) {
		$passwords = self::get_user_application_passwords( $user_id );

		foreach ( $passwords as $key => $item ) {
			if ( $item['uuid'] === $uuid ) {
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
	 * @param int $user_id User ID.
	 * @return int   The number of passwords that were deleted.
	 */
	public static function delete_all_application_passwords( $user_id ) {
		$passwords = self::get_user_application_passwords( $user_id );

		if ( $passwords ) {
			self::set_user_application_passwords( $user_id, array() );
			return count( $passwords );
		}

		return 0;
	}

	/**
	 * Sanitize and then split a password into smaller chunks.
	 *
	 * @since ?.?.0
	 *
	 * @param string $raw_password The raw application password.
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
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_user_application_passwords( $user_id ) {
		$passwords = get_user_meta( $user_id, self::USERMETA_KEY_APPLICATION_PASSWORDS, true );

		if ( ! is_array( $passwords ) ) {
			return array();
		}

		$save = false;

		foreach ( $passwords as $i => $password ) {
			if ( ! isset( $password['uuid'] ) ) {
				$passwords[ $i ]['uuid'] = wp_generate_uuid4();
				$save                    = true;
			}
		}

		if ( $save ) {
			static::set_user_application_passwords( $user_id, $passwords );
		}

		return $passwords;
	}

	/**
	 * Gets a user's application password with the given slug.
	 *
	 * @since ?.?.0
	 *
	 * @param int    $user_id The user id.
	 * @param string $uuid    The password's uuid.
	 * @return array|null
	 */
	public static function get_user_application_password( $user_id, $uuid ) {
		$passwords = self::get_user_application_passwords( $user_id );

		foreach ( $passwords as $password ) {
			if ( $password['uuid'] === $uuid ) {
				return $password;
			}
		}

		return null;
	}

	/**
	 * Marks that an application password has been used.
	 *
	 * @since ?.?.0
	 *
	 * @param int    $user_id The user id.
	 * @param string $uuid    The password's uuid.
	 * @return bool
	 */
	public static function used_application_password( $user_id, $uuid ) {
		$passwords = self::get_user_application_passwords( $user_id );

		foreach ( $passwords as &$password ) {
			if ( $password['uuid'] === $uuid ) {
				$password['last_used'] = time();
				$password['last_ip']   = $_SERVER['REMOTE_ADDR'];

				self::set_user_application_passwords( $user_id, $passwords );

				return true;
			}
		}

		return false;
	}

	/**
	 * Set a users application passwords.
	 *
	 * @since ?.?.0
	 *
	 * @param int   $user_id   User ID.
	 * @param array $passwords Application passwords.
	 *
	 * @return bool
	 */
	public static function set_user_application_passwords( $user_id, $passwords ) {
		return update_user_meta( $user_id, self::USERMETA_KEY_APPLICATION_PASSWORDS, $passwords );
	}
}
