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
					wp_nonce_url(admin_url('admin-post.php?action=ms_initialize_migration'))
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

		wp_redirect(wp_nonce_url(admin_url('plugins.php')));
		exit();
	}
}
add_action('admin_post_ms_migration', 'ms_turn_into_multisite');

function ms_display_process()
{
	if (!get_option('ms_migration')) {

		return;
	}

	if (key_exists('errors', $_GET)) {
		$errors = explode(',', (string) $_GET['errors']);

		foreach ($errors as $error) {
			if ('active_plugins' === $error) {
				wp_admin_notice(__('Making your installation Multisite ready failed, plugins are still enabled please try again.'), [
					'type' => 'error'
				]);
				continue;
			}
			if ('config_not_writable' === $error) {
				wp_admin_notice(__('Making your installation Multisite ready failed, it is not possible to write wp-config.php please try again.'), [
					'type' => 'error'
				]);
			}
		}
	}

	$active_plugins = get_option('active_plugins');

	$has_active_plugins = $active_plugins && count($active_plugins) > 0;


	?>
	<div class="notice notice-info ">
		<h3><?php echo __('Make your installation Multisite-ready'); ?></h3>
		<p><?php echo __('Make your installation Multisite-ready to add translation or creates new sites. The following steps will happen during this process:'); ?></p>
		<ol>
			<li><?php if ($has_active_plugins && !is_multisite()) :
						echo __('Deactivate your plugins. You can find them in the “Recently Active” tab in the plugins table.');
					else :
						echo __('All plugins deactivated.');
					endif; ?></li>
			<li>
				<?php echo __('Start converting to Multisite. Your database and wp-config.php will be made Multisite-ready.'); ?>
				<?php if (!$has_active_plugins &&  !is_multisite()) : ?>
					<a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=ms_migration')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>"><?php echo __('Make Multisite-ready'); ?></a>
				<?php endif; ?>
			</li>
			<li><?php echo __('Reactivate your plugins. You can find them in the “Recently Active” tab in the plugins table.'); ?> <?php if (is_multisite()) : ?>
					<p><?php echo __('Select all “Recently active” plugins and go to Bulk actions. Choose “Activate” and click “Apply”.'); ?></p>
					<a href="<?php echo wp_nonce_url(admin_url('plugins.php')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>"><?php echo __('Activate my plugins'); ?></a>
				<?php endif; ?>
			</li>
		</ol>
	</div>
<?php
}


add_action('admin_notices', 'ms_display_process');

function ms_initialize_migration()
{
	update_network_option(get_current_blog_id(), 'ms_migration', true);

	wp_safe_redirect(wp_nonce_url(admin_url('plugins.php')));
}

add_action('admin_post_ms_initialize_migration', 'ms_initialize_migration');
