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

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
