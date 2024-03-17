<?php
function ms_display_multisite_tool_box()
{
	if (get_network_option(null, 'ms_migration') || is_multisite()) {
		return;
	}

	if (current_user_can('setup_network')) :
?>
		<div class="card">
			<h2 class="title"><?php _e('Multisite Converter'); ?></h2>
			<p>
				<?php
				printf(
					__('<a href="%s">Turn this Site into a Multisite</a>'),
					wp_nonce_url( admin_url( 'admin-post.php?action=ms_initialize_migration' ) )
				);
				?>
			</p>
		</div>
<?php
	endif;
}
add_action('tool_box', 'ms_display_multisite_tool_box');

function wp_config_path()
{
	static $path;

	if (null === $path) {
		$path = false;

		if (getenv('WP_CONFIG_PATH') && file_exists(getenv('WP_CONFIG_PATH'))) {
			$path = getenv('WP_CONFIG_PATH');
		} elseif (file_exists(ABSPATH . 'wp-config.php')) {
			$path = ABSPATH . 'wp-config.php';
		} elseif (file_exists(dirname(ABSPATH) . '/wp-config.php') && !file_exists(dirname(ABSPATH) . '/wp-settings.php')) {
			$path = dirname(ABSPATH) . '/wp-config.php';
		}

		if ($path) {
			$path = realpath($path);
		}
	}

	return $path;
};

function modify_wp_config($content)
{
	$token           = "/* That's all, stop editing!";
	$config_contents = file_get_contents(wp_config_path());
	if (false === strpos($config_contents, $token)) {
		return false;
	}

	list($before, $after) = explode($token, $config_contents);

	$content = trim($content);

	file_put_contents(
		wp_config_path(),
		"{$before}\n\n{$content}\n\n{$token}{$after}"
	);
}
function ms_turn_into_multisite()
{
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// need to register the multisite tables manually for some reason
	foreach ($wpdb->tables('ms_global') as $table => $prefixed_table) {
		$wpdb->$table = $prefixed_table;
	}

	install_network();
	require_once ABSPATH . 'wp-admin/includes/network.php';
	require_once ABSPATH . 'wp-admin/includes/ms-admin-filters.php';
	require_once ABSPATH . 'wp-admin/includes/ms.php';
	require_once ABSPATH . 'wp-admin/includes/ms-deprecated.php';
	$base = parse_url(trailingslashit(get_option('home')), PHP_URL_PATH);
	$domain = get_clean_basedomain();

	$result = populate_network(
		get_current_blog_id(),
		$domain,
		get_bloginfo('admin_email'),
		get_bloginfo('name'),
		$base,
		false
	);

	$site_id = $wpdb->get_var("SELECT id FROM $wpdb->site");
	$site_id = (null === $site_id) ? 1 : (int) $site_id;

	// delete_site_option() cleans the alloptions cache to prevent dupe option
	delete_site_option('upload_space_check_disabled');
	update_site_option('upload_space_check_disabled', 1);

	if (!is_multisite()) {
		$ms_config        = <<<EOT
define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', false );
\$base = '{$base}';
define( 'DOMAIN_CURRENT_SITE', '{$domain}' );
define( 'PATH_CURRENT_SITE', '{$base}' );
define( 'SITE_ID_CURRENT_SITE', {$site_id} );
define( 'BLOG_ID_CURRENT_SITE', 1 );
EOT;
		is_writable(wp_config_path()) && modify_wp_config($ms_config);
	} else {
		/* Multisite constants are defined, therefore we already have an empty site_admins site meta.
			 *
			 * Code based on parts of delete_network_option. */
		$rows = $wpdb->get_results("SELECT meta_id, site_id FROM {$wpdb->sitemeta} WHERE meta_key = 'site_admins' AND meta_value = ''");

		foreach ($rows as $row) {
			wp_cache_delete("{$row->site_id}:site_admins", 'site-options');

			$wpdb->delete(
				$wpdb->sitemeta,
				['meta_id' => $row->meta_id]
			);
		}
	}
}
add_action('admin_post_ms_migration', 'ms_turn_into_multisite');


function ms_notice($args) {
	?>
	<div class="notice notice-info ">
			<h3><?php echo __('Make your installation Multisite-ready'); ?></h3>
			<p><?php echo __('Make your installation Multisite-ready to add translation or creates new sites. The following steps will happen during this process:');?></p>
			<ol>
				<li><?php echo __('Your currently active plugins will get deactivated.'); ?></li>
				<li><?php echo __('Your database will be prepared for Multisite.'); ?></li>
				<li><?php echo __('Your plugins will get a activated again.'); ?></li>
			</ol>
			<p>
				<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=ms_migration' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo __( 'Make Multisite-ready' ); ?></a>
			</p>
	</div>
	<?php
}

function ms_display_process()
{
	if (! get_network_option(null, 'ms_migration')) {
		return;
	}

	if( key_exists('errors', $_GET)) {
		wp_admin_notice(__('my_message'), [
			'type' => 'error'
		]);
	}


	ms_notice([]);
}


add_action('admin_notices', 'ms_display_process');

function ms_initialize_migration()
{
	update_network_option(null, 'ms_migration', '1');

	wp_safe_redirect( wp_nonce_url( admin_url( 'plugins.php' ) ) );

}

add_action('admin_post_ms_initialize_migration', 'ms_initialize_migration');
