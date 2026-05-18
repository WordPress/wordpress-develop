<?php
/**
 * Performance settings administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'Sorry, you are not allowed to manage options for this site.' ) );
}

global $wpdb;

$title       = __( 'Performance Settings' );
$parent_file = 'options-general.php';

wp_register_performance_optimization_settings();

if ( isset( $_POST['performance_action'] ) ) {
	check_admin_referer( 'performance-tools' );

	if ( 'clear_cache' === $_POST['performance_action'] ) {
		wp_delete_performance_cache();
		add_settings_error( 'performance', 'performance_cache_cleared', __( 'Performance caches cleared.' ), 'success' );
	} elseif ( 'database_cleanup' === $_POST['performance_action'] ) {
		$deleted  = 0;
		$deleted += (int) $wpdb->query( "DELETE FROM $wpdb->posts WHERE post_type = 'revision'" );
		$deleted += (int) $wpdb->query( "DELETE FROM $wpdb->comments WHERE comment_approved = 'spam'" );
		$deleted += (int) $wpdb->query( "DELETE FROM $wpdb->comments WHERE comment_approved = 'trash'" );
		$deleted += (int) $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = '_edit_lock' OR meta_key = '_edit_last'" );
		wp_clean_performance_page_cache();

		/* translators: %d: Number of database rows deleted. */
		add_settings_error( 'performance', 'performance_database_cleaned', sprintf( __( 'Database cleanup complete. %d rows were removed.' ), $deleted ), 'success' );
	}
}

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => '<p>' . __( 'Performance settings provide native controls for front-end caching, asset minification, critical CSS generation, lazy loading, image optimization, and database cleanup.' ) . '</p>',
	)
);

require_once ABSPATH . 'wp-admin/admin-header.php';

$settings = wp_get_performance_optimization_settings();

?>

<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<?php settings_errors( 'performance' ); ?>

<form action="options.php" method="post">
<?php settings_fields( 'performance' ); ?>

<h2 class="title"><?php _e( 'Optimization features' ); ?></h2>
<p><?php _e( 'Choose which native optimization features WordPress should apply.' ); ?></p>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php _e( 'Page caching' ); ?></th>
		<td>
			<label for="performance_optimization_page_cache">
				<input name="performance_optimization[page_cache]" type="checkbox" id="performance_optimization_page_cache" value="1" <?php checked( $settings['page_cache'] ); ?> />
				<?php _e( 'Cache anonymous front-end pages for faster repeat visits.' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php _e( 'CSS and JavaScript minification' ); ?></th>
		<td>
			<label for="performance_optimization_minify_assets">
				<input name="performance_optimization[minify_assets]" type="checkbox" id="performance_optimization_minify_assets" value="1" <?php checked( $settings['minify_assets'] ); ?> />
				<?php _e( 'Minify front-end HTML output and inline CSS and JavaScript.' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php _e( 'Critical CSS' ); ?></th>
		<td>
			<label for="performance_optimization_critical_css">
				<input name="performance_optimization[critical_css]" type="checkbox" id="performance_optimization_critical_css" value="1" <?php checked( $settings['critical_css'] ); ?> />
				<?php _e( 'Generate a compact critical CSS block from early inline styles.' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php _e( 'Lazy loading' ); ?></th>
		<td>
			<label for="performance_optimization_lazy_loading">
				<input name="performance_optimization[lazy_loading]" type="checkbox" id="performance_optimization_lazy_loading" value="1" <?php checked( $settings['lazy_loading'] ); ?> />
				<?php _e( 'Automatically lazy-load eligible images and iframes.' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php _e( 'Image optimization' ); ?></th>
		<td>
			<label for="performance_optimization_image_optimization">
				<input name="performance_optimization[image_optimization]" type="checkbox" id="performance_optimization_image_optimization" value="1" <?php checked( $settings['image_optimization'] ); ?> />
				<?php _e( 'Convert generated JPEG and PNG image sizes to AVIF or WebP when supported.' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php _e( 'Database cleanup tools' ); ?></th>
		<td>
			<label for="performance_optimization_database_cleanup">
				<input name="performance_optimization[database_cleanup]" type="checkbox" id="performance_optimization_database_cleanup" value="1" <?php checked( $settings['database_cleanup'] ); ?> />
				<?php _e( 'Show cleanup controls for revisions, spam, trash, and temporary editing metadata.' ); ?>
			</label>
		</td>
	</tr>
</table>

<?php submit_button(); ?>
</form>

<hr />

<h2 class="title"><?php _e( 'Maintenance tools' ); ?></h2>
<form method="post">
	<?php wp_nonce_field( 'performance-tools' ); ?>
	<input type="hidden" name="performance_action" value="clear_cache" />
	<?php submit_button( __( 'Clear performance caches' ), 'secondary', 'submit', false ); ?>
</form>

<?php if ( $settings['database_cleanup'] ) : ?>
	<form method="post" style="margin-top: 1em;">
		<?php wp_nonce_field( 'performance-tools' ); ?>
		<input type="hidden" name="performance_action" value="database_cleanup" />
		<?php submit_button( __( 'Clean up database' ), 'secondary', 'submit', false ); ?>
	</form>
<?php endif; ?>
</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
