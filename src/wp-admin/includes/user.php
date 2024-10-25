<?php
/**
 * WordPress user administration API.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Creates a new user from the "Users" form using $_POST information.
 *
 * @since 2.0.0
 *
 * @return int|WP_Error WP_Error or User ID.
 */
function add_user() {
	return edit_user();
}

/**
 * Edit user settings based on contents of $_POST
 *
 * Used on user-edit.php and profile.php to manage and process user options, passwords etc.
 *
 * @since 2.0.0
 *
 * @param int $user_id Optional. User ID.
 * @return int|WP_Error User ID of the updated user or WP_Error on failure.
 */
function edit_user( $user_id = 0 ) {
	$wp_roles = wp_roles();
	$user     = new stdClass();
	$user_id  = (int) $user_id;
	if ( $user_id ) {
		$update           = true;
		$user->ID         = $user_id;
		$userdata         = get_userdata( $user_id );
		$user->user_login = wp_slash( $userdata->user_login );
	} else {
		$update = false;
	}

	if ( ! $update && isset( $_POST['user_login'] ) ) {
		$user->user_login = sanitize_user( wp_unslash( $_POST['user_login'] ), true );
	}

	$pass1 = '';
	$pass2 = '';
	if ( isset( $_POST['pass1'] ) ) {
		$pass1 = trim( $_POST['pass1'] );
	}
	if ( isset( $_POST['pass2'] ) ) {
		$pass2 = trim( $_POST['pass2'] );
	}

	if ( isset( $_POST['role'] ) && current_user_can( 'promote_users' ) && ( ! $user_id || current_user_can( 'promote_user', $user_id ) ) ) {
		$new_role = sanitize_text_field( $_POST['role'] );

		// If the new role isn't editable by the logged-in user die with error.
		$editable_roles = get_editable_roles();
		if ( ! empty( $new_role ) && empty( $editable_roles[ $new_role ] ) ) {
			wp_die( __( 'Sorry, you are not allowed to give users that role.' ), 403 );
		}

		$potential_role = isset( $wp_roles->role_objects[ $new_role ] ) ? $wp_roles->role_objects[ $new_role ] : false;

		/*
		 * Don't let anyone with 'promote_users' edit their own role to something without it.
		 * Multisite super admins can freely edit their roles, they possess all caps.
		 */
		if (
			( is_multisite() && current_user_can( 'manage_network_users' ) ) ||
			get_current_user_id() !== $user_id ||
			( $potential_role && $potential_role->has_cap( 'promote_users' ) )
		) {
			$user->role = $new_role;
		}
	}

	if ( isset( $_POST['email'] ) ) {
		$user->user_email = sanitize_text_field( wp_unslash( $_POST['email'] ) );
	}
	if ( isset( $_POST['url'] ) ) {
		if ( empty( $_POST['url'] ) || 'http://' === $_POST['url'] ) {
			$user->user_url = '';
		} else {
			$user->user_url = sanitize_url( $_POST['url'] );
			$protocols      = implode( '|', array_map( 'preg_quote', wp_allowed_protocols() ) );
			$user->user_url = preg_match( '/^(' . $protocols . '):/is', $user->user_url ) ? $user->user_url : 'http://' . $user->user_url;
		}
	}
	if ( isset( $_POST['first_name'] ) ) {
		$user->first_name = sanitize_text_field( $_POST['first_name'] );
	}
	if ( isset( $_POST['last_name'] ) ) {
		$user->last_name = sanitize_text_field( $_POST['last_name'] );
	}
	if ( isset( $_POST['nickname'] ) ) {
		$user->nickname = sanitize_text_field( $_POST['nickname'] );
	}
	if ( isset( $_POST['display_name'] ) ) {
		$user->display_name = sanitize_text_field( $_POST['display_name'] );
	}

	if ( isset( $_POST['description'] ) ) {
		$user->description = trim( $_POST['description'] );
	}

	foreach ( wp_get_user_contact_methods( $user ) as $method => $name ) {
		if ( isset( $_POST[ $method ] ) ) {
			$user->$method = sanitize_text_field( $_POST[ $method ] );
		}
	}

	if ( isset( $_POST['locale'] ) ) {
		$locale = sanitize_text_field( $_POST['locale'] );
		if ( 'site-default' === $locale ) {
			$locale = '';
		} elseif ( '' === $locale ) {
			$locale = 'en_US';
		} elseif ( ! in_array( $locale, get_available_languages(), true ) ) {
			if ( current_user_can( 'install_languages' ) && wp_can_install_language_pack() ) {
				if ( ! wp_download_language_pack( $locale ) ) {
					$locale = '';
				}
			} else {
				$locale = '';
			}
		}

		$user->locale = $locale;
	}

	if ( $update ) {
		$user->rich_editing         = isset( $_POST['rich_editing'] ) && 'false' === $_POST['rich_editing'] ? 'false' : 'true';
		$user->syntax_highlighting  = isset( $_POST['syntax_highlighting'] ) && 'false' === $_POST['syntax_highlighting'] ? 'false' : 'true';
		$user->admin_color          = isset( $_POST['admin_color'] ) ? sanitize_text_field( $_POST['admin_color'] ) : 'fresh';
		$user->show_admin_bar_front = isset( $_POST['admin_bar_front'] ) ? 'true' : 'false';
	}

	$user->comment_shortcuts = isset( $_POST['comment_shortcuts'] ) && 'true' === $_POST['comment_shortcuts'] ? 'true' : '';

	$user->use_ssl = 0;
	if ( ! empty( $_POST['use_ssl'] ) ) {
		$user->use_ssl = 1;
	}

	$errors = new WP_Error();

	/* checking that username has been typed */
	if ( '' === $user->user_login ) {
		$errors->add( 'user_login', __( '<strong>Error:</strong> Please enter a username.' ) );
	}

	/* checking that nickname has been typed */
	if ( $update && empty( $user->nickname ) ) {
		$errors->add( 'nickname', __( '<strong>Error:</strong> Please enter a nickname.' ) );
	}

	/**
	 * Fires before the password and confirm password fields are checked for congruity.
	 *
	 * @since 1.5.1
	 *
	 * @param string $user_login The username.
	 * @param string $pass1     The password (passed by reference).
	 * @param string $pass2     The confirmed password (passed by reference).
	 */
	do_action_ref_array( 'check_passwords', array( $user->user_login, &$pass1, &$pass2 ) );

	// Check for blank password when adding a user.
	if ( ! $update && empty( $pass1 ) ) {
		$errors->add( 'pass', __( '<strong>Error:</strong> Please enter a password.' ), array( 'form-field' => 'pass1' ) );
	}

	// Check for "\" in password.
	if ( str_contains( wp_unslash( $pass1 ), '\\' ) ) {
		$errors->add( 'pass', __( '<strong>Error:</strong> Passwords may not contain the character "\\".' ), array( 'form-field' => 'pass1' ) );
	}

	// Checking the password has been typed twice the same.
	if ( ( $update || ! empty( $pass1 ) ) && $pass1 !== $pass2 ) {
		$errors->add( 'pass', __( '<strong>Error:</strong> Passwords do not match. Please enter the same password in both password fields.' ), array( 'form-field' => 'pass1' ) );
	}

	if ( ! empty( $pass1 ) ) {
		$user->user_pass = $pass1;
	}

	if ( ! $update && isset( $_POST['user_login'] ) && ! validate_username( $_POST['user_login'] ) ) {
		$errors->add( 'user_login', __( '<strong>Error:</strong> This username is invalid because it uses illegal characters. Please enter a valid username.' ) );
	}

	if ( ! $update ) {

		// Username must be unique.
		if ( username_exists( $user->user_login ) ) {
			$errors->add( 'user_login', __( '<strong>Error:</strong> This username is already registered. Please choose another one.' ) );
		}

		// Username must not match an existing user email.
		if ( email_exists( $user->user_login ) ) {
			$errors->add( 'user_login', __( '<strong>Error:</strong> This username is not available. Please choose another one.' ) );
		}
	}

	/** This filter is documented in wp-includes/user.php */
	$illegal_logins = (array) apply_filters( 'illegal_user_logins', array() );

	if ( in_array( strtolower( $user->user_login ), array_map( 'strtolower', $illegal_logins ), true ) ) {
		$errors->add( 'invalid_username', __( '<strong>Error:</strong> Sorry, that username is not allowed.' ) );
	}

	// Checking email address.
	if ( empty( $user->user_email ) ) {
		$errors->add( 'empty_email', __( '<strong>Error:</strong> Please enter an email address.' ), array( 'form-field' => 'email' ) );
	} elseif ( ! is_email( $user->user_email ) ) {
		$errors->add( 'invalid_email', __( '<strong>Error:</strong> The email address is not correct.' ), array( 'form-field' => 'email' ) );
	} else {
		$owner_id = email_exists( $user->user_email );
		if ( $owner_id && ( ! $update || ( $owner_id !== $user->ID ) ) ) {
			$errors->add( 'email_exists', __( '<strong>Error:</strong> This email is already registered. Please choose another one.' ), array( 'form-field' => 'email' ) );
		}
	}

	/**
	 * Fires before user profile update errors are returned.
	 *
	 * @since 2.8.0
	 *
	 * @param WP_Error $errors WP_Error object (passed by reference).
	 * @param bool     $update Whether this is a user update.
	 * @param stdClass $user   User object (passed by reference).
	 */
	do_action_ref_array( 'user_profile_update_errors', array( &$errors, $update, &$user ) );

	if ( $errors->has_errors() ) {
		return $errors;
	}

	if ( $update ) {
		$user_id = wp_update_user( $user );
	} else {
		$user_id = wp_insert_user( $user );
		$notify  = isset( $_POST['send_user_notification'] ) ? 'both' : 'admin';

		/**
		 * Fires after a new user has been created.
		 *
		 * @since 4.4.0
		 *
		 * @param int|WP_Error $user_id ID of the newly created user or WP_Error on failure.
		 * @param string       $notify  Type of notification that should happen. See
		 *                              wp_send_new_user_notifications() for more information.
		 */
		do_action( 'edit_user_created_user', $user_id, $notify );
	}
	return $user_id;
}

/**
 * Fetch a filtered list of user roles that the current user is
 * allowed to edit.
 *
 * Simple function whose main purpose is to allow filtering of the
 * list of roles in the $wp_roles object so that plugins can remove
 * inappropriate ones depending on the situation or user making edits.
 * Specifically because without filtering anyone with the edit_users
 * capability can edit others to be administrators, even if they are
 * only editors or authors. This filter allows admins to delegate
 * user management.
 *
 * @since 2.8.0
 *
 * @return array[] Array of arrays containing role information.
 */
function get_editable_roles() {
	$all_roles = wp_roles()->roles;

	/**
	 * Filters the list of editable roles.
	 *
	 * @since 2.8.0
	 *
	 * @param array[] $all_roles Array of arrays containing role information.
	 */
	$editable_roles = apply_filters( 'editable_roles', $all_roles );

	return $editable_roles;
}

/**
 * Retrieve user data and filter it.
 *
 * @since 2.0.5
 *
 * @param int $user_id User ID.
 * @return WP_User|false WP_User object on success, false on failure.
 */
function get_user_to_edit( $user_id ) {
	$user = get_userdata( $user_id );

	if ( $user ) {
		$user->filter = 'edit';
	}

	return $user;
}

/**
 * Retrieve the user's drafts.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $user_id User ID.
 * @return array
 */
function get_users_drafts( $user_id ) {
	global $wpdb;
	$query = $wpdb->prepare( "SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'draft' AND post_author = %d ORDER BY post_modified DESC", $user_id );

	/**
	 * Filters the user's drafts query string.
	 *
	 * @since 2.0.0
	 *
	 * @param string $query The user's drafts query string.
	 */
	$query = apply_filters( 'get_users_drafts', $query );
	return $wpdb->get_results( $query );
}

/**
 * Delete user and optionally reassign posts and links to another user.
 *
 * Note that on a Multisite installation the user only gets removed from the site
 * and does not get deleted from the database.
 *
 * If the `$reassign` parameter is not assigned to a user ID, then all posts will
 * be deleted of that user. The action {@see 'delete_user'} that is passed the user ID
 * being deleted will be run after the posts are either reassigned or deleted.
 * The user meta will also be deleted that are for that user ID.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $id       User ID.
 * @param int $reassign Optional. Reassign posts and links to new User ID.
 * @return bool True when finished.
 */
function wp_delete_user( $id, $reassign = null ) {
	global $wpdb;

	if ( ! is_numeric( $id ) ) {
		return false;
	}

	$id   = (int) $id;
	$user = new WP_User( $id );

	if ( ! $user->exists() ) {
		return false;
	}

	// Normalize $reassign to null or a user ID. 'novalue' was an older default.
	if ( 'novalue' === $reassign ) {
		$reassign = null;
	} elseif ( null !== $reassign ) {
		$reassign = (int) $reassign;
	}

	/**
	 * Fires immediately before a user is deleted from the site.
	 *
	 * Note that on a Multisite installation the user only gets removed from the site
	 * and does not get deleted from the database.
	 *
	 * @since 2.0.0
	 * @since 5.5.0 Added the `$user` parameter.
	 *
	 * @param int      $id       ID of the user to delete.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 * @param WP_User  $user     WP_User object of the user to delete.
	 */
	do_action( 'delete_user', $id, $reassign, $user );

	if ( null === $reassign ) {
		$post_types_to_delete = array();
		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
			if ( $post_type->delete_with_user ) {
				$post_types_to_delete[] = $post_type->name;
			} elseif ( null === $post_type->delete_with_user && post_type_supports( $post_type->name, 'author' ) ) {
				$post_types_to_delete[] = $post_type->name;
			}
		}

		/**
		 * Filters the list of post types to delete with a user.
		 *
		 * @since 3.4.0
		 *
		 * @param string[] $post_types_to_delete Array of post types to delete.
		 * @param int      $id                   User ID.
		 */
		$post_types_to_delete = apply_filters( 'post_types_to_delete_with_user', $post_types_to_delete, $id );
		$post_types_to_delete = implode( "', '", $post_types_to_delete );
		$post_ids             = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d AND post_type IN ('$post_types_to_delete')", $id ) );
		if ( $post_ids ) {
			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id );
			}
		}

		// Clean links.
		$link_ids = $wpdb->get_col( $wpdb->prepare( "SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $id ) );

		if ( $link_ids ) {
			foreach ( $link_ids as $link_id ) {
				wp_delete_link( $link_id );
			}
		}
	} else {
		$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d", $id ) );
		$wpdb->update( $wpdb->posts, array( 'post_author' => $reassign ), array( 'post_author' => $id ) );
		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				clean_post_cache( $post_id );
			}
		}
		$link_ids = $wpdb->get_col( $wpdb->prepare( "SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $id ) );
		$wpdb->update( $wpdb->links, array( 'link_owner' => $reassign ), array( 'link_owner' => $id ) );
		if ( ! empty( $link_ids ) ) {
			foreach ( $link_ids as $link_id ) {
				clean_bookmark_cache( $link_id );
			}
		}
	}

	// FINALLY, delete user.
	if ( is_multisite() ) {
		remove_user_from_blog( $id, get_current_blog_id() );
	} else {
		$meta = $wpdb->get_col( $wpdb->prepare( "SELECT umeta_id FROM $wpdb->usermeta WHERE user_id = %d", $id ) );
		foreach ( $meta as $mid ) {
			delete_metadata_by_mid( 'user', $mid );
		}

		$wpdb->delete( $wpdb->users, array( 'ID' => $id ) );
	}

	clean_user_cache( $user );

	/**
	 * Fires immediately after a user is deleted from the site.
	 *
	 * Note that on a Multisite installation the user may not have been deleted from
	 * the database depending on whether `wp_delete_user()` or `wpmu_delete_user()`
	 * was called.
	 *
	 * @since 2.9.0
	 * @since 5.5.0 Added the `$user` parameter.
	 *
	 * @param int      $id       ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 * @param WP_User  $user     WP_User object of the deleted user.
	 */
	do_action( 'deleted_user', $id, $reassign, $user );

	return true;
}

/**
 * Remove all capabilities from user.
 *
 * @since 2.1.0
 *
 * @param int $id User ID.
 */
function wp_revoke_user( $id ) {
	$id = (int) $id;

	$user = new WP_User( $id );
	$user->remove_all_caps();
}

/**
 * @since 2.8.0
 *
 * @global int $user_ID
 *
 * @param false $errors Deprecated.
 */
function default_password_nag_handler( $errors = false ) {
	global $user_ID;
	// Short-circuit it.
	if ( ! get_user_option( 'default_password_nag' ) ) {
		return;
	}

	// get_user_setting() = JS-saved UI setting. Else no-js-fallback code.
	if ( 'hide' === get_user_setting( 'default_password_nag' )
		|| isset( $_GET['default_password_nag'] ) && '0' === $_GET['default_password_nag']
	) {
		delete_user_setting( 'default_password_nag' );
		update_user_meta( $user_ID, 'default_password_nag', false );
	}
}

/**
 * @since 2.8.0
 *
 * @param int     $user_ID
 * @param WP_User $old_data
 */
function default_password_nag_edit_user( $user_ID, $old_data ) {
	// Short-circuit it.
	if ( ! get_user_option( 'default_password_nag', $user_ID ) ) {
		return;
	}

	$new_data = get_userdata( $user_ID );

	// Remove the nag if the password has been changed.
	if ( $new_data->user_pass !== $old_data->user_pass ) {
		delete_user_setting( 'default_password_nag' );
		update_user_meta( $user_ID, 'default_password_nag', false );
	}
}

/**
 * @since 2.8.0
 *
 * @global string $pagenow The filename of the current screen.
 */
function default_password_nag() {
	global $pagenow;

	// Short-circuit it.
	if ( 'profile.php' === $pagenow || ! get_user_option( 'default_password_nag' ) ) {
		return;
	}

	$default_password_nag_message  = sprintf(
		'<p><strong>%1$s</strong> %2$s</p>',
		__( 'Notice:' ),
		__( 'You are using the auto-generated password for your account. Would you like to change it?' )
	);
	$default_password_nag_message .= sprintf(
		'<p><a href="%1$s">%2$s</a> | ',
		esc_url( get_edit_profile_url() . '#password' ),
		__( 'Yes, take me to my profile page' )
	);
	$default_password_nag_message .= sprintf(
		'<a href="%1$s" id="default-password-nag-no">%2$s</a></p>',
		'?default_password_nag=0',
		__( 'No thanks, do not remind me again' )
	);

	wp_admin_notice(
		$default_password_nag_message,
		array(
			'additional_classes' => array( 'error', 'default-password-nag' ),
			'paragraph_wrap'     => false,
		)
	);
}

/**
 * @since 3.5.0
 * @access private
 */
function delete_users_add_js() {
	?>
<script>
jQuery( function($) {
	var submit = $('#submit').prop('disabled', true);
	$('input[name="delete_option"]').one('change', function() {
		submit.prop('disabled', false);
	});
	$('#reassign_user').focus( function() {
		$('#delete_option1').prop('checked', true).trigger('change');
	});
} );
</script>
	<?php
}

/**
 * Optional SSL preference that can be turned on by hooking to the 'personal_options' action.
 *
 * See the {@see 'personal_options'} action.
 *
 * @since 2.7.0
 *
 * @param WP_User $user User data object.
 */
function use_ssl_preference( $user ) {
	?>
	<tr class="user-use-ssl-wrap">
		<th scope="row"><?php _e( 'Use https' ); ?></th>
		<td><label for="use_ssl"><input name="use_ssl" type="checkbox" id="use_ssl" value="1" <?php checked( '1', $user->use_ssl ); ?> /> <?php _e( 'Always use https when visiting the admin' ); ?></label></td>
	</tr>
	<?php
}

/**
 * @since MU (3.0.0)
 *
 * @param string $text
 * @return string
 */
function admin_created_user_email( $text ) {
	$roles = get_editable_roles();
	$role  = $roles[ $_REQUEST['role'] ];

	if ( '' !== get_bloginfo( 'name' ) ) {
		$site_title = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	} else {
		$site_title = parse_url( home_url(), PHP_URL_HOST );
	}

	return sprintf(
		/* translators: 1: Site title, 2: Site URL, 3: User role. */
		__(
			'Hi,
You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.
If you do not want to join this site please ignore
this email. This invitation will expire in a few days.

Please click the following link to activate your user account:
%%s'
		),
		$site_title,
		home_url(),
		wp_specialchars_decode( translate_user_role( $role['name'] ) )
	);
}

/**
 * Checks if the Authorize Application Password request is valid.
 *
 * @since 5.6.0
 * @since 6.2.0 Allow insecure HTTP connections for the local environment.
 * @since 6.3.2 Validates the success and reject URLs to prevent `javascript` pseudo protocol from being executed.
 *
 * @param array   $request {
 *     The array of request data. All arguments are optional and may be empty.
 *
 *     @type string $app_name    The suggested name of the application.
 *     @type string $app_id      A UUID provided by the application to uniquely identify it.
 *     @type string $success_url The URL the user will be redirected to after approving the application.
 *     @type string $reject_url  The URL the user will be redirected to after rejecting the application.
 * }
 * @param WP_User $user The user authorizing the application.
 * @return true|WP_Error True if the request is valid, a WP_Error object contains errors if not.
 */
function wp_is_authorize_application_password_request_valid( $request, $user ) {
	$error = new WP_Error();

	if ( isset( $request['success_url'] ) ) {
		$validated_success_url = wp_is_authorize_application_redirect_url_valid( $request['success_url'] );
		if ( is_wp_error( $validated_success_url ) ) {
			$error->add(
				$validated_success_url->get_error_code(),
				$validated_success_url->get_error_message()
			);
		}
	}

	if ( isset( $request['reject_url'] ) ) {
		$validated_reject_url = wp_is_authorize_application_redirect_url_valid( $request['reject_url'] );
		if ( is_wp_error( $validated_reject_url ) ) {
			$error->add(
				$validated_reject_url->get_error_code(),
				$validated_reject_url->get_error_message()
			);
		}
	}

	if ( ! empty( $request['app_id'] ) && ! wp_is_uuid( $request['app_id'] ) ) {
		$error->add(
			'invalid_app_id',
			__( 'The application ID must be a UUID.' )
		);
	}

	/**
	 * Fires before application password errors are returned.
	 *
	 * @since 5.6.0
	 *
	 * @param WP_Error $error   The error object.
	 * @param array    $request The array of request data.
	 * @param WP_User  $user    The user authorizing the application.
	 */
	do_action( 'wp_authorize_application_password_request_errors', $error, $request, $user );

	if ( $error->has_errors() ) {
		return $error;
	}

	return true;
}

/**
 * Validates the redirect URL protocol scheme. The protocol can be anything except `http` and `javascript`.
 *
 * @since 6.3.2
 *
 * @param string $url The redirect URL to be validated.
 * @return true|WP_Error True if the redirect URL is valid, a WP_Error object otherwise.
 */
function wp_is_authorize_application_redirect_url_valid( $url ) {
	$bad_protocols = array( 'javascript', 'data' );
	if ( empty( $url ) ) {
		return true;
	}

	// Based on https://www.rfc-editor.org/rfc/rfc2396#section-3.1
	$valid_scheme_regex = '/^[a-zA-Z][a-zA-Z0-9+.-]*:/';
	if ( ! preg_match( $valid_scheme_regex, $url ) ) {
		return new WP_Error(
			'invalid_redirect_url_format',
			__( 'Invalid URL format.' )
		);
	}

	/**
	 * Filters the list of invalid protocols used in applications redirect URLs.
	 *
	 * @since 6.3.2
	 *
	 * @param string[] $bad_protocols Array of invalid protocols.
	 * @param string   $url The redirect URL to be validated.
	 */
	$invalid_protocols = apply_filters( 'wp_authorize_application_redirect_url_invalid_protocols', $bad_protocols, $url );
	$invalid_protocols = array_map( 'strtolower', $invalid_protocols );

	$scheme   = wp_parse_url( $url, PHP_URL_SCHEME );
	$host     = wp_parse_url( $url, PHP_URL_HOST );
	$is_local = 'local' === wp_get_environment_type();

	// Validates if the proper URI format is applied to the URL.
	if ( empty( $host ) || empty( $scheme ) || in_array( strtolower( $scheme ), $invalid_protocols, true ) ) {
		return new WP_Error(
			'invalid_redirect_url_format',
			__( 'Invalid URL format.' )
		);
	}

	if ( 'http' === $scheme && ! $is_local ) {
		return new WP_Error(
			'invalid_redirect_scheme',
			__( 'The URL must be served over a secure connection.' )
		);
	}

	return true;
}
