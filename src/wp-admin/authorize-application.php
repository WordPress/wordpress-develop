<?php
/**
 * Authorize Application Screen
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( isset( $_POST['action'] ) && 'authorize_application_password' === $_POST['action'] ) {
	check_admin_referer( 'authorize_application_password' );

	$success_url = $_POST['success_url'];
	$reject_url  = $_POST['reject_url'];
	$app_name    = $_POST['app_name'];
	$redirect    = admin_url();

	if ( isset( $_POST['reject'] ) ) {
		if ( $reject_url ) {
			$redirect = esc_url_raw( add_query_arg( 'success', 'false', $reject_url ) );
		}
	} elseif ( isset( $_POST['approve'] ) ) {
		list( $new_password ) = WP_Application_Passwords::create_new_application_password( get_current_user_id(), array( 'name' => $app_name ) );
		if ( empty( $success_url ) ) {
			wp_die( '<h1>' . __( 'Your New Application Password:' ) . '</h1><h3><kbd>' . esc_html( WP_Application_Passwords::chunk_password( $new_password ) ) . '</kbd></h3>' );
		}
		$redirect = add_query_arg(
			array(
				'username' => wp_get_current_user()->user_login,
				'password' => $new_password,
			),
			$success_url
		);
	}

	// Explicitly not using wp_safe_redirect b/c sends to arbitrary domain.
	wp_redirect( $redirect );
	exit;
}

$title = __( 'Authorize Application' );

$app_name    = ! empty( $_GET['app_name'] ) ? $_GET['app_name'] : '';
$success_url = ! empty( $_GET['success_url'] ) ? $_GET['success_url'] : null;
$reject_url  = ! empty( $_GET['reject_url'] ) ? $_GET['reject_url'] : $success_url;
$user        = wp_get_current_user();

wp_enqueue_script( 'auth-app' );
wp_localize_script(
	'auth-app',
	'authApp',
	array(
		'user_login' => $user->user_login,
		'success'    => $success_url,
		'reject'     => $reject_url ? $reject_url : admin_url(),
	)
);

require_once ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<div class="card js-auth-app-card">
		<h2 class="title"><?php __( 'An application would like to connect to your account.' ); ?></h2>
		<?php if ( $app_name ) : ?>
			<p>
			<?php
			/* translators: Application Name */
			printf( __( 'Would you like to give the application identifying itself as %s access to your account?  You should only do this if you trust the app in question.' ), '<strong>' . esc_html( $app_name ) . '</strong>' );
			?>
			</p>
		<?php else : ?>
			<p><?php _e( 'Would you like to give this application access to your account? You should only do this if you trust the app in question.' ); ?></p>
		<?php endif; ?>
		<form action="<?php echo esc_url( admin_url( 'authorize-application.php' ) ); ?>" method="post">
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
						/* translators: The URL the user is being redirected to. */
						__( 'You will be sent to %s' ),
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
					_e( 'You will be given a password to manually enter into the application in question.' );
				}
				?>
				</em>
			</p>

			<p><?php submit_button( __( 'No, I do not approve of this connection.' ), 'secondary', 'reject', false ); ?>
				<br /><em>
				<?php
				if ( $reject_url ) {
					printf(
						/* translators: The URL the user is being redirected to. */
						__( 'You will be sent to %s' ),
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
					_e( 'You will be returned to the WordPress Dashboard, and we will never speak of this again.' );
				}
				?>
				</em>
			</p>

		</form>
	</div>
</div>
<?php

require_once ABSPATH . 'wp-admin/admin-footer.php';
