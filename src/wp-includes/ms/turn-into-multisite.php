<?php
function ms_display_multisite_tool_box()
{
	if (current_user_can('setup_network')) :
?>
		<div class="card">
			<h2 class="title"><?php _e('Multisite Converter'); ?></h2>
			<p>
				<?php
				printf(
					__('Turn this Site into a Multisite'),
					'turn-into-multisite.php'
				);
				?>
			</p>
		</div>
<?php
	endif;
}
add_action('tool_box', 'ms_display_multisite_tool_box');

function ms_wp_config_path()
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

function ms_modify_wp_config($content, $writable)
{
	!is_super_admin() && wp_die(__('Sorry, you are not allowed allowed to do that'));
	$active_plugins = get_option('active_plugins');
	$errors = [];

	if (!empty($active_plugins)) $errors[] = 'active_plugins';
	if (!$writable) $errors[] = 'config_not_writable';

	if (!empty($errors)) {
		$errors = implode(',', $errors);
		$redirect = add_query_arg(['errors' => $errors], wp_get_referer());
		wp_redirect($redirect);
		exit();
	}
	$token           = "/* That's all, stop editing!";
	$config_contents = file_get_contents(ms_wp_config_path());
	if (false === strpos($config_contents, $token)) {
		return false;
	}

	list($before, $after) = explode($token, $config_contents);

	$content = trim($content);

	file_put_contents(
		ms_wp_config_path(),
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
define( 'DOMAIN_CURRENT_SITE', '{$domain}' );
define( 'PATH_CURRENT_SITE', '{$base}' );
define( 'SITE_ID_CURRENT_SITE', {$site_id} );
define( 'BLOG_ID_CURRENT_SITE', 1 );
EOT;
		$is_config_writable = is_writable(ms_wp_config_path());
		$is_config_writable && ms_modify_wp_config($ms_config, $is_config_writable);
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
