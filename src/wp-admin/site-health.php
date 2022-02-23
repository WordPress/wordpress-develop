<?php
/**
 * Tools Administration Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

wp_reset_vars( array( 'action' ) );

$tabs = array(
	/* translators: Tab heading for Site Health Status page. */
	''      => _x( 'Status', 'Site Health' ),
	/* translators: Tab heading for Site Health Info page. */
	'debug' => _x( 'Info', 'Site Health' ),
);

/**
 * An associated array of extra tabs for the Site Health navigation bar.
 *
 * Add a custom page to the Site Health screen, based on a tab slug and label.
 * The label you provide will also be used as part of the site title.
 *
 * @since 5.8.0
 *
 * @param array $tabs An associated array of tab slugs and their label.
 */
$tabs = apply_filters( 'site_health_navigation_tabs', $tabs );

$wrapper_classes = array(
	'wp-core-ui-tabs-wrapper',
	'hide-if-no-js',
	'tab-count-' . count( $tabs ),
);

$current_tab = ( isset( $_GET['tab'] ) ? $_GET['tab'] : '' );

$title = sprintf(
	// translators: %s: The currently displayed tab.
	__( 'Site Health - %s' ),
	( isset( $tabs[ $current_tab ] ) ? esc_html( $tabs[ $current_tab ] ) : esc_html( reset( $tabs ) ) )
);

if ( ! current_user_can( 'view_site_health_checks' ) ) {
	wp_die( __( 'Sorry, you are not allowed to access site health information.' ), '', 403 );
}

wp_enqueue_style( 'site-health' );
wp_enqueue_script( 'site-health' );

if ( ! class_exists( 'WP_Site_Health' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
}

if ( 'update_https' === $action ) {
	check_admin_referer( 'wp_update_https' );

	if ( ! current_user_can( 'update_https' ) ) {
		wp_die( __( 'Sorry, you are not allowed to update this site to HTTPS.' ), 403 );
	}

	if ( ! wp_is_https_supported() ) {
		wp_die( __( 'It looks like HTTPS is not supported for your website at this point.' ) );
	}

	$result = wp_update_urls_to_https();

	wp_redirect( add_query_arg( 'https_updated', (int) $result, wp_get_referer() ) );
	exit;
}

$health_check_site_status = WP_Site_Health::get_instance();

// Start by checking if this is a special request checking for the existence of certain filters.
$health_check_site_status->check_wp_version_check_exists();

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wp-core-ui-header">
	<div class="wp-core-ui-title-section">
		<h1>
			<?php _e( 'Site Health' ); ?>
		</h1>
	</div>

	<?php
	if ( isset( $_GET['https_updated'] ) ) {
		if ( $_GET['https_updated'] ) {
			?>
			<div id="message" class="notice notice-success is-dismissible"><p><?php _e( 'Site URLs switched to HTTPS.' ); ?></p></div>
			<?php
		} else {
			?>
			<div id="message" class="notice notice-error is-dismissible"><p><?php _e( 'Site URLs could not be switched to HTTPS.' ); ?></p></div>
			<?php
		}
	}
	?>

	<div class="wp-core-ui-title-section wp-core-ui-progress-wrapper loading hide-if-no-js">
		<div class="wp-core-ui-progress">
			<svg role="img" aria-hidden="true" focusable="false" width="100%" height="100%" viewBox="0 0 200 200" version="1.1" xmlns="http://www.w3.org/2000/svg">
				<circle r="90" cx="100" cy="100" fill="transparent" stroke-dasharray="565.48" stroke-dashoffset="0"></circle>
				<circle id="bar" r="90" cx="100" cy="100" fill="transparent" stroke-dasharray="565.48" stroke-dashoffset="0"></circle>
			</svg>
		</div>
		<div class="wp-core-ui-progress-label">
			<?php _e( 'Results are still loading&hellip;' ); ?>
		</div>
	</div>

	<nav class="<?php echo implode( ' ', $wrapper_classes ); ?>" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
		<?php
		$tabs_slice = $tabs;

		/*
		 * If there are more than 4 tabs, only output the first 3 inline,
		 * the remaining links will be added to a sub-navigation.
		 */
		if ( count( $tabs ) > 4 ) {
			$tabs_slice = array_slice( $tabs, 0, 3 );
		}

		foreach ( $tabs_slice as $slug => $label ) {
			printf(
				'<a href="%s" class="wp-core-ui-tab %s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'tab' => $slug,
						),
						admin_url( 'site-health.php' )
					)
				),
				( $current_tab === $slug ? 'active' : '' ),
				esc_html( $label )
			);
		}
		?>

		<?php if ( count( $tabs ) > 4 ) : ?>
			<button type="button" class="wp-core-ui-tab wp-core-ui-offscreen-nav-wrapper" aria-haspopup="true">
				<span class="dashicons dashicons-ellipsis"></span>
				<span class="screen-reader-text"><?php _e( 'Toggle extra menu items' ); ?></span>

				<div class="wp-core-ui-offscreen-nav">
					<?php
					// Remove the first few entries from the array as being already output.
					$tabs_slice = array_slice( $tabs, 3 );
					foreach ( $tabs_slice as $slug => $label ) {
						printf(
							'<a href="%s" class="wp-core-ui-tab %s">%s</a>',
							esc_url(
								add_query_arg(
									array(
										'tab' => $slug,
									),
									admin_url( 'site-health.php' )
								)
							),
							( isset( $_GET['tab'] ) && $_GET['tab'] === $slug ? 'active' : '' ),
							esc_html( $label )
						);
					}
					?>
				</div>
			</button>
		<?php endif; ?>
	</nav>
</div>

<hr class="wp-header-end">

<?php
if ( isset( $_GET['tab'] ) && ! empty( $_GET['tab'] ) ) {
	/**
	 * Output content of a custom Site Health tab.
	 *
	 * This action fires right after the Site Health header, and users are still subject to
	 * the capability checks for the Site Health page to view any custom tabs and their contents.
	 *
	 * @since 5.8.0
	 *
	 * @param string $tab The slug of the tab that was requested.
	 */
	do_action( 'site_health_tab_content', $_GET['tab'] );

	require_once ABSPATH . 'wp-admin/admin-footer.php';
	return;
} else {
	?>

<div class="notice notice-error hide-if-js">
	<p><?php _e( 'The Site Health check requires JavaScript.' ); ?></p>
</div>

<div class="wp-core-ui-body wp-core-ui-status-tab hide-if-no-js">
	<div class="site-status-all-clear hide">
		<p class="icon">
			<span class="dashicons dashicons-smiley" aria-hidden="true"></span>
		</p>

		<p class="encouragement">
			<?php _e( 'Great job!' ); ?>
		</p>

		<p>
			<?php _e( 'Everything is running smoothly here.' ); ?>
		</p>
	</div>

	<div class="site-status-has-issues">
		<h2>
			<?php _e( 'Site Health Status' ); ?>
		</h2>

		<p><?php _e( 'The site health check shows critical information about your WordPress configuration and items that require your attention.' ); ?></p>

		<div class="wp-core-ui-issues-wrapper" id="wp-core-ui-issues-critical">
			<h3 class="wp-core-ui-issue-count-title">
				<?php
					/* translators: %s: Number of critical issues found. */
					printf( _n( '%s critical issue', '%s critical issues', 0 ), '<span class="issue-count">0</span>' );
				?>
			</h3>

			<div id="wp-core-ui-site-status-critical" class="wp-core-ui-accordion issues"></div>
		</div>

		<div class="wp-core-ui-issues-wrapper" id="wp-core-ui-issues-recommended">
			<h3 class="wp-core-ui-issue-count-title">
				<?php
					/* translators: %s: Number of recommended improvements. */
					printf( _n( '%s recommended improvement', '%s recommended improvements', 0 ), '<span class="issue-count">0</span>' );
				?>
			</h3>

			<div id="wp-core-ui-site-status-recommended" class="wp-core-ui-accordion issues"></div>
		</div>
	</div>

	<div class="wp-core-ui-view-more">
		<button type="button" class="button wp-core-ui-view-passed" aria-expanded="false" aria-controls="wp-core-ui-issues-good">
			<?php _e( 'Passed tests' ); ?>
			<span class="icon"></span>
		</button>
	</div>

	<div class="wp-core-ui-issues-wrapper hidden" id="wp-core-ui-issues-good">
		<h3 class="wp-core-ui-issue-count-title">
			<?php
				/* translators: %s: Number of items with no issues. */
				printf( _n( '%s item with no issues detected', '%s items with no issues detected', 0 ), '<span class="issue-count">0</span>' );
			?>
		</h3>

		<div id="wp-core-ui-site-status-good" class="wp-core-ui-accordion issues"></div>
	</div>
</div>

<script id="tmpl-health-check-issue" type="text/template">
	<h4 class="wp-core-ui-accordion-heading">
		<button aria-expanded="false" class="wp-core-ui-accordion-trigger" aria-controls="wp-core-ui-accordion-block-{{ data.test }}" type="button">
			<span class="title">{{ data.label }}</span>
			<# if ( data.badge ) { #>
				<span class="badge {{ data.badge.color }}">{{ data.badge.label }}</span>
			<# } #>
			<span class="icon"></span>
		</button>
	</h4>
	<div id="wp-core-ui-accordion-block-{{ data.test }}" class="wp-core-ui-accordion-panel" hidden="hidden">
		{{{ data.description }}}
		<# if ( data.actions ) { #>
			<div class="actions">
				{{{ data.actions }}}
			</div>
		<# } #>
	</div>
</script>

	<?php
}
require_once ABSPATH . 'wp-admin/admin-footer.php';
