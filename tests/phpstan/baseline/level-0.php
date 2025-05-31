<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Filesystem_SSH2\\:\\:touch\\(\\) should return bool but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	// identifier: class.notFound
	'message' => '#^Instantiated class WP_Press_This_Plugin not found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/press-this.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Theme_JSON\\:\\:should_override_preset\\(\\) should return bool but return statement is missing\\.$#',
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
	// identifier: class.notFound
	'message' => '#^Class GdImage not found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	// identifier: function.nameCase
	'message' => '#^Call to function get_current_user_id\\(\\) with incorrect case\\: get_current_user_ID$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
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
