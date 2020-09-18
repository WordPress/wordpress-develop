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
	 * Add various hooks.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 */
	public static function add_hooks() {
		add_filter( 'authenticate', array( __CLASS__, 'authenticate' ), 10, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'rest_api_init' ) );
		add_filter( 'determine_current_user', array( __CLASS__, 'rest_api_auth_handler' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_authorize_application_password', array( __CLASS__, 'authorize_application_password' ) );
		self::fallback_populate_username_password();
	}

	/**
	 * Handle declaration of REST API endpoints.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 */
	public static function rest_api_init() {
		// List existing application passwords.
		register_rest_route( '2fa/v1', '/application-passwords/(?P<user_id>[\d]+)', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => __CLASS__ . '::rest_list_application_passwords',
			'permission_callback' => __CLASS__ . '::rest_edit_user_callback',
		) );

		// Add new application passwords.
		register_rest_route( '2fa/v1', '/application-passwords/(?P<user_id>[\d]+)/add', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => __CLASS__ . '::rest_add_application_password',
			'permission_callback' => __CLASS__ . '::rest_edit_user_callback',
			'args' => array(
				'name' => array(
					'required' => true,
				),
			),
		) );

		// Delete an application password.
		register_rest_route( '2fa/v1', '/application-passwords/(?P<user_id>[\d]+)/(?P<slug>[\da-fA-F]{12})', array(
			'methods' => WP_REST_Server::DELETABLE,
			'callback' => __CLASS__ . '::rest_delete_application_password',
			'permission_callback' => __CLASS__ . '::rest_edit_user_callback',
		) );

		// Delete all application passwords for a given user.
		register_rest_route( '2fa/v1', '/application-passwords/(?P<user_id>[\d]+)', array(
			'methods' => WP_REST_Server::DELETABLE,
			'callback' => __CLASS__ . '::rest_delete_all_application_passwords',
			'permission_callback' => __CLASS__ . '::rest_edit_user_callback',
		) );

		// Some hosts that run PHP in FastCGI mode won't be given the Authentication header.
		register_rest_route( '2fa/v1', '/test-basic-authorization-header/', array(
			'methods' => WP_REST_Server::READABLE . ', ' . WP_REST_Server::CREATABLE,
			'callback' => __CLASS__ . '::rest_test_basic_authorization_header',
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * REST API endpoint to list existing application passwords for a user.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param $data
	 *
	 * @return array
	 */
	public static function rest_list_application_passwords( $data ) {
		$application_passwords = self::get_user_application_passwords( $data['user_id'] );
		$with_slugs = array();

		if ( $application_passwords ) {
			foreach ( $application_passwords as $item ) {
				$item['slug'] = self::password_unique_slug( $item );
				unset( $item['raw'] );
				unset( $item['password'] );

				$item['created'] = date( get_option( 'date_format', 'r' ), $item['created'] );

				if ( empty( $item['last_used'] ) ) {
					$item['last_used'] =  '—';
				} else {
					$item['last_used'] = date( get_option( 'date_format', 'r' ), $item['last_used'] );
				}

				if ( empty( $item['last_ip'] ) ) {
					$item['last_ip'] =  '—';
				}

				$with_slugs[ $item['slug'] ] = $item;
			}
		}

		return $with_slugs;
	}

	/**
	 * REST API endpoint to add a new application password for a user.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param $data
	 *
	 * @return array
	 */
	public static function rest_add_application_password( $data ) {
		list( $new_password, $new_item ) = self::create_new_application_password( $data['user_id'], $data['name'] );

		// Some tidying before we return it.
		$new_item['slug']      = self::password_unique_slug( $new_item );
		$new_item['created']   = date( get_option( 'date_format', 'r' ), $new_item['created'] );
		$new_item['last_used'] = '—';
		$new_item['last_ip']   = '—';
		unset( $new_item['password'] );

		return array(
			'row'      => $new_item,
			'password' => self::chunk_password( $new_password )
		);
	}

	/**
	 * REST API endpoint to delete a given application password.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param $data
	 *
	 * @return bool
	 */
	public static function rest_delete_application_password( $data ) {
		return self::delete_application_password( $data['user_id'], $data['slug'] );
	}

	/**
	 * REST API endpoint to delete all of a user's application passwords.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param $data
	 *
	 * @return int The number of deleted passwords
	 */
	public static function rest_delete_all_application_passwords( $data ) {
		return self::delete_all_application_passwords( $data['user_id'] );
	}

	/**
	 * Whether or not the current user can edit the specified user.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param $data
	 *
	 * @return bool
	 */
	public static function rest_edit_user_callback( $data ) {
		return current_user_can( 'edit_user', $data['user_id'] );
	}

	/**
	 * Loosely Based on https://github.com/WP-API/Basic-Auth/blob/master/basic-auth.php
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param $input_user
	 *
	 * @return WP_User|bool
	 */
	public static function rest_api_auth_handler( $input_user ){
		// Don't authenticate twice.
		if ( ! empty( $input_user ) ) {
			return $input_user;
		}

		// Check that we're trying to authenticate
		if ( ! isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			return $input_user;
		}

		$user = self::authenticate( $input_user, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );

		if ( $user instanceof WP_User ) {
			return $user->ID;
		}

		// If it wasn't a user what got returned, just pass on what we had received originally.
		return $input_user;
	}

	/**
	 * Test whether PHP can see Basic Authorization headers passed to the web server.
	 *
	 * @return WP_Error|array
	 */
	public static function rest_test_basic_authorization_header() {
		$response = array();

		if ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			$response['PHP_AUTH_USER'] = $_SERVER['PHP_AUTH_USER'];
		}

		if ( isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$response['PHP_AUTH_PW'] = $_SERVER['PHP_AUTH_PW'];
		}

		if ( empty( $response ) ) {
			return new WP_Error( 'no-credentials', __( 'No HTTP Basic Authorization credentials were found submitted with this request.' ), array( 'status' => 404 ) );
		}

		return $response;
	}

	/**
	 * Some servers running in CGI or FastCGI mode don't pass the Authorization
	 * header on to WordPress.  If it's been rewritten to the `REMOTE_USER` header,
	 * fill in the proper $_SERVER variables instead.
	 */
	public static function fallback_populate_username_password() {
		// If we don't have anything to pull from, return early.
		if ( ! isset( $_SERVER['REMOTE_USER'] ) && ! isset( $_SERVER['REDIRECT_REMOTE_USER'] ) ) {
			return;
		}

		// If either PHP_AUTH key is already set, do nothing.
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) || isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			return;
		}

		// From our prior conditional, one of these must be set.
		$header = isset( $_SERVER['REMOTE_USER'] ) ? $_SERVER['REMOTE_USER'] : $_SERVER['REDIRECT_REMOTE_USER'];

		// Test to make sure the pattern matches expected.
		if ( ! preg_match( '%^Basic [a-z\d/+]*={0,2}$%i', $header ) ) {
			return;
		}

		// Removing `Basic ` the token would start six characters in.
		$token               = substr( $header, 6 );
		$userpass            = base64_decode( $token );
		list( $user, $pass ) = explode( ':', $userpass );

		// Now shove them in the proper keys where we're expecting later on.
		$_SERVER['PHP_AUTH_USER'] = $user;
		$_SERVER['PHP_AUTH_PW']   = $pass;

		return array( $user, $pass );
	}

	/**
	 * Check if the current request is an API request
	 * for which we should check the HTTP Auth headers.
	 *
	 * @return boolean
	 */
	public static function is_api_request() {
		// Process the authentication only after the APIs have been initialized.
		return ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) );
	}

	/**
	 * Filter the user to authenticate.
	 *
	 * @since ?.?.0
	 *
	 * @access public
	 * @static
	 *
	 * @param WP_User $input_user User to authenticate.
	 * @param string  $username   User login.
	 * @param string  $password   User password.
	 *
	 * @return mixed
	 */
	public static function authenticate( $input_user, $username, $password ) {
		if ( ! apply_filters( 'application_password_is_api_request', self::is_api_request() ) ) {
			return $input_user;
		}

		$user = get_user_by( 'login', $username );

		if ( ! $user && is_email( $username ) ) {
			$user = get_user_by( 'email', $username );
		}

		// If the login name is invalid, short circuit.
		if ( ! $user ) {
			return $input_user;
		}

		/*
		 * Strip out anything non-alphanumeric. This is so passwords can be used with
		 * or without spaces to indicate the groupings for readability.
		 *
		 * Generated application passwords are exclusively alphanumeric.
		 */
		$password = preg_replace( '/[^a-z\d]/i', '', $password );

		$hashed_passwords = get_user_meta( $user->ID, self::USERMETA_KEY_APPLICATION_PASSWORDS, true );

		// If there aren't any, there's nothing to return.  Avoid the foreach.
		if ( empty( $hashed_passwords ) ) {
			return $input_user;
		}

		foreach ( $hashed_passwords as $key => $item ) {
			if ( wp_check_password( $password, $item['password'], $user->ID ) ) {
				$item['last_used'] = time();
				$item['last_ip']   = $_SERVER['REMOTE_ADDR'];
				$hashed_passwords[ $key ] = $item;
				update_user_meta( $user->ID, self::USERMETA_KEY_APPLICATION_PASSWORDS, $hashed_passwords );

				do_action( 'application_password_did_authenticate', $user, $item );

				return $user;
			}
		}

		// By default, return what we've been passed.
		return $input_user;
	}

	/**
	 * Registers the hidden admin page to handle auth.
	 */
	public static function admin_menu() {
		add_submenu_page( null, __( 'Approve Application' ), null, 'exist', 'auth_app', array( __CLASS__, 'auth_app_page' ) );
	}

	/**
	 * Page for authorizing applications.
	 */
	public static function auth_app_page() {
		$app_name    = ! empty( $_GET['app_name'] ) ? $_GET['app_name'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
		$success_url = ! empty( $_GET['success_url'] ) ? $_GET['success_url'] : null; // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
		$reject_url  = ! empty( $_GET['reject_url'] ) ? $_GET['reject_url'] : $success_url; // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
		$user        = wp_get_current_user();

		wp_enqueue_script( 'auth-app' );
		wp_localize_script(
			'auth-app',
			'authApp',
			array(
				'root'       => esc_url_raw( rest_url() ),
				'namespace'  => '2fa/v1',
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'user_id'    => $user->ID,
				'user_login' => $user->user_login,
				'success'    => $success_url,
				'reject'     => $reject_url ? $reject_url : admin_url(),
				'strings'    => array(
					// translators: application, password.
					'new_pass' => esc_html_x( 'Your new password for %1$s is: %2$s', 'application, password' ),
				),
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Authorize Application' ); ?></h1>

			<div class="card js-auth-app-card">
				<h2 class="title"><?php esc_html_e( 'An application would like to connect to your account.' ); ?></h2>
				<?php if ( $app_name ) : ?>
					<p>
					<?php
					// translators: application name.
					printf( esc_html__( 'Would you like to give the application identifying itself as %1$s access to your account?  You should only do this if you trust the app in question.' ), '<strong>' . esc_html( $app_name ) . '</strong>' );
					?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'Would you like to give this application access to your account?  You should only do this if you trust the app in question.' ); ?></p>
				<?php endif; ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<?php wp_nonce_field( 'authorize_application_password' ); ?>
					<input type="hidden" name="action" value="authorize_application_password" />
					<input type="hidden" name="success_url" value="<?php echo esc_url( $success_url ); ?>" />
					<input type="hidden" name="reject_url" value="<?php echo esc_url( $reject_url ); ?>" />

					<label for="app_name"><?php esc_html_e( 'Application Title:' ); ?></label>
					<input type="text" id="app_name" name="app_name" value="<?php echo esc_attr( $app_name ); ?>" placeholder="<?php esc_attr_e( 'Name this connection&hellip;' ); ?>" required />

					<p><?php submit_button( __( 'Yes, I approve of this connection.' ), 'primary', 'approve', false ); ?>
						<br /><em>
						<?php
						if ( $success_url ) {
							printf(
								// translators: url.
								esc_html_x( 'You will be sent to %1$s', '%1$s is a url' ),
								'<strong><kbd>' . esc_html(
									add_query_arg(
										array(
											'username' => $user->user_login,
											'password' => '[------]',
										),
										$success_url
									)
								) . '</kbd></strong>'
							);
						} else {
							esc_html_e( 'You will be given a password to manually enter into the application in question.' );
						}
						?>
						</em>
					</p>

					<p><?php submit_button( __( 'No, I do not approve of this connection.' ), 'secondary', 'reject', false ); ?>
						<br /><em>
						<?php
						if ( $reject_url ) {
							printf(
								// translators: url.
								esc_html_x( 'You will be sent to %1$s', '%1$s is a url' ),
								'<strong><kbd>' . esc_html(
									add_query_arg(
										array(
											'success' => 'false',
										),
										$reject_url
									)
								) . '</kbd></strong>'
							);
						} else {
							esc_html_e( 'You will be returned to the WordPress Dashboard, and we will never speak of this again.' );
						}
						?>
						</em>
					</p>

				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle non-JS submissions via traditional posting.
	 */
	public static function authorize_application_password() {
		check_admin_referer( 'authorize_application_password' );

		$success_url = $_POST['success_url'];
		$reject_url  = $_POST['reject_url'];
		$app_name    = $_POST['app_name'];
		$redirect    = admin_url();

		if ( isset( $_POST['reject'] ) ) {
			if ( $reject_url ) {
				// Explicitly not using wp_safe_redirect b/c sends to arbitrary domain.
				$redirect = esc_url_raw( add_query_arg( 'success', 'false', $reject_url ) );
			}
		} elseif ( isset( $_POST['approve'] ) ) {
			list( $new_password, $new_item ) = self::create_new_application_password( get_current_user_id(), $app_name );
			if ( empty( $success_url ) ) {
				wp_die( '<h1>' . esc_html__( 'Your New Application Password:' ) . '</h1><h3><kbd>' . esc_html( self::chunk_password( $new_password ) ) . '</kbd></h3>' );
			}
			$redirect = add_query_arg(
				array(
					'username' => wp_get_current_user()->user_login,
					'password' => $new_password,
				),
				$success_url
			);
		}

		wp_redirect( $redirect ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;

	}

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