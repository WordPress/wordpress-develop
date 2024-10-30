<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	// identifier: variable.undefined
	'message' => '#^Undefined variable\\: \\$transient$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Filesystem_SSH2\\:\\:touch\\(\\) should return bool but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Privacy_Requests_Table\\:\\:column_status\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-privacy-requests-table.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Site_Health_Auto_Updates\\:\\:test_accepts_dev_updates\\(\\) should return array\\|false but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health-auto-updates.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Site_Health_Auto_Updates\\:\\:test_accepts_minor_updates\\(\\) should return array but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health-auto-updates.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Site_Health_Auto_Updates\\:\\:test_constants\\(\\) should return array but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health-auto-updates.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Site_Health_Auto_Updates\\:\\:test_filters_automatic_updater_disabled\\(\\) should return array but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health-auto-updates.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Site_Health_Auto_Updates\\:\\:test_wp_version_check_attached\\(\\) should return array but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health-auto-updates.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Function wp_get_nav_menu_to_edit\\(\\) should return string\\|WP_Error but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: class.notFound
	'message' => '#^Instantiated class WP_Press_This_Plugin not found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/press-this.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Comment\\:\\:__isset\\(\\) should return bool but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Query\\:\\:__isset\\(\\) should return bool but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Recovery_Mode\\:\\:handle_error\\(\\) should return WP_Error\\|true but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-recovery-mode.php',
];
$ignoreErrors[] = [
	// identifier: function.notFound
	'message' => '#^Function wp_get_duotone_filter_svg not found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Customize_Background_Image_Setting\\:\\:update\\(\\) should return bool but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-background-image-setting.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Customize_Filter_Setting\\:\\:update\\(\\) should return bool but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-filter-setting.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Customize_Header_Image_Setting\\:\\:update\\(\\) should return bool but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-header-image-setting.php',
];
$ignoreErrors[] = [
	// identifier: function.notFound
	'message' => '#^Function wp_update_https_detection_errors not found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/https-detection.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Function _wp_filter_build_unique_id\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: unset.offset
	'message' => '#^Cannot unset offset 0 on array\\<string, string\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: new.static
	'message' => '#^Unsafe usage of new static\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine-css-rules-store.php',
];
$ignoreErrors[] = [
	// identifier: variable.undefined
	'message' => '#^Undefined variable\\: \\$s$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/template.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Nav_Menu_Widget\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-nav-menu-widget.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Archives\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-archives.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Block\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-block.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Calendar\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-calendar.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Categories\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-categories.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Custom_HTML\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-custom-html.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Links\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-links.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Media\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-media.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Meta\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-meta.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Pages\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-pages.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Recent_Comments\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-recent-comments.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Recent_Posts\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-recent-posts.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_RSS\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-rss.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Search\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-search.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Tag_Cloud\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-tag-cloud.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Widget_Text\\:\\:form\\(\\) should return string but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-text.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
