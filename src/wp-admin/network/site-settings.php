<?php
/**
 * Edit Site Settings Administration Screen
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.1.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_sites' ) ) {
	wp_die( __( 'Sorry, you are not allowed to edit this site.' ) );
}

get_current_screen()->add_help_tab( get_site_screen_help_tab_args() );
get_current_screen()->set_help_sidebar( get_site_screen_help_sidebar_content() );

$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;

if ( ! $id ) {
	wp_die( __( 'Invalid site ID.' ) );
}

$details = get_site( $id );
if ( ! $details ) {
	wp_die( __( 'The requested site does not exist.' ) );
}

if ( ! can_edit_network( $details->site_id ) ) {
	wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
}

$is_main_site = is_main_site( $id );

if ( isset( $_REQUEST['action'] ) && 'update-site' === $_REQUEST['action'] && is_array( $_POST['option'] ) ) {
	check_admin_referer( 'edit-site' );

	switch_to_blog( $id );

	$skip_options = array( 'allowedthemes' ); // Don't update these options since they are handled elsewhere in the form.
	foreach ( (array) $_POST['option'] as $key => $val ) {
		$key = wp_unslash( $key );
		$val = wp_unslash( $val );
		if ( 0 === $key || is_array( $val ) || in_array( $key, $skip_options, true ) ) {
			continue; // Avoids "0 is a protected WP option and may not be modified" error when editing blog options.
		}
		update_option( $key, $val );
	}

	/**
	 * Fires after the site options are updated.
	 *
	 * @since 3.0.0
	 * @since 4.4.0 Added `$id` parameter.
	 *
	 * @param int $id The ID of the site being updated.
	 */
	do_action( 'wpmu_update_blog_options', $id );

	restore_current_blog();
	wp_redirect(
		add_query_arg(
			array(
				'update' => 'updated',
				'id'     => $id,
			),
			'site-settings.php'
		)
	);
	exit;
}

if ( isset( $_GET['update'] ) ) {
	$messages = array();
	if ( 'updated' === $_GET['update'] ) {
		$messages[] = __( 'Site options updated.' );
	}
}

// Used in the HTML title tag.
/* translators: %s: Site title. */
$title = sprintf( __( 'Edit Site: %s' ), esc_html( $details->blogname ) );

$parent_file  = 'sites.php';
$submenu_file = 'sites.php';

require_once ABSPATH . 'wp-admin/admin-header.php';

?>

<div class="wrap">
<h1 id="edit-site"><?php echo $title; ?></h1>
<p class="edit-site-actions"><a href="<?php echo esc_url( get_home_url( $id, '/' ) ); ?>"><?php _e( 'Visit' ); ?></a> | <a href="<?php echo esc_url( get_admin_url( $id ) ); ?>"><?php _e( 'Dashboard' ); ?></a></p>

<?php

network_edit_site_nav(
	array(
		'blog_id'  => $id,
		'selected' => 'site-settings',
	)
);

if ( ! empty( $messages ) ) {
	$notice_args = array(
		'type'        => 'success',
		'dismissible' => true,
		'id'          => 'message',
	);

	foreach ( $messages as $msg ) {
		wp_admin_notice( $msg, $notice_args );
	}
}
?>
<form method="post" action="site-settings.php?action=update-site">
	<?php wp_nonce_field( 'edit-site' ); ?>
	<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
	<table class="form-table" role="presentation">
		<?php
		$blog_prefix = $wpdb->get_blog_prefix( $id );
		$options     = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i
				WHERE option_name NOT LIKE %s
				AND option_name NOT LIKE %s',
				"{$blog_prefix}options",
				$wpdb->esc_like( '_' ) . '%',
				'%' . $wpdb->esc_like( 'user_roles' )
			)
		);

		$ltr_fields = array(
			'siteurl',
			'home',
			'admin_email',
			'new_admin_email',
			'mailserver_url',
			'mailserver_login',
			'mailserver_pass',
			'ping_sites',
			'permalink_structure',
			'category_base',
			'tag_base',
			'upload_path',
			'upload_url_path',
		);

		foreach ( $options as $option ) {
			if ( 'default_role' === $option->option_name ) {
				$editblog_default_role = $option->option_value;
			}

			$value         = $option->option_value;
			$disabled      = false;
			$is_serialized = false;

			if ( is_serialized( $value ) ) {
				if ( is_serialized_string( $value ) ) {
					$value = maybe_unserialize( $value );
				} else {
					// Other serialized values (arrays, objects) are shown raw, read-only, inside a collapsible <details>.
					$disabled      = true;
					$is_serialized = true;
				}
			}

			$class  = 'all-options' . ( $disabled ? ' disabled' : '' );
			$class .= in_array( $option->option_name, $ltr_fields, true ) ? ' ltr' : '';
			$name   = esc_attr( $option->option_name );
			$label  = esc_html( $option->option_name );
			?>
			<tr class="form-field">
				<th scope="row"><label for="<?php echo $name; ?>" class="code"><?php echo $label; ?></label></th>
				<?php if ( $is_serialized ) : ?>
				<td>
					<details class="<?php echo $class; ?>">
						<summary><?php esc_html_e( 'Serialized data' ); ?></summary>
						<textarea class="<?php echo $class; ?>" rows="5" cols="40" id="<?php echo $name; ?>" readonly="readonly"><?php echo esc_textarea( $value ); ?></textarea>
					</details>
				</td>
				<?php elseif ( str_contains( $value, "\n" ) ) : ?>
				<td><textarea class="<?php echo $class; ?>" rows="5" cols="40" name="option[<?php echo $name; ?>]" id="<?php echo $name; ?>"<?php disabled( $disabled ); ?>><?php echo esc_textarea( $value ); ?></textarea></td>
				<?php elseif ( $is_main_site && in_array( $option->option_name, array( 'siteurl', 'home' ), true ) ) : ?>
				<td><code><?php echo esc_html( $value ); ?></code></td>
				<?php else : ?>
				<td><input class="<?php echo $class; ?>" name="option[<?php echo $name; ?>]" type="text" id="<?php echo $name; ?>" value="<?php echo esc_attr( $value ); ?>" size="40" <?php disabled( $disabled ); ?> /></td>
				<?php endif; ?>
			</tr>
			<?php
		} // End foreach.

		/**
		 * Fires at the end of the Edit Site form, before the submit button.
		 *
		 * @since 3.0.0
		 *
		 * @param int $id Site ID.
		 */
		do_action( 'wpmueditblogaction', $id );
		?>
	</table>
	<?php submit_button(); ?>
</form>

</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
