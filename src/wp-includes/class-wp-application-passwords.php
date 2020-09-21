<?php
/**
 * WP_Application_Passwords class
 *
 * @package WordPress
 * @since   ?.?.0
 */

/**
 * Class for displaying, modifying, & sanitizing application passwords.
 *
 * @package WordPress
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
	 * @param int   $user_id  User ID.
	 * @param array $args     Information about the application password.
	 * @return array|WP_Error The first key in the array is the new password, the second is its detailed information.
	 *                        A WP_Error instance is returned on error.
	 */
	public static function create_new_application_password( $user_id, $args = array() ) {
		if ( empty( $args['name'] ) ) {
			return new WP_Error( 'application_password_empty_name', __( 'An application name is required to create an application password.' ) );
		}

		$new_password    = wp_generate_password( self::PW_LENGTH, false );
		$hashed_password = wp_hash_password( $new_password );

		$new_item = array(
			'uuid'      => wp_generate_uuid4(),
			'name'      => $args['name'],
			'password'  => $hashed_password,
			'created'   => time(),
			'last_used' => null,
			'last_ip'   => null,
		);

		$passwords   = self::get_user_application_passwords( $user_id );
		$passwords[] = $new_item;
		$saved       = self::set_user_application_passwords( $user_id, $passwords );

		if ( ! $saved ) {
			return new WP_Error( 'db_error', __( 'Could not save application password.' ) );
		}

		/**
		 * Fires when an application password is created.
		 *
		 * @since ?.?.0
		 *
		 * @param int    $user_id      The user id.
		 * @param array  $new_item     The newly created app password.
		 * @param string $new_password The generated app password.
		 * @param array  $args         Additional information about the application password.
		 */
		do_action( 'wp_create_application_password', $user_id, $new_item, $new_password, $args );

		return array( $new_password, $new_item );
	}

	/**
	 * Updates an application password.
	 *
	 * @since ?.?.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $uuid    The password's uuid.
	 * @param array  $update  Information about the application password to update.
	 * @return true|WP_Error True if successful, otherwise a WP_Error instance is returned on error.
	 */
	public static function update_application_password( $user_id, $uuid, $update = array() ) {
		$passwords = self::get_user_application_passwords( $user_id );

		foreach ( $passwords as &$item ) {
			if ( $item['uuid'] !== $uuid ) {
				continue;
			}

			$save = false;

			if ( ! empty( $update['name'] ) && $item['name'] !== $update['name'] ) {
				$item['name'] = $update['name'];
				$save         = true;
			}

			if ( $save ) {
				$saved = self::set_user_application_passwords( $user_id, $passwords );

				if ( ! $saved ) {
					return new WP_Error( 'db_error', __( 'Could not save application password.' ) );
				}
			}

			/**
			 * Fires when an application password is updated.
			 *
			 * @since ?.?.0
			 *
			 * @param int   $user_id The user id.
			 * @param array $item    The updated app password.
			 * @param array $update  Additional information about the application password.
			 */
			do_action( 'wp_update_application_password', $user_id, $item, $update );

			return true;
		}

		return new WP_Error( 'application_password_not_found', __( 'Could not find an application password with that id.' ) );
	}

	/**
	 * Delete a specified application password.
	 *
	 * @since ?.?.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $uuid    The password's uuid.
	 * @return true|WP_Error Whether the password was successfully found and deleted, a WP_Error otherwise.
	 */
	public static function delete_application_password( $user_id, $uuid ) {
		$passwords = self::get_user_application_passwords( $user_id );

		foreach ( $passwords as $key => $item ) {
			if ( $item['uuid'] === $uuid ) {
				unset( $passwords[ $key ] );
				$saved = self::set_user_application_passwords( $user_id, $passwords );

				if ( ! $saved ) {
					return new WP_Error( 'db_error', __( 'Could not delete application password.' ) );
				}

				/**
				 * Fires when an application password is deleted.
				 *
				 * @since ?.?.0
				 *
				 * @param int   $user_id The user id.
				 * @param array $item    The data about the application password.
				 */
				do_action( 'wp_delete_application_password', $user_id, $item );

				return true;
			}
		}

		return new WP_Error( 'application_password_not_found', __( 'Could not find an application password with that id.' ) );
	}

	/**
	 * Deletes all application passwords for the given user.
	 *
	 * @since ?.?.0
	 *
	 * @param int $user_id User ID.
	 * @return int|WP_Error The number of passwords that were deleted or a WP_Error on failure.
	 */
	public static function delete_all_application_passwords( $user_id ) {
		$passwords = self::get_user_application_passwords( $user_id );

		if ( $passwords ) {
			$saved = self::set_user_application_passwords( $user_id, array() );

			if ( ! $saved ) {
				return new WP_Error( 'db_error', __( 'Could not delete application passwords.' ) );
			}

			foreach ( $passwords as $item ) {
				/** This action is documented in wp-includes/class-wp-application-passwords.php */
				do_action( 'wp_delete_application_password', $user_id, $item );
			}

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
	 * @return true|WP_Error True if the usage was recorded, a WP_Error if an error occurs.
	 */
	public static function used_application_password( $user_id, $uuid ) {
		$passwords = self::get_user_application_passwords( $user_id );

		foreach ( $passwords as &$password ) {
			if ( $password['uuid'] !== $uuid ) {
				continue;
			}

			$password['last_used'] = time();
			$password['last_ip']   = $_SERVER['REMOTE_ADDR'];

			$saved = self::set_user_application_passwords( $user_id, $passwords );

			if ( ! $saved ) {
				return new WP_Error( 'db_error', __( 'Could not save application password.' ) );
			}

			return true;
		}

		// Specified Application Password not found!
		return new WP_Error( 'application_password_not_found', __( 'Could not find an application password with that id.' ) );
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
