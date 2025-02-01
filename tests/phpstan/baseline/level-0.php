<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Undefined variable\\: \\$transient$#',
	'identifier' => 'variable.undefined',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Filesystem_SSH2\\:\\:touch\\(\\) should return bool but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_get_nav_menu_to_edit\\(\\) should return string\\|WP_Error but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Instantiated class WP_Press_This_Plugin not found\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/press-this.php',
];
$ignoreErrors[] = [
	'message' => '#^Path in include\\(\\) "/press\\-this/class\\-wp\\-press\\-this\\-plugin\\.php" is not a file or it does not exist\\.$#',
	'identifier' => 'include.fileNotFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/press-this.php',
];
$ignoreErrors[] = [
	'message' => '#^Array has 2 duplicate keys with value \'Code\' \\(\'Code\', \'Code\'\\)\\.$#',
	'identifier' => 'array.duplicateKey',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-editor.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Customize_Background_Image_Setting\\:\\:update\\(\\) should return bool but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-background-image-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Customize_Filter_Setting\\:\\:update\\(\\) should return bool but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-filter-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Customize_Header_Image_Setting\\:\\:update\\(\\) should return bool but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-header-image-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _wp_filter_build_unique_id\\(\\) should return string but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe usage of new static\\(\\)\\.$#',
	'identifier' => 'new.static',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine-css-rules-store.php',
];
$ignoreErrors[] = [
	'message' => '#^Undefined variable\\: \\$s$#',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/template.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
